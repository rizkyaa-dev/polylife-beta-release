# Laporan Pengembangan Aplikasi Polylife

## Bab 1. Pendahuluan
### 1.1 Latar Belakang
Polylife adalah aplikasi manajemen produktivitas mahasiswa yang menggabungkan pengelolaan keuangan pribadi, jadwal kuliah, tugas/kegiatan akademik, to-do list, catatan, serta pemantauan IPK/Nilai Mutu dalam satu workspace. Basis kode menggunakan Laravel 12 dengan pola MVC dan antarmuka berbasis Blade + Tailwind/Vite.

### 1.2 Tujuan
- Menyediakan dashboard terpadu yang menampilkan ringkasan jadwal hari ini, status to-do, ringkasan keuangan bulanan, dan reminder aktif.
- Menyederhanakan input data akademik (matkul, jadwal, tugas, kegiatan) termasuk impor batch matkul.
- Menawarkan pencatatan keuangan dengan statistik tahunan dan deteksi anomali pengeluaran.
- Memfasilitasi pencatatan IPK/IPS, target akademik, serta konfigurasi peta nilai mutu yang fleksibel.
- Mengirim pengingat (reminder) yang dapat terhubung ke to-do/tugas/jadwal, disertai dukungan push notification.

### 1.3 Ruang Lingkup
- Fitur yang tercakup: dashboard, manajemen keuangan & statistik, jadwal & matkul, tugas/kegiatan, to-do list, reminder (push), catatan (dengan sampah/restore), IPK/IPS & nilai mutu, guest/demo workspace.
- Tidak mencakup integrasi pembayaran, SSO eksternal, atau manajemen file selain yang disebutkan di kode.

### 1.4 Lingkungan & Teknologi
- Backend: PHP 8.2, Laravel Framework ^12 (composer.json), migrasi database MySQL/PostgreSQL kompatibel.
- Frontend: Blade, Tailwind CSS ^3.1, Vite ^7, axios untuk AJAX (package.json).
- Library pendukung: Livewire ^3.6 (tersedia), minishlink/web-push ^10.0 untuk Web Push, Symfony Mailer (bridge Mailtrap API), Pest untuk testing.
- Konfigurasi waktu: `config('app.dashboard_timezone')` default `Asia/Jakarta` dengan fallback env `APP_DASHBOARD_TIMEZONE`.

### 1.5 Sumber Data & Referensi Kode
- Rute utama: `routes/web.php` (prefix `/workspace` untuk pengguna terautentikasi, `/guest` untuk mode demo).
- Controller inti: `app/Http/Controllers/*` (Dashboard, Keuangan, KeuanganStatistik, Jadwal, Matkul, Kegiatan, Tugas, Catatan, Todolist, Reminder, Ipk, NilaiMutu, PushSubscription, Guest*).
- Model & relasi: `app/Models/*` (matkulIds pada Jadwal, scheduleEntries pada Matkul, dsb.).
- Skema basis data: `database/migrations/*.php` (keuangans, jadwals, kegiatans, tugas, catatans, todolists, reminders, ipks, ipk_courses, nilai_mutus, push_subscriptions, reminder_push_logs).
- Antarmuka: `resources/views/**` (dashboard, keuangan, jadwal, matkul, todolist, reminder, ipk, nilai-mutu, catatan, tugas, welcome).

### 1.6 Sistematika Penulisan
Bab 2 membahas perancangan, Bab 3 implementasi, Bab 4 pengujian & hasil, Bab 5 kesimpulan dan saran.

---

## Bab 2. Perancangan
### 2.1 Arsitektur Sistem
- Pola MVC Laravel: controller menangani request & validasi, model mengelola data, Blade/Tailwind untuk tampilan.
- Autentikasi & middleware: grup `/workspace` memakai `auth` + `prevent-back-history`; grup `/guest` bebas login dengan data contoh (GuestWorkspace).
- Data utama per pengguna melalui kolom `user_id` (keuangan, jadwal, matkul, tugas, catatan, todolist, ipk, nilai_mutu) dan proteksi akses eksplisit per controller.
- Web Push: penyimpanan subscription di `push_subscriptions` via `PushSubscriptionController`, log pengiriman di `reminder_push_logs`.

### 2.2 Perancangan Modul Fungsional
- **Dashboard** (`DashboardController@index`): menampilkan jadwal hari ini (deduplikasi kuliah akhir pekan), to-do prioritas (status/selesai <10 menit), ringkasan keuangan bulan (grafik pemasukan/pengeluaran harian), dan reminder aktif terdekat. Endpoint AJAX untuk refresh keuangan & reminder.
- **Keuangan & Statistik** (`KeuanganController`, `KeuanganStatistikController`): CRUD pemasukan/pengeluaran dengan kategori & tanggal; statistik tahunan (grafik pemasukan/pengeluaran/net, saldo kumulatif, top kategori, anomali pengeluaran > mean+1.5*std).
- **Jadwal, Matkul, Kegiatan, Tugas**: 
  - Jadwal (`JadwalController`): kalender bulanan, multi-matkul per jadwal via `matkul_id_list`, jenis agenda (kuliah/libur/UTS/UAS/lomba/lainnya), deduplikasi kuliah, pengecualian akhir pekan.
  - Matkul (`MatkulController`): CRUD + impor batch dari teks tabel (parse jadwal, normalisasi hari/jam/ruangan, warna label, catatan otomatis).
  - Kegiatan (`KegiatanController`): daftar kegiatan per jadwal, dengan tanggal_deadline & waktu.
  - Tugas (`TugasController`): CRUD tugas dengan deadline dan status_selesai.
- **To-do & Reminder**:
  - Todolist (`TodolistController`): CRUD, toggle status via AJAX (`todolist.toggle-status`) yang mengatur badge dan waktu tampil 10 menit untuk item selesai; opsional reminder per item.
  - Reminder (`ReminderController`): reminder ke target todolist/tugas/jadwal/kegiatan, dengan aktif/nonaktif dan validasi kepemilikan target.
  - Push Subscription (`PushSubscriptionController`): simpan/dapus endpoint dan key Web Push.
- **Catatan** (`CatatanController`): CRUD catatan harian dengan status_sampah, halaman sampah, restore, dan hapus permanen.
- **IPK & Nilai Mutu**:
  - IPK/IPS (`IpkController`, `StoreIpkRequest`, `UpdateIpkRequest`): penyimpanan IPS aktual per semester, perhitungan ipk_running kumulatif, mode target `ips` (default), validasi unik semester per user.
  - Nilai Mutu (`NilaiMutuController`): profil konversi nilai (grade A/B/plus-minus) dengan flag `is_active`, digunakan sebagai referensi grading.
  - IpkCourse (migrasi): snapshot matkul, SKS, grade, target grade/score, status planned/in_progress/completed (terlampir saat IPK dibuat/diupdate).
- **Guest Workspace** (`GuestDashboardController`, `GuestWorkspaceController`): rute demo read-only untuk jadwal, todolist, catatan, ipk, nilai mutu, keuangan, statistik (data contoh dari `App\Support\GuestWorkspace` dan storage).

### 2.3 Perancangan Basis Data (ringkasan kolom utama)
- `keuangans`: `jenis` (pemasukan/pengeluaran), `kategori`, `nominal` decimal(14,2), `deskripsi`, `tanggal`, indeks per user/jenis/tanggal.
- `matkuls`: kode unik per user, nama, kelas, dosen, semester, SKS, jadwal multipel (`hari;jam_mulai;jam_selesai;ruangan`), `warna_label`, `catatan`.
- `jadwals`: `matkul_id_list` (daftar id dipisah `;`), `jenis`, rentang `tanggal_mulai`-`tanggal_selesai`, `semester`, `catatan_tambahan`.
- `kegiatans`: relasi ke `jadwals`, `nama_kegiatan`, `lokasi`, `waktu` (time), `tanggal_deadline`.
- `tugas`: relasi ke user & opsional `matkul_id`, `nama_tugas`, `deskripsi`, `deadline` datetime, `status_selesai`.
- `todolists`: `nama_item`, `status`; relasi ke reminders.
- `reminders`: target ke todolist/tugas/jadwal/kegiatan, `waktu_reminder` datetime, `aktif`.
- `catatans`: `judul`, `isi`, `tanggal`, `status_sampah`.
- `ipks`: `target_mode` (ips/ipk), `semester`, `academic_year`, `ips_actual/ips_target/ipk_running/ipk_target`, `status` (planned/in_progress/final), `remarks`.
- `ipk_courses`: relasi ke ipk & matkul, `course_code/name`, `sks`, `grade_point/letter`, target score/grade, `is_retake`, `status`.
- `nilai_mutus`: profil kampus/prodi/kurikulum, `grades_plus_minus`, `grades_ab` (JSON), `is_active`.
- `push_subscriptions`: endpoint unik, kunci `p256dh`, `auth_token`, `content_encoding`, `user_agent`.
- `reminder_push_logs`: log milestone pengiriman per reminder/user.

### 2.4 Rancangan Antarmuka & Artefak (placeholder)
- Dashboard ringkasan jadwal/to-do/keuangan/reminder (ini screenshot dashboard).
- Kalender jadwal + detail kegiatan harian (ini screenshot halaman jadwal).
- Form & tabel keuangan (ini screenshot halaman keuangan).
- Statistik keuangan (grafik pemasukan/pengeluaran/net) (ini screenshot grafik keuangan statistik).
- Daftar matkul & impor batch (ini screenshot halaman matkul/batch import).
- To-do list dengan toggle status & badge reminder (ini screenshot halaman todolist).
- Reminder list & form target (ini screenshot halaman reminder).
- Catatan + halaman sampah (ini screenshot halaman catatan/sampah).
- IPK/IPS & profil nilai mutu (ini screenshot halaman ipk dan nilai mutu).

### 2.5 Keamanan, Validasi, dan Akses
- Middleware `auth` dan pengecekan `user_id` manual di tiap controller (mis. `KeuanganController::authorizeAccess`, `CatatanController::authorizeAccess`, `NilaiMutuController::authorizeAccess`).
- Validasi input menggunakan Form Request (Ipk) atau `$request->validate()` di controller dengan Rule khusus (unique kode matkul per user, required-if reminder, format waktu/jadwal).
- Guest mode terisolasi: rute `/guest/*` hanya baca dan memakai data dummy.
- CSRF & session memakai default Laravel; session table tersedia (`create_sessions_table`).

### 2.6 Alur Data Utama (tingkat tinggi)
- Dashboard: query jadwal hari ini -> deduplikasi kuliah -> gabung kegiatan harian -> query todolist dengan status & meta -> query keuangan bulan terpilih -> bangun dataset grafik -> ambil reminder aktif >= now -> format urgensi/badge.
- Keuangan Statistik: filter per tahun -> agregasi bulanan -> hitung net, saldo kumulatif, rata-rata, savings rate, burn rate -> deteksi anomali -> top kategori pemasukan/pengeluaran.
- Jadwal/Matkul: jadwal menyimpan `matkul_id_list`; Matkul menyediakan helper scheduleEntries untuk dipakai di kalender/dashboard; batch import mem-parse teks tabel akademik.
- Reminder/Todolist: toggle status mematikan reminder aktif jika selesai, menyediakan respon JSON untuk UI.
- IPK: setiap simpan/edit IPK menghitung ulang `ipk_running` seluruh record user.

---

## Bab 3. Implementasi
### 3.1 Setup Lingkungan & Dependensi
- Dependensi backend (composer.json): Laravel ^12, Livewire ^3.6, Volt, minishlink/web-push, dev tools (Breeze, Pail, Pint, Pest).
- Dependensi frontend (package.json): Tailwind ^3.1, Vite ^7, laravel-vite-plugin, axios, @tailwindcss/forms.
- Skrip: `composer run setup` (install, copy .env, key:generate, migrate, npm install, build); `composer run dev` (server, queue listener, log, Vite via concurrently); `npm run dev|build`.

### 3.2 Struktur Kode
- Rute & middleware: `routes/web.php` dengan prefix `/workspace` (auth) dan `/guest` (demo), alias `route('dashboard')` diarahkan ke `/workspace/dashboard`.
- Controller per domain di `app/Http/Controllers`. Model di `app/Models` dengan helper (Matkul scheduleEntries, Jadwal matkulIds).
- Views Blade: `resources/views/*` (dashboard, keuangan, jadwal, matkul, dsb) memakai komponen Tailwind. Layout utama `resources/views/layouts/app.blade.php` (tidak dirinci di sini).
- Testing: Pest configuration `tests/Pest.php`; contoh Feature test untuk IPK (lihat Bab 4).

### 3.3 Implementasi Fitur Per Modul (kode rujukan)
- **Autentikasi & Verifikasi Email** (`resources/views/livewire/pages/auth/register.blade.php`, `app/Http/Controllers/Auth/VerifyEmailController.php`):
  - Pendaftaran memicu event `Registered` dan pengiriman email verifikasi; bila pengiriman gagal ditangani dengan try/catch dan pesan fallback agar user dapat login lalu resend verifikasi.
  - Verifikasi email memakai signed URL (`verification.verify`) sehingga validasi bergantung pada host/protocol yang benar di environment.
- **Dashboard** (`app/Http/Controllers/DashboardController.php`): 
  - Resolusi bulan terpilih (`resolveMonthSelection`), opsi bulan dari data keuangan (`buildMonthOptions`).
  - Ringkasan keuangan & dataset grafik harian; to-do prioritas dengan penanda selesai <10 menit; reminder dengan badge urgensi berdasarkan sisa detik.
  - Endpoint AJAX `dashboard.keuangan.data` & `dashboard.reminders.data`.
- **Keuangan & Statistik** (`KeuanganController.php`, `KeuanganStatistikController.php`):
  - CRUD pemasukan/pengeluaran dengan validasi tipe & nominal.
  - Statistik tahunan: agregasi 12 bulan, net & saldo kumulatif, rata-rata, savings/burn rate, proyeksi akhir tahun, top kategori, dan deteksi anomali pengeluaran.
- **Jadwal & Matkul** (`JadwalController.php`, `MatkulController.php`):
  - Kalender bulanan, deduplikasi kuliah, pengecualian akhir pekan untuk jenis kuliah.
  - Matkul batch import: parsing data tabel (kode, nama, kelas, SKS, jadwal “Hari, HH:MM s.d HH:MM @ Ruangan”), normalisasi warna, catatan otomatis, updateOrCreate per kode+user.
  - CRUD jadwal dengan opsi buat matkul baru on-the-fly (`buildMatkulList`), konfirmasi hapus.
- **Kegiatan & Tugas** (`KegiatanController.php`, `TugasController.php`):
  - Kegiatan terkait jadwal, validasi kepemilikan jadwal, urut berdasar waktu.
  - Tugas CRUD dengan status_selesai boolean.
- **Todolist & Reminder & Push** (`TodolistController.php`, `ReminderController.php`, `PushSubscriptionController.php`):
  - Toggle status to-do via AJAX, badge meta, dan pengaturan visibility 10 menit untuk item selesai.
  - Reminder ke target todolist/tugas/jadwal/kegiatan, validasi target & ownership, parsing waktu dengan Carbon.
  - Penyimpanan Web Push subscription (endpoint, p256dh, auth_token, content_encoding, user_agent) dan endpoint delete.
- **Catatan** (`CatatanController.php`): status sampah, halaman trash, restore, dan force delete.
- **IPK & Nilai Mutu** (`IpkController.php`, `StoreIpkRequest.php`, `NilaiMutuController.php`):
  - Validasi semester unik per user, normalisasi tahun akademik, perhitungan ulang ipk_running setiap simpan/hapus.
  - Profil nilai mutu dengan dua mode tabel (grades_plus_minus / grades_ab), flag `is_active`.
- **Guest Workspace** (`GuestDashboardController.php`, `GuestWorkspaceController.php`): memakai data dummy dari `App\Support\GuestWorkspace` & `storage/app/guest/*` untuk menampilkan preview fitur.
- **Antarmuka** (`resources/views/**/*`): Blade + Tailwind, komponen dashboard menyertakan grouping jadwal, badge reminder, dropdown bulan, serta routing guest/non-guest. Gunakan placeholder screenshot di Bab 2.4.

### 3.4 Integrasi Eksternal & Infrastruktur
- Web Push: library `minishlink/web-push`; endpoint subscribe/unsubscribe siap, memerlukan konfigurasi VAPID & worker/queue untuk pengiriman.
- Build tool: Vite + Tailwind; plugin `@tailwindcss/forms` untuk form styling.
- Queue/Logs: skrip dev menjalankan `php artisan queue:listen` dan `php artisan pail` (live tail) via concurrently.
- Mailer: Mailtrap Email API via `symfony/mailtrap-mailer` + `symfony/http-client`, dengan transport custom di `AppServiceProvider` (mendukung token/DSN).

### 3.5 Konfigurasi & Operasional
- Variabel penting: `APP_DASHBOARD_TIMEZONE`, kredensial database, kunci VAPID (untuk push), serta mailer (`MAIL_MAILER`, `MAILTRAP_API_TOKEN`/`MAILTRAP_DSN`, `MAIL_FROM_ADDRESS`, `MAIL_TIMEOUT`).
- Migrasi: jalankan `php artisan migrate` setelah konfigurasi DB.
- Serving: `php artisan serve` + `npm run dev` saat pengembangan; `npm run build` untuk produksi.
- Keamanan data: hampir semua tabel memakai cascading delete pada relasi ke users; matkul/jadwal/kegiatan memakai `nullOnDelete` bila relevan.
- Deploy produksi (Railway): pastikan `APP_URL` memakai HTTPS agar signed URL verifikasi valid, trust proxy aktif agar `X-Forwarded-Proto/Host` terbaca, dan pastikan Vite memakai build asset (hindari `public/hot` di production).

---

## Bab 4. Pengujian & Hasil
### 4.1 Pengujian Otomatis (Pest)
- `tests/Feature/Ipk/CreateIpkTest.php`: 
  - Membuat IPS target + menempel snapshot matkul terpilih; memastikan ipk & relasi tersimpan.
  - Menolak multiple IPK target mode `ipk` aktif pada user yang sama (assert session error).
- `tests/Feature/ExampleTest.php`: respons 200 untuk `/`.
- Command: `php artisan test` (tersedia di skrip composer `test`). **Belum dijalankan dalam laporan ini**; eksekusi disarankan setelah setup DB.

### 4.2 Rencana Pengujian Manual (berdasarkan fitur)
- Dashboard: cek filter bulan, refresh AJAX keuangan/reminder, deduplikasi kuliah akhir pekan.
- Keuangan: CRUD pemasukan/pengeluaran, validasi nominal & jenis, verifikasi statistik tahunan (grafik, anomali, top kategori).
- Jadwal & Matkul: buat jadwal dengan multi-matkul, pastikan kuliah tidak muncul di akhir pekan, uji impor batch matkul (format tabel), hapus & konfirmasi.
- Kegiatan & Tugas: tambah/ubah/hapus, keterkaitan dengan jadwal (kepemilikan).
- To-do & Reminder: toggle status via tombol/checkbox, cek badge & meta waktu, aktif/nonaktif reminder, pembuatan reminder ke semua target (todolist/tugas/jadwal/kegiatan).
- Catatan: pindah ke sampah, restore, hapus permanen.
- IPK & Nilai Mutu: simpan IPS per semester, hitung ipk_running, validasi semester unik, buat/edit profil nilai mutu.
- Push: simpan/unsubscribe endpoint via `push.subscribe` dan `push.unsubscribe` dengan payload webpush standar.
- Verifikasi email: uji link verifikasi dari email, pastikan tidak 403 (signed URL valid) dan redirect ke workspace.
- Asset produksi: uji navigasi menu di production untuk memastikan CSS tetap ter-load (tanpa `public/hot`).

### 4.3 Hasil Uji & Temuan
- Bukti uji otomatis: belum dieksekusi dalam penulisan laporan ini; jalankan `php artisan test` setelah environment siap.
- Risiko yang perlu diperhatikan: ketergantungan timezone pada konfigurasi, parsing impor batch matkul bergantung format teks, pengiriman push memerlukan VAPID & worker, serta validitas signed URL/verifikasi email bergantung pada `APP_URL` dan konfigurasi proxy HTTPS di production.

### 4.4 Bukti Tangkapan Layar (placeholder)
- (ini screenshot dashboard: jadwal/to-do/keuangan/reminder)
- (ini screenshot halaman keuangan & form input)
- (ini screenshot grafik statistik keuangan tahunan)
- (ini screenshot kalender jadwal + detail kegiatan)
- (ini screenshot batch import matkul)
- (ini screenshot to-do list + toggle status)
- (ini screenshot halaman reminder & form target)
- (ini screenshot IPK/IPS & tabel nilai mutu)
- (ini screenshot halaman catatan + sampah)

---

## Bab 5. Kesimpulan & Saran
### 5.1 Kesimpulan
- Aplikasi Polylife telah mengintegrasikan manajemen akademik, keuangan, tugas, to-do, catatan, serta IPK/Nilai Mutu dalam satu dashboard berbasis Laravel 12.
- Desain data memusatkan kepemilikan per user dan menyediakan jalur guest/demo tanpa login.
- Infrastruktur siap untuk Web Push dan statistik keuangan lanjutan, dengan dukungan impor batch matkul untuk mempercepat setup awal.

### 5.2 Saran Pengembangan
- Tambah cakupan pengujian: e2e untuk alur keuangan/jadwal/todo, dan unit test parsing batch matkul serta statistik keuangan.
- Aktifkan pipeline CI (lint, pint, pest) dan tambahkan seed data demo yang seragam antara guest & workspace.
- Implementasi service pengiriman push (queue job + konfigurasi VAPID) dan notifikasi in-app untuk reminder.
- Tambahkan ekspor/impor data (CSV/JSON) untuk keuangan, jadwal, catatan, serta backup profil nilai mutu.
- Perkuat observabilitas: logging agregasi keuangan anomali, metrik penggunaan reminder, dan audit trail perubahan data penting.
