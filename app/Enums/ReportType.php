<?php

namespace App\Enums;

enum ReportType: string
{
    case Booking = 'booking';
    case Occupancy = 'okupansi';
    case TopMenu = 'menu-terlaris';
    case Refund = 'refund';
    case PaymentMethod = 'pembayaran';
    case Revenue = 'pendapatan';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'Booking',
            self::Occupancy => 'Okupansi per tipe unit',
            self::TopMenu => 'Menu terlaris',
            self::Refund => 'Refund',
            self::PaymentMethod => 'Pembayaran per metode',
            self::Revenue => 'Pendapatan tenda dan F&B',
        };
    }

    /** The question each report answers, shown under its name on the report picker. */
    public function question(): string
    {
        return match ($this) {
            self::Booking => 'Tamu yang check-in di periode ini, dengan status dan pembayarannya.',
            self::Occupancy => 'Malam terisi dibanding malam tersedia, per tipe tenda.',
            self::TopMenu => 'Menu yang paling banyak terjual dan omzetnya.',
            self::Refund => 'Pengajuan pengembalian dana dan statusnya.',
            self::PaymentMethod => 'Uang masuk dan keluar, dikelompokkan per metode bayar.',
            self::Revenue => 'Uang masuk dipisah antara sewa tenda dan makanan minuman.',
        };
    }

    /**
     * Where a summary figure comes from, shown behind the small "i" next to its label. Keyed by the
     * summary label, so a label without an entry simply has no hint.
     */
    public function summaryHint(string $label): ?string
    {
        return match ($this) {
            self::Booking => match ($label) {
                'Jumlah booking' => 'Booking dengan tanggal check-in di periode ini, ditambah booking yang refund-nya diajukan di periode ini. Booking kedaluwarsa tidak dihitung. Angka di bagian Status adalah pecahan dari jumlah ini: satu booking punya satu status, jadi jika dijumlah hasilnya sama.',
                'Menunggu Bayar' => 'Tamu sudah memesan tapi belum membayar. Unit ditahan sampai batas waktu bayar; lewat dari itu booking jadi kedaluwarsa.',
                'Lunas' => 'Sudah bayar penuh, tapi staf belum menekan tombol Check-in di sistem. Tamu yang sudah datang dan pulang tetap tercatat Lunas jika tombol Check-in dan Check-out tidak ditekan.',
                'Check-in' => 'Staf sudah menekan tombol Check-in dan belum menekan Check-out. Jika tamu sudah pulang tapi masih di sini, tombol Check-out belum ditekan.',
                'Check-out' => 'Staf sudah menekan tombol Check-out: tamu sudah pulang dan tagihannya selesai.',
                'Perlu Ditinjau' => 'Batas waktu bayar sudah lewat, tapi sudah ada uang masuk atau bukti transfer yang belum diperiksa. Staf perlu memutuskan: lanjutkan booking atau batalkan dengan refund.',
                'Dibatalkan' => 'Booking yang dibatalkan. Tidak masuk ke total nilai booking terjual.',
                'Dikembalikan' => 'Booking yang dibatalkan dan uangnya sudah dikembalikan ke tamu.',
                'Sewa tenda' => 'Harga tenda dari booking terjual (Lunas, Check-in, Check-out) yang check-in di periode ini. Dihitung dari tanggal menginap, bukan tanggal bayar.',
                'Tambahan' => 'Add-on (extra bed, kayu bakar, aktivitas) dari booking terjual yang check-in di periode ini.',
                'Makanan' => 'Makanan yang ditagihkan ke booking terjual yang check-in di periode ini, termasuk pre-order dan pesanan QR tenda.',
                'Sudah dibayar' => 'Uang yang sudah diterima dari booking di tabel ini, kapan pun dibayarnya. Bisa termasuk uang yang dibayar sebelum periode ini.',
                'Belum dibayar' => 'Sisa tagihan booking terjual yang belum dibayar tamu, biasanya makanan yang ditagih ke booking.',
                'Kedaluwarsa' => 'Booking yang tidak dibayar sampai batas waktu. Hanya dihitung jumlahnya, tidak masuk tabel dan tidak masuk total.',
                default => null,
            },
            self::Occupancy => match ($label) {
                'Okupansi' => 'Malam terisi dibagi malam tersedia, dikali 100.',
                'Malam terisi' => 'Jumlah malam yang terjual di semua unit aktif selama periode ini.',
                'Malam tersedia' => 'Jumlah unit aktif dikali jumlah hari di periode ini, dikurangi malam saat unit diblokir.',
                default => null,
            },
            self::TopMenu => match ($label) {
                'Porsi terjual' => 'Porsi dari pesanan yang dibuat di periode ini dan sudah dibayar, atau ditagih ke booking yang sudah lunas.',
                'Omzet' => 'Porsi terjual dikali harga saat dipesan. Termasuk makanan yang ditagih ke booking walau tamu belum membayarnya.',
                'Ditagih ke booking, belum dibayar' => 'Bagian dari omzet yang masih ditagih ke booking dan belum dibayar tamu.',
                'Menu dengan stok dihitung' => 'Menu yang stoknya diisi. Angka stok selalu kondisi saat ini, bukan per periode.',
                'Menu habis' => 'Menu yang stoknya diisi dan saat ini tersisa 0 porsi.',
                default => null,
            },
            self::Refund => match ($label) {
                'Total dikembalikan' => 'Refund yang diajukan di periode ini dan sudah ditransfer ke tamu. Pengajuan yang belum ditransfer tidak dihitung.',
                default => null,
            },
            self::PaymentMethod => match ($label) {
                'Neto' => 'Uang masuk dikurangi refund keluar, dari pembayaran berhasil dengan tanggal bayar di periode ini.',
                'Uang masuk' => 'Semua pembayaran berhasil dengan tanggal bayar di periode ini: booking, pesanan QR, dan walk-in.',
                'Refund keluar' => 'Uang yang dikembalikan ke tamu dengan tanggal transfer di periode ini.',
                default => null,
            },
            self::Revenue => match ($label) {
                'Total neto' => 'Uang masuk dikurangi refund, dari pembayaran berhasil dengan tanggal bayar di periode ini. Sama dengan Neto di laporan Pembayaran per metode.',
                'Sewa tenda' => 'Bagian pembayaran booking yang menutup harga tenda, dihitung dari tanggal bayar. Tamu yang bayar sekarang untuk menginap bulan depan ikut masuk, jadi angkanya bisa berbeda dengan laporan Booking.',
                'Tambahan (add-on)' => 'Bagian pembayaran booking yang menutup add-on, setelah harga tenda lunas.',
                'F&B' => 'Makanan yang ditagih ke booking dan sudah dibayar, ditambah pesanan QR meja dan walk-in yang dibayar langsung.',
                default => null,
            },
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Booking => 'heroicon-o-calendar-days',
            self::Occupancy => 'heroicon-o-home-modern',
            self::TopMenu => 'heroicon-o-shopping-bag',
            self::Refund => 'heroicon-o-arrow-uturn-left',
            self::PaymentMethod => 'heroicon-o-credit-card',
            self::Revenue => 'heroicon-o-banknotes',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
