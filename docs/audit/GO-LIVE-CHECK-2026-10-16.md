# Serana Closet: Go-Live Check

**Date:** 2026-10-16 · **Question:** can this be deployed today? · **Basis:** the full regression on the current working tree after today's changes, one real Safaricom sandbox payment, a dependency audit and a production-config review. Detail of earlier rounds: `QA-TEST-REPORT-2026-10-16.md`. Deploy steps and rollback: the runbook in `CONTROLLED-PRODUCTION-TEST-REPORT.md`.

## Verdict

**The code is ready. The deployment is not, until the six items under "Must do before go-live" are done.** None of them is a code defect. They are release hygiene, server setup and business content.

## What was verified today

| Area | Result |
|---|---|
| PHP test suite | **144 passed** (3 new: Studio figures and payment time) |
| Frontend tests | **16 passed** |
| Browser regression (storefront, checkout, M-Pesa paths, tracking, custom orders, accounts, Studio, phone nav, custom-order options, product close, bag) | **101 + 111 checks passed** across two full runs. The remaining failures are test-script artefacts (data piling up across runs, renamed labels), each confirmed by hand. |
| Real Safaricom sandbox | Token issued. A real STK push was **accepted** ("Success. Request accepted for processing"). The customer sees "check your phone", then "Still confirming". Reconciliation correctly settled 3 real earlier sandbox payments (ResultCode 0) and marked 3 cancelled ones failed (1032/1037). It never settled an unknown checkout id. |
| Production caching | `php artisan optimize` (config, routes, views, events) succeeds and pages serve with the caches. |
| Migrations | All applied. One new additive migration today: `2026_10_16_000001_add_paid_at_to_custom_orders` (backfills existing paid orders). |
| Error pages, storage link, `env()` usage | Branded 401/403/404/419/429/500/503 pages. Storage linked. No `env()` outside `config/`. |

## Fixed in this round

| Sev | Issue | Fix |
|---|---|---|
| P1 | The Studio's **"Paid this week"** summed paid orders *placed* in the last 7 days, not money *received*. Archived (dispatched) orders were left out. | New `paid_at`, set when M-Pesa settles or the studio records a payment. The figure uses it and includes archived orders. Tested. |
| P1 | Studio-recorded payments had no date. The last edit stood in, so changing the stage later moved the "paid on" date on receipts. | `paidAtForDisplay()` prefers `paid_at`. Tested: a stage change no longer moves it. |
| P2 | **"In production"** counted new briefs not yet quoted. | Counts designing and sewing only. Tested. |
| P1 | **33 PHP security advisories** (guzzle high, league/commonmark high, dompdf, laravel/framework, symfony …). | `composer update` of those packages within existing constraints. `composer audit`: **none**. 144 tests pass. |
| P1 | **5 high npm advisories in shipped code** (vue, postcss, nanoid …). | `npm audit fix` (no forced majors). Production dependencies: **0 vulnerabilities**. 7 remain in build-only tooling and need major upgrades. |
| — | Local only: ddev had stale DNS, so no M-Pesa prompt could be sent. | `ddev restart`. Not an app issue. Production hosts resolve normally. |

Lock-file backups from before the updates are in the session scratchpad. `git diff composer.lock package-lock.json` shows the exact changes.

## Launching before the M-Pesa till is approved

Checkout supports two ways to pay, switched in **Studio → Settings → Payments**:

| Mode | What the customer does | What the studio does |
|---|---|---|
| **M-Pesa now** | Approves an STK prompt; the order confirms automatically. | Nothing; the receipt is sent automatically. |
| **Order on WhatsApp** | Places the order (code, held stock, tracking), taps *Send order on WhatsApp* (pre-filled), and pays as instructed (e.g. Send Money / Pochi la Biashara). They also get a "how to pay" email. | Gets an email copy and a "WhatsApp order: confirm payment" item in the Studio; taps **Confirm payment** (full amount + M-Pesa code). That runs the same settlement as M-Pesa: paid time, stock committed once, receipt email, tracking updated. Unpaid ones can be **cancelled** (stock released) or expire after the hold window (12–72 h). |

**For launch:** turn M-Pesa **off**, keep WhatsApp **on**, fill in *How customers pay you*, and choose the hold window. If the production server has no Daraja credentials yet, M-Pesa stays off automatically and WhatsApp is always offered, so checkout can never dead-end. When the till is live, add the credentials, press **Test connection**, and switch M-Pesa on: customers then choose between the two methods (M-Pesa preselected).

Safety: switching M-Pesa off stops only *new* prompts. Payments already in progress still settle via callback or reconciliation. A customer caught mid-payment can move the **same** order to WhatsApp (no duplicate). WhatsApp orders are never expired by a later checkout and never by the 15-minute M-Pesa window. A shop order paid by M-Pesa still can't be hand-marked paid.

Covered by `tests/Feature/PaymentMethodsTest.php` (15 tests) and an end-to-end browser run (17 checks). New migration: `2026_10_16_000002_add_payment_method_to_custom_orders` (additive, default `mpesa`).

## Must do before go-live (blockers)

1. **Commit and review the release.** About 120 files are uncommitted. They mix this session's work with another session's redesign (header, footer, logo, home). Deploy from a reviewed, tagged commit, never from a working tree (SC-047).
2. **Change the admin password.** `admin@serana.com` still signs in with the seeded `password`. Never run `AdminSeeder` on production.
3. **Production `.env`:**
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_NAME="Serana Closet"` (local says "Laravel", so emails open with "Laravel:"), `APP_URL=https://…`, `LOG_LEVEL=warning`.
   - `SESSION_SECURE_COOKIE=true`.
   - `MAIL_FROM_ADDRESS` on the brand domain with SPF, DKIM and DMARC (local uses a personal Gmail, which will land in spam).
   - `MPESA_ENV=production`, with the live consumer key/secret, **live shortcode** (174379 is the sandbox), passkey, a fresh random `MPESA_CALLBACK_TOKEN` and a public HTTPS callback URL.
   - `FILESYSTEM_DISK_PUBLIC` set explicitly.
   - `WHATSAPP_NUMBER`.
   - Check the Studio setting **site_name**: it is currently "SERANA INC", and receipts and emails use it.
4. **Queue worker and scheduler on the server.** Without the scheduler, a payment whose callback is missed is never confirmed, and abandoned checkouts never release stock. Without the worker, receipts are never emailed. (Runbook steps 10–11.)
5. **Backup and migrate** (runbook steps 2 and 7). The rollback notes say "four migrations"; there are now six (`paid_at`, `payment_method`). All are additive.
6. **Legal pages.** There is no privacy notice, terms, or delivery and returns page. A privacy notice is expected under Kenya's Data Protection Act 2019 before public launch. This needs the owner's content.

Then run the **live tests** in runbook steps 13–20: one real low-value payment, cancel-then-retry, stock expiry, Studio, a custom order, HTTPS and cookie flags.

## Should do soon after launch

| Sev | Item |
|---|---|
| P1 | **No owner alert for new custom orders** (WhatsApp *shop* orders now email the owner). The brief is saved and the customer is sent to WhatsApp, but if they never press Send, the owner only learns of it in the Studio. Add an owner email or notification on each new brief. |
| P1 | **Studio loads every order with full details** (about 1.6KB each: 300KB and 0.12s today). Expect about 1.6MB at 1,000 orders. Paginate or lazy-load before then (SC-015). |
| P2 | Checkout doesn't validate sizes per garment type on the server (a crafted request can skip them). |
| P2 | `trustProxies('*')`: set the host's real proxy IPs so the M-Pesa IP allowlist and rate limits can't be fooled by a forged `X-Forwarded-For`. The callback token remains the primary guard. |
| P2 | Payments confirmed by reconciliation have no M-Pesa receipt code (Daraja's STK Query doesn't return one). Receipts show the order code only. |
| P3 | Build-only npm advisories (major upgrades). Restyle the remaining Breeze auth screens. Leftover Telescope tables (package not installed). |

## Local development note

Safaricom can't reach `serana-closet.ddev.site`, so locally a payment confirms only through reconciliation. Keep `ddev exec php artisan schedule:work` (and `queue:work` for receipts) running when testing payments. If prompts fail after a network change, run `ddev restart`.
