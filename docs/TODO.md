# Daftar Pekerjaan

> Prioritas berikutnya: alur transfer manual, hapus bayar online di QR, tombol WhatsApp ke admin, keputusan email. Rincian di `docs/internal/next-steps-client-answers-2026-10-09.md`.

Diperbarui 9 Oktober 2026. Sumber: audit dua agent (bug hunt dan modul setengah jadi) pada sesi yang sama. Centang butir yang sudah selesai dan tulis buktinya (nama test atau commit). Keputusan yang menunggu owner ada di `OPEN-QUESTIONS.md`.

## Selesai di gelombang ini

- [x] **DP di checkout (FR-33).** Tamu memilih bayar penuh atau DP bila persen DP di Pengaturan bernilai 1 sampai 99. Nominal dihitung di server (`BookingBilling::downPaymentAmount`). Setelah DP, unit dipegang sampai akhir hari check-in, dan sisanya bisa dilunasi dari halaman yang sama. Bawaan 0 (mati) sampai owner mengisi. Bukti: `PaymentHardeningTest` (DP gateway, DP ditolak saat mati, hold).
- [x] **Booking ber-DP yang lewat batas hold tidak lagi hangus.** Statusnya masuk `needs_review`, bukan `expired`, supaya uangnya tetap tercatat. Bukti: `PaymentHardeningTest::test_lapsed_hold_with_a_down_payment_goes_to_review_instead_of_expiring`.
- [x] **Bayar order QR online, QRIS dan e-wallet (FR-47).** Pilihan baru di halaman menu QR. Dapur baru melihat pesanan setelah pembayaran diterima. Tamu bisa membuka lagi pembayaran dari halaman lacak pesanan. Bukti: `QrOrderTest` (dua test baru).
- [x] **Aturan tier refund di panel.** Menu "Aturan Refund" di grup Laporan & Keuangan, khusus owner. Bukti: `RefundPolicyResourceTest`.
- [x] **Pemeriksaan laporan kebersihan.** Aksi "Setujui" dan "Minta bersihkan ulang" (alasan wajib, tersimpan di catatan). Status tidak lagi dipilih bebas di form. Izin baru `review_cleaning_logs`, default hanya owner. Bukti: `CleaningLogReviewTest`.
- [x] **Sinkron fingerprint selalu terjadwal.** Job jalan tiap malam dan mencatat "skipped" di log selama `ATTENDANCE_LOG_PATH` kosong. Path didokumentasikan di kedua `.env` contoh.

## Gelombang jawaban client 10 Oktober 2026

- [x] Seeder refund dua tier, `SettingsSeeder` (pajak 0, DP 0, jam 14.00 dan 12.00), `EvaluationCriteriaSeeder`, kapasitas 4 + 2 di validasi booking, kontak dan TikTok di landing. Bukti: `ClientAnswersSeedTest`, `RefundCancellationTest`.
- [ ] Owner mengisi harga add-on `Extra Bed` dan mengaktifkannya (nominal belum diberikan).
- [ ] Konfirmasi harga 150rb per malam untuk tipe mana, lalu sesuaikan harga tipe.

- [ ] **Ditahan:** integrasi mesin absensi fingerprint (endpoint ADMS). Absensi manual lewat panel dulu.

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
- [x] Larastan level 6 (`composer analyse`, langkah CI). 433 temuan lama masuk `phpstan-baseline.neon`; kurangi bertahap.
- [ ] Perbaiki `EditUser::handleRecordUpdate` (return setelah `halt()`), lalu hapus `excludePaths` di `phpstan.neon`.
- [x] Backup di luar server siap di config: `app:backup-database` harian 02:30, salinan ke disk `BACKUP_DISK` (adapter S3 terpasang).
- [ ] Owner mengisi `BACKUP_DISK=s3` dan kredensial `AWS_*` di `.env` server, lalu uji `php artisan app:backup-database`.
- [ ] Commit semua perubahan yang masih menggantung.

## Alur transfer manual (10 Oktober 2026)

- [x] Hold 2 jam, perpanjangan 24 jam saat bukti masuk (config), rekening dari config, mode `manual`. Bukti: `TransferProofFlowTest`.
- [x] Unggah bukti di checkout, konfirmasi dan tolak di panel (Pembayaran dan Booking), filter, lencana, kartu dashboard, notifikasi staf.
- [x] Status booking menampilkan bukti menunggu atau ditolak; `ReleaseExpiredHolds` tidak meng-expire booking dengan bukti pending.
- [x] Tombol WhatsApp ke admin, email tamu (4 Mailable), `MAIL_*` di `.env.example`.
- [ ] Owner mengisi rekening bank, kredensial SMTP, dan nomor WhatsApp admin di `.env` server.
- [ ] Uji browser alur unggah bukti di server nyata.

- [x] Aksi "Selesaikan review" dikunci bila bukti transfer pending atau belum lunas. Bukti: `NeedsReviewResolutionTest` (tiga test baru).
- [x] Booking batal dengan bukti pending ditandai `proof_pending_on_cancel` untuk refund. Bukti: `test_cancelling_with_an_unchecked_proof_flags_it_for_refund_review`.
- [x] Sisa tagihan setelah reschedule dikirim ke halaman status (`outstanding`) dan email `RescheduleConfirmed`. Bukti: `BookingRescheduleTest` (tiga test baru).
- [ ] View email `resources/views/mail/reschedule-confirmed.blade.php` dan tampilan `outstanding` di `public/booking-status.blade.php` (tugas frontend).
