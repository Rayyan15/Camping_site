<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingStatus;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\BookingBilling;
use App\Services\BookingStatusTransition;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Pelanggan & Kode')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Kode Booking')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('customer_id')
                                ->label('Pelanggan')
                                ->relationship('customer', 'name')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Detail Menginap')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('check_in')
                                ->label('Check In')
                                ->disabled()
                                ->dehydrated(false),
                            DatePicker::make('check_out')
                                ->label('Check Out')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('guests')
                                ->label('Jumlah Tamu')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            Select::make('status')
                                ->label('Status Booking')
                                ->options(fn (?Booking $record): array => $record?->status
                                    ? app(BookingStatusTransition::class)->optionsFor($record->status)
                                    : [BookingStatus::PendingPayment->value => BookingStatus::PendingPayment->getLabel()])
                                ->required(),
                            DateTimePicker::make('hold_expires_at')
                                ->label('Batas Waktu Hold')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Rincian Pesanan')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('tent_list')
                                ->label('Tenda')
                                ->state(fn (?Booking $record): array => $record?->tentLabels() ?? [])
                                ->listWithLineBreaks()
                                ->placeholder('-'),
                            TextEntry::make('addon_list')
                                ->label('Tambahan')
                                ->state(fn (?Booking $record): array => $record ? self::addonLines($record) : [])
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->placeholder('Tidak ada'),
                            TextEntry::make('food_list')
                                ->label('Pesanan makanan')
                                ->state(fn (?Booking $record): array => $record ? self::foodLines($record) : [])
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->placeholder('Tidak ada'),
                        ]),
                    ]),

                Section::make('Rincian Biaya')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('subtotal')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            TextInput::make('total')
                                ->label('Total Akhir')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            TextInput::make('billed_food')
                                ->label('Makanan ditagih ke booking (scan QR)')
                                ->helperText('Di luar Total Akhir. Dibayar bersama sisa tagihan.')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn (?Booking $record): int => $record ? app(BookingBilling::class)->grandTotal($record) - $record->total : 0),
                            TextInput::make('paid_amount')
                                ->label('Sudah Dibayar')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->numeric(),
                            TextInput::make('outstanding')
                                ->label('Sisa Tagihan')
                                ->prefix('Rp')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn (?Booking $record): int => $record ? app(BookingBilling::class)->outstanding($record) : 0),
                            Textarea::make('notes')
                                ->label('Catatan Tambahan')
                                ->default(null)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    private static function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    /**
     * One dish per line with its price, so a long order no longer wraps into one comma run (owner, 13 Nov 2026).
     *
     * @return array<int, string>
     */
    private static function foodLines(Booking $booking): array
    {
        return $booking->orders
            ->reject(fn (Order $order): bool => $order->status === OrderStatus::Dibatalkan->value)
            ->flatMap(fn (Order $order) => $order->items->map(fn (OrderItem $item): string => $item->qty.'x '.$item->menuItem->name
                .' ('.self::rupiah($item->qty * $item->price).')'
                .($order->scheduled_at ? ', '.$order->scheduled_at->locale('id')->translatedFormat('D j/n H:i') : '')))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private static function addonLines(Booking $booking): array
    {
        return $booking->addons
            ->map(fn (BookingAddon $line): string => $line->qty.'x '.$line->addon->name.' ('.self::rupiah($line->subtotal).')')
            ->all();
    }
}
