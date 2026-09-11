<?php

namespace App\Support\Calendar;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Resolves frontend URLs for related calendar entities for the current actor.
 * Returns null when the user cannot open a valid route (never emit broken links).
 */
final class CalendarRelatedEntityUrlResolver
{
    public function resolve(User $actor, ?string $relatedType, ?int $relatedId): ?string
    {
        if ($relatedType === null || $relatedType === '' || $relatedId === null) {
            return null;
        }

        $role = $actor->role instanceof UserRole ? $actor->role : null;

        return match ($relatedType) {
            'crm_lead' => $role?->canAccessCrm() ? '/crm/leads/'.$relatedId : null,
            'crm_contact' => $role?->canAccessCrm() ? '/crm/contacts' : null,
            'crm_company' => $role?->canAccessCrm() ? '/crm/companies/'.$relatedId : null,
            'crm_opportunity' => $role?->canAccessCrm() ? '/crm/opportunities' : null,
            'crm_quotation' => $role?->canAccessCrm() ? '/crm/quotations' : null,
            'crm_follow_up' => $role?->canAccessCrm() ? '/crm/follow-ups' : null,
            'order' => $this->orderHref($role, $relatedId),
            'printing_request' => $this->printingHref($role, $relatedId),
            'project' => match (true) {
                $role === UserRole::Owner => '/owner/projects/'.$relatedId,
                $role === UserRole::AccountManager => '/workspace/projects/'.$relatedId,
                $role?->usesEmployeeWorkspace() === true => '/workspace/projects/'.$relatedId,
                default => null,
            },
            'workspace_task' => match (true) {
                $role === UserRole::AccountManager || $role === UserRole::Owner => '/workspace/account-manager/tasks?task='.$relatedId,
                $role?->usesEmployeeWorkspace() === true => '/workspace/tasks?task='.$relatedId,
                default => null,
            },
            'supplier' => in_array($role, [UserRole::Owner, UserRole::AdminManager], true)
                ? '/owner/suppliers/'.$relatedId
                : null,
            'payment' => $role === UserRole::Owner ? '/owner/payments/'.$relatedId : null,
            'employee' => $role === UserRole::Owner ? '/owner/employees' : null,
            default => null,
        };
    }

    private function orderHref(?UserRole $role, int $id): ?string
    {
        if ($role === UserRole::Owner) {
            return '/owner/orders/'.$id;
        }

        if ($role === UserRole::AccountManager) {
            return '/workspace/orders/'.$id;
        }

        return null;
    }

    private function printingHref(?UserRole $role, int $id): ?string
    {
        if ($role === null) {
            return null;
        }

        if (in_array($role, [UserRole::Owner, UserRole::AdminManager, UserRole::PrintingSpecialist], true)) {
            return '/printing-requests/'.$id;
        }

        return null;
    }
}
