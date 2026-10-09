<?php

namespace App\Models;

use App\Observers\UnitTypePhotoObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(UnitTypePhotoObserver::class)]
class UnitTypePhoto extends Model
{
    /** Tent photos are public marketing material, so they live on the public disk. */
    public const DISK = 'public';

    protected $fillable = ['unit_type_id', 'path', 'sort_order'];

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk(self::DISK)->url($this->path));
    }
}
