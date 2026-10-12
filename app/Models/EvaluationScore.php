<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property float $score
 */
class EvaluationScore extends Model
{
    public const MIN_SCORE = 0;

    public const MAX_SCORE = 100;

    protected $fillable = ['evaluation_id', 'criteria_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    /** @return BelongsTo<Evaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /** @return BelongsTo<EvaluationCriteria, $this> */
    public function criteria(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriteria::class, 'criteria_id');
    }
}
