# Perbaikan scroll percakapan AI — 1 Oktober 2026

Saat mengirim pesan, kode sebelumnya langsung menggeser viewport setelah
gelembung user ditambahkan. Indikator proses baru ditambahkan sesudahnya, tanpa
scroll berikutnya sampai request selesai. Indikator dapat berada di luar area
terlihat. Scroll unconditional ketika jawaban selesai juga memindahkan pengguna
yang sudah naik untuk membaca riwayat.

## Implementasi

`resources/js/ai/conversation-scroller.js` memiliki tanggung jawab tunggal:
menjaga posisi percakapan. Pengiriman pesan dan pemulihan run mengaktifkan
scroll ke bagian terbaru setelah seluruh perubahan DOM dan composer selesai.
Pekerjaan scroll digabung ke satu `requestAnimationFrame` sehingga memakai
ukuran layout terbaru.

Satu `ResizeObserver` memantau list dan viewport untuk perubahan tinggi karena
rumus, media, composer, dan ukuran layar. Scroll tetap mengikuti bagian bawah
selama pengguna berada di sana. Jika pengguna naik lebih dari 64 piksel,
pembaruan berikutnya menjaga posisi baca. Kembali ke bawah atau mengirim pesan
baru mengaktifkan auto-scroll kembali.

Memuat riwayat sebelumnya memakai `preservePosition()`: selisih tinggi konten
ditambahkan ke posisi lama, termasuk ketika tombol pemuatan dihapus. Observer
dan frame dibersihkan saat meninggalkan halaman; halaman yang masuk browser
history cache mempertahankan controller untuk pemulihan berikutnya. Tanpa
`ResizeObserver`, pemanggilan scroll eksplisit dan penyelesaian render math
tetap bekerja.

Perubahan dipasang pada `workspace.js`. Tidak ada perubahan backend, provider,
database, atau dependency.

## Verifikasi

- Dua tes handler composer asli gagal sebelum wiring patch: indikator tertinggal
  di bawah viewport dan jawaban menarik pengguna dari posisi baca ke bawah.
- Sepuluh tes scroll lulus setelah patch, mencakup perubahan layout tertunda,
  pembatalan frame ketika pengguna naik, pemulihan auto-scroll, prepend riwayat,
  cleanup, browser history cache, dan fallback tanpa observer.
- Seluruh **111 tes JavaScript lulus**.
- `npm.cmd run build`: passed; aset lokal dibangun ulang.
- `git diff --check`: passed.

Tes menjalankan controller dan handler workspace dari source dengan geometri,
observer, frame, serta transport deterministik. Browser terhubung tidak tersedia
di sesi ini, sehingga belum ada verifikasi visual pada browser nyata. Tidak ada
request ke provider AI untuk pengujian.

## Perbaikan tambahan: scrollbar sidebar riwayat

Scrollbar sidebar sebelumnya dimiliki seluruh `.ai-sidebar-nav`, termasuk
tombol percakapan baru dan kolom pencarian. Pada browser dengan scrollbar
overlay, track terlihat menimpa sisi kanan kontrol. Aturan navigasi mobile
pada layout bersama juga mengaktifkan `overflow-y: auto` pada nav.

Scroll kini dimiliki `.ai-history-scroll` yang hanya membungkus daftar dan
pesan kosong/hasil pencarian. Rantai flex menggunakan `min-height: 0` agar
daftar dapat menyusut sesuai ruang sidebar. Tombol, pencarian, judul, dan status
aksi tetap di luar area scroll. `scrollbar-gutter: stable` menyediakan ruang
scrollbar pada browser yang memakai track klasik; padding menjaga ruang untuk
track overlay dan focus ring. Region memiliki nama aksesibel, fokus keyboard,
serta `overscroll-behavior: contain`.

Selector overflow nav memakai specificity yang mengalahkan aturan nav mobile
tanpa mengubah navigasi workspace lainnya. Handler menu riwayat sudah menangkap
event scroll dari descendant, sehingga menu tetap ditutup ketika daftar
bergerak tanpa perubahan JavaScript.

Verifikasi tambahan: **11 tes AiWorkspaceViewTest lulus (68 assertions)**,
build produksi Vite dan `git diff --check` lulus. Aset lokal dibangun ulang.
Browser terhubung tetap tidak tersedia; tampilan belum diverifikasi secara
visual pada browser nyata.
