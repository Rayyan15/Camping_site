<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\ComponentAttributeBag;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'Akun Staf';

    protected static ?string $pluralModelLabel = 'Akun Staf';

    public static function getNavigationGroup(): ?string
    {
        return 'Pengaturan';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationIcon(): string|ComponentAttributeBag
    {
        return 'heroicon-o-users';
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /**
     * Guest accounts have no role and are managed from Pelanggan; listing them here would push the
     * owner into giving a guest a staff role just to edit it.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('roles');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
