<?php

namespace App\Models;

use App\Enums\SupplierVisibility;
use Database\Factories\SupplierDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'supplier_id',
    'uploaded_by',
    'title',
    'category',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'visibility',
    'metadata',
    'notes',
])]
class SupplierDocument extends Model
{
    /** @use HasFactory<SupplierDocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'disk' => 'local',
        'visibility' => SupplierVisibility::Internal->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'visibility' => SupplierVisibility::class,
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
