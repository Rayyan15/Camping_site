# Pertanyaan Terbuka

> Jawaban client 9 Oktober 2026 (pembayaran transfer manual, tanpa gateway, tanpa WA otomatis, rekomendasi mesin absensi) dan rencana kerjanya ada di `docs/internal/next-steps-client-answers-2026-10-09.md`.

Daftar hal yang hanya bisa dijawab owner atau yang masih menggantung. Pertanyaan PRD bagian 6.4 yang belum terjawab ikut dicantumkan.

## Menunggu keputusan owner

| # | Pertanyaan | Kenapa penting | Posisi sementara |
|---|---|---|---|
| 1 | Harga per tenda atau per orang? Ada biaya tambahan per orang di atas kapasitas? | Menentukan rumus harga | **Terjawab sebagian 10 Okt 2026:** harga dasar 150rb per malam sudah termasuk sarapan, kapasitas 4 orang. Nominal biaya per orang tambahan belum diberikan, lihat bagian data dari owner |
| 2 | Harga dan batas maksimal extra bed per tipe | Tagihan dan validasi kapasitas | **Terjawab 10 Okt 2026:** tambahan maksimal 2 orang per tenda (`booking.max_extra_guests_per_unit`). Harga diisi owner lewat add-on `Extra Bed` (bawaan 0 dan nonaktif) |
| 3 | Aturan refund pasti, dan apakah ada opsi jadwal ulang atau kredit | Menentukan tier dan jalur pembatalan | **Terjawab 10 Okt 2026:** H-7 atau lebih = 100%, kurang dari 7 hari = 0% (hangus). Seeder dua tier. Opsi jadwal ulang dikerjakan terpisah |
| 4 | DP atau lunas di depan | Fitur DP (FR-33) | **Terjawab 10 Okt 2026:** wajib lunas. DP bawaan 0 dan seeder menyimpan 0 |
| 5 | Persentase pajak dan biaya layanan, dan berlaku untuk apa | Hitungan total | **Terjawab 10 Okt 2026:** pajak 0. Default config dan seeder 0 |
| 6 | Jumlah meja atau area yang perlu QR selain tenda | Titik QR (`dining_spots`) | **Terjawab 10 Okt 2026:** hanya tenda dan meja cafe |
| 7 | Pembukuan apa yang tidak boleh dilihat operator selain pendapatan | Matriks akses | Laporan pendapatan owner saja |
| 8 | Merek dan model mesin fingerprint serta jumlah karyawan | Cara menarik log absensi | Impor file log umum (CSV atau teks tab ala ZKTeco) di balik antarmuka |
| 9 | Kriteria penilaian karyawan dan bobotnya | Penilaian bulanan | **Terjawab 10 Okt 2026:** Kehadiran, Kerapihan, Kesopanan, bobot sama rata (34/33/33). Owner bisa mengubah |
| 10 | Jam check-in dan check-out standar | Janji ke tamu | **Terjawab 10 Okt 2026:** check-in 14.00, check-out 12.00. Dibaca dari Pengaturan, tampil di FAQ |
| 11 | Midtrans atau Xendit | Gateway produksi | Midtrans diimplementasikan, Xendit belum |
| 12 | Boleh dapur melihat pre-order yang masih menunggu bayar? | Operasional dapur | Disembunyikan |
| 13 | Panjang kode booking, atau invoice butuh verifikasi tambahan? | Risiko enumerasi | Akses lewat `access_token`; kode tetap 6 karakter acak |
| 14 | Penyedia WhatsApp otomatis (Fonnte, Wablas, WA Cloud API) dan biayanya | Notifikasi FR-36 | Driver `log` bawaan; Fonnte tersedia bila token diisi |

## Menunggu data dari owner

- Nomor rekening bank tujuan transfer manual.
- Akun email pengirim (alamat dan kredensial SMTP).
- Konfirmasi bobot kriteria penilaian (saat ini sama rata).

## Terjawab

- **Harga 150rb per tipe dan harga Extra Bed:** terjawab, owner mengisi sendiri lewat panel (12 Okt 2026). Harga weekday dan weekend per tipe di menu Tipe Unit, harga tanggal khusus di menu Harga Khusus, Extra Bed di menu Add-on (isi harga lalu aktifkan).
- **Biaya per orang di atas kapasitas 4:** terjawab, owner isi sendiri lewat panel (12 Okt 2026). Resource Add-on (tambah, ubah, hapus) dengan kolom "Tambahan tamu per item" dan harga per malam; `PricingService` membaca dari harga add-on. Bawaan 0 dan nonaktif. Bukti: `ExtraGuestAddonTest`.
- **Jam periksa bukti transfer:** terjawab, 24 jam (usul tim, disetujui user 2026-10-12).

## Menggantung dari sisi teknis

- **Kontak:** nilai dari client (10 Okt 2026) sudah ada di `.env.example` dan `.env.production.example`. Salin ke `.env` server; tanpa itu blok kontak di landing tersembunyi.
- **Kunci Midtrans produksi** belum ada.
- **Workflow CI** belum pernah dijalankan di GitHub.
- **Larastan level 6** belum terpasang (PRD 7.1).
- **Race MySQL:** pada isolasi REPEATABLE READ, pemenang kedua pada skenario dua unit kadang mendapat "unit habis" padahal masih ada sisa. Tidak ada double booking; perbaikannya butuh retry di `pickFreeUnitIds`.
- **Uji browser** baru mencakup panel booking, notifikasi, antrean review, dan landing. Belum: checkout, status booking, QR menu, antrean dapur. Mobile hanya lewat iframe 390 px.
- **Salinan backup di luar server** (PRD 6.1) belum dipastikan.
- **Rotasi token QR otomatis saat check-out** sengaja tidak dibangun, lihat `DECISIONS.md`.
- **Perubahan belum di-commit.**
