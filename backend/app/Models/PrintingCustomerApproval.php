<?php

namespace App\Models;

use App\Enums\PrintingCustomerApprovalStatus;
use App\Enums\PrintingCustomerApprovalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'printing_request_id',
    'type',
    'status',
    'public_token_hash',
    'title',
    'decided_at',
    'notes',
])]
class PrintingCustomerApproval extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PrintingCustomerApprovalType::class,
            'status' => PrintingCustomerApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PrintingRequest, $this>
     */
    public function printingRequest(): BelongsTo
    {
        return $this->belongsTo(PrintingRequest::class);
    }

    public static function hashToken(string $rawToken): string
    {
        return hash_hmac('sha256', $rawToken, (string) config('app.key'));
    }

    public static function findByRawToken(string $rawToken): ?self
    {
        if ($rawToken === '') {
            return null;
        }

        return self::query()
            ->where('public_token_hash', self::hashToken($rawToken))
            ->first();
    }
}
