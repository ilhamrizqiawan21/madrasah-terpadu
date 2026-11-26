# DEPLOYMENT — Panduan Server

Target: satu VPS Linux (Ubuntu 24.04 LTS / Debian 12), 2 vCPU, 2–4 GB RAM, SSD, lokasi Indonesia. Aplikasi lain di server yang sama harus terisolasi (user, database, dan direktori terpisah).

## 1. Kebutuhan

- Nginx, PHP-FPM 8.3+ (`bcmath intl gd zip pdo_mysql mbstring xml curl openssl`), Composer 2, Node 24 (hanya untuk build; boleh build di CI lalu unggah `public/build`).
- MariaDB 10.11+ / MySQL 8: database dan user khusus aplikasi ini, hanya hak atas database itu.
- Supervisor (worker antrean), cron (scheduler), Certbot (HTTPS), `unzip`, `git`.

## 2. Langkah pertama kali

```bash
git clone <repo> /var/www/madrasah-terpadu && cd /var/www/madrasah-terpadu
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# isi .env: APP_ENV=production, APP_DEBUG=false, APP_URL=https://<domain>, DB_*, MIDTRANS_*, VAPID_*, MAIL_* (opsional)
php artisan migrate --force
php artisan db:seed --class=ProductionSeeder --force   # peran, izin, unit, surah. TANPA akun/data palsu
php artisan app:create-admin                          # interaktif
npm ci && npm run build
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan payments:check-config                     # menolak konfigurasi Midtrans yang tidak konsisten
```

Hak akses: pemilik berkas user deploy; `storage/` dan `bootstrap/cache/` dapat ditulis oleh user PHP-FPM.

## 3. Nginx (inti)

- Root `public/`, `try_files $uri $uri/ /index.php?$query_string;`
- HTTPS wajib (Certbot) + redirect HTTP → HTTPS. Web Push dan kamera **hanya** jalan di HTTPS.
- `client_max_body_size 5M;`
- `public/sw.js` disajikan dengan `Cache-Control: no-cache` dan `Service-Worker-Allowed: /`.
- Blokir akses ke berkas tersembunyi (`location ~ /\.(?!well-known) { deny all; }`).

## 4. Proses latar

Supervisor `/etc/supervisor/conf.d/madrasah-terpadu-worker.conf`:

```
[program:madrasah-terpadu-worker]
command=php /var/www/madrasah-terpadu/artisan queue:work --queue=payments,notifications,default --tries=3 --backoff=10 --max-time=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=60
stdout_logfile=/var/www/madrasah-terpadu/storage/logs/worker.log
```

Cron (user deploy): `* * * * * cd /var/www/madrasah-terpadu && php artisan schedule:run >> /dev/null 2>&1`

## 5. Deploy berikutnya

```bash
php artisan down --retry=30
git pull --ff-only
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

## 6. Backup & pemulihan

- `spatie/laravel-backup` (T-1402): database + `storage/app/private`, terenkripsi (`BACKUP_ARCHIVE_PASSWORD`), harian 02.00 WITA, simpan 14 harian + 8 mingguan, salinan ke penyimpanan di luar server.
- Uji pemulihan wajib sebelum serah terima dan setiap 3 bulan (T-1403). Catat tanggal uji di sini.

## 7. Midtrans produksi

1. Klien mengaktifkan akun production Midtrans atas nama yayasan.
2. Isi `MIDTRANS_IS_PRODUCTION=true`, Server/Client Key production.
3. URL notifikasi di dashboard Midtrans production: `https://<domain>/webhooks/midtrans`.
4. `php artisan payments:check-config` harus lulus.
5. Uji satu transaksi nominal kecil sungguhan bersama bendahara, lalu cek rekonsiliasi.

## 8. Pemantauan

- `storage/logs/laravel.log` harian (`LOG_STACK=daily`), simpan 14 hari.
- Endpoint `/up` untuk uptime check.
- Perintah `app:health` (T-1405): antrean macet, scheduler tidak berjalan, backup gagal, order pending lama → notifikasi ke admin.
