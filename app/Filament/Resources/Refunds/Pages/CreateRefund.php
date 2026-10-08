<?php

namespace App\Filament\Resources\Refunds\Pages;

use App\Exceptions\RefundException;
use App\Filament\Resources\Refunds\RefundResource;
use App\Models\Booking;
use App\Services\RefundService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateRefund extends CreateRecord
{
    protected static string $resource = RefundResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(RefundService::class)->request(
                Booking::findOrFail($data['booking_id']),
                $data['reason'],
                auth()->id(),
            );
        } catch (RefundException $e) {
            throw ValidationException::withMessages(['data.booking_id' => $e->getMessage()]);
        }
    }
}
