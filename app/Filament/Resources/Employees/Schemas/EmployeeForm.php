<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('name')
                    ->required(),
                TextInput::make('position')
                    ->default(null),
                TextInput::make('fingerprint_id')
                    ->default(null),
                TextInput::make('shift_id')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
