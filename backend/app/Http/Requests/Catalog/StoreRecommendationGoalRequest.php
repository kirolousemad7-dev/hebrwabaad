<?php

namespace App\Http\Requests\Catalog;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreRecommendationGoalRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:recommendation_goals,slug'],
            'explanation' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.item_type' => ['required', Rule::in(['service', 'package', 'addon'])],
            'items.*.item_slug' => ['required', 'string', 'max:255'],
            'items.*.priority' => ['nullable', 'integer', 'min:0'],
            'items.*.reason_ar' => ['nullable', 'string', 'max:500'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
