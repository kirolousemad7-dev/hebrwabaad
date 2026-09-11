<?php

namespace App\Models;

use App\Enums\PrintingDeliveryMethod;
use App\Enums\PrintingDeliveryStatus;
use Database\Factories\PrintingDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'printing_request_id',
    'method',
    'provider',
    'status',
    'external_reference',
    'tracking_url',
    'recipient_name',
    'contact_name',
    'contact_phone',
    'notes',
    'scheduled_at',
    'scheduled_window',
    'delivered_at',
    'proof_file_id',
    'metadata',
    'created_by',
])]
class PrintingDelivery extends Model
{
    /** @use HasFactory<PrintingDeliveryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PrintingDeliveryMethod::class,
            'status' => PrintingDeliveryStatus::class,
            'scheduled_at' => 'datetime',
            'delivered_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<PrintingRequest, $this>
     */
    public function printingRequest(): BelongsTo
    {
        return $this->belongsTo(PrintingRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<ManagedFile, $this>
     */
    public function proofFile(): BelongsTo
    {
        return $this->belongsTo(ManagedFile::class, 'proof_file_id');
    }
}
