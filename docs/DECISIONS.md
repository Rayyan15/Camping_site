# Catatan Keputusan

Setiap keputusan: apa yang dipilih, kenapa, dan konsekuensinya. Keputusan yang masih menunggu owner ada di `OPEN-QUESTIONS.md`.

## Booking dan unit

**Jaminan anti double booking berlapis.** Lapis 1: `lockForUpdate` pada baris unit di dalam transaksi. Lapis 2: tabel `booking_unit_nights` dengan unique `(unit_id, night)` lewat `UnitNightLedger`. Alasannya: lock baris tidak berlaku di SQLite dan bisa terlewat; unique constraint berlaku di semua driver. Malam check-out tidak diklaim (rentang setengah terbuka), jadi tamu baru boleh masuk di hari check-out tamu lama.

**Booking `needs_review` tidak punya baris ledger.** Malamnya sudah dipegang booking lain, jadi mengklaim akan melanggar unique. Resolusi hanya lewat dua jalur eksplisit: pindah ke unit lain yang bebas, atau batalkan dengan refund penuh yang mengabaikan tabel tier (kesalahan ada di pihak kita). Keduanya hanya untuk pemegang permission `approve_refund`.

**Pembatalan paid dengan tier 0% adalah pembatalan, bukan refund.** Booking menjadi `Cancelled`, unit dilepas, dan alasannya dicatat (`cancelled_without_refund` di log aktivitas serta kolom `cancelled_at` dan `cancellation_note`). Tidak ada baris Refund bernilai 0, supaya laporan keuangan bersih dan aturan "refund 0 ditolak" tetap benar.

**Pre-order milik booking `pending_payment` disembunyikan dari dapur.** Dapur tidak boleh menyiapkan makanan untuk transaksi yang belum pasti. Visibilitas diberikan lewat penghitung terpisah "Pre-order Menunggu Pembayaran" di dashboard. Satu sumber filter: `Order::scopeKitchenRelevant`.

**Booking tidak bisa dihapus dari panel.** Audit keuangan. Guard di model (`DeletionGuardObserver`) menolak hapus customer yang punya booking, unit yang punya riwayat, dan booking yang sudah punya payment, refund, atau order. FK `restrictOnDelete` tidak diubah karena mengubah FK di MySQL tanpa bisa mengujinya berisiko; guard model menutup kasusnya di semua driver kecuali SQL mentah.

## Pembayaran

**Satu sumber hitungan tagihan.** `BookingBilling` menghitung total booking ditambah F&B yang ditagihkan ke booking, dengan pajak dari `PricingService::taxFor`. Invoice, pembayaran online, dan validasi pembayaran manual memakainya.

**Pembayaran ganda tidak menaikkan `paid_amount` melebihi tagihan.** Payment kedua tetap dicatat `Paid` dengan `review_reason = overpaid` supaya staf bisa menelusuri refund-nya.

**Kegagalan jaringan ke gateway tidak final.** Payment ditandai `Failed` dengan alasan `gateway_unreachable`; settlement yang datang belakangan boleh memulihkannya. Kegagalan HTTP 500 tetap final.

**Pendapatan dashboard = masuk dikurangi keluar.** Refund mengurangi pendapatan camping, tidak pernah F&B.

**DP opsional dan mati secara bawaan.** Tamu hanya memilih "bayar penuh" atau "DP"; nominal selalu dihitung server dari persen di Pengaturan. Setelah DP, unit dipegang sampai akhir hari check-in. Booking ber-DP yang lewat batas hold masuk `needs_review`, tidak `expired`, karena uang yang sudah masuk tidak boleh hilang dari tampilan mana pun.

**Order QR yang dibayar online disembunyikan dari dapur sampai lunas.** Kolom `orders.payment_choice` membedakannya dari order "bayar ke kasir". Bila pembayaran online gagal, tamu bisa membuka ulang dari halaman lacak, atau kasir menandai lunas dan pesanan langsung masuk antrean.

**Token QR tenda tidak dirotasi otomatis saat check-out.** QR dicetak dan ditempel di tenda, jadi rotasi tiap tamu berarti cetak ulang. Tagihan ke booking sudah hanya untuk booking Paid atau CheckedIn, sehingga tamu yang sudah keluar tidak bisa menagihkan apa pun.

## Akses publik dan keamanan

**Kode booking adalah pengenal, bukan kredensial.** Format `RCM-yymmdd-XXXXXX` (PRD FR-18) tetap dipakai untuk komunikasi. Akses ke status, invoice, checkout, dan pembatalan memakai `access_token` acak 40 karakter. Pencarian manual lewat kode butuh verifikasi kedua: 4 digit terakhir telepon atau email. Respons kegagalan seragam supaya keberadaan kode tidak bocor.

**Halaman QR tenda tidak menampilkan identitas tamu.** Opsi "Tagihkan ke booking tenda ini" tetap ada, tetapi booking yang ditagih ditentukan di server. Token QR bisa diregenerasi dari panel. Rotasi otomatis saat check-out belum dibangun karena belum ada hook check-out khusus.

**Notifikasi tidak boleh menggagalkan pembayaran.** Event `BookingNeedsReview` dikirim setelah commit (`ShouldDispatchAfterCommit`), dan pencari owner tidak memakai `User::role()` yang melempar bila role belum ada.

**Staf masuk lewat footer.** Tombol Login Admin dipindah dari nav ke footer supaya tidak bersaing dengan tombol "Cek tanggal".

## Frontend

**Konsep landing: satu malam di perkemahan.** Empat fase (Sore, Petang, Malam, Subuh) tanpa jam, supaya tidak terbaca sebagai jadwal yang dijanjikan. Detail di `FRONTEND-DESIGN.md`.

**Aturan refund di halaman dibaca dari tabel kebijakan** (`RefundPolicySummary`), bukan diketik. Jam check-in (14.00) dan check-out (12.00) dibaca dari Pengaturan lewat `SettingRepository` dan tampil di FAQ.

**Geser otomatis rail tenda.** Satu kartu tiap 4,5 detik, berhenti saat dihover, difokus, disentuh, tidak terlihat, atau tab tersembunyi; menunggu 8 detik setelah input manual; mati bila pengguna meminta kurangi gerakan. Scrollbar dan bar progres disembunyikan atas permintaan owner. Tombol jeda tetap ada di DOM dan hanya tampak saat difokus keyboard (WCAG 2.2.2).

## Brand

**Nama tampilan: Halimun Highland.** Sesuai logo dari owner (9 Oktober 2026). Nama ada di satu tempat (`config('site.name')`), dipakai header, footer, judul tab, invoice, pesan WA, dan panel. Logo asli di `public/images/brand/halimun-highland.png`; versi 160 px untuk tampilan dan versi berlingkaran krem untuk latar gelap. Logo biru tua tidak ditarik ke palet hijau situs; ia ditaruh di atas krem supaya kontrasnya 13:1.

## Jawaban client 10 Oktober 2026

**Refund dua langkah.** H-7 atau lebih 100%, kurang dari itu 0%. `RefundService::calculate` tidak diubah karena sudah memilih tier terbesar yang tidak melebihi sisa hari. Seeder menghapus tier lama 3 hari 50% saat dijalankan ulang.

**Lunas di depan, tanpa pajak.** DP bawaan 0 dan `SettingsSeeder` menyimpan 0; pajak default config 0. Nilai seeder tidak menimpa baris yang sudah diubah owner.

**Kapasitas 4 + 2.** Seeder menyamakan kapasitas semua tipe ke 4. Validasi booking menolak lebih dari `booking.max_extra_guests_per_unit` (2) tamu tambahan per tenda. Biaya per orang tambahan tidak diisi karena nominalnya belum diberikan: add-on `Extra Bed` bawaan harga 0 dan nonaktif, owner mengaktifkan setelah mengisi harga. `PricingService` sudah menghitung harga add-on per malam.

**Sarapan.** Fakta "harga dasar sudah termasuk sarapan" disimpan sebagai `booking.base_price_includes_breakfast` dan tampil di FAQ, bukan di kartu tenda karena harga tipe belum dikonfirmasi.

**Mesin absensi fingerprint di-hold (12 Okt 2026).** Bukan dibatalkan. Absensi dicatat manual lewat panel dulu; endpoint ADMS dan integrasi mesin tidak dikerjakan sampai owner memutuskan merek dan model.

## Proses

**`CLAUDE.md` bagian 6: antislop wajib untuk setiap kerja frontend.** Urutan: lihat, baca (muat skill lewat Skill tool), pahami (Design Read dan dial), sebut, ingat ulang. Arahan desain wajib ada sebelum UI dibangun: catatan di `docs/internal/design-notes.md` atau gambar referensi dari owner.

**Pekerjaan paralel lewat agent dengan batas file.** Setiap agent hanya boleh mengubah kumpulan file yang disebut di prompt-nya dan tidak boleh commit. File bersama (`config/access.php`, `RoleSeeder`, `AppServiceProvider`, `routes/web.php`) hanya ditambah barisnya.


## Reschedule booking dan bayar online QR dihapus (12 Okt 2026)

**Bayar online di pesanan QR dihapus.** `QrPaymentChoice::Online`, `PaymentService::initiateForOrder` dan `settleOrder`, `QrOrderController::pay`, route `qr.pay`, panel "Menunggu pembayaran", dan `Order::awaitsOnlinePayment` sudah dihapus. Pesanan QR dan walk-in selalu tampil di dapur. Kolom `orders.payment_choice` dipertahankan (isinya cashier atau booking).

**Reschedule booking, tepat satu kali (keputusan client 10 Okt 2026).** Layanan: `BookingRescheduleService`.
- Syarat: status Lunas, `reschedule_count` di bawah `booking.reschedule.max_times` (1), check-in lama masih H-7 atau lebih (`min_days_before_check_in`), tidak ada pengajuan refund terbuka. Aturan sama untuk tamu dan admin; admin tidak bisa melewati batas H-7 atau kuota satu kali.
- Unit: tiap baris tetap di unit yang sama bila kosong di tanggal baru, kalau tidak pindah ke unit kosong termurah dengan tipe sama. Unit dikunci, malam diklaim ulang di ledger dalam savepoint; gagal berarti seluruh perubahan dibatalkan.
- Harga selalu dihitung ulang server lewat `PricingService` (unit dan add-on per malam). Makanan pre-order dibawa apa adanya.
- Harga naik: tamu ditolak dengan pesan jelas dan diminta hubungi admin. Admin boleh menerima lewat opsi "Terima selisih harga"; booking tetap Lunas, selisih muncul sebagai sisa tagihan dan dibayar lewat aksi Catat pembayaran.
- Harga turun: kelebihan bayar dicatat di `bookings.reschedule_credit` untuk refund manual; ditampilkan ke tamu dan masuk notifikasi staf.
- Jejak: entri audit `rescheduled`, event `BookingRescheduled` setelah commit, notifikasi database ke owner dan operator FO. Kolom baru: `reschedule_count`, `reschedule_credit`, `rescheduled_at`.
- Izin aksi admin memakai `record_manual_payment` (owner dan operator FO), supaya `config/access.php` tidak berubah.

## Pembayaran transfer manual (10 Oktober 2026)

**Transfer bank manual, dikonfirmasi admin.** `PAYMENT_GATEWAY=manual` jadi bawaan. Kode Midtrans dan `FakeGateway` dibiarkan tidak aktif; `ManualGateway` menolak setiap webhook, jadi tidak ada jalur di luar panel admin yang bisa menandai pembayaran lunas. Bila gateway tidak pernah dipakai, hapus seluruh jalur gateway beserta test-nya dalam satu PR terpisah.

**Wajib lunas di UI checkout.** Pilihan DP tidak ditampilkan. Kolom `plan` di `POST /checkout/{token}/bukti` dan kode DP di `TransferProofService` dibiarkan supaya DP bisa dihidupkan lagi tanpa mengubah endpoint. Route `checkout.pay` (gateway) dihapus.

**Tenda ditahan 2 jam** (`BOOKING_HOLD_MINUTES=120`). Saat bukti transfer masuk, tahanan diperpanjang `BOOKING_PROOF_REVIEW_HOURS` (bawaan 24 jam, keputusan owner) agar tenda tidak lepas selama admin memeriksa mutasi. Bila bukti ditolak, tahanan dipotong kembali ke 2 jam sejak penolakan.

**Rekening dari `config/site.php`** (`SITE_BANK_NAME`, `SITE_BANK_ACCOUNT_NUMBER`, `SITE_BANK_ACCOUNT_HOLDER`). Tidak ada nomor yang ditulis di kode. Selama salah satu kosong, halaman pembayaran menampilkan "Rekening belum diatur, hubungi admin." dan form upload disembunyikan; server juga menolak unggahan.

**Satu bukti pending per booking.** Unggahan baru menandai yang lama Expired. Bukti disimpan di disk privat `local`, folder `payment-proofs`, dilihat staf lewat `admin.payments.proof`. Maksimal 4 MB, JPG/PNG/WebP/PDF.

**Konfirmasi dan penolakan lewat `TransferProofService`.** Konfirmasi memakai aturan yang sama dengan pembayaran manual (`PaymentService::applyConfirmedTransfer`). Bukti untuk booking yang sudah dibatalkan atau direfund tidak bisa dikonfirmasi. Penolakan wajib beralasan, tersimpan di `payments.rejection_reason`.

**Booking dengan bukti pending tidak expired diam-diam.** `ReleaseExpiredHolds` memindahkannya ke `needs_review`, sama seperti booking ber-DP. Catatan: aksi "Selesaikan review" memindahkan booking ke Paid, jadi untuk booking yang buktinya belum dikonfirmasi staf harus mengonfirmasi atau menolak bukti dulu.

**Email tamu lewat antrean, setelah commit:** Booking diterima, Bukti transfer diterima, Pembayaran dikonfirmasi, Bukti ditolak. Isi: tamu mengecek email dan menunjukkan bukti konfirmasi di lokasi. Tidak dikirim bila tamu tidak punya email. `MAIL_FROM_NAME="Halimun Highland"`; kredensial SMTP diisi owner di `.env` server.

**Notifikasi staf:** notifikasi database ke setiap pengguna aktif dengan izin `record_manual_payment` saat bukti masuk, plus filter, lencana navigasi, dan kartu "Bukti Transfer Menunggu" di dashboard.

**Tombol WhatsApp ke admin** (`WhatsAppShareLink::forAdmin`) di halaman checkout dan status, hanya bila `SITE_WHATSAPP_NUMBER` terisi. Pengiriman WA otomatis tetap mati.

## Pengaman review, bukti pending saat batal, email reschedule (12 Okt 2026)

**"Selesaikan review" menunggu uang benar-benar masuk.** `BookingReviewService` menolak memindahkan booking Perlu Ditinjau menjadi Lunas bila masih ada bukti transfer yang belum diperiksa atau `paid_amount` kurang dari `total`. Aksi di panel dinonaktifkan dengan tooltip alasan yang sama; penjaga di service tetap berlaku untuk jalur lain.

**Booking batal dengan bukti transfer pending ditandai untuk refund.** Saat booking dibatalkan atau direfund, bukti yang belum diperiksa tetap `Pending` (staf masih bisa mengecek mutasi) dan diberi `review_reason = proof_pending_on_cancel`. Muncul di filter "Perlu ditinjau" pembayaran, sama seperti tanda "dana masuk setelah booking dibatalkan".

**Email konfirmasi reschedule.** Setiap reschedule mengirim email `RescheduleConfirmed` ke tamu (antrean, setelah commit, dilewati bila tanpa email) berisi sisa tagihan dan kredit kelebihan bayar saat itu. Halaman status menerima `outstanding` (sisa tagihan) untuk booking Lunas.

**Produksi memakai SQLite, bukan MySQL (17 Oktober 2026).** Trafik di bawah 100 pengguna per hari, satu server, jadi SQLite cukup dan lebih ringan dirawat. Koneksi memakai mode WAL, `busy_timeout` 5 detik, dan `synchronous=normal` (bisa diubah lewat `DB_JOURNAL_MODE`, `DB_BUSY_TIMEOUT`, `DB_SYNCHRONOUS`) supaya tamu tetap bisa membaca saat queue worker menulis. Double booking tetap dijaga unique key `booking_unit_nights`; `lockForUpdate` tidak berpengaruh di SQLite, dan karena penulis SQLite hanya satu pada satu waktu, transaksi sudah berurutan. Backup harian memakai `VACUUM INTO` (salinan konsisten tanpa menghentikan aplikasi), disimpan sebagai `*.sqlite.gz`. Pindah ke MySQL bila trafik tumbuh jauh atau aplikasi dipecah ke lebih dari satu server; kode dan test MySQL (`BookingConcurrencyMysqlTest`) tetap ada.
