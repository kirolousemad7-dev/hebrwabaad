<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtoWebhookEvent extends Model
{
    protected $fillable = [
        'event_type',
        'oto_order_id',
        'idempotency_key',
        'payload',
        'signature',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
