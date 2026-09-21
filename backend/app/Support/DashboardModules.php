<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Central catalog of Dashboard modules that already exist in the product.
 * Keys control visibility/open access — not fine-grained action permissions.
 */
final class DashboardModules
{
    /**
     * @return array<string, array{label: string, shell: string, description?: string}>
     */
    public static function definitions(): array
    {
        return [
            'dashboard' => ['label' => 'نظرة عامة', 'shell' => 'owner', 'description' => 'لوحة المالك الرئيسية'],
            'crm' => ['label' => 'CRM', 'shell' => 'owner', 'description' => 'المبيعات والعملاء المحتملون'],
            'projects' => ['label' => 'المشاريع', 'shell' => 'owner'],
            'work' => ['label' => 'المهام / العمل الموحّد', 'shell' => 'owner'],
            'calendar' => ['label' => 'التقويم', 'shell' => 'owner'],
            'files' => ['label' => 'الملفات', 'shell' => 'owner'],
            'approvals' => ['label' => 'الموافقات', 'shell' => 'owner'],
            'automations' => ['label' => 'الأتمتة', 'shell' => 'owner'],
            'departments' => ['label' => 'الأقسام', 'shell' => 'owner'],
            'printing' => ['label' => 'الطباعة التشغيلية', 'shell' => 'owner'],
            'orders' => ['label' => 'الطلبات', 'shell' => 'owner'],
            'support' => ['label' => 'الدعم', 'shell' => 'owner'],
            'suppliers' => ['label' => 'الموردون', 'shell' => 'owner'],
            'catalog' => ['label' => 'الكتالوج', 'shell' => 'owner'],
            'content' => ['label' => 'المحتوى والتسويق', 'shell' => 'owner'],
            'reports' => ['label' => 'تقارير التشغيل', 'shell' => 'owner'],
            'invoices' => ['label' => 'الفواتير', 'shell' => 'owner'],
            'finance' => ['label' => 'المالي / المدفوعات', 'shell' => 'owner'],
            'employees' => ['label' => 'المستخدمون / الموظفون', 'shell' => 'owner'],
            'integrations' => ['label' => 'التكاملات', 'shell' => 'owner'],
            'settings' => ['label' => 'الإعدادات', 'shell' => 'owner'],
            'notifications' => ['label' => 'الإشعارات', 'shell' => 'owner'],
            'role_access' => ['label' => 'صلاحيات لوحة التحكم', 'shell' => 'owner'],
            'workspace' => ['label' => 'لوحة الموظف', 'shell' => 'workspace'],
            'workspace.calendar' => ['label' => 'تقويم الموظف', 'shell' => 'workspace'],
            'workspace.work' => ['label' => 'عمل الموظف', 'shell' => 'workspace'],
            'workspace.tasks' => ['label' => 'مهام الموظف', 'shell' => 'workspace'],
            'workspace.projects' => ['label' => 'مشاريع الموظف', 'shell' => 'workspace'],
            'workspace.files' => ['label' => 'ملفات الموظف', 'shell' => 'workspace'],
            'workspace.orders' => ['label' => 'طلبات الموظف', 'shell' => 'workspace'],
            'workspace.support' => ['label' => 'دعم الموظف', 'shell' => 'workspace'],
            'workspace.directory' => ['label' => 'دليل الموظفين', 'shell' => 'workspace'],
            'workspace.team' => ['label' => 'الفريق', 'shell' => 'workspace'],
            'workspace.portfolio' => ['label' => 'معرض الأعمال', 'shell' => 'workspace'],
            'workspace.notifications' => ['label' => 'إشعارات الموظف', 'shell' => 'workspace'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function isValid(string $module): bool
    {
        return array_key_exists($module, self::definitions());
    }

    /**
     * Path prefix → module. Longest prefix match wins.
     *
     * @return array<string, string>
     */
    public static function pathMap(): array
    {
        return [
            '/owner/role-dashboard-access' => 'role_access',
            '/owner/payments/reconciliation' => 'finance',
            '/owner/payments/settings' => 'finance',
            '/owner/payments' => 'finance',
            '/owner/invoices' => 'invoices',
            '/owner/operations-insights' => 'reports',
            '/crm/reports' => 'reports',
            '/owner/employees' => 'employees',
            '/owner/integrations' => 'integrations',
            '/owner/operations-settings' => 'settings',
            '/owner/notification-preferences' => 'notifications',
            '/owner/notifications' => 'notifications',
            '/owner/settings' => 'settings',
            '/owner/services' => 'catalog',
            '/owner/packages' => 'catalog',
            '/owner/catalog-control' => 'catalog',
            '/owner/printing-catalog' => 'catalog',
            '/owner/blog' => 'content',
            '/owner/pages' => 'content',
            '/owner/website' => 'content',
            '/owner/seo' => 'content',
            '/owner/marketing' => 'content',
            '/owner/work-reviews' => 'content',
            '/owner/suppliers' => 'suppliers',
            '/owner/supplier-categories' => 'suppliers',
            '/owner/supplier-reviews' => 'suppliers',
            '/owner/tags' => 'suppliers',
            '/owner/projects' => 'projects',
            '/owner/work' => 'work',
            '/owner/calendar' => 'calendar',
            '/owner/files' => 'files',
            '/owner/approvals' => 'approvals',
            '/owner/automations' => 'automations',
            '/owner/departments' => 'departments',
            '/owner/printing-ops' => 'printing',
            '/owner/printing-quotations' => 'printing',
            '/printing-requests' => 'printing',
            '/owner/orders' => 'orders',
            '/owner/support' => 'support',
            '/owner/quote-requests' => 'crm',
            '/owner/requirements' => 'crm',
            '/owner/commercial-quotations' => 'crm',
            '/crm' => 'crm',
            '/owner' => 'dashboard',
            '/workspace/calendar' => 'workspace.calendar',
            '/workspace/work' => 'workspace.work',
            '/workspace/tasks' => 'workspace.tasks',
            '/workspace/projects' => 'workspace.projects',
            '/workspace/files' => 'workspace.files',
            '/workspace/orders' => 'workspace.orders',
            '/workspace/support' => 'workspace.support',
            '/workspace/directory' => 'workspace.directory',
            '/workspace/team' => 'workspace.team',
            '/workspace/portfolio' => 'workspace.portfolio',
            '/workspace/notifications' => 'workspace.notifications',
            '/workspace' => 'workspace',
        ];
    }

    public static function moduleForPath(string $path): ?string
    {
        $normalized = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: $path, '/');
        $map = self::pathMap();
        uksort($map, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($map as $prefix => $module) {
            if ($normalized === $prefix || str_starts_with($normalized, rtrim($prefix, '/').'/')) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Defaults matching today's hardcoded role allowlists — additive, no behavior change until edited.
     *
     * @return array<string, bool>
     */
    public static function defaultsForRole(UserRole $role): array
    {
        $access = array_fill_keys(self::keys(), false);

        if ($role === UserRole::Owner) {
            return array_map(static fn (): bool => true, $access);
        }

        $enable = function (array &$access, string ...$keys): void {
            foreach ($keys as $key) {
                if (array_key_exists($key, $access)) {
                    $access[$key] = true;
                }
            }
        };

        if ($role === UserRole::AdminManager) {
            $enable(
                $access,
                'crm',
                'projects',
                'work',
                'calendar',
                'approvals',
                'automations',
                'departments',
                'printing',
                'suppliers',
                'catalog',
                'content',
                'reports',
                'invoices',
                'integrations',
                'settings',
                'notifications',
            );
        }

        if ($role === UserRole::AccountManager) {
            $enable(
                $access,
                'workspace',
                'workspace.calendar',
                'workspace.work',
                'workspace.tasks',
                'workspace.projects',
                'workspace.files',
                'workspace.orders',
                'workspace.support',
                'workspace.team',
                'workspace.portfolio',
                'workspace.notifications',
                // Operations insights / printing command-center visibility (amounts still gated in controllers).
                'reports',
                'printing',
                'approvals',
            );
        }

        if (in_array($role, [UserRole::SalesManager, UserRole::SalesRepresentative], true)) {
            $enable($access, 'crm');
            if ($role === UserRole::SalesManager) {
                $enable($access, 'reports');
            }
        }

        if ($role === UserRole::PrintingSpecialist) {
            $enable($access, 'printing', 'workspace', 'workspace.calendar', 'workspace.work', 'workspace.portfolio', 'workspace.notifications', 'workspace.files');
        }

        if ($role === UserRole::Hr) {
            $enable(
                $access,
                'workspace',
                'workspace.calendar',
                'workspace.work',
                'workspace.directory',
                'workspace.team',
                'workspace.portfolio',
                'workspace.notifications',
                'workspace.files',
            );
        }

        if ($role->usesEmployeeWorkspace() && ! in_array($role, [
            UserRole::AccountManager,
            UserRole::Hr,
            UserRole::PrintingSpecialist,
        ], true)) {
            $enable(
                $access,
                'workspace',
                'workspace.calendar',
                'workspace.work',
                'workspace.tasks',
                'workspace.projects',
                'workspace.files',
                'workspace.portfolio',
                'workspace.notifications',
                // Employees could always submit / track approval requests under operations.
                'approvals',
            );
        }

        return $access;
    }

    /**
     * Nav path → module for frontend owner items.
     */
    public static function moduleForOwnerNavPath(string $to): ?string
    {
        return self::moduleForPath($to);
    }
}
