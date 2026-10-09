<?php

namespace App\Filament\Resources\Bookings;

use App\Filament\Resources\Bookings\Pages\EditBooking;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Schemas\BookingForm;
use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\View\ComponentAttributeBag;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $modelLabel = 'Booking';

    protected static ?string $pluralModelLabel = 'Daftar Booking';

    public static function getNavigationGroup(): ?string
    {
        return 'Operasional';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationIcon(): string|ComponentAttributeBag
    {
        return 'heroicon-o-calendar-days';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Booking::needsReview()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Booking yang perlu ditinjau';
    }

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'edit' => EditBooking::route('/{record}/edit'),
        ];
    }
}
