<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property float $total_score
 */
class Evaluation extends Model
{
    protected $fillable = ['employee_id', 'period', 'total_score', 'notes', 'evaluated_by'];

    protected function casts(): array
    {
        return ['total_score' => 'float'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return HasMany<EvaluationScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
    }

    /** @return BelongsTo<User, $this> */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }
}
