<?php

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Services\EvaluationScoreCalculator;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvaluation extends EditRecord
{
    protected static string $resource = EvaluationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['evaluated_by'] = auth()->id();

        return $data;
    }

    protected function afterSave(): void
    {
        app(EvaluationScoreCalculator::class)->refreshTotal($this->record);
    }
}
