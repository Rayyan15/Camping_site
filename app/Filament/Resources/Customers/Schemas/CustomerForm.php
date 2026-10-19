<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->maxLength(80)
                    ->required(),
                TextInput::make('phone')
                    ->label('Nomor WhatsApp')
                    ->tel()
                    ->placeholder('08xxxxxxxxxx')
                    ->maxLength(20)
                    ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact'))
                    ->required(),
                TextInput::make('email')
                    ->label('Email (opsional)')
                    ->email()
                    ->maxLength(120)
                    ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact'))
                    ->default(null),
            ]);
    }
}
