<?php

namespace App\Filament\Resources\Evaluations\Pages;

use App\Filament\Resources\Evaluations\EvaluationResource;
use App\Models\Evaluation;
use App\Services\EvaluationScoreCalculator;
use Filament\Resources\Pages\CreateRecord;
use LogicException;

class CreateEvaluation extends CreateRecord
{
    protected static string $resource = EvaluationResource::class;

    /**
     * The evaluation, its scores and the computed total are saved in one transaction, so a failed
     * calculation never leaves an evaluation stored with the placeholder score of 0.
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // total_score is a placeholder here; it is recomputed from the saved scores in afterCreate.
        $data['total_score'] = 0;
        $data['evaluated_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $evaluation = $this->getRecord();

        if (! $evaluation instanceof Evaluation) {
            throw new LogicException('CreateEvaluation can only save Evaluation records.');
        }

        app(EvaluationScoreCalculator::class)->refreshTotal($evaluation);
    }
}
