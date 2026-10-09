<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\WhatsAppMessageType;
use App\Jobs\SendWhatsappNotification;
use App\Models\Booking;
use App\Models\WhatsAppMessage;
use Illuminate\Console\Command;

class SendWhatsAppReminders extends Command
{
    protected $signature = 'whatsapp:send-reminders';

    protected $description = 'Queue a WhatsApp check-in reminder for every paid booking that checks in tomorrow';

    public function handle(): int
    {
        if (! config('whatsapp.enabled')) {
            $this->info('Notifikasi WhatsApp nonaktif, tidak ada pengingat dikirim.');

            return self::SUCCESS;
        }

        $bookingIds = Booking::query()
            ->where('status', BookingStatus::Paid)
            ->whereDate('check_in', now()->addDay()->toDateString())
            ->whereNotIn('id', WhatsAppMessage::query()
                ->where('type', WhatsAppMessageType::CheckInReminder)
                ->select('booking_id'))
            ->pluck('id');

        foreach ($bookingIds as $id) {
            SendWhatsappNotification::dispatch($id, WhatsAppMessageType::CheckInReminder);
        }

        $this->info($bookingIds->count().' pengingat check-in diantrekan.');

        return self::SUCCESS;
    }
}
