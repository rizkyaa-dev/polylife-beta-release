# Rework UI AI — 1 Oktober 2026

UI AI memakai aksen lilac asli PolyLife `#8181FF`. Mode terang memakai
canvas lavender pucat, mode gelap memakai ungu arang yang tenang.
Permukaan utama tidak memakai putih atau hitam murni; tidak ada lapisan
kuning/sepia. Warna teks tetap memiliki kontras yang cukup untuk dibaca.

Ini adalah perbaikan desain dan aksesibilitas, bukan jaminan medis bahwa setiap
pengguna bebas lelah mata. Kecerahan layar, pencahayaan ruangan, perangkat, dan
preferensi pembaca tetap memengaruhi pengalaman. Pengaturan tema pengguna
yang sudah ada tetap menjadi sumber pilihan mode.

## Penyelarasan dengan halaman login

Penyempurnaan berikutnya mengambil referensi dari `layouts/guest.blade.php`,
`livewire/pages/auth/login.blade.php`, dan `.auth-input` dalam `app.css`:
aksen lilac, sudut kartu membulat, batas yang lebih tegas, serta bayangan
offset seperti kartu. Identitas tersebut diterapkan ke composer, tombol utama,
kartu saran, tombol percakapan baru, dan dialog pengaturan.

Intensitas diturunkan untuk halaman percakapan panjang: shadow offset 2–4px,
aksen lilac, dan permukaan kontrol solid. Canvas AI mengikuti referensi login
dengan grid lilac statis yang samar; tidak ada dekorasi besar di belakang teks.
Grid CSS berjarak 64px dengan garis 1px, opacity 2% pada light dan 4% pada
dark. Warna canvas `#FDFBFF` dan `#121020` serta grid dimiliki `theme.css`.
Grid menjadi background, sehingga tidak menambah elemen, menghalangi klik,
atau mengubah posisi konten. Sidebar dan switch tetap memakai tampilan yang sama.

Token `brand` menjadi sumber warna `#8181FF` untuk kedua mode. Tombol utama
memakai alias token tersebut dengan tinta gelap agar label tetap terbaca.
Aksen greeting dan ikon assistant juga memakai warna brand. Teks kecil,
tautan, dan fokus memakai variasi lilac yang disesuaikan kontrasnya melalui
token `accent`; seluruh warna tetap dimiliki `theme.css`.

Layout akun pengguna, susunan workspace, seluruh font/font-family dan identitas
teks PolyLife tetap memakai implementasi workspace yang ada. Tidak ada perubahan
Blade, JavaScript, route, atau preferensi tema dalam penyempurnaan ini.
Token `shadow-color`, `card-shadow`, `primary-edge`, dan `decoration` dimiliki
`theme.css`; komponen tetap memakai token semantik.

Validasi palette dan grid: **18 tes kontras light/dark lulus**, build produksi Vite
dan `git diff --check` lulus. Browser interaktif tetap tidak terhubung; referensi
login diperiksa dari source yang dipakai route `/login`, tanpa verifikasi
visual langsung.

## Struktur untuk pemeliharaan

`resources/css/ai-workspace.css` menjadi entry point CSS. Ia mengimpor modul
berikut, yang dibundel Vite menjadi aset aplikasi yang sama:

| Modul | Tanggung jawab |
| --- | --- |
| `ai/theme.css` | Seluruh palette light/dark, peran warna, lebar konten, radius |
| `ai/sidebar.css` | Navigasi, riwayat, menu riwayat, akun compact |
| `ai/layout.css` | Susunan halaman, header, welcome, tombol umum |
| `ai/composer.css` | Composer, token, penalaran, kartu saran |
| `ai/conversation.css` | Pesan, markdown, tabel, kode, status, proposal |
| `ai/overlays.css` | Pengaturan dan panel preview kode |
| `ai/responsive.css` | Aturan viewport, fokus, collapse, reduced motion |

Header, welcome, suggestions, token counter, thinking control, dan account dipisahkan
menjadi partial Blade. Suggestions berasal dari satu array berisi ikon,
label, dan prompt; partial dapat menerima array alternatif.
Semua hook JavaScript, kontrak token, form, enum, dan route tetap dipakai.
Tidak ada framework, dependency, state tema, atau migrasi tambahan.

## Keputusan visual dan interaksi

- Identitas PolyLife dan switch mode memakai komponen sidebar workspace yang
  sama, termasuk font 24px/800, subtitle Workspace, tinggi brand 48px, dan jarak
  bawah 32px pada desktop. Tidak ada override brand khusus AI. Brand tidak
  menyusut di kedua mode; ukuran dan aturan switch yang sama berlaku juga pada
  sidebar collapsed. Hanya indikator mode aktif mengikuti halaman yang dibuka.
- Header mengikuti referensi desktop: nama 18px/600 di kiri dan persona
  13px di bawahnya, pengaturan di kanan, tanpa emblem atau garis pembatas.
  Lebar penuh, padding desktop kiri 48px, kanan 40px, atas 24px, bawah 16px. Dua baris
  mengikuti line box brand workspace (32px dan 16px), dengan baseline nama
  mengikuti PolyLife melalui wrapper 24px dan label 18px. Baseline persona
  mengikuti subtitle Workspace. Tinggi normal desktop 88px; target tombol tetap
  44px. Pada layar kecil padding menjadi 20px horizontal dan 16px vertikal.
  Persona tetap terlihat pada mobile,
  tombol menu memakai label aksesibilitas, dan nama panjang dapat membungkus.
  Header dimiliki partial terpisah; area percakapan memakai gutter 24px dan
  lebar baca 48rem.
- Composer menjadi pusat interaksi melalui permukaan dan batas input yang
  jelas. Label tersedia untuk pembaca layar, petunjuk keyboard tersedia lewat
  tooltip dan deskripsi aksesibilitas, dan input
  berukuran 16px dengan line height yang lebih lega.
  Padding vertikal composer 8px dan minimum textarea 32px menjaga tinggi kosong
  sekitar 96px, dengan target kontrol tetap 44px. Textarea tetap bertambah
  tinggi mengikuti isi; jarak catatan ke composer 12px.
- Welcome hanya menampilkan sapaan dan pertanyaan utama, tanpa ikon bintang.
  Sapaan, composer, dan pintasan memakai lebar serta tepi kiri yang sama;
  kelompoknya dipusatkan secara vertikal tanpa offset desktop tambahan.
  Jarak sapaan ke composer 32px, atau 20px pada viewport pendek.
  Tiga pintasan kegiatan,
  pengeluaran, dan coding memakai ikon serta label singkat, tanpa deskripsi
  tambahan. Tombol membungkus sesuai lebar layar dengan target minimum 44px.
- Placeholder composer dan catatan pemeriksaan jawaban dipersingkat. Label
  token yang berulang dihapus; ikon, angka akumulasi, dan panel detail tetap ada.
  Catatan jawaban hanya terlihat ketika `has-messages` aktif: pada percakapan
  tersimpan atau setelah pesan pertama dikirim. Layar percakapan baru tidak
  menampilkannya; tidak ada state tambahan atau handler JavaScript baru.
- Akun sidebar menyatukan avatar, nama, email, dan kontrol keluar. Kontrol tema,
  pengumuman, dan collapse tetap memakai mekanisme aplikasi yang sudah ada.
- Pill memakai ikon token alih-alih simbol uang. Angka tetap akumulasi sesi;
  detail pengiriman terakhir dan estimasi draft tetap terpisah.
- Penalaran memakai label Indonesia, dengan nilai Off/Low/High/Max dari backend
  yang sama. Panel memiliki teks bantuan yang terlihat.
- Pilihan gaya bicara memakai komponen `ai.square-radio`: indikator kotak 20px
  seperti To-Do, sudut 6px, isian lilac, dan centang putih. Input tetap radio
  native dengan satu pilihan aktif; label kartu serta navigasi keyboard tetap
  bekerja. Fokus keyboard ditampilkan pada kartu, dan centang diuji minimum 3:1.
- Kode mengikuti palette halaman di kedua mode. Isi dokumen dalam iframe
  preview tetap memakai desain artefaknya sendiri dan sandbox yang sama.
- Scroll riwayat tetap terpisah dari tombol/pencarian. Kontrol composer dapat
  membungkus, termasuk angka token panjang; panel detail mengikuti batas
  composer. Viewport pendek menggunakan panel fixed yang dapat digulir.
- Alignment welcome memakai `safe center` sehingga overflow bergerak ke awal
  yang dapat dijangkau. Browser lama mendapat fallback `center` dan aturan
  viewport pendek yang eksplisit.
- Titik proses tampil statis. Preferensi reduced motion menonaktifkan animasi
  dan transisi pada seluruh halaman AI, termasuk menu yang berada di body.

Token `line` khusus pembatas dekoratif. Batas input menggunakan `control-line`
yang lebih terlihat; fokus menggunakan `accent`. Token tema juga berlaku pada
menu riwayat yang dipindahkan JavaScript ke body. Override shell dan footer
berlaku pada sidebar AI untuk mengatasi specificity layout bersama.

## Kontras dan verifikasi

Tes mengikuti pewarisan mode dan alias token CSS, memakai nilai warna sRGB
aktual dari `theme.css`: teks utama, teks bantu,
placeholder, aksen, status, dan label tombol minimum 4.5:1; fokus serta batas
input minimum 3:1. Greeting berukuran besar dan ikon assistant memakai brand
dengan minimum 3:1. Pembatas dekoratif tidak dinilai seperti batas input.
Pemeriksaan grid menghitung warna hasil compositing pada garis dan persilangannya,
agar kontras terhadap canvas berpola juga terukur.
Ambang mengacu pada [WCAG contrast minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html)
dan [non-text contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).
Ini tidak menyatakan sertifikasi WCAG seluruh aplikasi atau hasil medis.

- Validasi awal rework: **131 tes JavaScript lulus**, termasuk 14 tes kontras.
- Penyelarasan lilac: **133 tes JavaScript lulus**, termasuk **16 tes kontras
  palette light/dark** dengan dua pemeriksaan tambahan untuk brand.
- **48 tes PHP lulus (291 assertions)**: workspace AI, token accounting,
  navigation acceleration, profil, preferensi tema/logout, dan pengumuman.
- Pint, `git diff --check`, parsing CSS, serta build produksi Vite lulus.
- Browser interaktif tidak tersedia dalam sesi ini. Belum ada pemeriksaan
  visual langsung pada desktop/mobile atau keyboard perangkat fisik; verifikasi
  UI memakai render Blade, analisis CSS, dan tes handler yang sudah ada.

Untuk mengubah palette, edit token semantik di `theme.css` dan jalankan
`node --test tests/Js/ai-theme-contrast.test.js`, lalu build aset. Jangan menambah
warna literal pada tiap komponen. Uji tampilan light/dark, sidebar collapsed,
layar kecil, konten panjang, zoom, dan panel detail saat browser tersedia.
