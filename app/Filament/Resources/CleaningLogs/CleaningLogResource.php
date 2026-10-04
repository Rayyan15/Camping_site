<?php
namespace App\Filament\Resources\CleaningLogs;
use App\Filament\Resources\CleaningLogs\Pages\CreateCleaningLog;
use App\Filament\Resources\CleaningLogs\Pages\EditCleaningLog;
use App\Filament\Resources\CleaningLogs\Pages\ListCleaningLogs;
use App\Filament\Resources\CleaningLogs\Schemas\CleaningLogForm;
use App\Filament\Resources\CleaningLogs\Tables\CleaningLogsTable;
use App\Models\CleaningLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
class CleaningLogResource extends Resource
{
    protected static ?string $model = CleaningLog::class;

    protected static ?string $modelLabel = 'Laporan Kebersihan';
    protected static ?string $pluralModelLabel = 'Laporan Kebersihan';
    public static function getNavigationGroup(): ?string { return 'Laporan'; }
    public static function getNavigationSort(): ?int { return 1; }
    public static function getNavigationIcon(): string|\Illuminate\View\ComponentAttributeBag { return 'heroicon-o-clipboard-document-check'; }

    public static function form(Schema $schema): Schema
    {
        return CleaningLogForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return CleaningLogsTable::configure($table);
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
            'index' => ListCleaningLogs::route('/'),
            'create' => CreateCleaningLog::route('/create'),
            'edit' => EditCleaningLog::route('/{record}/edit'),
        ];
    }
}
