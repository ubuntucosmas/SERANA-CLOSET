# SERANA-CLOSET — SYSTEM DISCOVERY REPORT

**Date:** 2026-10-15 · **Branch:** `master` @ `8dfaea1` (16 commits ahead of `origin/master`, unpushed) · **Phase:** Discovery — no code changed during this audit.

**Evidence base:** a full read of the commerce, payment, stock, Drops, custom-order and admin code paths, plus migrations and the live dev database; `route:list`; the production build; the PHPUnit suite; Pint in `--test` mode; and a real-Chrome audit (Playwright driving the system Chrome) covering 15 public pages, 2 customer pages and 7 Studio tabs at 375 / 430 / 768 / 1024 / 1440px in dark and light themes, with console and network capture, interaction probes and page-weight measurement. Screenshots and raw results are in the session scratchpad, not in the repo.

---

## Executive Summary

Serana Closet is a Laravel 12 + Inertia/Vue 3 boutique selling three things through **one order table** (`custom_orders`):

1. **Ready-to-wear shop orders**, paid by M-Pesa STK push.
2. **Bespoke commissions**, a brief handed off to WhatsApp with no online payment.
3. **Serana Drops**, sell-then-make pre-orders paid by M-Pesa.

**Strong:**
- The **M-Pesa money path is the best-engineered part of the system**: token-authenticated callbacks, server-authoritative amounts, verification that the paid amount matches, a record per STK push (`PaymentAttempt`), a paid-flip that only one caller can win, every-minute reconciliation that respects Safaricom's rate limit, and stock holds that expire.
- The Studio admin is visually coherent and usable.
- 83 tests pass, and they concentrate on the money path.

**Weak:**
- **Data model.** Products are hard-wired to a hoodie / jogger / "set" taxonomy that the admin cannot even change. Every product therefore asks for *two* sizes labelled Hoodie and Joggers, including Ladies Wear, Corporate, Kids and Accessories.
- **Order status.** One `status` column mixes payment states with production states.
- **Bag (cart).** Its quantity buttons crash, removing one size removes every size, and one product in two sizes cannot be checked out (verified in Chrome).
- **Visual direction.** The storefront has drifted into a "tech-noir" SaaS aesthetic: snake_case labels, trailing-underscore headings, card-in-card layouts, 8–10px text, three accent colours and empty spec rows. That competes with the clothes instead of serving them.

**Critical findings, reported rather than fixed:**

| # | Finding | Why it is critical |
|---|---|---|
| **P0-1** | A retry after a failed or cancelled STK push leaves the order in `Payment Failed`. Reconciliation only scans `Pending Payment` and `Expired`. If that retry is paid and Safaricom's callback is lost, **the customer has paid and the order is never settled**: no stock commit, no receipt, and the Studio still shows it as failed. | Money collected without fulfilment. No test covers it. |
| **P0-2** | `AdminSeeder` creates two admin accounts with the password **`password`**. It has run on the dev database (verified: `admin@serana.closet` / `password` logs into `/studio`). If any seeder has ever run in production, the admin is open to anyone. | Full admin takeover: orders, PII, prices, mark-as-paid. |
| **P0-3** | The Studio can set `is_paid = true` and free-text `status` on any order, and edit `price_quoted` on a paid order. A manual "mark paid" **skips stock commit and the receipt**, so the held stock expires back into the pool (oversell). Choosing a production stage overwrites `Paid` with e.g. `designing`, losing payment state. | Stock integrity and a payment audit trail can be silently broken from the UI. |

**Must confirm with ops (not derivable from the repo):** whether production runs `schedule:run` **and** a queue worker. If the scheduler is not running, stock holds never expire and reconciliation never runs. If the queue worker is not running, receipt emails never send. No deploy config exists in the repo; ddev runs neither locally.

---

## Technology Stack

| Layer | Actual |
|---|---|
| Backend | Laravel **12.62**, PHP **8.2.33** (ddev) |
| Frontend | Vue **3.5.32**, Inertia **2.3** (`@inertiajs/vue3`), Pinia **3.0.4**, Ziggy 2 |
| Build | Vite **7.3**, `laravel-vite-plugin` 2 |
| Styling | Tailwind **3.4** (PostCSS) with CSS-variable tokens. `@tailwindcss/vite` **4.2 is installed but unused** |
| Animation | animejs **4.3** (9 files). **gsap 3.15 installed, never imported** |
| i18n | vue-i18n **10.0.8**, uncommitted, 2 strings in use |
| DB | MariaDB 11.8 (ddev, `DB_CONNECTION=mariadb`) |
| Queue / cache / session | `database` drivers |
| Storage | `public` disk locally; S3 adapter installed (`league/flysystem-aws-s3-v3`); a `filesystems.public_disk` switch |
| Imaging | Intervention Image 3 (WebP at upload, `ImageOptimizer`) |
| PDF | barryvdh/laravel-dompdf (receipts) |
| Auth | Laravel Breeze (Inertia stack) + `role` column (`client` / `admin`) |
| Payments | Safaricom Daraja **M-Pesa STK push** (only live payment rail) |
| Fonts | Google Fonts: Cormorant Garamond, Manrope, **Material Symbols Outlined (full variable font, about 1.1MB)** |

### Tooling inventory

| Tool | Present? | State |
|---|---|---|
| PHPUnit 11 | ✅ | **83 tests / 238 assertions, all pass** (15 feature files, heavy on payments and stock) |
| Laravel Pint | ✅ installed | No config file; **65 files fail** `pint --test` (formatting only) |
| Pest | ❌ | — |
| PHPStan / Larastan | ❌ | — |
| ESLint / Prettier | ❌ | — |
| TypeScript / vue-tsc | ❌ | Plain JS (`jsconfig.json` only) |
| Vitest | ❌ | No frontend unit tests |
| Playwright / Cypress | ❌ | No browser tests in the repo (this audit used a scratchpad-only Playwright) |
| CI | ❌ | No workflow files |

### Baseline (re-confirmed)

- `npm run build` ✅ passes (5s, 43 chunks; main `app.js` 372KB / 132KB gzip; `app.css` 119KB).
- `ddev exec php artisan test` ✅ 83 passed.

---

## Application Architecture

```
Browser (Vue 3 SPA via Inertia)
 ├─ StorefrontLayout ─ TopNavBar · MobileBottomNav · CartDrawer · Footer · Notification(pulse)
 │    Pages: Home · Shop · ProductDetail · Checkout · CustomOrder · Drops · DropDetail · TrackOrder
 │           About · Contact · Circle · Profile/DesignsVault · Profile/Edit · Auth/*
 ├─ AdminShell ─ Admin/StudioDashboard (+7 partial panels, tab via ?tab=)
 ├─ Pinia: useCartStore (localStorage 'serana_bag') · usePulseStore (toasts)
 └─ Composables: useCurrency · useWhatsApp     Directive: v-reveal (animejs)
          │  Inertia visits + axios JSON (checkout, STK, status poll, custom order, drop reserve)
Laravel 12
 ├─ Controllers (thin-ish): Home · Shop · Checkout · Mpesa · CustomOrder · Drop · OrderTracking · Receipt · Profile · Newsletter · Circle
 │                         Admin: Studio · Catalog · Drop · Gallery · Theme
 ├─ Services: MpesaService · OrderSettlementService · ImageOptimizer   (+ unused: StripeCheckoutService, CurrencyConverter)
 ├─ Support: OrderStatus (consts) · Phone · WhatsApp · PublicUrl
 ├─ Event/Listener: MpesaWebhookHandled → SendOrderConfirmation (queued mail)
 ├─ Middleware: HandleInertiaRequests (shares auth, flash, theme_settings, categories, whatsapp) · AdminMiddleware · VerifyMpesaCallback
 ├─ Commands (scheduled): stock:release-expired (1m) · mpesa:reconcile (1m, bg) · drops:close-expired (5m)
 │                        (manual: media:reoptimize, mail test)
 └─ Jobs: ProcessAIStyleRecommendation (uncommitted, never dispatched)
```

- **No policies and no form-request authorization** beyond `StoreCheckoutRequest`. Authorization is the `admin` middleware plus `canActOnOrder()` (session-bound order ids) for guest payment.
- **No API routes file.** JSON endpoints live in `web.php`.

---

## Route Map

68 application routes (excluding vendor routes). **Auth** = middleware.

### Public storefront
| Method | URL | Action | Auth | Purpose |
|---|---|---|---|---|
| GET | `/` | HomeController@index | — | Home, featured products, live drop, studio load |
| GET | `/shop` | ShopController@index | — | Catalogue with `?category=` (server); sort is client-only |
| GET | `/shop/{product:slug}` | ShopController@show | — | Product detail |
| GET | `/about` | closure → About | — | Brand story |
| GET | `/contact` | closure → Contact | — | Contact |
| GET | `/the-circle` | CircleController@index | — | Community / gallery |
| POST | `/newsletter/subscribe` | NewsletterController@subscribe | — | Lead capture (**no throttle**) |
| GET | `/cart` | closure → `Cart` | — | ❌ **Dead: `Cart.vue` does not exist → HTTP 500** (verified) |

### Authentication (Breeze, `routes/auth.php`)
`GET/POST /login` · `GET/POST /register` (open self-registration) · `GET/POST /forgot-password` · `GET /reset-password/{token}` · `POST /reset-password` · `GET /verify-email` · `GET /verify-email/{id}/{hash}` · `POST /email/verification-notification` · `GET/POST /confirm-password` · `PUT /password` · `POST /logout`

### Customer account (`auth`)
| Method | URL | Action | Purpose |
|---|---|---|---|
| GET | `/dashboard` | closure | Redirect: admin → `/studio`, client → `/my-vault` |
| GET | `/my-vault` | closure → Profile/DesignsVault | Customer's orders (`profile.designs`). **Inline query in the route file** |
| POST | `/my-vault/invite` | ProfileController@generateInvitation | Invitation codes (no consumer for codes found) |
| GET/PATCH/DELETE | `/profile` | ProfileController | Account settings |

### Checkout / payment
| Method | URL | Action | Auth | Purpose |
|---|---|---|---|---|
| GET | `/checkout` | CheckoutController@index | — | Checkout page (bag comes from localStorage) |
| POST | `/checkout/process` | CheckoutController@process | throttle 20/min | Create order + stock holds (JSON) |
| POST | `/payments/mpesa/stk` | MpesaController@initiate | throttle 6/min + session-bound | STK push |
| GET | `/payments/mpesa/status/{orderId}` | MpesaController@status | throttle 30/min + session-bound | Poll payment status |
| GET | `/orders/{order}/receipt` | ReceiptController@download | `signed` | PDF receipt |

### Order tracking
| GET | `/track` | OrderTrackingController@index | — | Tracker form |
|---|---|---|---|---|
| POST | `/track/lookup` | OrderTrackingController@lookup | throttle 10/min | Cipher + phone lookup |

> The brief referred to `/track-order`; the real URL is `/track`.

### Custom orders
| GET | `/custom-order` | CustomOrderController@index | — | Bespoke brief form (`?product=` prefill) |
|---|---|---|---|---|
| POST | `/custom-orders` | CustomOrderController@store | throttle 10/min | Save brief → WhatsApp URL |

### Serana Drops
| GET | `/drops` | DropController@index | — | Live / greenlit drops |
|---|---|---|---|---|
| GET | `/drops/{drop:slug}` | DropController@show | — | Drop detail (drafts 404) |
| POST | `/drops/{drop}/reserve` | DropController@reserve | throttle 20/min | Create drop reservation order |

### Admin / Studio (`auth` + `admin`, prefix `/studio`)
| Method | URL | Action | Purpose |
|---|---|---|---|
| GET | `/studio` | StudioController@index | Single-page dashboard; **loads every order, product, gallery item, lead and drop** |
| PUT | `/studio/order/{order}` | StudioController@update | Free-text `status`, `is_paid`, `price_quoted`, notes |
| DELETE | `/studio/order/{order}` · `/{id}/force` | destroy / forceDelete | Archive / purge (purge cascades `payment_attempts`) |
| POST | `/studio/order/{id}/restore` · `/{order}/progress-snap` | restore / storeProgressSnap | Restore; progress photo |
| GET | `/studio/catalog` | CatalogController@index | ⚠ Renders the same dashboard (no separate Catalog page) |
| POST/PUT/DELETE | `/studio/catalog/product…` · `/category…` | CatalogController | Product / category CRUD, restore, force-delete |
| POST/PUT/DELETE | `/studio/drops…` | Admin\DropController | Drop CRUD (`{drop:id}` binding) |
| POST/PUT/DELETE | `/studio/gallery…` | GalleryController | Showcase CRUD |
| POST/DELETE | `/studio/theme/update` · `/bulk-update` · `/{key}` | ThemeController | **Arbitrary key/value settings + unvalidated file upload** |

### Callbacks / webhooks
| POST | `/payments/mpesa/callback/{token}` | MpesaController@callback | `mpesa.callback` (token + optional prod IP allowlist), CSRF-exempt |
|---|---|---|---|

### Route hygiene
- **Dead:** `/cart` (500).
- **Naming inconsistency:**
  - Admin routes are split between `studio.*` and `admin.*`.
  - `custom-order` (GET) vs `custom-orders.store` (POST).
  - `profile.designs` serves `/my-vault`.
- **Business logic in route closures:** `/my-vault` and `/dashboard`.
- **Without consumers:**
  - The `/studio/catalog` GET duplicates `/studio`.
  - `POST /my-vault/invite` creates invitation codes that nothing redeems.
- **Missing:** a sitemap, legal pages (footer Privacy / Terms / Shipping / Authenticity are `href="#"`), and a wishlist route (the Home copy promises a wishlist).

---

## Screen Inventory

| Screen | Route | Purpose | Primary user | Primary action | Current status | UX problems (observed in Chrome) | Redesign priority |
|---|---|---|---|---|---|---|---|
| Home | `/` | Brand + discovery | Shopper | Shop / Start order | Works; ~14 screens tall on mobile, 6.9MB | 9 full-width category tiles reusing the same 3 photos; generic "01/02/03 Bespoke fit / Fast styling / Secure checkout" SaaS card (uncommitted); studio load/capacity shown to shoppers; 2 newsletter forms; `Collection_` underscores; green gradient off-brand; 3× `<h1>` | **High** |
| Shop | `/shop` | Browse catalogue | Shopper | Open product | Works | Sort is client-only; out-of-stock items still listed; price wraps (`KSh / 5,000`) at 375px; light-grey filter bar in dark mode; 2 `<h1>` | **High** |
| Product detail | `/shop/{slug}` | Choose + add | Shopper | Add to bag | Works with defects | Forced "Hoodie / Joggers" pieces for every product; empty `PRODUCT_SPECS 01–04`; snake_case labels; CTA "Buy Now" only adds to bag, styled `blue-600` (not brand cyan); floating bag bubble overlaps CTA and **bypasses size selection**; no related products, breadcrumbs or delivery info | **High** |
| Cart drawer | (component) | Review bag | Shopper | Checkout | **Broken** | −/+ throws `TypeError: updateQuantity is not a function`; Remove deletes all sizes of a product; stale client prices; 8px text | **Critical** |
| Cart page | `/cart` | — | — | — | **500** | Page component missing | Remove route |
| Checkout | `/checkout` | Details + pay | Shopper | Pay with M-Pesa | Works (single-size bags) | Price shown is from localStorage, not server; two-size bag → raw `items.0.id field has a duplicate value`; M-Pesa green + cyan + blue = 3 accents; 6 inputs without programmatic labels | **High** |
| STK / success panels | (components) | Payment progress | Shopper | Enter PIN / view receipt | Works | Success copy fine; retry path leads to the P0-1 gap | Medium |
| Custom order | `/custom-order` | Bespoke brief | Bespoke client | Send brief to WhatsApp | Works | Very long single form (5 sections); "Expedite (+KSh 1,500)" promised but never charged or recorded; 12 inputs without labels; WhatsApp number not validated | **High** |
| Drops | `/drops` | List drops | Shopper | Open drop | Works | Thin page; fine | Medium |
| Drop detail | `/drops/{slug}` | Reserve | Shopper | Reserve your piece | Works | No refund messaging for unfunded drops; countdown fine | Medium |
| Track order | `/track` | Self-serve status | Guest / customer | Look up | Works | Two-field lookup is good; reachable on mobile only via the new (uncommitted) menu | Medium |
| About | `/about` | Story | Shopper | — | Works | Long; 3× `<h1>`; heavy textures | Low |
| Contact | `/contact` | Contact | Shopper | WhatsApp / form | Works | 5 unlabelled inputs | Low |
| The Circle | `/the-circle` | Community | Shopper | Join | Works | Purpose overlaps the newsletter | Low |
| Login / Register / Forgot | `/login` … | Auth | Customer | Sign in | Works | Breeze defaults reskinned; 3 unlabelled inputs on login; there is also a `LoginModal` (two login UIs) | Medium |
| My Vault | `/my-vault` | Customer orders | Customer | View progress | Works | Shows only orders tied to `user_id` (guest checkouts are not linked after sign-up) | Medium |
| Profile | `/profile` | Account | Customer | Edit | Works (Breeze) | Visually off-brand (Breeze defaults) | Low |
| Studio · Home | `/studio` | Daily overview | Owner | Act on attention items | Works | "Needs attention" lists only `Pending Payment`; omits **paid-awaiting-fulfilment, Amount Mismatch, Payment Failed, custom briefs** | **High** |
| Studio · Orders | `/studio?tab=orders` | Manage orders | Owner | Update status | Works | All orders loaded unpaginated; free-text status; manual paid toggle (P0-3) | **High** |
| Studio · Products | `?tab=products` | Catalogue | Owner | Add / edit | Works | **No garment-type / size model**; batch limit defaults to 15 silently | **High** |
| Studio · Drops | `?tab=drops` | Campaigns | Owner | Create drop | Works | No refund / notify flow for closed drops | Medium |
| Studio · Showcase / Subscribers / Settings | tabs | Content | Owner | Edit | Works | Settings accept arbitrary keys / files | Medium |

---

## Database / Domain Model

```
User 1─* CustomOrder *─1 Drop
            │1
            ├─* StockReservation *─1 Product *─1 Category
            ├─* PaymentAttempt
            └─* OrderProgressSnap
Lead (newsletter)   GalleryImage (showcase)   ThemeSetting (key/value CMS)
```

| Model | Key fields | Relationships | Lifecycle | Responsibility |
|---|---|---|---|---|
| **CustomOrder** (`custom_orders`) | `cipher` (SRN-XXXXXXXX-YYYY, unique), `type`, `status`, `is_paid`, `price_quoted` decimal(10,2), `items_json`, `mpesa_checkout_id`, `mpesa_receipt_number`, `drop_id`, measurements (`bust/waist/hips/length_cm`), `full_name`, `email`, `phone`, `whatsapp_number`, `address`, `city`, `priority_pass`, `internal_notes`, soft deletes | user, drop, stockReservations, paymentAttempts, progressSnaps | Payment: `Pending Payment → Paid / Payment Failed / Expired / Amount Mismatch`. Production: `pending → designing → sewing → completed → shipped` (**same column**) | **Every order type.** Shop orders, bespoke briefs and drop reservations. Also holds the stock and payment helpers |
| **Product** | `name`, `slug` (unique), `price` decimal(10,2), `garment_type` enum(set, hoodie, jogger) default `set`, `in_stock`, `batch_limit` (**default 15**), `batch_sold`, `is_customizable`, `specifications` JSON, `image_path`, `secondary_images` JSON, soft deletes | category, reservations | Available = `batch_limit − batch_sold − active holds`; null limit = unlimited | Catalogue + stock counter (no variants) |
| **Category** | `name`, `slug`, `banner_url` | products | — | Navigation / merchandising |
| **StockReservation** | `custom_order_id`, `product_id`, `quantity`, `status` (held / released / committed), `expires_at` | order, product | `held → committed` (paid) / `released` (expired / failed) | Hold-then-sell inventory |
| **PaymentAttempt** | `checkout_request_id` (unique), `phone`, `amount` int, `status` (pending / paid / failed), `result_code`, `result_desc`, `mpesa_receipt_number` | order | `pending → paid / failed` | One record per STK push |
| **Drop** | `title`, `slug`, `price`, `target_quantity`, `sizes` JSON, `ends_at`, `status` (draft / live / greenlit / fulfilled / closed), soft deletes | reservations (CustomOrder), paidReservations | `draft → live → greenlit / closed` (auto at deadline) → `fulfilled` (manual) | Sell-then-make campaign |
| **OrderProgressSnap** | `custom_order_id`, `image_path`, `description` | order | — | Production photos shown to the customer |
| **ThemeSetting** | `key` (unique), `value` text | — | — | CMS settings: logo, hero, WhatsApp, capacity, labels |
| **Lead** | `email` (unique), `status` | — | — | Newsletter |
| **GalleryImage** | `image_path`, `client_name`, `garment_name`, `testimonial`, `is_featured`, soft deletes | — | — | Showcase / testimonials |
| **User** | `role` (client / admin, comment mentions tailor), `invitation_code` | customOrders | — | Auth |

### Data-model risks
1. **No product variants or size model.** `garment_type` is a 3-value enum with no admin control (`CatalogController` neither validates nor saves it). All 2 live products are `set`. Sizes live in `items_json.options`, and stock is per product, not per size. This is the root cause of the product-page and cart defects.
2. **Overloaded `custom_orders.type`.** System values (`standard`, `drop`, `custom`) are mixed with free-text outfit types from the bespoke form (`Corporate`, `Hoodie` exist in the DB). Code branches on `type === 'standard'`.
3. **Overloaded `status`.** Payment and production share one column (acknowledged in `OrderStatus`). Admin edits overwrite `Paid`.
4. **No `order_items` table.** Line items are JSON, so there's no per-product sales reporting or referential integrity to products.
5. **`products.batch_limit` defaults to 15.** Every product the admin creates is silently capped at 15 units unless cleared.
6. **`payment_attempts` cascade-deletes with the order.** A force-delete in Studio destroys payment evidence (receipt numbers, result codes).
7. **Nullable fields that shouldn't be:** `custom_orders.cipher` is nullable (it's the public reference); `phone` and `email` are nullable for shop orders.
8. **Money types:** `decimal(10,2)` in the DB, but `PaymentAttempt.amount` is int (KSh whole shillings, correct for M-Pesa). JS cart totals use float math, for display only.
9. **Indexes:** good on hot paths (`status`, `user_id`, `mpesa_checkout_id`, `(drop_id, is_paid)`, reservation composite). Missing: `custom_orders.cipher` is unique-indexed (fine); `payment_attempts(status, created_at)` for the reconciler scan (minor at current volume).
10. **Oversell by design:** `commitReservations()` commits *released* holds too, so `batch_sold` can exceed `batch_limit` when a late payment arrives. This is deliberate (serve the payer) and surfaces as sold out, but nothing alerts the owner.

---

## Storefront Journey (actual)

```
ARRIVE (/)  ──►  DISCOVER (category tiles / featured / drop launchpad)
   ──►  SHOP (/shop, ?category=)  ──►  VIEW PRODUCT (/shop/{slug})
   ──►  SELECT SIZE  [always 2 sizes: "Hoodie" + "Joggers", because garment_type is always 'set']
   ──►  ADD TO BAG (Pinia → localStorage; drawer auto-opens)
   ──►  CART DRAWER  [✗ −/+ crashes · ✗ Remove deletes all sizes · prices are stale client copies]
   ──►  CHECKOUT (/checkout: details + measurements → POST /checkout/process
                  → order 'Pending Payment' + 15-min stock holds, order id bound to session)
        [✗ same product in 2 sizes → 422 "duplicate value"]
   ──►  M-PESA (POST /payments/mpesa/stk → STK prompt → client polls /status every 5s)
   ──►  CALLBACK / RECONCILE → OrderSettlementService::markPaid → commit stock → queued receipt email
   ──►  ORDER CONFIRMATION (MpesaSuccessPanel: cipher, amount, signed receipt PDF link)
   ──►  TRACK ORDER (/track: cipher + phone)  or  /my-vault (only if the order was placed while logged in)
```

**Missing transitions:**
- Success → account creation or linking. Guest orders never attach to a later account.
- Payment failure → a clear recovery screen (retry exists in the panel, but status stays `Payment Failed`; see P0-1).
- Out-of-stock (`in_stock=false`) products can still be viewed and bought. Only Home filters them.
- No order-shipped notification (email / SMS / WhatsApp) when the Studio changes the status.
- No wishlist (copy promises one).

---

## Bespoke Journey (actual)

```
/custom-order (optionally ?product=slug) → 5-section form (details · garment · measurements with SizingFigure · fabric/colour · inspiration uploads + notes · Expedite toggle)
  → POST /custom-orders → CustomOrder (type = outfit type or 'custom', status 'pending', no price) + images optimised
  → response: whatsapp_url + cipher → customer taps through to WhatsApp with a prefilled brief
  → [OFFLINE] studio quotes over WhatsApp → admin sets price_quoted / status / is_paid manually in Studio
  → progress snaps → customer sees them in /track (cipher+phone) or /my-vault
```

**Missing transitions:**
- Quote → online payment. There's no deposit or milestone payment; the STK flow is never offered for bespoke.
- The "Expedite (+KSh 1,500)" fee is never added to any price.
- Brief reference-image URLs are built as `APP_URL/storage/...`, so they're **wrong if the S3 disk is active**. The code should use `PublicUrl`.
- No email confirmation of the brief; WhatsApp is the only channel.

## Drops Journey (actual)

```
/drops → /drops/{slug} (progress x/target, countdown, sizes, SizingFigure measurements)
  → POST /drops/{drop}/reserve → CustomOrder(type 'drop', 'Pending Payment', price = drop price, no stock holds)
  → same STK + status polling + settlement as shop
  → paid reservations count toward target → drops:close-expired at deadline → 'greenlit' (funded) or 'closed'
  → admin marks 'fulfilled' manually
```

**Missing transitions:**
- **Closed / unfunded drop → refund.** Customers have paid, but there's no refund workflow, notification or Studio queue.
- Greenlit → production / shipping updates to reservers.
- A payment can still be initiated after the drop closes (STK `initiate` does not re-check `is_open`).
- No phone validation at reserve; the error appears only at the STK step.

---

## Checkout & Payment Flow (M-Pesa)

```
CHECKOUT  POST /checkout/process
  validate (items distinct, qty 1–20, Kenyan mobile) → lock product rows → re-check availability
  → create CustomOrder 'Pending Payment' (server-priced snapshot) → StockReservation 'held' (expires +15m)
  → session('serana.order_ids') += id
PAYMENT INITIATION  POST /payments/mpesa/stk
  canActOnOrder (session / owner / admin) → reject Paid / Expired → amount = round(price_quoted)
  → secureStockForPayment (extend live holds / re-acquire released, else 409)
STK PUSH  MpesaService::initiateStkPush (cached OAuth token) → PaymentAttempt 'pending' (+ mirror mpesa_checkout_id)
CALLBACK  POST /payments/mpesa/callback/{token}  [VerifyMpesaCallback: hash_equals token; prod IP allowlist]
  find attempt by CheckoutRequestID → if order already paid: book duplicate (refund-owed log) and ACK
  → success: amount must equal attempt amount, else Amount Mismatch + owner email
  → markPaid (conditional UPDATE where is_paid=false; commit reservations in the same txn) → event → queued receipt mail
  → failure: attempt failed; order → 'Payment Failed' + release holds only if no other live attempt
RECONCILIATION  mpesa:reconcile every minute (bg, withoutOverlapping)
  pending attempts 2 min – 24 h old on orders in [Pending Payment, Expired] → STK Query (13s spacing, batch 4, spike-arrest retry)
  → paid → markPaid · failure codes → markFailed / markUnpaid
EXPIRY  stock:release-expired every minute → holds 'released'; Pending Payment orders with no holds and no recent attempt → 'Expired'
```

| Property | Verdict | Evidence |
|---|---|---|
| Idempotency | ✅ | Conditional `UPDATE … WHERE is_paid=false`; only one caller can claim the flip; the event fires after commit |
| Callback authentication | ✅ (with config caveat) | Fail-closed token; IP allowlist only if configured and env=production |
| Duplicate callbacks | ✅ | Already-paid branch ACKs and books duplicate payments as refund-owed (log only; **no Studio surface**) |
| Failed payments | ⚠ | Marked correctly, but see P0-1 for a retry after failure |
| Pending payments | ✅ | Polling + reconciliation within ~2–3 min |
| Amount verification | ✅ | Mismatch flags the order and emails the owner. **Not shown in the Studio attention list** |
| Stock holds and release | ✅ | Row locks, re-check inside the lock, 15-min expiry, re-acquire on retry |
| Scheduled reconciliation | ✅ code / ❓ ops | Depends on the production scheduler (unverifiable from the repo) |
| Reconcile window | ⚠ | Attempts older than 24h are never queried again; they stay `pending` forever |
| **P0-1 gap** | ❌ | `initiate` never resets `Payment Failed` → `Pending Payment`; reconciler excludes `Payment Failed` |

### Stripe assessment

`StripeCheckoutService` is **not wired, not installable as-is and not justified yet**:
- `stripe/stripe-php` is absent from composer.
- It reads `env()` at runtime, which breaks under `config:cache`.
- It has no webhook, no order or payment model integration, and floats amounts.
- `automatic_tax` needs Stripe Tax setup.

The business is Kenya-centric:
- KSh pricing.
- Kenyan-mobile-only validation.
- "Delivered across Kenya" OG copy.
- WhatsApp-led bespoke.

Nothing in the data shows international demand. International card payment would also need multi-currency pricing, international shipping and duties, and address formats, none of which exist. **Recommendation: remove from the tree and revisit only on a business decision with evidence of overseas demand.** If cards become necessary for *Kenyan* customers, a local aggregator that supports M-Pesa and cards in KSh is likely a closer fit than Stripe (a business decision).

---

## Inventory Flow

| Question | Answer (from code) |
|---|---|
| When is stock checked? | Client side on add-to-bag (`batch_limit − batch_sold`, ignores holds, advisory only). Server side at checkout pre-check, and again **inside a row lock** during order creation. Again at STK initiate (`secureStockForPayment`). |
| When is it held? | At `/checkout/process`, for batch-limited products only (`StockReservation` held, 15 min). |
| When is it deducted? | On confirmed payment: `commitReservations()` increments `batch_sold`, holds become committed. |
| When does the hold expire? | `expires_at` (+15 min, extended on each STK retry); `stock:release-expired` releases every minute. |
| If M-Pesa fails? | The callback or reconciler marks the attempt failed; if no live attempt remains, the order becomes `Payment Failed` and holds are released. |
| If the callback is lost? | The reconciler queries Daraja after 2 min and settles. **Except** for orders already in `Payment Failed` (P0-1). |
| Two customers, last unit? | Serialized by `lockForUpdate()` on the product row; the second gets "just sold out". ✅ |
| Drops vs normal? | Drops have **no stock or holds**; reservations are unlimited by design (sell-then-make). The target counts paid reservations. |
| Custom / bespoke? | No stock interaction at all. |

### Oversell / incorrect-stock risks
1. **`in_stock=false` is ignored** by Shop, ProductDetail and checkout. The admin "Out of stock" switch only hides an item from Home. Products with `batch_limit=null` are unlimited, so an "out of stock" unlimited product can still be sold.
2. **Manual "Marked as paid" in Studio** doesn't commit holds. They expire, stock returns to the pool and is resold (P0-3).
3. **Late payment after release** commits anyway (deliberate). `batch_sold` can exceed `batch_limit` silently.
4. **Stock is per product, not per size.** A "last M" cannot be represented.
5. **Admin can edit `batch_sold` directly**, with no audit trail.
6. **The `batch_limit` default of 15** silently caps new products.

---

## Admin Architecture

One Inertia page (`Admin/StudioDashboard.vue`, 566 lines) with 7 tabs, inside `AdminShell`. The data for every tab loads on every visit.

| Owner question | Can the Studio answer it? |
|---|---|
| What needs my attention? | ⚠ Only `Pending Payment` (5 latest) + `in_stock=false` products. Misses Amount Mismatch, duplicate payments, Payment Failed with later money, paid-awaiting-fulfilment, new bespoke briefs, closed drops needing refunds |
| What orders came in? | ✅ Orders tab (All / Custom / Shop filter, search) |
| What has been paid? | ✅ Badges; revenue for the week |
| What is waiting for fulfilment? | ❌ No "paid, not shipped" view. Paid shop orders sit at status `Paid`, outside production stages |
| What products are available? | ✅ Products tab |
| What is low in stock? | ❌ "Low stock" = manually flagged out-of-stock; no threshold on `batch_limit − batch_sold` |
| What drops are active? | ✅ Drops tab with reservations |
| What custom orders need action? | ⚠ Mixed into the order list; no "new brief, not yet quoted" queue |
| Customer follow-ups? | ❌ Contact form / leads have no follow-up state |
| Where do I change content and settings? | ✅ Settings tab (logo, hero, WhatsApp, socials, capacity, highlights) |

**Fragmentation:**
- Payment state and production state are edited in one free-text field.
- The fulfilment step for shop orders is undefined.
- Drop refunds are invisible.
- The `/studio/catalog` route duplicates `/studio`.

---

## Existing Integrations (proven by code)

| Integration | Status |
|---|---|
| Safaricom Daraja (OAuth, STK Push, STK Query, callback) | ✅ Live path |
| SMTP mail (receipt to customer, Bcc owner; amount-mismatch alert) | ✅ Queued listener |
| WhatsApp click-to-chat (`wa.me` links, prefilled briefs) | ✅ No API; links only |
| Google Fonts | ✅ |
| S3-compatible storage | ⚙ Supported by config / adapter; local in dev |
| dompdf receipts | ✅ |
| Stripe / ExchangeRate-API / Ollama | ❌ Unwired, uncommitted prototypes |

---

## Current Design System

| Element | Current state |
|---|---|
| **Colours** | CSS-variable tokens (`--primary` electric cyan `#57d5fc`, kept per owner decision), surface / on-surface scale, dark default + light theme. **Off-token colours in use:** `blue-600` (product CTA, progress bars), M-Pesa green (checkout), a green gradient on "Our Collection", `slate-*` in admin, raw `rgba(57,255,20)` neon green in the nav badge shadow |
| **Typography** | Cormorant Garamond (headline) + Manrope (body / label; `mono` also maps to Manrope). Sizes range down to **7–9px** (`text-[7px]`, `text-[8px]`, `text-[9px]`), measured at 20–49 sub-11px text nodes per page; ultra-wide letter-spacing on micro labels; snake_case labels (`FABRIC_QUALITY`, `PRODUCT_SPECS`); trailing-underscore headings |
| **Spacing** | Ad hoc per page (`py-10 md:py-14`, `p-3 md:p-5`, …); no section rhythm scale |
| **Buttons** | At least 5 styles: cyan solid, cyan outline, `blue-600` solid, M-Pesa green, text-underline. Breeze `PrimaryButton` / `SecondaryButton` / `DangerButton` exist but storefront pages don't use them |
| **Inputs** | Dark filled boxes; many lack `<label for>` association (checkout 6, custom order 12, contact 5) |
| **Cards** | Card-in-card on product and checkout; glass-panel + border + shadow stacking; rounded radii vary from 0 / `rounded-sm` to `rounded-[2rem]` |
| **Product cards** | `ProductCard.vue` (303 lines), bordered tile, image + category + name + price |
| **Modals / drawers** | `Modal.vue` (Breeze), `LoginModal`, `CartDrawer`, mobile menu overlay; no focus trap or Escape on the mobile menu |
| **Badges** | Live drop dot, count badges (square, cyan), status pills in admin |
| **Navigation** | Desktop: 7 links + theme + WhatsApp + bag + login. Mobile: header bag + hamburger (uncommitted) + fixed bottom nav (Home, Shop, Cart, Circle, Drops), so **two cart buttons and two navs** |
| **Icons** | Material Symbols Outlined (full variable font) + inline SVG |
| **Shadows / borders** | Many 1px borders at 5–10% opacity; neon glow shadows; blur backdrops |
| **Animation** | animejs reveal on most sections (content is `opacity:0` until JS intersects), auto-scrolling gallery, pulse dots, hover lifts; global `transition-all` on every link and button (uncommitted) |

### Inconsistency summary
- Three accent hues.
- Two type personalities: an editorial serif vs dev-console snake_case.
- Five button styles.
- Variable radii.
- Admin (slate / clean) vs storefront (neon / noir) feel like different products.
- Light theme was retrofitted (17 hard-coded `text-white` on the product page at HEAD).

---

## Current Strengths
1. **Payment correctness engineering:** idempotent settlement, attempt ledger, amount verification, reconciliation, spike-arrest pacing, session-bound guest authorization, signed receipts, PII-safe tracking payload.
2. **Concurrency-safe stock holds** with row locks and an expiry job.
3. **Tests concentrated where money moves** (checkout integrity, STK auth, payment attempts, stock reservation, drops, tracking, receipts).
4. **A real differentiator:** Drops (sell-then-make with an autonomous lifecycle) plus bespoke with a sizing figure.
5. **Guest-friendly tracking** by cipher + phone.
6. **Images optimised to WebP at upload.**
7. **A clean, coherent Studio UI** that's mobile-usable.
8. **No horizontal overflow** at any tested width and no JS console errors on page load (prod build).

## Technical Debt

| Pri | Item |
|---|---|
| P1 | `custom_orders` overloading (type / status / items_json) and no variants. This is the foundation that blocks the product, cart and fulfilment work |
| P1 | Cart store API drift (`updateQuantity` not exported; id-keyed remove / update with multi-size lines) |
| P2 | Status vocabulary duplicated in `OrderStatus.php`, `OrdersPanel.vue`, `StudioDashboard.vue`, `TrackOrder.vue`, `DesignsVault.vue` |
| P2 | Oversized SFCs: `CustomOrder.vue` 643, `Shop.vue` 631, `About.vue` 572, `StudioDashboard.vue` 566, `Checkout.vue` 459, `ProductDetail.vue` 405 |
| P2 | Product page UI copy and config lives in `product.specifications.ui` JSON plus hard-coded hoodie / jogger defaults (business logic in UI) |
| P2 | `ThemeSetting::get()` runs one query per key; the shared `theme_settings` prop runs N+1 queries **on every Inertia request** |
| P2 | `Drop::getImageUrlAttribute` and `ThemeSetting::get` re-implement `PublicUrl`; the OG image in the blade uses `Storage::disk('public')` regardless of `public_disk` |
| P2 | `StudioController@index` loads all orders, products, gallery, leads and drops with relations, unpaginated |
| P2 | Unused: `BuyingModes.vue`, `gsap`, `@tailwindcss/vite`, `public/videos/heritage_loop.mp4` (5.9MB), `use App\Models\Order` (nonexistent class) in HomeController, invitation codes |
| P3 | Pint: 65 files unformatted; no static analysis, lint or CI |
| P3 | `env()` outside config in all 3 uncommitted prototypes |
| P3 | Inconsistent validation: custom-order `whatsapp_number` / measurements unbounded; drop reserve phone not validated; product `price` lacks `min:0` |

## UX Problems (customer-facing, verified)
1. **Cart −/+ crashes; Remove deletes every size; a 2-size bag can't check out** (P1).
2. Every product forces a Hoodie + Joggers two-size selection (P1).
3. The Checkout total and "Pay KSh X" label come from the stale localStorage price, so the shown price can differ from the charged price (P1).
4. Out-of-stock products remain purchasable (P1).
5. The CTA says "Buy Now" but adds to bag; a second floating add button skips size validation.
6. Empty "PRODUCT_SPECS 01–04" block; developer-style copy (`FABRIC_QUALITY`, `Commission Ready`, "Batch extraction limit reached", "System Error: Unable to sequence item to cart").
7. The Home page is ~14 screens long on mobile with repetitive category tiles; operational stats (studio load %) are shown to shoppers.
8. The Expedite fee is promised but never charged.
9. Footer legal links are dead (`#`).
10. The fake 3D-visualizer placeholder was already removed in the previous task.

## Mobile Problems
- Duplicate navigation: bottom nav (with cart) + header bag + hamburger.
- Before the uncommitted menu, **Custom, Track and About were unreachable on mobile** except via the footer.
- The mobile menu overlay covers the header and close button; Escape doesn't close it; no focus trap.
- The fixed bottom nav overlaps content and the product CTA area; the floating bag bubble overlaps "Buy Now".
- 10–21 interactive targets under 24px per page (WCAG 2.2 target-size).
- 2-column shop grid at 375px wraps prices onto two lines.
- A stray white bar renders before the logo at all widths.

## Security Risks
| Pri | Risk |
|---|---|
| **P0** | Seeded admin `password` credentials (P0-2) |
| **P0** | Studio can mark paid / overwrite status / edit price on paid orders without audit (P0-3; integrity more than access) |
| P1 | `ThemeController@update` accepts **any file type** for any key onto the public disk (admin-only stored XSS via SVG/HTML), plus arbitrary keys via `bulkUpdate` |
| P1 | No audit log of admin actions on orders and payments |
| P2 | `/newsletter/subscribe` unthrottled (spam / list pollution) |
| P2 | Custom-order upload allows PDF through `ImageOptimizer`; verify behaviour and size limits |
| P2 | Callback IP allowlist only enforced when configured; confirm `MPESA_ALLOWED_IPS` and `MPESA_CALLBACK_TOKEN` are set in production |
| P3 | `trustProxies(at: '*')`, fine behind a known proxy; confirm production topology |
| ✅ | Good: guest STK and status authorization, signed receipt URLs, tracking requires cipher + phone, throttles on money endpoints |

## Data Integrity Risks
1. P0-1 stranded payments.
2. P0-3 manual paid toggle bypasses settlement.
3. Payment-evidence cascade delete on order force-delete.
4. Payment / production status collision.
5. Free-text `type`.
6. Oversell from `in_stock` being ignored.
7. Duplicate successful payments are only logged, never surfaced for refund.
8. Closed-drop payments have no refund record.

## Performance Issues (measured)
| Rank | Bottleneck | Measured |
|---|---|---|
| 1 | Hard-coded PNGs on Home (`black_zip_up_hoodie_gray_bg.png`, `white_hoodie_silver_zipper.png`, plus `black_white_hoodies_showcase.png` in Hero / About) bypass the WebP pipeline | **2.2MB each; Home = 6.9MB total, 82 requests** |
| 2 | Material Symbols full variable icon font on every page | **1.1MB** |
| 3 | Only 2 of 40 `<img>` use `loading="lazy"` | — |
| 4 | Shared-props N+1 (`theme_settings`) + `Category::all()` on every request | ~10 queries per page |
| 5 | Studio loads all data on every tab | Fine now (~50 orders); grows linearly |
| 6 | `app.js` 132KB gzip includes animejs + vue-i18n globally | Acceptable |

Not bottlenecks: JS errors (none), layout shift from overflow (none), API chatter (polling only during payment).

## SEO Issues
- ✅ Server-rendered global `<title>`, description, OG and Twitter tags; `robots.txt` allows all.
- ❌ **OG and Twitter tags are site-wide only.** Every product and drop shared on WhatsApp or Instagram shows the generic hero preview, not the garment. This is the most important SEO / social gap for a WhatsApp-driven brand.
- ❌ No canonical, no `sitemap.xml`, no JSON-LD (`Product`, `Offer`, `Organization`, `BreadcrumbList`).
- ❌ Client `<Head>` adds a second meta description beside the server one (no `inertia` key on the server tag), and without SSR crawlers see the generic one.
- ❌ Multiple `<h1>` per page (2–3 on most pages).
- ⚠ 5 product / hero images without `alt` on Home, 3 on the product page.
- ✅ Clean slugged product URLs (`/shop/{slug}`, `/drops/{slug}`).

## Accessibility Issues (WCAG 2.2 AA)
| Issue | Evidence |
|---|---|
| Text below legible size | 20–49 visible text nodes under 11px per page, down to 7px |
| Unlabelled form fields | checkout 6, custom order 12, contact 5, login 3 |
| Target size (2.5.8) | 10–27 targets under 24px per page |
| No skip link; first Tab lands on the logo, then 7+ nav links | Verified |
| Mobile menu: no Escape, no focus trap, close control hidden under the overlay | Verified |
| Reveal animations hide content (`opacity:0`) until JS intersection | Content invisible if JS fails; the uncommitted reduced-motion CSS shortens CSS transitions but **does not cover animejs** |
| Focus visibility | Browser default outline present (2–3px); uncommitted global `focus-visible` outline improves it |
| Images without `alt` | 5 (Home), 3 (product), 1 (checkout) |
| Contrast | Low-contrast grey body copy on black (e.g. Home "Built by hand" paragraph, footer micro-labels); needs a token-level contrast pass |
| Buttons vs links | Mostly correct; size buttons have no `aria-pressed`; gallery arrows unlabelled |
| Heading structure | Multiple `<h1>` |

## Dead / Orphaned / Experimental Code
| Item | Kind |
|---|---|
| `/cart` route → missing `Cart.vue` | Dead route (500) |
| `BuyingModes.vue` | Unused component |
| `gsap`, `@tailwindcss/vite` | Unused dependencies |
| `public/videos/heritage_loop.mp4` (5.9MB) | Unreferenced asset |
| `use App\Models\Order` in HomeController | Import of a nonexistent class |
| `POST /my-vault/invite` + `users.invitation_code` | Feature without a consumer |
| `/studio/catalog` GET | Duplicate of `/studio` |
| `Product::xray_specs` accessor (fake default specs: "Thermal Regulation Coating", "Water-Resistant Membrane") | Fabricated product claims shipped to the client |
| `StripeCheckoutService`, `CurrencyConverter`, `ProcessAIStyleRecommendation`, `plugins/i18n.js` | Uncommitted prototypes |

## Missing Capabilities
- Product variants / size-level stock; real garment types (dress, suit, accessory with no size).
- An order-items table; fulfilment state for shop orders; a shipping / dispatch step and customer notifications.
- Online payment for bespoke quotes (deposit / balance); refunds for unfunded drops and duplicate payments.
- Legal pages (privacy and terms are a requirement when collecting PII and payments under Kenya's Data Protection Act; confirm with counsel).
- Per-product social previews, a sitemap and structured data.
- Linking guest orders to accounts; wishlist (only if wanted).
- An admin attention queue that covers every money-at-risk state.

---

# DELIVERABLE 2 — PRIORITISED BACKLOG

| ID | Pri | Area | Problem | Root cause | Recommendation | Effort | Risk | Deps |
|---|---|---|---|---|---|---|---|---|
| B-01 | **P0** | Payments | Paid retry after `Payment Failed` never reconciled (P0-1) | `initiate` doesn't reset status; reconciler status filter | On a successful STK push for a non-paid order, set status back to `Pending Payment`; reconcile pending attempts by **attempt** status regardless of order status (except Paid); add a regression test | S | Low | — |
| B-02 | **P0** | Security | Seeded admin password `password` (P0-2) | `AdminSeeder` | Rotate production admin credentials now (ops); make the seeder refuse to run outside `local` / `testing` and read the password from env | S | Low | Ops check |
| B-03 | **P0** | Admin / payments | Manual paid toggle, status overwrite, price edit on paid orders (P0-3) | Free-form `StudioController@update` | Remove the raw `is_paid` toggle; route "mark paid manually" through `OrderSettlementService::markPaid` with a reason; lock `price_quoted` once paid; validate status against the allowed set | M | Med | B-06 ideal, can precede |
| B-04 | P1 | Cart | −/+ crash, remove-all-sizes, 2-size checkout 422 | Store API drift; id-keyed lines; `distinct` rule | Line-key cart items (`id + options`); export `updateQuantity`; checkout merges or accepts duplicate ids with distinct options; Vitest-free unit coverage via a PHP test for checkout | S–M | Low | — |
| B-05 | P1 | Inventory | `in_stock=false` still purchasable | Flag only read on Home | Enforce in Shop query, product page CTA state and checkout validation | S | Low | — |
| B-06 | P1 | Data model | Payment and production share `status`; free-text `type` | Single overloaded table | Add `payment_status` + `fulfilment_status` (or `production_status`), normalise `type` to (`standard`, `bespoke`, `drop`) + `garment` field; migrate existing rows | M–L | **High** (touches money path) | B-01, B-03 |
| B-07 | P1 | Catalogue | Every product forced to a hoodie + jogger set; no admin control | Enum + missing admin field | Introduce a size / option model (product option groups, or a simple `size_scheme`: none / single / top+bottom) with admin control; drive the product page from it | M–L | Med | B-04 |
| B-08 | P1 | Checkout | Displayed price from stale localStorage | Cart never revalidated | Fetch server prices for the bag on drawer / checkout load; show server total; warn on change | S | Low | B-04 |
| B-09 | P1 | Admin | Attention list misses money-at-risk states and fulfilment | Narrow query | Attention queue: Amount Mismatch, duplicate paid attempts, paid-not-fulfilled, new briefs, closed drops with paid reservers | S–M | Low | B-06 helps |
| B-10 | P1 | Drops | Unfunded drop: paid customers, no refund path | Missing workflow | Business decision on policy, then a Studio refund queue + customer notice; block STK when the drop is not open | M | Med | Business decision |
| B-11 | P1 | Legal | Privacy / Terms / Shipping links dead | Missing pages | Add content-managed legal pages (content from the owner) | S | Low | Owner content |
| B-12 | P1 | Ops | Unknown production scheduler / queue | No deploy config in repo | Confirm cron `schedule:run` and a queue worker; document in the README; add a health check | S | Low | Ops |
| B-13 | P1 | Security | Theme upload accepts any file / key | No validation | Whitelist keys; `image` + mime validation for asset keys | S | Low | — |
| B-14 | P1 | Data integrity | Payment attempts cascade on force-delete | FK cascade | Disallow force-delete of orders with paid attempts, or `restrictOnDelete` | S | Low | — |
| B-15 | P2 | Bespoke | Expedite fee promised, never charged | No pricing path | Business decision: drop the fee copy, or record it on the order and quote it | S | Low | Decision |
| B-16 | P2 | Performance | 6.9MB Home; 2.2MB PNGs; 1.1MB icon font | Static assets outside the pipeline | Convert to responsive WebP / AVIF; subset icons (or inline SVG set); lazy-load below-fold images | S–M | Low | B-19 (design may change assets) |
| B-17 | P2 | SEO | Generic social previews for products / drops; no sitemap / JSON-LD / canonical | Global-only blade meta | Per-page server meta via Inertia shared `meta` prop rendered in the blade; Product JSON-LD; sitemap | M | Low | — |
| B-18 | P2 | Code quality | Status vocabulary duplicated in 5 places | No shared source | Expose status metadata from PHP (labels, groups) via a shared prop; delete JS copies | S | Low | B-06 |
| B-19 | P2 | Design system | 3 accents, 5 button styles, 7–9px text, snake_case copy | Organic growth | Define tokens + primitives (Button, Input, Field, Badge, Price, Section, ProductCard) and type scale (min 12px body labels, 14–16px body) | M | Low | — |
| B-20 | P2 | Accessibility | Unlabelled inputs, small targets, no skip link, menu without Escape / focus trap, animejs ignores reduced motion | — | Fold into the design-system primitives + shell work | M | Low | B-19 |
| B-21 | P2 | Performance | Shared-props N+1; Studio loads everything | Accessor design | Cache settings (single query, cache bust on save); paginate / lazy-load Studio tabs | S–M | Low | — |
| B-22 | P2 | Code quality | Oversized SFCs (6 over 400 lines) | — | Split during the redesign of each screen, not as a standalone refactor | — | — | Phase-aligned |
| B-23 | P3 | Hygiene | Dead route `/cart`, unused component / deps / video, bad import, fake `xray_specs` defaults | — | Remove | S | Low | — |
| B-24 | P3 | Tooling | No lint / static analysis / CI; Pint drift | — | Add a Pint config + run once (separate formatting-only commit); add Larastan level 5 later; CI running tests + build | S | Low | — |
| B-25 | P3 | Account | Guest orders not linked after registration | — | Link by verified email / phone on registration | S | Low | — |

---

# DELIVERABLE 3 — TARGET EXPERIENCE

**One coherent premium fashion-commerce product.** Editorial serif display + clean sans body; photography leads; cyan `#57d5fc` as a single, restrained accent (per owner decision); dark-first with an equally finished light theme; quiet borders, generous whitespace, no dev-console copy.

- **Storefront shell:** one header (logo, Shop, Drops, Bespoke, Track, search optional, bag). On mobile, a compact header with bag + menu *or* a bottom bar, **never both**. A real drawer with focus trap and Escape. Footer with working legal / help pages.
- **Product discovery:** Home is a short, image-led story: hero → live drop → new arrivals → 3–4 curated collections → bespoke invitation → newsletter (once). Shop has server-side sort / filter, in-stock first, honest sold-out states, 2-up mobile grid with single-line prices.
- **Product page:** large gallery (swipe + thumbnails), name / price / short description first, **size options driven by the product's real size scheme** (none / single / top+bottom), stock honesty ("2 left"), one primary CTA "Add to bag" in brand cyan, delivery and returns summary, WhatsApp fit help, related pieces. No fabricated specs.
- **Cart:** reliable line-level quantity / remove, server-verified prices, clear total, single "Checkout" CTA.
- **Checkout:** one page with a progress indicator, labelled fields, server-confirmed order summary, M-Pesa as the familiar trusted step (green confined to the M-Pesa badge / button), resilient retry, and a confirmation page with cipher, receipt and "track your order".
- **Account:** My Orders across shop, bespoke and drops (guest orders linked), status timelines driven by one shared status vocabulary.
- **Bespoke:** a guided multi-step brief (Piece → Fit → Fabric → Inspiration → Contact) with a save-and-send summary; quote → online deposit via M-Pesa (if the business wants it); progress photos.
- **Drops:** clear campaign page (progress, deadline, sizes, "made only if funded" plus an explicit refund promise), and automated notifications on greenlit / closed.
- **Admin / Studio:** an attention queue for every money-at-risk and action-needed state; separate payment and fulfilment status; settlement-safe actions; paginated lists; settings with validated inputs.

# DELIVERABLE 4 — TARGET ARCHITECTURE

| Area | Current | Target | Verdict |
|---|---|---|---|
| Payment rail (M-Pesa, settlement, reconciliation, attempts) | Strong | Same + P0-1 fix + admin settlement path | **KEEP** (patch) |
| Stock holds | Per-product holds | Per-variant holds (same mechanism) | **IMPROVE** |
| Order model | `custom_orders`, overloaded `type` / `status`, JSON items | `custom_orders` kept as the orders table (no rename) + `payment_status`, `fulfilment_status`, normalised `type`; optional `order_items` later | **REFACTOR** (incremental, migrated) |
| Product model | enum `garment_type`, default `batch_limit` 15 | Size scheme / option groups with admin control; explicit stock | **REFACTOR** |
| Cart | Pinia + localStorage, id-keyed | Line-keyed, server-revalidated | **IMPROVE** |
| Shared props / settings | N+1, arbitrary keys | Cached settings service, whitelisted keys | **IMPROVE** |
| Status vocabulary | 5 copies | PHP source of truth shared to the client | **REFACTOR** |
| UI | Page-local styling, 6 SFCs over 400 lines | Design-system primitives + page sections | **REFACTOR** (per phase) |
| Studio | One page, all data | Tabbed with lazy / paginated data, attention queue | **IMPROVE** |
| SEO | Global meta | Per-page server meta + JSON-LD + sitemap | **INTRODUCE** |
| Tooling | PHPUnit only | + Pint config, CI, later Larastan; browser smoke tests (Playwright) for checkout | **INTRODUCE** |
| Stripe, multi-currency, AI concierge | Unwired prototypes | — | **REMOVE** now; revisit on a business case |
| i18n | 2 strings | English only | **REMOVE** now; **INTRODUCE LATER** if markets justify it |
| Size guide | WhatsApp link | Real size chart per size scheme | **INTRODUCE LATER** (with the product model) |

### Experimental features review
| Feature | Decision | Why |
|---|---|---|
| **Stripe** | **REMOVE** (needs a business decision to revive) | Unwired, SDK missing, `env()` at runtime, no webhook / settlement; no evidence of international demand; KSh / Kenya-only throughout |
| **Multi-currency** | **REMOVE** | `CurrencyConverter` assumes a USD base while the catalogue is KSh; its fallback silently charges unconverted amounts; the existing admin `currency` symbol setting only changes the *label*, so it should also be removed or locked to KSh |
| **i18n** | **REMOVE now, KEEP FOR LATER as a concept** | 2 strings, no switcher, no RTL for Arabic; adds bundle weight; copy is in flux before the redesign |
| **AI style concierge** | **REMOVE** (needs a business decision) | Never dispatched; discards its output; needs a self-hosted Ollama the infra doesn't have; tone conflicts with the brand direction |
| **Size guide** | **REDESIGN** | The need is real (fit is central to bespoke and drops), but it should be a size chart tied to the new product size scheme; keep the WhatsApp fit-help link meanwhile |

---

# DELIVERABLE 5 — MASTER IMPLEMENTATION ROADMAP

Ordered by dependency. Each phase is shippable on its own and ends with tests + build + a browser check at 375 / 768 / 1440.

| Phase | Goal | Contents (backlog IDs) | Exit criteria |
|---|---|---|---|
| **0 · Stabilise working tree** | Clean baseline | Apply the working-tree recommendations below; commit in small coherent commits; `/cart` removed (B-23 part) | Tree clean; tests + build green |
| **1 · Integrity & security** | No money or data at risk | B-01, B-02, B-03, B-05, B-13, B-14, B-12 (ops confirmation) | Regression tests for each; P0 list empty |
| **2 · Foundations** | Model the business correctly | B-04 (cart lines), B-08 (price revalidation), B-06 (status split + type normalisation), B-07 (size scheme / options), B-18 (shared status vocabulary), B-21 (settings cache), B-24 (Pint config + CI) | Migrations reversible and tested; existing orders migrated; checkout and payment tests still green |
| **3 · Design system** | One visual language | B-19 tokens + primitives, type scale, button / input / field / badge / price / product-card, motion rules incl. reduced motion, B-20 a11y baked in | Primitives documented on a style page; no new off-token colours |
| **4 · Storefront shell** | Navigation & layout | Header, single mobile nav pattern, drawer behaviour, footer + legal pages (B-11) | Keyboard and screen-reader pass; no duplicate nav |
| **5 · Home / discovery / shop** | Shorter, image-led discovery | Home recomposition, server-side sort / filter, honest stock states, image pipeline (B-16) | Home under 1.5MB; Shop sort server-side |
| **6 · Product experience** | Convert | Product page on the new size scheme, gallery, related pieces, honest specs (remove `xray_specs` fakes) | No forced two-size UI; one CTA |
| **7 · Cart & checkout** | Trustworthy payment | Drawer + checkout redesign on primitives, server-confirmed totals, retry UX | Browser test: add → checkout → STK (sandbox) |
| **8 · Confirmation / tracking / account** | Post-purchase | Confirmation page, tracker timeline from the shared status, My Orders, guest-order linking (B-25), shipped notifications | Status shown consistently everywhere |
| **9 · Bespoke** | Guided brief | Multi-step brief, fixed image URLs, Expedite decision (B-15), optional quote deposit (decision) | Brief completion on mobile in under 3 min |
| **10 · Drops** | Campaign trust | Refund policy + queue (B-10), STK guard when closed, notifications | Closed-drop path tested |
| **11 · Studio** | Operate from one queue | Attention queue (B-09), fulfilment view, pagination, settlement-safe actions, audit log | Owner can answer every Section-15 question |
| **12 · Performance / SEO / accessibility** | Polish & reach | B-17 per-page meta / JSON-LD / sitemap, icon subsetting, Lighthouse and axe pass | Product share shows product image; axe critical = 0 |
| **13 · International commerce** | *Only if justified* | Business case → currency, shipping, card rail | Explicit go decision |
| **14 · Intelligent features** | *Only if justified* | e.g. fit recommendations from the measurements already collected | Explicit go decision |

---

# WORKING TREE RECOMMENDATION (not executed)

Classification: **A** required fix · **B** valid UX improvement · **C** incomplete feature · **D** experiment / prototype · **E** possibly dead · **F** business decision.

| File | Class | Recommendation | Reason |
|---|---|---|---|
| `.env.example` | A + D | **ADJUST** | Keep the MariaDB revert (matches ddev); remove the Stripe / forex / AI blocks with their prototypes |
| `package.json` / `package-lock.json` | D | **REVERT** | `vue-i18n` only serves the i18n prototype; revert together with `app.js` and `plugins/i18n.js` |
| `resources/js/app.js` | D | **REVERT** | i18n plugin registration |
| `resources/js/plugins/i18n.js` | D | **REVERT** (delete) | 2 strings, no switcher, no RTL |
| `resources/js/Pages/CustomOrder.vue` | D | **REVERT** | Only remaining change is `$t()` swaps (the visualizer is already gone); restores "Private Commission" copy and keeps the build free of vue-i18n |
| `resources/css/app.css` | B | **ADJUST** | Keep reduced-motion + `focus-visible`; drop the global `a, button, input… { transition-all }` (animates layout properties site-wide and fights component transitions) |
| `resources/js/Components/TopNavBar.vue` | A + B | **ADJUST** | Keep the scroll-listener fix (real leak at HEAD) and the mobile menu (only mobile route to Custom / Track / About), but add Escape / focus trap and a visible close control; deduplicating with `MobileBottomNav` belongs to Phase 4 |
| `resources/js/Components/CartDrawer.vue` | B | **DEFER** | Trust tiles in the empty bag use 8px text and a "Style help" tile that links nowhere; revisit in the Phase 7 drawer redesign |
| `resources/js/Pages/Home.vue` | D | **REVERT** | Generic SaaS-style "Bespoke fit / Fast styling / Secure checkout" card conflicts with the brand direction and makes "friction-free" claims while the cart is broken |
| `resources/js/Pages/ProductDetail.vue` | A + B | **KEEP** (minor ADJUST) | Fixes real HEAD bugs (undefined `currentIndex` gallery dots; 17 hard-coded `text-white` unreadable in light mode); add-to-bag toast is good. The 8px trust-badge labels should become ≥ 11px when touched next |
| `resources/js/Pages/Shop.vue` | B | **KEEP** | `clearFilters` preserves sort and avoids a `category=all` param; better empty state |
| `app/Services/StripeCheckoutService.php` | D / E / F | **REVERT** (delete) | Unwired, SDK missing, `env()` at runtime; not justified by the current business |
| `app/Services/CurrencyConverter.php` | D / E / F | **REVERT** (delete) | Wrong base currency; silent unconverted fallback |
| `app/Jobs/ProcessAIStyleRecommendation.php` | D / E / F | **REVERT** (delete) | Never dispatched; discards results; no AI infra |

> Deleting the three prototypes loses nothing that git history would otherwise keep, because they were never committed. If you want them preserved, move them to a `prototypes/` branch first.

---

# NEXT TASK (recommended, not started)

**TASK:** B-01: close the paid-retry reconciliation gap (P0-1).

**WHY FIRST:** It's the only finding where a customer's money can be taken and the order never settles, with no alert to anyone. It's a small, contained change inside the best-tested part of the codebase, so the risk is low. It has no dependency on the data-model or design work. (B-02 is an ops action, rotating production admin credentials, and should happen today in parallel. Its code half, hardening the seeder, is a five-minute follow-on.)

**FILES LIKELY AFFECTED:**
- `app/Http/Controllers/MpesaController.php`: on a successful STK push for an unpaid order in `Payment Failed`, conditionally set status back to `Pending Payment`.
- `app/Console/Commands/ReconcilePendingPayments.php`: select by pending **attempts** on any unpaid order (not only `Pending Payment` / `Expired`).
- `app/Console/Commands/ReleaseExpiredReservations.php`: confirm the expiry rule still spares orders with recent pending attempts after the status reset.
- `tests/Feature/PaymentAttemptTest.php` (new cases).

**DEPENDENCIES:** None. Phase 0 (working-tree clean-up) should ideally land first so the commit is isolated, but it isn't technically required.

**ACCEPTANCE CRITERIA:**
1. An order whose first attempt failed (status `Payment Failed`), then retried, then paid with the callback lost, is settled by `mpesa:reconcile`: `is_paid=true`, status `Paid`, reservations committed, receipt event fired exactly once.
2. A failed retry on such an order leaves it `Payment Failed` with holds released.
3. Already-paid orders are never re-settled; `Expired` orders keep current behaviour.
4. All 83 existing tests still pass; the build is unaffected.

**TESTS:**
- *New:* `test_reconciliation_settles_paid_retry_after_payment_failed`.
- *New:* `test_retry_initiation_resets_payment_failed_to_pending`.
- *New:* `test_failed_retry_keeps_order_failed_and_releases_holds`.
- *Run:* `ddev exec php artisan test`, `npm run build`.

---

## Business decisions needed (cannot be derived from the code)

1. **Production operations:** is `schedule:run` (cron) running every minute, and is a queue worker running? Have seeders ever run in production?
2. **Unfunded drops:** what's the refund policy (automatic M-Pesa reversal, manual refund, or store credit)?
3. **Bespoke payments:** should quotes be paid online (deposit + balance via M-Pesa), or stay offline via WhatsApp?
4. **Expedite fee (+KSh 1,500):** real or remove?
5. **Catalogue scope:** which garment / size schemes do you sell? (Single size S–XXL; top + bottom sets; one-size accessories; made-to-measure only.)
6. **International customers:** any real demand or plans? This decides whether Phases 13–14 exist at all.
7. **Legal content:** who supplies the privacy policy, terms, and shipping / returns text?
