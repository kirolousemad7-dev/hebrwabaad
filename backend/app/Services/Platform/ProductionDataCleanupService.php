<?php

namespace App\Services\Platform;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductionDataCleanupService
{
    public const OWNER_EMAIL = 'hassan@gmail.com';

    public const OWNER_NAME = 'حسن';

    public const OWNER_PASSWORD = 'Password123';

    /**
     * Framework / infra tables never truncated by cleanup.
     *
     * @var list<string>
     */
    private const SYSTEM_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
    ];

    /**
     * Catalog / platform configuration — never deleted.
     *
     * @var list<string>
     */
    private const KEEP_CONFIG_TABLES = [
        'services',
        'packages',
        'package_tiers',
        'package_items',
        'sectors',
        'sector_service',
        'sector_package',
        'sector_portfolio_item',
        'catalog_addons',
        'addon_package',
        'addon_service',
        'recommendation_goals',
        'recommendation_goal_items',
        'printing_product_categories',
        'printing_products',
        'printing_product_options',
        'printing_product_option',
        'event_types',
        'platform_settings',
        'seo_pages',
        'payment_settings',
        'portfolio_items',
        'portfolio_media',
        'portfolio_item_service',
        'departments',
        'business_calendars',
        'business_calendar_holidays',
        'calendar_templates',
        'operational_sla_rules',
        'operations_settings',
        'crm_lead_sources',
        'crm_lost_reasons',
        'crm_pipeline_stages',
        'crm_tags',
        'crm_settings',
        'crm_assignment_rules',
        'workflow_automations',
        'testimonials',
        'content_media',
    ];

    /**
     * Runtime / demo transactional tables — emptied in FK-safe order (children first).
     *
     * @var list<string>
     */
    private const DELETE_RUNTIME_TABLES = [
        'notifications',
        'delayed_notifications',
        'personal_access_tokens',
        'password_reset_tokens',
        'user_notification_preferences',
        'calendar_reminders',
        'calendar_item_activities',
        'calendar_item_assignees',
        'calendar_item_comments',
        'calendar_saved_filters',
        'calendar_user_settings',
        'calendar_items',
        'messages',
        'conversations',
        'customer_communication_deliveries',
        'customer_communication_logs',
        'customer_portal_accesses',
        'crm_activities',
        'crm_follow_ups',
        'crm_quotation_items',
        'crm_quotations',
        'crm_audit_logs',
        'crm_lead_tag',
        'crm_sales_targets',
        'crm_saved_filters',
        'crm_opportunities',
        'crm_contacts',
        'crm_leads',
        'crm_companies',
        'contact_inquiries',
        'consultation_events',
        'consultation_leads',
        'consultations',
        'commercial_quotation_events',
        'commercial_quotation_items',
        'payment_attempts',
        'payment_refunds',
        'payments',
        'commercial_quotations',
        'quote_request_events',
        'quote_requests',
        'printing_customer_approvals',
        'printing_deliveries',
        'printing_status_histories',
        'printing_quotation_events',
        'printing_quotations',
        'printing_requests',
        'order_item_addons',
        'order_addons',
        'order_items',
        'order_status_history',
        'managed_file_visibilities',
        'files',
        'project_members',
        'project_milestones',
        'tasks',
        'orders',
        'projects',
        'event_requests',
        'content_reviews',
        'work_submissions',
        'approval_requests',
        'operational_attention_snoozes',
        'operational_escalation_states',
        'operational_saved_views',
        'operations_audit_logs',
        'webhook_deliveries',
        'outbound_webhooks',
        'inbound_webhook_receipts',
        'inbound_webhook_integrations',
        'workflow_automation_runs',
        'supplier_portfolio_items',
        'supplier_products',
        'supplier_profile_versions',
        'suppliers',
    ];

    /**
     * @return array{driver: string, host: ?string, database: ?string, environment: string, safe: bool, reason: string}
     */
    public function environmentSnapshot(): array
    {
        $connection = (string) config('database.default');
        $config = config('database.connections.'.$connection, []);
        $host = isset($config['host']) ? (string) $config['host'] : null;
        $database = isset($config['database']) ? (string) $config['database'] : null;
        $environment = (string) app()->environment();

        $localHosts = ['', '127.0.0.1', 'localhost', '::1'];
        $isSqlite = $connection === 'sqlite';
        $isLocalHost = $host === null || in_array($host, $localHosts, true);
        $isLocalEnv = in_array($environment, ['local', 'testing', 'development'], true);

        $safe = $isLocalEnv && ($isSqlite || $isLocalHost);
        $reason = $safe
            ? 'Local/pre-deployment database connection.'
            : 'Refusing cleanup: environment or host does not look like a local pre-deployment database.';

        return [
            'driver' => $connection,
            'host' => $host,
            'database' => $database,
            'environment' => $environment,
            'safe' => $safe,
            'reason' => $reason,
        ];
    }

    public function assertSafeEnvironment(bool $allowUnsafe = false): void
    {
        $snapshot = $this->environmentSnapshot();

        if ($snapshot['safe'] || $allowUnsafe) {
            return;
        }

        throw new RuntimeException($snapshot['reason'].' Pass --allow-unsafe only when you intentionally target this connection.');
    }

    /**
     * @return list<array{table: string, count: int, classification: string}>
     */
    public function classifyTables(): array
    {
        $rows = [];

        foreach ($this->existingTables() as $table) {
            $classification = $this->classificationFor($table);
            $rows[] = [
                'table' => $table,
                'count' => Schema::hasTable($table) ? (int) DB::table($table)->count() : 0,
                'classification' => $classification,
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['table'], $b['table']));

        return $rows;
    }

    /**
     * @return array{
     *     environment: array{driver: string, host: ?string, database: ?string, environment: string, safe: bool, reason: string},
     *     tables: list<array{table: string, count: int, classification: string}>,
     *     will_delete: list<array{table: string, rows: int}>,
     *     will_preserve: list<array{table: string, rows: int}>,
     *     users_to_remove: int,
     *     owner: array{email: string, name: string, role: string},
     *     suppliers: list<array{id: int, name: string, slug: string}>,
     *     departments: list<array{id: int, name: string, slug: ?string}>,
     *     portfolio_items: list<array{id: int, title: string, slug: ?string}>
     * }
     */
    public function preview(): array
    {
        $tables = $this->classifyTables();
        $willDelete = [];
        $willPreserve = [];

        foreach ($tables as $row) {
            if (in_array($row['classification'], ['DELETE_RUNTIME', 'OWNER_RELATED'], true) && $row['count'] > 0) {
                if ($row['table'] === 'users') {
                    $willDelete[] = [
                        'table' => 'users (non-owner)',
                        'rows' => max(0, $row['count'] - (User::query()->where('email', self::OWNER_EMAIL)->exists() ? 1 : 0)),
                    ];
                } else {
                    $willDelete[] = ['table' => $row['table'], 'rows' => $row['count']];
                }
            }

            if (in_array($row['classification'], ['KEEP_CONFIG', 'SYSTEM'], true)) {
                $willPreserve[] = ['table' => $row['table'], 'rows' => $row['count']];
            }
        }

        return [
            'environment' => $this->environmentSnapshot(),
            'tables' => $tables,
            'will_delete' => $willDelete,
            'will_preserve' => $willPreserve,
            'users_to_remove' => (int) User::query()->where('email', '!=', self::OWNER_EMAIL)->count(),
            'owner' => [
                'email' => self::OWNER_EMAIL,
                'name' => self::OWNER_NAME,
                'role' => UserRole::Owner->value,
            ],
            'suppliers' => Schema::hasTable('suppliers')
                ? DB::table('suppliers')->get(['id', 'name', 'slug'])->map(fn ($r) => (array) $r)->all()
                : [],
            'departments' => Schema::hasTable('departments')
                ? DB::table('departments')->get(['id', 'name', 'slug'])->map(fn ($r) => (array) $r)->all()
                : [],
            'portfolio_items' => Schema::hasTable('portfolio_items')
                ? DB::table('portfolio_items')->get(['id', 'title', 'slug'])->map(fn ($r) => (array) $r)->all()
                : [],
        ];
    }

    /**
     * @return array{
     *     deleted: array<string, int>,
     *     preserved: array<string, int>,
     *     owner_id: int,
     *     files_deleted: list<string>,
     *     warnings: list<string>
     * }
     */
    public function execute(bool $dryRun = false): array
    {
        $preview = $this->preview();
        $deleted = [];
        $warnings = [];
        $filePaths = [];

        if ($dryRun) {
            foreach ($preview['will_delete'] as $row) {
                $deleted[$row['table']] = $row['rows'];
            }

            return [
                'deleted' => $deleted,
                'preserved' => $this->preservedCounts(),
                'owner_id' => (int) (User::query()->where('email', self::OWNER_EMAIL)->value('id') ?? 0),
                'files_deleted' => [],
                'warnings' => ['Dry-run only — no data was modified.'],
            ];
        }

        if (Schema::hasTable('files')) {
            $filePaths = DB::table('files')
                ->whereNotNull('path')
                ->get(['disk', 'path'])
                ->map(fn ($f) => ['disk' => (string) ($f->disk ?: 'local'), 'path' => (string) $f->path])
                ->all();
        }

        DB::transaction(function () use (&$deleted, &$warnings): void {
            $owner = $this->upsertOwner();
            $this->reassignProtectedForeignKeysToOwner((int) $owner->id);
            $this->detachPortfolioFromWorkSubmissions();

            foreach (self::DELETE_RUNTIME_TABLES as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $count = (int) DB::table($table)->count();
                if ($count === 0) {
                    $deleted[$table] = 0;

                    continue;
                }

                $deleted[$table] = $count;
                DB::table($table)->delete();
            }

            if (Schema::hasTable('users')) {
                $nonOwner = (int) User::query()->where('email', '!=', self::OWNER_EMAIL)->count();
                User::query()->where('email', '!=', self::OWNER_EMAIL)->delete();
                $deleted['users (non-owner)'] = $nonOwner;
            }

            $this->sanitizeDemoPaymentSettings($warnings);
            $this->upsertOwner();
        });

        $filesDeleted = $this->deleteCollectedRuntimeFiles($filePaths, $warnings);

        return [
            'deleted' => $deleted,
            'preserved' => $this->preservedCounts(),
            'owner_id' => (int) User::query()->where('email', self::OWNER_EMAIL)->value('id'),
            'files_deleted' => $filesDeleted,
            'warnings' => $warnings,
        ];
    }

    public function ensureOwner(): User
    {
        return $this->upsertOwner();
    }

    public function upsertOwner(): User
    {
        $owner = User::query()->firstOrNew(['email' => self::OWNER_EMAIL]);
        $owner->name = self::OWNER_NAME;
        $owner->role = UserRole::Owner;
        $owner->is_active = true;
        $owner->email_verified_at = $owner->email_verified_at ?? now();
        $owner->password = Hash::make(self::OWNER_PASSWORD);
        $owner->department_id = null;
        $owner->remember_token = null;
        $owner->save();

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')->where('tokenable_id', $owner->id)->delete();
        }

        return $owner->fresh() ?? $owner;
    }

    /**
     * @return array<string, int>
     */
    public function preservedCounts(): array
    {
        $counts = [];

        foreach (array_merge(self::KEEP_CONFIG_TABLES, self::SYSTEM_TABLES) as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = (int) DB::table($table)->count();
            }
        }

        $counts['users'] = Schema::hasTable('users') ? (int) DB::table('users')->count() : 0;

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    public function runtimeZeroTargets(): array
    {
        $targets = [
            'users' => 1,
        ];

        foreach (self::DELETE_RUNTIME_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $targets[$table] = 0;
            }
        }

        return $targets;
    }

    /**
     * @return list<string>
     */
    private function existingTables(): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return array_values(array_map(
                static fn (object $row): string => (string) $row->name,
                DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
            ));
        }

        return array_values(Schema::getTableListing());
    }

    private function classificationFor(string $table): string
    {
        if (in_array($table, self::SYSTEM_TABLES, true)) {
            return 'SYSTEM';
        }

        if (in_array($table, self::KEEP_CONFIG_TABLES, true)) {
            return 'KEEP_CONFIG';
        }

        if ($table === 'users') {
            return 'OWNER_RELATED';
        }

        if (in_array($table, self::DELETE_RUNTIME_TABLES, true)) {
            return 'DELETE_RUNTIME';
        }

        return 'NEEDS_REVIEW';
    }

    private function detachPortfolioFromWorkSubmissions(): void
    {
        if (Schema::hasTable('portfolio_items') && Schema::hasColumn('portfolio_items', 'work_submission_id')) {
            DB::table('portfolio_items')->whereNotNull('work_submission_id')->update(['work_submission_id' => null]);
        }

        if (Schema::hasTable('work_submissions') && Schema::hasColumn('work_submissions', 'published_portfolio_item_id')) {
            DB::table('work_submissions')->whereNotNull('published_portfolio_item_id')->update(['published_portfolio_item_id' => null]);
        }
    }

    private function reassignProtectedForeignKeysToOwner(int $ownerId): void
    {
        $assignments = [
            'calendar_templates' => 'created_by',
            'workflow_automations' => 'created_by',
            'operational_sla_rules' => 'created_by',
            'content_media' => 'uploaded_by',
            'business_calendars' => 'created_by',
            'departments' => 'manager_id',
        ];

        foreach ($assignments as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', $ownerId)
                ->update([$column => $ownerId]);
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function sanitizeDemoPaymentSettings(array &$warnings): void
    {
        if (! Schema::hasTable('payment_settings')) {
            return;
        }

        $row = DB::table('payment_settings')->orderBy('id')->first();
        if ($row === null) {
            return;
        }

        $demoHints = ['تجريبي', 'demo', '@hebr-demo', '00000000000000', 'SA0000000000000000000000'];
        $blob = strtolower(json_encode($row, JSON_UNESCAPED_UNICODE) ?: '');
        $looksDemo = false;
        foreach ($demoHints as $hint) {
            if (str_contains($blob, strtolower($hint))) {
                $looksDemo = true;
                break;
            }
        }

        if (! $looksDemo) {
            return;
        }

        DB::table('payment_settings')->where('id', $row->id)->update(array_filter([
            'bank_transfer_enabled' => Schema::hasColumn('payment_settings', 'bank_transfer_enabled') ? false : null,
            'bank_name' => Schema::hasColumn('payment_settings', 'bank_name') ? null : null,
            'bank_account_name' => Schema::hasColumn('payment_settings', 'bank_account_name') ? null : null,
            'bank_account_number' => Schema::hasColumn('payment_settings', 'bank_account_number') ? null : null,
            'bank_iban' => Schema::hasColumn('payment_settings', 'bank_iban') ? null : null,
            'bank_instructions' => Schema::hasColumn('payment_settings', 'bank_instructions') ? null : null,
            'instapay_enabled' => Schema::hasColumn('payment_settings', 'instapay_enabled') ? false : null,
            'instapay_account_name' => Schema::hasColumn('payment_settings', 'instapay_account_name') ? null : null,
            'instapay_bank_name' => Schema::hasColumn('payment_settings', 'instapay_bank_name') ? null : null,
            'instapay_account_number' => Schema::hasColumn('payment_settings', 'instapay_account_number') ? null : null,
            'instapay_handle' => Schema::hasColumn('payment_settings', 'instapay_handle') ? null : null,
            'instapay_instructions' => Schema::hasColumn('payment_settings', 'instapay_instructions') ? null : null,
            'updated_at' => now(),
        ], fn ($value, $key) => $key === 'updated_at' || Schema::hasColumn('payment_settings', $key), ARRAY_FILTER_USE_BOTH));

        $warnings[] = 'Cleared demo bank/Instapay destination fields from payment_settings; reconfigure real accounts before go-live.';
    }

    /**
     * @param  list<array{disk: string, path: string}>  $filePaths
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function deleteCollectedRuntimeFiles(array $filePaths, array &$warnings): array
    {
        $deleted = [];

        foreach ($filePaths as $file) {
            $path = $file['path'];
            if ($path === '' || $this->isProtectedAssetPath($path)) {
                continue;
            }

            try {
                if (Storage::disk($file['disk'])->exists($path)) {
                    Storage::disk($file['disk'])->delete($path);
                    $deleted[] = $file['disk'].':'.$path;
                }
            } catch (Throwable $e) {
                $warnings[] = 'Could not delete file '.$path.': '.$e->getMessage();
            }
        }

        return $deleted;
    }

    private function isProtectedAssetPath(string $path): bool
    {
        $normalized = strtolower(str_replace('\\', '/', $path));
        $protected = [
            'brand/',
            'logo',
            'favicon',
            'portfolio/',
            'sectors/',
            'services/',
            'packages/',
            'printing/',
            'suppliers/logos/',
            'public/brand',
        ];

        foreach ($protected as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
