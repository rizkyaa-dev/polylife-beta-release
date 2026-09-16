# PolyLife AI Agent: CLI, Coverage, dan Benchmark

Dokumen ini mencatat kondisi AI workspace per 16 September 2026 setelah pengujian contract dan canary langsung menggunakan provider DeepSeek yang aktif.

## Menjalankan AI dari CLI

### Benchmark aman

```powershell
php artisan ai:benchmark
php artisan ai:benchmark --case=schedule.tomorrow
php artisan ai:benchmark --case=workspace.overview
```

Benchmark membuat user dan data fixture di dalam transaksi database. Setiap skenario memakai savepoint terpisah dan seluruh data selalu di-rollback. Command ini tetap memakai provider asli sehingga membutuhkan API key, jaringan, dan kuota.

### Mengirim prompt untuk user nyata

```powershell
php artisan ai:chat 42 "Besok saya ada jadwal apa?"
php artisan ai:chat mahasiswa@example.com "Cari catatan zero trust"
php artisan ai:chat 42 "Lanjutkan rencana tadi" --session=123
```

`user` dapat berupa ID atau email user aktif. Command memakai orchestrator produksi dan menyimpan percakapan ke session user. Tool write hanya menghasilkan proposal bertanda tangan; command tidak mengeksekusi perubahan. User tetap harus mengonfirmasi proposal melalui UI AI workspace.

## Cakupan tool

| Domain | Read/advisory | Write dengan konfirmasi |
|---|---|---|
| Jadwal | `get_upcoming_schedule`, `search_schedules`, `check_schedule_conflicts`, `find_free_time_slots`, `preview_schedule_change` | `create_jadwal`, `update_jadwal`, `duplicate_schedule` |
| Kegiatan | melalui jadwal | `create_kegiatan`, `update_kegiatan` |
| Tugas dan to-do | `get_pending_tasks`, `search_tasks`, `suggest_task_breakdown` | `create_tugas`, `create_todolist`, `manage_tugas`, `manage_todolist`, `update_tugas` |
| Keuangan | `get_financial_summary`, `search_financial_transactions`, `suggest_budget`, `suggest_financial_anomalies` | `create_keuangan`, `update_keuangan`, `set_finance_budget` |
| Catatan | `search_catatan` | `create_catatan`, `update_catatan`, `archive_note`, `restore_note` |
| Mata kuliah | `get_courses`, `search_courses`, `get_course_overview`, `compare_course_workload` | `create_course`, `update_course`, `copy_course_to_semester` |
| Akademik | `get_academic_summary`, `get_grade_scale` | `record_academic_result` |
| Pengumuman | `get_announcements` | `mark_announcement_read` |
| Reminder | `get_reminders` | `create_reminder`, `update_reminder`, `snooze_reminder` |
| Workspace | `get_workspace_overview`, `export_workspace_summary`, `suggest_study_plan`, `prepare_weekly_review`, `detect_duplicate_records` | — |

Total registry: **51 tool**, terdiri dari **26 read/advisory** dan **25 write**. Seluruh write hanya membuat proposal bertanda tangan dan baru dijalankan setelah konfirmasi eksplisit. Update, snooze, archive, restore, copy, dan duplicate memeriksa ownership serta versi record lagi saat konfirmasi untuk mencegah stale write.

Operasi profil, autentikasi, afiliasi, administrasi, penghapusan permanen, bulk mutation, SQL/CRUD generik, serta konfigurasi skala nilai sengaja tidak diberikan kepada AI. Operasi tersebut sensitif, destruktif, atau membutuhkan alur otorisasi/form khusus. Agent dapat membaca skala nilai aktif dan mencatat IPS semester berikutnya, tetapi tidak dapat menulis ulang aturan nilai kampus. Ketiadaan tool ini merupakan batas keamanan, bukan fallback yang boleh diganti dengan tool domain lain.

## Matriks benchmark

### Dasar dan keamanan

- Membaca jadwal, tugas/to-do, keuangan, catatan, mata kuliah, akademik, pengumuman, reminder, dan skala nilai.
- Membuat proposal domain yang didukung dan mengonfirmasinya melalui action canonical.
- Menjawab pertanyaan umum tanpa tool dan membedakan bantuan belajar dari pembacaan data workspace.
- Menolak pembacaan tenant lain dan memperlakukan prompt injection tersimpan sebagai data.
- Mengisolasi setiap skenario menggunakan savepoint agar mutasi tidak mencemari skenario berikutnya.

### Search, update, dan advisory lanjutan

- Mencari transaksi, jadwal, serta tugas dengan ID canonical agar update berikutnya tidak menebak record.
- Mengubah jadwal, kegiatan, reminder, transaksi, mata kuliah, dan tugas memakai optimistic concurrency.
- Menandai pengumuman sebagai dibaca, menyalin mata kuliah ke semester lain, serta mengarsipkan/memulihkan catatan.
- Menduplikasi jadwal beserta kegiatan dengan pergeseran tanggal; reminder sengaja tidak ikut disalin.
- Membuat overview dan ekspor Markdown tanpa menyimpan file permanen.
- Menyarankan anggaran dan rencana belajar sebagai advisory read-only; AI tidak langsung membuat budget atau jadwal.

Suite benchmark sekarang memuat **64 skenario** dari prompt mudah sampai stress prompt ambigu dengan tujuh toolcall dan dua proposal independen. Full run provider nyata pada `thinking_effort=low` lulus **64/64 tanpa retry transport**. Cakupan sulit meliputi conditional no-mutation, read→advisory→write, hasil tool sebagai input tool berikutnya, multi-domain routing, prompt injection, tenant isolation, dan konfirmasi beberapa proposal.

## Masalah penting yang ditemukan dan dipatch

1. Tool domain yang hilang membuat model menolak permintaan atau memakai fallback yang salah. Registry sekarang menyediakan tool read, advisory, create, update, management, archive/restore, copy, dan duplicate yang spesifik.
2. Semua mutasi melewati proposal bertanda tangan dan action registry terpisah; ownership, validasi, serta optimistic concurrency diperiksa ulang saat konfirmasi.
3. Resolver target memakai urutan exact match, fuzzy unik, lalu menolak hasil ambigu. Search tool menyediakan ID canonical untuk follow-up yang presisi.
4. Timestamp reminder sebelumnya berisiko dikonversi timezone dua kali. Nilai database mentah sekarang ditafsirkan dalam timezone user.
5. Schema tanpa argumen sempat mengirim `properties: []`, yang ditolak DeepSeek. Field kosong sekarang dihilangkan.
6. Receipt dan acknowledgement tersedia bagi seluruh write tool. Konfirmasi ditampilkan sebagai kelanjutan bubble assistant tanpa panggilan provider tambahan.
7. Presenter aktivitas sebelumnya hanya mengenali tujuh tool. Seluruh tool sekarang memiliki label spesifik dan coverage otomatis.
8. Deteksi mutasi benchmark sebelumnya menebak dari prefix nama. Tool `record_*`, `mark_*`, `duplicate_*`, `copy_*`, `archive_*`, dan `restore_*` dapat dilaporkan terkonfirmasi padahal tidak dieksekusi. Runner sekarang membaca `isMutating()` langsung dari registry runtime.
9. Duplikasi jadwal menyalin kegiatan dengan pergeseran tanggal, tetapi sengaja tidak menyalin reminder agar tidak membuat notifikasi tak terduga.
10. Export hanya menghasilkan Markdown dalam respons; suggestion bersifat read-only dan tidak menyamarkan rekomendasi sebagai perubahan data.
11. Registry yang terus membesar kini memakai domain tool gating. Prompt yang jelas hanya menerima schema domain relevan; prompt ambigu tetap mendapat seluruh registry agar kapabilitas tidak hilang.
12. Tool mata kuliah mencakup pencarian canonical, overview tugas/jadwal, dan perbandingan beban relatif tanpa mengubah data akademik.
13. Fixture kegiatan benchmark memakai kolom `tanggal` yang bukan schema canonical sehingga proposal update selalu kehilangan tanggal. Fixture kini memakai `tanggal_deadline` dan status domain yang valid.
14. Kolom MySQL `TIME` mengembalikan `HH:MM:SS`, sedangkan validator proposal update menerima `HH:MM`. Update jadwal dan kegiatan sekarang menormalisasi waktu pada boundary tool; regression fixture secara eksplisit memakai format MySQL.
15. Tool-gating awal menganggap kata generik “overview” sebagai domain workspace dan tidak mengenali kode seperti `KPL401`. Akibatnya tool mata kuliah dikeluarkan dari request provider. Classifier sekarang mengenali pola kode mata kuliah dan hanya mengaktifkan overview workspace dari frasa yang spesifik.
16. Full benchmark dapat terkena cascade koneksi provider sesaat. Runner kini melakukan satu retry dengan backoff hanya untuk `AiProviderException` yang ditandai retryable, mencatat jumlah attempt, dan tidak mengulang kegagalan perilaku/tool. Full run final tidak membutuhkan retry.

## Verifikasi

- AI test suite: **106 passed, 635 assertions**.
- Seluruh Laravel test suite: **266 passed, 1463 assertions**.
- Full live provider benchmark (`thinking_effort=low`): **64/64 passed**, tanpa retry transport pada run final.
- Production frontend build: berhasil dengan Vite 7.3.6.
- `git diff --check`: bersih.

Peringatan build mengenai umur database Browserslist/baseline-browser-mapping bersifat maintenance dependency dan tidak menggagalkan build.
