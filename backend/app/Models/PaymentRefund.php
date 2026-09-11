<?php

namespace App\Models;

use App\Enums\PaymentRefundStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id',
    'provider',
    'provider_refund_reference',
    'amount',
    'currency',
    'status',
    'reason',
    'is_manual',
    'requested_by',
    'requested_at',
    'processed_at',
    'failed_at',
    'failure_code',
    'failure_message',
    'metadata',
])]
class PaymentRefund extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentRefundStatus::class,
            'is_manual' => 'boolean',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
