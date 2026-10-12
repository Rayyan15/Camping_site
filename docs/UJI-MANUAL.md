# Uji Manual

Daftar periksa untuk dicoba langsung di browser. Centang yang sudah dicoba. Tulis temuan di kolom Catatan, sesingkat apa pun ("tombol kekecilan", "harusnya Rp, bukan angka"). Temuan nanti dikerjakan per nomor.

Cara pakai: buka di HP (lebar layar kecil) dan di laptop. Pakai data uji, bukan data tamu asli.

## A. Halaman tamu

| No | Yang dicoba | Hasil yang diharapkan | Sudah | Catatan |
|---|---|---|---|---|
| A1 | Buka beranda `/` | Foto, cari tanggal, tipe tenda, menu, FAQ, kontak tampil rapi | [ ] | |
| A2 | Cari tanggal dan jumlah tamu | Muncul tipe tenda yang kosong beserta sisa unit | [ ] | |
| A3 | Buka detail tenda `/tenda/{tipe}` | Foto, fasilitas, harga, kalender ketersediaan | [ ] | |
| A4 | Pilih tenda, tambah tamu tambahan, isi data, kirim | Pindah ke halaman pembayaran | [ ] | |
| A5 | Halaman pembayaran | Rincian biaya, rekening, hitung mundur HH:MM:SS, form unggah bukti | [ ] | |
| A6 | Unggah bukti transfer (foto atau PDF) | Pindah ke status booking, tertulis menunggu konfirmasi | [ ] | |
| A7 | Biarkan hitung mundur habis | Tampil "Waktu pembayaran habis" dan link cari tanggal lagi | [ ] | |
| A8 | Cek booking `/cek-booking` | Bisa masuk dengan kode booking dan 4 digit telepon atau email | [ ] | |
| A9 | Status booking: ubah tanggal (reschedule) | Hanya bisa 1x, paling lambat H-7, harga dihitung ulang | [ ] | |
| A10 | Status booking: batalkan | H-7 atau lebih refund penuh, kurang dari itu hangus | [ ] | |
| A11 | Unduh invoice | File invoice terbuka dengan angka yang benar | [ ] | |
| A12 | Login tamu `/akun/masuk` | Tampilan beda dari login admin, ada ikon mata di kata sandi | [ ] | |
| A13 | Tombol WhatsApp melayang | Ikon WhatsApp saja, membuka chat admin | [ ] | |
| A14 | Blok kontak di bawah beranda | Rata tengah, semua link jalan (peta, WA, IG, TikTok) | [ ] | |

## B. Pesan lewat QR

| No | Yang dicoba | Hasil yang diharapkan | Sudah | Catatan |
|---|---|---|---|---|
| B1 | Scan QR tenda atau meja cafe | Menu tampil, yang habis tidak muncul | [ ] | |
| B2 | Pesan beberapa menu, kirim | Pindah ke halaman lacak pesanan | [ ] | |
| B3 | Halaman lacak pesanan | Status pesanan berubah sesuai dapur | [ ] | |

## C. Panel admin

| No | Yang dicoba | Hasil yang diharapkan | Sudah | Catatan |
|---|---|---|---|---|
| C1 | Login admin `/admin/login` | Tampilan baru, ikon mata di kata sandi, 2FA untuk owner | [ ] | |
| C2 | Tombol tema di atas | Satu tombol, bergantian terang dan gelap | [ ] | |
| C3 | Sidebar | Bisa dilipat dan dibuka, tanpa scrollbar terlihat | [ ] | |
| C4 | Semua tabel | Isi rata tengah, tombol aksi berupa ikon dengan tooltip | [ ] | |
| C5 | Dashboard | Angka dan kartu sesuai data, termasuk bukti transfer menunggu | [ ] | |
| C6 | Pembayaran: lihat bukti, konfirmasi, tolak (alasan wajib) | Booking jadi Lunas atau bukti ditolak, tamu dapat email | [ ] | |
| C7 | Booking: "Selesaikan review" | Terkunci selama bukti belum diperiksa atau uang kurang | [ ] | |
| C8 | Booking: reschedule dari admin | Aturan sama dengan tamu, opsi terima selisih harga | [ ] | |
| C9 | Booking: catat pembayaran manual | Sisa tagihan berkurang | [ ] | |
| C10 | Booking: check-in dan check-out | Ditolak sebelum tanggal check-in | [ ] | |
| C11 | Refund | Ajukan, setujui, nominal sesuai aturan H-7 | [ ] | |
| C12 | Tipe Unit | Tambah, ubah harga weekday dan weekend, foto | [ ] | |
| C13 | Unit dan Blokir Unit | Unit yang diblokir tidak bisa dipesan | [ ] | |
| C14 | Harga Khusus | Harga tanggal tertentu dipakai saat booking | [ ] | |
| C15 | Add-on (Extra Bed) | Isi harga, aktifkan, muncul di form booking tamu | [ ] | |
| C16 | Pengaturan | Pajak 0, jam check-in dan check-out, rekening transfer | [ ] | |
| C17 | Menu dan Kategori Menu | Tambah, ubah, tandai habis | [ ] | |
| C18 | Antrean Dapur | Pesanan QR masuk, ubah status, catat bayar | [ ] | |
| C19 | Pesanan Walk-in | Buat pesanan di kasir | [ ] | |
| C20 | Titik QR | Cetak atau putar ulang token QR | [ ] | |
| C21 | Kalender Okupansi | Booking tampil di tanggal yang benar | [ ] | |
| C22 | Laporan | Pendapatan, ekspor PDF dan Excel | [ ] | |
| C23 | Karyawan, Shift, Absensi, Rekap Absensi | Tambah karyawan, absen manual, rekap per bulan | [ ] | |
| C24 | Penilaian dan Kriteria Penilaian | Nilai bulanan dengan bobot 34/33/33 | [ ] | |
| C25 | Laporan Kebersihan | Setujui atau minta bersihkan ulang | [ ] | |
| C26 | Pelanggan | Riwayat menginap dan belanja | [ ] | |
| C27 | Pengguna | Tambah staf, peran, reset kata sandi | [ ] | |
| C28 | Log Aktivitas | Perubahan tercatat | [ ] | |
| C29 | Login sebagai operator FO dan kasir | Menu yang tidak boleh tidak tampil, laporan pendapatan hanya owner | [ ] | |

## Temuan lain

Tulis di sini hal yang tidak masuk tabel di atas.

-
