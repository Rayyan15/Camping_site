# Daftar Pekerjaan

Diperbarui 9 Oktober 2026. Sumber: audit dua agent (bug hunt dan modul setengah jadi) pada sesi yang sama. Centang butir yang sudah selesai dan tulis buktinya (nama test atau commit). Keputusan yang menunggu owner ada di `OPEN-QUESTIONS.md`.

## Selesai di gelombang ini

- [x] **DP di checkout (FR-33).** Tamu memilih bayar penuh atau DP bila persen DP di Pengaturan bernilai 1 sampai 99. Nominal dihitung di server (`BookingBilling::downPaymentAmount`). Setelah DP, unit dipegang sampai akhir hari check-in, dan sisanya bisa dilunasi dari halaman yang sama. Bawaan 0 (mati) sampai owner mengisi. Bukti: `PaymentHardeningTest` (DP gateway, DP ditolak saat mati, hold).
- [x] **Booking ber-DP yang lewat batas hold tidak lagi hangus.** Statusnya masuk `needs_review`, bukan `expired`, supaya uangnya tetap tercatat. Bukti: `PaymentHardeningTest::test_lapsed_hold_with_a_down_payment_goes_to_review_instead_of_expiring`.
- [x] **Bayar order QR online, QRIS dan e-wallet (FR-47).** Pilihan baru di halaman menu QR. Dapur baru melihat pesanan setelah pembayaran diterima. Tamu bisa membuka lagi pembayaran dari halaman lacak pesanan. Bukti: `QrOrderTest` (dua test baru).
- [x] **Aturan tier refund di panel.** Menu "Aturan Refund" di grup Laporan & Keuangan, khusus owner. Bukti: `RefundPolicyResourceTest`.
- [x] **Pemeriksaan laporan kebersihan.** Aksi "Setujui" dan "Minta bersihkan ulang" (alasan wajib, tersimpan di catatan). Status tidak lagi dipilih bebas di form. Izin baru `review_cleaning_logs`, default hanya owner. Bukti: `CleaningLogReviewTest`.
- [x] **Sinkron fingerprint selalu terjadwal.** Job jalan tiap malam dan mencatat "skipped" di log selama `ATTENDANCE_LOG_PATH` kosong. Path didokumentasikan di kedua `.env` contoh.

## Tidak dikerjakan, dengan alasan

- **Rotasi token QR tenda saat check-out.** Tidak dibangun karena QR tenda dicetak dan ditempel. Rotasi tiap check-out berarti mencetak ulang stiker setiap tamu. Risikonya sudah tertutup: tagihan ke booking hanya bisa ke booking berstatus Paid atau CheckedIn (`DiningSpotService::activeBooking`), jadi tamu yang sudah check-out tidak bisa menagihkan apa pun. Rotasi manual tetap ada di menu Titik QR.
- **Log kebersihan otomatis saat check-out.** Log butuh karyawan dan foto, dan keduanya tidak tersedia saat check-out. Bisa diganti dengan penanda "tenda perlu dibersihkan" bila owner memintanya.

## Bug terbuka (dari bug hunt, belum diperbaiki)

Prioritas tinggi:

- [x] Batal booking `PendingPayment` yang sudah ada DP kini lewat jalur refund (tier berlaku); admin tidak bisa batal biasa. Bukti: `PaymentHardeningTest` (dua test DP batal).
- [x] Booking batal tidak bisa hidup lagi. Link pending di-expire saat batal; uang yang tetap masuk dicatat dengan tanda "dana masuk setelah booking dibatalkan" untuk dikembalikan. Bukti: `test_old_link_paid_after_cancellation_does_not_revive_the_booking`.
- [x] `capture` + `fraud_status=deny` kini Failed. Bukti: `test_card_capture_denied_by_fraud_check_is_a_failure`.
- [x] Tagihan QR tenda memilih tamu yang sudah check-in, lalu yang datang paling akhir. Bukti: `QrOrderTest` (dua test pergantian tamu). Catatan: sebelum tamu baru check-in, tagihan masih ke tamu lama yang belum check-out.
- [x] `serve_date` wajib format `Y-m-d`, format lain jadi pesan validasi. Bukti: `test_serve_date_in_another_format_is_a_validation_error_not_a_crash`.

Prioritas sedang:

- [x] Kapasitas tambahan kini dari kolom `addons.extra_guests` (extra bed = 1, diisi di form add-on). Bukti: `test_a_per_night_addon_that_is_not_a_bed_adds_no_capacity`.
- [x] Klaim ulang unit berjalan di savepoint, jadi konflik di tengah tidak meninggalkan baris ledger sebagian.
- [x] Race MySQL: checkout diulang di transaksi baru saat konflik, sehingga memilih unit kosong berikutnya. Belum diuji di MySQL nyata (test konkurensi MySQL dilewati di SQLite).
- [ ] Aturan hari check-out tidak konsisten antara pre-order (ditolak) dan QR (masih aktif). Menunggu owner (pertanyaan 10).

Prioritas rendah:

- [x] Pajak makanan yang ditagihkan ke booking disimpan per order (`orders.tax`). Bukti: `test_food_billed_to_a_booking_keeps_the_tax_rate_of_its_day`.
- [x] Link bayar dengan nominal lama ditutup saat link baru dibuat. Bukti: `test_a_new_amount_closes_the_previous_payment_link`. Kelebihan bayar tetap ditandai untuk dikembalikan manual.
- [x] Check-in ditolak sebelum tanggal check-in. Bukti: `test_check_in_is_refused_before_the_check_in_date`.
- [x] Refund dan tagihan makanan: tidak bisa terjadi. Makanan hanya bisa ditagihkan saat tamu sedang menginap, sedangkan pembatalan butuh minimal 1 hari sebelum check-in.

## Modul belum lengkap

- [ ] **Varian menu (FR-42).** Belum ada tabel, model, form, atau test. Menunggu owner (pertanyaan 18).
- [ ] **Xendit (FR-30).** Menunggu keputusan owner. Kunci Midtrans produksi juga belum ada.
- [x] **Notifikasi WA saat batal dan refund disetujui.** Bukti: `test_cancelling_and_refunding_tell_the_guest`. Di produksi `WHATSAPP_ENABLED` masih false sampai penyedia dipilih.
- [x] **Izin mati dihapus** dari `config/access.php`. Akses tetap dijaga izin per resource.
- [x] **Activity log** kini juga mencatat Order, MenuItem, Attendance, Evaluation, DiningSpot.
- [x] **Penilaian karyawan** disimpan dan dihitung dalam satu transaksi.
- [x] **File debug** `storage/dbg.txt` dihapus. Tidak ada kode di repo yang menulisnya, kemungkinan sisa uji browser.

## Operasional dan tooling

- [ ] Uji browser untuk checkout (termasuk pilihan DP), status booking, menu QR (termasuk QRIS), antrean dapur.
- [ ] Larastan level 6.
- [ ] Backup di luar server.
- [ ] Commit semua perubahan yang masih menggantung.
