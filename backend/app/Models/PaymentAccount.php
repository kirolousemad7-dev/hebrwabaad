<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'method',
    'account_holder_name',
    'instapay_address',
    'mobile_number',
    'bank_name',
    'account_number',
    'iban',
    'branch_name',
    'instructions',
    'is_active',
    'is_default',
    'archived_at',
])]
class PaymentAccount extends Model
{
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PaymentAccount>  $query
     * @return Builder<PaymentAccount>
     */
    public function scopeReceivable(Builder $query, PaymentMethod $method): Builder
    {
        return $query
            ->where('method', $method->value)
            ->where('is_active', true)
            ->whereNull('archived_at');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isReceivable(): bool
    {
        return $this->is_active && $this->archived_at === null;
    }
}
