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

**Aturan refund di halaman dibaca dari tabel kebijakan** (`RefundPolicySummary`), bukan diketik. Jam check-in dan check-out tidak dijanjikan karena belum ada di sistem; FAQ menyuruh bertanya ke pengelola.

**Geser otomatis rail tenda.** Satu kartu tiap 4,5 detik, berhenti saat dihover, difokus, disentuh, tidak terlihat, atau tab tersembunyi; menunggu 8 detik setelah input manual; mati bila pengguna meminta kurangi gerakan. Scrollbar dan bar progres disembunyikan atas permintaan owner. Tombol jeda tetap ada di DOM dan hanya tampak saat difokus keyboard (WCAG 2.2.2).

## Brand

**Nama tampilan: Halimun Highland.** Sesuai logo dari owner (9 Oktober 2026). Nama ada di satu tempat (`config('site.name')`), dipakai header, footer, judul tab, invoice, pesan WA, dan panel. Logo asli di `public/images/brand/halimun-highland.png`; versi 160 px untuk tampilan dan versi berlingkaran krem untuk latar gelap. Logo biru tua tidak ditarik ke palet hijau situs; ia ditaruh di atas krem supaya kontrasnya 13:1.

## Proses

**`CLAUDE.md` bagian 6: antislop wajib untuk setiap kerja frontend.** Urutan: lihat, baca (muat skill lewat Skill tool), pahami (Design Read dan dial), sebut, ingat ulang. Arahan desain wajib ada sebelum UI dibangun: catatan di `docs/internal/design-notes.md` atau gambar referensi dari owner.

**Pekerjaan paralel lewat agent dengan batas file.** Setiap agent hanya boleh mengubah kumpulan file yang disebut di prompt-nya dan tidak boleh commit. File bersama (`config/access.php`, `RoleSeeder`, `AppServiceProvider`, `routes/web.php`) hanya ditambah barisnya.
