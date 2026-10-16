# Serana Closet: End-to-End QA Report

**Date:** 2026-10-16 · **Branch:** `design/novatek-layout-system` (uncommitted working tree) · **Environment:** local ddev, MariaDB, Mailpit
**Method:** scripted real-browser sessions (Chrome via Playwright) at 390px, 360px and 1440px, acting as a guest shopper, a signed-in customer, a bespoke client and the studio owner. Every step was checked in the UI *and* in the database. A DB snapshot `pre-qa-2026-10-16` was taken first (`ddev snapshot restore pre-qa-2026-10-16` undoes all QA data).

## Result

| Area | Checks | Outcome |
|---|---|---|
| Navigation (desktop header, mega-menu, footer, 404, sitemap, theme) | 12 | All pass |
| Shop (search, empty state, sort + URL, chips, card → product) | 6 | All pass |
| Product + bag (sizes, sets, stock cap, remove, persistence, WhatsApp order, /cart, sold-out, 404) | 13 | All pass |
| Checkout + M-Pesa (focus mode, validation, order creation, stock hold, resume, STK, callback security, confirmation, receipt PDF, signature tamper, settlement, emails) | 18 | All pass (Safaricom leg stubbed, see below) |
| Payment failure paths (cancelled → retry → paid, amount mismatch) | 3 | All pass |
| Tracking (deep link, wrong phone, +254 format, refresh, receipt, privacy, rate limit) | 7 | All pass |
| Bespoke (validation, chips, uploads, drag-drop, save, no duplicates, tracking, "made to measure" prefill, server validation) | 12 | All pass after fixes |
| Contact, newsletter, content pages, Drops flag | 7 | All pass after fixes |
| Phone navigation (bottom bar, menu, overflow at 360px) | 9 | All pass after fixes |
| Accounts (guards, register, signed-in checkout, Your Orders, profile, password, sign in/out, lockout, reset by email, delete) | 22 | All pass after fixes |
| Studio (brief → quote → payment → stage → photo → customer view, shop fulfilment, archive/restore, products, categories, showcase, settings, subscribers, phone) | 24 | All pass after fixes |

PHP suite: 134 tests pass. Frontend suite: 13 tests pass.

## Defects found and fixed

| # | Sev | Defect | Fix |
|---|---|---|---|
| Q-1 | **P0** | **Opening any bespoke order with a progress photo froze the Studio tab.** `orderImages()` pushed snaps into the order's own reactive `inspiration_urls` during render, which re-rendered forever. In committed code since `23bf3de`. The owner could never reopen a custom order after its first progress photo. | Builds a new array. Moved to `Support/orderImages.js` with a regression test. |
| Q-2 | P1 | Studio unreadable in light mode. The global `html:not(.dark) .text-white` rule turned the dark-only Studio's text dark-on-navy. | `AdminShell` forces dark while open and restores the visitor's theme on leave. |
| Q-3 | P1 | Bespoke: out-of-range measurements caused a **500** (DB overflow). Past or invalid deadlines were accepted. | `between:` ranges per measurement and `deadline` as `date|after_or_equal:today` (422 with field messages). Inputs carry `min`/`max`/`:min`. |
| Q-4 | P1 | Phone menu backdrop didn't cover the page. The header's `backdrop-blur` made the `fixed` layer size itself to the header, so footer links showed through and could catch taps. | Menu teleported to `<body>`. |
| Q-5 | P1 | Customers saw raw M-Pesa errors ("Failed to authenticate with M-Pesa.") when a prompt couldn't be sent. | Calm message: not charged, check number, try again. Technical reason is logged. |
| Q-6 | P2 | Forgot-password revealed whether an email has an account ("We can't find a user…"). | The same neutral message either way; throttling still reports. Test added. |
| Q-7 | P2 | Newsletter: re-subscribing showed "The email has already been taken". Subscribing showed two different confirmations (inline + toast). | Idempotent and case-insensitive. Inline confirmation only. Tests added. |
| Q-8 | P2 | Bespoke: fabric options were clickable `<div>`s (no keyboard), the deadline and measurement labels were invisible in light mode, the WhatsApp-number error was never shown, and the "Preferred Fabric" label focused the notes box. Outfit/colour chips didn't expose their selected state. | Real toggle buttons with `aria-pressed`, token colours, inline error, labels wired. |
| Q-9 | P2 | Contact: "Studio status" text hard-coded white (invisible in light mode). The WhatsApp message said "Inquiry: support" instead of the option's label. | Token colour, readable label. |
| Q-10 | P2 | Delete account: the warning said "all of its resources and data will be permanently deleted", but orders are kept (FK `set null`). Its password field reused `id="password"` from the change-password form. | Accurate copy (orders stay and remain trackable). `id="delete-password"`. |
| Q-11 | P3 | Success screen and checkout summary printed raw keys ("top size: M"). | "Top M · Bottom L" everywhere. |
| Q-12 | P3 | Menu arrows and product archive icons were announced as "arrow_forward" / "archive". | `aria-hidden` icons, `aria-label` on the buttons. |
| Q-13 | P3 | Tab titles were built from `APP_NAME` (see Q-C2). | Titles only add "Serana Closet" when the page title doesn't already name it. |

## Not fixed: needs the owner or the host

| # | Sev | Finding | Action |
|---|---|---|---|
| Q-C1 | **P0 (prod)** | The admin account `admin@serana.com` signs in with the seeded password **`password`** (`AdminSeeder`). | Confirm production uses a different, strong password, and never run `AdminSeeder` there. |
| Q-C2 | P1 | Local `.env` has `APP_NAME=Laravel`, so emails open with "**Laravel:** https://…". `.env.example` is correct. The database setting `site_name` is "SERANA INC", so emails and tab titles say "SERANA INC". | Set `APP_NAME="Serana Closet"` on every environment and confirm the intended `site_name`. |
| Q-C3 | P1 | The ddev web container has **no DNS** (it can't resolve google.com or sandbox.safaricom.co.ke; the host can). Every real STK push fails locally with "Failed to authenticate". The Safaricom call itself was therefore **not** exercised. In QA, that one network call was answered by the test browser, which recorded the attempt exactly as `MpesaController@initiate` does. Callbacks, polling, settlement, stock, receipts, emails and tracking all ran through the real app. | Fix Docker DNS (for example, `ddev poweroff && ddev start`, or set DNS in Docker's `daemon.json`), then run one sandbox payment with test MSISDN 254708374149. |
| Q-C4 | P2 | No queue worker runs locally. Receipt emails waited in `jobs` until `queue:work` was run by hand (they were then delivered with the PDF attached). | Production needs the worker (SC-043). Locally: `ddev exec php artisan queue:work`, or a `web_extra_daemons` entry. |
| Q-C5 | P2 | Checkout doesn't validate sizes server-side per `garment_type`. A hand-made request can buy a set without sizes. | Add validation in `CheckoutController@process`. |
| Q-C6 | P3 | The Drops tab is a primary Studio tab while Drops are switched off. | Hide or demote it while `DROPS_ENABLED=false`. |
| Q-C7 | P3 | Forgot / reset password, verify and confirm screens still use the old Breeze `GuestLayout`. | Restyle to match Login/Register. |

## Notes for whoever re-runs this

- The QA scripts live outside the repo (Playwright from the npx cache). Moving a short version into `tests/Browser` is item A-5 in `UX-DESIGN-RECOMMENDATIONS.md`.
- Playwright's synthetic mouse input stalls once the Studio's native `<dialog>` editor is open. Dispatch clicks instead. Real users are unaffected (an in-page click opens the editor in about 7ms).
- QA data is prefixed `ZZ QA` / `QA …` and uses the Safaricom sandbox number only. Restore the snapshot to remove it.
