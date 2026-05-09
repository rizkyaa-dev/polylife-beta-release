# PolyLife Hosting Guide

Panduan ini dipakai saat menyiapkan VPS Ubuntu baru untuk menjalankan PolyLife dengan Docker, Nginx reverse proxy, SSL Let's Encrypt, MySQL, Redis, queue worker, scheduler, dan CI/CD.

Dokumen ini tidak menyimpan secret asli. Semua nilai password, token, dan key harus dibuat sendiri atau disalin dari VPS lama dengan aman.

## Arsitektur

Alur request production:

```text
Browser
-> Cloudflare/DNS
-> Nginx VPS port 80/443
-> http://127.0.0.1:8086
-> container polylife_app port 80
-> Laravel
```

Service production:

```text
polylife_app       Laravel + Nginx + PHP-FPM
polylife_queue     php artisan queue:work
polylife_scheduler php artisan schedule:work
laravel_db         MySQL
polylife_redis     Redis
host nginx         reverse proxy + SSL
```

## Data yang Harus Dibawa Saat Migrasi

Wajib:

- `APP_KEY` lama.
- `.env` production lama, tanpa membocorkan ke Git.
- dump database MySQL.
- file upload/storage jika aplikasi sudah menyimpan file user.
- akses GHCR untuk pull image Docker.
- SSH key untuk user deploy.

Penting: `APP_KEY` harus sama dengan VPS lama. Jika berubah, data terenkripsi, session, signed URL, reset link lama, dan cookie bisa tidak valid.

## 1. Setup Ubuntu Bersih

Jalankan sebagai `root` di VPS baru.

```bash
apt update
apt upgrade -y
timedatectl set-timezone Asia/Jakarta
```

Buat user aplikasi.

```bash
adduser polylife
usermod -aG sudo polylife
```

Pasang SSH public key.

```bash
mkdir -p /home/polylife/.ssh
nano /home/polylife/.ssh/authorized_keys
chown -R polylife:polylife /home/polylife/.ssh
chmod 700 /home/polylife/.ssh
chmod 600 /home/polylife/.ssh/authorized_keys
```

Isi `authorized_keys` dengan public key dari komputer lokal, contoh:

```text
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI... polylife@your-device
```

Login ulang sebagai user `polylife`.

```bash
ssh polylife@IP_VPS_BARU
```

## 2. Install Dependency Server

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg ufw nginx certbot python3-certbot-nginx
```

Install Docker resmi.

```bash
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg
```

```bash
echo \
"deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
$(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
```

```bash
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker polylife
```

Logout lalu login lagi supaya group Docker aktif.

Cek Docker.

```bash
docker version
docker compose version
```

## 3. Firewall VPS

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

Jika sudah yakin SSH key aktif, password login SSH bisa dimatikan.

```bash
sudo nano /etc/ssh/sshd_config
```

Pastikan nilai ini:

```text
PasswordAuthentication no
PermitRootLogin no
PubkeyAuthentication yes
```

Reload SSH.

```bash
sudo sshd -t
sudo systemctl reload ssh
```

## 4. Buat Folder Aplikasi

```bash
sudo mkdir -p /opt/polylife
sudo chown -R polylife:polylife /opt/polylife
cd /opt/polylife
```

## 5. Login ke GHCR

Jika image GHCR private, login dulu.

```bash
docker login ghcr.io
```

Username: username GitHub.

Password: GitHub Personal Access Token dengan izin minimal untuk read package.

Jika image public, langkah ini bisa dilewati.

## 6. Buat `docker-compose.yml`

Buat file:

```bash
nano /opt/polylife/docker-compose.yml
```

Isi lengkap:

```yaml
services:
  app:
    image: ghcr.io/rizkyaa-dev/polylife-beta-release:latest
    container_name: polylife_app
    restart: unless-stopped
    ports:
      - "127.0.0.1:8086:80"
    env_file:
      - .env
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app-network

  queue:
    image: ghcr.io/rizkyaa-dev/polylife-beta-release:latest
    container_name: polylife_queue
    restart: unless-stopped
    command: php artisan queue:work --sleep=3 --tries=3 --timeout=90
    env_file:
      - .env
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app-network

  scheduler:
    image: ghcr.io/rizkyaa-dev/polylife-beta-release:latest
    container_name: polylife_scheduler
    restart: unless-stopped
    command: php artisan schedule:work
    env_file:
      - .env
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app-network

  db:
    image: mysql:8.0
    container_name: laravel_db
    restart: unless-stopped
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h localhost -p$${MYSQL_ROOT_PASSWORD} --silent"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - app-network

  redis:
    image: redis:7-alpine
    container_name: polylife_redis
    restart: unless-stopped
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - app-network

volumes:
  db_data:
  redis_data:

networks:
  app-network:
```

Catatan:

- Port app hanya bind ke `127.0.0.1:8086`, jadi container tidak langsung terbuka ke internet.
- Public traffic harus lewat Nginx host.
- `queue` wajib ada jika `QUEUE_CONNECTION=redis`.
- `scheduler` menjalankan Laravel scheduler terus-menerus.

## 7. Buat `.env` Production

Buat file:

```bash
nano /opt/polylife/.env
chmod 600 /opt/polylife/.env
```

Template:

```env
APP_NAME=PolyLife
APP_ENV=production
APP_KEY=base64:ISI_DENGAN_APP_KEY_LAMA_ATAU_HASIL_KEY_GENERATE
APP_DEBUG=false
APP_URL=https://polylife.site

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=polylife
DB_USERNAME=polylife
DB_PASSWORD=GANTI_PASSWORD_DB_USER
DB_ROOT_PASSWORD=GANTI_PASSWORD_ROOT_DB

CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true

QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local

MAIL_MAILER=mailtrap
MAIL_FROM_ADDRESS="no-reply@polylife.site"
MAIL_FROM_NAME="${APP_NAME}"
MAILTRAP_API_TOKEN=GANTI_DENGAN_TOKEN_MAILTRAP

VITE_APP_NAME="${APP_NAME}"

VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=https://polylife.site

# Isi setelah Docker network dibuat. Untuk setup ini biasanya 172.18.0.1.
TRUSTED_PROXIES=172.18.0.1
SECURITY_STRIP_UNTRUSTED_PROXY_HEADERS=true
```

Jika belum punya `APP_KEY`, generate dari container setelah app bisa start:

```bash
docker compose run --rm app php artisan key:generate --show
```

Untuk migrasi dari VPS lama, gunakan `APP_KEY` lama.

## 8. Start Container Pertama Kali

```bash
cd /opt/polylife
docker compose pull
docker compose up -d
docker compose ps
```

Cek log:

```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 db
docker compose logs --tail=100 redis
```

Cek gateway Docker untuk `TRUSTED_PROXIES`.

```bash
docker network inspect polylife_app-network | grep -m1 Gateway
```

Jika gateway bukan `172.18.0.1`, update `.env`:

```bash
nano /opt/polylife/.env
```

Lalu restart app:

```bash
docker compose up -d
```

## 9. Inisialisasi Laravel

```bash
cd /opt/polylife
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan optimize
```

Cek app internal:

```bash
curl -I http://127.0.0.1:8086
```

Harus mendapat HTTP response dari aplikasi.

## 10. Nginx Reverse Proxy

Buat file:

```bash
sudo nano /etc/nginx/sites-available/polylife.site
```

Isi awal sebelum SSL:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name polylife.site www.polylife.site;

    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        proxy_pass http://127.0.0.1:8086;
        proxy_http_version 1.1;
        proxy_hide_header X-Powered-By;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Aktifkan site:

```bash
sudo ln -s /etc/nginx/sites-available/polylife.site /etc/nginx/sites-enabled/polylife.site
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

## 11. Arahkan DNS

Di DNS provider:

```text
A     polylife.site      IP_VPS_BARU
A     www                IP_VPS_BARU
```

Jika memakai Cloudflare:

- SSL/TLS mode: Full (strict).
- Untuk awal migrasi, boleh DNS only sampai SSL valid.
- Setelah SSL valid, bisa Proxied lagi jika ingin proteksi DDoS/WAF.
- Jangan cache HTML Laravel secara global.
- Cache hanya static asset seperti `/build/assets/*`.

## 12. SSL Let's Encrypt

Setelah DNS mengarah ke VPS baru:

```bash
sudo certbot --nginx -d polylife.site -d www.polylife.site
sudo certbot renew --dry-run
```

Certbot akan mengubah konfigurasi Nginx menjadi HTTPS.

Pastikan redirect HTTP ke HTTPS aktif:

```bash
curl -I http://polylife.site
curl -I https://polylife.site
```

## 13. Hardening Nginx Setelah SSL

Setelah Certbot selesai, cek file:

```bash
sudo nano /etc/nginx/sites-available/polylife.site
```

Pastikan server HTTPS memiliki header ini:

```nginx
add_header Strict-Transport-Security "max-age=15552000" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "accelerometer=(), autoplay=(), camera=(), clipboard-read=(), clipboard-write=(), display-capture=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), sync-xhr=(), usb=(), xr-spatial-tracking=()" always;
```

Contoh blok HTTPS final:

```nginx
server {
    listen 443 ssl;
    listen [::]:443 ssl ipv6only=on;
    server_name polylife.site www.polylife.site;

    ssl_certificate /etc/letsencrypt/live/polylife.site/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/polylife.site/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    add_header Strict-Transport-Security "max-age=15552000" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "accelerometer=(), autoplay=(), camera=(), clipboard-read=(), clipboard-write=(), display-capture=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), sync-xhr=(), usb=(), xr-spatial-tracking=()" always;

    location / {
        proxy_pass http://127.0.0.1:8086;
        proxy_http_version 1.1;
        proxy_hide_header X-Powered-By;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Reload:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 14. Restore Database dari VPS Lama

Di VPS lama:

```bash
cd /opt/polylife
docker exec laravel_db sh -lc 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' | gzip > polylife-db.sql.gz
```

Kirim ke VPS baru:

```bash
scp polylife-db.sql.gz polylife@IP_VPS_BARU:/opt/polylife/
```

Di VPS baru:

```bash
cd /opt/polylife
gunzip -c polylife-db.sql.gz | docker exec -i laravel_db sh -lc 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

Jika memakai user database non-root dan restore gagal karena permission, restore dengan root seperti contoh di atas.

## 15. Restore File Storage

Jika aplikasi punya file upload di `storage/app/public`, backup dari VPS lama:

```bash
cd /opt/polylife
docker cp polylife_app:/var/www/html/storage/app/public ./storage-public-backup
tar -czf storage-public-backup.tar.gz storage-public-backup
```

Kirim ke VPS baru:

```bash
scp storage-public-backup.tar.gz polylife@IP_VPS_BARU:/opt/polylife/
```

Restore ke container baru:

```bash
cd /opt/polylife
tar -xzf storage-public-backup.tar.gz
docker cp storage-public-backup/. polylife_app:/var/www/html/storage/app/public/
docker compose exec app chown -R www-data:www-data storage/app/public
docker compose exec app php artisan storage:link
```

Jika belum ada upload user, langkah ini bisa dilewati.

## 16. CI/CD GitHub Actions

Workflow repository sudah melakukan:

```text
push main
-> run test
-> build image Docker
-> push image ke GHCR
-> SSH ke VPS
-> docker pull
-> docker compose up -d --remove-orphans
-> migrate
-> optimize
-> prune image lama
```

Secrets GitHub yang dibutuhkan:

```text
SERVER_HOST      IP VPS baru
SERVER_USER      polylife
SERVER_PORT      22
SERVER_SSH_KEY   private key untuk login ke VPS
```

Di VPS baru, pastikan folder `/opt/polylife` sudah ada dan berisi:

```text
/opt/polylife/docker-compose.yml
/opt/polylife/.env
```

Test manual deploy:

```bash
cd /opt/polylife
docker compose pull
docker compose up -d --remove-orphans
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize
```

## 17. Perintah Operasional Harian

Status container:

```bash
cd /opt/polylife
docker compose ps
```

Log aplikasi:

```bash
docker compose logs --tail=100 app
docker compose logs --tail=100 queue
docker compose logs --tail=100 scheduler
```

Masuk shell app:

```bash
docker compose exec app sh
```

Jalankan artisan:

```bash
docker compose exec app php artisan about
docker compose exec app php artisan route:list
docker compose exec app php artisan optimize
```

Cek resource:

```bash
docker stats --no-stream
free -h
df -h /
uptime
```

Restart app:

```bash
docker compose restart app queue scheduler
```

Update image:

```bash
docker compose pull
docker compose up -d --remove-orphans
docker image prune -f
```

## 18. Backup Rutin

Buat folder backup:

```bash
mkdir -p /opt/polylife/backups
```

Backup database:

```bash
cd /opt/polylife
backup_file="backups/polylife-db-$(date +%Y%m%d-%H%M%S).sql.gz"
docker exec laravel_db sh -lc 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' | gzip > "$backup_file"
ls -lh "$backup_file"
```

Backup `.env`:

```bash
cd /opt/polylife
cp .env "backups/env-$(date +%Y%m%d-%H%M%S).backup"
chmod 600 backups/env-*.backup
```

Ambil backup ke komputer lokal:

```bash
scp polylife@IP_VPS:/opt/polylife/backups/polylife-db-YYYYMMDD-HHMMSS.sql.gz .
```

## 19. Cloudflare yang Aman

Jika Cloudflare aktif:

- DNS record `A polylife.site` ke IP VPS.
- Mode SSL/TLS: Full (strict).
- Aktifkan HTTP/2, HTTP/3, Brotli.
- Jangan cache HTML yang memakai session.
- Cache static asset saja.

Cache rule yang aman:

```text
If URI Path starts with /build/assets/
Then Cache Eligible / Cache Everything
Edge TTL: 1 month
Browser TTL: Respect existing headers atau 1 month
```

Jangan cache:

```text
/
/login
/register
/forgot-password
/reset-password/*
/workspace/*
/endmin/*
/api/*
```

Untuk proteksi auth:

```text
Rate limit /login
Rate limit /register
Rate limit /forgot-password
Rate limit /api/v1/auth/*
```

## 20. Trusted Proxy

Jangan gunakan wildcard:

```env
TRUSTED_PROXIES=*
```

Gunakan IP reverse proxy yang benar. Untuk Docker bridge setup ini biasanya:

```env
TRUSTED_PROXIES=172.18.0.1
```

Cek gateway:

```bash
docker network inspect polylife_app-network | grep -m1 Gateway
```

Jika gateway berbeda, update `.env`, lalu:

```bash
docker compose up -d
docker compose exec app php artisan optimize
```

Dampak jika salah:

- `request()->ip()` bisa salah.
- rate limit berbasis IP bisa tidak akurat.
- signed URL/HTTPS detection bisa bermasalah.
- redirect bisa menghasilkan scheme/host yang salah.

## 21. Troubleshooting

App tidak bisa dibuka:

```bash
docker compose ps
docker compose logs --tail=100 app
curl -I http://127.0.0.1:8086
sudo nginx -t
sudo systemctl status nginx
```

502 dari Nginx:

```bash
docker compose ps app
curl -I http://127.0.0.1:8086
sudo tail -100 /var/log/nginx/error.log
```

Database belum siap:

```bash
docker compose logs --tail=100 db
docker compose exec db mysqladmin ping -h localhost -p"$DB_ROOT_PASSWORD"
```

Queue tidak memproses job:

```bash
docker compose ps queue
docker compose logs --tail=100 queue
docker compose restart queue
```

Cache/config terasa aneh:

```bash
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
```

Email gagal:

```bash
docker compose exec app php artisan tinker
```

Lalu cek `.env`:

```bash
grep -E '^(MAIL_MAILER|MAIL_FROM_ADDRESS|MAILTRAP_API_TOKEN)=' /opt/polylife/.env
```

Web terasa lambat:

```bash
curl -sS -o /dev/null -w 'total=%{time_total} ttfb=%{time_starttransfer}\n' https://polylife.site/
curl --resolve polylife.site:443:IP_VPS -sS -o /dev/null -w 'origin total=%{time_total} ttfb=%{time_starttransfer}\n' https://polylife.site/
docker stats --no-stream
```

Jika direct origin cepat tapi domain lewat Cloudflare lambat, masalahnya kemungkinan routing/proxy Cloudflare, bukan Laravel.

## 22. Checklist Go Live

- Docker service jalan.
- `docker compose ps` semua service `Up`.
- `curl -I http://127.0.0.1:8086` berhasil dari VPS.
- Nginx `sudo nginx -t` OK.
- HTTPS valid untuk `polylife.site` dan `www.polylife.site`.
- `.env` memakai `APP_ENV=production` dan `APP_DEBUG=false`.
- `APP_URL=https://polylife.site`.
- `APP_KEY` benar.
- `TRUSTED_PROXIES` bukan wildcard.
- `SESSION_SECURE_COOKIE=true`.
- Queue worker jalan.
- Scheduler jalan.
- Database sudah restore.
- `php artisan migrate --force` selesai.
- `php artisan optimize` selesai.
- GitHub Actions secrets sudah mengarah ke VPS baru.
- Backup database pertama sudah dibuat.

