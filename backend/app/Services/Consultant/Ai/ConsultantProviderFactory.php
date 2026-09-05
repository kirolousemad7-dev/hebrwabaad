<?php

namespace App\Services\Consultant\Ai;

use App\Services\Consultant\ConsultantSettings;

class ConsultantProviderFactory
{
    public function __construct(
        private readonly RuleBasedConsultantProvider $rules,
        private readonly OpenAiConsultantProvider $openai,
    ) {}

    public function make(): ConsultantProvider
    {
        if (ConsultantSettings::provider() === 'openai' && filled(config('consultant.openai.api_key'))) {
            return $this->openai;
        }

        return $this->rules;
    }
}
