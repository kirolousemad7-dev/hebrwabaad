# Supplier / Vendor Management Architecture

Phase 1 audit and extension plan for Hebr & Abaad. **Additive only** — extend the existing Supplier catalog/content stack; do not invent a parallel Vendor domain.

## Existing relevant tables

| Table | Role | Action |
|-------|------|--------|
| `suppliers` | Core supplier profile + public catalog flags | **Extend** (lifecycle, verification, codes, notes) |
| `supplier_portfolio_items` | Portfolio / previous work + review workflow | **Extend** (visibility, media) |
| `supplier_products` | Supplier catalog products + review workflow | **Extend** (SKU, unit, internal notes, visibility) |
| `supplier_profile_versions` | Pending profile edits for review | Keep |
| `content_reviews` | Morph audit log for content decisions | Keep |
| `content_media` | UUID media uploads | Reuse for documents where appropriate |
| `users` | Auth; `role = SUPPLIER` links via `suppliers.user_id` | Keep |
| `services` / `packages` | Platform public catalog | **Do not reuse** as supplier offerings |
| `crm_companies` / `crm_contacts` / `crm_tags` | Sales CRM | **Do not reuse** for supplier contacts/tags |
| `files` | Project/order/task files | No `supplier_id` today — add dedicated `supplier_documents` |
| `portfolio_items` | HEBR employee case studies | **Do not confuse** with supplier portfolio |
| `printing_*` | Printing ops catalog | Separate domain |

No `vendors` table exists. Soft deletes already apply to portfolio items and products; supplier rows historically hard-delete (Phase 1 adds soft deletes).

## Existing APIs

| Surface | Paths | Notes |
|---------|-------|-------|
| Public catalog | `GET /api/suppliers`, `GET /api/suppliers/{slug}`, portfolio/products | Must never expose `internal_notes`, contacts, documents, verification internals, or unpublished catalog |
| Supplier workspace | `/api/supplier/*` (`role:SUPPLIER`) | Profile + content submit |
| Admin | `/api/admin/suppliers*`, `/api/admin/supplier-reviews*` | OWNER / ADMIN_MANAGER |

**Route conflict note:** User-facing CRUD paths like `POST /api/suppliers` would collide with the public catalog. Management remains under `/api/admin/...`. Nested admin routes cover contacts, services, products, portfolio, documents, categories, and tags.

## Existing React routes

| Path | Page |
|------|------|
| `/suppliers`, `/suppliers/:slug`, … | Public network |
| `/owner/suppliers`, `/owner/suppliers/:id` | Owner admin (extend) |
| `/owner/supplier-reviews` | Content review inbox |
| `/owner/suppliers/new` | **Add** dedicated create flow |
| `/supplier/*` | Supplier self-service workspace |

Reusable UI: `DashboardSection`, `SupplierFilters`, `SupplierCard`, `useAsyncData`, `FeedbackBanner`, services in `supplierWorkspace.ts` / `suppliers.ts`.

## Auth / RBAC

- Custom `UserRole` enum + Sanctum — **not Spatie**.
- Catalog managers: `OWNER`, `ADMIN_MANAGER` via `canReviewContent()` / `canManageCatalog()`.
- Phase 1 adds `SupplierPermission` capability strings mapped onto those roles (and supplier self-scope where needed). Do **not** install a new ACL package without approval.

## What to extend

1. Supplier lifecycle: `status`, `verification_status`, `onboarding_status`, `supplier_code`, legal/display names, geo, WhatsApp, rating, notes / `internal_notes`, audit users, soft deletes.
2. First-class `supplier_contacts`, admin-managed `supplier_categories` (+ pivot), reusable `tags` (+ pivot) scoped for suppliers (separate from `crm_tags`).
3. Structured `supplier_services` (pricing model, price band, lead time) — keep legacy JSON `suppliers.services` for BC.
4. `supplier_documents` (INTERNAL by default).
5. Product / portfolio field extensions + explicit visibility defaults (internal unless published).
6. Richer owner UI: filters, create page, detail tabs (contacts, services, documents, pricing, notes).

## What must not be duplicated

- Do not create a second suppliers table or Vendor models.
- Do not replace `SupplierContentService` / content review pipeline.
- Do not wire supplier contacts into `crm_contacts`.
- Do not auto-publish products to the public site; public visibility still requires catalog publish rules.
- Do not expose internal supplier identity/pricing/documents to customers by default.

## Customer privacy rule

Customers and public catalog consumers see only **published** supplier marketing fields. They must not see: internal notes, verification internals, documents, unpublished products/portfolio, contact directory, or operational pricing unless an explicit public profile path is enabled.

## Remaining after Phase 1 foundation

- Supplier self-registration + application queue (beyond owner-provisioned accounts).
- Deep links: quotations / projects / tasks / execution assignment pivots.
- Bulk actions and full activity timeline UI.
- Formal invite emails and KYC document verification workflows.
