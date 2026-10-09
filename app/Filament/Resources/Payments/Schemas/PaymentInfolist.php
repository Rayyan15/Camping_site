<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\PaymentLabels;
use App\Models\Booking;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transaksi')
                ->columns(3)
                ->schema([
                    TextEntry::make('direction')
                        ->label('Arah')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => PaymentLabels::direction($state))
                        ->color(fn ($state): string => PaymentLabels::directionColor($state)),
                    TextEntry::make('amount')
                        ->label('Nominal')
                        ->money('IDR', locale: 'id', decimalPlaces: 0),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => PaymentLabels::status($state))
                        ->color(fn ($state): string => PaymentLabels::statusColor($state)),
                    TextEntry::make('method')
                        ->label('Metode')
                        ->formatStateUsing(fn ($state): string => PaymentLabels::method($state)),
                    TextEntry::make('paid_at')
                        ->label('Waktu bayar')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Belum dibayar'),
                    TextEntry::make('recorded_by')
                        ->label('Dicatat oleh')
                        ->state(fn (Payment $record): string => $record->recorded_by
                            ? (User::find($record->recorded_by)?->name ?? 'Akun dihapus')
                            : 'Sistem'),
                    TextEntry::make('gateway_ref')
                        ->label('Referensi gateway')
                        ->placeholder('Tidak ada')
                        ->copyable(),
                ]),
            Section::make('Terkait')
                ->columns(3)
                ->schema([
                    TextEntry::make('payable_type')
                        ->label('Jenis')
                        ->state(fn (Payment $record): string => PaymentLabels::payableType($record)),
                    TextEntry::make('payable_code')
                        ->label('Kode')
                        ->state(fn (Payment $record): string => PaymentLabels::payableCode($record))
                        ->url(fn (Payment $record): ?string => self::payableUrl($record)),
                    TextEntry::make('payer')
                        ->label('Atas nama')
                        ->state(fn (Payment $record): string => PaymentLabels::payerName($record)),
                ]),
            Section::make('Catatan sistem')
                ->visible(fn (Payment $record): bool => PaymentLabels::attentionNote($record) !== null)
                ->schema([
                    TextEntry::make('attention')
                        ->hiddenLabel()
                        ->state(fn (Payment $record): ?string => PaymentLabels::attentionNote($record)),
                ]),
        ]);
    }

    private static function payableUrl(Payment $payment): ?string
    {
        $payable = $payment->payable;

        return match (true) {
            $payable instanceof Booking && BookingResource::canEdit($payable) => BookingResource::getUrl('edit', ['record' => $payable]),
            $payable instanceof Order && OrderResource::canEdit($payable) => OrderResource::getUrl('edit', ['record' => $payable]),
            default => null,
        };
    }
}
