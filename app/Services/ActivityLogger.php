<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes the audit trail (PRD FR-56). Sensitive attributes never reach the log in clear text.
 */
class ActivityLogger
{
    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_DELETED = 'deleted';

    public const ACTION_STATUS_CHANGED = 'status_changed';

    public const MASK = '[disembunyikan]';

    /** Attributes whose value is never stored. */
    private const HIDDEN_ATTRIBUTES = [
        'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes',
        'raw_payload', 'proof_path',
    ];

    /** Contact attributes that are stored partially masked. */
    private const MASKED_ATTRIBUTES = ['phone', 'email'];

    /** Bookkeeping columns that would only add noise. */
    private const IGNORED_ATTRIBUTES = ['created_at', 'updated_at'];

    public function created(Model $model): void
    {
        $this->write($model, self::ACTION_CREATED, [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $new = array_diff_key($model->getChanges(), array_flip(self::IGNORED_ATTRIBUTES));

        if ($new === []) {
            return;
        }

        $old = array_map(fn (string $key) => $model->getRawOriginal($key), array_keys($new));
        $action = array_key_exists('status', $new) ? self::ACTION_STATUS_CHANGED : self::ACTION_UPDATED;

        $this->write($model, $action, array_combine(array_keys($new), $old), $new);
    }

    public function deleted(Model $model): void
    {
        $this->write($model, self::ACTION_DELETED, $model->getAttributes(), []);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function write(Model $model, string $action, array $old, array $new): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'action' => $action,
            'changes' => ['old' => $this->sanitize($old), 'new' => $this->sanitize($new)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function sanitize(array $attributes): array
    {
        $clean = [];

        foreach (array_diff_key($attributes, array_flip(self::IGNORED_ATTRIBUTES)) as $key => $value) {
            $clean[$key] = match (true) {
                in_array($key, self::HIDDEN_ATTRIBUTES, true) => self::MASK,
                in_array($key, self::MASKED_ATTRIBUTES, true) => $this->maskContact($key, $value),
                default => $value,
            };
        }

        return $clean;
    }

    private function maskContact(string $key, mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if ($key === 'email') {
            [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

            return Str::mask($local, '*', 1).'@'.$domain;
        }

        return Str::mask($value, '*', 3, -2);
    }
}
