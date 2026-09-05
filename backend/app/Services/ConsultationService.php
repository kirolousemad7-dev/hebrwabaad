<?php

namespace App\Services;

use App\Enums\ConsultationStatus;
use App\Models\Consultation;
use App\Models\ConsultationEvent;
use App\Models\ConsultationLead;
use App\Models\User;
use App\Services\Consultant\Ai\ConsultantProviderFactory;
use App\Services\Consultant\CatalogContext;
use App\Services\Consultant\ConsultantSettings;
use App\Services\Consultant\DiagnosisBuilder;
use App\Services\Consultant\QuestionEngine;
use App\Services\Consultant\ReadinessScoreCalculator;
use App\Services\Consultant\RecommendationEngine;
use App\Support\Consultant\BusinessCatalog;
use App\Support\Consultant\ConsultationState;
use Illuminate\Support\Str;
use Throwable;

class ConsultationService
{
    public function __construct(
        private readonly QuestionEngine $questions,
        private readonly DiagnosisBuilder $diagnosis,
        private readonly ReadinessScoreCalculator $readiness,
        private readonly RecommendationEngine $recommendations,
        private readonly CatalogContext $catalog,
        private readonly ConsultantProviderFactory $providers,
    ) {}

    public function enabled(): bool
    {
        return ConsultantSettings::enabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function publicConfig(): array
    {
        return [
            'enabled' => $this->enabled(),
            ...BusinessCatalog::publicConfig(),
        ];
    }

    public function start(?User $user = null): Consultation
    {
        $state = ConsultationState::empty();
        $state->set('current_step', 'help_mode');
        $state->appendMessage('ai', "مرحباً بك في مستشار حبر الذكي.\n\nدعنا نفهم نشاطك وأهدافك، ثم نرشّح الحل المناسب من خدمات وباقات المنصة.");

        $consultation = Consultation::query()->create([
            'public_token' => Str::random(64),
            'user_id' => $user?->id,
            'status' => ConsultationStatus::InProgress,
            'state' => $state->toArray(),
        ]);

        $this->recordEvent($consultation, 'ai_consultation_started');

        return $this->withPrompt($consultation, $state, welcome: true);
    }

    public function findByToken(string $token): Consultation
    {
        return Consultation::query()
            ->where('public_token', $token)
            ->with('lead')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function answer(Consultation $consultation, string $questionId, mixed $value): Consultation
    {
        $state = ConsultationState::fromArray($consultation->state ?? []);
        $state->setAnswer($questionId, $value);
        $state->set('current_step', $questionId);
        $state->appendMessage('user', $this->answerLabel($questionId, $value));

        if ($questionId === 'business_category') {
            $this->recordEvent($consultation, 'business_type_selected', ['value' => $value]);
        }
        if ($questionId === 'goals') {
            $this->recordEvent($consultation, 'goal_selected', ['value' => $value]);
        }

        return $this->advance($consultation, $state);
    }

    public function message(Consultation $consultation, string $text): Consultation
    {
        $state = ConsultationState::fromArray($consultation->state ?? []);
        $state->appendMessage('user', $text);

        $provider = $this->providers->make();

        try {
            $extracted = $provider->extract($text, $state);
        } catch (Throwable) {
            $extracted = [];
        }

        if (is_array($extracted) && ($extracted['answers']['dine_channels'] ?? null)) {
            $state->setAnswer('dine_channels', $extracted['answers']['dine_channels']);
        }

        $state->mergeExtracted(is_array($extracted) ? $extracted : []);

        return $this->advance($consultation, $state);
    }

    public function reset(Consultation $consultation): Consultation
    {
        if ($consultation->status === ConsultationStatus::InProgress) {
            $this->recordEvent($consultation, 'consultation_abandoned');
            $consultation->status = ConsultationStatus::Abandoned;
            $consultation->save();
        }

        return $this->start($consultation->user);
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null, business_name?: string|null, contact_method?: string}  $data
     */
    public function captureLead(Consultation $consultation, array $data): ConsultationLead
    {
        $lead = $consultation->lead()->updateOrCreate(
            ['consultation_id' => $consultation->id],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'business_name' => $data['business_name'] ?? $consultation->state['business_name'] ?? null,
                'contact_method' => $data['contact_method'] ?? 'email',
            ]
        );

        $this->recordEvent($consultation, 'quote_requested', ['source' => 'lead']);

        return $lead;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function recordEvent(Consultation $consultation, string $name, ?array $payload = null): void
    {
        $allowed = [
            'ai_consultation_started',
            'business_type_selected',
            'goal_selected',
            'consultation_completed',
            'recommendation_viewed',
            'package_clicked',
            'service_clicked',
            'quote_requested',
            'consultation_abandoned',
        ];

        if (! in_array($name, $allowed, true)) {
            return;
        }

        ConsultationEvent::query()->create([
            'consultation_id' => $consultation->id,
            'name' => $name,
            'payload' => $payload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Consultation $consultation): array
    {
        $state = ConsultationState::fromArray($consultation->state ?? []);
        $prompt = $consultation->status === ConsultationStatus::Completed
            ? null
            : $this->questions->next($state);

        return $this->toPayload($consultation, $state, $prompt);
    }

    private function advance(Consultation $consultation, ConsultationState $state): Consultation
    {
        if ($this->questions->readyForRecommendation($state)) {
            return $this->complete($consultation, $state);
        }

        return $this->withPrompt($consultation, $state);
    }

    private function withPrompt(Consultation $consultation, ConsultationState $state, bool $welcome = false): Consultation
    {
        $prompt = $this->questions->next($state);
        $provider = $this->providers->make();

        try {
            $narration = $provider->narrate($state, $prompt, null);
        } catch (Throwable) {
            $narration = is_array($prompt) ? (string) ($prompt['title'] ?? '') : 'أخبرني بالمزيد.';
        }

        if (! $welcome || $prompt) {
            $last = $state->messages();
            $lastText = $last !== [] ? $last[array_key_last($last)]['text'] ?? '' : '';
            if ($narration !== '' && $narration !== $lastText) {
                $state->appendMessage('ai', $narration);
            }
        }

        $state->set('current_step', is_array($prompt) ? $prompt['id'] : 'complete');
        $consultation->state = $state->toArray();
        $consultation->save();

        return $consultation->fresh(['lead']) ?? $consultation;
    }

    private function complete(Consultation $consultation, ConsultationState $state): Consultation
    {
        $catalog = $this->catalog->snapshot();
        $diagnosis = $this->diagnosis->build($state);
        $readiness = $this->readiness->calculate($state);

        try {
            $recommendations = $this->recommendations->recommend($state, $catalog);
        } catch (Throwable) {
            $recommendations = [
                'intent' => ['primary' => 'unknown', 'flags' => ['fallback']],
                'best_match' => null,
                'alternative' => null,
                'services' => [],
                'printing' => null,
                'cta' => [
                    'type' => 'talk_expert',
                    'label' => 'تحدث مع مختص',
                    'path' => '/consultant?lead=1',
                ],
                'fallback' => [
                    'title' => 'تعذر إكمال التوصية تلقائياً',
                    'message' => 'لا أملك معلومات كافية لتأكيد توصية من الكتالوج الآن. يمكنك طلب عرض سعر أو التحدث مع مختص.',
                ],
            ];
        }

        $provider = $this->providers->make();
        try {
            $narration = $provider->narrate($state, null, $diagnosis);
        } catch (Throwable) {
            $narration = $diagnosis['summary'];
        }

        $state->appendMessage('ai', $narration);
        $state->set('current_step', 'complete');

        $consultation->state = $state->toArray();
        $consultation->diagnosis = $diagnosis;
        $consultation->readiness = $readiness;
        $consultation->recommendations = $recommendations;
        $consultation->status = ConsultationStatus::Completed;
        $consultation->completed_at = now();
        $consultation->save();

        $this->recordEvent($consultation, 'consultation_completed');
        $this->recordEvent($consultation, 'recommendation_viewed');

        return $consultation->fresh(['lead']) ?? $consultation;
    }

    /**
     * @param  array<string, mixed>|null  $prompt
     * @return array<string, mixed>
     */
    public function toPayload(Consultation $consultation, ?ConsultationState $state = null, ?array $prompt = null): array
    {
        $state ??= ConsultationState::fromArray($consultation->state ?? []);
        $prompt ??= $consultation->status === ConsultationStatus::Completed ? null : $this->questions->next($state);

        return [
            'token' => $consultation->public_token,
            'status' => $consultation->status->value,
            'step' => $state->get('current_step'),
            'progress' => $this->questions->progress($state),
            'state' => [
                'help_mode' => $state->get('help_mode'),
                'business_category' => $state->get('business_category'),
                'business_subtype' => $state->get('business_subtype'),
                'business_name' => $state->get('business_name'),
                'location' => $state->get('location'),
                'branches' => $state->get('branches'),
                'goals' => $state->goals(),
                'needed_services' => $state->neededServices(),
                'unsure_needs' => $state->isUnsure(),
                'budget' => $state->get('budget'),
                'timeline' => $state->get('timeline'),
                'event_date' => $state->get('event_date'),
                'has_website' => $state->get('has_website'),
                'social_platforms' => $state->get('social_platforms'),
            ],
            'messages' => $state->messages(),
            'prompt' => $prompt,
            'diagnosis' => $consultation->diagnosis,
            'readiness' => $consultation->readiness,
            'recommendations' => $consultation->recommendations,
            'lead_captured' => $consultation->lead !== null,
            'enabled' => $this->enabled(),
        ];
    }

    private function answerLabel(string $questionId, mixed $value): string
    {
        if ($value === '__skip__') {
            return 'تخطي';
        }

        if (is_array($value)) {
            return implode('، ', array_map(fn ($item) => $this->optionLabel($questionId, (string) $item), $value));
        }

        return $this->optionLabel($questionId, (string) $value);
    }

    private function optionLabel(string $questionId, string $value): string
    {
        $maps = [
            'help_mode' => ['guided' => 'استشارة موجّهة', 'chat' => 'محادثة حرة', 'unsure' => 'لست متأكداً مما أحتاجه'],
        ];

        if (isset($maps[$questionId][$value])) {
            return $maps[$questionId][$value];
        }

        $category = BusinessCatalog::category($value);
        if (is_array($category)) {
            return (string) $category['label'];
        }

        foreach (BusinessCatalog::categories() as $item) {
            foreach ($item['subtypes'] ?? [] as $subtype) {
                if (($subtype['id'] ?? null) === $value) {
                    return (string) $subtype['label'];
                }
            }
        }

        foreach ([...BusinessCatalog::goals(), ...BusinessCatalog::serviceNeeds(), ...BusinessCatalog::budgetBands(), ...BusinessCatalog::timelines()] as $option) {
            if (($option['id'] ?? null) === $value) {
                return (string) $option['label'];
            }
        }

        $question = BusinessCatalog::question($questionId);
        foreach ($question['options'] ?? [] as $option) {
            if (($option['id'] ?? null) === $value) {
                return (string) $option['label'];
            }
        }

        return $value;
    }
}
