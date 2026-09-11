<?php

namespace App\Models;

use App\Enums\CatalogPricingMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_id',
    'service_id',
    'quantity',
    'pricing_mode',
    'unit_price',
    'currency',
    'notes',
    'sort_order',
])]
class OrderItem extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'currency' => 'SAR',
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'pricing_mode' => CatalogPricingMode::class,
            'unit_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<OrderItemAddon, $this>
     */
    public function addons(): HasMany
    {
        return $this->hasMany(OrderItemAddon::class);
    }

    /**
     * @return HasOne<Task, $this>
     */
    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    public function isChargeable(): bool
    {
        $mode = $this->pricing_mode instanceof CatalogPricingMode
            ? $this->pricing_mode
            : CatalogPricingMode::tryFrom((string) $this->pricing_mode);

        return $mode === CatalogPricingMode::Fixed
            && $this->unit_price !== null
            && (float) $this->unit_price > 0;
    }

    public function lineTotal(): ?string
    {
        if (! $this->isChargeable()) {
            return null;
        }

        $total = (float) $this->unit_price * max(1, (int) $this->quantity);

        return number_format($total, 2, '.', '');
    }
}
