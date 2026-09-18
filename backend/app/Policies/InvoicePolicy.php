<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->role($user)?->canViewInvoices() === true;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Customers reach their own invoices through the portal, never the staff surface.
     */
    public function viewOwned(User $user, Invoice $invoice): bool
    {
        return $user->is_active
            && $user->role === UserRole::Customer
            && $invoice->belongsToCustomer($user)
            && $invoice->statusEnum()->isVisibleToCustomer();
    }

    public function create(User $user): bool
    {
        return $this->role($user)?->canManageInvoices() === true;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->create($user) && $invoice->statusEnum()->isEditable();
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->role($user)?->canIssueInvoices() === true;
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->issue($user, $invoice);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->role($user)?->canCancelInvoices() === true;
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->cancel($user, $invoice);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->role($user)?->canRecordInvoicePayment() === true;
    }

    public function viewInternal(User $user, Invoice $invoice): bool
    {
        return $this->role($user)?->canViewInvoiceInternal() === true;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->cancel($user, $invoice) && $invoice->statusEnum()->isEditable();
    }

    private function role(User $user): ?UserRole
    {
        if (! $user->is_active || ! $user->role instanceof UserRole) {
            return null;
        }

        return $user->role;
    }
}
