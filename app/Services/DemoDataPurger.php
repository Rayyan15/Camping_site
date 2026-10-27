<?php

namespace App\Services;

use App\Models\User;
use App\Services\Payment\TransferProofService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Empties guest, transaction and staff records before the system goes live, while
 * keeping what the owner configured: tent types, units, photos, prices, menu, spots,
 * settings, refund policy, shifts and evaluation criteria.
 */
class DemoDataPurger
{
    /** DemoUserSeeder creates its accounts on this domain. */
    public const DEMO_EMAIL_DOMAIN = '@raynad.test';

    /** Child tables come first so a foreign key never blocks a delete. */
    private const TABLES = [
        'order_items',
        'orders',
        'booking_unit_nights',
        'booking_addons',
        'refunds',
        'payments',
        'booking_units',
        'bookings',
        'customers',
        'whatsapp_messages',
        'evaluation_scores',
        'evaluations',
        'attendances',
        'cleaning_logs',
        'employees',
        'unit_blocks',
        'activity_logs',
        'notifications',
    ];

    private const CLEANING_PHOTO_DISK = 'local';

    /**
     * @return array{rows: array<string, int>, demo_users_removed: int}
     */
    public function purge(): array
    {
        $proofs = DB::table('payments')->whereNotNull('proof_path')->pluck('proof_path')->all();
        $cleaningPhotos = DB::table('cleaning_logs')->pluck('photo_path')->all();

        $result = DB::transaction(function (): array {
            $rows = [];
            foreach (self::TABLES as $table) {
                $rows[$table] = DB::table($table)->delete();
            }

            return ['rows' => $rows, 'demo_users_removed' => $this->removeDemoUsers()];
        });

        // Files go only after the commit, so a rolled back purge never loses an upload.
        Storage::disk(TransferProofService::DISK)->delete($proofs);
        Storage::disk(self::CLEANING_PHOTO_DISK)->delete($cleaningPhotos);

        return $result;
    }

    public function hasRealOwner(): bool
    {
        return User::role(User::ROLE_OWNER)
            ->where('email', 'not like', '%'.self::DEMO_EMAIL_DOMAIN)
            ->exists();
    }

    /** Demo accounts stay until a real owner exists, so nobody is locked out of the panel. */
    private function removeDemoUsers(): int
    {
        if (! $this->hasRealOwner()) {
            return 0;
        }

        $demoUsers = User::where('email', 'like', '%'.self::DEMO_EMAIL_DOMAIN)->get();
        $demoUsers->each->delete();

        return $demoUsers->count();
    }
}
