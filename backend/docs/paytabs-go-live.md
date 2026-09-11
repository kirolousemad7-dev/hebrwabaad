# PayTabs go-live

Operational guide for enabling PayTabs card checkout in Hebr & Abaad (orders, printing quotations, **and commercial RFQ quotations**). **Never commit or paste live server keys into tickets, docs, or chat.**

## Environment variables

| Variable | Purpose |
| --- | --- |
| `PAYTABS_ENABLED` | Master switch. `false` disables card checkout even if keys exist. |
| `PAYTABS_PROFILE_ID` | Merchant profile ID from the PayTabs dashboard. |
| `PAYTABS_SERVER_KEY` | Server-side Authorization header value (secret). |
| `PAYTABS_BASE_URL` | Regional API host, e.g. `https://secure-egypt.paytabs.com`. |
| `PAYTABS_ENVIRONMENT` | `test` (sandbox) or `live`. |
| `PAYTABS_TIMEOUT` | HTTP timeout seconds (default `15`). |
| `APP_URL` | Public API origin used to build callback + return URLs. |

Also ensure `PaymentSetting.card_enabled` is on for customers (Owner payment settings UI).

Safe status only (no secrets):

```bash
php artisan payments:paytabs-status
```

Expect fields like `configured`, `enabled`, `environment`, `profile_id_hint` — **never** a server key.

Capabilities API: `GET /api/operations/payment-capabilities` includes `paytabs_config` without secrets.

Commercial quotation public payload exposes:

- `paytabs_configured` (boolean)
- `can_checkout` (boolean — accepted + amount due + configured + not PROCESSING)
- `card_unavailable_message` when payment is required but PayTabs is not configured

## Official API surface (already wired)

- **Checkout**: `POST {base}/payment/request` with `tran_type=sale`, `tran_class=ecom`.
- **Query**: `POST {base}/payment/query` by `tran_ref`.
- **Refund**: `POST {base}/payment/request` with `tran_type=refund`, original `tran_ref`, unique `cart_id`, amount/currency/description, `profile_id`, `tran_class=ecom`.
- **Auth**: `Authorization` header = `PAYTABS_SERVER_KEY`.
- **Browser return is not confirmation** — only server callback + query/reconcile mark payments paid.

Callback: `{APP_URL}/api/webhooks/paytabs`  
Return: `{APP_URL}/api/payments/paytabs/return`

Commercial RFQ checkout: `POST /api/public/commercial-quotations/{token}/checkout`

## Sandbox procedure (operator checklist)

1. Set sandbox credentials: `PAYTABS_ENVIRONMENT=test`, sandbox `PAYTABS_PROFILE_ID`, sandbox `PAYTABS_SERVER_KEY`.
2. Set `PAYTABS_ENABLED=true`.
3. Confirm profile ID matches the PayTabs sandbox merchant profile.
4. Set `APP_URL` to a publicly reachable **HTTPS** origin (or tunnel) so PayTabs can hit the callback.
5. Run `php artisan payments:paytabs-status` — confirm `configured=yes`, environment `test`, no secrets printed.
6. Create a commercial quote request → Owner prices → send → Customer accepts with **DEPOSIT** or **FULL** payment policy.
7. Open `/cq/{token}` and start **ادفع الآن** (only visible when `can_checkout=true`).
8. Complete the PayTabs sandbox card transaction in the hosted page.
9. Verify server-side provider query / webhook marks the Payment `PAID` (do not trust browser return alone).
10. Re-open the quotation: payment summary shows paid / remaining; `requirement_met` when deposit/full satisfied.
11. Verify execution eligibility still follows existing ops rules (acceptance alone does **not** start production).
12. Replay / duplicate the same callback payload — payment must stay single / idempotent (no duplicate paid rows).
13. Run `php artisan payments:reconcile-pending` and confirm PROCESSING rows reconcile safely.

Repeat step 6–11 for both **DEPOSIT** and **FULL**. Optionally exercise Owner refunds after a paid sandbox sale.

Also still valid for order checkout and printing quotation checkout.

## Production switch checklist

1. Rotate to **live** profile ID + server key in the host secret store (not `.env` in git).
2. Set `PAYTABS_ENVIRONMENT=live` and the live `PAYTABS_BASE_URL` for your region.
3. Confirm `APP_URL` is the production API hostname (HTTPS).
4. Confirm PayTabs dashboard callback allow-list matches production URLs.
5. `payments:paytabs-status` → configured, live environment, profile hint matches last 4 of live profile.
6. Smoke-test one low-value live sale + query reconcile (order **or** commercial quotation).
7. Confirm `payments:reconcile-pending` is scheduled.
8. Keep `PAYTABS_ENABLED=true` only after the smoke test passes.

## Rollback

1. Set `PAYTABS_ENABLED=false` (or clear `PAYTABS_SERVER_KEY`) — card checkout returns unavailable; commercial UI shows «الدفع الإلكتروني غير متاح حالياً» when payment is required.
2. Optionally disable `card_enabled` in payment settings so the UI hides card.
3. Leave pending `PROCESSING` payments for `payments:reconcile-pending` or Owner reconcile; do not force-mark paid.
4. Revert env to sandbox only after finishing live reconciliation.

## Common failures

| Symptom | Likely cause |
| --- | --- |
| Card checkout 503 / no ادفع الآن | Missing key, `PAYTABS_ENABLED=false`, or PayTabs HTTP error/timeout |
| Stays `PROCESSING` | Callback not reachable (`APP_URL`), or return treated as paid (it must not be) |
| `FAILED` + `mismatch:amount/currency/profile` | Cart amount/currency/profile do not match payment row |
| Refund fails | Original `tran_ref` missing, amount over refundable, or PayTabs declined (`response_status` ≠ `A`) |
| Secrets in logs | Never log `Authorization` / server key; use `payments:paytabs-status` / validator (key never returned) |

## Ops commands (read-only)

```bash
php artisan payments:paytabs-status
php artisan operations:health-check
```
