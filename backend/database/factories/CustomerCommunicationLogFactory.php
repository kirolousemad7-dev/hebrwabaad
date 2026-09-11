<?php

namespace Database\Factories;

use App\Models\CustomerCommunicationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCommunicationLog>
 */
class CustomerCommunicationLogFactory extends Factory
{
    protected $model = CustomerCommunicationLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'channel' => 'email',
            'template' => 'printing_quotation',
            'status' => 'skipped',
            'related_type' => null,
            'related_id' => null,
            'result_summary' => 'Test log',
            'sent_at' => null,
        ];
    }
}
