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
aksen lilac dan sudut kartu membulat. Permukaan konten AI memakai satu keluarga
violet netral untuk menyatu dengan canvas, dengan bayangan lembut pada composer
dan dialog. Outline composer dan tombol utama 1px; pintasan tanpa bayangan.
Palet ini di-scope ke workspace AI, sidebar AI, dan menu riwayat.
Sidebar workspace tetap memakai permukaannya sendiri; switch memiliki modul
serta token mandiri agar warna, ukuran, dan posisinya konsisten di kedua mode.

Intensitas diturunkan untuk halaman percakapan panjang melalui outline yang
lebih ringan, aksen lilac, dan permukaan kontrol solid. Canvas AI mengikuti referensi login
dengan grid lilac statis yang samar; tidak ada dekorasi besar di belakang teks.
Grid CSS berjarak 64px dengan garis 1px, opacity 2% pada light dan 4% pada
dark. Warna canvas `#FDFBFF` dan `#121020` serta grid dimiliki `theme.css`.
Grid menjadi background, sehingga tidak menambah elemen, menghalangi klik,
atau mengubah posisi konten. Sidebar tetap solid tanpa grid.

Sidebar AI memakai canvas `#F1EDF7` / `#171323`, dengan kontrol dari keluarga
violet netral area chat. Riwayat hover dan aktif dibedakan melalui permukaan
tonal; pilihan aktif tetap memakai penanda lilac dan teks tebal. Pada light,
hover `#E8E1F2` dan aktif `#DED5F0` memberi pemisahan lebih jelas.
Pencarian serta tombol percakapan baru memakai outline 1px tanpa bayangan offset.
Fokus keyboard tetap eksplisit; tata letak akun dan tombol footer tetap sama.
Subtitle brand bersama memakai `brand-subtitle` yang lebih terbaca di kedua
mode, dengan font, ukuran, tracking, dan posisi yang tetap.

Token `brand` menjadi sumber warna `#8181FF` untuk kedua mode. Tombol utama
memakai alias token tersebut dengan tinta gelap agar label tetap terbaca.
Token `primary-shadow` dan `disabled-surface/ink/line` mengatur tombol secara
eksplisit. Disabled memakai opacity 1 dan bayangan kosong; hover hanya berlaku
pada tombol aktif. Pill token transparan saat idle, mengikuti warna form,
dan menampilkan permukaan tonal saat hover/fokus.
Aksen greeting dan ikon assistant juga memakai warna brand. Teks kecil,
tautan, dan fokus memakai variasi lilac yang disesuaikan kontrasnya melalui
token `accent`; seluruh warna tetap dimiliki `theme.css`.

Layout akun pengguna, susunan workspace, seluruh font/font-family dan identitas
teks PolyLife tetap memakai implementasi workspace yang ada. Perubahan Blade
memakai class warna subtitle semantik dan label `AI Assistance` pada mode AI.
Tidak ada perubahan JavaScript, route, atau preferensi tema.
Token `shadow-color`, `card-shadow`, `primary-edge`, dan `decoration` dimiliki
`theme.css`; komponen tetap memakai token semantik.

Validasi palette, sidebar, switch, menu, dan grid: **30 tes kontras light/dark lulus**,
**147 tes JavaScript lulus**, **15 tes PHP lulus (102 assertions)**, build produksi Vite
dan `git diff --check` lulus. Browser interaktif tetap tidak terhubung; referensi
login diperiksa dari source yang dipakai route `/login`, tanpa verifikasi
visual langsung.

## Struktur untuk pemeliharaan

Susunan akun juga diterapkan pada sidebar workspace melalui partial
`layouts/components/sidebar-account.blade.php`: avatar, nama dan email dua
baris, lalu Logout di kanan. `workspace-sidebar.css` hanya mengatur layout
footer serta alignment kontrol bawah. Class tampilan avatar, Logout, tema,
pengumuman, dan collapse tetap memakai implementasi workspace; palet AI tidak
diterapkan ke tombol workspace. Nama/email panjang dipotong dengan ellipsis,
dan hook pembaruan profil serta penyimpanan tema saat logout tetap tersedia.
Aturan collapsed yang sudah ada tetap berlaku.
Validasi perubahan ini: **31 tes PHP lulus (184 assertions)** untuk profil,
workspace AI, dan navigasi mode; build produksi serta pemeriksaan diff lulus.

`resources/css/ai-workspace.css` menjadi entry point CSS. Ia mengimpor modul
berikut, yang dibundel Vite menjadi aset aplikasi yang sama:

| Modul | Tanggung jawab |
| --- | --- |
| `ai/theme.css` | Seluruh palette light/dark, peran warna, lebar konten, radius |
| `workspace-mode-switch.css` | Palet dan geometri switch bersama, termasuk collapse dan fokus |
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
  sama, termasuk font 24px/800, subtitle `AI Assistance` pada AI / `Workspace`
  pada workspace, tinggi brand 48px, dan jarak
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
  sekitar 94px. Padding textarea atas 5.6px dan bawah 0 menggeser teks sekitar
  4px ke bawah tanpa menambah tinggi satu baris; target kontrol tetap 44px.
  Textarea tetap bertambah
  tinggi mengikuti isi; catatan chat memakai jarak atas dan bawah 8px,
  line-height 1.4, serta alignment tengah. Jarak bawah tetap menghormati safe area.
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
- Gap antar pesan 12px, dikurangi menjadi 8px khusus user ke asisten (termasuk
  indikator pemrosesan). Jawaban asisten ke pertanyaan berikutnya memakai
  jarak 40px agar pergantian giliran lebih jelas; jarak panel proses ke jawaban
  tetap 4px. Pada perangkat
  dengan hover dan pointer presisi, toolbar Edit tanpa navigasi versi berada
  di samping bubble, sehingga baris tersembunyinya tidak menyisakan ruang 44px.
  Bubble menyisakan lebar untuk target tombol; hover dan fokus keyboard tetap
  menampilkannya. Navigasi versi dan kontrol layar sentuh tetap dalam alur
  dokumen, dengan target tombol 44px. Line-height isi jawaban tetap 1.8.
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
aktual dari `theme.css` dan `workspace-mode-switch.css`: teks utama, teks bantu,
placeholder, aksen, status, dan label tombol minimum 4.5:1; fokus serta batas
input minimum 3:1. Greeting berukuran besar dan ikon assistant memakai brand
dengan minimum 3:1. Pembatas dekoratif tidak dinilai seperti batas input.
Pemeriksaan grid menghitung warna hasil compositing pada garis dan persilangannya,
agar kontras terhadap canvas berpola juga terukur.
Palet sidebar, menu yang dipindah ke body, dan switch dibaca dari selector
masing-masing; tes sidebar tidak memakai warna foreground area chat sebagai
pengganti. Subtitle bersama diuji pada permukaan AI dan workspace.
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
