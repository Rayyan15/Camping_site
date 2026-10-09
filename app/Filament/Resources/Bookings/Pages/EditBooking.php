<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Actions\BookingActions;
use App\Filament\Resources\Bookings\BookingResource;
use App\Services\BookingStatusTransition;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    /**
     * A status change from the form goes through the transition service so the
     * lock, the ledger release and the activity log all run.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $target = BookingStatus::from($data['status']);

        if ($target !== $record->status) {
            app(BookingStatusTransition::class)->apply($record, $target);
        }

        unset($data['status']);

        return parent::handleRecordUpdate($record->refresh(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            BookingActions::checkIn(),
            BookingActions::checkOut(),
            BookingActions::recordPayment(),
            BookingActions::resolveReview(),
        ];
    }
}
