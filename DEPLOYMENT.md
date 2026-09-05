# HEBR & ABAAD — demo deployment

This repository is a split app:

- `backend/` — Laravel 13 REST API (PHP 8.4, Sanctum Bearer tokens, MySQL 8)
- `frontend/` — React 19 + TypeScript + Vite SPA

Default hosting target: **Render**.

- Laravel has no first-class Render runtime, so the API uses **Docker** (PHP 8.4).
- The React app is a **Render Static Site**.
- Render MySQL is **paid**. Attach any MySQL 8 host through environment variables. Do not switch this project to SQLite or PostgreSQL.

Do not commit `.env` files, passwords, `APP_KEY`, PayTabs Server Key, or database credentials.

---

## 1. Requirements

- GitHub account
- Render account (free web + static services are enough)
- MySQL 8 database reachable from Render
- Node.js 20+ (for local frontend builds)
- PHP 8.3+ / 8.4 and Composer 2 (for local API checks)

Optional:

- PayTabs TEST Server Key if you want live card checkout in the demo
- A real mailer if you want Forgot Password emails (default demo mailer is `log`)

---

## 2. GitHub setup

The repo is already a Git working tree. There is no remote until you add one.

```bash
git add .
git status
# Review the staged files. Confirm no .env or secret files are included.
git commit -m "Prepare HEBR for Render demo deployment."
git remote add origin https://github.com/YOUR-USER/YOUR-REPO.git
git push -u origin HEAD
```

Connect that GitHub repo to Render (Blueprint or manual services).

---

## 3. Database setup

Create an empty MySQL 8 database. Then set these API variables:

- `DB_CONNECTION=mysql`
- `DB_HOST`
- `DB_PORT` (usually `3306`)
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Free / low-cost MySQL options that stay compatible:

- Any existing MySQL 8 server
- Aiven / TiDB Cloud Serverless (MySQL protocol)
- Render MySQL if you are willing to use a paid database

The first API boot runs:

```bash
php artisan migrate --force
```

To load the catalog and synthetic review accounts on the first deploy, set `RUN_DB_SEED=true`. The seeder is idempotent and does not overwrite existing demo passwords.

Never run `migrate:fresh` or `db:wipe` against a database that already has review data.

---

## 4. Backend deployment (Render Web Service)

Preferred: apply `render.yaml` from the GitHub repo.

Manual equivalent:

1. New **Web Service**
2. Root directory: `backend`
3. Runtime: **Docker**
4. Dockerfile path: `./Dockerfile`
5. Health check: `/api/health`
6. Plan: Free

Generate the app key locally (do not commit it):

```bash
cd backend
php artisan key:generate --show
```

Paste the output into `APP_KEY`.

Set `APP_URL` to the public API origin Render assigns, for example:

`https://hebr-abaad-api.onrender.com`

The container start script:

1. Creates writable storage directories
2. Runs `php artisan storage:link --force` (safe if unused)
3. Runs `php artisan migrate --force`
4. Seeds when `RUN_DB_SEED=true`
5. Caches config, routes, and views
6. Serves the API on `$PORT`

---

## 5. Frontend deployment (Render Static Site)

1. New **Static Site**
2. Root directory: `frontend`
3. Build command: `npm ci && npm run build`
4. Publish directory: `dist`
5. Rewrite: `/*` → `/index.html` (already in `render.yaml` and `frontend/public/_redirects`)

`VITE_API_URL` is baked in at **build time**.

Use the API origin only. Do **not** append `/api`.

```
VITE_API_URL=https://hebr-abaad-api.onrender.com
```

The SPA already calls paths such as `/api/auth/login` and `/api/packages`.

Deploy order:

1. Deploy the API and copy its URL
2. Set `VITE_API_URL` on the static site
3. Set `FRONTEND_URL` and `CORS_ALLOWED_ORIGINS` on the API to the static-site URL
4. Redeploy the frontend
5. Redeploy the API (or restart it) so CORS picks up the frontend origin

---

## 6. Required environment variables

### API (Render Web Service)

| Variable | Required? | Example / notes |
|---|---|---|
| `APP_ENV` | Yes | `production` |
| `APP_DEBUG` | Yes | `false` |
| `APP_KEY` | Yes | Output of `php artisan key:generate --show` |
| `APP_URL` | Yes | `https://hebr-abaad-api.onrender.com` |
| `FRONTEND_URL` | Yes | `https://hebr-abaad-web.onrender.com` |
| `LOG_CHANNEL` | Yes | `stack` |
| `LOG_LEVEL` | Yes | `error` |
| `DB_CONNECTION` | Yes | `mysql` |
| `DB_HOST` | Yes | MySQL host |
| `DB_PORT` | Yes | `3306` |
| `DB_DATABASE` | Yes | Database name |
| `DB_USERNAME` | Yes | Database user |
| `DB_PASSWORD` | Yes | Database password |
| `SESSION_DRIVER` | Yes | `file` |
| `CACHE_STORE` | Yes | `file` |
| `QUEUE_CONNECTION` | Yes | `sync` |
| `FILESYSTEM_DISK` | Yes | `local` |
| `SANCTUM_STATEFUL_DOMAINS` | Yes | Leave empty (Bearer tokens) |
| `CORS_ALLOWED_ORIGINS` | Yes | Exact frontend origin, e.g. `https://hebr-abaad-web.onrender.com` |
| `MAIL_MAILER` | Yes | `log` for demo, or a real mailer |
| `RUN_DB_SEED` | First deploy | `true` then optionally `false` |
| `DEMO_PASSWORD` | If seeding | Shared demo password, e.g. `DemoPass123!` |
| `PAYTABS_PROFILE_ID` | Card payments only | PayTabs profile id |
| `PAYTABS_SERVER_KEY` | Card payments only | Secret. Never put this in git. |
| `PAYTABS_BASE_URL` | Card payments only | `https://secure-egypt.paytabs.com` |
| `PAYTABS_ENVIRONMENT` | Card payments only | `test` for the demo |

### Frontend (Render Static Site)

| Variable | Required? | Example / notes |
|---|---|---|
| `VITE_API_URL` | Yes | `https://hebr-abaad-api.onrender.com` — no `/api` suffix |

---

## 7. CORS configuration

Laravel reads `CORS_ALLOWED_ORIGINS` (comma-separated exact origins).

Production example:

```
CORS_ALLOWED_ORIGINS=https://hebr-abaad-web.onrender.com
```

Do not use `*`. The SPA sends `Authorization: Bearer ...` from a different origin.

After the frontend URL is known, update this variable and restart the API.

---

## 8. Sanctum configuration

Authentication is **token-based**, not cookie-based.

- Login/register return a Sanctum personal access token
- The SPA stores it in `localStorage` and sends `Authorization: Bearer`
- Keep `SANCTUM_STATEFUL_DOMAINS` empty
- Do not enable first-party cookie auth for this demo

Owner, employee, and customer dashboards stay behind the same role middleware.

---

## 9. Migration commands

Safe production commands (already in the API start script):

```bash
php artisan migrate --force
php artisan db:seed --force   # only when RUN_DB_SEED=true
```

Never run:

```bash
php artisan migrate:fresh
php artisan db:wipe
```

---

## 10. Storage setup

Uploads use the **private local disk** (`storage/app/private`):

- Customer / workspace files
- Printing-request attachments

They are served through authenticated download/preview routes. They are not public URLs.

`php artisan storage:link` only links `storage/app/public`. The app does not depend on that public link for private files.

**Demo limitation:** Render Free web services have an ephemeral filesystem. Uploaded files are lost when the service sleeps, restarts, or redeploys. That is acceptable for a temporary review. Do not switch the storage architecture unless you later add an S3-compatible disk.

---

## 11. Build commands

Frontend:

```bash
cd frontend
npm ci
npm run build
```

Laravel (inside the Docker image / Render start script):

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
```

---

## 12. Render configuration

`render.yaml` defines:

- `hebr-abaad-api` — Docker web service
- `hebr-abaad-web` — static site with SPA rewrite

Values marked `sync: false` must be typed in the Render dashboard. The blueprint never contains secrets.

Free-tier note: the API sleeps after idle time. The first request after sleep can take 30–60 seconds.

---

## 13. How to update the deployment

1. Push to GitHub
2. Render rebuilds both services
3. If `VITE_API_URL` changed, trigger a **frontend** rebuild
4. If `CORS_ALLOWED_ORIGINS` or `APP_URL` changed, restart the **API**
5. Leave `RUN_DB_SEED=true` only if you still want catalog/demo account top-up (idempotent)

---

## 14. Common deployment errors

| Symptom | Likely cause | Fix |
|---|---|---|
| Frontend shows a blank page on refresh of `/login` | Missing SPA rewrite | Confirm `/*` → `/index.html` |
| Browser CORS error | `CORS_ALLOWED_ORIGINS` does not match the exact frontend origin | Set the `https://...onrender.com` origin and restart the API |
| `VITE_API_URL must be set for production builds` | Frontend built without the env var | Set `VITE_API_URL` and rebuild the static site |
| API calls go to `/api/api/...` | `VITE_API_URL` ended with `/api` | Use the origin only |
| `APP_KEY is required` | Missing API secret | Generate with `php artisan key:generate --show` |
| `SQLSTATE[HY000] [2002]` | MySQL host unreachable | Open the DB firewall to Render outbound IPs / use a public host |
| Card checkout never returns | Missing public `APP_URL` or PayTabs Server Key | Set TEST keys; callback is `{APP_URL}/api/webhooks/paytabs` |
| Forgot Password does nothing visible | `MAIL_MAILER=log` | Expected for the free demo |
| Uploaded file disappears later | Render Free disk reset | Expected demo limitation |
| First visit is very slow | Free service was asleep | Wait for cold start, then retry |

---

## 15. Demo limitations

- Render Free API sleeps when idle
- Uploaded files are ephemeral
- Forgot Password emails are not delivered unless you add a real mailer
- Card payments stay on PayTabs TEST unless you supply live keys
- Manual InstaPay / bank details seeded for review are **fake demo accounts**, not real company accounts
- Do not put real customer data in this demo database

### Review accounts (created when `RUN_DB_SEED=true`)

| Role | Email | Password |
|---|---|---|
| Owner | `owner.demo@hebr.test` | `DEMO_PASSWORD` or `DemoPass123!` |
| Customer | `customer.demo@hebr.test` | same |
| Account Manager | `manager.demo@hebr.test` | same |
| Employee | `employee.demo@hebr.test` | same |

These are synthetic emails for manager review. They are not real people.

Change `DEMO_PASSWORD` in Render before sharing the URL if you want a private review password.

---

## Local production-like check

```bash
# API
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
cd ../frontend
VITE_API_URL=http://127.0.0.1:8000 npm run build
```

Clear Laravel caches before returning to local development:

```bash
cd backend
php artisan config:clear
php artisan route:clear
php artisan view:clear
```
