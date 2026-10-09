<?php

namespace App\Filament\Resources\EvaluationCriterias\Pages;

use App\Filament\Resources\EvaluationCriterias\EvaluationCriteriaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvaluationCriterias extends ListRecords
{
    protected static string $resource = EvaluationCriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
