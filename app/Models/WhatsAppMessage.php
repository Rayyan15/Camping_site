<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property WhatsAppMessageType $type
 * @property WhatsAppMessageStatus $status
 * @property ?Carbon $sent_at
 */
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'booking_id', 'type', 'to_masked', 'status', 'provider_message_id', 'error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => WhatsAppMessageType::class,
            'status' => WhatsAppMessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
