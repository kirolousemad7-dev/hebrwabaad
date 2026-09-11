<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'delivery_id',
    'outbound_webhook_id',
    'event',
    'idempotency_key',
    'payload',
    'status',
    'attempt_count',
    'response_status',
    'response_summary',
    'delivered_at',
    'next_retry_at',
    'failed_at',
])]
class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt_count' => 'integer',
            'response_status' => 'integer',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OutboundWebhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(OutboundWebhook::class, 'outbound_webhook_id');
    }
}
