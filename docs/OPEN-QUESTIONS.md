# Pertanyaan Terbuka

> Jawaban client 9 Oktober 2026 (pembayaran transfer manual, tanpa gateway, tanpa WA otomatis, rekomendasi mesin absensi) dan rencana kerjanya ada di `docs/internal/next-steps-client-answers-2026-10-09.md`.

Daftar hal yang hanya bisa dijawab owner atau yang masih menggantung. Pertanyaan PRD bagian 6.4 yang belum terjawab ikut dicantumkan.

## Menunggu keputusan owner

| # | Pertanyaan | Kenapa penting | Posisi sementara |
|---|---|---|---|
| 1 | Harga per tenda atau per orang? Ada biaya tambahan per orang di atas kapasitas? | Menentukan rumus harga | Harga per tenda per malam |
| 2 | Harga dan batas maksimal extra bed per tipe | Tagihan dan validasi kapasitas | Add-on `Extra Bed` per malam, tanpa batas per tipe |
| 3 | Aturan refund pasti, dan apakah ada opsi jadwal ulang atau kredit | Menentukan tier dan jalur pembatalan | Tabel tier 7 hari 100%, 3 hari 50%, 0 hari 0% (data seeder) |
| 4 | DP atau lunas di depan | Fitur DP (FR-33) | DP sudah dibangun dan mati secara bawaan (0%). Owner mengaktifkan dengan mengisi persen di Pengaturan |
| 5 | Persentase pajak dan biaya layanan, dan berlaku untuk apa | Hitungan total | Dibaca dari tabel settings oleh `PricingService::taxFor` |
| 6 | Jumlah meja atau area yang perlu QR selain tenda | Titik QR (`dining_spots`) | Seeder membuat titik untuk tenda dan beberapa meja |
| 7 | Pembukuan apa yang tidak boleh dilihat operator selain pendapatan | Matriks akses | Laporan pendapatan owner saja |
| 8 | Merek dan model mesin fingerprint serta jumlah karyawan | Cara menarik log absensi | Impor file log umum (CSV atau teks tab ala ZKTeco) di balik antarmuka |
| 9 | Kriteria penilaian karyawan dan bobotnya | Penilaian bulanan | Resource kriteria tersedia, isi oleh owner |
| 10 | Jam check-in dan check-out standar | Janji ke tamu | Tidak dijanjikan; FAQ menyuruh bertanya ke pengelola |
| 11 | Midtrans atau Xendit | Gateway produksi | Midtrans diimplementasikan, Xendit belum |
| 12 | Boleh dapur melihat pre-order yang masih menunggu bayar? | Operasional dapur | Disembunyikan |
| 13 | Panjang kode booking, atau invoice butuh verifikasi tambahan? | Risiko enumerasi | Akses lewat `access_token`; kode tetap 6 karakter acak |
| 14 | Penyedia WhatsApp otomatis (Fonnte, Wablas, WA Cloud API) dan biayanya | Notifikasi FR-36 | Driver `log` bawaan; Fonnte tersedia bila token diisi |

## Menggantung dari sisi teknis

- **Konfigurasi kosong:** alamat, link peta, nomor WhatsApp, dan Instagram belum diisi (`SITE_ADDRESS`, `SITE_MAPS_URL`, `SITE_WHATSAPP_NUMBER`, `SITE_INSTAGRAM_URL`). Blok kontak di landing tersembunyi sampai diisi.
- **Kunci Midtrans produksi** belum ada.
- **Workflow CI** belum pernah dijalankan di GitHub.
- **Larastan level 6** belum terpasang (PRD 7.1).
- **Race MySQL:** pada isolasi REPEATABLE READ, pemenang kedua pada skenario dua unit kadang mendapat "unit habis" padahal masih ada sisa. Tidak ada double booking; perbaikannya butuh retry di `pickFreeUnitIds`.
- **Uji browser** baru mencakup panel booking, notifikasi, antrean review, dan landing. Belum: checkout, status booking, QR menu, antrean dapur. Mobile hanya lewat iframe 390 px.
- **Salinan backup di luar server** (PRD 6.1) belum dipastikan.
- **Rotasi token QR otomatis saat check-out** sengaja tidak dibangun, lihat `DECISIONS.md`.
- **Perubahan belum di-commit.**
