# Habr & Abad — Frontend audit

Audit date: 17 October 2026. Scope is discovery, local run, and a page inventory. No redesign and no business-logic edits were made for this audit.

Evidence labels:

- **Verified** — loaded in the browser against the local servers, or confirmed with a read-only API/status check.
- **Source** — read from routes and components. Not clicked while signed in.

## 1. Executive summary

حبر وأبعاد is a working Arabic RTL product, not a prototype shell. The public site renders on `http://localhost:5173` against a local Laravel API on `http://127.0.0.1:8000`. The homepage, catalog section, login, and an unauthenticated owner redirect all loaded. The local API returned 71 services, 8 packages, and the public marketing payload.

The public marketing surface is the most complete part of the UI: navigation, hero, discovery card, service categories, priced packages (after load), contact form, footer, and floating WhatsApp. Authenticated workspaces (owner, customer, employee, supplier, CRM, printing) are implemented in source and guarded by Sanctum tokens. They were not exercised in the browser because no session was present, and this audit did not create accounts or read `.env`.

Card checkout cannot complete locally. PayTabs Hosted Payment Page is enabled in test mode, but the server key is missing, so the app correctly refuses checkout. The configured PayTabs host is the Egypt host while the region is SAU.

The homepage package block briefly shows three invented packages («الباقة الأساسية»، «الباقة الاحترافية»، «الباقة المتكاملة»، all «السعر عند الطلب») while `getPublicPackages` is still loading. After the request finishes, those cards are replaced by the real priced packages. That flash is a content-accuracy bug, not a stuck empty API.

## 2. Detected technology stack

Discovered from `frontend/package.json`, `backend/composer.json`, `frontend/index.html`, and `frontend/src/index.css`. Not assumed.

| Layer | What is installed |
|---|---|
| Frontend | React 19, TypeScript ~5.7, Vite 6, React Router 7 |
| Styling | Tailwind CSS 4 via `@tailwindcss/vite`. Brand tokens in `frontend/src/index.css` `@theme` |
| Motion / interaction | Framer Motion, dnd-kit |
| 3D | three.js (present as a dependency; not part of the public pages verified here) |
| Tests / lint | Vitest 4, ESLint 9 |
| Backend | Laravel 13 (`laravel/framework` ^13.17), PHP ^8.3, Sanctum 4, Pint, PHPUnit 12 |
| Documents | dompdf, ar-php, phpspreadsheet |
| Auth | Sanctum bearer token in `localStorage` key `hebr_abaad_token`. Not cookie SPA auth |
| Language | Public HTML is `lang="ar"` `dir="rtl"`. Fonts: IBM Plex Sans Arabic and Inter |
| Brand tokens | Cobalt `#315CFF`, ink `#111318`, paper `#F7F5EF`, success `#138A5B`, warning `#D99300`, error `#D83A52` |
| Money | Riyal glyph U+20C1 with SAR fallback, `dir="ltr"`, English digits (`SarAmount`) |

Local runtimes: Node v22.15.0 (engines `>=20`), PHP 8.4.24. `frontend/node_modules` and `backend/vendor` were already installed. Nothing was upgraded.

## 3. Local development setup

| Item | Finding |
|---|---|
| Frontend env example | `frontend/.env.example` sets `VITE_API_URL=http://127.0.0.1:8000` with no `/api` suffix. Client paths already start with `/api/...` |
| Backend env example | `backend/.env.example` defaults to MySQL database `hebr_abaad`. Credential fields are empty. PayTabs comments require Profile ID + Server Key |
| Live local config | `database.default` is `sqlite`. File: `backend/database/qa.sqlite`. This is the local QA database, not production |
| Existing env files | `backend/.env` and `frontend/.env` exist. They were not opened, printed, or overwritten |
| Migrations / seed | Not run for this audit |
| Dependency install | Not required |

`npm run dev` and `npm run lint` on this machine fail when the shell resolves `node_modules\.bin` shims, because the folder name `hebr&abaad` splits the command. Use the Node and PHP binaries directly, with the paths quoted.

Frontend:

```powershell
cd D:\hebr&abaad\frontend
node --max-old-space-size=4096 .\node_modules\vite\bin\vite.js --host 127.0.0.1 --port 5173
```

Backend:

```powershell
cd D:\hebr&abaad\backend
php artisan serve --host=127.0.0.1 --port=8000 --no-interaction
```

Do not run `composer setup`. That script copies env, generates a key, and runs `migrate --force`.

## 4. Working localhost URLs

| Surface | URL | Status |
|---|---|---|
| Frontend | http://localhost:5173 | Running. Vite reported Local |
| Frontend (explicit host) | http://127.0.0.1:5173 | Same dev server |
| Backend | http://127.0.0.1:8000 | Running. `php artisan serve` reported Server running |
| API sample | `GET /api/services` 200 (71), `GET /api/packages` 200 (8), `GET /api/public/marketing` 200 | Verified |

## 5. Frontend route inventory

Statuses: Complete, Partially complete, Placeholder, Broken, Not implemented.

UI quality is a judgment of the rendered or source UI, not a redesign score. Priority is the next action if that page is the work item.

### Public marketing (verified unless noted)

| Page / Route | Current Status | UI Quality | Functionality | Missing Elements | Priority |
|---|---|---|---|---|---|
| `/` Home | Partially complete | Strong RTL marketing page. Header, hero, discovery card, six category cards, footer, WhatsApp verified | Real services and, after load, real priced packages. Contact form present | Invented package cards flash while packages load. Portfolio/partners were still loading on the first snapshot, then partners finished | P1 |
| `/branding-design` | Complete | Section hero, breadcrumbs, footer. 7 articles after load. No horizontal overflow at 1920 or 390 | Catalog section page. Services and packages load from the API | Package images are placeholders when `image_url` is null (local QA has no package media) | P2 |
| `/services` | Partially complete | Same public chrome. Source uses a large empty card when nothing is published | List is implemented | Empty state copy «الخدمات قيد التجهيز» remains on this page. Not re-opened after data load in this pass | P2 |
| `/services/:slug` | Partially complete | Detail layout exists in source | Loads one service, related services, price line | Not opened in the browser this pass | P2 |
| `/packages` | Partially complete | Equal-height grid in source (`sm` 2 cols, `xl` 3) | Public package list | Not opened after the homepage confirmed packages exist | P2 |
| Catalog sections: `/business-diagnosis`, `/content-production`, `/websites-stores`, `/printing-packaging`, `/events-management`, `/digital-marketing`, `/photography-video`, `/social-media`, `/brand-identity` | Partially complete | Same `CatalogSectionPage` as `/branding-design` | Routed. One section was verified in the browser | Other section slugs were not each opened | P3 |
| `/solutions`, `/portfolio`, `/suppliers`, `/suppliers/:slug` | Partially complete | Public layout. Homepage partners section finished loading | Pages exist and are linked | Individual supplier and portfolio pages were not opened | P2 |
| `/consultant`, `/build-package` | Partially complete | Builder/discovery flows exist in source | Linked from nav and homepage | End-to-end submit was not executed | P2 |
| `/about`, `/contact`, `/guarantee`, `/terms`, `/privacy`, `/shipping-policy`, `/return-policy` | Partially complete | Footer links resolve in the DOM | CMS/static legal pages are routed | Page bodies were not opened one by one | P3 |
| `/login`, `/register`, `/forgot-password` | Partially complete | Login form verified: email, password, OTP tab, Google button, forgot-password link | Password form is usable. Google button enables after a short “جاري التحضير...” | Google and OTP were not submitted. No account was created | P2 |
| `/payment/return`, `/payment/result` | Partially complete | Return page exists | Depends on PayTabs callback | Checkout cannot be completed until the server key is set | P0 |
| `/q/:token`, `/cq/:token`, `/track/:token`, `/portal/:token`, `/crm/q/:token` | Partially complete | Token pages sit outside the public layout | Implemented for quotes, tracking, and portals | No live token was opened | P2 |
| Unknown path | Complete | `NotFoundPage` is the catch-all | Source-wired | Not opened | P3 |

### Authenticated areas (source, except the owner redirect)

`/owner` with no token redirected to `/login`. Verified. The pages below were not clicked while signed in.

| Page / Route | Current Status | UI Quality | Functionality | Missing Elements | Priority |
|---|---|---|---|---|---|
| `/owner` and owner operations, finance, team, printing, settings | Partially complete | `OwnerLayout` and many owner pages exist | Role gate: OWNER (and narrower gates per route) | Browser pass while signed in | P1 |
| `/owner/services`, `/owner/packages`, `/owner/website` and related catalog manager routes | Partially complete | Admin price fields (current, before, discount) are in the forms | API tests cover save/clear for service and package discounts | Owner form was not clicked in the browser | P1 |
| `/dashboard`, `/customer/*` | Partially complete | `CustomerLayout` | Customer orders, quotes, account | Signed-in pass | P2 |
| `/workspace`, `/workspace/directory` | Partially complete | `EmployeeLayout` | Employee roles in `EMPLOYEE_WORKSPACE_ROLES`; HR directory is separate | Signed-in pass | P2 |
| Printing operations routes | Partially complete | `PrintingOperationsLayout` | Specialist/owner printing flows | Signed-in pass | P2 |
| `/supplier/*` operational pages | Partially complete | `SupplierLayout` | Orders and profile paths exist | Signed-in pass | P2 |
| `/supplier/projects`, `/supplier/tasks`, `/supplier/calendar`, `/supplier/messages` | Placeholder | `SupplierModulePlaceholderPage` | Routes exist so the nav does not 404 | Real modules | P2 |
| `/crm/*` | Partially complete | `CrmLayout` | Pipeline, quotes, targets | Signed-in pass | P2 |

## 6. Current UI implementation status

The design system is already in use: cobalt/ink/paper tokens, IBM Plex Sans Arabic, RTL document direction, shared buttons, `SarAmount`, public nav/footer, and catalog cards. The homepage and `/branding-design` match that system. Header logo, hero discovery card, footer columns, copyright «© 2026 حبر وأبعاد. جميع الحقوق محفوظة.», and WhatsApp `0579434114` rendered.

Package cards on the catalog section use a 4:3 visual. With no `image_url` they show the brand mark, not a broken image. That matches the local database, which has no package media rows.

## 7. Working features

- Public shell: skip link, main nav, “المزيد”, login/register, breadcrumbs, footer policy links, floating discovery and WhatsApp.
- Homepage Arabic RTL layout at a 1920-wide viewport. No horizontal overflow (`scrollWidth` 1905).
- `/branding-design` at 1920 and at a 390-wide mobile metric. No horizontal overflow at 390.
- Login page fields and the unauthenticated redirect from `/owner` to `/login`.
- Public catalog API (services, packages, marketing).
- After load, homepage package prices render as riyal/SAR amounts (32 currency hits, zero «السعر عند الطلب»).

## 8. Broken or incomplete features

- **PayTabs checkout (P0).** Read-only `php artisan payments:paytabs-status`: integration `hosted_payment_page`, enabled, mode `test`, region `SAU`, currency `SAR`, `configured` no, server key absent. The status command reports the required sentence: “PayTabs Profile ID and Server Key are required for Hosted Payment Page.” `base_host` is `secure-egypt.paytabs.com` while the region is SAU. An empty `PAYTABS_BASE_URL` is what selects the SAU host. Merchant id, public key, and secret key are not set and are not substitutes for Profile ID + Server Key.
- **Homepage package flash (P1).** `FALLBACKS` in `frontend/src/components/landing/PackagesSection.tsx` render whenever the package list is still empty, including the loading state. They are illustrative names and “السعر عند الطلب”, not API rows.
- **Supplier module routes (P2).** Four routes are explicit placeholders.
- **Local package photography.** Cards are structurally fine. Local QA has no package images, so visitors see the brand placeholder until an owner uploads media.
- **Signed-in product.** Owner CRUD, CRM, workspace, and customer journeys are implemented in source and tests from earlier work. They were not re-verified in the browser in this audit.

## 9. UI/UX issues

- Fake packages appear before real ones on the homepage. A skeleton should occupy that slot until `useAsyncData` is `ready`.
- Several icon-only buttons on the homepage service cards have no accessible name in the snapshot (empty `button` refs next to “تفاصيل الخدمة”).
- Login’s Google control is disabled with “جاري التحضير...” and then becomes “المتابعة باستخدام Google”. The wait is visible but unexplained.
- Catalog empty copy on `/services` («الخدمات قيد التجهيز») is a large empty state. The section page no longer uses that pattern.
- Discovery and WhatsApp floats are present on content pages. They were not covering the footer in the snapshots taken here. A full scroll-to-footer overlap check was not repeated at every viewport.

## 10. Responsive issues

Checked with device metrics, not by resizing a physical device.

| Viewport | Page | Result |
|---|---|---|
| ~1920 CSS px | `/` and `/branding-design` | No horizontal overflow |
| 390×844 | `/branding-design` | No horizontal overflow. A control that can open the menu is present |

Tablet (768) and the other public pages were not remeasured in this pass. Homepage package cards use a horizontal snap row below the `lg` grid, which is the intended mobile pattern and needs a thumb-scroll check on a real phone.

## 11. Technical issues

ESLint (`node .\node_modules\eslint\bin\eslint.js . -f json`), 17 October 2026:

- 2 errors, 12 warnings, 14 files.
- Errors: `CatalogHero.tsx` unused `_tone`; `FileLibrary.tsx` redundant `Boolean` call.
- Warnings: `react-refresh/only-export-components` (5), `react-hooks/exhaustive-deps` (5), plus one unused-var warning path already counted in the two errors above. Context files (`AuthContext`, `ToastContext`, `PublicMarketingContext`, `PlatformSettingsContext`) export hooks beside components.

`npm run lint` itself was not used, because the `.bin` shim breaks on this path. The Node binary invocation is the valid check.

Type-check (`node .\node_modules\typescript\bin\tsc -b --pretty false --noEmit`) exited 0 in about 21 seconds. This audit does not change TypeScript sources.

No production build was run during the audit, to keep memory available for the Vite dev server. Vite has previously exited out of memory (2147483651) when a production build and the dev server overlapped. The dev server for this audit was started with `--max-old-space-size=4096`.

State: React context for auth, marketing, platform settings, and toasts. Server data uses `useAsyncData` per section. There is no second global store.

API errors: catalog pages show Arabic loading lines («جاري تحميل الخدمات...», «جاري تحميل الباقات...», «جاري تحميل الشركاء...»). PayTabs misconfiguration fails with one explicit English sentence rather than a generic 500.

Accessibility basics that are in place: `lang="ar"`, `dir="rtl"`, skip link, named `<nav>` landmarks, required fields on login and the contact form. Gaps: unnamed icon buttons on the homepage service cards.

Maintainability: public chrome is shared (`PublicLayout`, `PublicNav`, `PublicFooter`). Catalog price display goes through `CatalogPriceLine` / `discountPresentation`. Owner forms go through `CatalogPriceFields`. Supplier placeholders are isolated. `PackageDetails.tsx` remains in the tree while the card opens `PackageDetailsModal`.

Files changed for this audit: `docs/FRONTEND_AUDIT.md` only. No application source, env, or database file was edited to start the servers.

## 12. Recommended improvements

| ID | Priority | Item | Where |
|---|---|---|---|
| P0 | Critical | Set PayTabs Profile ID and Server Key on the server. Leave `PAYTABS_BASE_URL` empty so the SAU host is used. Do not put keys in git | Server `.env` only. Status UI: `OwnerOperationsSettingsPage` |
| P1 | Major | Replace homepage `FALLBACKS` with a loading skeleton, then the API packages. If the API returns an empty list, show an empty line, not invented names | `frontend/src/components/landing/PackagesSection.tsx` |
| P1 | Major | Signed-in click-through of owner service/package price forms and one customer order path | Owner and customer pages already built |
| P2 | Important | Name the homepage icon buttons. Review `/services` empty state so it matches the compact section empty row | Homepage service cards, `ServicesPage.tsx` |
| P2 | Important | Either build supplier projects/tasks/calendar/messages or hide those nav items | `SupplierPortalPages.tsx` and supplier nav |
| P2 | Important | Clear the two ESLint errors so `lint` can be a gate | `CatalogHero.tsx`, `FileLibrary.tsx` |
| P3 | Polish | Tablet pass at 768, and a real-device check of the package snap row and footer clearance | Public layout, `useFooterClearance` |

## 13. Prioritized frontend roadmap

### Stage 1 — Fix broken pages and critical UI issues

1. Homepage must not show invented packages while loading or when the API is empty. File: `frontend/src/components/landing/PackagesSection.tsx`.
2. Confirm PayTabs return/result pages still explain a refused checkout when the server key is missing. Files: `frontend/src/pages/PaymentReturnPage.tsx`, `frontend/src/utils/payments.ts`. The credential fix itself is server configuration, not a frontend rewrite.
3. Remove or hide navigation that lands on `SupplierModulePlaceholderPage` until those modules exist.

### Stage 2 — Complete unfinished frontend sections

1. Click through owner package and service forms (current price, price before discount, discount amount) and confirm the public cards update. Files: `OwnerPackagesPage.tsx`, `OwnerServicesPage.tsx`, `CatalogPriceFields.tsx`, `PackageCard.tsx`, `CatalogPriceLine.tsx`.
2. Finish supplier projects, tasks, calendar, and messages, or keep them out of the nav.
3. Walk `/services`, `/services/:slug`, `/packages`, `/portfolio`, and `/build-package` with the local API and note any section that still uses the giant empty card.

### Stage 3 — Design consistency and responsive layouts

1. Measure 768 and 1366 on `/`, `/branding-design`, `/packages`, and `/login`.
2. Keep one catalog grid (1 / 2 / 3 columns, max width 1180) and one price line (current, struck previous, red percent or amount).
3. Upload real package images through the existing owner uploader. Do not invent image files.

### Stage 4 — Interactions and UX

1. Skeleton states for packages, services, and partners instead of either a blank line or fake cards.
2. Accessible names on icon-only controls.
3. Leave the current motion and color system in place.

### Stage 5 — Quality assurance and production readiness

1. Fix the two ESLint errors. Re-run eslint via the Node binary, `tsc -b`, and a Vite production build when the dev server is stopped.
2. Run the existing PayTabs and catalog PHPUnit files after the server key is present. Do not print the key.
3. Production host checks stay in `docs/PRODUCTION_READINESS.md` and `backend/docs/paytabs-go-live.md`. This audit did not touch production.

## Files changed during this audit

- Added: `docs/FRONTEND_AUDIT.md`
- Application source: none
- Environment files: none
- Database: none
