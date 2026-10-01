# Perbaikan akumulasi token pada pill AI — 2026-10-01

## Penyebab

Backend sudah menyediakan `session_tokens` yang berisi akumulasi pemakaian
per sesi. Namun, Blade dan pembaruan JavaScript memakai token run terakhir
untuk angka input/output pada pill. Saat mengetik, angka input juga diganti
dengan estimasi teks draft. Akumulasi hanya terlihat pada footer popover.

## Perubahan

- Pill menampilkan input/output dari `session_tokens`, termasuk setelah reload.
- Popover berlabel “Pengiriman Terakhir” mempertahankan rincian run terakhir.
- Estimasi teks draft tampil pada baris terpisah di popover dan disinkronkan
  ketika draft berubah melalui pengetikan, submit, pemulihan, atau saran pesan.
- Status `partial` dan `unknown` mengikuti cakupan pengukuran sesi, sekalipun
  pengiriman terakhir memiliki pengukuran lengkap.
- Pembaruan menggunakan total sesi dari server. Pengamatan status yang sama
  berulang kali tidak menjumlahkan token dua kali. Pembaruan yang hanya berisi
  run terakhir tidak menimpa akumulasi sesi yang sudah diketahui.

Perbaikan ini tidak mengubah pencatatan backend dan tidak memerlukan migrasi.
Chat lama tanpa pengukuran tetap menggunakan status cakupan yang sudah ada;
patch ini tidak membuat estimasi historis menjadi penggunaan tercatat.

## Verifikasi

- Seluruh tes JavaScript: **117 lulus**.
- Tes PHP pencatatan dan ringkasan token: **14 lulus, 96 assertions**,
  termasuk render halaman setelah dua pengiriman pada sesi yang sama.
- Tes regresi mengeksekusi handler JavaScript aktual untuk akumulasi sesi,
  rincian pengiriman terakhir, draft, pembaruan berulang, status partial/unknown,
  serta pembaruan session-only dan run-only.
- Pemeriksaan Pint, sintaks JavaScript, `git diff --check`, dan build produksi
  Vite lulus. Aset lokal sudah dibangun ulang.
- Browser interaktif tidak tersedia dalam sesi ini; verifikasi UI menggunakan
  render Blade dan handler DOM terisolasi, bukan pemeriksaan visual langsung.
