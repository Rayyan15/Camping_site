<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const SYSTEM_ACTOR_LABEL = 'Sistem';

    protected $fillable = ['user_id', 'subject_type', 'subject_id', 'action', 'changes'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Entries written by webhooks, jobs or the console have no user. */
    protected function actorLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->user?->name ?? self::SYSTEM_ACTOR_LABEL);
    }
}
