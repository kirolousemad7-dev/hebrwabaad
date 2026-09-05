<?php

namespace App\Models;

use Database\Factories\PaymentSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'card_enabled',
    'instapay_enabled',
    'instapay_account_name',
    'instapay_bank_name',
    'instapay_account_number',
    'instapay_handle',
    'instapay_phone',
    'instapay_instructions',
    'instapay_notes',
    'bank_transfer_enabled',
    'bank_name',
    'bank_account_name',
    'bank_account_number',
    'bank_iban',
    'bank_swift',
    'bank_branch',
    'bank_instructions',
    'bank_notes',
])]
class PaymentSetting extends Model
{
    /** @use HasFactory<PaymentSettingFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'card_enabled' => true,
        'instapay_enabled' => true,
        'bank_transfer_enabled' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_enabled' => 'boolean',
            'instapay_enabled' => 'boolean',
            'bank_transfer_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $existing = self::query()->first();

        if ($existing !== null) {
            return $existing;
        }

        return self::query()->create([
            'card_enabled' => true,
            'instapay_enabled' => true,
            'bank_transfer_enabled' => false,
        ]);
    }

    /**
     * InstaPay is only offered once the owner entered an account the customer can transfer to.
     */
    public function instapayReady(): bool
    {
        return $this->instapay_enabled
            && filled($this->instapay_account_name)
            && (filled($this->instapay_account_number) || filled($this->instapay_handle))
            && filled($this->instapay_instructions);
    }

    /**
     * Bank transfer is only offered once the owner entered an account the customer can transfer to.
     */
    public function bankTransferReady(): bool
    {
        return $this->bank_transfer_enabled
            && filled($this->bank_name)
            && filled($this->bank_account_name)
            && (filled($this->bank_account_number) || filled($this->bank_iban))
            && filled($this->bank_instructions);
    }
}
