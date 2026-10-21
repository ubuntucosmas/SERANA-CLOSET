# SERANA-CLOSET — AI / ENGINEER HANDOFF (release candidate `v1.0.0-rc.2`, 2026-10-21)

**PRODUCT**

A single-shop Nairobi fashion studio sells through:
- **Online ready-to-wear**, limited runs, sold per size.
- **Bespoke commissions**: brief → WhatsApp → quote → deposit/balance by a signed pay link → production → handover.
- **An internal shop till** (`/pos`).

Currency is KES only. Drops (pre-orders) are coded but off.

**FROZEN V1 BOUNDARY**
- **Public:** home, shop, categories, product page with per-size choice, bag and cart, delivery or pickup choice, checkout, M-Pesa STK (retry, reconciliation), confirmation, receipt, tracking, account and Your Orders, bespoke request, quote, pay link, deposit and balance, contact and WhatsApp.
- **Studio:** overview and attention, orders (bespoke included), payment problems, refunds, products, stock, gallery, subscribers, staff and roles, activity, settings, delivery options.
- **Till:** included but controlled (staff only). It is not a public-launch dependency.
- **Off:** Drops, Stripe, card/PDQ, multi-currency, AI stylist, translations/RTL, VAT, eTIMS, online discounts, multi-shop, offline till, loyalty, wishlist, reviews.

**CURRENT RELEASE COMMIT**

`master` at tag `v1.0.0-rc.2`. It is PRs #5 (stock per size) → #6 (staff + till) → #7 (frontend align) → #8 (delivery) → #9 (V1 hardening), merged in order with merge commits. The old `v1.0.0-rc.1` tag is stale and must not be used.

**STACK**
- Laravel 12 (PHP 8.2), Inertia 2, Vue 3.5, Pinia 3, Vite 7, Tailwind 3.
- MariaDB ≥ 11.6 with `innodb_snapshot_isolation=ON`.
- Database driver for queue, cache and sessions.
- DomPDF; Intervention Image (WebP); Safaricom Daraja (STK push and query).
- Local dev is ddev only. Run tests with `ddev exec php artisan test`, and never with `--env=testing`.

**ARCHITECTURE**
- A monolith with no API layer.
- Single writers: `OrderSettlementService` (money: ledger `order_payments` + `order_lines`, derived columns, legacy projection), `StockService` (stock levels and movements), `TillService` (cash).
- `OrderLedger::derive` is the pure money rule.
- Global lock order: stock levels (or products) → holds → order → ledger → till.
- `CustomOrder::unfunded()` is the one definition of "no money received", used by every path that expires, cancels, supersedes or archives an order.

**CORE FLOWS**

Shop:
1. `POST /checkout/process` (idempotent `request_id`): prices re-read; delivery priced by the server; holds of 15 min (M-Pesa) or `holdHours` (WhatsApp); the same browser's *unfunded* abandoned orders are superseded.
2. `POST /payments/mpesa/stk` creates a `PaymentAttempt`.
3. The callback or `mpesa:reconcile` calls `recordPayment`; becoming paid commits stock; `PaymentRecorded` queues the receipt.
4. `/track` (reference + phone).
5. Studio stages → `ready_for_pickup` → collected at the till only, or `shipped`.

Bespoke: brief → quote (`setQuote`) → `/pay/{cipher}` → deposit starts work → fully paid before handover.

**PAYMENT (V1 money invariants, each tested; see `docs/audit/V1-RELEASE-HARDENING-2026-10-21.md` §3)**
1. The ledger is authoritative.
2. Money received never silently disappears.
3. A settled order never becomes unpaid.
4. Stock commits once.
5. A duplicate payment becomes a refund owed.
6. An underpayment becomes a balance due, with a held stock window and a pay link, then a refund owed if unpaid.
7. An overpayment becomes a refund owed.
8. A late payment with the stock gone becomes a Studio decision.
9. Callback and reconcile can arrive in any order safely.
10. Staff can't fake a settlement.

B-01 (paid retry after a failure, callback lost) is closed. Reconciliation now also finds a second paid prompt on an already-paid order.

**STOCK MODE: V1 starts on the old batch model** (`STOCK_V2=setting`, Studio switch off). Stock per size is switched on only after:
1. a physical count per size;
2. variants confirmed;
3. counts entered in Studio → Stock;
4. `serana:stock:reconcile` passes;
5. the `serana:stock:cutover` preview is clean;
6. the owner checks availability;
7. `serana:stock:cutover --confirm --by=<owner>`.

Rollback is `--rollback` or the Studio switch; `STOCK_V2=off` is the emergency brake.

**LEDGER READ MODE:** `LEDGER_READS=false` for V1 (a verified projection of the ledger). Switch after a production cycle with `serana:ledger:verify` = 0. Dropping the legacy columns (A5) is post-V1.

**TILL STATUS:** controlled internal. It shares the money, stock, cash and audit writers and the lock order (race-tested on MariaDB). No integrity conflict with the website was found.

**DROPS STATUS:** `DROPS_ENABLED=false`. Public routes 404, hidden in navigation and the sitemap, STK refused. Admin routes are still reachable by staff. Re-enabling needs a failed-target refund policy and parity with the shop's wrong-amount rules.

**AUTH**
- Breeze; open registration; email verification not enforced.
- Guests can buy and track. Guest orders are not linked to accounts.
- Roles: client, cashier (till only), manager, owner (`App\Support\Role::MATRIX`). Till PINs; suspend/reactivate.
- **Staff can't delete their own accounts** (403); customers can.
- `php artisan serana:admin <email>` creates or resets an owner.

**SETTINGS**
- Studio settings (`theme_settings`) override `.env`.
- Visitors receive only `ThemeSetting::PUBLIC_KEYS` (+ `cat_*_bg`). The owner inbox goes to Studio staff only. Policies, switches and stock mode never leave the server.

**KEY MODELS**
- `CustomOrder`: every order. Legacy columns: `status`, `type`, `is_paid`, `price_quoted`. Split columns: `kind`, `channel`, `order_state`, `fulfilment_status`, `payment_status`, `resolution`, `chargeable_total`, `amount_paid`, `balance_due`, plus handover fields.
- `OrderLine`, `OrderPayment`, `PaymentAttempt`.
- `Product`, `ProductVariant`, `StockLevel`, `StockMovement`, `StockReservation`, `Location`, `DeliveryOption`.
- `TillSession`, `CashMovement`, `User`, `AuditLog`, `ThemeSetting`, `Drop` (dormant).

**KEY ROUTES**
- Storefront: `/`, `/shop`, `/shop/{slug}`, `/cart`, `/checkout`, `POST /checkout/process`, `POST /checkout/{order}/whatsapp`.
- Payments: `/pay/{cipher}` (signed), `/track`, `POST /payments/mpesa/stk`, `GET /payments/mpesa/status/{id}`, `POST /payments/mpesa/callback/{token}`, `/orders/{id}/receipt` (signed).
- Bespoke: `/custom-order`.
- Account: `/my-vault`.
- Staff: `/studio/*`, `/pos/*`.

**KEY STATUS RULES**
- Reads go through `Concerns\ReadsOrderState`; `LegacyOrderMap` dual-writes; `LedgerVerifier` runs after every test.
- `fulfilment_status`: pending → designing → sewing → completed → `ready_for_pickup` (pickup only) or `shipped` (delivery only) → `collected` (till only, phone check, nothing owed).

**IMPORTANT CONFIG**
- `APP_ENV=production`, `APP_DEBUG=false`, https `APP_URL`, `SESSION_SECURE_COOKIE=true`.
- `MAIL_*` + `STORE_NOTIFICATION_EMAIL` (SPF/DKIM/DMARC); `FILESYSTEM_DISK_PUBLIC` set explicitly.
- `MPESA_ENV=production` + live keys, shortcode, passkey, `MPESA_CALLBACK_TOKEN`/`URL`.
- `MPESA_ALLOWED_IPS` with `TRUSTED_PROXIES`.
- `DROPS_ENABLED=false`, `LEDGER_READS=false`, `STOCK_V2=setting`.
- **Owner configuration required:** delivery zones, fees and ETAs; the pickup address; payment switches; hold hours; deposit %; refund days; till rules.

**RUNTIME REQUIREMENTS**
- Cron `schedule:run` every minute: `stock:release-expired`, `mpesa:reconcile`, heartbeat, `serana:resolve-overdue` (hourly), `drops:close-expired`.
- Supervisor `queue:work` ×2.
- `deploy/deploy.sh v1.0.0-rc.2`, then `serana:preflight`.

**TEST STATUS (2026-10-21, on the RC code)**
- SQLite: 536 passed, 17 skipped (MariaDB-only races).
- MariaDB: 553/553. MariaDB with `STOCK_V2=on LEDGER_READS=true`: 553/553.
- Frontend: 45/45. Build OK. config/route/view cache OK.
- Local `serana:ledger:verify` 0 differences, `ledger-check` 0 mismatches, `stock:reconcile` passes.

**DEPLOYMENT STATUS:** a release candidate, never deployed. Local preflight fails on environment-only items (local `APP_ENV`, cookies, sandbox M-Pesa, seeded demo password, 40 stale local failed jobs). Production preflight has not been run.

**CURRENT P0:** none. **CURRENT P1:** none in code. Operations P1: the controlled production test hasn't run (live M-Pesa, cron, worker, mail).

**CURRENT P2**
- No error alerting.
- No scheduled off-server backups.
- Pending prompts older than 24 h are not queried and nothing alerts.
- Studio index eager payload.
- Legal pages (terms, privacy, returns) missing.
- Delivery values not set.

**PRODUCTION UNKNOWNS**
- The server and environment; live Daraja approval and callback reachability.
- Cron and workers actually running; mail deliverability; MariaDB version and isolation.
- HTTPS, security headers and upload limits.
- Backups and a restore drill; production data (ledger verify, owner account).
- Real catalogue and stock counts; legal content; till hardware.

**DEFERRED FEATURES**
- Drops; card; VAT; eTIMS; online discounts; offline till; multi-shop.
- Email verification and guest-order linking; shop search and pagination.
- Ledger reads cutover + A5; retiring the old stock model; splitting `StudioController`; Studio/till on design tokens; e2e in CI.

**IMPORTANT BUSINESS DECISIONS**
1. Delivery zones, fees and ETAs.
2. Returns, privacy and terms; data retention.
3. Hold lengths, deposit %, refund days, till discount limits.
4. When to do the stock take and switch to per-size stock.
5. The Drops refund policy.
6. Open registration.
7. Garment types beyond set / single / jogger.
8. Priority Pass.

**NEXT RECOMMENDED ACTION**
1. Provision the server per `deploy/`.
2. Deploy `v1.0.0-rc.2`.
3. Get `serana:preflight` green.
4. Run `docs/audit/CONTROLLED-PRODUCTION-TEST-REPORT.md` live (pay; cancel → retry; lost callback → reconcile; receipt), with the owner's delivery and policy settings entered and the old stock model.
