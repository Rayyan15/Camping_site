<?php

namespace App\Filament\Resources\RefundPolicies;

use App\Filament\Resources\RefundPolicies\Pages\CreateRefundPolicy;
use App\Filament\Resources\RefundPolicies\Pages\EditRefundPolicy;
use App\Filament\Resources\RefundPolicies\Pages\ListRefundPolicies;
use App\Filament\Resources\RefundPolicies\Schemas\RefundPolicyForm;
use App\Filament\Resources\RefundPolicies\Tables\RefundPoliciesTable;
use App\Models\RefundPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RefundPolicyResource extends Resource
{
    protected static ?string $model = RefundPolicy::class;

    protected static ?string $modelLabel = 'Aturan Refund';

    protected static ?string $pluralModelLabel = 'Aturan Refund';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    public static function getNavigationGroup(): ?string
    {
        return 'Laporan & Keuangan';
    }

    public static function form(Schema $schema): Schema
    {
        return RefundPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RefundPoliciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefundPolicies::route('/'),
            'create' => CreateRefundPolicy::route('/create'),
            'edit' => EditRefundPolicy::route('/{record}/edit'),
        ];
    }
}
