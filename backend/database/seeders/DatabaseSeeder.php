<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed catalog + Owner. Demo users/suppliers/portfolio samples are opt-in for local only.
     */
    public function run(): void
    {
        $this->call([
            ProductionBootstrapSeeder::class,
            ServiceSeeder::class,
            PackageSeeder::class,
            SectorSeeder::class,
            CatalogAddonSeeder::class,
            RecommendationGoalSeeder::class,
            SeoPageSeeder::class,
            CrmSettingsSeeder::class,
            PlatformCatalogSeeder::class,
        ]);

        if (app()->environment('production')) {
            return;
        }

        if (filter_var(env('SEED_DEMO_ACCOUNTS', false), FILTER_VALIDATE_BOOL)) {
            $this->call(DemoAccountSeeder::class);
        }

        if (filter_var(env('SEED_SAMPLE_SUPPLIERS', false), FILTER_VALIDATE_BOOL)) {
            $this->call(SupplierSeeder::class);
        }

        if (filter_var(env('SEED_SAMPLE_PORTFOLIO', false), FILTER_VALIDATE_BOOL)) {
            $this->call(PortfolioSampleSeeder::class);
        }
    }
}
