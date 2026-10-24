<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Validation\Validator;

trait ValidatesServiceDiscount
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->has('price_before_discount') && ! $this->has('discount_amount')) {
                return;
            }

            $service = $this->route('service');
            $current = $this->has('base_price')
                ? (float) ($this->input('base_price') ?? 0)
                : (float) ($service?->base_price ?? 0);
            $before = $this->optionalMoney('price_before_discount');
            $discount = $this->optionalMoney('discount_amount');

            if ($before !== null && $before < $current) {
                $validator->errors()->add(
                    'price_before_discount',
                    'The price before discount must not be less than the current price.',
                );
            }

            if ($before !== null && $discount !== null && $discount > 0 && $before > $current) {
                $expected = round($before - $current, 2);
                if (abs($expected - $discount) > 0.01) {
                    $validator->errors()->add(
                        'discount_amount',
                        'The discount amount must match the difference between the price before discount and the current price.',
                    );
                }
            }
        });
    }

    private function optionalMoney(string $key): ?float
    {
        if (! $this->has($key) || $this->input($key) === null || $this->input($key) === '') {
            return null;
        }

        return round((float) $this->input($key), 2);
    }
}
