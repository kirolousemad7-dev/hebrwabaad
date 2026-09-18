# HEBR & ABAAD — Production Readiness Report

**Updated:** 2026-09-18 (production hardening + invoices)  
**Verification:** `php artisan test` — **722 passed**; frontend `tsc -b` + `vite build` — **passed**.

**Overall verdict:** Core RED invoice blocker is cleared. The platform is **conditionally production-ready** when operators configure real mail, S3-compatible storage, and set secrets in the host (Render/dashboard). Remaining items below are **YELLOW operational requirements**, not missing product modules.

Do not claim “unconditionally production ready” until mail + durable storage credentials are live in the target environment.

---

## Legend

| Status | Meaning |
|--------|---------|
| **GREEN** | Implemented and verified |
| **YELLOW** | Acceptable with documented operational requirement |
| **RED** | Blocking production |

---

## Modules

| Module | Status |
|--------|--------|
| Authentication | **GREEN** (Sanctum PAT + configurable TTL) |
| Authorization | **GREEN** |
| Owner / Employees / CRM | **GREEN** |
| Suppliers / portal / registration | **GREEN** |
| Email / phone / OTP (supplier) | **YELLOW** (supplier-scoped by design) |
| Quotations / sourcing | **GREEN** |
| **Invoices** | **GREEN** (first-class module) |
| Payments | **GREEN** |
| Files / supplier documents | **GREEN** (private upload + gated download) |
| Calendar / Meet / Zoom | **GREEN** (graceful without credentials) |
| Blog / SEO / public pages / sidebar | **GREEN** |
| Notifications | **GREEN** |

### Invoices — **GREEN**
- Models: `Invoice`, `InvoiceItem`, `InvoiceEvent`; payments link via `invoice_id`
- Lifecycle: DRAFT → ISSUED → SENT → PARTIALLY_PAID / PAID / OVERDUE / CANCELLED / VOID
- Staff APIs under `/api/operations/invoices/*`; customer `/api/customer/invoices/*`
- Server-generated numbers (`INV-YYYY-####`); server-side totals; customer payload strips `internal_notes` / events
- Gates: `invoices.view|create|update|issue|send|cancel|record_payment|view_internal`
- Owner UI: `/owner/invoices`; customer: `/dashboard/invoices`
- Scheduler: `invoices:mark-overdue`

---

## Critical business rules

| Rule | Status |
|------|--------|
| Customer never sees supplier identity/cost | **GREEN** |
| Supplier isolation | **GREEN** |
| Invoice customer scrub (no internal notes/supplier) | **GREEN** |
| Server-side authorization | **GREEN** |
| Supplier documents private by default + gated download | **GREEN** |

---

## Hardening phases

| Phase | Status | Notes |
|-------|--------|-------|
| 1 Invoices | **GREEN** | See above |
| 2 Mail | **YELLOW** | Code + Mailables ready (`ShouldQueue`); set `MAIL_MAILER=smtp` + secrets in prod |
| 3 Queue | **GREEN** | `QUEUE_CONNECTION=database` default for prod blueprint; `queue:work` in `render-start.sh`; tests still use `sync` |
| 4 Persistent storage | **YELLOW** | S3 disk configured; set `FILESYSTEM_DISK=s3` + `AWS_*`; supplier docs upload to private disk |
| 5 Sanctum | **GREEN** | `SANCTUM_TOKEN_EXPIRATION_MINUTES` (default 10080 in example); frontend clears token on 401. Bearer+localStorage retained (cookie SPA not adopted — CORS/multi-role complexity) |
| 6 Production env | **YELLOW** | Blueprint updated; operators must fill sync:false secrets |
| 7 Tests | **GREEN** | 722 passed |
| 8 Security | **GREEN** / residual **YELLOW** below |

---

## Security notes (post-hardening)

| Item | Status | Detail |
|------|--------|--------|
| CORS | **GREEN** | Env allowlist |
| Sanctum TTL | **GREEN** | Configurable expiration |
| Token storage | **YELLOW** | Still `localStorage` Bearer — XSS risk; mitigated by TTL + logout clear |
| Supplier docs | **GREEN** | Real upload; path/disk not returned; gated download |
| OTP | **GREEN** | Unchanged hardened controls |
| Mass assignment `User.role` | **YELLOW** | Still fillable; register hardcodes Customer |
| APP_DEBUG | **GREEN** | Render blueprint `false` |

---

## Required environment variables

### Application
- `APP_NAME`, `APP_ENV=production`, `APP_KEY`, `APP_DEBUG=false`, `APP_URL`, `FRONTEND_URL`
- `LOG_LEVEL=error`

### Database
- `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### Auth / CORS
- `SANCTUM_TOKEN_EXPIRATION_MINUTES` (e.g. `10080`)
- `SANCTUM_STATEFUL_DOMAINS=` (empty for Bearer SPA)
- `CORS_ALLOWED_ORIGINS` (exact frontend origin(s))

### Mail (production)
- `MAIL_MAILER=smtp` (or `ses` / `postmark` / `resend`)
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

### Queue
- `QUEUE_CONNECTION=database` (or `redis` if Redis is provisioned)
- Worker: `php artisan queue:work database --tries=3` (started by `scripts/render-start.sh`)

### Storage
- Local/dev: `FILESYSTEM_DISK=local`
- Production: `FILESYSTEM_DISK=s3` plus:
  - `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`
  - Optional R2/compatible: `AWS_ENDPOINT`, `AWS_URL`, `AWS_USE_PATH_STYLE_ENDPOINT`

### Optional integrations
- `GOOGLE_*`, `ZOOM_*`, `PAYTABS_*`, `SMS_*`

### Deploy flags
- `RUN_DB_SEED=false` after first boot

---

## Mail setup
1. Choose SMTP or Laravel-supported API mailer.
2. Set env vars (never commit secrets).
3. Verify with `php artisan tinker` / feature tests using `Mail::fake()` locally (`MAIL_MAILER=array` in phpunit).
4. Existing queued mail: supplier OTP/verify, printing quotation, payment confirmed, portal magic link, approvals, delivery, **invoice issued**.

## Queue setup
1. Ensure `jobs` / `failed_jobs` migrations applied.
2. `QUEUE_CONNECTION=database` (or redis).
3. Run worker: `php artisan queue:work --tries=3` (or rely on `render-start.sh`).
4. Monitor `failed_jobs`.

## Persistent storage setup
1. Create S3/R2 bucket (private ACL).
2. Set `FILESYSTEM_DISK=s3` and AWS credentials.
3. Do not use public disk for supplier/customer private files.
4. Downloads remain application-gated.

## Scheduler setup
`php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`).

Includes: reminders, digests, webhooks, printing/commercial/supplier quote expiry, blog publish, OTP prune, payments reconcile, **invoices:mark-overdue**.

## Authentication architecture
- Sanctum personal access tokens (`Authorization: Bearer`).
- Token TTL via `SANCTUM_TOKEN_EXPIRATION_MINUTES`.
- Frontend stores token in `localStorage`; clears on logout and HTTP 401.
- Cookie SPA auth not enabled (`SANCTUM_STATEFUL_DOMAINS` empty).

## Deployment steps
1. Set all production env vars (mail, queue, storage, CORS, APP_DEBUG=false).
2. Build frontend: `npm run build`; deploy static with SPA fallback.
3. Deploy API Docker image (`backend/Dockerfile` → `scripts/render-start.sh`).
4. Start script runs: migrate, caches, `schedule:work`, `queue:work`, `artisan serve`.
5. Confirm `GET /api/health`, `php artisan schedule:list`, send a test invoice email.
6. Disable `RUN_DB_SEED`.

## Rollback considerations
- Invoice migrations are additive (`invoices`, `invoice_items`, `invoice_events`, `payments.invoice_id`).
- Rollback: `migrate:rollback` only on environments where invoice data can be discarded; never `migrate:fresh` / `db:wipe` in production.
- Morph map: `invoice` → `Invoice` (was mistakenly aliased to `Payment`); `payment` → `Payment`. Re-check any media rows stored with old alias if present.

---

## Remaining RED
**None** in product modules after this hardening pass.

## Remaining YELLOW (operational)
1. Production SMTP/API mail credentials must be configured before go-live email flows work.
2. S3/R2 credentials must be configured or uploads remain on ephemeral disk.
3. Bearer token in `localStorage` (XSS surface) — consider HttpOnly cookie auth in a future hardening pass.
4. `User.role` / `is_active` remain fillable (defense-in-depth).
5. Supplier email/phone/OTP remain supplier-only by product scope.
6. HTTPS termination depends on host (Render terminates TLS).

---

## Go-live checklist
- [ ] `APP_DEBUG=false`
- [ ] Real `MAIL_*`
- [ ] `QUEUE_CONNECTION=database` + worker running
- [ ] `FILESYSTEM_DISK=s3` + AWS/R2 secrets
- [ ] `CORS_ALLOWED_ORIGINS` exact
- [ ] `SANCTUM_TOKEN_EXPIRATION_MINUTES` set
- [ ] `RUN_DB_SEED=false`
- [ ] Smoke: login, create/issue/send invoice, customer view, supplier doc upload/download
