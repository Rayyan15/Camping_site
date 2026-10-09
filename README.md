# Raynad Camping

Sistem hospitality untuk PT Raynad Cipta Makmur: booking tenda online, pre-order dan QR order makanan, pembayaran, refund, dan dashboard operasional dalam satu aplikasi.

32 unit tenda dari 7 tipe (Pancar, Safari, Salak, Indian, Romance, Snail, Dome).

## Stack

- PHP 8.2+, Laravel 11
- Filament v4 (dashboard admin di `/admin`)
- spatie/laravel-permission (role dan permission)
- Blade + Tailwind CSS v4 (Vite) untuk halaman publik
- barryvdh/laravel-dompdf (invoice PDF), simplesoftwareio/simple-qrcode (QR)
- Payment gateway: Midtrans Snap, dengan gateway palsu untuk pengembangan lokal
- SQLite untuk pengembangan, MySQL 8 untuk produksi

## Menjalankan secara lokal

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite          # Windows PowerShell: New-Item database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm run build                           # atau npm run dev saat mengembangkan UI
php artisan serve
php artisan schedule:work               # job pelepas hold booking, jalankan di terminal lain
```

Buka `http://127.0.0.1:8000`. Admin ada di `/admin`. Akun demo hanya dibuat di environment `local` dan `testing` (password dari `DEMO_PASSWORD` di `.env`, atau acak bila kosong).

Di produksi tidak ada akun bawaan. Buat owner dengan:

```bash
php artisan app:create-owner email@contoh.com "Nama Owner"
```

Detail akses dan keamanan: `docs/internal/05-access-security-ops.md`.

Menjalankan test:

```bash
php artisan test
```

## Fitur yang sudah ada

- Landing page, daftar tipe tenda, cek ketersediaan dengan kalender rentang tanggal
- Booking multi-unit dengan extra bed dan pre-order makanan, harga dihitung di server
  (weekday, weekend, harga khusus tanggal, addon, pajak)
- Hold unit 15 menit; booking yang tidak dibayar kedaluwarsa otomatis
- Checkout, pembayaran lewat Midtrans, webhook terverifikasi dan idempoten
- Halaman status booking tanpa login (`/booking/{kode}`), pembatalan, refund sesuai kebijakan
- Invoice PDF dan tombol bagikan ke WhatsApp
- QR order di tenda dan meja (`/order/{token}`), bisa dibayar ke kasir atau ditagihkan ke booking
- Antrian dapur, order walk-in, dan pre-order yang tampil otomatis H-1 dan hari H
- Dashboard admin: booking, unit, pelanggan, order, menu, QR, refund, pembersihan, karyawan, penilaian
- Tiga role (Owner, Operator FO, Operator Kasir), 2FA untuk owner, log aktivitas, security headers

Daftar kebutuhan lengkap ada di `docs/internal/prd-sistem-hospitality-camping.md` (folder ini di-gitignore).

## Konfigurasi penting (`.env`)

| Variabel | Fungsi |
|---|---|
| `APP_TIMEZONE`, `APP_LOCALE` | Default `Asia/Jakarta` dan `id` |
| `PAYMENT_GATEWAY` | `fake` untuk lokal, `midtrans` untuk produksi |
| `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION` | Kredensial Midtrans |
| `SITE_*` | Nama usaha, nomor WhatsApp, alamat, maps, Instagram (dipakai landing dan invoice) |
| `FILESYSTEM_DISK` | `public` agar foto unggahan bisa diakses |

Di dashboard Midtrans, atur notification URL ke `https://<domain>/webhook/payment`.

## Struktur kode

- `app/Services` berisi logika bisnis (BookingService, PricingService, RefundService, InvoiceService, Payment/*). Controller dan kelas Filament hanya menerjemahkan input ke pemanggilan service.
- `app/Enums` berisi status (BookingStatus, OrderStatus, RefundStatus, PaymentStatus, dan lain-lain).
- `app/Jobs/ReleaseExpiredHolds` dijadwalkan tiap menit di `routes/console.php`.
- Aturan kode dan desain ada di `CLAUDE.md`.

## Produksi

- Set `APP_ENV=production`, `APP_DEBUG=false`, database MySQL, `PAYMENT_GATEWAY=midtrans`.
- Jalankan `php artisan migrate --force`, `php artisan storage:link`, `npm run build`.
- Cron: `* * * * * php /path/artisan schedule:run`.
- Backup database harian dan salinan di luar server belum disiapkan.

## Deploy

Runbook lengkap (prasyarat, urutan rilis, migrasi yang mengubah data, rencana mundur) ada di `deploy/DEPLOY.md`. Untuk menjalankan urutan rilis secara terpandu:

```
bash deploy/deploy.sh
```
