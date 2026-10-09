<?php

namespace App\Models;

use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
