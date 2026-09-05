<?php

namespace App\Services\Consultant\Ai;

use App\Services\Consultant\ConversationExtractor;
use App\Support\Consultant\ConsultationState;

class RuleBasedConsultantProvider implements ConsultantProvider
{
    public function __construct(
        private readonly ConversationExtractor $extractor,
    ) {}

    public function extract(string $message, ConsultationState $state): array
    {
        return $this->extractor->extract($message);
    }

    public function narrate(ConsultationState $state, ?array $prompt, ?array $diagnosis): string
    {
        if (is_array($diagnosis)) {
            return $diagnosis['summary'] ?? 'بناءً على إجاباتك جهّزنا تشخيصاً وتوصية من الخدمات والباقات المتاحة حالياً.';
        }

        if (is_array($prompt)) {
            $title = (string) ($prompt['title'] ?? '');
            $body = (string) ($prompt['body'] ?? '');

            return trim($title.($body !== '' ? "\n\n".$body : ''));
        }

        return 'أخبرني بالمزيد عن نشاطك أو هدفك وسأكمل من هناك.';
    }
}
