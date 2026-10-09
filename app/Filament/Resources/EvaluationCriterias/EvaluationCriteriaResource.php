<?php

namespace App\Filament\Resources\EvaluationCriterias;

use App\Filament\Resources\EvaluationCriterias\Pages\CreateEvaluationCriteria;
use App\Filament\Resources\EvaluationCriterias\Pages\EditEvaluationCriteria;
use App\Filament\Resources\EvaluationCriterias\Pages\ListEvaluationCriterias;
use App\Filament\Resources\EvaluationCriterias\Schemas\EvaluationCriteriaForm;
use App\Filament\Resources\EvaluationCriterias\Tables\EvaluationCriteriasTable;
use App\Models\EvaluationCriteria;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EvaluationCriteriaResource extends Resource
{
    protected static ?string $model = EvaluationCriteria::class;

    protected static ?string $modelLabel = 'Kriteria Penilaian';

    protected static ?string $pluralModelLabel = 'Kriteria Penilaian';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    public static function getNavigationGroup(): ?string
    {
        return 'SDM & Karyawan';
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function form(Schema $schema): Schema
    {
        return EvaluationCriteriaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EvaluationCriteriasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvaluationCriterias::route('/'),
            'create' => CreateEvaluationCriteria::route('/create'),
            'edit' => EditEvaluationCriteria::route('/{record}/edit'),
        ];
    }
}
