<?php

namespace App\Console\Commands\Catalog;

use App\Models\CatalogAddon;
use App\Models\Package;
use App\Models\PackageTier;
use App\Models\RecommendationGoal;
use App\Models\Sector;
use App\Models\Service;
use App\Services\Catalog\CatalogReadinessService;
use Illuminate\Console\Command;

class PdfImportReportCommand extends Command
{
    protected $signature = 'catalog:pdf-import-report';

    protected $description = 'Report PDF platform-structure catalog coverage and missing commercial fields';

    public function handle(CatalogReadinessService $readiness): int
    {
        $this->info('PDF platform structure catalog report');
        $this->table(
            ['Entity', 'Count'],
            [
                ['Sectors', Sector::query()->count()],
                ['Services', Service::query()->count()],
                ['Packages', Package::query()->count()],
                ['Package tiers', PackageTier::query()->count()],
                ['Add-ons', CatalogAddon::query()->count()],
                ['Recommendation goals', RecommendationGoal::query()->count()],
            ],
        );

        $rows = collect($readiness->report());
        $missingPrice = $rows->filter(fn (array $row) => in_array('price', $row['missing'], true));
        $missingDuration = $rows->filter(fn (array $row) => in_array('duration', $row['missing'], true));
        $missingRevision = $rows->filter(fn (array $row) => in_array('revision', $row['missing'], true));
        $quoteOnly = $rows->filter(fn (array $row) => ($row['pricing_mode'] ?? null) === 'QUOTE');

        $this->warn('Missing commercial data (Owner must enter — not invented):');
        $this->line('  Missing price / starting price: '.$missingPrice->count());
        $this->line('  Missing duration: '.$missingDuration->count());
        $this->line('  Missing revision count: '.$missingRevision->count());
        $this->line('  Quote-only / not directly purchasable: '.$quoteOnly->count());

        $addonsUnpriced = CatalogAddon::query()
            ->where(function ($query): void {
                $query->whereNull('price')->orWhere('price', '<=', 0);
            })
            ->count();
        $this->line('  Add-ons without price: '.$addonsUnpriced);

        return self::SUCCESS;
    }
}
