# SERANA-CLOSET — Real-User QA Issue Register

**Date:** 2026-10-16
**Method:** a scripted real Chrome browser driving the running app (ddev) as a shopper, a bespoke client, a registered customer and the Studio owner, at phone (375–430px) and desktop (1440px) widths in dark and light themes. Supporting work:
- **Accessibility:** axe-core scans.
- **Network:** throttled-network and page-weight measurement.
- **Server cost:** per-request query counts.
- **Code reading:** every finding below was traced to the line responsible.

**How the payment tests worked:** checkout, bespoke, tracking and Studio ran against the real backend. M-Pesa was the only thing simulated: the STK call was mocked in the browser, and Safaricom-shaped callbacks were posted to the local callback endpoint. Settlement, stock, receipts, the queue and mail (caught by Mailpit) were all real. Nothing external was contacted.

**Tree under test:** the ddev site serves the working tree: `design/novatek-layout-system` (= release candidate `master@f06e895` + uncommitted layout work), plus Home edits made by another tool during this session. Each issue notes whether it also exists in the release candidate (**RC**).

**Severity:**
- **P0:** money, data or security loss, or an impossible purchase.
- **P1:** a core workflow broken, or a real risk to money or orders.
- **P2:** important; customers or staff are confused, or it could fail.
- **P3:** polish or minor.

**Type:**
- **BROKEN:** doesn't work.
- **GAP:** missing piece.
- **UNCLEAR:** confusing copy or state.
- **RISK:** latent failure.
- **DELAY:** slowness.
- **COMPLEXITY:** more moving parts than needed.
- **OPS:** a production requirement.

No P0 was found. Each reference below (SC-001 and so on) is stable for later work.

## Fix status (2026-10-16, branch `design/novatek-layout-system`, uncommitted)

**Evidence for every FIXED item:**
- **PHP:** 130 tests pass (477 assertions). New suites: `ArchivedOrderPaymentTest`, `StudioFulfilmentTest`, `ThemeSettingCacheTest`, plus supersede tests in `SubmissionRetryTest`.
- **Cart:** 8 frontend tests pass.
- **Build:** `npm run build` is clean.
- **Browser:** the affected flows were re-run.
- **Accessibility:** axe-core reports 0 WCAG 2.2 AA violations on 12 pages at 390px and 1440px, in dark and light themes.

| Ref | Status | What changed |
|---|---|---|
| SC-001 | FIXED | Payment lookups, the callback, reconciliation and settlement include archived orders. A paid callback restores the order. Studio refuses to archive while a prompt is in progress. |
| SC-002 | FIXED | Permanent delete is refused for paid orders or any order with payment attempts. Unpaid orders with no payment history can still be purged. |
| SC-003 | FIXED | Tracking finds archived orders. |
| SC-004 | FIXED | Checkout remembers the order for 30 minutes, tied to the bag's contents. A refresh shows **Resume Payment / Start Over** instead of creating a duplicate (browser: orders 1 → 1). |
| SC-005 | FIXED | A new checkout expires this shopper's abandoned unpaid orders and releases their holds. Orders with a prompt in progress are kept. |
| SC-006 | FIXED | Amount Mismatch replaces the pay button with **Track This Order** and **Contact Support**. |
| SC-007 | FIXED | Amount Mismatch can no longer be overwritten by a later failed prompt. |
| SC-008 | FIXED | Studio Attention lists orders paid more than once as "Paid N times: refund owed". |
| SC-009 | FIXED | Studio has **Record payment** (amount and M-Pesa reference) for bespoke orders. It is audited in internal notes, can only be done once, and keeps the production stage. |
| SC-010 | FIXED | Price must be at least KSh 1. |
| SC-011 | FIXED | The editor lists the items to pack (name, size, quantity, price). The orders list shows real item names. |
| SC-012 | FIXED | The editor has a "Deliver to" block. **Copy brief** includes items, sizes and address. |
| SC-013 | FIXED | Shows the WhatsApp number when no phone is stored, with **Message** and email links. |
| SC-014 | FIXED | A payment block replaces the automatic-payment claim on bespoke orders. |
| SC-015 | DEFERRED | 36 queries is fine at current volume. Paginate or lazy-load tabs once orders pass a few hundred. |
| SC-016 | FIXED | The checkbox explains what it does. Customizable products show **Request a Custom Version**. |
| SC-017 | FIXED | "Use whole shillings, e.g. 1500. M-Pesa cannot charge cents." |
| SC-018 | FIXED | `batch_sold` must be zero or more. |
| SC-019 | FIXED | Studio, tracking and Your Orders all say **Payment Pending**, from one shared status helper. |
| SC-020 | FIXED | "+" is disabled at the cap with "That's all we have of this piece right now." |
| SC-021 | FIXED | The picker offers only JPG, PNG, WEBP and PDF, and the server now accepts WEBP. The message explains the iPhone fix. 413 errors explained. |
| SC-022 | FIXED | Sign Out added to the desktop header, the mobile menu and the account page (browser: logs out). |
| SC-023 | FIXED | Receipt email and PDF show "Size XL" / "Top M · Bottom L". |
| SC-024 | FIXED | Customer-facing dates use `APP_DISPLAY_TIMEZONE` (Africa/Nairobi). Storage stays UTC. The paid date comes from the payment, not the last edit. |
| SC-025 | FIXED | Branded reset email ("Reset your Serana Closet password"). |
| SC-026 | FIXED | Your Orders sends only the fields it shows. |
| SC-027 | FIXED | Decorative stepper removed. |
| SC-028 | FIXED | Sort is kept in `?sort=` (browser: survives reload). |
| SC-029 | FIXED | Renamed **Ask about fit**. |
| SC-030 | **INVALID** | Name and message were already `required`. The finding came from reading the wrong lines. A 5-character minimum was added to the message. |
| SC-031 | DEFERRED | The server reprices at checkout and the review shows the true total. A refresh when the bag opens is optional polish. |
| SC-032 | FIXED | Gallery images have alt text. |
| SC-033 | FIXED | The bottom nav is always dark, with readable labels. |
| SC-034 | FIXED | Light-mode accent darkened to `#07617f` (≥ 4.5:1). |
| SC-035 | FIXED | Faded labels now use readable tokens. M-Pesa green is darkened in light mode. |
| SC-036 | FIXED | The gallery is keyboard-focusable (region with label). |
| SC-037 | FIXED | Small targets enlarged. The hidden desktop Shop dropdown is now `invisible` (not just transparent), so screen readers and touch can't reach it. |
| SC-038 | FIXED | The icon font is subset to the 79 glyphs in use: **28KB instead of 1.1MB**. Product-spec icon overrides fall back to a shipped glyph. Adding a new icon means adding its name in `app.blade.php` (comment there). |
| SC-039 | FIXED | About uses a WebP: **40KB instead of 1.9MB**. The PNG is kept in `public/images` but no longer referenced. |
| SC-040 | FIXED | Settings load once per request or job, and refresh on write (scoped container binding). |
| SC-041 | NOT TOUCHED | Another tool owns Home. Findings stand for that work. |
| SC-042 | DOCUMENTED | Default left as `s3`. Production storage isn't verified, and changing the default could silently move uploads to local disk. `.env.example` now says to always set it explicitly. |
| SC-043 / SC-044 | DOCUMENTED | Queue worker and every-minute cron documented in `.env.example`. Still needs to be set up on the host. |
| SC-045 | DOCUMENTED | Upload limits documented in `.env.example`. Still needs to be set on the host. |
| SC-046 | DOCUMENTED | Brand sender with SPF, DKIM and DMARC documented. DNS still needs setting up. |
| SC-047 | OPEN | Commit, review and push are the owner's decision. |
| SC-048 | FIXED | `.github/workflows/ci.yml` runs build, PHP tests and frontend tests. Pint is not yet enforced. |
| SC-049 | FIXED | Removed `LoginModal`, `AuthenticatedLayout` and the 4 components only it used (`Dropdown`, `DropdownLink`, `NavLink`, `ResponsiveNavLink`), the `/my-vault/invite` route and method, `laravel/sanctum`, and `@tailwindcss/container-queries`. The `invitation_code` column stays (no destructive migration). |
| SC-050 | FIXED | `.env.example` now includes `APP_DISPLAY_TIMEZONE`, `WHATSAPP_NUMBER`, `DROPS_ENABLED`, the queue/cron note and the upload limits. |
| SC-051–054 | BUSINESS | These need the owner's content and decisions. They can't be written in code. SC-054 is unblocked by SC-009's Record payment. |
| SC-055 | OPEN (P1, guarded) | Found 2026-10-21 while testing Phase E races. The ledger is read with plain reads after the order lock. Safety comes from MariaDB 11.6+ `innodb_snapshot_isolation=ON` aborting a stale locking read (1020, retried). With it off, `ConcurrencyTest` shows two refunds, two late payments and the callback/reconciler pair all going through. **Guard:** `serana:preflight` now blocks a deploy when the setting isn't on, and a test pins it on. **Fix:** make the ledger and hold reads under the lock locking reads, so the setting stops mattering. Until then, production must run MariaDB ≥ 11.6. |

**New findings during fixing:**
- `composer` reports 33 security advisories across 8 packages. Run `composer audit` and update before launch.
- Daraja's STK timestamp uses `now()` in UTC. Safaricom expects EAT. It works in sandbox, but verify it during the controlled live test.

---

## A. Payments and order integrity

| ID | Sev | Type | RC | Issue | Evidence (how to reproduce) | Expected |
|---|---|---|---|---|---|---|
| **SC-001** | P1 | BROKEN | yes | **Archiving an order while its payment is in progress detaches the payment.** | Live: checkout → STK sent → Studio › Orders › order › **Archive** (no warning) → customer pays → callback answered `Order Not Found`; order stays *Pending Payment*; `mpesa:reconcile` leaves the attempt `pending`. Cause: `PaymentAttempt::order()` and the callback / reconciler lookups skip soft-deleted orders (`MpesaController@callback`, `ReconcilePendingPayments::targets`); `StudioController@destroy` has no guard. Repro ref `SRN-8E8543DB-2026`. | Archive refused for unpaid orders with a pending attempt and for paid orders, **and** callback/reconcile still settle soft-deleted orders. |
| **SC-002** | P1 | BROKEN (data loss) | yes | **"Delete" permanently erases payment evidence**, even for paid orders. | Live: `payment_attempts` for the order went 1 → 0 after Studio force-delete. FK `payment_attempts.custom_order_id` is `cascadeOnDelete`. | Permanent delete refused while any attempt or payment exists (or attempts retained). |
| **SC-003** | P1 | BROKEN | yes | **Archived orders (including paid) vanish from Track Order.** | Live: tracking with correct reference + phone → "We couldn't find an order…". `OrderTrackingController@lookup` excludes trashed rows. | Customer can always find their order. |
| **SC-004** | P1 | BROKEN | yes | **Refreshing or returning during checkout creates a duplicate order.** | Live: Review Order & Pay → refresh → Review Order & Pay again → orders 1 → **2**. Checkout does send a `request_id`, but it is generated fresh on every page load (`crypto.randomUUID()` in `Checkout.vue`) and `orderId` lives only in page memory. So it protects against a double click, not a refresh / return / new tab, which arrives as a brand-new request. *(Corrected 2026-10-16: an earlier draft said no `request_id` was sent; that was a truncated search.)* | Same pending order resumed after refresh / back / reopen; no duplicate. |
| **SC-005** | P1 | BROKEN | yes | **On a last-unit item, the customer is blocked by their own abandoned order** for the 15-minute hold. | Live (limit = 1 unit): checkout → refresh → pay again → "Insufficient stock for: ZZ Audit Bespoke Set — only **0** available (requested: 1)". | Customer's own pending order resumed (fixes with SC-004); message aimed at the customer, not raw stock maths. |
| **SC-006** | P2 | UNCLEAR | yes | After **Amount Mismatch**, the checkout still offers "Resume Payment"; pressing it is refused (409, console error). | Live: mismatch → "Payment needs review…" + Resume Payment button → STK 409 "needs studio review". | No pay action for mismatch / expired orders; show support / track actions only. |
| **SC-007** | P2 | RISK | yes | A later failed attempt overwrites `Amount Mismatch` with `Payment Failed`, re-enabling prompts and dropping the Studio flag. | Code: `OrderSettlementService::markUnpaid` only guards `is_paid=false`. Needs two concurrent prompts. | Mismatch is sticky until staff review. |
| **SC-008** | P2 | GAP | yes | A customer paying twice is only logged ("refund may be owed"); **no Studio view of refunds owed.** | Code: `MpesaController@callback` already-paid branch. | Studio attention item for duplicate paid attempts. |
| **SC-009** | P2 | GAP | yes | **Bespoke orders can never be marked paid.** Studio can't record a WhatsApp / offline payment (`is_paid` locked), so briefs stay "Quote Pending" forever; after a quote is saved, tracking still says "The studio will review your brief and confirm pricing". | Live: quote KSh 4,500 saved → tracking "Quote Pending KSh 4,500 … will confirm pricing". | Business decision: how bespoke payment is recorded (e.g. staff "record offline payment" with reference, or an STK request from the quote). Copy changes once quoted. |
| **SC-010** | P2 | RISK | yes | **A KSh 0 product can be created** (`price` `min:0`; the empty form only flagged name / description) and ordered, but can never be paid ("Order has no payable amount"). | Code + live validation run. | Price ≥ 1. |

## B. Studio (fulfilment and admin)

| ID | Sev | Type | RC | Issue | Evidence | Expected |
|---|---|---|---|---|---|---|
| **SC-011** | P1 | BROKEN | yes | **Shop order editor doesn't show what was ordered.** No product names, sizes or quantities; every order shows "Item: **Custom piece**". | Live screenshot of paid order `SRN-390F04E5-2026` (hoodie, XL). Template reads `garment_name || outfit_type || 'Custom piece'`, fields that don't exist (`StudioDashboard.vue:332`). | Line items with name, size(s), qty, price. |
| **SC-012** | P1 | BROKEN | yes | **Shop order editor has no delivery address or city.** Staff can't dispatch without the database. "Copy brief" builds from the same missing fields. | Live screenshot; template never renders `address` / `city`. | Delivery block + copy-ready dispatch text. |
| **SC-013** | P1 | BROKEN | yes | **Bespoke order editor hides the customer's WhatsApp number** (it renders `phone`; bespoke stores `whatsapp_number`) and every brief field: location, occasion, deadline, fit, fabric, colour, notes. No "message customer" action. | Live screenshot of brief `SRN-3B246502-2026`. | Full brief + WhatsApp contact button. |
| **SC-014** | P2 | UNCLEAR | yes | Bespoke editor says "Payment confirmation is managed automatically", which isn't true for bespoke. | Live screenshot. | Correct per order type (see SC-009). |
| **SC-015** | P2 | COMPLEXITY / DELAY | yes | Studio loads **every** order, product, lead and drop on each visit (36 queries); no pagination. Fine at today's volume, but degrades linearly. | Query count run; `StudioController@index`. | Paginate / lazy-load tabs post-V1. |
| **SC-016** | P3 | UNCLEAR | yes | "Customizable" product checkbox only adds a "Bespoke" badge; the customer flow is identical, and nothing explains what it means. | Live product created + viewed. | Define meaning or remove. |
| **SC-017** | P3 | UNCLEAR | yes | Fractional price error reads "The price field format is invalid." | Live. | "Use whole shillings, e.g. 1500." |
| **SC-018** | P3 | RISK | yes | `batch_sold` accepted unbounded (incl. negative) by the product update endpoint (not in the form). | Code `CatalogController@updateProduct`. | `min:0` or not writable. |
| **SC-019** | P3 | UNCLEAR | yes | Studio badge "Awaiting payment" vs customer "Payment Pending"; same states, different words. | `OrdersPanel.vue` vs `orderStatus.js`. | One vocabulary. |

## C. Storefront and customer experience

| ID | Sev | Type | RC | Issue | Evidence | Expected |
|---|---|---|---|---|---|---|
| **SC-020** | P2 | UNCLEAR | yes | **Bag "+" at the stock cap silently does nothing.** No message, button stays enabled. | Live (limit 1); `useCartStore.updateQuantity` returns silently. | Disabled "+" + "Only N available". |
| **SC-021** | P2 | BROKEN | yes | **Bespoke upload picker accepts any image, but the server rejects WEBP / HEIC** with a raw message: "The images.0 field must be a file of type: jpeg, png, jpg, pdf." iPhone photos are often HEIC. | Live WEBP upload. | Accept webp (+ heic if feasible) or restrict the picker, with a human message. |
| **SC-022** | P2 | GAP | yes | **No sign-out control for customers** on storefront or account pages (Studio has one). | Live account pass; no logout in `TopNavBar` / profile pages. | Sign out in account menu / page. |
| **SC-023** | P2 | GAP | yes | **Receipt email omits sizes / options** ("YYLUGYFLKY Qty 1", no "XL"). | Mailpit message. | Size per line on email + PDF. |
| **SC-024** | P2 | BROKEN | yes | **All customer-facing dates are UTC**, not Nairobi time (`config/app.php` `'timezone' => 'UTC'`). Order paid 02:25 EAT on 16 Oct shows "issued on Oct 15, 2026 23:25". | Mailpit receipt vs clock; `now()` = UTC. | `Africa/Nairobi` for display (store UTC or local consistently). |
| **SC-025** | P2 | UNCLEAR | yes | Password-reset email is Laravel's default ("Reset Password Notification", "Laravel" in the body). | Mailpit. | Branded copy. |
| **SC-026** | P2 | RISK (privacy) | yes | Your Orders page data includes Studio `internal_notes` (not displayed, readable in page source). | Code: `/my-vault` passes full models; `CustomOrder` has no `$hidden`. | Customer-safe projection. |
| **SC-027** | P3 | UNCLEAR | yes | Product page stepper "1. Start 2. Select 3. Fit" is decorative and always on step 2. | Live. | Remove or make real. |
| **SC-028** | P3 | GAP | yes | Shop sort isn't kept in the URL; refresh / share resets it. | Live. | Sort in query string. |
| **SC-029** | P3 | UNCLEAR | yes | "Size Guide" opens WhatsApp; it's fit help, not a guide. | Live. | Rename "Ask about fit" or add a chart. |
| **SC-030** | P3 | UNCLEAR | yes | Contact form fields aren't required; an empty submit opens WhatsApp with a blank message. | Code `Contact.vue`. | Require a message. |
| **SC-031** | P3 | RISK | yes | Bag keeps the price / stock snapshot from add-time until checkout reprices (server is authoritative; the review shows the true total). | Code. | Optional refresh on bag open. |

## D. Accessibility (axe-core, WCAG 2.2 AA)

| ID | Sev | Type | RC | Issue | Evidence | Expected |
|---|---|---|---|---|---|---|
| **SC-032** | P2 | BROKEN | yes | **Product gallery images have no `alt`** (axe *critical*). | `/shop/{slug}` all widths. | Product name / view as alt. |
| **SC-033** | P2 | BROKEN | yes | **Light mode: mobile bottom-nav labels are invisible** (contrast 1.2:1). The legacy light-mode remap turns their white text dark on the always-dark bar. | axe `/checkout@390 light` "Home" fg #232324 / bg #313131. | Bar exempt from the remap (`always-dark`). |
| **SC-034** | P2 | UNCLEAR | yes | Light-mode accent `#0b7da3` on light surfaces is **4.0–4.3:1** for small text (kicker, links, phone number, "CLOSET"). | axe light pages. | Darker accent for light text (≥ 4.5:1). |
| **SC-035** | P3 | UNCLEAR | yes | Faded labels fail contrast: "FABRIC" (text-white/40), "Showing" (opacity-40), "Forgot?" 2.3:1 light; "Lipa na M-PESA" green 2.2:1 light; white on "NEW" badge 3.7:1. | axe details. | Raise to ≥ 4.5:1. |
| **SC-036** | P3 | BROKEN | yes | Product gallery scroller isn't keyboard-reachable. | axe `scrollable-region-focusable`. | Focusable region / arrow buttons. |
| **SC-037** | P3 | UNCLEAR | yes | Touch targets < 24px: product title links in cards, breadcrumb, "Size Guide". | Mobile pass. | ≥ 24px hit area. |

## E. Performance and delay

| ID | Sev | Type | RC | Issue | Evidence | Expected |
|---|---|---|---|---|---|---|
| **SC-038** | P2 | DELAY | yes | **Material Symbols icon font = 1.1MB on every first visit**, finishing 6–11s into load; inflates measured LCP (Home LCP ≈ 11s). | Network capture; full variable axis font from Google. | Subset to used icons / static weight, or inline SVG. |
| **SC-039** | P2 | DELAY | yes | **About ships a 1.9MB PNG** (`black_white_hoodies_showcase.png`). RC Home also ships two 2.2MB PNGs (removed in the working-tree redesign). | Network capture. | WebP, responsive sizes. |
| **SC-040** | P3 | DELAY | yes | 15 identical `theme_settings` queries per request (17–21 queries per page). | Query count run. | One cached read. |
| **SC-041** | P3 | COMPLEXITY / DELAY | no (working tree) | In-progress Home (another tool): 2.1–2.5MB, 87–93 requests, new `showcaseProducts` correlated sub-query on every Home load, cinematic scroll script. | Network capture; `HomeController` diff. | Review before it reaches a release. |

## F. Operations and configuration (production requirements)

| ID | Sev | Type | RC | Issue | Evidence | Requirement |
|---|---|---|---|---|---|---|
| **SC-042** | P1 | OPS | yes | Public uploads disk falls back to **`s3`** if `FILESYSTEM_DISK_PUBLIC` is unset (`config/filesystems.php`). | Code. | Set `FILESYSTEM_DISK_PUBLIC=public` (or default to `public`). |
| **SC-043** | P1 | OPS | yes | **Receipts need a queue worker.** The job sat in `jobs` until `queue:work` ran, then delivered (0 failed). | Live. | Persistent or cron-driven `queue:work`. |
| **SC-044** | P1 | OPS | yes | **Lost-callback recovery and hold expiry need cron** (`schedule:run` every minute). | Code + tests. | Cron + log proof. |
| **SC-045** | P1 | OPS | yes | **PHP upload limits.** Local allows 100MB; typical production defaults (2MB file / 8MB post) would reject normal photos (bespoke: up to 6 × 10MB; Studio: 5–10MB). | `php -i` + validation rules. | `upload_max_filesize ≥ 10M`, `post_max_size ≥ 64M`, `max_file_uploads ≥ 6` (and web-server body limit). |
| **SC-046** | P2 | OPS | yes | Mail sender is a personal Gmail in dev; production needs a brand sender with SPF / DKIM / DMARC or receipts land in spam. | Mailpit headers. | Brand `MAIL_FROM_*` + DNS records. |
| **SC-047** | P2 | OPS | yes | Release hygiene: `master` 28 commits unpushed; working tree mixes unreviewed design work and another tool's concurrent edits. | git. | Deploy only a reviewed, committed, pushed revision. |
| **SC-048** | P3 | OPS | yes | No CI; browser checks live outside the repo; Pint not enforced. | Repo. | CI running tests + build. |
| **SC-049** | P3 | COMPLEXITY | yes | Dead code / deps: `LoginModal.vue`, `AuthenticatedLayout.vue`, `/my-vault/invite`, `laravel/sanctum`, `@tailwindcss/container-queries`. | Reference search. | Remove. |
| **SC-050** | P3 | OPS | yes | `.env.example` omits `DROPS_ENABLED`, `WHATSAPP_NUMBER`. | Diff vs `config/*`. | Document. |

## G. Business content (not code)

| ID | Sev | Issue |
|---|---|---|
| **SC-051** | Public-launch blocker | No privacy, terms, delivery or returns/refund pages (Kenya DPA 2019 for privacy). |
| **SC-052** | Before public launch | About page figures ("500+ happy clients", "100%", "3 yrs", "KE/EA") unconfirmed. |
| **SC-053** | Before enabling Drops | Failed-target / refund policy undecided (Drops correctly disabled). |
| **SC-054** | Decision | How bespoke payments are recorded (ties to SC-009). |

---

## Verified working (no action)

| Area | What was verified |
|---|---|
| Navigation | Every crawled public route (21) returns 200; no dead links; no JS errors. |
| Discovery | Search, empty-search recovery, category deep links (survive refresh and Back). |
| Product | Out-of-stock blocking; jogger asks only for a bottom size; set requires both sizes with clear messages. |
| Bag | Per-size lines; stock cap enforced; bag survives reload; Escape closes; scroll lock. |
| Checkout | Field + server validation messages; one order per click. |
| Payment, through the real UI | **cancel → "not completed" in ~3s → Resume Payment → paid retry confirmed in ~3s** (B-01). |
| Confirmation | Receipt PDF downloads; duplicate callback ignored; mismatch blocked and owner emailed. |
| Stock | Committed exactly once; a second shopper sees the item sold out. |
| Tracking | Phone in any format; generic error on a wrong phone; deep-link prefill; fulfilment stage shown. |
| Bespoke | No duplicate on double-submit; reference; WhatsApp opens with store number, reference and image link; trackable; appears in the Studio attention queue; quote saved. |
| Account | Register (password rules), profile and password updates, login throttling, reset email delivered. |
| Studio | Product validation, duplicate names, create / search, settings save, export. |
| Mobile (375×667) | Every key action reachable, none covered by fixed bars; payment panel fits. |
| Platform | PHP extensions present (`gd`, `imagick`, `intl`, `mbstring`, `pdo_mysql`, `bcmath`, `zip`, …); Composer platform check passes. |

## Runtime requirements for efficient production operation

| Area | Requirement |
|---|---|
| Runtime | PHP 8.2+ with the extensions above; Composer `--no-dev -o`; built assets (`npm ci && npm run build`, Node 20+). |
| Database | MariaDB / MySQL (InnoDB) |
| Processes | Cron `* * * * * php artisan schedule:run` (reconcile + expiry); queue worker for receipts |
| Mail | SMTP with brand sender + SPF / DKIM |
| Network | HTTPS (secure cookies, `crypto.randomUUID` needs a secure context); Safaricom must reach the public HTTPS callback URL |
| Uploads | Upload limits per SC-045; `storage:link` (local disk) or S3 / R2 credentials |
| Environment | `.env` per the release report, incl. `FILESYSTEM_DISK_PUBLIC`, production M-Pesa values, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` |
| Config | Timezone decision (SC-024); caches `config:cache` / `route:cache` / `view:cache` after each deploy, then `queue:restart` |
| Recovery | Database + uploads backups; never `db:seed` |

## Local test data created by this QA pass

All in the **local dev database only**, marked "ZZ Audit":
- 3 products (`zz-audit-jogger`, `zz-audit-out-of-stock`, `zz-audit-bespoke-set`)
- 9 orders/briefs
- 2 test users (`zz.account.*@example.com`, `audit+*@example.com`)
- Studio settings re-saved with existing values
- the bespoke-set batch limit was adjusted for the last-unit test

**Removed:** temporary scripts in `storage/app`. Mailpit holds the test emails.
