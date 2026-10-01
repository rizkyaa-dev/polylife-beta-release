# Patch penggunaan token AI — 1 Oktober 2026

Patch ini menyelesaikan AI-T01, AI-T02, dan AI-T03 dari
[audit penggunaan token](ai-token-usage-audit-2026-10-01.md).

## Perilaku setelah patch

Sesi berisi jawaban lama tanpa run tidak lagi diberi status `complete` hanya
karena chat berikutnya memiliki usage lengkap. Token aktual yang tersedia tetap
dipertahankan, sementara status sesi menjadi `partial`. Jika belum ada pengukuran,
status tetap `unknown`. Pengukuran nol yang valid tetap dikenali sebagai aktual.
Pesan user, jawaban pending/gagal, dan data sesi lain tidak mengubah kelengkapan.

Edit pesan memperbarui penghitung dari setiap respons polling, termasuk respons
terminal gagal atau batal, sebelum penanganan error berjalan. Contoh regresi:
run edit memakai 220 token dan total sesi menjadi 330; layar menampilkan 330
tanpa perlu reload. Edit sukses tetap menavigasi ke workspace sesi yang sesuai.

Gemini membaca `thoughtsTokenCount`. Nilainya tersimpan sebagai reasoning pada
metadata step. Prompt 100, candidates 10, thoughts 5 menghasilkan total 115.
Total dari provider dipertahankan; fallback memasukkan thoughts satu kali dan
tetap berstatus parsial jika total provider tidak tersedia. Fixture lama yang
menggunakan field salah dan total tanpa thoughts telah diselaraskan.

## Struktur implementasi

- `AiTokenUsageSummary`: subquery terbatas mendeteksi jawaban assistant selesai
  tanpa run pada sesi yang sama. Pemeriksaan dan agregasi berjalan dalam satu
  pembacaan SQL, tanpa memuat koleksi riwayat atau membuat run sintetis. Pencarian
  relasi run memakai indeks `assistant_message_id` yang sudah tersedia.
- `workspace.js`: satu callback `onRunStatus` dipakai composer, pemulihan run,
  dan edit. Total sesi selalu diganti dengan nilai server; tidak dijumlahkan
  ulang di browser. Pembaruan sukses yang redundan telah dihapus.
- `LlmTokenUsageParser`: memakai nama field resmi, dengan validasi counter dan
  aturan agregasi reasoning yang sudah ada. Tidak ada alias untuk field typo.

Perubahan ini tidak membutuhkan migrasi atau dependensi baru. Backfill historis
belum diimplementasikan; token lama yang tidak pernah dicatat tidak menjadi
pengukuran aktual melalui patch ini. Metadata reasoning yang sudah hilang juga
tidak bisa dipulihkan tanpa payload sumber.

## Verifikasi

Tes regresi baru gagal pada kode sebelum patch dan lulus setelahnya. Cakupan
mencakup seluruh celah audit, measured zero, isolasi sesi, ringkasan yang tetap
read-only, persistence reasoning Gemini, total tanpa hitung ganda, dan alur edit
sukses/gagal/batal. Suite terarah: 46 tes PHP / 238 assertion lulus. Seluruh
101 tes JavaScript lulus. Hasil verifikasi final:

- Suite PHP lengkap: **613 passed, 5.201 assertion**.
- Suite JavaScript lengkap: **101 passed**.
- Pint pada enam file PHP yang disentuh patch: passed.
- `git diff --check`: passed.
- `npm.cmd run build`: passed; aset lokal sudah dibangun ulang.
- `php artisan queue:restart`: sinyal restart worker lokal berhasil dikirim.

Query ringkasan baru juga berhasil dijalankan pada MySQL lokal untuk sesi kosong;
pengujian perilaku memakai SQLite in-memory dan provider palsu. Tes frontend
menjalankan handler, observer, callback, dan wiring state dari source produksi
dengan DOM serta transport deterministik. Tidak ada request ke provider berbayar
atau pengujian browser end-to-end pada patch ini.

```powershell
php vendor/bin/pest --compact
node --test (Get-ChildItem -LiteralPath tests/Js -Filter '*.test.js' -File).FullName
npm.cmd run build
php artisan queue:restart
```
