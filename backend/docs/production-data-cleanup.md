# Production data cleanup

Date: 2026-09-11  
Environment cleaned: **local** SQLite `database/qa.sqlite` (`APP_ENV=local`)

## Backup status

| Backup | Path | Status |
| --- | --- | --- |
| Pre-cleanup (file) | `storage/backups/pre-production-cleanup-20260911-0525.sqlite` | Created |
| Pre-cleanup (SQL) | `storage/backups/pre-production-cleanup-20260911-0525.sql` | Created |
| Clean baseline (file) | `storage/backups/production-clean-baseline-20260911-0540.sqlite` | Created |
| Clean baseline (SQL) | `storage/backups/production-clean-baseline-20260911-0540.sql` | Created |

Backup files are gitignored. Do not commit them.

## Command

```bash
php artisan platform:clean-production-data --dry-run
php artisan platform:clean-production-data --confirm
```

Safety:

- Refuses non-local environments/hosts unless `--allow-unsafe` is passed
- Does **not** use `migrate:fresh`, `db:wipe`, or drop tables
- Deletes runtime rows only; catalog/config preserved

## Final Owner

- Email: `hassan@gmail.com`
- Role: `OWNER`
- Name: `حسن`
- Active: yes
- Password: Initial Owner credential configured as requested (hashed via `Hash::make` / User hashed cast)
- Recommendation: change password immediately after first production login

## Pre-clean highlights (selected)

| Table | Rows | Classification |
| --- | ---: | --- |
| users | 11 | OWNER_RELATED |
| services | 49 | KEEP_CONFIG |
| packages | 8 | KEEP_CONFIG |
| package_tiers | 24 | KEEP_CONFIG |
| sectors | 12 | KEEP_CONFIG |
| catalog_addons | 12 | KEEP_CONFIG |
| recommendation_goals | 8 | KEEP_CONFIG |
| printing_products | 3 | KEEP_CONFIG |
| platform_settings | 11 | KEEP_CONFIG |
| orders | 6 | DELETE_RUNTIME |
| projects | 3 | DELETE_RUNTIME |
| crm_leads | 5 | DELETE_RUNTIME |
| printing_requests | 4 | DELETE_RUNTIME |
| quote_requests | 8 | DELETE_RUNTIME |
| commercial_quotations | 8 | DELETE_RUNTIME |
| payments | 2 | DELETE_RUNTIME |
| notifications | 102 | DELETE_RUNTIME |
| suppliers | 1 (`demo-supplier`) | DELETE_RUNTIME |
| portfolio_items | 3 | KEEP_CONFIG |
| departments | 0 | KEEP_CONFIG |

Full dry-run output: `storage/backups/dry-run-report.txt`

## Post-clean verification

| Check | Result |
| --- | --- |
| users | **1** |
| OWNER email/role | `hassan@gmail.com` / `OWNER` |
| Owner login | OK (`POST /api/auth/login`) |
| orders / projects / tasks | 0 |
| CRM leads / opportunities / activities | 0 |
| printing requests / quotations | 0 |
| quote / commercial quotations | 0 |
| payments / notifications | 0 |
| suppliers | 0 |
| services | 49 |
| packages | 8 |
| package_tiers | 24 |
| package_items | 46 |
| sectors | 12 |
| catalog_addons | 12 |
| recommendation_goals | 8 |
| printing catalog products/categories | 3 / 11 |
| event_types | 6 |
| platform_settings | 11 |
| seo_pages | 12 |
| portfolio case studies | 3 preserved |
| CRM pipeline sources/stages/tags | preserved |

## Suppliers

Deleted demo supplier `demo-supplier` (مورد تجريبي). No other supplier rows were present.  
`SupplierSeeder` is **opt-in** via `SEED_SAMPLE_SUPPLIERS=true` (non-production only).

## Departments

No department rows were present (count 0). Definitions remain supported; memberships cleaned with users.

## Files / assets

- Runtime `files` table was empty at cleanup time (0 physical runtime deletions)
- Brand/catalog/portfolio assets were not targeted
- Demo payment destination fields in `payment_settings` were cleared (bank/Instapay demo placeholders)

## Seeders

`DatabaseSeeder` now:

1. Always seeds `ProductionBootstrapSeeder` (Owner only) + catalog seeders
2. Does **not** auto-create `test@example.com`
3. Does **not** auto-run `DemoAccountSeeder`, `SupplierSeeder`, or `PortfolioSampleSeeder` in production
4. Local opt-in flags:
   - `SEED_DEMO_ACCOUNTS=true`
   - `SEED_SAMPLE_SUPPLIERS=true`
   - `SEED_SAMPLE_PORTFOLIO=true`

Explicit demo seed remains available:

```bash
php artisan db:seed --class=DemoAccountSeeder
```

## Empty-state QA

Owner authenticated endpoints returned non-500 responses for dashboard, employees, payments, printing requests/catalog, CRM, calendar summary, command center, my-day, orders, services, packages, portfolio, platform settings.  
`GET /api/calendar` returned 422 without required date range params (validation, not a crash).

## Tests

`tests/Feature/ProductionDataCleanupCommandTest.php` covers dry-run, runtime deletion, catalog preservation, Owner password login, and idempotency.

## Remaining warnings

1. Reconfigure real bank/Instapay destination fields in payment settings before accepting manual transfers.
2. Configure live PayTabs credentials separately (env) — not part of this cleanup.
3. `SupplierSeeder` / `PortfolioSampleSeeder` / `DemoAccountSeeder` still exist for local demos; keep them opt-in.
4. This cleanup targeted the local pre-deployment SQLite DB. Re-run `--dry-run` against the real production MySQL host before any remote `--confirm`.
