# PolyLife Mobile

Flutter app untuk pengguna mobile PolyLife.

## Mode aplikasi

- `uiOnly = false` di `lib/core/config/app_mode.dart`:
  - Login + API aktif (Sanctum token).
- `uiOnly = true`:
  - UI-only tanpa login gate dan tanpa request API.

## Konfigurasi API base URL

Base URL default ada di `lib/core/config/api_config.dart`:

- default: `http://10.0.2.2:8000/api/v1` (Android Emulator ke localhost)

Override saat run/build:

```bash
flutter run --dart-define=POLYLIFE_API_BASE_URL=https://domainkamu.com/api/v1
```

Contoh untuk HP fisik dalam 1 Wi-Fi dengan laptop:

```bash
flutter run --dart-define=POLYLIFE_API_BASE_URL=http://192.168.1.10:8000/api/v1
```

## Endpoint auth yang dipakai

- `POST /auth/login`
- `GET /auth/me`
- `POST /auth/logout`

Semua endpoint berada di prefix `api/v1` dan memakai Sanctum bearer token.

## Sinkronisasi data mobile dan web

Gunakan akun yang sama dan API dari instalasi Laravel yang sama dengan web.
Catatan, Keuangan, To-Do, Jadwal, dan Reminder memakai data server yang sama.
Pengumuman dan profil tetap dibaca melalui endpoint masing-masing.

- Refresh menunggu pengiriman perubahan lokal dan pembacaan perubahan server
  sebelum menampilkan cache terbaru. Saat jaringan tidak tersedia, cache tetap
  dapat dibaca dan perubahan yang gagal dikirim tetap tersimpan untuk sync berikutnya.
- Data workspace dimuat ulang saat aplikasi dibuka atau kembali dari background;
  refresh halaman juga mengambil perubahan terbaru. Web membaca hasil perubahan
  mobile pada request/refresh berikutnya. Ini bukan pembaruan melalui WebSocket.
- Cursor disimpan per akun. Cursor lama yang bersifat global tidak digunakan,
  sehingga pembaruan pertama mengambil ulang data server tanpa menghapus edit offline.
- Antrean dikirim dalam batch maksimal 25 operasi hingga snapshot antrean selesai;
  kegagalan jaringan menghentikan pengiriman dan mempertahankan operasi yang belum dikirim.
- Mobile meminta `GET /sync/pull?cursor=...&replay_boundary=1` agar perubahan pada
  detik yang sama tidak terlewat. Versi server mencegah replay menurunkan versi cache.
  Klien lama yang tidak mengirim opsi ini mempertahankan perilaku cursor sebelumnya.

Verifikasi:

```bash
flutter test
flutter analyze
```

Dari root Laravel:

```bash
php artisan test --compact tests/Feature/Api
```
