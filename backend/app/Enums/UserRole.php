<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'CUSTOMER';
    case Owner = 'OWNER';
    case AdminManager = 'ADMIN_MANAGER';
    case WebDeveloper = 'WEB_DEVELOPER';
    case GraphicDesigner = 'GRAPHIC_DESIGNER';
    case VideoEditor = 'VIDEO_EDITOR';
    case MarketingSpecialist = 'MARKETING_SPECIALIST';
    case EventSpecialist = 'EVENT_SPECIALIST';
    case PrintingSpecialist = 'PRINTING_SPECIALIST';
    case MediaBuyer = 'MEDIA_BUYER';
    case AccountManager = 'ACCOUNT_MANAGER';
    case Hr = 'HR';
    case Supplier = 'SUPPLIER';
    case SalesManager = 'SALES_MANAGER';
    case SalesRepresentative = 'SALES_REPRESENTATIVE';

    public function isStaff(): bool
    {
        return $this !== self::Customer && $this !== self::Supplier;
    }

    public function isSupplier(): bool
    {
        return $this === self::Supplier;
    }

    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Owner, self::AdminManager], true);
    }

    public function canManagePlatformSettings(): bool
    {
        return in_array($this, [self::Owner, self::AdminManager], true);
    }

    public function canReviewPrintingRequests(): bool
    {
        return in_array($this, [self::Owner, self::AdminManager, self::PrintingSpecialist], true);
    }

    public function isEmployee(): bool
    {
        return $this->isStaff() && $this !== self::Owner;
    }

    public function canReviewContent(): bool
    {
        return $this->canManageCatalog();
    }

    public function canSubmitEmployeeWork(): bool
    {
        return $this->usesEmployeeWorkspace();
    }

    public function canAssignTasks(): bool
    {
        return $this === self::AccountManager;
    }

    public function canManageProjects(): bool
    {
        return $this === self::AccountManager;
    }

    public function canOverseeProjects(): bool
    {
        return $this === self::Owner || $this === self::AccountManager;
    }

    public function canManageOrders(): bool
    {
        return $this === self::Owner || $this === self::AccountManager;
    }

    public function canManagePayments(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Refunds follow the same finance boundary as payment management (Owner only).
     */
    public function canRefundPayments(): bool
    {
        return $this->canManagePayments();
    }

    /**
     * Financial amounts for printing revenue (insights, command center, exports).
     * Account managers may see counts without amounts.
     */
    public function canViewPrintingRevenue(): bool
    {
        return in_array($this, [self::Owner, self::AdminManager], true);
    }

    /**
     * Operational revenue / quote pipeline widgets (counts always; amounts gated separately).
     */
    public function canViewPrintingRevenueSection(): bool
    {
        return $this->canViewCommandCenter();
    }

    public function canManageSupport(): bool
    {
        return $this === self::Owner || $this === self::AccountManager;
    }

    public function canViewAssignedProjects(): bool
    {
        return $this->canReceiveAssignedTasks() || $this->canOverseeProjects();
    }

    public function canReceiveAssignedTasks(): bool
    {
        return in_array($this, [
            self::WebDeveloper,
            self::GraphicDesigner,
            self::VideoEditor,
            self::MarketingSpecialist,
            self::EventSpecialist,
            self::PrintingSpecialist,
            self::MediaBuyer,
        ], true);
    }

    public function canAccessCrm(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::SalesManager,
            self::SalesRepresentative,
        ], true);
    }

    public function canManageCrmTeam(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::SalesManager,
        ], true);
    }

    public function canManageCrmSettings(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::SalesManager,
        ], true);
    }

    public function canAccessWorkCalendar(): bool
    {
        return $this->isStaff();
    }

    public function canViewTeamCalendar(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::AccountManager,
            self::SalesManager,
            self::Hr,
        ], true);
    }

    public function canViewUnifiedWork(): bool
    {
        return $this->canAccessWorkCalendar();
    }

    /**
     * Department managers and leadership roles that may manage team work.
     * Callers should also allow when actor.id matches department.manager_id.
     */
    public function canManageTeamWork(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::Hr,
            self::AccountManager,
        ], true);
    }

    public function canManageWorkCalendar(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::AccountManager,
            self::SalesManager,
        ], true);
    }

    public function canManageDepartments(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::Hr,
        ], true);
    }

    public function canManageAutomations(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
        ], true);
    }

    public function canManageIntegrations(): bool
    {
        return $this->canManageAutomations();
    }

    public function canViewCommandCenter(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::AccountManager,
        ], true);
    }

    public function canManageApprovals(): bool
    {
        return $this->canManageWorkCalendar();
    }

    public function isSalesRole(): bool
    {
        return $this === self::SalesManager || $this === self::SalesRepresentative;
    }

    public function canViewQuoteRequests(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::AccountManager,
            self::SalesManager,
            self::SalesRepresentative,
        ], true);
    }

    public function canManageQuoteRequests(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::AccountManager,
            self::SalesManager,
        ], true);
    }

    public function canCreateQuotations(): bool
    {
        return in_array($this, [
            self::Owner,
            self::AdminManager,
            self::AccountManager,
            self::SalesManager,
        ], true);
    }

    /**
     * Staff roles the Owner may assign. Owner and Customer are excluded
     * so employee management cannot grant Owner access or convert staff
     * into public customer accounts.
     *
     * @return array<int, string>
     */
    public static function assignableStaffValues(): array
    {
        return self::employeeValues();
    }

    /**
     * @return array<int, string>
     */
    public static function taskReceiverValues(): array
    {
        return array_values(array_map(
            fn (self $role) => $role->value,
            array_filter(self::cases(), fn (self $role) => $role->canReceiveAssignedTasks()),
        ));
    }

    /**
     * Staff roles that count as employees (everyone except the owner and customers).
     *
     * @return array<int, string>
     */
    public static function employeeValues(): array
    {
        return array_values(array_map(
            fn (self $role) => $role->value,
            array_filter(self::cases(), fn (self $role) => $role->isEmployee()),
        ));
    }

    /**
     * Admin Manager keeps the catalog destination.
     * Sales roles use the CRM workspace.
     * Other employees use the shared employee workspace shell.
     */
    public function usesEmployeeWorkspace(): bool
    {
        return $this->isEmployee()
            && $this !== self::AdminManager
            && ! $this->isSalesRole();
    }

    /**
     * @return array<int, string>
     */
    public static function workspaceEmployeeValues(): array
    {
        return array_values(array_map(
            fn (self $role) => $role->value,
            array_filter(self::cases(), fn (self $role) => $role->usesEmployeeWorkspace()),
        ));
    }

    /**
     * Stable workspace key for role → dashboard mapping.
     */
    public function workspaceKey(): string
    {
        return match ($this) {
            self::Owner => 'owner',
            self::Customer => 'customer',
            self::AdminManager => 'admin-manager',
            self::WebDeveloper => 'web-developer',
            self::GraphicDesigner => 'graphic-designer',
            self::VideoEditor => 'video-editor',
            self::MarketingSpecialist => 'marketing',
            self::EventSpecialist => 'event',
            self::PrintingSpecialist => 'printing',
            self::MediaBuyer => 'media-buyer',
            self::AccountManager => 'account-manager',
            self::Hr => 'hr',
            self::Supplier => 'supplier',
            self::SalesManager, self::SalesRepresentative => 'crm',
        };
    }
}
