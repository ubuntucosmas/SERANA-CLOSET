# SERANA-CLOSET — FIRST-PRINCIPLES CURRENT STATE REPORT

**As of:** 2026-10-21 · audited at `phase-d2/handover` @ `e78c440`, then updated for the release candidate **`v1.0.0-rc.2`** (V1 freeze and hardening, PR #9).

> **Update after the V1 freeze (same day).** These findings below are now **fixed** in `v1.0.0-rc.2`. Details and tests are in `docs/audit/V1-RELEASE-HARDENING-2026-10-21.md`.
> - **G1 (P1)**, checkout supersede expiring part-paid orders: fixed. The same gap was also fixed in WhatsApp-order expiry, the switch to WhatsApp, Studio cancel and Studio archive. There is now one definition: `CustomOrder::unfunded()`.
> - **G2 (P2)**, reconciliation skipping a second paid prompt on a paid order: fixed. It is booked as a refund owed.
> - **Staff self-delete (P2):** fixed (403 for staff).
> - **Public settings leak (P2):** fixed (an allowlist, `ThemeSetting::PUBLIC_KEYS`).
> - **Bespoke brief links on S3 (P2) and the favicon (P3):** fixed (`PublicUrl::absolute`).
> - **Release state:** PRs #5 to #9 merged to `master` in order. Fresh tag `v1.0.0-rc.2`.
> - **Tests on the RC:** SQLite 536 passed + 17 skipped; MariaDB 553/553; MariaDB with stock-per-size + ledger reads 553/553; frontend 45/45.
>
> Sections 3, 10, 11, 25, 29, 38, 42 and 45–47 describe the state *before* these fixes unless marked. Read them with this block in mind.

**Evidence labels:** **[F]** fact, verified in current code, tests or runtime · **[I]** inference from evidence · **[U]** unknown, can't be proven from the repo · **[D]** decision required from the owner.

**How this was checked:** routes, controllers, services, models, migrations, config and Vue sources were read. The PHP suite was run 3 ways (SQLite; MariaDB; MariaDB with both feature flags on), plus the Node frontend tests and a production build. Read-only commands ran against the local data (`serana:ledger:verify`, `serana:ledger-check`, `serana:stock:reconcile`, `serana:preflight`, `schedule:list`). A scripted Chrome pass covered 22 URLs at 375, 768 and 1440px. Earlier docs were treated as evidence, not truth.

---

## 1. Executive Summary

Serana Closet is a single-shop Nairobi fashion business, run on one Laravel 12 + Inertia/Vue 3 monolith. It now does four jobs:

1. It sells **limited ready-to-wear pieces online**. Customers pay by M-Pesa STK push, or place the order on WhatsApp and staff record the payment.
2. It takes **bespoke (made-to-measure) briefs**. The customer submits a brief and continues on WhatsApp. The Studio quotes, the customer pays a deposit and then the balance (through a signed pay link), and the piece moves through production.
3. It runs the **physical shop till** (POS at `/pos`): PIN-operated cash drawers, counter sales with split tenders, returns, click & collect, bespoke intake, and paper-slip late entry.
4. It gives staff **one back office** ("the Studio", `/studio`): orders, a payment-problems list, refunds, stock per size, staff and roles, till history, an audit log, and settings.

**Drops** (sell-then-make pre-orders) are fully coded but switched off (`DROPS_ENABLED=false`).

The scope has grown a lot since the V1 report (2026-10-15/18). That report described a narrow web shop. The code now has a money **ledger** (lines + payments, derived balances), deposits, refunds, payment-problem handling, **stock per size and location**, roles (owner/manager/cashier), an audit trail, a **till/POS**, and **delivery options with fees**.

**State:**
- **[F]** Everything is complete locally and tested: 535 PHP tests pass on MariaDB, and they pass with the stock-per-size and ledger-reads switches on too. 45 frontend tests pass, and CI is green on every open PR.
- **[F]** None of it is on `master` past Phase C. Four stacked PRs (#5 to #8) are open.
- **[U]** Nothing in the repo proves a production deployment exists.

**This audit found:**
- One P1 money-path gap: checkout can silently expire a part-paid order.
- A few P2 items: a reconciliation blind spot, a public settings leak, and staff accounts that can delete themselves.
- Stale docs and a stale release tag.

---

## 2. First-Principles Product Model

### 2.1 Why it exists

**[I]** A small studio needs to sell limited runs and custom work by the channels its customers already use: M-Pesa, WhatsApp, and walking into the shop. It must do that without overselling a size, without losing track of money, and without losing track of what to make and where it goes.

### 2.2 Users

| User | How identified | Comes here to |
|---|---|---|
| Guest shopper | Browser session (`serana.order_ids`) | Browse, buy, pay, track, submit a bespoke brief |
| Registered customer (`client`) | Breeze login | The same, plus "Your Orders" (`/my-vault`) |
| Cashier | Staff login + till PIN | Sell at the till only (`pos.sell`) |
| Manager | Staff login (+ PIN at the till) | Studio daily work, record money, refunds, stock, till review |
| Owner | Staff login | Everything, plus payment settings, policies, staff, ledger reversal, stock cutover |
| Safaricom | Secret token in the callback URL (+ optional IP allowlist) | Post STK results |
| Scheduler / worker | Server processes | Expire holds, reconcile payments, resolve overdue orders, send mail |

**[F]** Roles and abilities: `app/Support/Role.php` (`MATRIX`), enforced as Gates in `AppServiceProvider::boot` and `can:` route middleware.

### 2.3 Buying modes (derived from code)

| Mode | `kind` / `channel` | Payment | Status |
|---|---|---|---|
| Online shop, M-Pesa | `shop` / `online` | STK push, callback or reconcile | **Active [F]** |
| Online shop, WhatsApp | `shop` / `whatsapp` | Paid outside the system; staff record it | **Active [F]** (local M-Pesa is switched off in settings, so WhatsApp is the only local method) |
| Bespoke | `bespoke` / `online` (or till intake) | Deposit + balance by pay link (STK), or recorded manually | **Active [F]** |
| Counter sale | `shop` / `counter` | Cash, M-Pesa code, M-Pesa prompt, exchange credit, split | **Active [F]** (on PR #6, not master) |
| Click & collect | `shop` with `fulfilment_method=pickup` | Online, collected at the till | **Active [F]** (PR #8) |
| Drop reservation | `drop` | STK | **Disabled [F]** |

### 2.4 Irreducible V1 journeys

| Customer need | System capability | Business outcome |
|---|---|---|
| Buy a garment online | Browse → size → bag → checkout (delivery choice) → STK or WhatsApp → settle → receipt | Paid order, its sizes sold, receipt sent, order in the fulfilment queue |
| Know my order is real and where it is | Reference (`SRN-…`) + phone tracking, signed receipt PDF | Fewer "did you get my money?" messages |
| Get something made for me | Brief + images → WhatsApp → quote → deposit/balance pay link → production stages → photos → handover | A priced, part-paid commission with a production trail |
| Buy in the shop | Till: PIN, bag, tenders, receipt; returns and exchanges | Counted drawer, stock and ledger in step with online |
| Collect an online order | Pickup option at checkout → ready for pickup → till checks the phone and that nothing is owed → collected | The goods go to the right person |
| Not be overcharged or lose money | Ledger, duplicate and late-payment handling, refunds queue, refund receipts | Every shilling is booked once and anything owed back is listed |
| (Owner) run the day | Studio overview: attention items, queues, refunds, till variances | Nothing falls through |

---

## 3. Current Git / Release State

| Item | Value |
|---|---|
| Current branch | `phase-d2/handover` **[F]** |
| Current commit | `e78c44027a81ad4fbb28459ae38a25e56a8a5c61` — "docs(phase-d2): D2 as built, the browser walk-through, audit item 6 closed" **[F]** |
| Working tree | Clean. `git diff --check` is clean. No untracked files **[F]** |
| Pushed? | Yes. `origin/phase-d2/handover` = HEAD **[F]** |
| Ahead of `origin/master` | **33 commits** (Phase D1 → E → frontend align → D2) **[F]** |
| Local `master` | `c1b0dee` (PR #3), **behind** `origin/master` `25f6569` (PR #4) **[F]** |
| Open PRs | #5 D1 stock, #6 E till, #7 frontend align, #8 D2 delivery. Each is stacked on the previous one, CI green, **unmerged** **[F]** (`gh pr list`, `gh run list`) |
| Merged PRs | #1 hardening, #2 Phase A ledger, #3 Phase B deposits, #4 Phase C refunds **[F]** |
| Tags | `v1.0.0-rc.1` → `cc88fa4` (2026-10-18). **Local only** (not on origin). **90 commits behind HEAD**, from before the ledger, deposits, refunds, stock v2, the till and delivery **[F]** |
| Commits in repo | 238, from 2026-04-05 to 2026-10-21 **[F]** |

**Classification: DEVELOPMENT.** HEAD is a feature-branch stack awaiting review and merge. There is no current release candidate: the only rc tag predates most of the current system. Production deployment status is **UNKNOWN**. `deploy/deploy.sh` deploys tags only, and no tag matches current code.

---

## 4. Progress Since Earlier Baseline

Baseline: `docs/PROJECT_UNDERSTANDING.md` (snapshot `cc88fa4`, 2026-10-19) and `docs/audit/V1-RELEASE-REPORT.md`.

| Area | Before | Problem | Change | Current result | Status |
|---|---|---|---|---|---|
| **Payment state** | One `status` column held both payment and production; `is_paid` was flipped directly | Overloaded; bespoke "record payment" bypassed settlement and overwrote `price_quoted` | Phase A: `order_lines` + `order_payments` ledger, split columns (`kind`, `channel`, `order_state`, `fulfilment_status`, `payment_status`, `resolution`), dual-write; `OrderSettlementService` is the single writer (`c119240`…`fa9f3cd`) | Money is derived from lines and ledger (`OrderLedger::derive`); legacy columns are projections, verified equal after every test | **Complete locally**; read cutover (`LEDGER_READS`) not done |
| **Bespoke money** | No deposits; no receipt | Quote overwritten by the amount received | Phase B: deposit % policy, per-order override, STK deposit or balance, signed pay link `/pay/{cipher}`, receipt for every payment (`da9f7fa`…`c22452e`) | Bespoke pays in parts with a receipt each time | **Complete locally** (on master) |
| **Refunds / problems** | "Paid N times" shown, but no refund action; mismatch released holds | Money owed back was invisible and unrecorded | Phase C: duplicate payment becomes refund owed; wrong-amount shop payment settles or becomes a part payment; late payment with stock gone opens a Studio decision; Refunds queue + refund receipt; owner reversal or confirmation; one M-Pesa code booked once (DB unique) (`554b83d`…`b816277`) | One payment-problems list and one refunds queue | **Complete locally** (on master) |
| **Stock** | Product-level `batch_limit − batch_sold − holds` | A shop can't sell "the last M" from a product count | D1: variants (per part and size), locations, `stock_levels`, `stock_movements`; `StockService` is the single writer; Studio → Stock; cutover and rollback switch (`458e599`…`d59dae6`) | Two models live side by side, with a switch (`STOCK_V2`); CI runs both | **Complete locally**; cutover per environment |
| **Physical shop** | None | The shop sold off-system | Phase E: staff accounts, roles, PINs, tills, counter sales, returns, collect, intake, late entry (`378387f`…`97d8c22`) | `/pos` till screen | **Complete locally** (PR #6) |
| **Delivery** | None; WhatsApp text said "delivery included" | Fee and policy undefined | D2: Studio-managed `delivery_options` (pickup, zone + fee + free-over, WhatsApp quote), delivery line in every total, `ready_for_pickup` stage + email (`a137a64`) | Every total, receipt and tracking view shows delivery | **Complete locally** (PR #8) |
| **Concurrency** | Locks tested only on SQLite | Real deadlocks on MariaDB | One global lock order + deadlock retries, MariaDB race tests in CI (`61bcda3`, `2514ead`, `efc6fd5`, `97d8c22`) | 17 race tests | **Complete locally** |
| **Security** | `trustProxies('*')` | IP allowlist spoofable | `TRUSTED_PROXIES`, warning middleware (`1f09def`, `5bfb8b7`) | — | Complete |
| **Studio UX** | One long dashboard | Hard to see what needs doing | Sectioned Studio with grouped navigation, attention list, queues, deep links (`b36be53`, `8b7b4d6`, `1fdd222`, `c3ae8f4`) | 12 sections | Working; practical-review gaps remain |
| **Design** | Tech-noir, mono fonts | — | Token-based light/dark system; owner's layout refresh (`a0d6739`, `b36be53`) | Storefront token-consistent; Studio and till use raw slate | Partial consistency |
| **Drops** | Built in June | No refund policy for a failed target | Paused behind `DROPS_ENABLED` (`f9442b0`) | Hidden publicly | **Disabled intentionally** |
| **SEO** | Client-only meta | — | Server-rendered meta, canonical, OG/Twitter, Product JSON-LD, sitemap, robots (`db19b67`) | — | Complete |
| **Deploy** | None | — | `deploy.sh` (tag-only, backup, migrate, `queue:restart`), crontab, supervisor, `serana:preflight` (`3cf9516`) | — | Tooling complete; never run on a server **[U]** |

---

## 5. Architecture

```
Browser (Vue 3 + Inertia 2 + Pinia bag in localStorage)
  │  Inertia page visits; axios JSON for checkout, STK, status polling, tracking, till actions
  ▼
Laravel 12 monolith (no API layer)
  routes/web.php (125 routes) → middleware (auth, admin=studio.access, can:<ability>,
  pos.operator, throttle per session+IP, mpesa.callback token, signed)
  → Controllers (storefront, Admin/*, Pos/*)
  → Services: OrderSettlementService (money writer), StockService (stock writer),
     TillService (cash writer), CounterSaleService, ReturnService, ShopDeskService, MpesaService,
     OrderLedger (pure), Ledger/Stock backfill + verify + cutover
  → Support: Role, OrderPolicy, ShopPolicy, PaymentSettings, StoreProfile, LegacyOrderMap,
     StudioWorkflow, StockMode, PageSeo, Audit, Actor, Phone, WhatsApp…
  → Eloquent → MariaDB 11.8 (innodb_snapshot_isolation required)
Background: schedule:run every minute; queue:work (database driver) for mail
External: Safaricom Daraja (OAuth, STK push, STK query), SMTP, wa.me links, public disk or S3/R2
```

**[F]** Lock order for all money and stock writes: stock levels by id (v2) or products by id (v1), then holds by id, then the order row, then ledger rows, then the till session. See `OrderSettlementService::lockForMoney`, `StockService::lockForOrder` and `TillService::lock`. Guarded by `StockLockOrderTest` and `TillLockOrderTest` (static lock-order checks) and `ConcurrencyTest`.

---

## 6. Current V1 Scope

**[F]** What the code on HEAD does:
- Online catalogue with per-size availability.
- Bag and checkout, with a delivery or pickup choice priced by the server.
- Payment by M-Pesa STK or WhatsApp.
- Settlement by callback or reconciliation.
- Receipts (email + signed PDF), and tracking by reference + phone.
- Bespoke briefs with WhatsApp handoff, quote, deposit/balance pay link and production photos.
- Studio: orders, queues, payment problems, refunds, products, stock per size, gallery, subscribers, staff, till history, activity log, settings.
- Physical-shop till.
- Roles and audit trail.
- Deploy and preflight tooling.

**[F]** Disabled: Drops.

**[I]** The *documented* V1 (`V1-RELEASE-REPORT.md`) is a subset of this. The code has outgrown the V1 definition. The owner should decide whether V1 now means "the web shop on master" (through Phase C) or "everything on HEAD" (with the till and delivery) **[D]**.

---

## 7. Customer Features (screen inventory)

All routes are in `routes/web.php` and `routes/auth.php`. Pages are in `resources/js/Pages`. Statuses come from the 22-URL browser pass plus code.

| Screen | Route | Purpose / question answered | Primary / secondary action | Data source | Status | Known issue |
|---|---|---|---|---|---|---|
| Home | `/` | "What is Serana, what can I buy?" | Shop / custom order | `HomeController` (featured + showcase, availability-filtered) | 200 at all widths **[F]** | Meta description says "limited Drops" while Drops are off **[F]** (`PageSeo.php:20`) |
| Shop | `/shop?category=` | "What's available?" | Open product / filter, sort | `ShopController@index`, all products, **no pagination** | 200 **[F]** | 120 KB HTML with 72 products; sorting is client-side **[I]** |
| Search | — | — | — | No server search; the Shop filters by category only | **Doesn't exist** | — |
| Categories | via `/shop?category=` + shared `categories` prop | — | — | `Category::all()` on every response | Working | — |
| Product detail | `/shop/{slug}` | "Can I get my size?" | Choose size(s), add to bag / bespoke version | `ShopController@show`, active variants with availability (v2) or fallback sizes | 200 **[F]** | JSON-LD availability uses the old batch model, not per size **[F]** (`PageSeo.php`) |
| Bag drawer | component `CartDrawer.vue` | Quick bag review | Checkout / remove | Pinia `useCartStore` (localStorage `serana_bag`) | Working | — |
| Cart | `/cart` | Full bag review | Checkout | Same Pinia store | 200 **[F]** | Same data as the drawer, so no conflict **[F]** |
| Checkout | `/checkout`; POST `/checkout/process` | "How do I get it, and how do I pay?" | Place order → pay | `CheckoutController` (delivery options), `StoreCheckoutRequest` | 200 **[F]** | — |
| M-Pesa states | `MpesaStkPanel`, `MpesaSuccessPanel`, poll `/payments/mpesa/status/{id}` | "Did my payment work?" | Retry / switch to WhatsApp | `MpesaController@status` (every 5s) | Working (tests) **[F]**; live Daraja **[U]** | The client branches on legacy status strings (`Checkout.vue:149-162`) |
| WhatsApp order | `WhatsAppOrderPanel`, POST `/checkout/{order}/whatsapp` | "How do I pay on WhatsApp?" | Open WhatsApp | `WhatsAppOrderMessage` | Working **[F]** | — |
| Payment confirmation | success panel + signed receipt `/orders/{id}/receipt` | "I paid; where's my proof?" | Download receipt / track | `ReceiptController` (signed, 7 days, paid only) | Working **[F]** | — |
| Pay link | `/pay/{cipher}` (signed) | "What do I owe on my bespoke or part-paid order?" | Pay deposit or balance | `PayController` | Working **[F]**; an invalid signature gets a friendly 403 page | — |
| Track order | `/track`; POST `/track/lookup` | "Where is my order?" | Look up by reference + phone | `publicTrackingPayload()` | 200 **[F]** | — |
| Bespoke | `/custom-order`; POST `/custom-orders` | "Can you make this for me?" | Submit brief → WhatsApp | `CustomOrderController`, `CustomOrderOptions` | 200 **[F]** | 2 text fields without an accessible label (occasion, notes) **[F]**; brief image links hard-code `/storage/` **[F]** |
| Contact | `/contact` | "How do I reach you?" | WhatsApp / email | `StoreProfile` | 200 **[F]** | — |
| About | `/about` | Brand story | Shop | Static | 200 **[F]** | — |
| The Circle | `/the-circle` | Social proof gallery | Custom order | `GalleryImage` | 200 **[F]** | — |
| Drops / Drop detail | `/drops`, `/drops/{slug}` | — | — | `DropController` | **404 by design** (`abort_unless`) **[F]** | — |
| Login / Register / Forgot / Reset | `/login`, `/register`, `/forgot-password`, `/reset-password/{token}` | Account access | — | Breeze | 200 **[F]** | Open registration; email verification not enforced |
| Account settings | `/profile` | Edit profile, password, delete account | — | Breeze `ProfileController` | Working | **Staff can delete their own accounts** (see §25) |
| Your orders | `/my-vault` (auth) | "What have I ordered?" | Track | Closure route, customer-safe projection | Redirects to login when signed out **[F]** | Guest orders never link to an account **[F]** |
| Error pages | `resources/views/errors/{401,403,404,419,429,500,503}` | — | Home | Blade | 404 renders **[F]** | — |
| Till | `/pos` (`pos.sell`) | Staff only | — | `Pos\*` | Login redirect when signed out **[F]** | — |

**Browser pass (375 / 768 / 1440px) [F]:**
- No horizontal overflow on any HTML page.
- One `<h1>` per page.
- No JavaScript page errors.
- Every page logs one console error: a **403 on the favicon** (`/storage/storage/themes/…`). `ThemeSetting::get` already returns `/storage/themes/…`, and `app.blade.php:39-46` passes that through `Storage::disk('public')->url()` again.
- Home and Shop also 403 on two QA product images whose files are missing. That is a local-data problem, not a code problem **[I]**.

---

## 8. Studio Features

`/studio` is guarded by `auth` + `admin` (= `studio.access`: owner, manager). The page is `Admin/StudioDashboard.vue` with section partials. Sections are defined in `resources/js/Support/studioSections.js`.

| Section | What staff use it for | Data it controls | Actions | Protected by | Needs attention | Status |
|---|---|---|---|---|---|---|
| Overview | "What needs attention?" | Read-only | Open items | studio.access | **Every Studio request eager-loads all products, archived products, gallery, leads and drops with all reservations** (`StudioController::index`) | Working |
| Orders | Payment check, stage, quote, delivery, notes, photos | `custom_orders`, lines, ledger | Update stage, set quote, record or confirm payment, set deposit, set delivery, cancel bespoke, resolve stock problem, archive/restore/purge, progress snap | stage update: studio.access; money: `money.record`; deposit: `deposit.override`; reversal: `ledger.reverse` | Queues: review, quotes, payment, fulfilment, production, dispatch, pickup (`StudioWorkflow`) | Working **[F]** |
| Payment problems (on Overview) | Mismatch, code conflicts, stock gone, unexpected bespoke amount, refund owed | — | Resolve (refund / swap / wait) | `money.record`, `ledger.reverse` | Misses part-paid orders expired by checkout (§38) | Working |
| Refunds | "Who is owed money back?" | Refund ledger rows | Mark refunded (method, code, reason) → refund receipt | `refunds.mark` | — | Working **[F]** |
| Products | Catalogue | products, categories, images | CRUD, archive/restore/purge, sizes | studio.access (**managers too**) | No pagination | Working |
| Stock | Count, receive, write off per size; cutover | stock levels and movements | move, sizes, track, cutover/rollback | `stock.manage`; cutover: `stock.cutover` (owner) | — | Working **[F]** |
| Pre-orders (Drops) | Drop campaigns | drops | CRUD | studio.access; **section hidden by flag, but routes still live server-side** | — | Dormant |
| Photo gallery | The Circle | gallery_images | CRUD | studio.access | — | Working |
| Email subscribers | Newsletter list | leads | Search, CSV export | studio.access | No unsubscribe / consent model **[I]** | Working |
| Till sessions | Drawer history, Z reports | till_sessions, cash_movements | View report | `till.review` | Last 30 sessions only (Studio practical review) | Working |
| Staff | People and access | users | Create, role, suspend/reactivate, PIN | `staff.manage` (owner) | — | Working **[F]** |
| Activity history | Audit and movements | audit_logs, stock_movements | Filter, inspect | `audit.review` | — | Working |
| Store settings | Profile, images, custom-order options, payments, policies, delivery, handover | theme_settings, delivery_options, locations | Save per section; M-Pesa connection test | payments: `settings.payments`; policies, delivery, handover: `settings.policies`; others: studio.access | — | Working |

**[F]** There is no separate Bespoke section. Bespoke orders live in Orders, with the `quotes` and `payment` queues.

---

## 9. Normal Purchase Flow (money path)

| Step | Input | Action / backend | Database change | Output | Failure path | Next |
|---|---|---|---|---|---|---|
| 1. Browse | — | `ShopController@index` / `@show`; v2 availability via `StockService::preloadAvailability` / `sizesOnline` | none | Products, sizes with "left" counts | — | Choose a size |
| 2. Bag | Size options | Pinia store caps quantity by availability on the client | localStorage | Bag | Server re-checks everything | Checkout |
| 3. Checkout submit | `items[{id, quantity, options, variant_ids?}]`, contact, `delivery_option_id`, `payment_method`, `request_id` | `StoreCheckoutRequest`; `SubmissionReceipt::recall` (idempotent replay); `supersedeAbandonedOrders`; prices re-read from the DB; v2: `demandsFor` → `shortageFor` | — | — | 422 with a per-size message | Transaction |
| 4. Order + hold | — | `DB::transaction(…, 3)`: v2 `allocate` locks levels (v1: `lockForUpdate` products by id); `CustomOrder::create` (`created` hook backfills lines + ledger); holds (`StockService::hold` or `stock_reservations`); `setDelivery` adds a delivery line and runs `recompute` | `custom_orders` (Pending Payment / `kind=shop`), `order_lines`, `stock_reservations(held, +15 min or +holdHours)` | `{order_id, reference, amount, handover, whatsapp_url?}`; session `serana.order_ids` | Stock race → 422 `DomainException`; anything else → 500 logged `Checkout_Fault` | STK or WhatsApp |
| 5. STK push | `order_id`, `phone`, `pay?` | `MpesaController@initiate`: ownership (session / owner / staff) → M-Pesa paused? → delivery quote pending? → WhatsApp order? → paid? expired? drop paused? mismatch? fractional? → `amountDue` from the ledger → `secureStockForPayment` → `MpesaService::initiateStkPush` | `payment_attempts(pending, amount)`, `mpesa_checkout_id`; commerce order reset to Pending Payment, conditionally | "Check your phone" | 404/409/422; Daraja failure → 400 "not charged" | Poll |
| 6. Poll | — | `GET /payments/mpesa/status/{id}` every 5s (ownership-checked) | none | `is_paid`, status, balance, attempt status, signed receipt URL when paid | — | Success panel |
| 7. Callback | Safaricom JSON | `VerifyMpesaCallback` (token, `hash_equals`, + IP allowlist in production) → match attempt → code already booked on another order? → already paid? → amount check → `OrderSettlementService::recordPayment` | `order_payments(payment)`; recompute money + legacy projection; commit holds (`StockService::commit` writes sale movements; `batch_sold` +=) | 200 Accepted | Wrong amount: shop over → settle + refund owed; shop under → part payment + balance hold + pay link; bespoke → book as received + owner alert; drop → legacy Amount Mismatch | Receipt |
| 8. Reconcile | — | `mpesa:reconcile` every minute: pending attempts aged 2 min to 24 h on unpaid orders, 4 per run, 13s apart, STK Query | Same as 7 via `recordPayment` (no M-Pesa code) | — | Codes 1032/1037/1025/1/2001 → attempt failed; order failed only if no live attempt | Receipt |
| 9. Receipt | — | `PaymentRecorded` (after commit) → queued `SendOrderConfirmation` (3 tries) → `OrderReceiptMail` + PDF; Bcc owner | jobs | Email | Retries; then `failed_jobs` (preflight warns) | — |
| 10. Track | Reference + phone | `/track/lookup` (throttled) → `publicTrackingPayload` | none | Stage, items, delivery, balance, receipt URL | Generic 404 message | — |
| 11. Fulfil | Studio | `StudioController@update`: production stages; pickup → `ready_for_pickup` (email) → collected **only at the till** (`ShopDeskService::collect` checks the phone, the shop, and that nothing is owed); delivery → `shipped` | `fulfilment_status`, audit | Customer sees the stage | Refused: unpaid commerce order; shipping a pickup; ready-for-pickup on a delivery; balance owed | Done |

**Expiry [F]** (`stock:release-expired`, every minute):
- Holds past `expires_at` are released.
- Unpaid M-Pesa orders with no recent prompt are marked Expired, but never when part-paid, a counter sale or a WhatsApp order.
- WhatsApp orders expire after `holdHours`.
- A part-paid shop order whose balance hold lapsed → `cancelForRefund(…,'expired')`, and what was received becomes refund owed.

---

## 10. Payment Architecture

**Methods [F]:**
- `mpesa_stk`: online STK, and STK from the till (`tenderMpesaPrompt`).
- WhatsApp orders, settled by Studio "record payment" with `mpesa_manual`, `cash` or `bank` (`OrderSettlementService::MANUAL_METHODS`).
- Till tenders: cash with change, M-Pesa code typed in (unique), M-Pesa prompt, exchange credit.
- Card: **deferred** (design D3).

**Production-intended:** M-Pesa STK, WhatsApp/manual, and the till tenders.

**Removed experiments:** Stripe and CurrencyConverter. They exist only as `.txt` files in `docs/deferred-experiments/source/` **[F]**.

| Question | Answer |
|---|---|
| What creates a PaymentAttempt? | A successful Daraja STK response in `MpesaController@initiate` or `CounterSaleService::tenderMpesaPrompt` **[F]** |
| What makes payment authoritative? | A row in `order_payments` written by `OrderSettlementService::book`, inside `recordPayment`, under `lockForMoney`. DB unique on `payment_attempt_id` and `(reference_scope, external_reference)` **[F]** |
| Callback processing | Token middleware → attempt lookup → code-conflict check → already-paid branch (books the duplicate as a refund owed) → amount rules → `recordPayment` **[F]** |
| Reconciliation | `ReconcilePendingPayments` (above). Batch 4 per minute, so a backlog of more than about 4 pending attempts a minute queues up **[F]** |
| Callback lost | Reconciliation settles it after 2 minutes. B-01 test **[F]** |
| Retry | New attempt; the order goes back to Pending Payment conditionally; stock is re-secured or the push is refused **[F]** |
| Amount mismatch | Shop: settle and owe the excess, or part-pay with a balance hold. Bespoke: book as received and alert the owner. Drop: legacy `flagAmountMismatch`, holds released **[F]** |
| Duplicate payment | Booked; `balance_due < 0` → Refunds queue, "Paid twice" **[F]** |
| Expired order paid late | Reconcile includes Expired orders. `recordPayment` commits stock only if free; otherwise `resolution=needs_resolution / stock_gone` (refund, swap, or wait for restock) **[F]** |
| What makes the order Paid | `OrderLedger::derive`: net payments ≥ chargeable total, chargeable > 0, no resolution → `is_paid` / `payment_status=paid` **[F]** |
| What commits stock | `CustomOrder::commitReservations` → `StockService::commit` (v2) when the order *becomes* paid **[F]** |
| What sends the receipt | `PaymentRecorded`, one per booked payment, after commit → `SendOrderConfirmation` **[F]**. `MpesaWebhookHandled` is still dispatched but **has no listener** (`php artisan event:list`) **[F]** |
| Studio reads | Legacy projection (`status`, `is_paid`, `price_quoted`) through `ReadsOrderState` while `LEDGER_READS=false`; ledger rows via `ledger_rows` **[F]** |
| Track Order reads | `publicTrackingPayload()` → `isPaidUp()`, `legacyStatus()`, `amount_paid`, `balance_due` **[F]** |

**PAYMENT SYSTEM CURRENT CONFIDENCE: HIGH in code, MEDIUM end to end.**

Why high in code:
- One writer.
- A pure derivation rule.
- DB-level uniqueness.
- Locks in one global order, proven on MariaDB with races.
- A ledger verifier after **every** test.
- 535 passing tests across both databases and both read modes.

Why medium end to end:
1. It has never run against live Daraja or production callbacks **[U]**.
2. Reconciliation has a blind spot for second payments on already-paid orders, and gives up after 24 h without an alert **[F]**.
3. The checkout supersede gap (§38) **[F]**.

---

## 11. Payment Retry / B-01 Status

**Original failure [F]** (`V1-RELEASE-REPORT.md` §B-01):
1. The first prompt fails, so the order becomes Payment Failed.
2. The retry creates a new attempt, but the order stays failed.
3. The reconciler only selected Pending/Expired orders, so a paid retry whose callback was lost was never settled.

**Current protection [F]:**
- `MpesaController.php:148-152` resets a commerce order to Pending Payment with a conditional `paidUp(false)` update.
- `ReconcilePendingPayments::targets()` selects pending attempts on **any** unpaid order, whatever its status.
- Settlement is idempotent per attempt: `alreadyBooked` + the unique `payment_attempt_id`.

**Regression tests (pass on SQLite and MariaDB, both flag modes) [F]** (`tests/Feature/PaymentAttemptTest.php`):
- `test_reconciliation_settles_paid_retry_after_payment_failed`
- `test_reconciliation_resolves_pending_attempts_on_payment_failed_orders`
- `test_retry_restores_pending_state_for_automatic_reconciliation`
- `test_cancelled_retry_does_not_fail_order_with_a_live_attempt`
- `test_failed_retry_keeps_order_failed_and_releases_holds`
- `test_initiate_reacquires_released_holds_or_refuses`

**Verdict: B-01 remains CLOSED [F].**

**Remaining edge cases:**
- **Stale test assertion [F]:** the end-to-end B-01 test asserts `MpesaWebhookHandled` fires once as "the receipt event". The receipt now rides on `PaymentRecorded`, which that test doesn't count. **[I]** The behaviour is still right: the late callback finds the attempt already `paid` and books nothing, and `OrderReceiptEmailTest` counts the receipt mail for the plain callback path. But the test no longer proves what its comment says.
- **Lost code [F]:** when reconciliation settles first, the payment is booked without the M-Pesa code (STK Query returns none). A later callback carrying the code is skipped, so the code is never recorded.
- **Blind spot [F]:** a second prompt paid on an already-paid order whose callback is lost is never queried, because `targets()` filters out `isPaidUp()` orders. The duplicate money is never surfaced.
- **24-hour cut-off [F]:** a pending attempt older than 24 h is never queried again, with no alert.

---

## 12. Inventory / Stock

**Two models, one switch [F]:**
- `StockMode::v2()` reads `config('serana.stock_v2')` (`STOCK_V2`: `setting` | `on` | `off`).
- On `setting` (the default) it follows the Studio setting `theme_settings.stock_v2`, which the cutover turns on.
- **Locally it is ON** (`stock_v2=1`). In production: **[U]**.

| Question | Old model (v1) | Stock per size (v2) |
|---|---|---|
| What "stock" means | `batch_limit − batch_sold − live holds` per product; null limit = unlimited | `on_hand` per variant per location, minus live holds; `track_stock=false` = made to order |
| Tracked by | Product | **Variant = product × part (single/top/bottom) × size**, per location |
| Multi-size sets | Counted once per set | A set consumes one **top** and one **bottom** variant (design D7a); `second_part` stops double counting in `batch_sold` |
| When checked | Pre-check, then re-check under lock | `demandsFor` → `shortageFor` (no lock) → `allocate` (locks levels; first location by priority with all of it; a line is never split) |
| When held | At checkout: M-Pesa 15 min; WhatsApp `holdHours`; counter until void or drawer close; pickup at its shop | Same, per variant + location |
| When released | Expiry job; failure (`markUnpaid`); supersede; cancel; void | Same |
| When committed | On becoming paid (`batch_sold +=`) | On becoming paid: a `sale` movement per hold + `batch_sold +=` (kept, so rollback stays safe) |
| Payment failure | Holds released if no live attempt | Same |
| Retry | `secureStockForPayment` extends or re-takes; refuses if gone | `StockService::secure` |
| Checkout expires | Holds released; order Expired | Same |
| Can two customers oversell? | No: product row locks, tested | No: level row locks, tested (`ConcurrencyTest`, MariaDB) **[F]** |
| Last unit | Lock + re-check inside the transaction | Lock + `availableAt(current: true)` |
| Late money, stock gone | Commit refused; Studio decides (refund / swap / wait); `serana:resolve-overdue` makes it a refund owed after `resolutionDays` | Same, by size |

**V1 compromises [F/I]:**
- A WhatsApp order holds stock for up to `holdHours` (12 to 72) while unpaid.
- The two models are kept in parallel. CI runs every suite in both, so test time doubles.
- `batch_sold` is still maintained as a shadow under v2.
- Production needs an owner stock take before the cutover, or the shop sells from batch numbers **[D]**.

**Contradiction:** `PROJECT_UNDERSTANDING.md` invariant I8 says late money oversells "by design". Since Phase C, `commitReservations` refuses lapsed holds that are no longer free. **Code wins: no oversell [F].**

---

## 13. Product & Sizing

- **Garment types [F]:** a DB enum `garment_type` ∈ {`set`, `hoodie`, `jogger`} (migration `2026_04_21_070954…`, `CatalogController` validation `in:set,hoodie,jogger`).
  - `set` → top + bottom.
  - `jogger` → bottom.
  - Anything else (`hoodie`) → `single` (`ProductVariant::partsFor`).
- **Naming debt [F]:** `hoodie` is used as "any single piece". Local products include caps, coats and jackets typed as `hoodie`.
- **One size [F]:** supported by listing the size `One size` (`ProductVariant::ONE_SIZE`); `resolve()` needs no option when it is the only one. There is no separate scheme.
- **Where sizes are configured [F]:**
  - Product `specifications.ui.sizes` / `specifications.sizes`, else the default S to XXL.
  - Variants are created by `ProductVariant::ensureFor` and managed in Studio → Stock (`sizes`).
  - The frontend fallback `resources/js/Support/sizes.js::fallbackSizes` **duplicates** the default list and the parts rule.
  - Labels are duplicated in `CustomOrder::sizeLabel` and `sizes.js::sizeLabel`, which is documented as "same wording".
- **Backend validation [F]:** `StockService::demandsFor` refuses lines that don't resolve to active variants, and refuses `variant_ids` that disagree with the options.
- **Bespoke sizing [F]:** free measurements: bust, waist, hips, length (`between:30,250`) and height. There is no size scheme. Options (garment, fabric, colour) are Studio-managed (`CustomOrderOptions`); garments are fixed to the three shapes.
- **Drops sizing:** `drops.sizes` JSON (dormant).

---

## 14. Bespoke

| Aspect | Current reality |
|---|---|
| Customer experience | `/custom-order` form → submit → WhatsApp opens with a formatted brief; reference `SRN-…` shown **[F]** |
| Fields | Required: `full_name`, `whatsapp_number`. Optional: location, outfit type, fabric, colour (each validated against Studio options), notes, measurements, deadline (≥ today), occasion, fit, up to 6 images (JPG/PNG/WEBP/PDF, 10 MB each, WebP-optimised), `priority_pass` **[F]** |
| Duplicate protection | `request_id` UUID + payload hash in the session (`SubmissionReceipt`) **[F]** |
| Reference | `cipher` = `SRN-` + 8 hex characters + `-YYYY` (32 random bits; DB unique; not retried on collision) **[F]** |
| WhatsApp handoff | `StoreProfile::whatsappLink`; the brief starts "Hello Serana" (hard-coded) and image links hard-code `/storage/` (**breaks on S3**) **[F]** |
| Owner alert | Queued `NewCustomOrderAlertMail` (only if a notification email is set) **[F]** |
| Studio handling | Orders → `quotes` queue (no total) → set quote (`setQuote`, append-only lines, reason) → `payment` queue → send the pay link (`pay_whatsapp_url`) → deposit / balance → stages `designing → sewing → completed → ready_for_pickup / shipped → collected` **[F]** |
| Rules | Work starts when the deposit is paid; it ships only when fully paid, unless overridden (`fulfilment.override`, owner, with a reason) (`FulfilmentGuardTest`). Cancel by rule N8: before designing everything is owed back; from designing the deposit is retained (`cancelBespoke`) **[F]** |
| Payment | STK deposit or balance via the pay link; manual record (`money.record`); a receipt for every payment **[F]** |
| Quote changes | Raise freely before payment; once paid, only down, with the excess owed back (`QuoteLoweredTest`) **[F]** |
| Tracking | Same reference + **whatsapp_number** match; stages and progress photos **[F]** |
| Email | The form collects **no email**, so receipts go only to the owner unless staff add one **[F]** |
| Phone | `whatsapp_number` is not validated as a Kenyan mobile and not normalised at intake (tracking normalises on compare) **[F]** |
| Deferred | Priority Pass is a flag only, with no fee (the Expedite fee was removed); customer-chosen delivery on the pay page (P7) **[F]** |

**Change from the simple V1:** substantial. Bespoke now has a full money lifecycle (deposit, balance, refunds, cancellation rules) through the ledger. Earlier it was "record payment flips is_paid" **[F]**.

---

## 15. Drops

| Layer | State |
|---|---|
| Config | `services.drops.enabled` = `env('DROPS_ENABLED', false)`; `.env.example`, production example and local `.env` all off **[F]** |
| Public routes | `/drops`, `/drops/{drop}`, `/drops/{drop}/reserve` exist and `abort_unless(enabled, 404)`; verified 404 in the browser **[F]** |
| Navigation / home / sitemap | Nav links hidden by the `drops_enabled` prop; home `featuredDrop` null; sitemap excludes them **[F]** |
| SEO copy | Home meta description still says "limited Drops" **[F]** (contradiction) |
| Studio | "Pre-orders" section hidden by flag (UI). **Admin routes `admin.drops.*` stay live server-side**, and `StudioController::index` still eager-loads every drop with all reservations **[F]** |
| Scheduler | `drops:close-expired` runs every 5 min regardless (greenlight or close live drops) **[F]** |
| Payment | STK refused for drop orders while off (`MpesaController.php:93`); a wrong-amount callback on a drop still uses the legacy `flagAmountMismatch` (holds released) **[F]** |
| Tests | `DropReservationTest` (8) **[F]** |
| Policy | Failed-target refund policy undecided; must match shop wrong-amount rules, pay links, problems list and refunds before re-enabling (design D18) **[D]** |

**PUBLIC: disabled. ADMIN: dormant but reachable by staff. PAYMENT: refused. POLICY: incomplete.**

---

## 16. Account / Authentication

| Topic | Reality |
|---|---|
| Registration | Open (Breeze); creates `role=client` (explicit in `RegisteredUserController`) **[F]** |
| Login | Breeze `LoginRequest`, 5 attempts per email+IP; `/dashboard` sends staff to `/studio` and clients to `/my-vault` **[F]** |
| Password reset | Branded mail (`AppServiceProvider` `ResetPassword::toMailUsing`) **[F]** |
| Email verification | Not enforced (`MustVerifyEmail` commented out); verify routes exist and are dormant **[F]** |
| Profile | Edit name/email/password; **delete account** (Breeze default), available to staff too **[F]** |
| Orders | `/my-vault`: own `user_id` orders only, customer-safe projection **[F]** |
| Guest buying | Yes. Session-bound order ids; tracking by reference + phone **[F]** |
| Guest orders → account | **Never linked** (no claim-by-email) **[F]** |
| Staff | Owner creates staff with a one-time password (forced change via `EnsureStaffAccountIsUsable`); suspend/reactivate; hashed till PIN; 5 wrong PINs lock the pad for 5 minutes **[F]** |
| Admin bootstrap | `php artisan serana:admin <email>` (≥ 12 characters, ends other sessions); demo seeders refuse outside local/testing **[F]** |
| Studio authorization | `admin` middleware = `canUseStudio()` (owner, manager) + per-route `can:` abilities; cashiers get `/pos` only (`RoleAccessTest`) **[F]** |
| Customer data stored | Name, email, phone/WhatsApp, address/city, measurements, inspiration images (public disk, random names), order history **[F]**. Retention policy: none **[D]** |

---

## 17. Orders / Fulfilment

**[F]** One table, `custom_orders`, is every order: shop, bespoke, drop, counter. It carries:
- **Legacy, overloaded columns:** `type` (standard / drop / garment), `status` (payment + production vocabulary), `is_paid`, `price_quoted`, `payment_method`.
- **Split columns (Phase A):**
  - `kind` (shop / bespoke / drop), `garment`, `channel` (online / whatsapp / counter);
  - `order_state` (open / expired / cancelled), `fulfilment_status` (pending … collected);
  - `payment_status` (derived), `resolution` (+ code, reason, since), `payment_failed`;
  - money: `chargeable_total`, `amount_paid`, `balance_due`, `refund_due_since`, `refund_cause`;
  - handover: `fulfilment_method`, `pickup_location_id`, `fulfilment_snapshot`, `delivery_option_id`;
  - till: `till_session_id`, `sold_by`, `slip_number`.
- **Truth:** `order_lines` (what is owed) and `order_payments` (money in and out: payment / refund / reversal).
- **Attempts:** `payment_attempts` (one per STK push).

**Is it one model or several?** One table with presentation abstractions. `ReadsOrderState` answers every question from either the legacy or the split columns (`LEDGER_READS`). `LegacyOrderMap` keeps the two in step on every write. `LedgerVerifier` proves it after every test.

**Compromise:** two vocabularies are live until step A5 drops the legacy columns. Vue still reads the legacy fields (`status`, `is_paid`, `type`, `price_quoted`) from JSON (design §10 lists the files). The client compares legacy status strings.

---

## 18. Data Model

| Model (table) | Responsibility | Important fields | Relationships | Lifecycle | Source of truth for | Known debt |
|---|---|---|---|---|---|---|
| `CustomOrder` (`custom_orders`) | Every order | see §17 | User, Drop, DeliveryOption, Location; has many OrderLine, OrderPayment, PaymentAttempt, StockReservation, OrderProgressSnap | open → expired / cancelled; fulfilment pending → … → shipped / collected | Contact, items snapshot, handover | 610-line model; `$guarded=[]`; dual vocabulary; `deposit_required` appended on every serialisation |
| `OrderLine` | What is owed (item, delivery, discount, adjustment, retained, cancelling) | `line_type`, `amount`, actor, reason, `reverses_line_id` | Order | Append-only | Order total | — |
| `OrderPayment` | Money in and out | `type`, `amount`, `method`, `reference_scope` + `external_reference` (unique), `payment_attempt_id` (unique), actor, `till_session_id` | Order, Attempt | Append-only; reversal rows | Payment state | — |
| `payment_reviews` | Owner confirmation that a flagged booking is genuine | decision, actor, reason | OrderPayment | Append | — | Query builder, no model |
| `PaymentAttempt` | One STK push | `checkout_request_id` (unique), phone, amount, status, result | Order (withTrashed) | pending → paid / failed | Attempt outcome | — |
| `Product` | Sellable piece | price (whole KES), `in_stock`, `batch_limit`, `batch_sold`, `track_stock`, `garment_type`, `specifications`, images, soft delete | Category, Variants | live → archived → purged | Price | `hoodie` = single piece |
| `ProductVariant` | Size × part | `part`, `size`, `sku`, `barcode` (EAN-13, prefix 2), `active` | Product, StockLevels | Never deleted | Size | — |
| `StockLevel` | On hand per variant + location | `on_hand`, `counted_at` | Variant, Location | Moved only by `StockService` | Stock (v2) | — |
| `StockMovement` | History | type (receive, sale, damage, count_correction, return, transfer), qty, actor, reason | Variant, Location, Order | Append-only | Stock history | — |
| `StockReservation` | Hold | status held / committed / released, `expires_at`, variant, location, `second_part` | Order, Product, Variant | held → committed / released | Holds | Shared by both models |
| `Location` | Shop / warehouse | `fulfils_online`, `accepts_pickup`, address | — | — | Pickup point | Single shop (D2) |
| `DeliveryOption` | Pickup / zone / quote | type, fee, `free_over`, `eta_text`, location, active | Location | Deactivated, never deleted | Delivery price | — |
| `TillSession` | Drawer | float, expected / counted / variance, opened / closed by | Location, CashMovements | open → closed | Drawer cash | — |
| `CashMovement` | Cash in and out | type, amount, tendered, approved_by | TillSession, OrderPayment | Append-only | Cash | — |
| `User` | Person | role, `suspended_at`, `pos_pin`, `must_change_password` | Orders | — | Identity | `invitation_code` dead |
| `AuditLog` | Who did what and why | actor, action, subject, before / after, reason, ip | — | Append-only | Accountability | actor_id is nulled if the user is deleted |
| `Drop` | Pre-order campaign | price, target, sizes, `ends_at`, status | Orders | draft → live → greenlit / closed / fulfilled | — | Dormant |
| `Category`, `GalleryImage`, `OrderProgressSnap` | Merchandising, social proof, production photos | — | — | — | — | Copies of the URL resolver |
| `Lead` | Newsletter email | email | — | — | — | `$fillable` lists non-existent `source`, `is_active`; `leads.status` unused |
| `ThemeSetting` | Key/value admin settings (profile, images, payment settings, order and shop policy, custom-order options, `stock_v2`) | key, value | — | Loaded once per request/job (scoped) | Store settings | Mixes public and internal keys (see §25) |

---

## 19. Status Model

| DB value(s) | Domain meaning | Customer label (tracking) | Studio label / queue | Next action |
|---|---|---|---|---|
| `payment_status=unpaid`, legacy `Pending Payment` | Awaiting money | Awaiting payment | Payment queue / "Awaiting payment confirmation" | Pay / confirm |
| `payment_failed=1`, legacy `Payment Failed` | Last prompt failed, nothing collected | Payment not completed | Review queue | Retry / expires |
| `order_state=expired`, legacy `Expired` | Abandoned | Expired | Hidden from queues | None (late money still settles) |
| `order_state=cancelled`, legacy `Cancelled` | Studio cancelled (keeps its stage) | Cancelled | Cancelled | Refund if paid |
| `payment_status=deposit_paid` | Part paid (bespoke deposit **or** a shop short payment) | Paid so far / balance | Payment queue | Pay the balance |
| `payment_status=paid`, `is_paid=1`, legacy `Paid` | Fully paid | Paid | Fulfilment queue | Start / ship / ready |
| `payment_status=refund_due` | More paid than owed | (pay page: refund due) | Refunds | Mark refunded |
| `payment_status=refunded` | Total 0, money returned | — | — | — |
| `resolution=amount_mismatch` | Drop or legacy wrong amount | Under review | Payment problems | Studio decides |
| `resolution=needs_resolution` (+ code `stock_gone` / `reference_conflict`) | Human decision needed | Under review | Payment problems | Refund / swap / reverse |
| `resolution=waiting_restock` | Customer waits for the next batch | Under review | Payment problems | Allocate on receive |
| `fulfilment_status`: pending, designing, sewing, completed, ready_for_pickup, shipped, collected | Production / handover | Stage names | Production, dispatch, pickup queues | Next stage |

**Issues:**
- `status` is overloaded in legacy **[F]**.
- `deposit_paid` also names shop part payments **[F]**.
- `legacyStatus()` folds every resolution into "Amount Mismatch" for screens that still speak legacy **[F]**.
- Client code branches on legacy strings **[F]**.
- Label mapping lives in three places: server `next_action` strings, `resources/js/Support/orderStatus.js`, and `studioWorkflow.js` **[F]**.

**Acceptable for V1:** yes. Both vocabularies are verified equal after every test and by `serana:ledger:verify` (0 differences on 223 local orders). The risk sits in the eventual A5 drop, not in today's behaviour **[I]**.

---

## 20. Source-of-Truth Map

| Concept | Authoritative source | Also computed or duplicated in |
|---|---|---|
| Price | `products.price` (re-read at checkout) | `items_json` snapshot (intended); Pinia bag (display only) |
| Product availability (to sell) | v1: `Product::availableStock()`; v2: `StockService::availableOnline` | `PageSeo` JSON-LD uses `availableStock()` even under v2 **[F]**; client cap in `useCartStore` |
| Stock | v2 `stock_levels` + live holds (v1 batch numbers when off) | `batch_sold` shadow-maintained under v2 |
| Bag | `localStorage['serana_bag']` (Pinia) | Drawer and Cart page share the store |
| Order total | Sum of `order_lines.amount` → `chargeable_total` | `price_quoted` (projection); `$order->total()` reads either |
| Payment state | `order_payments` + lines + resolution → `OrderLedger::derive` | `is_paid`, `status` (projections), `payment_status` (stored derivation) |
| Payment attempt | `payment_attempts` | `mpesa_checkout_id` (latest, legacy), `mpesa_receipt_number` |
| Order status | `order_state` + `fulfilment_status` + `resolution` | Legacy `status` |
| Fulfilment | `fulfilment_status`, `fulfilment_method` | — |
| Product size | `product_variants` (v2) | `specifications.sizes`; `sizes.js` default list |
| Store settings | `theme_settings` (overrides `.env`) | `config()` fallbacks; Inertia `theme_settings` prop |
| Customer details | `custom_orders` row (per order) | No customer master (users are optional) |
| Drop availability | `drops.status` + `ends_at` | — |
| Bespoke status | `fulfilment_status` + ledger | Legacy `status` |
| Media URL | `PublicUrl::resolve` | `ThemeSetting::get`, model accessors (`Category`, `Drop`, `GalleryImage`, `OrderProgressSnap`), `app.blade.php` favicon (double-resolves: favicon bug), `CustomOrderController::buildBrief` (`/storage/` hard-coded) |

---

## 21. Frontend Architecture

- **Stack [F]:** Vue 3.5, Inertia 2, Pinia 3, Vite 7, Tailwind 3 + forms, animejs 4, Ziggy (`@routes` publishes every route name, Studio included).
- **Layouts:** `StorefrontLayout`, `AdminShell` (Studio), `GuestLayout` (auth).
- **Pages:**
  - Storefront: Home, Shop, ProductDetail, Cart, Checkout, Pay, TrackOrder, CustomOrder, Contact, About, Circle, Drops, DropDetail.
  - Auth (6).
  - Profile: Edit + 3 partials, DesignsVault.
  - Studio: `Admin/StudioDashboard` + 18 partials.
  - Till: `Pos/Till`.
- **Shared components (34):** TopNavBar, MobileBottomNav, Footer, CartDrawer, ProductCard, PageHeader, SectionHeader, MpesaStkPanel, MpesaSuccessPanel, WhatsAppOrderPanel, StatusBadge, Notification, Modal, SizingFigure, SiteSeo, …
- **Composables:** useCurrency, useStore, useWhatsApp, useBackToShop, useCinematicScroll.
- **Stores:** `useCartStore` (bag), `usePulseStore` (toasts).
- **Support modules (testable, node-tested):** sizes, stock, orderStatus, orderImages, colourNames, posStock, studioSections, studioWorkflow, trackingMemory.
- **Shared vs page-specific [F]:** storefront chrome and pay panels are shared. Studio and till logic is page-specific and large: `StudioDashboard.vue` 1,004 lines, `Pos/Till.vue` 981, `Checkout.vue` 786, `CustomOrder.vue` 636.
- **Duplication [F]:**
  - Size rules: `sizes.js` vs `ProductVariant`.
  - Status labels: `orderStatus.js` vs server.
  - WhatsApp link building: `useWhatsApp.js` vs `WhatsApp` / `WhatsAppOrderMessage`.
  - Money formatting: `useCurrency` vs server `number_format`.
  - **[I]** Acceptable: each pair is small and some are pinned by node tests.
- **Dead code [F]:** every component, composable, store and support module is referenced (systematic grep). The Tailwind `curator-*` colour tokens are unused.

---

## 22. Backend Architecture

| Layer | Contents | Assessment |
|---|---|---|
| Controllers (storefront 14, Admin 11, Pos 6, Auth 9) | `StudioController` **814 lines**: index payload, update, record/confirm payment, refunds, resolve, reverse, deposit, cancel, archive, snaps. `MpesaController` 404, `CheckoutController` 347 | `StudioController` is overloaded: **POST-V1 REFACTOR**. Its index eager-loads every catalogue, gallery, leads and drop dataset: **SHOULD IMPROVE** (SC-015) |
| Services (14) | `OrderSettlementService` 960 lines (single money writer), `StockService` 699 (single stock writer), `TillService`, `CounterSaleService`, `ReturnService`, `ShopDeskService`, `MpesaService`, `OrderLedger`, backfill / verify / cutover / reconcile | **GOOD ENOUGH FOR V1**: large but cohesive, with a single-writer discipline and documented lock order |
| Models (21) + `Concerns/ReadsOrderState` | `CustomOrder` 610 lines | V1 OK; A5 cleanup later |
| Commands (13) | reconcile, release-expired, resolve-overdue, drops close, preflight, admin, ledger backfill/check/verify, stock cutover/reconcile, media reoptimize, mail test | Good |
| Events / listeners | `PaymentRecorded` → `SendOrderConfirmation` (queued); `MpesaWebhookHandled` → **no listener** (dead event) | **CURRENT RISK (low)**: misleading tests and comments |
| Middleware | Admin, EnsureStaffAccountIsUsable, HandleInertiaRequests, RequirePosOperator, VerifyMpesaCallback, WarnOnUntrustedForwardedHeaders | Good. `HandleInertiaRequests` over-shares settings (§25) |
| Requests | `StoreCheckoutRequest`, `ProfileUpdateRequest`, `LoginRequest`; other validation is inline | V1 OK |
| Policies | None. A fixed role matrix as Gates | V1 OK (decision D11) |
| Support (22) | Rules and settings readers | Good |
| Exception handling | `withExceptions` is empty; no error tracker; logs only | **SHOULD IMPROVE** before public launch |

---

## 23. Design System

- **Tokens [F]:** `resources/css/app.css` (828 lines). `:root` holds the light "Studio White" palette (accent `#07617f`); `.dark` overrides (accent **`#00d2ff`**). Channel tokens feed Tailwind (`primary`, `surface`, `on-surface`, …) so opacity modifiers work. Radius 8/16/24. Easing tokens.
- **Type [F]:** Space Grotesk (headline), Plus Jakarta Sans (body, label, and also "mono"), Material Symbols (subset by `icon_names`).
- **Dark/light [F]:** Tailwind `darkMode: "class"` with a `ThemeToggle`. Studio light mode remaps a few Tailwind shades for contrast.
- **Consistency [I]:**
  - The **storefront** is token-based and consistent across pages.
  - **Studio and till** use raw Tailwind `slate-*`, `bg-white`, `bg-black`. Counts: `text-slate-400` ×167, `bg-slate-950` ×91, and so on, across ~25 Studio/POS files. They are a separate visual language from the storefront tokens.
  - So the Stitch-inspired system is consistent on the storefront only.
- **Discrepancy:** memory note `design-accent-cyan.md` says the accent is `#57d5fc`. The code has `#00d2ff` (dark) and `#07617f` (light) **[F]**. Code wins; the note is stale. Cyan identity is kept in dark mode.
- **Reduced motion [F]:** honoured in `app.css` (two blocks), `HeroSection` and `Footer`.

---

## 24. UX / Flow Clarity

Only real current gaps:

| Flow | Gap |
|---|---|
| Bespoke | The customer gives no email, so they get no receipt email for deposits or the balance. They depend on WhatsApp and the pay link **[F]** |
| Account | A registered customer never sees orders placed as a guest **[F]** |
| Checkout (part-paid) | Starting a new checkout in the same browser can silently expire a part-paid order (§38) **[F]** |
| Studio | A practical review (`STUDIO-PRACTICAL-REVIEW-2026-10-21.md`) lists unsaved-form guards, catalogue pagination, till history limited to 30 with no search or export, and "Today" meaning "this drawer" **[F]** (from the doc; not re-walked) |
| Shop | No search; all products in one page **[F]** |
| Drops copy | The home meta mentions Drops that don't exist **[F]** |

Everything else answers where am I, what is this for, and what happens next. Each Studio section carries `question`, `next` and `when` copy (`studioSections.js`). Checkout shows steps and the delivery line before paying. Tracking shows the next step per handover (`handoverDetails()['next']`).

---

## 25. Security

| Area | Finding | Class |
|---|---|---|
| Studio protection | `admin` (owner, manager) + per-route abilities; cashiers locked to `/pos` (`RoleAccessTest`, `AdminAccessTest`) **[F]** | ACCEPTABLE |
| Object ownership | STK and status: session / owner / staff (`canActOnOrder`), 404 not 403; WhatsApp switch: session / owner **[F]** | ACCEPTABLE |
| Tracking privacy | Reference (32 random bits) + phone; one generic error; payload has no email, address or notes; throttled **[F]** | ACCEPTABLE |
| Receipt URLs | Signed, 7 days, paid only; a bearer link while valid **[F]** | ACCEPTABLE |
| Pay link | Signed, `payLinkDays`; binds the order to the opening session **[F]** | ACCEPTABLE |
| Callback auth | URL token (`hash_equals`, fails closed) + production IP allowlist; `TRUSTED_PROXIES` **[F]** | ACCEPTABLE |
| Rate limits | Per session + per IP for checkout, STK, polling, tracking, bespoke, newsletter; PIN pad lockout **[F]**. The per-session limit is bypassable by dropping cookies; the per-IP ceiling is the real limit **[I]** | ACCEPTABLE |
| CSRF | Default on; only the callback is exempt **[F]** | ACCEPTABLE |
| Mass assignment | `CustomOrder $guarded=[]`, always built from validated arrays; `User` fillable includes `role`, but the profile update uses `ProfileUpdateRequest` (name, email) **[F]** | ACCEPTABLE |
| XSS | One `v-html` with static SVG icons only (`SocialLinks.vue:18`); JSON-LD escaped with `JSON_HEX_*` **[F]** | ACCEPTABLE |
| File uploads | MIME and size checked, WebP re-encoded, random names; no SVG **[F]**. Inspiration images are publicly reachable by URL **[F]** | ACCEPTABLE (retention **[D]**) |
| **Public settings leak** | `HandleInertiaRequests::share` sends **every** `theme_settings` key except two to anonymous visitors. That includes `shop_policy` (cashier discount %, cap, till variance alert, PIN timeouts), `notification_email` (owner inbox) and `stock_v2`. Verified on `/shop` page props **[F]** | **IMPORTANT** |
| **Staff self-delete** | `/profile` DELETE (Breeze) lets any user, the **only owner** included, delete their account. Ledger, audit, attempt and line `actor_id` are `nullOnDelete`, so attribution is lost. A staff member with till history hits FK `restrict` (`opened_by`, `sold_by`), causing a 500 after `Auth::logout()` already ran **[F]** (code trace) | **IMPORTANT** |
| Route-name exposure | `@routes` publishes Studio and POS route names to all visitors **[F]** | ACCEPTABLE (low) |
| Error handling | No error tracker; `APP_DEBUG=false` in the examples **[F]** | IMPORTANT for operations |
| Secrets | `.env` git-ignored; the example passkey is Safaricom's public sandbox key **[F]** | ACCEPTABLE |
| Admin seed | Demo seeders refuse outside local/testing; preflight blocks the default password **[F]**. Local owner `admin@serana.com` still uses the default password (local only) | ACCEPTABLE |
| HTTP security headers | The app sets none (HSTS, CSP, X-Frame-Options); no nginx config in `deploy/` **[U]** | PRODUCTION VERIFICATION |

There are no BLOCKER-class security findings.

---

## 26. Runtime Jobs

| Job | Purpose | Frequency | If it stops | Required for V1 |
|---|---|---|---|---|
| `stock:release-expired` | Release lapsed holds; expire abandoned and part-paid-overdue orders | every minute, no overlap | Unpaid carts and WhatsApp orders lock stock forever; overdue part payments never become refunds | **Yes** |
| `mpesa:reconcile` | Settle prompts whose callback was lost | every minute, background, no overlap | Paid customers stuck in "pending"; no receipt; stock not committed | **Yes** |
| `scheduler-heartbeat` | Proof of life for preflight | every minute | Preflight reports the scheduler down | Yes (ops) |
| `serana:resolve-overdue` | Undecided stock-gone late payments become refund owed | hourly | Decisions linger; nothing breaks | Yes (policy) |
| `drops:close-expired` | Greenlight or close drops | every 5 min | Nothing (Drops off) | No |
| Queue `SendOrderConfirmation` | Receipt per payment (+ PDF) | per payment, 3 tries | No receipts; money still correct | Yes |
| Queue mails | WhatsApp-order-placed, new bespoke alert, mismatch / unexpected / problem alerts, balance due, stock gone, refund receipt, bespoke cancelled, ready for pickup | per event | Customers and owner uninformed | Yes |

**Failure scenarios:**
- **Scheduler stops:** the payment and stock safety nets stop.
- **Worker stops:** emails pile up in `jobs` (preflight warns on backlog).
- **Mail stops:** jobs retry, then fail into `failed_jobs` (preflight warns).
- **Cache locks fail:** `withoutOverlapping` uses the database cache (`cache_locks`). If the cache DB is down, the app is down anyway **[I]**.

**Local evidence [F]:** 40 failed jobs on 2026-10-21 06:38–07:23 UTC, all `Call to undefined method CustomOrder::handoverDetails()`. The method exists now. **[I]** The ddev queue worker was running code from before it was added. Lesson: restart workers after code changes. `deploy.sh` does `queue:restart`.

---

## 27. Configuration

| Key(s) | Class | Notes |
|---|---|---|
| `APP_ENV`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL` (https), `APP_DISPLAY_TIMEZONE` | REQUIRED V1 | Preflight checks |
| `DB_*` (MariaDB 11.6+ with `innodb_snapshot_isolation=ON`) | REQUIRED V1 | Preflight checks the isolation |
| `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true` | REQUIRED V1 | Local `.env` uses `file`, no secure cookie (preflight blocker locally) |
| `CACHE_STORE=database`, `QUEUE_CONNECTION=database` | REQUIRED V1 | — |
| `MAIL_*`, `STORE_NOTIFICATION_EMAIL` | REQUIRED V1 | SPF / DKIM / DMARC on the brand domain |
| `FILESYSTEM_DISK_PUBLIC` (set explicitly), `AWS_*` | REQUIRED / OPTIONAL | The code default is `s3` if unset |
| `MPESA_ENV`, `MPESA_CONSUMER_KEY/SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_CALLBACK_TOKEN`, `MPESA_CALLBACK_URL` | REQUIRED V1 (for STK) | Without them, checkout is WhatsApp-only |
| `MPESA_ALLOWED_IPS`, `TRUSTED_PROXIES` | OPTIONAL V1 | Must be set together correctly behind a CDN |
| `MPESA_RESERVATION_MINUTES` (15), `MPESA_QUERY_SPACING_SECONDS` (13), `MPESA_RECONCILE_BATCH` (4) | OPTIONAL | — |
| `WHATSAPP_NUMBER` | OPTIONAL (the Studio setting overrides) | — |
| `DROPS_ENABLED=false` | DEFERRED | — |
| `LEDGER_READS=false` | OPTIONAL (cutover switch) | Turn on after `serana:ledger:verify` is clean in production |
| `STOCK_V2=setting` | OPTIONAL (switch) | **Absent from `deploy/env.production.example`**; the default `setting` is fine **[F]** |
| `MEDIA_*` | OPTIONAL | — |
| `REDIS_*`, `MEMCACHED_HOST`, `BROADCAST_CONNECTION` | UNUSED | Laravel defaults |
| `LOG_STACK=daily`, `LOG_LEVEL=warning` | REQUIRED (production) | — |
| Studio settings (`theme_settings`): payment switches, hold hours, order policy (deposit %, pay-link days, refund days, resolution days, balance hold hours), shop policy (discount, variance, PIN timeouts, receipt footer), delivery options, pickup location | REQUIRED V1 (owner-set) | Override `.env` |

`config:cache`, `route:cache` and `view:cache` all succeed **[F]** (125 routes).

---

## 28. Dependencies

| Package | Use | Note |
|---|---|---|
| `laravel/framework` ^12, `inertiajs/inertia-laravel` ^2, `tightenco/ziggy` ^2 | Core | — |
| `barryvdh/laravel-dompdf` ^3.1 | Receipt and refund PDFs | Heavy but used **[F]** |
| `intervention/image` ^3.11 | WebP optimisation | Used |
| `league/flysystem-aws-s3-v3` | S3 / R2 | Used only if configured |
| `laravel/tinker` | In **both** `require` and `require-dev` | Duplicate **[F]** |
| dev: breeze, pail, pint, sail, faker, mockery, collision, phpunit 11 | — | `sail` unused (ddev) **[I]**; Pint not enforced in CI **[F]** |
| JS runtime: `pinia` ^3, `animejs` ^4 | Bag/toasts; animation (4 files) | Used **[F]** |
| JS dev: vue, @inertiajs/vue3, vite 7, tailwind 3, @tailwindcss/forms, axios, laravel-vite-plugin, postcss, autoprefixer, `concurrently` | Build | `concurrently` only for `composer dev`, which assumes host PHP (ddev has none) **[I]** |

There are no packages left from the removed experiments (Stripe, i18n, AI) **[F]**. `composer audit` can't run in ddev (no DNS), so dependency vulnerability status is **[U]**.

---

## 29. Testing

**Run on 2026-10-21 [F]:**

| Command | Result |
|---|---|
| `ddev exec php artisan test` (SQLite) | **518 passed, 17 skipped** (MariaDB-only races), 9,064 assertions, 15.7s |
| `DB_CONNECTION=mariadb DB_DATABASE=testing php artisan test --fail-on-skipped` | **535 passed**, 9,144 assertions, 57.7s |
| Same, with `STOCK_V2=on LEDGER_READS=true` | **535 passed**, 9,149 assertions |
| `node --test 'tests/Frontend/*.test.mjs'` | **45 passed** |
| `npm run build` | OK (3.8s) |
| `config:cache` / `route:cache` / `view:cache` | OK |
| CI (GitHub) | 8 PHP runs (SQLite / MariaDB × ledger reads × stock v2) + frontend; green on PRs #5 to #8 |

**Note on the run:** a first attempt at the flags-on MariaDB run overlapped with the `config:cache` check. The cached config pointed the tests at the dev database, and `TestCase::createApplication` refused it ("Refusing to run tests against database db"). The dev data was untouched (223 orders before and after), and the clean re-run passed.

**Safety net:** `TestCase::tearDown` runs `LedgerVerifier` (mapping, money, reads) after **every** passing test.

| Area | Main tests (count) | Gaps |
|---|---|---|
| Payment | PaymentAttempt 12, MpesaPaymentSecurity 9, SettlementLedger 12, DuplicatePayment 4, ShopAmountMismatch 6, UnexpectedAmount 4, ReferenceUniqueness 8, PaymentReversal 5, PaymentReceipt 6, OrderReceiptEmail 4, StkAuthorization 2, AmountMismatchAlert 4 | Reconcile blind spot and 24 h cut-off untested; B-01 test asserts a dead event |
| Stock | StockService 10, StockCutover 10, StockSchema 13, CheckoutBySize 9, SettlementBySize 9, StockReservation 7, LatePaymentStockGone 10, Concurrency 17, StockLockOrder 1 | Good |
| Checkout | CheckoutIntegrity 7, SubmissionRetry 5, PaymentMethods 15, DeliveryOptions 10 | **Supersede of a part-paid order untested** |
| Bespoke | CustomOrder 8, CustomOrderOptions 7, DepositPayment 7, PayLink 7, BespokeCancellation 5, QuoteLowered 5, FulfilmentGuard 8 | Brief links on S3 |
| Drops | DropReservation 8 | Fine for dormant |
| Studio | StudioWorkflowCompletion 11, StudioStock 9, StudioOrderList 5, StudioFulfilment 5, RefundQueue 5, PaymentAttention 2, StudioStats 3, StorefrontStudioSync 7 | No Vue component tests |
| Till / POS | CounterSale 15, Till 12, Returns 9, ShopDesk 8, ShopDay 3, TillLockOrder 2, StaffManagement 8 | — |
| Auth | Breeze suite (≈19), RoleAccess 7, AdminAccess 2, AuditLog 9 | Staff self-delete untested |
| Tracking | OrderTracking 8, ArchivedOrderPayment 11 | — |
| Frontend | cart 12, sizes 5, stock 5, orderStatus 5, studioSections 6, … | No component or e2e tests in CI; browser walkthroughs are manual evidence in `docs/audit/verification/` |
| Security | TrustedProxies 5, UntrustedProxyWarning 6, MpesaPaymentSecurity, ProductionReadiness 10, SeoReadiness 2 | Settings-leak untested |

---

## 30. Browser Verification

Chrome (Playwright), 22 URLs × {375, 768, 1440}, signed out, read-only. Raw output: scratchpad `walk.json`.

- **HTTP [F]:**
  - 200: `/`, shop, product, cart, checkout, custom-order, track, about, contact, the-circle, login, register, forgot-password, sitemap, robots.
  - Redirect to login: my-vault, studio, pos.
  - 404: drops (by design), an invalid pay link, an unknown page.
- **Overflow [F]:** none on HTML pages at any width.
- **Console / network [F]:**
  - Favicon 403 on every page (double `/storage/` prefix, a real bug).
  - 2 missing QA product images (local data).
  - No JS exceptions.
- **Structure [F]:**
  - One `h1` per page.
  - No `<img>` without `alt`.
  - Custom-order has 2 unlabelled text fields plus a hidden file input.
- **Not verified in this pass [U]:** signed-in Studio and till flows, a real checkout and payment, and dark mode. Earlier walkthrough evidence exists in `docs/audit/verification/{phase-b,phase-c,phase-d,phase-d2,phase-e,frontend-align,workflows-2026-10-21}` but was not re-run.

---

## 31. Performance

| Item | Measure | Verdict |
|---|---|---|
| Main JS `app-*.js` | 346 KB raw (Vue, Inertia, Pinia, Ziggy, animejs) | GOOD ENOUGH FOR V1 |
| CSS | 122 KB raw | GOOD ENOUGH |
| Studio / Till chunks | 80 KB / 64 KB, lazy | GOOD |
| `/shop` HTML | 120 KB for 72 products, unpaginated | SHOULD IMPROVE as the catalogue grows |
| Studio index | Eager-loads all products, archived products, categories, gallery (incl. archived), leads, drops + **every reservation** on each Studio visit, including saves | **SHOULD IMPROVE** (SC-015); real risk over months |
| Shared props | All categories + settings on every Inertia response | GOOD ENOUGH |
| `public/images` | 6.7 MB, mostly three 2 MB PNGs | SHOULD IMPROVE (only matters if referenced) |
| Fonts | Google Fonts, 2 families + an icon subset | GOOD |
| Payment status polling | 5s × 30, `payment-status` limit 40/min per session | GOOD |
| DB hot paths | Indexed (cipher, attempts, holds, ledger unique keys, status/created_at) | GOOD |

---

## 32. SEO

**Implemented [F]** (`PageSeo`, `app.blade.php`, `SitemapController`):
- Per-route title and description; canonical; OG and Twitter cards.
- Product JSON-LD (`Product` + `Offer`, KES); home JSON-LD (store profile).
- `index,follow` for public pages and products, `noindex` elsewhere.
- `sitemap.xml` (76 URLs locally; Drops excluded while off).
- `robots.txt` disallows studio, profile, my-vault, checkout, cart, track, pay, payments, orders.

**Gaps:**
- The home description mentions Drops.
- JSON-LD availability uses the v1 batch number under v2.
- `robots.txt` doesn't list `/pos` (it is auth-gated and noindex anyway).
- The client `<Head>` titles ("The Collection | Serana Closet") differ from the server titles ("Shop Collection — Serana Closet"). This is harmless.

---

## 33. Accessibility

**Present [F]:**
- Reduced-motion handling.
- Labelled checkout fields.
- Focus styles in CSS.
- A hero pause control.
- One `h1` per page.
- `alt` on all images.
- A native `<dialog>` / Modal and a CartDrawer with dialog semantics.

**Gaps [F]:**
- Custom-order "occasion" and "notes" fields have ids but no associated label.
- The Studio practical review lists dialog focus-return and keyboard gaps in the till and Studio.

Not audited: contrast ratios, screen-reader walkthroughs, full keyboard paths. **No WCAG compliance is claimed.**

---

## 34. Dead Code

| Item | Class | Evidence |
|---|---|---|
| `App\Events\MpesaWebhookHandled` (dispatched, no listener) | **CONFIRMED DEAD** (behaviourally) | `event:list`; `AppServiceProvider` comment is stale |
| `users.invitation_code` + `User::$fillable` entry | CONFIRMED DEAD | No reader |
| `leads.status`; `Lead::$fillable` `source`, `is_active` | CONFIRMED DEAD | Columns don't exist / unused |
| Tailwind `curator-*` colours | CONFIRMED DEAD | 0 uses in `resources/js` |
| `flagAmountMismatch` | INTENTIONALLY DORMANT (Drops only) | Shop and bespoke use the Phase C rules |
| Drops (controllers, pages, panel, command) | FUTURE / FEATURE FLAG | `DROPS_ENABLED` |
| Email-verification routes and pages | INTENTIONALLY DORMANT | `MustVerifyEmail` off |
| v1 stock code paths | FUTURE / FEATURE FLAG (rollback) | `StockMode` |
| Legacy columns `status`, `type`, `is_paid`, `price_quoted`, `mpesa_checkout_id` | INTENTIONALLY DORMANT soon (A5) | `ReadsOrderState` |
| `docs/deferred-experiments/source/*.txt` | Archived | Not code |
| Unused PHP classes or Vue files | None found | Systematic reference grep (Artisan commands are auto-discovered) |

---

## 35. Redundant Code

| Duplication | Where | Class |
|---|---|---|
| Media URL resolution | `PublicUrl`, `ThemeSetting::get`, 4 model accessors, `app.blade.php` favicon, bespoke brief | **CURRENT BUG RISK** (favicon broken; brief links break on S3) |
| Settings shared to the client | `HandleInertiaRequests` passes the whole `theme_settings` table | **CURRENT BUG RISK** (leak, §25) |
| Availability | `Product::availableStock` (v1) vs `StockService` (v2); `PageSeo` uses v1 | INCONSISTENCY RISK |
| Expire-an-order logic | `ReleaseExpiredReservations` (guards part-paid) vs `CheckoutController::supersedeAbandonedOrders` (doesn't) | **CURRENT BUG RISK** (§38) |
| Size defaults and parts | `ProductVariant` vs `sizes.js` | TECH DEBT (node-tested) |
| Status labels | server `next_action`, `orderStatus.js`, `studioWorkflow.js`, legacy strings in `Checkout.vue` | TECH DEBT |
| WhatsApp links | `WhatsApp`, `WhatsAppOrderMessage`, `StoreProfile::whatsappLink`, `useWhatsApp.js` | HARMLESS |
| Money formatting | `number_format` in PHP, `useCurrency` in JS | HARMLESS |
| Ownership checks | `MpesaController::canActOnOrder`, `CheckoutController::switchToWhatsApp` (no staff branch) | HARMLESS |

---

## 36. Redundant Features

| Question | Answer |
|---|---|
| Bag drawer vs Cart page | Same Pinia store, no conflict. The drawer is quick review, `/cart` the full page **[F]** |
| Multiple order views | Customer: tracking (reference + phone), `/my-vault`, pay page, receipt; each answers a different question. Staff: Studio Orders + the till's order desk (collect / returns) **[F]**. Canonical record: the Studio order |
| Old/new account concepts | `admin` role renamed to `owner` (migration `2026_10_19_000001`); `canUseStudio()` everywhere **[F]** |
| Multiple catalogue screens | Studio Products (catalogue) + Studio Stock (quantities per size) + till catalogue (read-only). Complementary **[F]** |
| Old Drops reachable? | Public: no. Admin routes: yes, while the UI is hidden **[F]** |
| Settings in several places | Store profile in `theme_settings` overriding `.env` (`WHATSAPP_NUMBER`, mail); payment switches in `payment_settings` JSON; policies in `order_policy` / `shop_policy` JSON; delivery in its own table. Canonical: the Studio setting **[F]** |
| Two stock models | Deliberate, until v1 is retired **[F]** |

---

## 37. Unnecessary Complexity

**[I]** Most of the complexity matches real business needs: money owed, sizes, a physical till.

What is complex mainly because of transition, and should be retired once production is cut over:
1. **Dual vocabulary** (legacy + split + `LEDGER_READS`): needs A5.
2. **Dual stock models** (`STOCK_V2`): needs v1 retired after the production cutover.
3. **An 8-way CI matrix**, as a result of 1 and 2.

None should be removed before production has run on the new paths with zero verify differences. Removing them now would remove the rollback.

---

## 38. Implementation Gaps

| # | Gap | Evidence | Impact |
|---|---|---|---|
| G1 | **Checkout supersede expires part-paid shop orders silently** | `CheckoutController::supersedeAbandonedOrders` (l.327-351) selects `paidUp(false)` + legacy `Pending Payment`/`Payment Failed` with no pending attempt, and has **no `amount_paid <= 0` guard**. `ReleaseExpiredReservations` has exactly that guard ("A part-paid order is never silently expired"). The expired order has `balance_due > 0`, so it is in neither `paymentAttention()` nor `refundQueue()`. The part-paid branch of the expiry job needs `order_state=open`, so it never sees it **[F]** (code trace; not reproduced at runtime) | Money received sits on an expired order with nobody told. Needs a shop order paid short (or swapped to a dearer item) and the customer checking out again in the same browser session. **Low likelihood, money path** |
| G2 | Reconciliation ignores pending attempts on already-paid orders | `ReconcilePendingPayments::targets()` `->filter(… ! isPaidUp())` **[F]** | A second paid prompt with a lost callback is never surfaced as a refund owed |
| G3 | No alert when a pending attempt ages past 24 h | Same file, `subDay()` window **[F]** | A silent stuck payment |
| G4 | No error tracking or alerting | `bootstrap/app.php` `withExceptions` empty **[F]** | Faults are only in logs |
| G5 | Guest orders never link to accounts | `/my-vault` closure uses `user_id` **[F]** | Customer confusion |
| G6 | Bespoke intake collects no email and no phone validation | `CustomOrderController@store` **[F]** | No receipt email; tracking depends on the typed number |
| G7 | Shop search and pagination | `ShopController@index` **[F]** | Scale |
| G8 | Studio unsaved-form guards, till history search/export, catalogue pagination | Studio practical review **[F]** (doc) | Staff efficiency |
| G9 | Ledger reads cutover and A5 not done | `LEDGER_READS=false`; design §10 **[F]** | Transitional complexity |
| G10 | Drops parity with Phase C rules | D18 **[F]** | Blocks re-enabling Drops |

---

## 39. Technical Debt

- `StudioController` (814 lines) and its eager index payload.
- `CustomOrder` (610) with appended computed attributes that run queries (`ledger_rows`, `stock_problem`, `delivery_info`) on serialisation.
- Two status vocabularies; two stock models.
- `hoodie` used as "single piece"; the `garment_type` enum needs a migration to add types.
- Several URL resolvers.
- Studio and till styled outside the token system.
- Pint not in CI; `withExceptions` empty.
- Stale comments: `AppServiceProvider` (receipt listener), `OrderStatus` docblock ("splitting … is a recommended future refactor", now done), the `flagAmountMismatch` docblock.
- Stale tests: the B-01 and PaymentMethods tests assert `MpesaWebhookHandled` as the receipt trigger.

---

## 40. Business Decisions

| # | Decision | State |
|---|---|---|
| B1 | **What V1 is**: web shop only (master, through Phase C), or web + till + delivery (HEAD)? | **[D]** |
| B2 | **Drops**: failed-target refund policy; whether and when to re-enable | **[D]** (D18) |
| B3 | **Delivery zones and fees, free-over thresholds, ETAs** | Code is Studio-managed; values **[D]**. Only local QA zones exist |
| B4 | **Returns policy** (customer-facing) | The till supports returns; no published policy page **[D]** |
| B5 | **Privacy / terms / data retention** (measurements, images, addresses) | No pages or retention rules **[D]** |
| B6 | **Hold lengths**: M-Pesa 15 min, WhatsApp 12–72 h, balance hold, resolution days | Settings exist; values **[D]** |
| B7 | **Deposit %**, pay-link lifetime, refund working days | Settings exist; values **[D]** |
| B8 | **Cashier discount limits**, variance alert | Defaults 10% / KES 500 / 200 **[D]** confirm |
| B9 | **Card payments, VAT, KRA eTIMS** | Deferred by the owner (D3, D17, D6) |
| B10 | **Open registration and email verification** | **[D]** |
| B11 | **Garment types** beyond set / hoodie / jogger (dresses, one-size accessories) | **[D]** |
| B12 | **International sales / multi-currency** | Not in scope; KES only **[F]** |
| B13 | **Priority Pass / expedite fee** | Flag only, no fee **[D]** keep or remove |
| B14 | **When to cut production over** to stock per size (requires a stock take) and to ledger reads | **[D]** |

---

## 41. Production Unknowns

All of these are **PRODUCTION VERIFICATION REQUIRED**:
- Whether a production server exists, and which tag or commit it runs.
- The production `.env` values: secure cookies, mail, disk, `TRUSTED_PROXIES`, `MPESA_*` live keys, shortcode and passkey.
- Daraja live app approval; callback reachability from Safaricom over HTTPS; the IP allowlist values.
- Cron actually running `schedule:run` every minute (heartbeat); supervisor workers running and restarted on deploy.
- SMTP deliverability (SPF, DKIM, DMARC); the owner inbox.
- MariaDB version ≥ 11.6 with `innodb_snapshot_isolation=ON`.
- Production data state: `serana:ledger:verify` = 0, `serana:stock:reconcile` passing, stock take done, owner account (not the default password).
- HTTPS, HSTS and other security headers, and upload size limits (nginx `client_max_body_size` ≥ 64m, PHP `post_max_size`).
- Off-server scheduled backups and a restore drill (`deploy.sh` only backs up on deploy, to the same server).
- Legal content (terms, privacy, returns), and real catalogue content (local data is QA data).
- Till hardware: the receipt printer (80mm), barcode scanner, tablet.

---

## 42. Deployment State

| State | Qualifies? | Why |
|---|---|---|
| CODE READY | **Partly** | Feature-complete and tested on HEAD, but four PRs are unmerged and G1 (P1) is open |
| LOCALLY VERIFIED | **Yes** | 535/535 MariaDB in both flag modes, 518+17 SQLite, 45 frontend, build and caches OK, local ledger and stock verify clean, browser walkthroughs in `docs/audit/verification/` |
| DEPLOYMENT READY | **No** | No release tag on current code (the rc tag is local and 90 commits old); PRs not merged; production env unproven; preflight not run on a server |
| PRODUCTION VERIFIED | **No / Unknown** | No evidence |
| PUBLIC LAUNCH READY | **No** | Plus legal pages, delivery values, alerting, backups and a live M-Pesa test (runbook in `CONTROLLED-PRODUCTION-TEST-REPORT.md`) |

---

## 43. Progress Timeline

| Phase | Dates | What happened (evidence) |
|---|---|---|
| 1. Initial store | Apr 5–20 | Laravel + Inertia storefront, Studio, gallery, bespoke brief; storage experiments (`FILESYSTEM_DISK_PUBLIC=s3` ×5, "Production hardening for Cloudinary" ×4); ~40 "ui updates" commits |
| 2. M-Pesa | Apr 24–25 | STK push integration (30 "stk-push" commits) |
| 3. Storefront styling | May 22–24 | Colour scheme, storefront modernisation (`013bc57`) |
| 4. Tracking, receipts, Drops | Jun 19–20 | Order tracking + receipts + payment hardening (`0e0e894`), reconcile pacing, Studio redesign, **Drops** (`727b66f`), sizing figure, money-path indexes |
| 5. Payment attempts | Jul 7 | Per-push `payment_attempts`, system polish (`8dfaea1`) |
| 6. V1 hardening | Oct 15–16 | **B-01 fix** (`e712560`), stock across size lines + idempotent submits, SEO, Studio money guards, **Drops paused** (`f9442b0`), **experiments removed** (`0f54cc9`), design system (`a0d6739`), CI (`7509c02`) |
| 7. Release prep | Oct 18 | WhatsApp ordering (`dc8483a`), preflight and deploy tooling (`3cf9516`), **`v1.0.0-rc.1`** (`cc88fa4`) |
| 8. Batch-1 hardening | Oct 19 (PR #1) | Archived receipts, trusted proxies, global lock order + MariaDB CI, deadlock retry |
| 9. Ledger, deposits, refunds | Oct 19 (PRs #2–#4) | Roles + audit, ledger + status split + dual write, deposits + pay link + per-payment receipts, refunds + payment problems + late-payment rules |
| 10. Stock per size | Oct 20 (PR #5, open) | Variants, locations, levels, movements, `StockService`, cutover, size picker, races |
| 11. Physical shop | Oct 20–21 (PR #6, open) | Staff, PINs, tills, counter sales, returns, collect, intake, paper slips, `/pos` |
| 12. Frontend align + Studio review | Oct 21 (PR #7, open; Codex follow-up in #8) | Owner's layout refresh, till recovery, till history, sold-where, pickup, Studio investigation |
| 13. Delivery | Oct 21 (PR #8, open) | Delivery options and fees, delivery lines, `ready_for_pickup` |

---

## 44. Readiness Scores (0–5)

| Area | Score | Why |
|---|---|---|
| Product definition | 3 | The buying modes are clear in code; V1 boundary, Drops policy and delivery values are undecided |
| Architecture | 4 | Single writers, a pure ledger rule, documented lock order; transitional dual models |
| Frontend | 3.5 | Storefront consistent and clean in the browser; Studio and till large and off-token |
| Backend | 4 | Cohesive services; one overloaded controller |
| Payment integrity | 4 | Strong design and tests; G1–G3; never live |
| Stock integrity | 4.5 | Locked, race-tested, two models verified equal |
| Order lifecycle | 4 | Stages, guards, handover rules; dual vocabulary |
| Bespoke | 3.5 | Full money lifecycle; no email capture, S3 link bug |
| Drops | 1.5 | Built but paused; policy and parity missing |
| Studio | 3.5 | Complete coverage; efficiency and payload gaps |
| Security | 3.5 | Solid core; settings leak, staff self-delete, headers unknown |
| Testing | 4.5 | 535 tests × 8 CI modes, ledger verify after every test; no e2e in CI |
| Performance | 3 | Fine now; Studio index and shop unpaginated |
| SEO | 4 | Server meta, JSON-LD, sitemap; minor copy gaps |
| Accessibility | 2.5 | Basics present; not audited |
| Deployment | 2 | Tooling ready; nothing tagged, merged or proven |
| Operations | 2 | Preflight and heartbeat; no alerting or off-box backups; unproven cron and worker |
| Business / legal | 1.5 | No terms, privacy or returns pages; key values undecided |

---

## 45. P0 / P1 / P2 / P3

| P | Finding | Type | Blocks controlled deployment? | Blocks public launch? |
|---|---|---|---|---|
| P0 | None found | — | — | — |
| ~~P1~~ | G1: checkout supersede silently expires part-paid orders | BUG | **FIXED in rc.2** | — |
| P1 | Live M-Pesa never exercised; scheduler and worker unproven in production | OPERATIONS | Is the controlled test itself | Yes |
| P2 | ~~G2 reconcile blind spot~~ **FIXED in rc.2**; G3 no 24 h alert (accepted; runbook query) | IMPLEMENTATION GAP | No | Yes |
| ~~P2~~ | Public `theme_settings` leak | BUG (security) | **FIXED in rc.2** | — |
| ~~P2~~ | Staff self-delete | BUG (security) | **FIXED in rc.2** | — |
| P2 | No error alerting; no off-box backups | OPERATIONS | Backups: yes | Yes |
| P2 | Studio index eager payload (SC-015) | TECH DEBT | No | No (watch) |
| ~~P2~~ | Bespoke brief links hard-code `/storage/` | BUG | **FIXED in rc.2** | — |
| P2 | Legal pages, delivery values, Drops policy | BUSINESS DECISION | No | Yes (legal, delivery) |
| ~~P3~~ | Favicon double `/storage/` prefix | BUG | **FIXED in rc.2** | — |
| P3 | Stale B-01 test assertion and dead `MpesaWebhookHandled` | TECH DEBT | No | No |
| P3 | M-Pesa code not recorded when reconcile wins | IMPLEMENTATION GAP | No | No |
| P3 | Home meta mentions Drops; JSON-LD availability v1 | BUG (copy/SEO) | No | No |
| P3 | Custom-order unlabelled fields | BUG (a11y) | No | No |
| P3 | Dead columns, tokens, fillables; duplicate tinker; stale README / understanding docs / memory note | TECH DEBT | No | No |
| Post-V1 | A5 legacy column drop, v1 stock retirement, StudioController split, token adoption in Studio/till, search, pagination, guest-order linking, e2e in CI | POST-V1 IMPROVEMENT | No | No |

---

## 46. Controlled Deployment Blockers

1. **Decide the V1 boundary (B1),** then merge the matching PRs into `master` and cut a fresh tag. The current rc tag is stale and local-only.
2. **Fix G1** (one guard, plus a regression test). This is the only money-path code defect found.
3. **Provision production** per `deploy/`: env (secure cookies, `FILESYSTEM_DISK_PUBLIC`, `TRUSTED_PROXIES`, live `MPESA_*`), cron, supervisor, mail DNS, upload limits, MariaDB ≥ 11.6 with snapshot isolation.
4. **`serana:preflight` green on the server**, and `serana:ledger:verify` = 0 on production data.
5. **Off-server backup** taken before the first deploy, with a restore tested once.
6. **Owner settings entered:** payment switches, hold hours, delivery options, pickup address, order/shop policy, notification email.
7. **Stock decision:** sell from batch numbers (v1) at first, or do the stock take and cut over (v2).

Then run the live test runbook (`CONTROLLED-PRODUCTION-TEST-REPORT.md` steps, including B-01 live).

---

## 47. Public Launch Blockers

Everything in §46, plus:
- Live M-Pesa test passed: pay, cancel then retry (B-01), lost callback settled by reconcile, receipt delivered.
- G2 and G3 (reconcile coverage and alert).
- Error alerting (tracker or log alerts on `Checkout_Fault`, mismatches, failed jobs).
- Settings leak fixed.
- Staff self-delete blocked.
- Terms, privacy and returns pages published.
- Delivery fees and zones confirmed.
- The brief S3 link fixed if production uses S3/R2.
- Security headers confirmed at the web server.
- Scheduled backups.

---

## 48. Post-V1 Backlog

- `LEDGER_READS=true` in production, then A5 (drop `status`, `type`, `is_paid`, `price_quoted`; Vue reads adapter fields).
- Retire v1 stock after a stable v2 period.
- Split `StudioController`; make Studio sections lazy or paginated (products, gallery, leads, drops).
- Bring Studio and till onto the design tokens.
- Shop search and pagination; catalogue pagination in the Studio.
- Link guest orders to accounts by verified email; optional email verification.
- Bespoke email capture; phone validation at intake.
- Drops: policy, then parity with Phase C, then re-enable.
- e2e (Playwright) in CI for checkout → pay → track and the till day.
- Pint in CI; error tracker.
- Garment-type model (replace the enum), a one-size scheme.
- Card (PDQ reference), VAT and eTIMS if the owner confirms.
- Offline-capable till (D9).

---

## Final answers

1. **What is Serana Closet today?** An omnichannel system for one Nairobi fashion studio. It is an online shop (M-Pesa STK or WhatsApp), a bespoke commission workflow with deposits and pay links, and a physical-shop till, all on one ledger, one stock-per-size model and one Studio back office. Drops are built but switched off.
2. **How far has it progressed?** From a styled storefront (April) to a hardened, ledger-based commerce and POS system (October). Locally it is near feature-complete for its stated scope and heavily tested (535 PHP tests × 8 CI modes). Four PRs carrying stock per size, the till, frontend alignment and delivery are unmerged, and nothing is proven in production.
3. **What works end-to-end?** Locally, under tests and local walkthroughs:
   - browse → size → bag → checkout with delivery or pickup → STK (faked Daraja) or WhatsApp → callback or reconcile settlement → receipt → tracking → Studio fulfilment → pickup collection at the till;
   - bespoke brief → quote → deposit/balance pay link → production → handover;
   - till sale, return, close and Z report;
   - refunds and payment problems.
4. **What is unfinished or unverified?**
   - The live M-Pesa and production runtime (cron, worker, mail, HTTPS, backups).
   - Merges and a release tag.
   - G1 (part-paid supersede), the reconcile gaps, the settings leak, staff self-delete.
   - Legal content and owner policy values.
   - The ledger-read and stock cutovers in production; Drops policy.
5. **What is the single most important next action?** The owner decides the V1 boundary (web only, or web + till + delivery). Then fix G1 with a regression test, merge to `master`, tag a fresh release candidate, and run the controlled production test runbook on a provisioned server until `serana:preflight` is green and one live M-Pesa payment, retry and lost-callback reconcile all settle with a receipt.
