<?php
namespace App\Filament\Resources\Refunds;
use App\Filament\Resources\Refunds\Pages\CreateRefund;
use App\Filament\Resources\Refunds\Pages\EditRefund;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Filament\Resources\Refunds\Schemas\RefundForm;
use App\Filament\Resources\Refunds\Tables\RefundsTable;
use App\Models\Refund;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;

    protected static ?string $modelLabel = 'Pengembalian Dana';
    protected static ?string $pluralModelLabel = 'Pengembalian Dana';
    public static function getNavigationGroup(): ?string { return 'Keuangan'; }
    public static function getNavigationSort(): ?int { return 1; }
    public static function getNavigationIcon(): string|\Illuminate\View\ComponentAttributeBag { return 'heroicon-o-banknotes'; }

    public static function form(Schema $schema): Schema
    {
        return RefundForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return RefundsTable::configure($table);
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
            'index' => ListRefunds::route('/'),
            'create' => CreateRefund::route('/create'),
            'edit' => EditRefund::route('/{record}/edit'),
        ];
    }
}
