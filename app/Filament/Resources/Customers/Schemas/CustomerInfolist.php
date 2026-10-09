<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pelanggan')
                ->schema([
                    TextEntry::make('name')
                        ->label('Nama'),
                    TextEntry::make('phone')
                        ->label('Telepon')
                        ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact')),
                    TextEntry::make('email')
                        ->label('Email')
                        ->placeholder('-')
                        ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact')),
                ])
                ->columns(3),
            Section::make('Ringkasan belanja')
                ->description('Kunjungan adalah booking berstatus Lunas, Check-in, atau Check-out. Total belanja adalah pembayaran masuk dikurangi refund untuk booking tersebut, ditambah pembayaran pesanan makanan yang tertaut ke booking itu.')
                ->schema([
                    TextEntry::make('visit_count')
                        ->label('Jumlah kunjungan')
                        ->numeric(),
                    TextEntry::make('total_spend')
                        ->label('Total belanja')
                        ->money('IDR', locale: 'id', decimalPlaces: 0),
                    TextEntry::make('last_visit_at')
                        ->label('Kunjungan terakhir')
                        ->date('d M Y')
                        ->placeholder('Belum pernah menginap'),
                ])
                ->columns(3)
                ->visible(fn (): bool => (bool) auth()->user()?->can('viewSpend', Customer::class)),
        ]);
    }
}
