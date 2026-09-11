<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

/**
 * Thin helper around PaymentStatus::canTransitionTo for PaymentService apply paths.
 */
class PaymentStatusTransitionService
{
    public function assertCanTransition(Payment $payment, PaymentStatus $next): void
    {
        $current = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::from((string) $payment->status);

        if (! $current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => ['This payment cannot move to the requested status.'],
            ]);
        }
    }

    public function apply(Payment $payment, PaymentStatus $next): void
    {
        $this->assertCanTransition($payment, $next);
        $payment->status = $next;
    }
}
