<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Platform\ProductionDataCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CleanProductionDataCommand extends Command
{
    protected $signature = 'platform:clean-production-data
                            {--dry-run : Preview deletions without modifying data}
                            {--confirm : Execute destructive cleanup of demo/runtime data}
                            {--allow-unsafe : Bypass local-environment safety check (dangerous)}';

    protected $description = 'Remove demo/runtime transactional data while preserving catalog and platform configuration';

    public function handle(ProductionDataCleanupService $cleanup): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $confirm = (bool) $this->option('confirm');

        if (! $dryRun && ! $confirm) {
            $this->error('Specify --dry-run or --confirm.');

            return self::FAILURE;
        }

        if ($dryRun && $confirm) {
            $this->error('Use either --dry-run or --confirm, not both.');

            return self::FAILURE;
        }

        try {
            $cleanup->assertSafeEnvironment((bool) $this->option('allow-unsafe'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $preview = $cleanup->preview();
        $env = $preview['environment'];

        $this->info('Database environment');
        $this->table(
            ['Field', 'Value'],
            [
                ['environment', $env['environment']],
                ['driver', $env['driver']],
                ['host', (string) ($env['host'] ?? '')],
                ['database', (string) ($env['database'] ?? '')],
                ['safe', $env['safe'] ? 'yes' : 'no'],
            ],
        );

        $this->newLine();
        $this->info('Table classification');
        $this->table(
            ['Table', 'Rows', 'Classification'],
            array_map(
                fn (array $row): array => [$row['table'], (string) $row['count'], $row['classification']],
                $preview['tables'],
            ),
        );

        $this->newLine();
        $this->info('Will remove (runtime / non-owner)');
        $this->table(
            ['Table', 'Rows'],
            array_map(
                fn (array $row): array => [$row['table'], (string) $row['rows']],
                $preview['will_delete'] !== [] ? $preview['will_delete'] : [['table' => '(none)', 'rows' => 0]],
            ),
        );

        $this->newLine();
        $this->info('Will preserve (catalog / config / system)');
        $this->table(
            ['Table', 'Rows'],
            array_map(
                fn (array $row): array => [$row['table'], (string) $row['rows']],
                $preview['will_preserve'],
            ),
        );

        $this->newLine();
        $this->info('Final Owner account');
        $this->line('  email: '.$preview['owner']['email']);
        $this->line('  name: '.$preview['owner']['name']);
        $this->line('  role: '.$preview['owner']['role']);
        $this->comment('  Initial Owner credential configured as requested (not printed).');

        if ($preview['suppliers'] !== []) {
            $this->newLine();
            $this->warn('Suppliers marked for deletion (treated as demo/runtime unless re-seeded intentionally):');
            foreach ($preview['suppliers'] as $supplier) {
                $this->line('  #'.$supplier['id'].' '.$supplier['name'].' ('.$supplier['slug'].')');
            }
        }

        if ($preview['portfolio_items'] !== []) {
            $this->newLine();
            $this->info('Portfolio / case studies preserved:');
            foreach ($preview['portfolio_items'] as $item) {
                $this->line('  #'.$item['id'].' '.$item['title'].' ('.($item['slug'] ?? '').')');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Dry-run complete — no data was modified.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('THIS WILL REMOVE DEMO/RUNTIME BUSINESS DATA.');
        $this->warn('Catalog, platform settings, SEO, and brand configuration will be preserved.');

        $result = $cleanup->execute(dryRun: false);

        $this->newLine();
        $this->info('Cleanup finished');
        $this->table(
            ['Table', 'Deleted rows'],
            collect($result['deleted'])
                ->map(fn (int $count, string $table): array => [$table, (string) $count])
                ->values()
                ->all(),
        );

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $owner = User::query()->find($result['owner_id']);
        $userCount = User::query()->count();
        $ownerOk = $owner !== null
            && $owner->email === ProductionDataCleanupService::OWNER_EMAIL
            && $owner->isOwner()
            && $owner->is_active
            && Hash::check(ProductionDataCleanupService::OWNER_PASSWORD, $owner->password);

        $this->newLine();
        $this->info('Post-cleanup verification');
        $this->table(
            ['Check', 'Result'],
            [
                ['users_count', (string) $userCount],
                ['owner_email', (string) $owner?->email],
                ['owner_role', (string) $owner?->role?->value],
                ['owner_active', $owner?->is_active ? 'yes' : 'no'],
                ['owner_password_ok', $ownerOk ? 'yes' : 'no'],
                ['services', (string) ($result['preserved']['services'] ?? 0)],
                ['packages', (string) ($result['preserved']['packages'] ?? 0)],
                ['package_tiers', (string) ($result['preserved']['package_tiers'] ?? 0)],
                ['sectors', (string) ($result['preserved']['sectors'] ?? 0)],
                ['catalog_addons', (string) ($result['preserved']['catalog_addons'] ?? 0)],
                ['platform_settings', (string) ($result['preserved']['platform_settings'] ?? 0)],
                ['printing_products', (string) ($result['preserved']['printing_products'] ?? 0)],
                ['files_deleted', (string) count($result['files_deleted'])],
            ],
        );

        if ($userCount !== 1 || ! $ownerOk) {
            $this->error('Owner verification failed.');

            return self::FAILURE;
        }

        $this->info('Production data cleanup completed successfully.');
        $this->comment('Recommend changing the Owner password immediately after first production login.');

        return self::SUCCESS;
    }
}
