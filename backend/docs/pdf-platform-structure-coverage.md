# PDF coverage matrix — هيكلة منصة الخدمات الإبداعية والتسويقية

Source: `هيكلة_منصة_الخدمات_الإبداعية_والتسويقية.docx.pdf` (6 pages, sections 1–15).

Status legend: `EXISTS` | `PARTIAL` | `MISSING` | `BLOCKED_PRICE`

| PDF § | Requirement | Existing | Gap | Change | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | Brief→recommendation→pay→execute→deliver | Consultant + Orders + Projects + Payments | Wire accept/modify/human CTAs; no fake prices | FE+API | PARTIAL→IMPLEMENT |
| 2 | Nav structure (اكتشف/خدمات/باقات/إضافات/قطاعات/أعمال/طباعة/فعاليات/متابعة) | Partial public nav | Sectors, add-ons catalog, primary CTA | FE+DB | MISSING→IMPLEMENT |
| 3 | 12 sectors + needs | Hardcoded BusinessCatalog only | First-class `sectors` + M2M | Migration+seed+API+FE | MISSING→IMPLEMENT |
| 4 | Individual services (5 categories) | ServiceSeeder ~47 services | Add `social-media-plan`, `video-editing`; map categories | Seed+fields | PARTIAL→IMPLEMENT |
| 5 | 8 packages + 3 tiers | PackageSeeder + package_tiers | Ensure tiers for all; Owner prices | Seed verify | EXISTS / BLOCKED_PRICE |
| 6 | 12 add-ons | FE hardcoded invented prices | Real `catalog_addons` | Migration+seed+API+FE | MISSING→IMPLEMENT |
| 7 | Printing full lifecycle + Reorder | Printing ops + proof + quote | Reorder endpoint/UI | API+FE | PARTIAL→IMPLEMENT |
| 8 | Events as projects | Event package + project workspace | Event request intake → project | Migration+API+FE | PARTIAL→IMPLEMENT |
| 9 | Smart Brief engine + 3 CTAs | Consultant/RecommendationEngine | Catalog-only rules; restaurant template Owner-editable | Rules DB | PARTIAL→IMPLEMENT |
| 10 | Goal recommendation matrix | Hardcoded scoring | Configurable `recommendation_goals` | DB+seed+engine | MISSING→IMPLEMENT |
| 11 | End-to-end journey | Order/project/files/approvals | Gaps: reorder, complementary offer | Integrate | PARTIAL→IMPLEMENT |
| 12 | Case studies as sales tool | PortfolioItem basic | challenge/solution/CTA prefill + filters | Migration+API+FE | PARTIAL→IMPLEMENT |
| 13 | Pricing rules | CatalogPricingMode | Readiness gate; urgent eligibility; creative vs production split | Service | PARTIAL→IMPLEMENT |
| 14 | Main nav + primary CTA اكتشف احتياجك | Hero CTA is contact | Update nav + hero | FE | MISSING→IMPLEMENT |
| 15 | MVP scope | Mostly present | Complete gaps above | — | IMPLEMENT |

Pricing: never invent SAR. Incomplete commercial rows stay QUOTE / not chargeable.
