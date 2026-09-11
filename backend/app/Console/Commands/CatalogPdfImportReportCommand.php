<?php

namespace App\Console\Commands;

use App\Models\CatalogAddon;
use App\Models\Package;
use App\Models\PackageTier;
use App\Models\RecommendationGoal;
use App\Models\Sector;
use App\Models\Service;
use App\Services\Catalog\CatalogReadinessService;
use Illuminate\Console\Command;

class CatalogPdfImportReportCommand extends Command
{
    protected $signature = 'catalog:pdf-import-report';

    protected $description = 'Print PDF platform catalog counts and missing commercial fields';

    public function handle(CatalogReadinessService $readiness): int
    {
        $this->info('PDF platform catalog import report');
        $this->newLine();

        $this->table(['Entity', 'Count'], [
            ['sectors', Sector::query()->count()],
            ['services', Service::query()->count()],
            ['packages', Package::query()->count()],
            ['package_tiers', PackageTier::query()->count()],
            ['catalog_addons', CatalogAddon::query()->count()],
            ['recommendation_goals', RecommendationGoal::query()->count()],
        ]);

        $expected = [
            'sectors' => 12,
            'packages' => 8,
            'catalog_addons' => 12,
            'recommendation_goals' => 8,
        ];

        foreach ($expected as $label => $count) {
            $actual = match ($label) {
                'sectors' => Sector::query()->count(),
                'packages' => Package::query()->count(),
                'catalog_addons' => CatalogAddon::query()->count(),
                'recommendation_goals' => RecommendationGoal::query()->count(),
            };

            if ($actual < $count) {
                $this->warn("Expected at least {$count} {$label}, found {$actual}");
            }
        }

        foreach (['social-media-plan', 'video-editing'] as $slug) {
            if (! Service::query()->where('slug', $slug)->exists()) {
                $this->warn("Missing required service slug: {$slug}");
            }
        }

        $incomplete = collect($readiness->report())
            ->filter(fn (array $row) => ! $row['is_price_complete'] || $row['missing'] !== [])
            ->values();

        $this->newLine();
        $this->info('Rows with missing commercial / readiness fields: '.$incomplete->count());

        if ($incomplete->isNotEmpty()) {
            $this->table(
                ['Type', 'Slug', 'Missing', 'Purchasable'],
                $incomplete->take(40)->map(fn (array $row) => [
                    $row['type'],
                    $row['slug'] ?? '',
                    implode(', ', $row['missing']),
                    $row['is_purchasable'] ? 'yes' : 'no',
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
