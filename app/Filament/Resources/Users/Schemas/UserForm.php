<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public const ROLE_LABELS = [
        User::ROLE_OWNER => 'Owner',
        User::ROLE_FRONT_OFFICE => 'Operator Front Office',
        User::ROLE_CASHIER => 'Operator Kasir/Dapur',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Nama')->required()->maxLength(255),
                        TextInput::make('email')->label('Email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                        TextInput::make('phone')->label('Telepon')->tel()->maxLength(32),
                        Select::make('role')
                            ->label('Peran')
                            ->options(self::ROLE_LABELS)
                            ->required(),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                        self::passwordField()->visibleOn('create'),
                    ]),
            ]);
    }

    public static function passwordField(): TextInput
    {
        $minLength = (int) config('access.min_password_length');

        return TextInput::make('password')
            ->label('Password')
            ->password()
            ->revealable()
            ->required()
            ->minLength($minLength)
            ->helperText('Minimal '.$minLength.' karakter.');
    }
}
