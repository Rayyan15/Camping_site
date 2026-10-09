<?php

namespace App\Models;

use App\Services\SettingRepository;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        $flush = fn () => app(SettingRepository::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }
}
