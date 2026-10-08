<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use App\Services\UserAccountService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->weight('bold'),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('roles.name')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => UserForm::ROLE_LABELS[$state] ?? $state),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                self::resetPasswordAction(),
            ])
            ->emptyStateHeading('Belum ada akun staf')
            ->striped()
            ->defaultSort('name');
    }

    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset password')
            ->icon('heroicon-o-key')
            ->color('warning')
            ->schema([UserForm::passwordField()])
            ->action(function (User $record, array $data): void {
                app(UserAccountService::class)->resetPassword($record, $data['password']);

                Notification::make()->title('Password diganti')->success()->send();
            });
    }
}
