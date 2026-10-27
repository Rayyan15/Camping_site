<?php

namespace App\Console\Commands;

use App\Services\DemoDataPurger;
use Illuminate\Console\Command;

class PurgeDemoData extends Command
{
    protected $signature = 'app:purge-demo-data
        {--force : Skip the confirmation prompt}
        {--skip-backup : Do not run app:backup-database first}';

    protected $description = 'Delete demo bookings, orders, guests, staff records and demo accounts before going live. Tent, menu and setting data stay.';

    public function handle(DemoDataPurger $purger): int
    {
        $this->components->warn('Semua booking, pesanan, pembayaran, tamu, karyawan, absensi, dan log akan dihapus permanen.');
        $this->components->info('Tipe tenda, unit, foto, harga, menu, spot QR, pengaturan, dan shift tetap ada.');

        if (! $purger->hasRealOwner()) {
            $this->components->warn('Belum ada akun owner asli, jadi akun demo @raynad.test tidak dihapus. Buat dulu dengan app:create-owner.');
        }

        if (! $this->option('force') && ! $this->confirm('Lanjut hapus data demo?')) {
            $this->components->info('Dibatalkan, tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        if (! $this->option('skip-backup') && $this->call('app:backup-database') !== self::SUCCESS) {
            $this->components->error('Backup gagal, data tidak dihapus.');

            return self::FAILURE;
        }

        $result = $purger->purge();

        $this->table(['Tabel', 'Baris dihapus'], collect($result['rows'])->map(fn (int $count, string $table) => [$table, $count])->values()->all());
        $this->components->info("Akun demo dihapus: {$result['demo_users_removed']}.");

        return self::SUCCESS;
    }
}
