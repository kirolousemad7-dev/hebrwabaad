<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\InstapayRejectedNotification;
use App\Notifications\InstapaySubmittedNotification;
use App\Notifications\NewSupportMessageNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\OwnerInstapayVerificationNotification;
use App\Notifications\OwnerPaymentReceivedNotification;
use App\Notifications\PaymentPaidNotification;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PlatformNotifier
{
    public function orderStatusUpdated(Order $order, OrderStatus $status): void
    {
        $customer = $order->customer;

        if (! $this->canReceive($customer)) {
            return;
        }

        $customer->notify(new OrderStatusUpdatedNotification($order, $status));
    }

    public function supportMessagePosted(Conversation $conversation, User $sender): void
    {
        $conversation->loadMissing(['customer', 'assignee', 'order', 'project']);

        if ($sender->role === UserRole::Customer) {
            $this->notifySupportRecipients($conversation, $sender);

            return;
        }

        $customer = $conversation->customer;
        if ($this->canReceive($customer) && $customer->id !== $sender->id) {
            $customer->notify(new NewSupportMessageNotification(
                $conversation,
                '/dashboard/messages/'.$conversation->id,
                true,
            ));
        }
    }

    public function taskAssigned(Task $task, ?int $previousAssigneeId = null): void
    {
        if ($previousAssigneeId !== null && $previousAssigneeId === $task->assigned_to) {
            return;
        }

        $assignee = $task->assignee ?? User::query()->find($task->assigned_to);

        if (! $this->canReceive($assignee)) {
            return;
        }

        $assignee->notify(new TaskAssignedNotification($task));
    }

    public function paymentPaid(Payment $payment): void
    {
        $payment->loadMissing(['customer', 'order']);

        if ($this->canReceive($payment->customer)) {
            $payment->customer->notify(new PaymentPaidNotification($payment));
        }

        Notification::send($this->activeOwners(), new OwnerPaymentReceivedNotification($payment));
    }

    public function manualTransferSubmitted(Payment $payment): void
    {
        $payment->loadMissing(['customer', 'order']);

        if ($this->canReceive($payment->customer)) {
            $payment->customer->notify(new InstapaySubmittedNotification($payment));
        }

        Notification::send($this->activeOwners(), new OwnerInstapayVerificationNotification($payment));
    }

    public function manualTransferRejected(Payment $payment): void
    {
        $payment->loadMissing(['customer', 'order']);

        if ($this->canReceive($payment->customer)) {
            $payment->customer->notify(new InstapayRejectedNotification($payment));
        }
    }

    private function notifySupportRecipients(Conversation $conversation, User $sender): void
    {
        $recipients = collect();

        if ($conversation->assignee && $this->canReceive($conversation->assignee)) {
            $recipients->push($conversation->assignee);
        } else {
            $recipients = $recipients->merge($this->activeOwners());
        }

        $recipients = $recipients
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $sender->id)
            ->values();

        foreach ($recipients as $recipient) {
            $href = $recipient->role === UserRole::Owner
                ? '/owner/support/'.$conversation->id
                : '/workspace/support/'.$conversation->id;

            $recipient->notify(new NewSupportMessageNotification($conversation, $href, false));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function activeOwners(): Collection
    {
        return User::query()
            ->active()
            ->where('role', UserRole::Owner)
            ->get();
    }

    private function canReceive(?User $user): bool
    {
        return $user !== null && $user->is_active;
    }
}
