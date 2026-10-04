<?php
namespace App\Filament\Resources\Orders;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $modelLabel = 'Pesanan Makanan';
    protected static ?string $pluralModelLabel = 'Pesanan Makanan';
    public static function getNavigationGroup(): ?string { return 'Operasional'; }
    public static function getNavigationSort(): ?int { return 2; }
    public static function getNavigationIcon(): string|\Illuminate\View\ComponentAttributeBag { return 'heroicon-o-shopping-cart'; }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
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
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
