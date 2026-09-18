# HEBR & ABAAD — Production Readiness Report

**Audit date:** 2026-09-18  
**Scope:** Full integration audit (no new product features).  
**Verification:** `php artisan test` — **710 passed**; frontend `tsc -b` + `vite build` — **passed**.

**Overall verdict:** Not fully production-ready. Core marketplace isolation and most modules are solid, but **Invoices** are absent as a first-class domain, and several ops/security items remain **YELLOW/RED** below. Do not treat a green module list as a go-live sign-off while any **RED** item is open.

---

## Legend

| Status | Meaning |
|--------|---------|
| **GREEN** | Implemented, wired, and adequate for production with current tests/evidence |
| **YELLOW** | Works but has gaps, demo-grade ops config, or defense-in-depth debt |
| **RED** | Broken, missing, or unsafe for production until fixed |

---

## Modules

| Module | Status | Notes |
|--------|--------|-------|
| Authentication | **GREEN** | Sanctum PAT login/register/forgot-reset; role + active account middleware |
| Authorization | **GREEN** | Server-side `role:` middleware, policies, service asserts |
| Owner | **GREEN** | Admin/owner APIs + `/owner/*` UI |
| Employees | **GREEN** | Admin employees + workspace directory |
| Suppliers | **GREEN** | Admin + public catalog; visibility scopes |
| Supplier portal | **GREEN** | `/api/supplier/*` + `/supplier/*`; ownership 404s |
| Supplier registration | **GREEN** | Public register + onboarding verification |
| Email verification | **YELLOW** | Supplier-only today |
| Phone verification | **YELLOW** | Supplier-only OTP |
| OTP login | **YELLOW** | Supplier OTP login; `/login/code` is supplier-scoped |
| CRM | **GREEN** | Companies, customers, settings, quotations |
| Customers | **GREEN** | Customer dashboard + CRM 360 |
| Companies | **GREEN** | CRM companies API + UI |
| Services | **GREEN** | Public + owner catalog |
| Products | **GREEN** | Supplier products + printing catalog |
| Categories | **GREEN** | Supplier / blog / printing categories |
| Tags | **GREEN** | Admin + blog + CRM tags |
| Portfolio | **GREEN** | Public slug detail + admin/supplier management |
| Projects | **GREEN** | Workspace + operations + customer views |
| Tasks | **GREEN** | Workspace tasks + reminders scheduled |
| Calendar | **GREEN** | Internal calendar + digests/reminders |
| Google Calendar | **GREEN** | OAuth; graceful when unconfigured |
| Google Meet | **GREEN** | Provider gated on Google OAuth config |
| Zoom | **GREEN** | S2S provider; graceful when unconfigured |
| Quotations | **GREEN** | Commercial + printing + public tokens; customer scrub |
| Supplier sourcing | **GREEN** | Owner sourcing + supplier portal; isolation tested |
| Invoices | **RED** | No invoice domain (see below) |
| Files | **GREEN** | Managed files + media policies; private disk default |
| Notifications | **GREEN** | In-app + delayed delivery scheduled |
| Blog | **GREEN** | CMS + public; scheduled publish now scheduled |
| Sidebar | **GREEN** | Role-aware `dashboardNav` |
| SEO | **GREEN** | Page SEO + sitemap APIs |
| Public pages | **GREEN** | Marketing + catalog + public quote/portal |

### RED / YELLOW module detail

#### Invoices — **RED**
- **Problem:** No invoice models/controllers/routes/pages. Payments/receipts and media owner-type `invoice` are not an invoicing module.
- **Impact:** Cannot issue, number, or manage customer invoices as a product capability.
- **Exact fix:** Either implement an Invoice domain (model, lifecycle, PDF, customer/owner APIs, UI) **or** remove “Invoices” from product language and document Payments/Receipts as the commercial document surface.

#### Email / phone / OTP — **YELLOW**
- **Problem:** Verification and OTP login cover suppliers only; customers/staff use password auth without email verify.
- **Impact:** Unverified customer emails; OTP UX looks global but is supplier-only.
- **Exact fix:** Document as supplier-only in UX copy, **or** add customer/staff email verification and/or OTP if product requires it.

---

## Critical business rules

| # | Rule | Status |
|---|------|--------|
| 1 | Customer never sees supplier identity unless public | **GREEN** |
| 2 | Customer never sees supplier cost | **GREEN** |
| 3 | Supplier never sees another supplier | **GREEN** |
| 4 | Supplier never sees unrelated customers | **GREEN** (minor YELLOW below) |
| 5 | Supplier never accesses Owner routes | **GREEN** |
| 6 | Supplier documents respect visibility | **YELLOW** (hardened this audit; residual below) |
| 7 | Internal quotation data never leaks via customer APIs | **GREEN** (scrub + tests) |
| 8 | Customer invoices must not expose supplier info | **GREEN** for payments/orders (no invoice module) |
| 9 | Authorization is server-side | **GREEN** |
| 10 | Frontend hiding is not security | **YELLOW** (UI gates exist; server enforces quotes/sourcing) |

### Rule 4 — shared auth surfaces — **YELLOW**
- **Problem:** Some `/api/workspace/*`, `/api/media`, `/api/meetings` routes use `auth:sanctum` without an explicit `role:` deny for `SUPPLIER`. Policies/scopes usually empty/403, but route-layer denial is inconsistent.
- **Impact:** Defense-in-depth gap if a policy regresses.
- **Exact fix:** Add explicit role middleware (or controller abort) denying `SUPPLIER` on staff-only controllers.

### Rule 6 — supplier documents — **YELLOW** (was RED)
- **Problem (fixed in audit):** Default disk was `public`; clients could send `disk`. Now forced to `local`; `disk` input prohibited.
- **Residual:** API still accepts a client-supplied `path` (metadata-only create); no gated download endpoint that streams by visibility.
- **Impact:** Misconfigured deploys that place files under a public path could still expose content if operators copy paths into public storage.
- **Exact fix:** Replace metadata create with authenticated upload to private disk + authorized download that checks `visibility` and actor; never return raw public URLs for `INTERNAL` docs.

### Rule 7 — scrub pattern — **YELLOW** debt
- **Problem:** Customer scrub uses Eloquent `makeHidden` rather than a dedicated customer resource/DTO.
- **Impact:** Future raw serialization could reintroduce leaks.
- **Exact fix:** Introduce explicit customer quotation resources (mirror `CustomerOrderResource`) and stop returning Eloquent models to customers.

---

## API quality

| Concern | Status | Notes |
|---------|--------|-------|
| N+1 | **YELLOW** | Most list endpoints eager-load; keep auditing new relations |
| Pagination | **GREEN** | Staff/customer list endpoints paginate |
| Validation | **GREEN** | Form requests on mutating endpoints |
| Authorization | **GREEN** | Middleware + policies + service asserts |
| Rate limits | **GREEN** | Auth, OTP, uploads, public portal, payments |
| Error handling | **GREEN** | `ApiResponse` + validation exceptions |
| API Resources / shape | **YELLOW** | Mix of Resources and controller `serialize()` arrays |

---

## Security

| Item | Status | Problem / impact / fix (if not GREEN) |
|------|--------|----------------------------------------|
| CORS | **GREEN** | Allowlist via `CORS_ALLOWED_ORIGINS`; set exact frontend origin in prod |
| Sanctum | **YELLOW** | Bearer tokens in `localStorage`, `expiration => null`. **Impact:** XSS = full API access. **Fix:** short TTL/rotation; prefer HttpOnly cookie SPA auth |
| CSRF | **GREEN** | Bearer API model (not cookie SPA) |
| File uploads | **YELLOW** | Media/managed files OK; supplier docs still metadata-path (see Rule 6) |
| OTP security | **GREEN** | Hashed codes, TTL, attempts, cooldown, throttles |
| OAuth token storage | **GREEN** | Google tokens encrypted at rest |
| Rate limiting | **GREEN** | Named limiters applied |
| Mass assignment | **YELLOW** | `User` fillable includes `role`/`is_active`. Register hardcodes Customer today. **Fix:** remove privileged attrs from fillable; `forceFill` only in admin services |
| IDOR | **GREEN** | Ownership checks return 404 on quotes/sourcing/files |
| Sensitive fields | **GREEN** | Customer scrub; payment secret redaction in logs |
| Content media visibility | **GREEN** | Fixed: public serve requires parent `visibility === PUBLIC` (+ published/active/supplier public) |

---

## Scheduler

| Job | Status |
|-----|--------|
| Calendar / task reminders | **GREEN** |
| Daily digest | **GREEN** |
| Delayed notifications | **GREEN** |
| Escalations / webhooks / workflows | **GREEN** |
| Printing quotation expiry | **GREEN** |
| Commercial quotation expiry | **GREEN** (added `quotations:expire-commercial`) |
| Supplier quote expiry | **GREEN** (added `quotations:expire-supplier-quotes`) |
| Scheduled blog publish | **GREEN** (wired `blog:publish-scheduled`) |
| OTP prune | **GREEN** (wired `otp:prune-expired`) |
| Payments reconcile | **GREEN** |
| Scheduler runner on deploy | **GREEN** (`schedule:work` backgrounded in `backend/scripts/render-start.sh`) |
| Google Calendar sync cron | **YELLOW** | On-demand only; optional reconcile if needed |
| Duplicate artisan commands | **YELLOW** | Two `catalog:pdf-import-report` classes (not scheduled) — delete one |

---

## Google / Zoom

| Integration | Status |
|-------------|--------|
| Google Calendar missing credentials | **GREEN** — `configured: false` / 422, app usable |
| Google Meet missing credentials | **GREEN** — provider availability flags |
| Zoom missing credentials | **GREEN** — same pattern |
| Zoom redirect URI in `.env.example` | **YELLOW** — unused (S2S); remove or document |

---

## Files & storage

| Item | Status | Notes |
|------|--------|-------|
| Upload/download auth | **GREEN** | Policies on workspace/customer/media |
| Private disk default | **GREEN** | `FILESYSTEM_DISK=local` |
| Render ephemeral disk | **YELLOW** | Uploads lost on sleep/redeploy without S3. **Fix:** configure `AWS_*` / S3 for real production |

---

## Database / migrations

| Item | Status | Notes |
|------|--------|-------|
| Additive migrations | **GREEN** | No destructive `up()` wipes |
| Column `change()` | **YELLOW** | Verify on target MySQL before go-live |
| Forbidden ops | **GREEN** | Do not run `migrate:fresh` / `db:wipe` in production |

---

## Production environment

| Item | Status | Problem / impact / fix (if not GREEN) |
|------|--------|----------------------------------------|
| Frontend production build | **GREEN** | `vite build` succeeds |
| SPA fallback | **GREEN** | Render rewrite + `_redirects` |
| API health | **GREEN** | `GET /api/health` |
| CORS | **GREEN** | Env allowlist (configure prod origin) |
| HTTPS | **YELLOW** | Depends on host TLS termination — confirm on Render/custom domain |
| Storage | **YELLOW** | Use durable object storage for prod |
| Cron / scheduler | **GREEN** | `schedule:work` in start script |
| Queue | **YELLOW** | `QUEUE_CONNECTION=sync` in blueprint. **Impact:** blocks requests under load. **Fix:** `database`/`redis` + worker |
| Cache | **YELLOW** | Confirm `CACHE_STORE` / Redis for multi-instance locks |
| Mail | **YELLOW** | `MAIL_MAILER=log` in blueprint. **Impact:** resets/verify/quote emails never leave the host. **Fix:** real SMTP/SES + `MAIL_FROM_*` |
| OAuth env | **GREEN** | Optional `GOOGLE_*` / `ZOOM_*`; empty = disabled |
| Demo seed | **YELLOW** | `RUN_DB_SEED=true` in Render blueprint — disable after first boot |

---

## Fixes applied during this audit

1. Customer quote-request scrub for internal commercial fields (prior session) + isolation test.
2. Portfolio catalog test uses public slug detail endpoint.
3. `ContentMedia::isPublishedParent()` requires parent `SupplierVisibility::Public`.
4. Supplier documents default/force `local` disk; client `disk` prohibited.
5. Commands + schedule: `quotations:expire-commercial`, `quotations:expire-supplier-quotes`, `blog:publish-scheduled`, `otp:prune-expired`.
6. `render-start.sh` starts `php artisan schedule:work` alongside the HTTP server.
7. Feature coverage in `tests/Feature/ProductionIntegrationAuditTest.php`.

---

## Go-live blockers (must clear before claiming production-ready)

1. **RED — Invoices** domain missing or product language corrected.
2. **YELLOW→ops — Mail + queue + durable storage** still demo-grade in deploy blueprint.
3. **YELLOW — Supplier document upload/download** should become real private-disk upload + gated download before treating supplier docs as production-safe.
4. **YELLOW — Sanctum token TTL / storage** hardening recommended before high-trust production traffic.

Until (1) is resolved and (2)–(3) are accepted or fixed, **do not claim the platform is production-ready**.
