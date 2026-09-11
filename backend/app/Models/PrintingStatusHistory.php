<?php

namespace App\Models;

use App\Enums\PrintingRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'printing_request_id',
    'from_status',
    'to_status',
    'actor_id',
    'note',
])]
class PrintingStatusHistory extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => PrintingRequestStatus::class,
            'to_status' => PrintingRequestStatus::class,
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
