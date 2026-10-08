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
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact'))
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->visible(fn (): bool => (bool) auth()->user()?->can('view_customer_contact'))
                    ->default(null),
                TextInput::make('user_id')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
