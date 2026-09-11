<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'inbound_webhook_integration_id',
    'integration_type',
    'event',
    'delivery_id',
    'status',
    'response_status',
    'result_summary',
    'payload_meta',
    'received_at',
    'processed_at',
])]
class InboundWebhookReceipt extends Model
{
    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_meta' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'response_status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<InboundWebhookIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(InboundWebhookIntegration::class, 'inbound_webhook_integration_id');
    }
}
