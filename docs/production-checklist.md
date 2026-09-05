# HEBR production deployment checklist

Step-by-step Render instructions live in [`DEPLOYMENT.md`](../DEPLOYMENT.md).

Use this list before the first production deploy. Do not copy secrets into git.

## Application

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` generated and stored in the secret manager (`php artisan key:generate --show`)
- [ ] `APP_URL` is the public API origin (HTTPS)
- [ ] `FRONTEND_URL` is the public SPA origin (used in password-reset emails)
- [ ] `APP_LOCALE=ar`

## Database (MySQL)

- [ ] `DB_CONNECTION=mysql`
- [ ] MySQL 8 is reachable from the app host
- [ ] Credentials are in the secret manager, not in git
- [ ] Run `php artisan migrate --force` against an empty or maintained schema
- [ ] Confirm foreign keys, unique indexes, JSON columns, and enums/strings after migrate
- [ ] Run `php artisan test` with MySQL (override phpunit sqlite) on the staging host:
      `DB_CONNECTION=mysql DB_DATABASE=hebr_staging DB_USERNAME=... DB_PASSWORD=... php artisan test`
- [ ] Do not run `migrate:fresh` or `db:wipe` against production data

## Mail (required for Forgot Password)

- [ ] `MAIL_MAILER` is a real provider (`smtp`, `ses`, `postmark`, or `resend`) — not `log`
- [ ] `MAIL_FROM_ADDRESS` is a domain the provider can send from
- [ ] Send one password-reset email on staging and open the `FRONTEND_URL/reset-password` link

## HTTP / Auth

- [ ] `CORS_ALLOWED_ORIGINS` lists only the production SPA origin(s)
- [ ] `SANCTUM_STATEFUL_DOMAINS` stays empty unless cookie auth is intentionally enabled
- [ ] TLS terminates in front of the API and the SPA
- [ ] Rate limiters `hebr-login`, `hebr-register`, `hebr-password`, `hebr-consultations`, `hebr-messages`, `hebr-uploads` are active (they are disabled only during PHPUnit unless `testing.force_rate_limits=true`)

## Storage

- [ ] `FILESYSTEM_DISK=local` (private root `storage/app/private`)
- [ ] `php artisan storage:link` is allowed only for `storage/app/public`
- [ ] Private uploads are not under the public document root
- [ ] Upload directory is writable by the app user and not world-readable beyond what the host requires

## Frontend build

- [ ] `VITE_API_URL` is the public API origin (no localhost)
- [ ] `npm run build` succeeds (TypeScript + Vite)
- [ ] Built assets do not contain `127.0.0.1:8000` or development API hosts
- [ ] SPA is served over HTTPS with `index.html` fallback for client routes

## Go-live smoke

- [ ] Register creates CUSTOMER only
- [ ] Login / logout / inactive account rejection
- [ ] Forgot password email + reset + old tokens rejected
- [ ] Customer isolation (projects, orders, files, notifications)
- [ ] Employee workspace sees assigned work only
- [ ] Owner / Admin Manager / HR scopes unchanged
- [ ] API 500 responses are `{"success":false,"message":"Server error."}` with no traces
