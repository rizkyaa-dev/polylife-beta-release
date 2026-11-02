# PolyLife

<p align="center">
  <strong>Platform Terpadu Manajemen Akademik, Produktivitas, dan Keuangan Mahasiswa</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS">
  <img src="https://img.shields.io/badge/Flutter-3.x-02569B?style=for-the-badge&logo=flutter&logoColor=white" alt="Flutter">
  <img src="https://img.shields.io/badge/Pest_PHP-Tested-48BB78?style=for-the-badge&logo=pest&logoColor=white" alt="Pest Tested">
  <img src="https://img.shields.io/badge/License-Source_Available_Non--Commercial-red?style=for-the-badge" alt="Source Available Non-Commercial License">
</p>

---

## 📌 Tentang PolyLife

**PolyLife** adalah aplikasi workspace produktivitas serbaguna yang dirancang khusus untuk mahasiswa dan civitas akademika. PolyLife memadukan pencatatan keuangan pribadi, kalender jadwal kuliah, penugasan akademik, daftar tugas (to-do list), catatan materi kuliah, simulasi IPK/IPS, hingga pengingat otomatis (push notification) dan asisten komputasi AI dalam satu ekosistem web dan mobile.

PolyLife hadir dengan antarmuka modern yang responsif (Blade + Tailwind CSS), arsitektur modular di atas Laravel 12, serta aplikasi mobile pendamping berbasis Flutter dengan dukungan sinkronisasi offline-first.

---

## ✨ Fitur Utama

### 1. 📊 Dashboard Terpadu
- **Overview Harian**: Jadwal kuliah hari ini, ringkasan tugas prioritas, dan status to-do list aktif.
- **Arus Kas Bulanan**: Grafik mini pemasukan vs. pengeluaran dan indikator saldo akun.
- **Reminder & Pengumuman**: Notifikasi pengingat terdekat dan papan siaran resmi kampus/afiliasi.

### 2. 💰 Manajemen Keuangan & Statistik
- **Pencatatan Transaksi**: Kelola pemasukan dan pengeluaran dengan kategori yang dapat disesuaikan.
- **Anggaran (Budgeting)**: Batasan pengeluaran bulanan per kategori untuk mencegah pemborosan.
- **Deteksi Anomali**: Algoritma statistik pendeteksi lonjakan pengeluaran tidak wajar.
- **Analisis & Statistik Tahunan**: Grafik komparasi bulanan, saldo kumulatif, top kategori, dan dropdown filter tahunan dinamis yang menyesuaikan riwayat transaksi riil.

### 3. 📚 Akademik, Jadwal & Tugas
- **Kalender Kuliah & Agenda**: Tampilan jadwal mingguan/bulanan, multi-matkul, serta filter agenda (kuliah, UTS, UAS, kegiatan organisasi, libur).
- **Impor Batch Mata Kuliah**: Fitur parser cerdas untuk menyalin jadwal kuliah langsung dari format tabel teks ke sistem.
- **Penugasan (Tugas)**: Pelacakan deadline tugas kuliah, tingkat prioritas, dan status penyelesaian.

### 4. ✅ To-Do List & Pengingat (Reminder)
- **To-Do List Cepat**: Tambah dan centang tugas dengan toggle status instan via AJAX.
- **Web Push Notifications**: Notifikasi browser otomatis menggunakan standar Web Push (VAPID).
- **Target Reminder Luwes**: Menghubungkan pengingat ke to-do list, tugas kuliah, maupun jadwal kegiatan.

### 5. 📝 Catatan (Notes)
- **Pencatatan Terstruktur**: Editor catatan materi dan ide dengan pengelompokan kategori.
- **Tempat Sampah & Pemulihan (Trash & Restore)**: Fitur soft-delete untuk mencegah kehilangan catatan penting.

### 6. 🎓 Simulasi IPK/IPS & Peta Nilai Mutu
- **Kalkulator IPK**: Perhitungan IPK berjalan berdasarkan SKS dan nilai bobot.
- **Target IPS Semester**: Simulasi target pencapaian nilai per semester.
- **Konfigurasi Nilai Mutu**: Penyesuaian rentang skor dan bobot huruf mutu (A, B+, B, dst.) sesuai standar masing-masing kampus/program studi.

### 7. 🤖 AI Assistant & Science Execution Sandbox
- **Chat Asisten Mahasiswa**: Tanya jawab materi dan bantuan produktivitas dengan dukungan model LLM (Gemini / OpenAI / DeepSeek).
- **Client-Side Science Sandbox**: Eksekusi komputasi ilmiah dan analisis formula secara aman di sisi browser klien (QuickJS Web Worker).

### 8. 👥 Multi-Role & Institusi Kampus
- **Mahasiswa (User)**: Akses workspace pribadi lengkap.
- **Admin Afiliasi / Fakultas**: Mengirim siaran pengumuman terverifikasi dan mengelola data mahasiswa terafiliasi.
- **Endmin (Super Admin)**: Manajemen pengguna, konfigurasi institusi kampus, serta pencatatan audit log sistem.
- **Guest / Demo Workspace**: Mode pratinjau interaktif tanpa login (`/guest`) dengan data contoh yang aman.

### 9. 📱 Sinkronisasi Mobile (Flutter)
- Aplikasi Android/iOS pendamping di direktori `mobile_ver/`.
- Otentikasi aman berbasis Laravel Sanctum.
- Arsitektur sinkronisasi offline-first (`sync/pull` & `sync/push`) dengan antrean transaksi lokal (Sqflite).

---

## 🛠️ Tech Stack

| Komponen | Teknologi |
|---|---|
| **Backend Framework** | [Laravel 12](https://laravel.com/) (PHP 8.2+) |
| **Authentication & API** | Laravel Sanctum |
| **Frontend Web** | Blade, [Tailwind CSS 3](https://tailwindcss.com/), [Vite 7](https://vitejs.dev/) |
| **Komponen Reaktif** | [Livewire 3](https://livewire.laravel.com/) & Volt |
| **Visualisasi & Rendering** | [Chart.js](https://www.chartjs.org/), [KaTeX](https://katex.org/), [QuickJS](https://bellard.org/quickjs/) |
| **Mobile App** | [Flutter 3](https://flutter.dev/) (Dart) |
| **Notifikasi Push** | Minishlink Web-Push (VAPID) |
| **Database** | MySQL / PostgreSQL / SQLite |
| **Testing** | [Pest PHP](https://pestphp.com/) (Feature & Unit) |

---

## 🚀 Panduan Instalasi & Menjalankan Proyek

### Prasyarat Sistem
- PHP >= 8.2 dengan ekstensi `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`
- [Composer](https://getcomposer.org/) >= 2.x
- [Node.js](https://nodejs.org/) >= 18.x & NPM
- Database server (MySQL / MariaDB / PostgreSQL / SQLite)

### Langkah Instalasi (Web Application)

1. **Clone Repository**
   ```bash
   git clone https://github.com/rizkyaa-dev/polylife-beta-release.git
   cd polylife-beta-release
   ```

2. **Install Dependensi PHP & JavaScript**
   ```bash
   composer install
   npm install
   ```

3. **Salin File Environment**
   ```bash
   cp .env.example .env
   # Pada Windows PowerShell:
   # Copy-Item .env.example .env
   ```

4. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

5. **Konfigurasi Database**
   Buka file `.env` dan sesuaikan koneksi database Anda:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=polylife
   DB_USERNAME=root
   DB_PASSWORD=
   ```

6. **Jalankan Migrasi & Seeder**
   ```bash
   php artisan migrate --seed
   ```

7. **Kompilasi Asset Frontend**
   ```bash
   # Mode pengembangan (HMR):
   npm run dev

   # Atau mode build produksi:
   npm run build
   ```

8. **Jalankan Server Lokal**
   ```bash
   php artisan serve
   ```
   Buka peramban di [http://localhost:8000](http://localhost:8000).

---

## ⚙️ Menjalankan Worker Antrean (Queue)

Fitur pengingat push notification dan pemrosesan latar belakang memerlukan queue worker yang berjalan:

```bash
php artisan queue:listen
```

Untuk memantau log secara real-time saat pengembangan:
```bash
php artisan pail
```

---

## 📱 Panduan Aplikasi Mobile (Flutter)

Aplikasi mobile berada di direktori `mobile_ver/`.

1. **Masuk ke Direktori Mobile**
   ```bash
   cd mobile_ver
   ```

2. **Ambil Paket Dependensi**
   ```bash
   flutter pub get
   ```

3. **Jalankan Aplikasi dengan Konfigurasi API**
   ```bash
   # Android Emulator (akses localhost host OS):
   flutter run

   # Perangkat Fisik (dalam jaringan Wi-Fi lokal yang sama):
   flutter run --dart-define=POLYLIFE_API_BASE_URL=http://<IP_KOMPUTER_ANDA>:8000/api/v1
   ```

Untuk dokumentasi lengkap arsitektur mobile dan sinkronisasi offline, baca [mobile_ver/README.md](mobile_ver/README.md).

---

## 🧪 Pengujian (Testing)

Proyek ini dilengkapi dengan suite pengujian otomatis berbasis **Pest PHP**:

```bash
# Menjalankan seluruh pengujian backend:
php artisan test

# Menjalankan pengujian spesifik fitur:
php artisan test --filter=KeuanganStatistik
php artisan test --filter=Api

# Menjalankan pengujian mobile:
cd mobile_ver && flutter test
```

---

## 📄 Lisensi

Hak cipta (c) 2026 **rizkyaa-dev**.

Kode sumber dan materi proyek PolyLife dilisensikan di bawah **[Source Available Non-Commercial License](LICENSE)**. Perangkat lunak ini tersedia hanya untuk kebutuhan non-komersial (pribadi, edukasi, dan penelitian akademis). Penggunaan komersial, korporasi, enterprise, atau monetisasi dalam bentuk apa pun dilarang tanpa izin tertulis dari pemegang hak cipta.

*Catatan: Komponen pihak ketiga (seperti framework Laravel dan dependensi terkait) tetap tunduk pada lisensi masing-masing.*
