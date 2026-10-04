<?php

namespace App\Filament\Resources\Refunds\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RefundForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Data Pengembalian Dana (Refund)')
                    ->schema([
                        \Filament\Schemas\Components\Grid::make(2)->schema([
                            \Filament\Forms\Components\Select::make('booking_id')
                                ->label('Terkait Booking')
                                ->relationship('booking', 'code')
                                ->required(),
                            TextInput::make('amount')
                                ->label('Nominal Pengembalian')
                                ->prefix('Rp')
                                ->required()
                                ->numeric(),
                            \Filament\Forms\Components\Select::make('status')
                                ->label('Status Refund')
                                ->options([
                                    'requested' => 'Diajukan',
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Ditolak',
                                    'processed' => 'Sudah Ditransfer'
                                ])
                                ->required()
                                ->default('requested'),
                            \Filament\Forms\Components\Textarea::make('reason')
                                ->label('Alasan Batal / Refund')
                                ->columnSpanFull(),
                        ])
                    ])
            ]);
    }
}
