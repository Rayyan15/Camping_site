<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_proof')
                ->label('Lihat bukti')
                ->icon('heroicon-o-document-magnifying-glass')
                ->url(fn (Payment $record): string => route('admin.payments.proof', $record))
                ->openUrlInNewTab()
                ->visible(fn (Payment $record): bool => filled($record->proof_path)
                    && (bool) auth()->user()?->can('viewProof', $record)),
        ];
    }
}
