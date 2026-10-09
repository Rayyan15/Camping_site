# Catatan Perubahan

Ringkasan pekerjaan sejak audit 9 Oktober 2026. Tiap butir menyebut apa yang diperbaiki, di mana, dan bagaimana dibuktikan. Kode butir (K1, T2, S3, dan seterusnya) berasal dari laporan bug hunt pada sesi yang sama.

Baseline sebelum pekerjaan ini: 110 test lulus. Setelah gelombang perbaikan dan fitur pertama: 254 test lulus, 2 dilewati (test MySQL yang otomatis lewat di SQLite).

## 1. Perbaikan bug dari bug hunt

### Kritis

| Kode | Masalah | Perbaikan | Bukti |
|---|---|---|---|
| K1 | `UnitNightLedger` tidak pernah dipanggil, jaminan anti double booking di level DB tidak aktif | `claim()` dipanggil di akhir transaksi `BookingService::createBooking`, `release()` saat expired, batal, dan refund disetujui, serta klaim ulang saat pembayaran terlambat | `UnitNightLedgerTest` (11 test) |
| K2 | Signature webhook Midtrans bisa dipalsukan bila server key kosong | `MidtransGateway::serverKey()` melempar `PaymentException::gatewayNotConfigured()`; `gross_amount` dicocokkan dengan nominal payment | `PaymentTest` |

### Tinggi

| Kode | Masalah | Perbaikan |
|---|---|---|
| T1 | Halaman QR tenda menampilkan kode booking dan nama tamu | Controller tidak lagi mengirim model Booking ke view; kolom nama tidak diisi otomatis |
| T2, T3, T4 | Form booking admin memakai status yang tidak ada di enum, Create gagal, closure badge mengharapkan string | Select memakai `BookingStatus`, hanya transisi sah, field tanggal dan nominal read-only, halaman Create dihapus, badge memakai label dan warna dari enum |
| T5 | Dashboard menghitung refund sebagai pendapatan | `monthlyRevenue` = pembayaran masuk dikurangi pembayaran keluar (scope `Payment::netByPayableType`) |
| T6 | `PaymentException` tidak ditangkap saat bayar, halaman 500 | Controller menangkap dan mengalihkan dengan pesan aman, detail gateway hanya di log |
| T7 | Satuan add-on berupa teks bebas, extra bed bisa tertagih terlalu murah | Enum `AddonUnit`, `Select` di form, migrasi normalisasi data lama |

### Sedang dan rendah

| Kode | Perbaikan |
|---|---|
| S1 | Pemilihan unit dipindah ke dalam transaksi dengan `lockForUpdate` |
| S2 | `recordManual` memvalidasi nominal dan status; DP sebagian memperpanjang hold (`booking.manual_dp_hold_minutes`) |
| S3 | Payment pending dipakai ulang; pembayaran ganda ditandai `overpaid`; kegagalan jaringan bisa dipulihkan oleh settlement |
| S4 | Satu sumber hitungan tagihan (`BookingBilling`) termasuk pajak dan F&B yang ditagihkan ke booking |
| S5 | Refund: kunci baris, cek status, tolak nominal 0, `markPaid` tidak melebihi dana masuk |
| S6 | Aksi check-in dan check-out di panel, lewat `BookingStatusTransition::apply` |
| S7 | Customer ditentukan oleh email dan telepon, tidak lagi menempel pada customer orang lain |
| S8 | Throttle rute publik, batas `max_nights` dan `max_advance_days` |
| S9 | Guard hapus di model (`DeletionGuardObserver`) dan policy; booking tidak bisa dihapus dari panel |
| R1 | Nama nav group diseragamkan, grup "Menu" ditambah |
| R2, R3 | Harga baris unit di invoice sama dengan subtotal; nomor invoice retry saat bentrok |
| R4 | `calculate()` tidak memutasi objek tanggal pemanggil |
| R5 | `stayTotal` memuat special price sekali per rentang; unique `special_prices(unit_type_id, date)` |
| R6, R9 | Status order dikunci dan hanya maju satu langkah; pre-order milik booking yang tidak hidup tidak tampil di dapur |
| R7 | Slug dan kode unit unik dengan validasi, bukan error 500 |
| R8 | `ReleaseExpiredHolds` mengubah status lewat model sehingga audit log tercatat |

## 2. Fitur baru

- **Penyelesaian booking `needs_review`** (aksi "Selesaikan review"): pindah ke unit lain atau batalkan dengan refund penuh. Antrean "Perlu ditinjau" dengan umur dan badge. Notifikasi ke owner lewat event `BookingNeedsReview`.
- **Pembatalan paid tanpa refund** (tier 0%): booking dibatalkan tanpa baris Refund, tercatat sebagai `cancelled_without_refund`.
- **`access_token`** untuk URL status, invoice, checkout, dan pembatalan; halaman `/cek-booking` dengan verifikasi kedua.
- **Nominal refund** tampil sebelum tamu mengonfirmasi pembatalan.
- **Penghitung "Pre-order Menunggu Pembayaran"** di dashboard (tidak masuk antrean dapur).
- **CI GitHub Actions** (lint, test SQLite, test MySQL), retry deadlock, test konkurensi MySQL.
- **Runbook dan skrip deploy** di `deploy/`.
- **Landing page baru** dengan konsep "satu malam" (lihat `FRONTEND-DESIGN.md`).

Gelombang fitur berikutnya (resource admin, laporan, kalender, absensi, dan lainnya) dicatat di bagian bawah dokumen ini setelah selesai.

## 3. Temuan dari uji browser

- Link mati "+ Booking Baru" di dashboard (halaman Create sudah dihapus): diganti.
- Label tombol notifikasi terbaca sebagai kunci terjemahan mentah: ditambahkan override di `lang/vendor/filament-panels/id/layout.php`.
- Rupiah tampil dengan ",00": semua kolom rupiah memakai `decimalPlaces: 0`.
- Migrasi normalisasi add-on salah di MySQL karena perbandingan tidak membedakan huruf besar kecil: penyaringan dipindah ke PHP.
- Notifikasi `needs_review` sempat bisa menggagalkan pembayaran bila role `owner` belum ada: kueri diganti dan event dikirim setelah commit.

## 4. Keamanan dan tata kelola repo

- `CLAUDE.md` dikeluarkan dari git dan di-ignore. Cadangan ada di `docs/internal/CLAUDE.backup.md` (juga ter-ignore).
- `docs/internal/` tetap di-ignore. Dokumen di `docs/` selain itu ikut repo.
