# Runbook deploy

Untuk server Ubuntu dengan Nginx, PHP-FPM 8.3, dan MySQL 8. Otomasi: `bash deploy/deploy.sh`.

## Prasyarat

- PHP 8.3 dengan ekstensi: bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, tokenizer, xml, zip.
- MySQL 8, Composer 2, Node 20+ (hanya untuk build aset), `mysqldump` di PATH.
- Cron `* * * * * cd /path/app && php artisan schedule:run >> /dev/null 2>&1`.
- Worker queue dijalankan Supervisor (`php artisan queue:work --tries=3 --max-time=3600`), restart otomatis.

## Checklist .env produksi

Mulai dari `.env.production.example`, isi semua `CHANGE_ME`, jangan commit.

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` memakai https.
- `APP_TIMEZONE=Asia/Jakarta`.
- `DB_CONNECTION=mysql` dengan user khusus untuk skema ini.
- `PAYMENT_GATEWAY=midtrans` wajib disertai `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`, `MIDTRANS_IS_PRODUCTION=true`. Webhook di dashboard Midtrans: `https://<domain>/webhook/payment`.
- `OWNER_2FA_REQUIRED=true`.
- `OWNER_PASSWORD` dan `DEMO_PASSWORD` kosong setelah pembuatan owner.
- `BACKUP_DISK` terisi bila ingin salinan di luar server.

## Urutan rilis

1. Jalankan suite di MySQL sebelum rilis (lihat bagian di bawah).
2. Cadangkan DB: `php artisan app:backup-database` (hasil di direktori `config('backup.directory')`). Alternatif: `mysqldump --single-transaction --routines <db> | gzip > backup.sql.gz`. Salin keluar server.
3. `php artisan down --retry=60`.
4. `git pull` (atau unggah rilis), lalu `composer install --no-dev -o --no-interaction`.
5. `npm ci && npm run build`.
6. `php artisan migrate --pretend`, tinjau SQL-nya.
7. `php artisan migrate --force`.
8. `php artisan db:seed --class=RoleSeeder --force` bila `config/access.php` (matriks akses) berubah. Idempoten.
9. `php artisan filament:upgrade` dan `php artisan storage:link`.
10. `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache`.
11. `php artisan queue:restart` agar worker memuat kode baru.
12. `php artisan up`.
13. Smoke test: buka landing, cek ketersediaan, halaman login panel, `php artisan app:health`, `php artisan migrate:status` tanpa `Pending`, dan pastikan `schedule:run` serta worker aktif.

## Migrasi yang mengubah data

Keduanya mengubah atau menghapus baris. Jumlah baris tercatat di log aplikasi (level info), id yang dihapus pada level debug.

### 2026_10_12_000001_normalize_addon_units

Satuan add-on bebas teks diubah ke nilai enum: teks mengandung "malam" menjadi per malam, sisanya per item. Teks asli tidak bisa dipulihkan.

```sql
-- sebelum: baris yang akan diubah
SELECT id, name, unit FROM addons WHERE unit NOT IN ('per malam', 'per item');
-- sesudah: harus kosong
SELECT id, name, unit FROM addons WHERE unit NOT IN ('per malam', 'per item');
```

Cocokkan nilai enum dengan `app/Enums/AddonUnit.php` sebelum menjalankan query.

### 2026_10_12_000002_add_unique_to_special_prices

Duplikat `special_prices` per (`unit_type_id`, `date`) dihapus dengan menyisakan id tertinggi, lalu indeks unik dibuat.

```sql
-- sebelum: kelompok duplikat dan baris yang akan dihapus
SELECT unit_type_id, date, COUNT(*) AS jumlah, MAX(id) AS id_dipertahankan
FROM special_prices GROUP BY unit_type_id, date HAVING COUNT(*) > 1;

SELECT sp.* FROM special_prices sp
JOIN (SELECT unit_type_id, date, MAX(id) AS keep_id FROM special_prices GROUP BY unit_type_id, date) k
  ON k.unit_type_id = sp.unit_type_id AND k.date = sp.date AND sp.id <> k.keep_id;

-- sesudah: harus kosong
SELECT unit_type_id, date, COUNT(*) FROM special_prices GROUP BY unit_type_id, date HAVING COUNT(*) > 1;
```

Catatan mundur: `down()` hanya menghapus indeks unik dan add-on tidak dipulihkan. Cadangan DB satu-satunya jalan mundur untuk data yang sudah berubah.

## Suite di MySQL

SQLite tidak menguji `FOR UPDATE` dan perilaku kunci baris (anti double booking), jadi jalankan juga di MySQL sebelum rilis:

```
php artisan test --configuration=phpunit.mysql.xml
```

Gunakan database uji terpisah sesuai isi `phpunit.mysql.xml`, bukan database produksi.

## Rencana mundur

1. `php artisan down`.
2. Kembalikan kode ke rilis sebelumnya (`git checkout <tag>`), `composer install --no-dev -o`, build ulang aset.
3. Pulihkan cadangan: `gunzip -c backup.sql.gz | mysql <db>` (berkas dari `app:backup-database` berformat gzip).
4. Ulangi cache (`config:cache` dan seterusnya), `queue:restart`, `up`.

`migrate:rollback` tidak cukup: dua migrasi data di atas tidak mengembalikan baris yang dihapus atau diubah. Pesanan yang masuk antara cadangan dan pemulihan ikut hilang, jadi pertahankan jendela `down` sesingkat mungkin.
