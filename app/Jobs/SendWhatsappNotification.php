<?php

namespace App\Jobs;

use App\Contracts\WhatsAppGateway;
use App\Enums\BookingStatus;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Booking;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\PhoneNumberNormalizer;
use App\Services\WhatsApp\WhatsAppConfigurationException;
use App\Services\WhatsApp\WhatsAppMessageComposer;
use App\Services\WhatsApp\WhatsAppSendResult;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWhatsappNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $bookingId,
        public readonly WhatsAppMessageType $type,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return $this->bookingId.':'.$this->type->value;
    }

    public function handle(WhatsAppGateway $gateway, WhatsAppMessageComposer $composer, PhoneNumberNormalizer $phones): void
    {
        $booking = Booking::with('customer')->find($this->bookingId);

        if ($booking === null) {
            return;
        }

        $record = WhatsAppMessage::firstOrCreate(
            ['booking_id' => $booking->id, 'type' => $this->type],
            ['status' => WhatsAppMessageStatus::Pending],
        );

        if ($record->status === WhatsAppMessageStatus::Sent) {
            return;
        }

        $phone = $phones->normalize($booking->customer?->phone);
        $record->to_masked = $phones->mask($phone ?? $booking->customer?->phone);

        $skipReason = $this->skipReason($booking, $phone);

        if ($skipReason !== null) {
            $this->finish($record, WhatsAppMessageStatus::Skipped, $skipReason);

            return;
        }

        $result = $this->deliver($gateway, $phone, $composer->compose($booking, $this->type), $record);

        if ($result->successful) {
            $record->provider_message_id = $result->providerMessageId;
            $record->sent_at = now();
            $this->finish($record, WhatsAppMessageStatus::Sent, null);

            return;
        }

        $this->finish($record, WhatsAppMessageStatus::Failed, $result->reason);
    }

    private function skipReason(Booking $booking, ?string $phone): ?string
    {
        if (! config('whatsapp.enabled')) {
            return 'Notifikasi WhatsApp dinonaktifkan (WHATSAPP_ENABLED=false).';
        }

        if (! in_array($booking->status, $this->eligibleStatuses(), true)) {
            return 'Status booking '.$booking->status->value.' tidak sesuai untuk pesan ini.';
        }

        if ($phone === null) {
            return 'Nomor telepon pelanggan tidak valid.';
        }

        return null;
    }

    /**
     * @return array<int, BookingStatus>
     */
    private function eligibleStatuses(): array
    {
        return match ($this->type) {
            WhatsAppMessageType::BookingCreated => [BookingStatus::PendingPayment],
            WhatsAppMessageType::PaymentConfirmed => [BookingStatus::Paid, BookingStatus::CheckedIn],
            WhatsAppMessageType::CheckInReminder => [BookingStatus::Paid],
            WhatsAppMessageType::BookingCancelled => [BookingStatus::Cancelled],
            WhatsAppMessageType::RefundApproved => [BookingStatus::Refunded],
        };
    }

    /**
     * A sender problem must end as a recorded failure, never as an exception into the booking flow.
     */
    private function deliver(WhatsAppGateway $gateway, string $phone, string $message, WhatsAppMessage $record): WhatsAppSendResult
    {
        try {
            return $gateway->send($phone, $message);
        } catch (WhatsAppConfigurationException $e) {
            Log::error('WhatsApp not configured', ['booking_id' => $record->booking_id, 'error' => $e->getMessage()]);

            return WhatsAppSendResult::failed($e->getMessage());
        } catch (Throwable $e) {
            Log::error('WhatsApp send crashed', [
                'booking_id' => $record->booking_id,
                'to' => $record->to_masked,
                'exception' => $e::class,
            ]);

            return WhatsAppSendResult::failed('Pengiriman gagal: '.$e::class);
        }
    }

    private function finish(WhatsAppMessage $record, WhatsAppMessageStatus $status, ?string $error): void
    {
        $record->status = $status;
        $record->error = $error;
        $record->save();

        if ($status !== WhatsAppMessageStatus::Sent) {
            Log::warning('WhatsApp message '.$status->value, [
                'booking_id' => $record->booking_id,
                'type' => $this->type->value,
                'to' => $record->to_masked,
                'reason' => $error,
            ]);
        }
    }
}
