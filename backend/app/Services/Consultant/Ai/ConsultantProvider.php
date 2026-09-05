<?php

namespace App\Services\Consultant\Ai;

use App\Support\Consultant\ConsultationState;

interface ConsultantProvider
{
    /**
     * @return array<string, mixed>
     */
    public function extract(string $message, ConsultationState $state): array;

    /**
     * @param  array<string, mixed>|null  $prompt
     * @param  array<string, mixed>|null  $diagnosis
     */
    public function narrate(ConsultationState $state, ?array $prompt, ?array $diagnosis): string;
}
