<?php

namespace App\Services\Consultant;

use App\Support\Consultant\ConsultationState;

class ReadinessScoreCalculator
{
    /**
     * Deterministic score from known answers only. Missing data scores low, never random.
     *
     * @return array{score: int, dimensions: array<string, int>}
     */
    public function calculate(ConsultationState $state): array
    {
        $website = $state->get('has_website');
        $social = (array) $state->get('social_platforms', []);
        $hasSocial = $social !== [] && ! in_array('none', $social, true);
        $ads = $state->get('answers')['run_ads'] ?? null;
        $channels = (array) ($state->get('answers')['current_channels'] ?? []);
        $goals = $state->goals();
        $challenge = $state->get('answers')['biggest_challenge'] ?? null;
        $hasPayment = $state->get('answers')['has_payment'] ?? null;
        $hasBooking = $state->get('answers')['has_booking'] ?? null;
        $hasOrdering = $state->get('answers')['has_online_ordering'] ?? null;

        $digital = 20;
        if (in_array($website, [true, 'yes'], true)) {
            $digital += 45;
        } elseif ($website === 'in_progress') {
            $digital += 25;
        }
        if ($hasSocial) {
            $digital += 25;
        }
        if (in_array($hasOrdering, ['yes'], true) || in_array($hasBooking, ['yes'], true)) {
            $digital += 10;
        }

        $marketing = 15;
        if ($hasSocial) {
            $marketing += 20;
        }
        if ($ads === 'yes') {
            $marketing += 35;
        } elseif ($ads === 'tried') {
            $marketing += 15;
        }
        if ($channels !== [] && ! in_array('none', $channels, true)) {
            $marketing += 20;
        }

        $branding = in_array('rebrand', $goals, true) || $challenge === 'branding' ? 35 : 60;
        if ($hasSocial) {
            $branding += 10;
        }

        $acquisition = 20;
        if ($ads === 'yes') {
            $acquisition += 30;
        }
        if (in_array('increase_sales', $goals, true) || in_array('more_customers', $goals, true)) {
            $acquisition += 10;
        }
        if ($challenge === 'not_enough_customers') {
            $acquisition += 5;
        } else {
            $acquisition += 15;
        }

        $retention = in_array('retention', $goals, true) ? 40 : 55;
        $sales = $challenge === 'low_conversion' ? 35 : 55;
        if (in_array($hasPayment, ['yes'], true) || in_array($hasOrdering, ['yes'], true)) {
            $sales += 15;
        }

        $online = $digital;

        $dimensions = [
            'digital_presence' => $this->clamp($digital),
            'marketing' => $this->clamp($marketing),
            'branding' => $this->clamp($branding),
            'customer_acquisition' => $this->clamp($acquisition),
            'customer_retention' => $this->clamp($retention),
            'sales' => $this->clamp($sales),
            'online_infrastructure' => $this->clamp($online),
        ];

        $score = (int) round(array_sum($dimensions) / count($dimensions));

        return [
            'score' => $this->clamp($score),
            'dimensions' => $dimensions,
        ];
    }

    private function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
