<?php

namespace App\Filament\Resources\EvaluationCriterias\Pages;

use App\Filament\Resources\EvaluationCriterias\EvaluationCriteriaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvaluationCriteria extends EditRecord
{
    protected static string $resource = EvaluationCriteriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
