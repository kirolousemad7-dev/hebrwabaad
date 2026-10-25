<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\PaymentAccount;
use App\Models\PaymentSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAccountService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): PaymentAccount
    {
        return DB::transaction(function () use ($attributes) {
            $method = PaymentMethod::from((string) $attributes['method']);
            $this->assertAccountFields($method, $attributes);

            $makeDefault = (bool) ($attributes['is_default'] ?? false)
                || ! PaymentAccount::query()->where('method', $method->value)->whereNull('archived_at')->exists();

            if ($makeDefault) {
                $this->clearDefault($method);
            }

            return PaymentAccount::query()->create([
                'method' => $method,
                'account_holder_name' => trim((string) $attributes['account_holder_name']),
                'instapay_address' => $this->nullable($attributes['instapay_address'] ?? null),
                'mobile_number' => $this->nullable($attributes['mobile_number'] ?? null),
                'bank_name' => $this->nullable($attributes['bank_name'] ?? null),
                'account_number' => $this->nullable($attributes['account_number'] ?? null),
                'iban' => $this->nullable($attributes['iban'] ?? null),
                'branch_name' => $this->nullable($attributes['branch_name'] ?? null),
                'instructions' => trim((string) $attributes['instructions']),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'is_default' => $makeDefault,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(PaymentAccount $account, array $attributes): PaymentAccount
    {
        return DB::transaction(function () use ($account, $attributes) {
            $method = $account->method instanceof PaymentMethod
                ? $account->method
                : PaymentMethod::from((string) $account->method);
            $merged = array_merge($account->only([
                'account_holder_name',
                'instapay_address',
                'mobile_number',
                'bank_name',
                'account_number',
                'iban',
                'branch_name',
                'instructions',
            ]), $attributes);
            $this->assertAccountFields($method, $merged);

            $account->fill([
                'account_holder_name' => trim((string) ($merged['account_holder_name'] ?? '')),
                'instapay_address' => $this->nullable($merged['instapay_address'] ?? null),
                'mobile_number' => $this->nullable($merged['mobile_number'] ?? null),
                'bank_name' => $this->nullable($merged['bank_name'] ?? null),
                'account_number' => $this->nullable($merged['account_number'] ?? null),
                'iban' => $this->nullable($merged['iban'] ?? null),
                'branch_name' => $this->nullable($merged['branch_name'] ?? null),
                'instructions' => trim((string) ($merged['instructions'] ?? '')),
            ]);

            if (array_key_exists('is_active', $attributes)) {
                $account->is_active = (bool) $attributes['is_active'];
            }

            $account->save();

            if (! empty($attributes['is_default'])) {
                $this->makeDefault($account);
            }

            return $account->fresh() ?? $account;
        });
    }

    public function makeDefault(PaymentAccount $account): PaymentAccount
    {
        return DB::transaction(function () use ($account) {
            if ($account->archived_at !== null) {
                throw ValidationException::withMessages([
                    'account' => ['لا يمكن تعيين حساب مؤرشف كحساب افتراضي.'],
                ]);
            }

            $method = $account->method instanceof PaymentMethod
                ? $account->method
                : PaymentMethod::from((string) $account->method);
            $this->clearDefault($method);
            $account->forceFill(['is_default' => true, 'is_active' => true])->save();

            return $account->fresh() ?? $account;
        });
    }

    public function archive(PaymentAccount $account): PaymentAccount
    {
        return DB::transaction(function () use ($account) {
            $wasDefault = $account->is_default;
            $method = $account->method instanceof PaymentMethod
                ? $account->method
                : PaymentMethod::from((string) $account->method);

            $account->forceFill([
                'is_active' => false,
                'is_default' => false,
                'archived_at' => now(),
            ])->save();

            if ($wasDefault) {
                $replacement = PaymentAccount::query()
                    ->receivable($method)
                    ->orderByDesc('id')
                    ->first();
                if ($replacement !== null) {
                    $replacement->forceFill(['is_default' => true])->save();
                }
            }

            return $account->fresh() ?? $account;
        });
    }

    public function resolve(PaymentMethod $method, ?int $accountId): PaymentAccount
    {
        if ($accountId !== null) {
            $account = PaymentAccount::query()->whereKey($accountId)->first();
            if ($account === null || ! $account->isReceivable()) {
                throw ValidationException::withMessages([
                    'payment_account_id' => ['حساب الاستلام غير متاح.'],
                ]);
            }
            $accountMethod = $account->method instanceof PaymentMethod
                ? $account->method
                : PaymentMethod::from((string) $account->method);
            if ($accountMethod !== $method) {
                throw ValidationException::withMessages([
                    'payment_account_id' => ['حساب الاستلام لا يطابق طريقة الدفع.'],
                ]);
            }

            return $account;
        }

        $account = PaymentAccount::query()
            ->receivable($method)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();

        if ($account !== null) {
            return $account;
        }

        $legacy = $this->materializeLegacy($method);
        if ($legacy !== null) {
            return $legacy;
        }

        throw ValidationException::withMessages([
            'payment_account_id' => ['لا يوجد حساب استلام نشط لهذه الطريقة.'],
        ]);
    }

    public function methodAvailable(PaymentMethod $method): bool
    {
        if ($method === PaymentMethod::Card) {
            return PaymentSetting::current()->card_enabled;
        }

        if (PaymentAccount::query()->where('method', $method->value)->exists()) {
            return PaymentAccount::query()->receivable($method)->exists();
        }

        $settings = PaymentSetting::current();

        return $method === PaymentMethod::Instapay
            ? $settings->instapayReady()
            : $settings->bankTransferReady();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(PaymentAccount $account): array
    {
        return [
            'id' => $account->id,
            'method' => $account->method instanceof PaymentMethod ? $account->method->value : (string) $account->method,
            'account_holder_name' => $account->account_holder_name,
            'instapay_address' => $account->instapay_address,
            'mobile_number' => $account->mobile_number,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'iban' => $account->iban,
            'branch_name' => $account->branch_name,
            'instructions' => $account->instructions,
            'is_default' => $account->is_default,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ownerPayload(PaymentAccount $account): array
    {
        return [
            ...$this->publicPayload($account),
            'is_active' => $account->is_active,
            'archived_at' => $account->archived_at?->toIso8601String(),
            'payments_count' => $account->payments()->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assertAccountFields(PaymentMethod $method, array $attributes): void
    {
        if (trim((string) ($attributes['account_holder_name'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'account_holder_name' => ['اسم صاحب الحساب مطلوب.'],
            ]);
        }

        if (trim((string) ($attributes['instructions'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'instructions' => ['تعليمات الدفع مطلوبة.'],
            ]);
        }

        if ($method === PaymentMethod::Instapay && trim((string) ($attributes['instapay_address'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'instapay_address' => ['عنوان إنستاباي مطلوب.'],
            ]);
        }

        if ($method === PaymentMethod::BankTransfer) {
            if (trim((string) ($attributes['bank_name'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'bank_name' => ['اسم البنك مطلوب.'],
                ]);
            }
            if (trim((string) ($attributes['account_number'] ?? '')) === '' && trim((string) ($attributes['iban'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'account_number' => ['رقم الحساب أو الآيبان مطلوب.'],
                ]);
            }
        }
    }

    private function clearDefault(PaymentMethod $method): void
    {
        PaymentAccount::query()
            ->where('method', $method->value)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function materializeLegacy(PaymentMethod $method): ?PaymentAccount
    {
        $settings = PaymentSetting::current();
        if ($method === PaymentMethod::Instapay && $settings->instapayReady()) {
            return $this->create([
                'method' => PaymentMethod::Instapay->value,
                'account_holder_name' => $settings->instapay_account_name,
                'instapay_address' => $settings->instapay_handle ?: $settings->instapay_account_number,
                'mobile_number' => $settings->instapay_phone,
                'instructions' => $settings->instapay_instructions,
                'is_active' => true,
                'is_default' => true,
            ]);
        }

        if ($method === PaymentMethod::BankTransfer && $settings->bankTransferReady()) {
            return $this->create([
                'method' => PaymentMethod::BankTransfer->value,
                'account_holder_name' => $settings->bank_account_name,
                'bank_name' => $settings->bank_name,
                'account_number' => $settings->bank_account_number,
                'iban' => $settings->bank_iban,
                'branch_name' => $settings->bank_branch,
                'instructions' => $settings->bank_instructions,
                'is_active' => true,
                'is_default' => true,
            ]);
        }

        return null;
    }

    private function nullable(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
