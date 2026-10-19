# SERANA-CLOSET V1 RELEASE REPORT

**Date:** 2026-10-15 · **Branch:** `master` @ `8dfaea1` plus uncommitted working tree · **Scope:** the V1 launch simplification gate. Nothing was committed, pushed or deployed. No production systems were touched.

> **Read first:** production deploys from git, and the tree holds about 80 uncommitted files (this pass plus the earlier screen-clarity pass). None of the fixes below reach production until they are committed. Local `master` is also 16 commits ahead of `origin/master`.

---

## Must-Fix Issues Completed

| Issue | Status | Where |
|---|---|---|
| **Every full page returned HTTP 500.** `app.blade.php` mixed an inline `@php(...)` with a later `@php … @endphp` block, which Blade compiles into a parse error. 11 tests were failing. | ✅ Fixed (block form + comment) | `resources/views/app.blade.php` |
| **B-01: a paid retry after `Payment Failed` was never settled** | ✅ Fixed (see below) | `MpesaController`, `ReconcilePendingPayments` |
| A new STK prompt could be sent on an `Amount Mismatch` order, so the customer would be charged again before the studio reviewed the first payment | ✅ Blocked (409) | `MpesaController::initiate` |
| Unpaid Drop reservations were payable while the refund policy is undecided | ✅ Paused behind `DROPS_ENABLED` (default off) | see *Drops Launch Decision* |
| `ShopSeeder` / `GallerySeeder` could insert demo catalogue / gallery data into any environment | ✅ Same local/testing guard as the other seeders | `database/seeders/*` |
| Fabricated product specs ("Thermal Regulation Coating", "Water-Resistant Membrane") were attached to every product payload | ✅ Removed (`Product::xray_specs`; no consumer) | `app/Models/Product.php` |
| Developer wording in customer toasts (`SYSTEM_LOG // 001`) | ✅ "Done" / "Something went wrong", 11px, labelled dismiss button | `Notification.vue` |
| Home hero promised Drops while Drops are paused | ✅ Drop phrase shown only when enabled | `HeroSection.vue` |

**Already fixed in the working tree by the earlier screen-clarity pass, and re-verified in this one:**
- Bag −/+ crash, remove-all-sizes, and two-size checkout rejection (variant line keys).
- Admin seeders refuse to run outside local/testing.
- Studio can no longer toggle `is_paid`, edit commerce totals, or set payment statuses by hand.
- Out-of-stock enforcement.
- `/cart` page exists.
- Footer `#` links removed.
- Expedite fee removed.
- Stripe / currency / AI / vue-i18n prototypes removed.
- Labelled checkout fields.
- Custom error pages.

## B-01 Result

**Root cause (traced):**
1. A failed or cancelled STK callback marks the order `Payment Failed` (when no other attempt is live).
2. The customer retries. `MpesaController::initiate` creates a new pending `PaymentAttempt`, but the order stays `Payment Failed`.
3. `mpesa:reconcile` only selected attempts whose **order** was `Pending Payment` or `Expired`.
4. Result: if the retry was paid and Safaricom's callback was lost, nothing ever resolved it. The money was taken, the order showed as failed, stock was not committed and no receipt went out.

**Fix (smallest correct layers; no architecture change):**
1. **Retry reset.** After a successful STK push, the order is set back to `Pending Payment` with a *conditional* update (`WHERE is_paid = false`). If an earlier prompt's callback settles the order at the same moment, the reset can't overwrite `Paid`. (The earlier pass had made this reset unconditional; that race is now closed.)
2. **Reconciler.** It now resolves pending attempts on **any unpaid order**, whatever its status. This is defence in depth against any other path that leaves an unpaid order with a live push. The legacy (pre-attempt) branch is unchanged.
3. **Mismatch guard.** `Amount Mismatch` orders refuse new prompts until reviewed.

**Unchanged and still proven by tests:**
- Idempotent conditional paid-flip.
- Amount verification.
- Per-attempt ledger.
- Paying an earlier prompt still settles.
- A cancelled retry doesn't fail an order with a live attempt.
- Expired-order reconciliation.
- Expiry spares in-flight payments.

**Regression tests added** (`tests/Feature/PaymentAttemptTest.php`):

| Test | Proves |
|---|---|
| `test_reconciliation_settles_paid_retry_after_payment_failed` | End to end: fail → retry → callback lost → reconcile settles; **2nd reconcile run + late callback change nothing**; `batch_sold` committed once; 1 order; **receipt event dispatched exactly once**; Track Order payload `is_paid = true` |
| `test_reconciliation_resolves_pending_attempts_on_payment_failed_orders` | The reconciler no longer filters by order status |
| `test_failed_retry_keeps_order_failed_and_releases_holds` | A failed retry re-acquires then releases the hold; no stock sold |
| `test_initiate_rejected_for_amount_mismatch_orders` | No new prompt or attempt on a mismatch order; nothing sent to Daraja |
| *(existing, from the earlier pass)* `test_retry_restores_pending_state_for_automatic_reconciliation` | The status reset |

**Studio parity:** Studio payment badges and the attention queue read `is_paid` (`Support/orderStatus.js`, `OrdersPanel.vue`, `StudioController@index`), the same field settlement writes. So Studio, Track Order and the customer's confirmation all show the same authoritative state.

**Remaining edge (documented, not a defect):** the reconciler stops querying a push after 24 hours. Prompts expire on the phone within minutes, so a running scheduler resolves every paid push long before then. That's why the scheduler is a V1 requirement.

## Production Scheduler Requirement

**REQUIRED FOR V1.**

| Job | Schedule | Needed because |
|---|---|---|
| `mpesa:reconcile` | every minute | Only recovery path when Safaricom's callback is lost (B-01 depends on it) |
| `stock:release-expired` | every minute | Marks abandoned checkouts `Expired` and releases holds. (Availability already ignores expired holds through `expires_at`, so stock doesn't stay locked without it, but order states would.) |
| `drops:close-expired` | every 5 min | Only matters when Drops are enabled |

**Minimum production process: one cron entry.** Nothing else (no Redis, Horizon or supervisor) is needed for the scheduler:

```
* * * * * cd /path/to/serana && php artisan schedule:run >> /dev/null 2>&1
```

`mpesa:reconcile` uses `runInBackground()` + `withoutOverlapping()`, which need a working cache lock. The `database` cache store (current config) is sufficient.

## Queue Worker Requirement

**REQUIRED FOR V1 (receipts only).** The only queued work is `SendOrderConfirmation` (the paid-order receipt email to the customer + Bcc owner). With `QUEUE_CONNECTION=database` and no worker, **payment still settles and shows correctly**, but receipt emails never send.

Pick the simplest option the host supports:
- **VPS / systemd / supervisor:** `php artisan queue:work --tries=3 --max-time=3600` kept alive by the process manager.
- **Shared hosting, cron only:** a second cron line, `* * * * * cd /path && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1`.

Do **not** switch to `QUEUE_CONNECTION=sync`: the email would then send inside Safaricom's callback request, and a slow SMTP call could make Safaricom time out and retry.

## Seeder Risk

| Seeder | Creates | Classification | Guard |
|---|---|---|---|
| `DatabaseSeeder` | 2 users with known dev passwords (one admin), calls Shop + Gallery seeders | **DANGEROUS IN PRODUCTION** | Refuses outside `local` / `testing` ✅ |
| `AdminSeeder` | 2 admin users with a well-known dev password | **DANGEROUS IN PRODUCTION** | Refuses outside `local` / `testing` ✅ |
| `ShopSeeder` | 10 demo categories + 7 demo products | **DEVELOPMENT ONLY** | Refuses outside `local` / `testing` ✅ (added this pass) |
| `GallerySeeder` | Demo showcase images / testimonials | **DEVELOPMENT ONLY** | Refuses outside `local` / `testing` ✅ (added this pass) |

**No seeder is safe or required for production.** Deploy with `php artisan migrate --force` only. Create the real admin account deliberately (e.g. `php artisan tinker`), never from a seeder.

## M-Pesa Production Requirements

Environment (values never committed; set on the server):

| Key | Requirement |
|---|---|
| `MPESA_ENV` | `production` |
| `MPESA_CONSUMER_KEY` / `MPESA_CONSUMER_SECRET` | Production Daraja app credentials |
| `MPESA_SHORTCODE` / `MPESA_PASSKEY` | Production till / paybill + Lipa Na M-Pesa passkey. **`.env.example` holds the public sandbox values (174379); they must be replaced** |
| `MPESA_CALLBACK_TOKEN` | Random secret (`php -r "echo bin2hex(random_bytes(24));"`). **Empty = every callback rejected** (fail-closed) |
| `MPESA_CALLBACK_URL` | Public **HTTPS** URL `…/payments/mpesa/callback/{token}`, reachable by Safaricom |
| `MPESA_ALLOWED_IPS` | Optional Safaricom callback IP allowlist (enforced only when `MPESA_ENV=production`) |
| `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` | Standard hardening |
| `MAIL_*`, `STORE_NOTIFICATION_EMAIL` | Receipts + amount-mismatch alerts |
| `WHATSAPP_NUMBER` (or the Studio setting) | Bespoke hand-off and support links |

**Deploy steps:** `php artisan config:cache && php artisan route:cache && php artisan view:cache`. All three were verified to succeed. No `env()` calls remain outside `config/`.

## Admin Security Actions (operations, before public traffic)

1. On the production database, list privileged accounts (`role = 'admin'`) and confirm every one is known.
2. If `admin@serana.closet` or `admin@serana.com` exist in production, **delete them or rotate their passwords**. Those addresses had well-known development passwords. (They are confirmed usable on the dev database, so if production was ever seeded, they're live.)
3. Create the owner's admin account with a strong unique password; remove any obsolete privileged users.
4. Credentials are deliberately not printed anywhere in this report.

No password-management feature was built.

## V1 Features Included

**Customer:**
- Home, Shop, category filter and search, product (gallery, size selection, availability).
- Bag drawer and `/cart`, checkout, M-Pesa STK with payment status.
- Confirmation with reference and receipt, Track Order (reference + phone).
- Contact (WhatsApp), Bespoke request.
- Sign in / register / password reset, Account settings, Your Orders, About, The Circle.

**Studio:**
- Attention queue (pending, failed, mismatch, paid-awaiting-fulfilment, new briefs).
- Orders with settlement-safe edits and progress photos.
- Products with garment type, stock and availability; categories.
- Showcase, subscribers (CSV), basic settings.
- Drops management: admin-only; customers can't reach Drops while the switch is off.

## Features Removed/Deferred

| Item | V1 action |
|---|---|
| Stripe, international cards, multi-currency, `CurrencyConverter` | Removed from the tree (no runtime dependency); **POST-V1** if demand is confirmed |
| AI style concierge job | Removed; **POST-V1** |
| vue-i18n / translations / Arabic RTL | Removed; **POST-V1** |
| Expedite / Priority surcharge | Removed from the customer UI; the backend ignores it; **business confirmation required** to revive |
| Online bespoke deposits / milestones | **POST-V1** (WhatsApp quote for V1) |
| Paid Drops | Paused (switch); **business decision required** |
| Unused `BuyingModes.vue`, `@tailwindcss/vite`, 5.9MB `heritage_loop.mp4`, fabricated `xray_specs` | Removed |

## Drops Launch Decision

**Paid Drops are OFF for V1** (Option A, least risky). The failed-target / refund policy is undecided, and no policy was written into code or copy.

- `config('services.drops.enabled')` ← `DROPS_ENABLED` (default `false`).
- **When off:**
  - `/drops`, `/drops/{slug}` and reserve return 404.
  - STK initiation for any unpaid drop order returns 409.
  - Header, mobile menu and bottom-nav links are hidden (bottom nav shows **Track** instead).
  - The Home launchpad and hero Drop wording are hidden.
  - Sitemap omits Drops.
- **Preserved:**
  - Existing paid reservations remain trackable via Track Order / Your Orders.
  - Studio can still create and manage Drops.
  - `drops:close-expired` keeps running.
- **To launch Drops:** management supplies the failed-target policy → publish the customer copy → set `DROPS_ENABLED=true` → `config:cache`. No code change is needed for the switch itself.
- Covered by `test_drops_are_hidden_and_unpayable_while_disabled`. The existing Drop tests run with the switch on.

## Bespoke V1 Flow

Customer submits the brief (name + WhatsApp required, everything else optional) → reference (SRN-…) created and shown → WhatsApp opens with the prefilled brief (the saved confirmation survives popup blockers) → studio confirms design, quote and timeline on WhatsApp → studio updates stage and progress photos → customer follows via Track Order.

The page states that **no payment is taken on submission** (verified in Chrome). No surcharge is advertised.

## Supported Size Schemes

Only what the current catalogue uses (all live products are `set`). Studio selects one per product:

| Scheme | Customer selects | Use for |
|---|---|---|
| `set` | Top size **and** bottom size (S–XXL) | Hoodie & jogger sets |
| `hoodie` | Top size (S–XXL) | Single tops |
| `jogger` | Bottom size (S–XXL) | Single bottoms |
| Bespoke | Optional measurements + WhatsApp fitting | Made-to-measure |

Stock caps are per product, combined across sizes. **Confirm the launch catalogue fits these.** One-size accessories or dresses would need a small, targeted addition (a `single` / `one_size` scheme), not a sizing engine. That's only if such products launch.

## Legal/Policy Content Still Required

**BUSINESS CONTENT REQUIRED — not complete.** No privacy policy, terms, shipping/delivery or returns pages exist, and none were invented. The footer no longer shows dead links; it points to Contact & Delivery Help and About. When approved copy is supplied, publish each as a simple static page. Collecting names, phones, addresses and payments in Kenya makes a privacy notice a legal expectation (Data Protection Act 2019), so treat it as required before **public** launch, not before a controlled test.

## Automated Tests

| Suite | Result |
|---|---|
| `ddev exec php artisan test` | **109 passed, 371 assertions**, 0 failed |
| `node tests/Frontend/cart.test.mjs` | **6 passed**, 0 failed |
| `npm run build` | Passes |
| `config:cache` / `route:cache` / `view:cache` | All succeed |
| Pint / static analysis / lint | Not configured (unchanged; post-V1) |

## Browser Tests

Real Chrome (Playwright driving the system Chrome, from the session scratchpad; not added to the repo). Checkout and payment endpoints were **mocked at the network layer**: no order written, no STK sent. **57/57 passed:**
- 11 V1 pages × 375 / 768 / 1440: HTTP 200, one `<h1>`, no horizontal overflow, no JS errors, no `#` links, no visible Drops links.
- `/drops` returns 404 at all widths.
- Flow:
  - Add to Bag blocked until sizes are chosen.
  - Two sizes become two lines; −/+ works; Remove deletes one size.
  - Checkout sends one order request, then one STK, then pending, then **Payment confirmed** with reference and Track action.
  - Bag cleared; no JS errors.
- Bespoke: no Expedite; "No payment is taken here" shown.
- Studio Home / Orders / Products / Drops / Settings at 390 and 1440: 200, no overflow, no JS errors.

## Manual Production Tests Required

1. **Live STK, low amount:** buy a test product end to end. The callback arrives, the order is Paid, stock is committed, the receipt email is received, Track Order and Studio both show Paid.
2. **Cancel then retry:** cancel the first prompt on the phone, retry, pay. The order settles (B-01 path, live).
3. **Scheduler alive:** `php artisan schedule:list` on the server; confirm `mpesa:reconcile` / `stock:release-expired` run (log lines or an abandoned checkout turning `Expired` after 15 minutes).
4. **Queue alive:** the receipt email from test 1 arrives (proves the worker runs).
5. **Callback security:** a POST to `/payments/mpesa/callback/wrong-token` returns 403.
6. **Admin:** sign in with the new owner account; confirm the seeded dev accounts don't work.
7. **Drops hidden:** `/drops` returns 404 in production.
8. **HTTPS + cookies:** the site is served over HTTPS; session cookie is `Secure`.

## Genuine Remaining Launch Blockers

These are deployment and operations preconditions, not code defects:
1. **Commit the working tree** (coherent commits) and deploy from git. Uncommitted fixes don't reach production.
2. **Cron** `schedule:run` every minute + a **queue worker** on the production host.
3. **Production M-Pesa configuration** (keys, passkey, shortcode, callback token, HTTPS callback URL).
4. **Admin credential review** on the production database (rotate or remove seeded dev accounts if present).
5. Production `.env` hardening (`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, mail).

*Before public launch (not a controlled test):* approved privacy / terms / delivery / returns copy.

## Post-V1 Backlog

- **Drops:** failed-target policy → refund queue + customer notices, then enable.
- Online bespoke deposit / balance via M-Pesa; Expedite (if confirmed).
- Separate `payment_status` / `fulfilment_status` columns; normalised order `type` (the presentation layer already separates them).
- Size schemes beyond the current catalogue (one-size, single garment labels) when such products launch.
- Duplicate-payment and refund-owed surfacing in Studio; protect payment evidence from order force-delete.
- Shipped / dispatch notifications to customers.
- Performance: convert the 2.2MB hero PNGs to WebP, subset the 1.1MB icon font, lazy-load below the fold.
- Settings caching (shared-props N+1), Studio pagination.
- Pint config + one formatting commit, CI (tests + build), Larastan.
- Linking guest orders to accounts; per-product social preview polish.
- Stripe / multi-currency / i18n / AI: only with confirmed demand.

---

**READY FOR CONTROLLED PRODUCTION TEST**, once the five deployment preconditions above are in place.
