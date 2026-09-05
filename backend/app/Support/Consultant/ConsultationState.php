<?php

namespace App\Support\Consultant;

final class ConsultationState
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private array $data)
    {
        $this->data = array_merge(self::defaults(), $data);
        $this->data['answers'] = is_array($this->data['answers'] ?? null) ? $this->data['answers'] : [];
        $this->data['goals'] = array_values(array_filter((array) ($this->data['goals'] ?? [])));
        $this->data['needed_services'] = array_values(array_filter((array) ($this->data['needed_services'] ?? [])));
        $this->data['social_platforms'] = array_values(array_filter((array) ($this->data['social_platforms'] ?? [])));
        $this->data['messages'] = is_array($this->data['messages'] ?? null) ? $this->data['messages'] : [];
        $this->data['completed_steps'] = array_values(array_filter((array) ($this->data['completed_steps'] ?? [])));
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'help_mode' => null,
            'business_category' => null,
            'business_subtype' => null,
            'business_name' => null,
            'location' => null,
            'branches' => null,
            'business_age' => null,
            'team_size' => null,
            'channel' => null,
            'has_website' => null,
            'social_platforms' => [],
            'goals' => [],
            'needed_services' => [],
            'unsure_needs' => false,
            'budget' => null,
            'timeline' => null,
            'event_date' => null,
            'answers' => [],
            'messages' => [],
            'current_step' => 'welcome',
            'completed_steps' => [],
        ];
    }

    public static function empty(): self
    {
        return new self(self::defaults());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function filled(string $key): bool
    {
        $value = $this->data[$key] ?? null;

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null && $value !== '';
    }

    public function answered(string $questionId): bool
    {
        if (array_key_exists($questionId, $this->data['answers']) && $this->data['answers'][$questionId] !== null) {
            return true;
        }

        return $this->filled($questionId);
    }

    public function skipped(string $questionId): bool
    {
        return ($this->data['answers'][$questionId] ?? null) === '__skip__';
    }

    public function setAnswer(string $questionId, mixed $value): self
    {
        $this->data['answers'][$questionId] = $value;

        $mapped = [
            'help_mode',
            'business_category',
            'business_subtype',
            'business_name',
            'location',
            'branches',
            'business_age',
            'team_size',
            'channel',
            'has_website',
            'social_platforms',
            'goals',
            'needed_services',
            'budget',
            'timeline',
            'event_date',
        ];

        if (in_array($questionId, $mapped, true) && $value !== '__skip__') {
            $this->data[$questionId] = $value;
        }

        if ($questionId === 'needed_services' && (is_array($value) && in_array('unsure', $value, true) || $value === 'unsure')) {
            $this->data['unsure_needs'] = true;
        }

        if ($questionId === 'help_mode' && $value === 'unsure') {
            $this->data['unsure_needs'] = true;
        }

        if ($questionId === 'branches' && is_numeric($value)) {
            $this->data['branches'] = (int) $value;
        }

        if ($questionId === 'has_website') {
            $this->data['has_website'] = in_array($value, [true, 'yes', 'true', 1, '1'], true);
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $extracted
     */
    public function mergeExtracted(array $extracted): self
    {
        foreach ($extracted as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($key === 'answers' && is_array($value)) {
                foreach ($value as $answerId => $answerValue) {
                    if (! $this->answered((string) $answerId)) {
                        $this->setAnswer((string) $answerId, $answerValue);
                    }
                }

                continue;
            }

            if (in_array($key, ['goals', 'needed_services', 'social_platforms'], true)) {
                $current = array_values(array_filter((array) ($this->data[$key] ?? [])));
                $this->data[$key] = array_values(array_unique([...$current, ...array_values((array) $value)]));
                $this->data['answers'][$key] = $this->data[$key];

                continue;
            }

            if (! $this->filled($key) || in_array($this->data[$key] ?? null, [null, '', []], true)) {
                $this->setAnswer($key, $value);
            }
        }

        return $this;
    }

    public function appendMessage(string $role, string $text): self
    {
        $this->data['messages'][] = [
            'role' => $role,
            'text' => $text,
            'at' => now()->toIso8601String(),
        ];

        return $this;
    }

    /**
     * @return list<array{role: string, text: string, at?: string}>
     */
    public function messages(): array
    {
        return array_values($this->data['messages']);
    }

    /**
     * @return list<string>
     */
    public function goals(): array
    {
        return array_values(array_filter((array) $this->data['goals']));
    }

    /**
     * @return list<string>
     */
    public function neededServices(): array
    {
        return array_values(array_filter((array) $this->data['needed_services']));
    }

    public function isUnsure(): bool
    {
        return $this->get('help_mode') === 'unsure' || (bool) $this->get('unsure_needs');
    }
}
