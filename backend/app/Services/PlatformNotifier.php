<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SupplierContentType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\WorkSubmission;
use App\Notifications\ContentWorkflowNotification;
use App\Notifications\CrmNotification;
use App\Notifications\InstapayRejectedNotification;
use App\Notifications\InstapaySubmittedNotification;
use App\Notifications\NewSupportMessageNotification;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Notifications\OwnerInstapayVerificationNotification;
use App\Notifications\OwnerPaymentReceivedNotification;
use App\Notifications\PaymentPaidNotification;
use App\Notifications\TaskAssignedNotification;
use App\Services\Notifications\NotificationChannelManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class PlatformNotifier
{
    public function __construct(
        private readonly NotificationChannelManager $channels,
    ) {}

    public function orderStatusUpdated(Order $order, OrderStatus $status): void
    {
        if (! $this->channels->usesDatabase()) {
            return;
        }

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

    public function employeeWorkSubmitted(WorkSubmission $work): void
    {
        Notification::send($this->contentReviewers(), new ContentWorkflowNotification([
            'type' => 'employee_work_submitted',
            'title' => 'عمل موظف بانتظار المراجعة',
            'message' => 'أرسل '.$work->employee?->name.' العمل "'.$work->title.'" للمراجعة.',
            'href' => '/owner/work-reviews',
            'work_submission_id' => $work->id,
        ]));
    }

    public function employeeWorkPublished(WorkSubmission $work): void
    {
        $employee = $work->employee;
        if (! $this->canReceive($employee)) {
            return;
        }

        $employee->notify(new ContentWorkflowNotification([
            'type' => 'employee_work_published',
            'title' => 'تم اعتماد عملك ونشره',
            'message' => 'نُشر العمل "'.$work->title.'" في معرض الأعمال.',
            'href' => '/workspace/work',
            'work_submission_id' => $work->id,
        ]));
    }

    public function employeeWorkRejected(WorkSubmission $work, string $notes): void
    {
        $employee = $work->employee;
        if (! $this->canReceive($employee)) {
            return;
        }

        $employee->notify(new ContentWorkflowNotification([
            'type' => 'employee_work_rejected',
            'title' => 'تم رفض العمل',
            'message' => 'رُفض العمل "'.$work->title.'". '.$notes,
            'href' => '/workspace/work',
            'work_submission_id' => $work->id,
            'review_notes' => $notes,
        ]));
    }

    public function employeeWorkChangesRequested(WorkSubmission $work, string $notes): void
    {
        $employee = $work->employee;
        if (! $this->canReceive($employee)) {
            return;
        }

        $employee->notify(new ContentWorkflowNotification([
            'type' => 'employee_work_changes_requested',
            'title' => 'مطلوب تعديلات على عملك',
            'message' => 'يطلب المراجع تعديلات على "'.$work->title.'". '.$notes,
            'href' => '/workspace/work',
            'work_submission_id' => $work->id,
            'review_notes' => $notes,
        ]));
    }

    public function supplierContentSubmitted(Supplier $supplier, SupplierContentType $type, string $title): void
    {
        Notification::send($this->contentReviewers(), new ContentWorkflowNotification([
            'type' => 'supplier_content_submitted',
            'title' => 'محتوى مورد بانتظار المراجعة',
            'message' => 'أرسل '.$supplier->name.' «'.$title.'» للمراجعة.',
            'href' => '/owner/supplier-reviews',
            'supplier_id' => $supplier->id,
            'content_type' => $type->value,
        ]));
    }

    public function supplierContentPublished(Supplier $supplier, SupplierContentType $type, string $title): void
    {
        $user = $supplier->user;
        if (! $this->canReceive($user)) {
            return;
        }

        $user->notify(new ContentWorkflowNotification([
            'type' => 'supplier_content_published',
            'title' => 'تم اعتماد المحتوى ونشره',
            'message' => 'نُشر «'.$title.'» بعد موافقة المالك.',
            'href' => '/supplier',
            'supplier_id' => $supplier->id,
            'content_type' => $type->value,
        ]));
    }

    public function supplierContentRejected(Supplier $supplier, SupplierContentType $type, string $title, string $notes): void
    {
        $user = $supplier->user;
        if (! $this->canReceive($user)) {
            return;
        }

        $user->notify(new ContentWorkflowNotification([
            'type' => 'supplier_content_rejected',
            'title' => 'تم رفض المحتوى',
            'message' => 'رُفض «'.$title.'». '.$notes,
            'href' => '/supplier',
            'supplier_id' => $supplier->id,
            'content_type' => $type->value,
            'review_notes' => $notes,
        ]));
    }

    public function supplierContentChangesRequested(Supplier $supplier, SupplierContentType $type, string $title, string $notes): void
    {
        $user = $supplier->user;
        if (! $this->canReceive($user)) {
            return;
        }

        $user->notify(new ContentWorkflowNotification([
            'type' => 'supplier_content_changes_requested',
            'title' => 'مطلوب تعديلات على المحتوى',
            'message' => 'يطلب المراجع تعديلات على «'.$title.'». '.$notes,
            'href' => '/supplier',
            'supplier_id' => $supplier->id,
            'content_type' => $type->value,
            'review_notes' => $notes,
        ]));
    }

    public function crmLeadAssigned(CrmLead $lead, User $assignee): void
    {
        if (! $this->canReceive($assignee) || ! $this->allowsCrmAlerts($assignee)) {
            return;
        }

        $assignee->notify(new CrmNotification([
            'type' => 'crm_lead_assigned',
            'title' => 'Lead assigned to you',
            'message' => 'Lead '.$lead->reference.' ('.$lead->full_name.') was assigned to you.',
            'href' => '/crm/leads/'.$lead->id,
            'lead_id' => $lead->id,
        ]));
    }

    public function crmFollowUpOverdue(CrmFollowUp $followUp): void
    {
        $followUp->loadMissing('assignee', 'lead');
        $assignee = $followUp->assignee;
        if (! $this->canReceive($assignee) || ! $this->allowsCrmAlerts($assignee)) {
            return;
        }

        $assignee->notify(new CrmNotification([
            'type' => 'crm_follow_up_overdue',
            'title' => 'Follow-up overdue',
            'message' => 'Follow-up for '.($followUp->lead?->full_name ?? 'lead').' is overdue.',
            'href' => '/crm/leads/'.$followUp->lead_id,
            'follow_up_id' => $followUp->id,
            'lead_id' => $followUp->lead_id,
        ]));
    }

    public function crmQuotationApprovalNeeded(CrmQuotation $quotation): void
    {
        $managers = User::query()
            ->active()
            ->whereIn('role', [UserRole::Owner, UserRole::AdminManager, UserRole::SalesManager])
            ->get()
            ->filter(fn (User $user): bool => $this->allowsApprovalAlerts($user));

        Notification::send($managers, new CrmNotification([
            'type' => 'crm_quotation_approval_needed',
            'title' => 'Quotation needs approval',
            'message' => 'Quotation '.$quotation->number.' exceeds the discount threshold.',
            'href' => '/crm/quotations/'.$quotation->id,
            'quotation_id' => $quotation->id,
        ]));
    }

    public function crmMention(User $mentioned, User $actor, CrmLead $lead, string $snippet): void
    {
        if (! $this->canReceive($mentioned) || $mentioned->id === $actor->id || ! $this->allowsCrmAlerts($mentioned)) {
            return;
        }

        $mentioned->notify(new CrmNotification([
            'type' => 'crm_mention',
            'title' => 'You were mentioned in CRM',
            'message' => $actor->name.' mentioned you on lead '.$lead->reference.': '.$snippet,
            'href' => '/crm/leads/'.$lead->id,
            'lead_id' => $lead->id,
        ]));
    }

    public function crmDealWon(CrmLead $lead): void
    {
        $lead->loadMissing('assignee');
        $recipients = collect();
        if ($this->canReceive($lead->assignee) && $this->allowsCrmAlerts($lead->assignee)) {
            $recipients->push($lead->assignee);
        }
        $recipients = $recipients->merge(
            User::query()->active()->whereIn('role', [UserRole::Owner, UserRole::AdminManager, UserRole::SalesManager])->get()
                ->filter(fn (User $user): bool => $this->allowsCrmAlerts($user))
        )->unique('id');

        Notification::send($recipients, new CrmNotification([
            'type' => 'crm_deal_won',
            'title' => 'Deal won',
            'message' => 'Lead '.$lead->reference.' ('.$lead->full_name.') was converted.',
            'href' => '/crm/leads/'.$lead->id,
            'lead_id' => $lead->id,
        ]));
    }

    public function crmStaleLead(CrmLead $lead): void
    {
        $lead->loadMissing('assignee');
        $assignee = $lead->assignee;
        if (! $this->canReceive($assignee) || ! $this->allowsCrmAlerts($assignee)) {
            return;
        }

        $assignee->notify(new CrmNotification([
            'type' => 'crm_stale_lead',
            'title' => 'Stale lead needs attention',
            'message' => 'Lead '.$lead->reference.' ('.$lead->full_name.') has gone stale.',
            'href' => '/crm/leads/'.$lead->id,
            'lead_id' => $lead->id,
        ]));
    }

    /**
     * @return Collection<int, User>
     */
    private function contentReviewers(): Collection
    {
        return User::query()
            ->active()
            ->whereIn('role', [UserRole::Owner, UserRole::AdminManager])
            ->get();
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

    private function allowsCrmAlerts(User $user): bool
    {
        $prefs = UserNotificationPreference::query()->where('user_id', $user->id)->first();

        return $prefs === null || $prefs->crm_alerts !== false;
    }

    private function allowsApprovalAlerts(User $user): bool
    {
        $prefs = UserNotificationPreference::query()->where('user_id', $user->id)->first();

        return $prefs === null || $prefs->approval_requests !== false;
    }
}
