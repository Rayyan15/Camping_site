<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $date
 */
class SpecialPrice extends Model
{
    protected $fillable = ['unit_type_id', 'date', 'price', 'note'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'integer',
        ];
    }

    /** @return BelongsTo<UnitType, $this> */
    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }
}
