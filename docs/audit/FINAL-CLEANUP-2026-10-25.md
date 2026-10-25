# SERANA-CLOSET — FINAL CLEANUP & PRODUCT SUCCESS REPORT

2026-10-25 · branch `fix/first-principles-pass` · base `869491f` · everything uncommitted (owner commits at the end).

## Executive Verdict

**READY AFTER SMALL FIXES.** The code can run a real shop. The money, stock and payment-recovery machinery was already strong; what was weak was speed under realistic data. At ~500 products and ~5,000 orders, every Studio action took about 2 seconds, the Stock screen 6.7 seconds (16,831 database queries) and the Shop 2 seconds. Those are fixed and covered by tests that fail if queries start growing with data again.

What stands between this and real customers is not code: commit and merge the pending work, then run the live checklist on the real server (deploy, low-value live M-Pesa payments, off-server backup with a restore drill, uptime monitor, real product photos). After that, stop building and learn from real sales.

## Current Product Purpose

A Nairobi fashion studio selling (1) ready-to-wear pieces online by size, paid by M-Pesa or arranged on WhatsApp, delivered or collected; (2) bespoke pieces from a customer brief, quoted and paid by link; (3) at the shop counter through the Sales Desk tablet. Studio is the back office for orders, money problems, refunds, catalogue, stock, staff and settings.

## Current Architecture

| Layer | What is there (measured on this branch) |
|---|---|
| Routes | ~134 named routes: storefront (shop, checkout, pay link, track, bespoke), M-Pesa (STK, status, token-guarded callback), Studio (`/studio`, `auth`+`admin`+per-ability gates), Sales Desk (`/pos`, `pos.sell` + PIN operator) |
| Controllers | 41. Large: `StudioController` (868 lines), `MpesaController` (441), `CheckoutController` (358) |
| Domain services | `OrderSettlementService` (only money writer, 960 lines), `StockService` (only stock writer, ~720), `TillService` (cash drawer), `CounterSaleService`, `ShopDeskService`, `ReturnService`, `PaymentReviewService`, `StockCutover`/`StockReconciler`, `LedgerVerifier`/`LedgerBackfill`, `MpesaService`, `ImageOptimizer` |
| Models | 23; `CustomOrder` (one table for shop, bespoke, drop and counter orders) with the `ReadsOrderState` adapter (legacy columns or ledger, `LEDGER_READS`) |
| Support | 27 small rule holders: `Role` (ability matrix), `OrderPolicy`, `ShopPolicy`, `PaymentSettings`, `StoreProfile`, `SystemHealth`, `OperatorAlerts`, `JobRuns`, `StudioWorkflow`, `StudioStock`… |
| Scheduler | every minute: `stock:release-expired`, `mpesa:reconcile`, heartbeat; 5 min: operator alerts, drops; hourly: `serana:resolve-overdue`. Every job records success/failure (`JobRuns`) |
| Frontend | 23 Inertia pages, 36 components, 2 Pinia stores (bag, toasts), 5 composables, 10 support modules. Studio is one page with 19 panels; Sales Desk is one page |
| Tests | 637 PHP tests (+20 MariaDB-only race tests), 49 frontend unit tests; CI runs 8 combinations (SQLite/MariaDB × ledger reads × stock per size) |

Every frontend module is reachable from a page, and every PHP class is referenced (checked with an import-graph script and a reference count, not by eye).

## User Jobs

| User | Goals | High-frequency tasks | Main failure risks | Unneeded complexity found |
|---|---|---|---|---|
| First-time customer | Find a piece, trust the price, pay, know when it arrives | Browse, pick sizes, checkout, M-Pesa | Unclear size labels; M-Pesa uncertainty; slow phone pages | "Hoodie size / Jogger size" on every two-piece (fixed) |
| Returning customer | Re-order, track | `/track`, WhatsApp | Lost reference | None new |
| Bespoke customer | Explain an idea, get a quote, pay deposit | Brief form, pay link | Not knowing what happens next | None found |
| Cashier | Fast sale, pickups, drawer | Search, size, tender, receipt | Slow search, wrong stock | Search ran ~470 queries per keystroke batch (fixed) |
| Manager | Work the order queues, keep stock right | Orders, Stock, Products | Slow screens, two controls for one filter | Queue chips + "Work queue" dropdown set the same filter (merged) |
| Owner | See exceptions, money owed, staff, settings | Studio home, refunds, payment review, settings | Not knowing when something breaks | Alerts, health and backups exist (previous pass) |

## Storefront Practicality

Home → Shop → Product → Bag → Checkout → M-Pesa → Confirmation → Track works on a 375 px phone without horizontal scroll or console errors. Server time at 500 products: Home 93 ms, Shop 179 ms (was 2,041 ms), Product ~10 ms, Checkout 6 ms.

Found and fixed:
- The featured product's name and price on the Home hero were dark grey on a dark photo (global `h2`/`p` colours beat the inherited white). Now white.
- Every page's HTML carried the full route list, including all Studio and Sales Desk URLs, plus a second copy of the routing library: 34 KB of a 45 KB page. Shoppers now get 41 storefront routes; `/contact` HTML is 14.2 KB (gzipped 12.7 → 4.0 KB).
- Product cards rendered every photo of a product, but the extra photos only show on a desktop hover. Phones now download one photo per card (a product holds up to 3 photos of 60–100 KB each, so up to ~2 MB less on a 12-card Shop view).

Not changed (no evidence of a problem): the scroll-reveal motion on Home (content reveals correctly when scrolling; respects reduced motion), the hero carousel (pausable), the idle-time prefetch of likely next pages.

## Shopping Experience

The 5-second test passes: headline "Good style. Entirely yours." with "Shop Collection" and "Start a Custom Order" as the two actions. Trust strip (delivery or collection, secure M-Pesa, tracking, fit help) is visible on the first screen.

## Product Information

Name, price, category, description, photos (up to 3), sizes per part with live availability ("Only 2 left in M"), made-to-order vs stocked. No fabric/care/fit fields: they live in the description. Add structured fields only if real products show customers asking the same questions repeatedly.

## Checkout

One page: contact, delivery or pickup (priced by the server), M-Pesa or WhatsApp. The "secure STK push" phrase (developer term) now reads "You'll get an M-Pesa prompt on your phone. Enter your PIN to pay".

## Payment

Unchanged in this pass, deliberately (idempotency, one unanswered prompt per order, unconfirmed placeholders, reconcile, payment review). The customer never sees "not charged, try again" when the outcome is unknown.

## Tracking

`/track` with reference + phone, rate limited, no PII in the response. Unchanged.

## Bespoke

Brief → quote → pay link (deposit/balance) → production stages → delivery or pickup. The brief form was reworked before this pass (`CustomOrderBrief.vue`, uncommitted, 23 Oct). Unchanged here.

## Studio Practicality

The 3–5 second test passes on the Overview: a "System" box only when something is wrong, then "Next decision", then the payment review list and drawer differences. Orders now have one row of work queues in the order work flows (it was a 7-chip row missing three queues, plus a dropdown with all nine). Fixed a staff-visible typo ("before marking shitched" in the Ready-to-ship queue explanation).

Speed is the main change: Studio loads and every save that reloads it went from ~2.3 s / 316 queries to ~0.3 s / 73 queries at 500 products and 5,000 orders.

## Product Management

Create product → sizes and stock in one step (previous pass). No new friction found. The per-product piece names ("Hoodie"/"Jogger") had no Studio field, so every set showed hoodie/jogger labels; sets now say "Top size / Bottom size", matching the receipt, unless a product names its pieces.

## Stock

The Stock screen answers have / held / can sell / what changed / why. At 500 products it ran 16,831 queries (6.7 s) and sent every product's last 50 movements up front (25,000 rows). Now 77 queries (0.5 s); a product's history loads when it is opened. The owner's "switch to stock per size" preview ran a full reconciliation each load (11,184 queries); now 17. `serana:stock:reconcile` and preflight use the same report and are faster too.

## Orders

Paginated at 30 (already). The per-order cost (delivery lines summed 3–4 times, payments read 3 times, delivery option per order) is now loaded once per page: 315 → 72 queries. A test fails if the count grows with the number of orders on the page.

## Refunds

Unchanged. The refund queue and payment problems list grow only with unresolved problems; they are work queues that should stay short.

## Sales Desk

Back-to-back sale flow: search/scan → size → add → tender → receipt → next. The search was the measurable bottleneck (two queries per size, ~470 per search); now 12 queries and 19–78 ms. Search still waits for typing to pause; scanning an exact SKU/barcode jumps to the item.

## Staff

Unchanged: per-person accounts, PINs, roles (owner, manager, cashier), suspension, session revocation, history. Cashiers are redirected away from Studio endpoints (verified for the new stock history endpoint).

## Settings

Contact, delivery zones, payment methods, policies and Studio options are owner-editable. One leftover: `currency` is in the public settings allowlist but has no Studio field (multi-currency remnant). Harmless; left in place.

## Terminology

Customer-facing: Bag (not Cart) in the UI, Sales Desk (not Till/POS) for staff screens, "Custom order" for bespoke, "Payment confirmed/Paid", "Ready for pickup", "Ready to ship/Mark shipped". Code names (`till`, `pos`, `bespoke`) stay internal. Removed from staff screens: `STOCK_V2` (Stock panel). Remaining technical words are intentional and owner-only: "Daraja keys … callback token" in Settings → Payments (the owner has to pass this to whoever runs the server).

## Design Consistency

One money formatter is used everywhere; it now fixes the locale (`en-KE`), so a phone set to another language still shows "KSh 6,500". The WhatsApp product enquiry used its own "Ksh" spelling; it now uses the shared formatter and the same size labels as the product page. No broad visual rebuild was attempted or needed.

## Accessibility

Checked in the browser: hero text contrast (fixed), queue buttons keep `aria-pressed`, stock history has a loading state and an `alert` on failure, all 12 Studio tabs and the Sales Desk load without console errors. No full WCAG audit was run in this pass; the earlier `tests/Browser/operational-ux.mjs` axe run remains the reference.

## Responsive Behaviour

375 px storefront: no horizontal scroll on Home, Shop, Product, Bag, Checkout, Bespoke, Track, Contact, About. 1280 px Studio orders: the table scrolls sideways with a sticky Action column (by design); the longest action label ("Review payment before continuing") is now "Check payment first", so the sticky column covers less of the Payment column. 1024 px Sales Desk: catalogue and sale panel side by side, fine.

## Dead Code Removed

- `resources/js/Directives/reveal.js` and its global registration: never used in any template; it pulled anime.js into the entry bundle.
- 31 unused CSS classes in `app.css` (an old slide-deck template: `slide-title`, `company-name`, `gthumb`, `split-text`, `nav-btn`, `btn-outline`…). Shared selectors kept their used parts (`h1, h2`, `p`, `label, .tag`).
- 5 unused images in `public/images` (three ~2 MB hoodie PNGs, `checkout_bg.png`, `serana_logo.svg`): 7.4 MB → 1.0 MB of public images. Checked against code, docs, seeders and a full dump of the dev database first.
- `public/images/stitch_…/DESIGN.md` was being served publicly; moved to `docs/design/ATELIER-DESIGN-SYSTEM-EARLY.md`.
- The Laravel boilerplate `inspire` command.

## Redundant Code Removed

- The duplicate "Work queue" dropdown (same filter as the queue chips).
- The second, inline copy of the routing library on every page (the bundle already contains it).
- The WhatsApp enquiry's own money formatting.

## Complexity Reduced

- `StudioStock::forStudio`: per-product queries replaced by whole-list queries, history split into `StudioStock::history()`.
- One batched availability reader, `StockService::availableAtMany()`, used by the Stock screen and the Sales Desk search, instead of each calling `availableAt()` per size. Same rule; a test proves it equal to `availableAt()` for every size.

## Dependencies Removed

None. `composer.json` and `package.json` are already lean: every package has a use (anime.js still drives the bag drawer and About page; `league/flysystem-aws-s3-v3` serves an S3/R2 public disk; `concurrently` is the `composer dev` script). `laravel/tinker` is listed in both `require` and `require-dev`; harmless, not worth a lock-file change now.

## Query Improvements

| Path | Before | After |
|---|---|---|
| `StockService::preloadAvailability` (Shop, Home, Studio products) at 500 products | 1,873 ms (filtered every size per product) | 68 ms (grouped once) |
| Studio orders page (30 orders) | 315 queries | 72 |
| Stock screen | 16,831 queries, 6.7 s | 77 queries, 0.5 s |
| Stock reconciliation report (owner preview, `serana:stock:reconcile`, preflight) | 11,184 queries, 3.3 s | 17 queries, 0.2 s |
| Sales Desk search | 459–481 queries | 11–12 |

No indexes added: every list filter already has one (status, payment status, fulfilment, kind, resolution, phone, cipher, paid_at…), and the remaining unindexed filters (`balance_due < 0`, `LIKE '%…%'` search) take single-digit milliseconds at 5,000 orders.

## Payload Improvements

- Every storefront page: −31 KB HTML (route list and inline library).
- Studio orders page: 80 KB → 72 KB (a delivery-option object was being serialized as a side effect; nothing read it).
- Stock screen at 500 products: 683 KB → 625 KB (history no longer sent up front).

## Asset Improvements

- Product cards: one image per card until a desktop hover.
- Public images 7.4 MB → 1.0 MB.
- Product photos are already WebP, ≤1600 px, 60–100 KB. Cards (≈170–300 px wide) still load the full photo; thumbnails would cut that further (see Before Scale).

## Frontend Performance

Entry bundle 347 KB → 316 KB (gzip 124 → 112 KB). Storefront pages: LCP 250–400 ms locally on a 375 px viewport, CLS 0 (Home 0.08 from the hero carousel). Admin code stays in Studio/Sales Desk chunks and is never fetched by shoppers.

## Backend Performance

Server time at 500 products / 5,000 orders (local ddev, MariaDB 11.8): Shop 179 ms, Studio 253–340 ms, Stock 516 ms, Sales Desk search 19–78 ms, Track/Checkout/Product under 15 ms.

## Database Performance

See Query Improvements. Largest remaining per-request query counts: Studio full load 73 (lists, counters, policies), activity history 83 (paginated).

## Operational Improvements

- The README now says what Serana is, how to run, test and deploy it, which services must run, the settings that change behaviour, and what must never be done in production. It used to describe features that don't exist ("X-Ray overlay", "Studio Pulse", "The Registry").
- Scheduler comments were attached to the wrong jobs; fixed.

## Security Review

- Fixed: shoppers no longer receive every Studio and Sales Desk URL in each page. Staff still do; signing in or out as staff changes the Inertia version, so the page reloads with the right list (tested, and checked in a browser).
- Fixed: a design document was publicly downloadable from `/images/…/DESIGN.md`.
- New endpoint `GET /studio/stock/product/{id}/history` sits behind `auth`, `admin` and `can:stock.manage`; cashiers are redirected (tested).
- Uploads: images only (plus PDF for bespoke briefs), size-capped, rasters re-encoded to WebP. No `$guarded = []` models. Production template has `APP_DEBUG=false`.
- Low, left as is: the public product JSON includes `batch_sold` (units sold under the old stock model) because the bag uses it as a fallback. A competitor could read sales counts. Remove when the old stock model is retired.

## Observability

From the previous pass, verified present: `/health` (503 naming database, scheduler or queue), Studio "System" box, `JobRuns` per scheduled job, emailed operator alerts every 5 minutes, failed-job and stale-prompt warnings. Nothing added. Note for local work: the test suite writes to the same `storage/logs/laravel.log`, so local runs can trigger the "server errors" warning in dev.

## Backup / Recovery

From the previous pass: nightly `deploy/backup.sh` (database + uploads, checked, rclone off-server, retention), `deploy/restore-drill.sh`, `deploy/RESTORE.md`. Drill passed locally on 2026-10-22; **production drill and the off-server copy have not been exercised** (no server yet). This pass made no schema change, so no migration risk was added.

## SEO

Unchanged and present: per-page titles and descriptions, canonical, OpenGraph/Twitter tags, product structured data, `sitemap.xml`, `robots.txt` (`SeoReadinessTest`). Real product copy and photos matter more than anything technical now.

## Documentation Cleanup

README rewritten (see Operational Improvements). Code comments added only where a rule isn't obvious (bulk reads must equal the one-item rules; eager-loaded relations are for read-only lists; why staff pages get more routes).

## Test Quality

Added 6 tests, all on behaviour:
- `ListReadPathsTest`: bulk availability equals the per-product and per-size rules across sizes, sets with an uneven part, active and lapsed holds, made-to-order and retired products; the Stock screen, Shop availability and the Studio orders page take the same number of queries at 4 vs 36 products and 3 vs 23 orders.
- `PageShellTest`: shoppers get no staff routes; staff get theirs; becoming staff forces a full reload (409).
- `DeliveryOptionsTest`: the Studio list reads delivery totals, quote status and ledger rows the same from loaded rows as from queries, before and after pricing and payment.
- `StudioStockTest`: history now read from the endpoint; cashier access refused.

Suite speed: 26–27 s on SQLite; MariaDB runs in the gate table below. No slow outliers worth touching; the MariaDB race tests are kept.

### Technical gate (this branch, all uncommitted work included)

| Run | Result |
|---|---|
| SQLite, ledger reads off, stock per size off | 617 passed, 20 skipped (MariaDB-only), 26 s |
| MariaDB 11.8, off/off (`--fail-on-skipped`) | 637 passed, 85 s |
| SQLite, ledger reads on | 617 passed, 20 skipped, 25 s |
| MariaDB, ledger reads on | 637 passed, 77 s |
| SQLite, stock per size on | 617 passed, 20 skipped, 26 s |
| MariaDB, stock per size on | 637 passed, 77 s |
| SQLite, both on | 617 passed, 20 skipped, 25 s |
| MariaDB, both on | 637 passed, 71 s |
| Frontend unit tests | 49 passed |
| `npm run build` | OK |
| `config:cache`, `route:cache`, `view:cache` | OK (then cleared) |
| `serana:ledger-check` (dev DB) | passed, 237 orders, 0 mismatches |
| `serana:stock:reconcile` (dev DB) | passed |
| `serana:ledger:verify` (dev DB) | 1 difference: order #4313, a 21 Oct QA artefact (FC-14), not from this pass |
| `git diff --check` | clean |
| Browser: storefront at 375 px, 12 Studio tabs at 1280 px, Sales Desk at 1024 px, staff sign-in reload | no console errors (dev-only 403s on missing QA photos) |

## Problem Register

| ID | Problem | Who experiences it | Evidence | Business impact | Root cause | Priority | Action |
|---|---|---|---|---|---|---|---|
| FC-01 | Every Studio load and save takes ~2 s at 500 products | Owner, manager | probe: 2,266 ms, 316 queries | Staff slow on every action | Availability filtered every size per product (O(products × sizes)); ~9 queries per order on the list | Must fix | Fixed: 340 ms, 73 queries |
| FC-02 | Stock screen 6.7 s, 16,831 queries at 500 products | Manager, owner | probe | Stock checks avoided → wrong stock | Per-product queries; 50 history rows per product sent up front | Must fix | Fixed: 0.5 s, 77 queries; history on demand |
| FC-03 | Shop 2 s server time at 500 products | Every shopper | probe | Abandonment on phones | Same as FC-01 | Must fix | Fixed: 179 ms |
| FC-04 | Owner's stock preview / reconcile ran 11,184 queries | Owner, preflight | bench | Slow Stock screen for the owner | Per-product reconciliation | Must fix | Fixed: 17 queries |
| FC-05 | Featured product name unreadable on Home | Every visitor | screenshot; computed colour `rgb(23,23,26)` on a dark photo | First impression | Global heading colour beat inherited white | Must fix | Fixed |
| FC-06 | Two-piece sets labelled "Hoodie size / Jogger size" | Shoppers of non-hoodie sets | product page screenshot | Wrong-size purchases, questions | Hard-coded defaults; no Studio field for piece names | Must fix | Fixed: "Top/Bottom size" |
| FC-07 | Every page shipped all staff URLs and a second copy of the router (34 KB of 45 KB) | Every visitor | HTML breakdown | Slower first load; staff surface mapped publicly | `@routes` without a group; inline library | Should fix | Fixed |
| FC-08 | Product cards download every photo on phones | Phone shoppers | template: all photos rendered, shown only on hover | Data cost, slower grid | Hover preview rendered eagerly | Should fix | Fixed |
| FC-09 | Sales Desk search ~470 queries | Cashier | probe | Slower search as sizes grow | Two queries per size | Should fix | Fixed: 12 |
| FC-10 | Staff-visible typo "marking shitched" | Manager | `studioWorkflow.js` | Credibility | Typo | Must fix | Fixed |
| FC-11 | Duplicate queue controls on Orders | Manager | screenshot | Confusion | Two controls for one filter | Should fix | Merged into one row of all 9 queues |
| FC-12 | README describes features that don't exist | New engineer | README | Wrong mental model | Stale marketing copy | Should fix | Rewritten |
| FC-13 | Public design doc under `/images` | Anyone | file listing | Minor exposure | Old asset dump | Should fix | Moved to docs |
| FC-14 | One dev order whose legacy and split state disagree (#4313, from 21 Oct QA) | Nobody in production | `serana:ledger:verify` | None (dev data) | Earlier QA scenario | Report only | Left; production starts empty |

## Cleanup Register

| Area | Dead/duplicate/complex item | Why it exists | Can remove now? | Risk | Action |
|---|---|---|---|---|---|
| JS | `v-reveal` directive | Early animation experiment | Yes | Low | Removed |
| CSS | 31 unused classes | Old slide-deck template | Yes | Low | Removed |
| Assets | 5 unused images (6.5 MB) | Early mock-ups | Yes | Low | Removed |
| Assets | Public `DESIGN.md` | Design export dropped in `public/` | Yes | Low | Moved to `docs/design/` |
| Console | `inspire` | Laravel skeleton | Yes | Low | Removed |
| Studio UI | "Work queue" dropdown | Added before the chip row | Yes | Low | Removed |
| HTML | Inline Ziggy library | Ziggy default | Yes | Low | `skip-route-function` |
| Stock UI | History in the main payload | Built for a small catalogue | Yes | Low | On demand |
| Schema | `custom_orders` legacy columns, `LegacyOrderMap`, `ReadsOrderState` dual read | Rollback path for the ledger cutover (`LEDGER_READS`) | No | High | After stable production with `LEDGER_READS=true` |
| Schema | Old stock model (`batch_limit`/`batch_sold`), `STOCK_V2` setting | Rollback path for stock per size | No | High | After a stable season on stock per size |
| Schema | Duplicate index `products_slug_index` beside the unique index | Old migration | Later | Low | Drop in a post-launch migration |
| Code | Drops (public pages, Studio panel, jobs) | Paused feature (`DROPS_ENABLED=false`) | No | Medium | Keep until the owner decides on Drops |
| Code | `currency` public setting | Multi-currency idea | Later | Low | Remove with the next settings change |
| Code | `optimized_*` image accessors (return the same URLs) | Old image pipeline | Later | Low | Rename on the next storefront change |
| Packages | `laravel/tinker` listed twice | Skeleton | Later | Low | Next dependency update |

## Performance Register

| Screen/Operation | Baseline (500 products, 5,000 orders) | Bottleneck | Fix | Result | Worth it? |
|---|---|---|---|---|---|
| Shop | 2,041 ms | `preloadAvailability` O(P×V) | Group sizes once | 179 ms | Yes |
| Studio load / any save | 2,266 ms, 316 q | Same + per-order queries | Same + eager loads | 340 ms, 73 q | Yes |
| Studio orders partial | 1,993 ms, 315 q | Per-order delivery/ledger queries | Eager loads, relation-aware helpers | 159 ms, 72 q | Yes |
| Stock screen | 6,657 ms, 16,831 q, 683 KB | Per-product queries, history up front, reconcile | Whole-list queries, history on demand, batched report | 516 ms, 77 q, 625 KB | Yes |
| Sales Desk search | 173–199 ms, ~470 q | Two queries per size | `availableAtMany` | 19–78 ms, 12 q | Yes |
| Storefront HTML | 45.6 KB (12.7 KB gz) | Route list + inline router | Storefront group, skip inline | 14.2 KB (4.0 KB gz) | Yes |
| Entry JS | 347 KB (124 KB gz) | Unused directive + anime.js | Removed | 316 KB (112 KB gz) | Yes |
| Product page | 14 queries, 8 ms | A variant→product N+1 | — | — | No: 3 ms of DB |
| Shop payload | 353 KB (23 KB gz) at 500 products | Whole catalogue sent | — | — | Not yet (see Before Scale) |
| Studio `products` prop | 362 KB (10 KB gz) on every Studio load | Sent for every tab | — | — | Not yet |

## Product Gap Register

| Missing capability/problem | Who needs it | Evidence | Can operate manually? | Build now? | Why |
|---|---|---|---|---|---|
| Real product photos and copy | Shoppers | Dev catalogue is QA data | — | **Yes (content, not code)** | The biggest conversion factor |
| Returns/exchange policy page | Shoppers | No policy text on the storefront | Yes (WhatsApp) | Content only | Trust; write it in Studio content or About/Contact |
| What sold / sizes sold / slow movers report | Owner | Not in Studio | Yes (order list, stock history) | No | Wait for a month of real sales |
| Abandoned checkout insight | Owner | Expired orders exist in Orders | Yes (filter) | No | Real volume first |
| Online vs Sales Desk split | Owner | Orders filter by channel exists | Yes | No | Filter answers it |
| Repeat customers | Owner | Orders by phone/email exist | Yes (search) | No | No volume yet |
| Card thumbnails | Phone shoppers | Cards load 1600 px photos | — | Before scale | Worth it when real photos exist |
| Analytics (views, funnel) | Owner | None | Partly | No | Add a privacy-friendly page-view counter only if decisions need it |
| Reviews, wishlist, AI stylist, multi-currency, Stripe | — | No customer evidence | — | **No** | Feature creep |

## Must Fix Now

1. Commit the pending work (the 22–24 Oct launch-closure changes and this pass) and merge with CI green on all 8 runs.
2. Deploy to the real server and run `docs/audit/V1-CONTROLLED-LAUNCH-CHECKLIST.md` end to end, including low-value live M-Pesa payments, the off-server backup and a restore drill.
3. Put an external uptime monitor on `/health`.
4. Load the real catalogue (photos, descriptions, sizes, counted stock) through the Studio only.
5. Publish delivery and returns terms in customer-facing text.

## Before Scale

- Product photo thumbnails for cards and the Sales Desk (generate a ~600 px WebP at upload; backfill with `media:reoptimize`).
- Server-side pagination or search for the Shop beyond ~500 products (payload is 23 KB gzipped at 500; fine until then).
- Send the Studio product list only to the Products tab and the swap dialog.
- Shorter Studio table rows (drop the "Production" column when it repeats the action).

## Post-Launch

- A one-screen "what sold this week / sizes / slow movers" view, built from real questions the owner asks.
- Retire the old stock model and legacy order columns after a stable season (removes `batch_sold` from public JSON, `LegacyOrderMap`, `ReadsOrderState` dual reads).
- Drop the duplicate slug index.

## Do Not Build

Stripe or card payments, multi-currency, i18n/RTL, AI stylist, wishlist, ratings/reviews, discount codes online, loyalty points, Drops public relaunch (until the refund policy is decided), a custom analytics dashboard, multi-store support.

---

## Final Product Questions

- **What is SERANA really good at now?** Taking money safely: M-Pesa uncertainty, duplicates, retries, late payments and refunds are all explainable, and stock per size can't be oversold. Staff work from queues, not memory.
- **What still makes shopping harder than it should be?** Content: QA photos and descriptions, no written returns terms. Card photos are heavier than they need to be.
- **What still makes staff work harder than necessary?** Wide Studio tables on 1280 px laptops; the Studio is one long page with 19 panels (works, but dense).
- **What code is still more complex than the business needs?** The dual legacy/ledger read path and the dual stock model. Both are rollback paths that earn their keep until production proves the new models; `StudioController` (868 lines) mixes presentation helpers with request handling.
- **What will become a problem first as orders grow?** Not the order list (paginated, query count flat). The refund and payment-problem lists are unpaginated by design; they only grow if problems go unresolved.
- **As products grow?** The Shop and Studio send the whole catalogue: fine to ~500 products, then paginate.
- **What information does the owner still not have?** What sells by size and what doesn't move. Answerable from orders and stock history today, not on one screen.
- **What customer trust gap remains?** Written returns/exchange terms and real photos.
- **Biggest technical risk?** Running without the live checklist done: off-server backups, restore drill and the uptime monitor are unproven on the real server.
- **Biggest product risk?** Launching with thin product content.
- **Biggest operational risk?** The scheduler or queue silently stopping. Mitigated by `/health`, alerts and the Studio System box, but only if an external monitor watches `/health`.
- **What should we not build yet?** See Do Not Build.

## Final Readiness Score (0–5)

| Area | Score | Note |
|---|---|---|
| Customer clarity | 4 | Clear paths; needs real content and returns terms |
| Shopping speed | 4 | Fast server; card thumbnails would help phones |
| Mobile experience | 4 | No overflow, sticky actions, readable |
| Product management | 4 | One-step create; Studio-only |
| Stock management | 4 | Explainable, now fast |
| Studio decision speed | 4 | Exceptions first; dense but clear |
| Sales Desk speed | 4 | Search now fast |
| Payment reliability | 5 | Heavily hardened and tested |
| Operational resilience | 4 | Health, alerts, backups; production drill pending |
| Backend maintainability | 3 | Clear service boundaries; large Studio controller, dual read paths |
| Frontend maintainability | 3 | One-page Studio with 19 panels; consistent helpers |
| Database efficiency | 4 | Flat query counts on hot lists |
| Performance | 4 | Measured at 500 products / 5,000 orders |
| Security | 4 | Abilities enforced server-side; minor public `batch_sold` |
| Observability | 4 | Needs the external monitor connected |
| Business configurability | 4 | Settings in Studio |
| Owner visibility | 4 | Exceptions yes; sales insight not yet |
| Trust | 3 | Content and returns terms |
| Scalability for current business | 5 | Tested beyond expected first-year volume |
| Overall V1 quality | 4 | |

## Final Verdict

**READY AFTER SMALL FIXES**

1. The pending work is uncommitted and unmerged.
2. The live launch checklist has not been run on a real server (no server access yet): live M-Pesa, off-server backup, restore drill, uptime monitor.
3. The catalogue is QA data; real products, photos and returns terms are needed before customers arrive.

## Final Priorities

### DO NOW
1. Commit and merge with CI green.
2. Deploy and run the live launch checklist.
3. Connect an uptime monitor to `/health`.
4. Enter the real catalogue through the Studio.
5. Publish delivery and returns terms.

### DO AFTER REAL CUSTOMER DATA
1. Card thumbnails, if phone shoppers dominate (check the first month's orders).
2. A "what sold / sizes / slow movers" view, if the owner keeps asking.
3. Shop pagination, if the catalogue passes ~500 products.
4. Retire the legacy order columns and old stock model after a stable season.
5. Structured product fields (fabric, fit, care), if customers keep asking the same questions.

### DO NOT BUILD YET
1. Card/Stripe payments
2. Multi-currency
3. i18n/RTL
4. AI stylist
5. Wishlist
6. Ratings and reviews
7. Online discount codes
8. Loyalty points
9. Drops relaunch
10. Custom analytics dashboards

**Stop rule:** after the checklist passes, stop polishing. The next source of truth is real customers, real sales, real staff use and real operational data.
