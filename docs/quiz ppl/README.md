# Kuis Praktik 1 - Docker PolyLife

Implementasi ini mengadaptasi soal Node.js/PostgreSQL menjadi Laravel 12/PHP/MySQL sesuai proyek PolyLife. Penggantian stack memerlukan persetujuan pengajar. Konfigurasi berada di folder ini dan menggunakan source proyek pada dua tingkat di atasnya.

## Konfigurasi

- `Dockerfile`: multi-stage build, Composer dan npm memakai lockfile, runtime PHP-FPM/Nginx dengan user `www-data`, working directory `/app`, port container 3000.
- `Dockerfile.dockerignore`: allowlist source agar `.env`, vendor lokal, node_modules, data storage dan dokumen pribadi tidak masuk build.
- `docker-compose.yml`: service `web` dan `db` (MySQL 8.4), dependency `service_healthy`, healthcheck, bridge network `app_net`, named volume `db_data`.
- Nama project `polylife-quiz` memberi prefix pada network dan volume agar terpisah dari stack lain.
- Default port host 8080. `.env` lokal memakai 8088 karena 8080 sudah digunakan stack lain. Database tidak dipublikasikan ke host.
- `APP_ENV=local` dan `APP_DEBUG=false` untuk pengujian HTTP lokal; proyek memaksakan URL HTTPS pada environment selain local.

## Menjalankan

Jalankan dari folder `docs/quiz ppl`:

```powershell
docker compose config --quiet
docker compose up -d --build --wait
docker compose exec -T web php artisan migrate --force
docker compose cp setup-demo.php web:/tmp/quiz-setup-demo.php
docker compose exec -T web php /tmp/quiz-setup-demo.php
```

`.env` lokal sudah disiapkan. Untuk lingkungan baru, salin `.env.example`, isi password database dan root yang berbeda, dan isi APP_KEY Laravel (format `base64:` dengan 32 byte acak). Pertahankan APP_KEY yang sama selama memakai volume database yang sama karena konten catatan dienkripsi aplikasi.

## Pengujian

Buka `http://localhost:8088`. Import `KUIS_Docker_PolyLife.postman_collection.json` ke Postman dan jalankan request sesuai urutan. Akun ini khusus database praktikum terpisah:

- Email: `quiz.docker@example.test`
- Password demo: `QuizDocker!2405097`

Pengujian terminal alternatif:

```powershell
python verify-api.py
docker compose logs --tail 50 web
docker compose exec db sh
```

Dalam shell container database:

```sh
MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"
```

Dalam MySQL monitor:

```sql
SHOW DATABASES;
SELECT DATABASE();
SELECT id, user_id, tanggal FROM catatans;
exit
```

Ketik `exit` untuk kembali dari shell container ke PowerShell.

## Persistensi dan penghentian

```powershell
docker compose down
docker volume ls --filter name=polylife-quiz_db_data
docker compose up -d --wait
python verify-api.py --read-only
```

Mode `--read-only` memverifikasi catatan yang sudah dibuat tanpa membuat catatan baru. Login tetap membuat token sementara yang langsung dicabut setelah pengujian. `docker compose down` mempertahankan volume; jangan tambahkan `-v` jika data harus dipertahankan.

## Bukti dan laporan

- `screenshot/01` sampai `09`: bukti dari stack Docker port 8088, termasuk pengujian Postman POST 201, GET 200 dan validasi 422. Laporan menggunakan screenshot Postman baru; catatan ID 2 dibuat melalui Postman, ID 1 untuk pengujian CLI/persistensi.
- Screenshot bernama `00_ringkasan`, `11_catatan`, `12_catatan`, dan `16_catatan`: salinan bukti pengujian API sebelumnya dari `docs/tugas ppl/ss`; ringkasan aslinya memakai `127.0.0.1:8000`. Bukti lama bukan bukti pengujian container baru.
- `logs`: output build, status migrasi, log web, respons API yang telah menghapus bearer token, dan bukti volume.
- Laporan disusun pada dokumen Word yang sudah dibuka dengan cover dan logo asli, kemudian diekspor ke PDF.

Stack ini menguji HTTP/API dan MySQL. Worker AI, scheduler, email eksternal, dan push notification tidak termasuk ruang lingkup pengujian kuis.

Referensi: [Docker startup order](https://docs.docker.com/compose/how-tos/startup-order/) dan [Docker build best practices](https://docs.docker.com/build/building/best-practices/).
