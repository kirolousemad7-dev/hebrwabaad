# HEBR & ABAAD — Production Readiness Report

**Updated:** 2026-09-18 (Google OAuth / Continue with Google)  
**Verification:** run `php artisan test`, frontend `tsc -b`, `npm run build` after deploy.  

**Overall verdict:** Core RED invoice blocker is cleared. Google sign-in is implemented in code (Sanctum Bearer exchange). **Code is ready, but Google Cloud Console credentials/redirect configuration must be verified manually.** Remaining items below are **YELLOW operational requirements**, not missing product modules.

Do not claim “unconditionally production ready” until mail + durable storage credentials are live in the target environment, and Google OAuth client redirect URIs match the live API route.

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
| Authentication | **GREEN** (Sanctum PAT + configurable TTL + Google OAuth exchange) |
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
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI` — Calendar connect callback (`/api/google-calendar/callback`)
- `GOOGLE_AUTH_REDIRECT_URI` — Continue with Google (`/api/auth/google/callback`)
- `ZOOM_*`, `PAYTABS_*`, `SMS_*`

### Deploy flags
- `RUN_DB_SEED=false` after first boot

---

## Google OAuth (Continue with Google)

**Implemented in code.** Operators must configure Google Cloud Console before the button works in production.

### Application routes (actual)
| Step | Method | Path |
|------|--------|------|
| Status | `GET` | `/api/auth/google/status` |
| Start | `GET` | `/api/auth/google/redirect?intent=login\|register\|supplier` |
| Google → API | `GET` | `/api/auth/google/callback` |
| SPA exchange | `POST` | `/api/auth/google/exchange` `{ code }` |
| Frontend finish | page | `/auth/google/callback` |

Production examples (must match Console + env):
- API callback: `https://api.hebrwabaad.com/api/auth/google/callback`
- Frontend origin: `https://hebrwabaad.com`
- Frontend callback page: `https://hebrwabaad.com/auth/google/callback` (SPA route; not registered in Google Console)

### Env
```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://api.hebrwabaad.com/api/google-calendar/callback
GOOGLE_AUTH_REDIRECT_URI=https://api.hebrwabaad.com/api/auth/google/callback
FRONTEND_URL=https://hebrwabaad.com
APP_URL=https://api.hebrwabaad.com
```

### Google Cloud Console checklist
1. Create OAuth 2.0 Client ID (Web application).
2. Authorized JavaScript origins:
   - `https://hebrwabaad.com`
   - `http://localhost:5173` (local Vite)
3. Authorized redirect URIs (exact):
   - `https://api.hebrwabaad.com/api/auth/google/callback`
   - `https://api.hebrwabaad.com/api/google-calendar/callback` (if Calendar connect is used)
   - local: `http://127.0.0.1:8000/api/auth/google/callback` (or your `APP_URL`)
4. Copy Client ID/Secret into host env; never commit secrets.
5. Confirm `GET /api/auth/google/status` returns `configured: true`.

### Behaviour
- New Google email + `login`/`register` intent → Customer + `email_verified_at`
- `supplier` intent → Supplier user + Pending supplier profile (approval unchanged)
- Existing email → link `oauth_accounts` row; no duplicate user
- Never creates Owner/Admin via Google
- Inactive / Blocked / Rejected / Suspended supplier → denied
- Google tokens stay server-side; SPA receives one-time exchange code → Sanctum Bearer token

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
- Google OAuth: browser hits API redirect → Google → API callback → redirect to SPA `/auth/google/callback` with one-time code → `POST /api/auth/google/exchange` → Bearer token.

## Deployment steps
1. Set all production env vars (mail, queue, storage, CORS, APP_DEBUG=false, Google OAuth).
2. Build frontend: `npm run build`; deploy static with SPA fallback.
3. Deploy API Docker image (`backend/Dockerfile` → `scripts/render-start.sh`).
4. Start script runs: migrate, caches, `schedule:work`, `queue:work`, `artisan serve`.
5. Confirm `GET /api/health`, `GET /api/auth/google/status`, `php artisan schedule:list`, send a test invoice email.
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
7. Google Cloud Console OAuth client + `GOOGLE_*` env must be verified manually before “Continue with Google” works in prod.

---

## Go-live checklist
- [ ] `APP_DEBUG=false`
- [ ] Real `MAIL_*`
- [ ] `QUEUE_CONNECTION=database` + worker running
- [ ] `FILESYSTEM_DISK=s3` + AWS/R2 secrets
- [ ] `CORS_ALLOWED_ORIGINS` exact
- [ ] `SANCTUM_TOKEN_EXPIRATION_MINUTES` set
- [ ] `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_AUTH_REDIRECT_URI` set and Console redirect URI exact-match
- [ ] `RUN_DB_SEED=false`
- [ ] Smoke: login, Google login, create/issue/send invoice, customer view, supplier doc upload/download
