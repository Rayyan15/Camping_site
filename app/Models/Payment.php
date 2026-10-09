<?php

namespace App\Models;

use App\Enums\PaymentDirection;
use App\Enums\PaymentFailureReason;
use App\Enums\PaymentMethod;
use App\Enums\PaymentReviewReason;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'payable_type', 'payable_id', 'direction', 'method', 'gateway_ref',
        'amount', 'status', 'paid_at', 'proof_path', 'raw_payload', 'recorded_by',
        'redirect_url', 'expires_at', 'failure_reason', 'review_reason',
    ];

    protected function casts(): array
    {
        return [
            'direction' => PaymentDirection::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'failure_reason' => PaymentFailureReason::class,
            'review_reason' => PaymentReviewReason::class,
            'raw_payload' => 'array',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isNetworkFailure(): bool
    {
        return $this->status === PaymentStatus::Failed
            && $this->failure_reason === PaymentFailureReason::GatewayUnreachable;
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Paid->value);
    }

    public function scopePaidBetween(Builder $query, string $from, string $until): Builder
    {
        return $query->where('paid_at', '>=', $from)->where('paid_at', '<', $until);
    }

    /** Net cash per payable type: money in minus money out (refunds). */
    public function scopeNetByPayableType(Builder $query): Builder
    {
        return $query
            ->selectRaw(
                'payable_type, SUM(CASE WHEN direction = ? THEN amount ELSE -amount END) as total',
                [PaymentDirection::In->value],
            )
            ->groupBy('payable_type');
    }

    /** One row per payment with its sign applied: money in is positive, refunds out are negative. */
    public function scopeWithSignedAmount(Builder $query): Builder
    {
        return $query
            ->select(['payable_type', 'paid_at'])
            ->selectRaw('CASE WHEN direction = ? THEN amount ELSE -amount END as signed_amount', [PaymentDirection::In->value]);
    }
}
