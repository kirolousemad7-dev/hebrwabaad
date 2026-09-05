<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\ApiFormRequest;

class UpdatePaymentSettingsRequest extends ApiFormRequest
{
    /**
     * Owner-managed manual payment accounts. Card gateway credentials are never
     * accepted here; PayTabs keys stay in server environment configuration.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card_enabled' => ['sometimes', 'boolean'],
            'instapay_enabled' => ['sometimes', 'boolean'],
            'instapay_account_name' => ['nullable', 'string', 'max:120'],
            'instapay_bank_name' => ['nullable', 'string', 'max:120'],
            'instapay_account_number' => ['nullable', 'string', 'max:80'],
            'instapay_handle' => ['nullable', 'string', 'max:120'],
            'instapay_phone' => ['nullable', 'string', 'max:40'],
            'instapay_instructions' => ['nullable', 'string', 'max:2000'],
            'instapay_notes' => ['nullable', 'string', 'max:2000'],
            'bank_transfer_enabled' => ['sometimes', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:80'],
            'bank_iban' => ['nullable', 'string', 'max:80'],
            'bank_swift' => ['nullable', 'string', 'max:32'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'bank_instructions' => ['nullable', 'string', 'max:2000'],
            'bank_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
