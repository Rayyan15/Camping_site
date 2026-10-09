<?php

namespace App\Models;

use App\Exceptions\ReferencedSetupRecordException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCriteria extends Model
{
    public const MIN_WEIGHT = 1;

    public const MAX_WEIGHT = 100;

    public const TOTAL_WEIGHT = 100;

    protected $fillable = ['name', 'weight', 'is_attendance', 'is_active'];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'is_attendance' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (EvaluationCriteria $criteria): void {
            if ($criteria->scores()->exists()) {
                throw ReferencedSetupRecordException::criteria();
            }
        });
    }

    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class, 'criteria_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function activeWeightTotal(?int $exceptId = null): int
    {
        return (int) static::query()
            ->active()
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->sum('weight');
    }
}
