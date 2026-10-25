# SERANA-CLOSET — OPERATIONAL CONSTRAINT & FAILURE-MODE REPORT

**Date:** 2026-10-22 · **Branch:** `fix/first-principles-pass` (base `4724c02`, master at `b5143b3`) · **Scope:** the frozen V1 boundary (shop, checkout, M-Pesa, tracking, account, bespoke, Studio, Sales Desk). Drops are off (`DROPS_ENABLED=false`) and were not reviewed beyond confirming they stay off.

**Method.** The current code was read, not earlier documents: routes, controllers, services, models, Vue pages, the scheduler and the tests. Each operation was asked: what must be true first, what can stop it, what happens if it fails halfway, how the user finds out, and how they recover. Anything that could lose money, mislead someone or leave a dead end was fixed and covered by a regression test that checks state, not only the HTTP answer. Business rules were not invented: those are listed under *Owner decisions required*.

**Severity.** P0: money loss, data or stock corruption, security compromise. P1: a core workflow blocked, an unsafe payment state, staff unable to fulfil, a customer unable to buy. P2: a significant usability or operational problem. P3: polish.

---

## Executive Assessment

The money and stock core is sound. One ledger writer (`OrderSettlementService`), one stock writer (`StockService`), one cash writer (`TillService`), a single lock order, and `innodb_snapshot_isolation` race protection all held up under review. The earlier V1 hardening (no money ever abandoned, duplicates become refunds owed, callback and reconcile in any order) is intact.

The gaps were at the edges, where a person, the network or the background machinery fails:

1. **A customer could be prompted twice** for the same order while the first prompt was still on their phone. Nothing stopped a second prompt (P1, fixed).
2. **A timed-out M-Pesa request was reported as "not charged, try again"**, although Safaricom may have sent the prompt. Its confirmation then matched no order, so the money became invisible (P1, fixed).
3. **A double tap or retried request at the Sales Desk could book the same cash twice.** Two tablets, or a retry after a lost response, each read the balance before either booked it. The drawer then expected cash never handed over, and the sale showed a false refund owed. Reproduced on MariaDB with two processes (P1, fixed).
4. **Staff could not see when the background jobs stopped.** If the scheduler stopped, missed M-Pesa confirmations were not checked; if the queue stopped, receipts did not go out. Nothing in the Studio said so (P1, fixed).
5. **Permanently deleting a product always failed with a server error**, because every product has size rows (P1, fixed).
6. **One PIN could open two staff accounts** after a suspended member was reactivated, so Sales Desk actions and manager approvals could be recorded against the wrong person (P1, fixed).

Fourteen smaller problems were fixed too (see *Bugs fixed*). Nothing found is P0. The remaining items are operational (alerting, backups, legal pages), scale (the Studio and shop load whole lists), or owner decisions.

---

## Customer Constraints

| Constraint | Handling now |
|---|---|
| Product sells out while shopping | Server re-checks every line at checkout, inside a row lock; per-size message names the size. The bag says prices and stock are confirmed at checkout. |
| Price changed since the item went in the bag | Server charges the current price. **New:** checkout says which item changed and the new total before the customer pays, and updates the bag. |
| Old or malformed bag in `localStorage` | Lines without a valid id, price or whole quantity are dropped on load. Lines saved before sizes existed are asked to choose a size. |
| No delivery or collection option on offer | **Was a dead end** (an empty choice, then an error the customer could not fix). **New:** checkout explains that online orders are paused, links to WhatsApp, and disables Pay. The Studio home and `serana:preflight` also flag it. |
| Broken product image | The product card falls back. The bag and product page show the alt text (P3). |
| Large catalogue | `/shop` loads every product (P2 at a few hundred products; search and pagination were deferred). |

## Product Constraints

- Hidden or unsellable products: `in_stock=false` and archived (soft-deleted) products are refused at checkout and at the Sales Desk (`exists … whereNull(deleted_at)`).
- A product page reached by an old link after a rename is a 404: the web address follows the name (P3; see *Owner decisions*).
- Sets need a top and a bottom size; per-size demand is checked server-side (`StockService::demandsFor`).

## Cart Constraints

- The bag is browser-only. Two tabs share `localStorage`, but a tab doesn't pick up the other's changes until reload (P3). The server re-validates every line, so this can't over-sell.
- Quantity caps in the bag are advisory; the server is authoritative.

## Checkout Constraints

| Case | Handling |
|---|---|
| Double click, refresh mid-submit, response lost | `request_id` saved in the session (`SubmissionReceipt`) returns the same order. Session `block()` serialises same-session requests. The page saves its context before posting, so a refresh resumes the order. |
| Back button or a return visit | The saved context (1 h) resumes the pending order with its server status. |
| Two checkout tabs | The second gets the same order (same bag signature and request id) or supersedes only an *unfunded* earlier order. |
| Missing name, email, phone or address | Jump to the first missing field with a message. Phone must be a Kenyan mobile. |
| Delivery fee changed | Priced by the server at checkout; the order keeps what it was charged. |
| Payment method switched off mid-checkout | `mpesa_paused`: the same order moves to WhatsApp, with no duplicate order. |

## Payment Constraints

### M-Pesa failure matrix

| Case | Order state | Payment state | Customer sees | Studio sees | Recovery |
|---|---|---|---|---|---|
| Customer cancels / wrong PIN / no funds | Payment Failed (if no other live prompt) | attempt failed | "Payment was not completed. Press Resume Payment…" | Follow-up queue | Retry (allowed: the prompt was answered) |
| Prompt times out on the phone (1037) | Payment Failed | failed | Same | Same | Retry |
| **Customer asks again while the first prompt is unanswered** | unchanged | first prompt pending | **New:** "A payment prompt was sent to the number ending 678 moments ago… approve it… before asking for another." The page waits on that prompt. | — | Wait; allowed after 150 s (`MPESA_PROMPT_WINDOW_SECONDS`) or once the prompt is answered |
| **Daraja timed out after we connected** | Pending Payment | **New:** placeholder attempt `unconfirmed-…` | **New:** "We couldn't confirm whether M-Pesa sent the prompt… please don't pay again until this page updates." (was "You have not been charged… try again") | — | The callback claims the placeholder by phone and amount; otherwise it is closed after 30 min |
| Daraja unreachable (DNS, refused, connect timeout) | unchanged | none | "M-Pesa couldn't send the payment prompt just now. You have not been charged." (true: no request left) | — | Retry |
| Callback delayed or lost | Pending Payment | pending | "Still confirming your payment… No need to pay again", **new** Check payment status button | Awaiting list | `mpesa:reconcile` asks Daraja from 2 min to 24 h |
| Callback duplicated | Paid | idempotent per attempt and per code | Paid | — | — |
| Callback before the page polls | Paid | paid | Polling shows paid | — | — |
| Customer closes the page | Paid on callback | paid | Receipt email | Paid | Track with reference + phone |
| Customer pays two prompts | Paid | second booked | Paid | Refunds queue "Paid twice" | Owner refunds |
| Underpayment (shop) | Part paid; stock held 24 h | booked | Pay-link balance | Attention | Pay the balance, or a refund owed after the hold |
| Overpayment | Paid | booked | Paid | Refunds queue | Owner refunds |
| Unknown reference (callback for no order) | — | **new:** audit `payment.unmatched` | — | **New:** Studio home "N M-Pesa payments match no order" with amount, code, phone ending | Owner records it on the right order or refunds |
| Amount mismatch (legacy) | Amount Mismatch | flagged | "Contact support before paying again" (no new prompt allowed) | Attention | Owner review |
| Prompt older than the 24 h query window | Pending | pending | Pending | **New:** Studio home lists the orders | Check the M-Pesa statement (owner decision below) |

**Never create payment uncertainty.** Every customer-facing failure now says what did and didn't happen. Three paths used to invite a second payment: an unanswered prompt, a timed-out request, and the bespoke pay page after a network error. They now wait and check instead.

## Tracking Constraints

Lookup needs the reference and the phone together, throttled at 10/min, and returns a customer-safe projection. Bespoke WhatsApp numbers are now stored normalised (see Bespoke), which keeps matching reliable. Guest orders stay unlinked to accounts (a deferred decision).

## Account Constraints

- Customer deletion keeps orders (`user_id` set null); tracking still works by reference + phone.
- Staff can't delete their own accounts (403); they are suspended instead.
- Duplicate email is refused by Breeze. Email verification is not enforced (owner decision).

## Bespoke Constraints

| Case | Handling |
|---|---|
| Missing or invalid WhatsApp number | **Was accepted as any text up to 20 characters.** **New:** 10–15 digits after normalising (international numbers allowed), stored as digits; the brief shows it readably. |
| Resubmitted brief (double tap, lost response) | `request_id` returns the same order. |
| Too many or large uploads | Max 6 files of 10 MB each, JPG/PNG/WEBP/PDF, with specific messages. |
| Save failed | **New message:** "We could not save your request, so nothing was sent to the studio…" (was "The digital atelier is momentarily busy"). |
| Quote, deposit, balance | Server sets every amount; the deposit starts work, full payment before handover; owner override with a reason (`fulfilment.override`). |
| Cancel before or after designing | `cancelBespoke` applies the deposit rule and puts any excess in Refunds. |
| Pay link expired | Friendly page: ask the studio for a new link. |
| Pay page network error | **New:** waits and checks the balance instead of "try again". |

## Studio Constraints

- **New system warnings** on the Studio home (`App\Support\SystemHealth`), shown only when something is wrong: the scheduler stopped (over 5 min), emails stuck (over 15 min), failed jobs in the last day, prompts never resolved (over 24 h, each order one click away), M-Pesa payments matching no order, and no way to check out. Each says what it means for orders and money, and who acts.
- Payment cases come first in "Next decision". Low stock, till variances, follow-ups and work queues follow.

## Order Constraints

Transitions enforced on the server (`StudioController::update`, settlement):

| From → to | Rule |
|---|---|
| Unpaid shop order → any stage | Refused: "Confirm payment before updating fulfilment." |
| Part-paid shop order → any stage | Refused, with the balance. |
| Pickup order → shipped | Refused. Delivery order → ready for pickup: refused. |
| Any → collected | Only at the Sales Desk (phone check, nothing owed, the right shop). |
| **Collected → anything** | **New: refused.** It used to be allowed, so a collected order could be moved back to "ready" and handed over again. The editor now shows it read-only. |
| Cancelled → any stage | Refused; notes only. |
| Bespoke → designing without the deposit, or shipped with a balance | Refused unless the owner overrides with a reason (audited). |
| Payment states | Never set by hand. |
| Archive | Refused with a prompt in flight, with money unsettled, or for a paid order not yet finished. |
| Purge | Refused with any payment or prompt history. |

## Product Management Constraints

| Case | Before | Now |
|---|---|---|
| Two names with the same web address ("Silk Set" and "Silk-Set"), including an archived product | Database error (500) | "“Silk Set” (archived) already uses this web address (/shop/silk-set). Choose a slightly different name." |
| A name with no letters or digits | Product with an empty address | Refused with a reason |
| Permanent delete of an archived product | **Always 500** (its sizes block the delete); images deleted first | Refused if it was ever ordered, held or counted ("stays in the archive as the record of those sales"). Otherwise the sizes, the product, then the files are removed, in that order. |
| Replacing a photo | Old file deleted *before* the new one was stored: a failed upload left a broken image | The new file is stored first; the old one is deleted after the save |
| Zero, negative or fractional price | Refused (whole shillings, at least KSh 1) | Unchanged |
| Published with no stock | Counted products show sold out until counted, with a message | Unchanged |

## Stock Constraints

Definitions (stock per size): **on hand** = `stock_levels.on_hand` (moved only by `StockService`); **reserved** = active holds (`status=held` and not past `expires_at`); **available** = on hand − reserved. Holds lapse by time in every availability query, so a stopped scheduler can't freeze stock. A commit happens once, when the order *becomes* paid. A late payment with the stock gone becomes an owner decision, never an oversell. Concurrency is covered by `ConcurrencyTest` (two buyers, buyer + till, slip + payment, stock takes) on MariaDB.

## Refund Constraints

Refunds are recorded by hand (method, reference, reason) and are never automatic. A part refund leaves the rest in the queue, and two refund clicks can't both go through (snapshot isolation, tested). The refunds queue includes archived orders. A refund owed doesn't block a handover (the obligation stays visible). The Sales Desk exchange credit uses the same ledger.

## Delivery Constraints

Fees are priced by the server; orders keep what they were charged. Options are deactivated, never deleted. A quote order waits for the studio's price before any payment. Staff changes to an order's delivery need `money.record` and a reason. **New:** no available option is flagged at checkout, on the Studio home and in preflight.

## Sales Desk Constraints

| Case | Handling |
|---|---|
| Register not open | "Open Till 1 first." |
| Cashier role | Sales Desk only; Studio redirects to the Sales Desk. |
| Final unit sold online at the same moment | One wins under the size-level lock (tested). |
| **Cash tender retried or sent from two tablets** | **Fixed:** booked against the balance under the order lock. The second gets "This sale was just paid in full on another screen. No cash was recorded now." (MariaDB race test) |
| **Another tender while a prompt is on the customer's phone** | **Fixed on the server** (the screen already blocked it): refused for 150 s, or until the prompt is answered. |
| **Till prompt timed out after reaching Safaricom** | **New:** a placeholder holds other tenders; the message says the prompt may have arrived. |
| Duplicate M-Pesa code | Refused: "already recorded on order SRN-…" (one namespace for STK and manual codes). |
| Cash handed over less than due | Refused. Change is computed and never booked. |
| Printer unavailable | The receipt is a browser print of a stored sale; reprint from Today's sales. |
| Tablet tab closed | Today's sales lists every sale on the drawer; resume, reprint or void. |

## Register Constraints

Open, payout and close are audited with actor and approver. Payouts need a reason and a manager's PIN, and can't exceed the drawer. Closing voids unpaid sales (not ones with money or a prompt in flight). **New:** the difference is re-checked under the lock, so cash recorded while the drawer was counted can't close a large variance without a reason and a manager. Closed drawers with a variance over the alert appear on the Studio home.

## Staff / Permission Constraints

- Abilities live in one matrix (`App\Support\Role::MATRIX`), enforced by Gates on routes. The UI hides only what the server refuses. Object checks: STK and status by session or owner; the pay link is signed; tracking needs reference + phone.
- **Fixed:** a PIN can't be given to anyone if another account, suspended included, already has it. A PIN that still opens two accounts (set before this fix) now names no one: "This PIN belongs to more than one person. Ask the owner to set each of you a new PIN."
- Last-owner protection, no self-suspend and no self role change are unchanged.
- **Open:** managers can edit store contact details (WhatsApp number, alert email) and custom-order options. These changes are now audited; whether they should be owner-only is an owner decision.

## Settings Constraints

Validated at the boundary: deposit 0–100 %, pay link 1–90 days, refund days 1–14, balance hold 1–168 h, resolution 1–30 days, WhatsApp hold 12/24/48/72 h, Sales Desk hold 5–240 min, idle lock 1–120 min. At least one payment method must stay on. M-Pesa can't be switched on without credentials. WhatsApp-only needs payment instructions. **New:** store-detail and custom-order-option changes are recorded in Activity, with before/after values and the actor.

## Frontend State Constraints

The server stays authoritative: totals, payment status and the next action come from the server. **Fixed:** the "Still confirming" dialog's Close button did nothing, so the customer was stuck. It now closes, and Check payment status asks the server. The bag syncs across tabs only on reload (P3).

## Backend Constraints

Unique keys: product slug, SKU, barcode, (product, part, size), M-Pesa reference scope, payment attempt id, checkout request id, slip number, one open register. Foreign keys restrict deletes of ledger rows. Product → reservations cascade (now unreachable: products with holds can't be purged).

## Concurrency Constraints

Verified on MariaDB 11.8: two buyers for the last unit; buyer + Sales Desk; slip + online payment; callback + reconcile; two refunds; two stock takes; deadlock retries; **new:** two cash tenders on one sale. Two staff editing the same order's notes: the last save wins (P3; money and stage are server-checked).

## External Service Constraints

| Service | If it fails | Still works | Data safe? | Who is told | Recovery |
|---|---|---|---|---|---|
| Daraja STK | No prompt / timed out | WhatsApp ordering, the till's cash and codes | Yes; a timeout keeps a placeholder | Customer, with a precise message | Retry, or the callback claims the placeholder |
| Daraja callback | No confirmation | Reconcile (2 min–24 h) | Yes | **Studio home** after 24 h | Owner checks the statement |
| SMTP | Emails fail | Everything else | Yes (receipts reprintable, tracking) | **Studio home** (failed jobs) | Fix the mailer, `queue:retry` |
| Queue worker | Emails wait | Payments, stock | Yes | **Studio home** (emails stuck) | Restart the worker |
| Scheduler | No reconcile, no expiry | Holds lapse by time; callbacks still settle | Yes | **Studio home** (over 5 min) | Restart cron |
| Storage | Upload fails | The product keeps its old image | Yes (the new file is stored before the old one goes) | The staff member (error) | Retry |
| Database | 500 | — | Transactions roll back | Logs only (P2: no alerting) | — |
| WhatsApp hand-off | The customer doesn't send it | The order exists and is held | Yes | Studio (WhatsApp orders awaiting) | Studio follows up, or the hold expires |

## Performance Constraints

- `/studio` loads every product with availability on every visit (P2: fine to a few hundred products; paginate later).
- `/shop` loads the whole catalogue (P2, deferred).
- Orders, till history and activity are paginated. Subscribers load fully on their tab (P3).

## Security Constraints

No new findings beyond the PIN ambiguity (fixed). The STK route is session-authorised and throttled; the callback is token-guarded, with an optional IP allowlist; receipts are signed; tracking needs two keys; the pay link is signed and expires.

## Recovery Constraints

| Failure | Recovery path |
|---|---|
| Payment unknown | Wait on the existing prompt; Check payment status; reconcile; Studio list after 24 h |
| Stock conflict | Back to the bag with the size named |
| No delivery option | WhatsApp link; the owner switches one on |
| Register variance | Count again, or a reason + manager PIN |
| Image failed | The old image stays; retry |
| Product purge refused | Stays archived (already hidden) |
| Shared PIN | Owner sets new PINs |

## Business Policy Constraints

See *Owner decisions required*. The code stays neutral where policy is missing: refunds are never automatic, and a late payment with the stock gone waits for a decision.

---

## Bugs Fixed

| # | Sev | Bug | Fix | Test |
|---|---|---|---|---|
| B1 | P1 | A second STK prompt could be sent while the first was unanswered (double payment) | `CustomOrder::unansweredPrompt()`; `initiate` returns 409 `prompt_pending` with the prompt id; the Sales Desk prompt is refused too | `test_a_second_prompt_is_refused…`, `test_a_new_prompt_is_allowed_once…` |
| B2 | P1 | A timed-out Daraja request was reported as "not charged", and its callback matched no order (invisible money) | `MpesaService::mayHaveBeenSent`; placeholder attempt; the callback adopts it by phone + amount; reconcile closes it after 30 min; unmatched callbacks audited and listed | `test_a_timed_out_prompt_…settles_the_order`, `test_money_that_matches_no_order…`, `test_a_placeholder_nobody_claimed…`, `test_only_a_failure_to_connect…` |
| B3 | P1 | Racing or retried cash tenders booked the cash twice | `tenderCash` books with `limitToBalance` under the order lock | `ConcurrencyTest::test_two_cash_tenders_racing…` (fails without the fix: both booked KSh 2,500) and `test_a_retried_cash_tender…` |
| B4 | P1 | Product purge always failed (FK on sizes); images deleted before the delete | History check, then sizes → product → files | `test_a_never_sold_archived_product…`, `test_a_product_with_sales_history…` |
| B5 | P1 | Reactivation could create a shared PIN; the first match won | PINs unique across suspended staff; an ambiguous PIN names no one | `test_a_pin_held_by_a_suspended_account…`, `test_a_pin_that_opens_two_accounts…` |
| B6 | P2 | A collected order could be moved back and collected again | Stage locked after handover; UI read-only | `test_a_collected_order_cannot_be_moved_back…` |
| B7 | P2 | Duplicate web address → 500; an empty address possible | `uniqueSlug()` with a named reason | `test_two_names_with_the_same_web_address…` |
| B8 | P2 | Replacing a photo deleted the old one first | Store, save, then delete | `test_a_replaced_product_image…` |
| B9 | P2 | Register close: a variance could grow past the alert between the check and the close | Re-checked under the lock | `test_a_register_close_rechecks…` |
| B10 | P2 | Bespoke WhatsApp accepted any text | 10–15 digits, normalised | `test_a_bespoke_request_needs…` |
| B11 | P2 | Tenders were refused only by the screen while a prompt waited | Server refuses cash, code and credit tenders | `test_no_other_tender_is_taken…` |

## UX Issues Fixed

| # | Screen | Issue | Fix |
|---|---|---|---|
| U1 | Checkout | "Still confirming" Close did nothing; no way to check | Close works; **Check payment status** button with a clear result |
| U2 | Checkout | A price change since the bag was filled was silent | Names the item, its new price and the new total |
| U3 | Checkout | No delivery option = an empty choice and an error that couldn't be fixed | Explains, links to WhatsApp, disables Pay |
| U4 | Checkout / Pay | Existing prompt or lost response led to "try again" | Waits on the prompt; "please don't pay again until this page updates" |
| U5 | Studio home | Background failures invisible | System warnings with what it means and who acts; orders one click away |
| U6 | Studio order | Collected order offered every stage | Read-only "Collected at the Sales Desk" |
| U7 | Bespoke | "The digital atelier is momentarily busy" | Says nothing was sent and what to do |
| U8 | Staff | PIN clash message | Says active or suspended accounts count |

## Backend Issues Fixed

Invariants added or enforced on the server: one unanswered prompt per order (online and till); "maybe sent" prompts are never treated as not sent; cash booked only within the balance under the lock; collected is final; product history is never purged; product addresses are unique including archived ones; PINs identify one person; the register variance check is under the lock; store settings are audited; there is always a way to check out, or a warning.

Files: `MpesaController`, `MpesaService`, `PaymentAttempt`, `CustomOrder`, `ReconcilePendingPayments`, `CounterSaleService`, `TillService`, `StudioController`, `CatalogController`, `StaffController`, `PosOperator`, `ThemeController`, `CustomOrderController`, `ProductionPreflight`, new `SystemHealth`, `ActivityLabels`, `config/services.php` (`MPESA_PROMPT_WINDOW_SECONDS`, default 150). Frontend: `Checkout.vue`, `Pay.vue`, `OverviewPanel.vue`, `StudioDashboard.vue`.

---

## Constraint Register

| ID | Area | Constraint | Trigger | User impact | System risk | Current handling | Required handling | Sev | Fixed? |
|---|---|---|---|---|---|---|---|---|---|
| OC-01 | Payment | Two prompts for one order | Customer taps Pay again while the prompt is on the phone | Pays twice | Refund work, distrust | 409 `prompt_pending`; the page waits | — | P1 | ✅ |
| OC-02 | Payment | Daraja read timeout | Slow Safaricom | Told "not charged"; pays; order unpaid | Invisible money | Placeholder, adopted by its callback; unmatched listed | — | P1 | ✅ |
| OC-03 | Payment | Callback for an unknown checkout id | B2, or a foreign push | Order unpaid | Invisible money | Audited, Studio home | Owner action | P1 | ✅ |
| OC-04 | Payment | Prompt pending over 24 h | Lost callback beyond the query window | Unknown | Unconfirmed money | Studio home list | Owner decision (OD-1) | P2 | ◐ visible |
| OC-05 | Sales Desk | Cash tender race / retry | Two tablets, lost response | Drawer expects double cash | False variance, false refund | Balance-checked under the lock | — | P1 | ✅ |
| OC-06 | Sales Desk | Tender while a prompt waits | Second tablet | Paid twice | Refund | Server refuses for 150 s | — | P2 | ✅ |
| OC-07 | Sales Desk | Till prompt timed out | Slow Safaricom | Cashier unsure | Double payment | Placeholder + message | — | P2 | ✅ |
| OC-08 | Register | Variance changes during the count | Cash sale during close | Large variance closed unacknowledged | Accountability | Re-check under the lock | — | P2 | ✅ |
| OC-09 | Staff | Shared PIN | Reactivating a suspended member | Wrong person recorded; cashier self-approves | Accountability, security | Unique across suspended; ambiguous refused | — | P1 | ✅ |
| OC-10 | Studio | Scheduler stopped | Cron missing | Missed payments not checked | Stuck orders | Studio warning (over 5 min) | — | P1 | ✅ |
| OC-11 | Studio | Queue stopped / failed jobs | Worker down, SMTP | No receipts | Customer distrust | Studio warning | — | P2 | ✅ |
| OC-12 | Products | Purge always 500 | Owner cleans the archive | Error page | History loss if forced | History check, ordered delete | — | P1 | ✅ |
| OC-13 | Products | Duplicate web address | Similar names | Error page | — | Named validation | — | P2 | ✅ |
| OC-14 | Products | Image replaced before the new one is saved | Upload failure | Broken image | Catalogue trust | Store first, delete after | — | P2 | ✅ |
| OC-15 | Orders | Collected order moved back | Staff picks a stage | Second handover possible | Goods released twice | Stage locked | — | P2 | ✅ |
| OC-16 | Checkout | No delivery option | Options all off / pickup without address | Can't buy, unclear why | Lost sales | Message + WhatsApp + Studio + preflight | — | P1 | ✅ |
| OC-17 | Checkout | Silent price change | Price edited after the bag was filled | Different total, distrust | — | Notice with the new total | — | P2 | ✅ |
| OC-18 | Checkout | Pending dialog's Close did nothing | Customer wants out | Stuck | — | Close + Check status | — | P2 | ✅ |
| OC-19 | Bespoke | Unreachable WhatsApp number | Typo, text | Studio can't reply; tracking fails | Lost commission | Validated, normalised | — | P2 | ✅ |
| OC-20 | Settings | Unaudited store contact changes | Manager edits the WhatsApp number | Orders/money sent elsewhere | Fraud risk | Audited | Owner-only? (OD-4) | P2 | ◐ |
| OC-21 | Observability | No error alerting | 500s, DB errors | Silent failures | — | Logs only | Simple alert (log mail or uptime check) | P2 | ❌ |
| OC-22 | Ops | No off-server backups | Disk or server loss | Data loss | P0 if it happens | Manual | Scheduled dump + restore drill | P2 | ❌ |
| OC-23 | Performance | Studio loads every product | Catalogue grows | Slow Studio | — | Eager list | Paginate when >300 products | P2 | ❌ |
| OC-24 | Performance | Shop loads every product | Catalogue grows | Slow shop on mobile | — | Eager list | Paginate/search (deferred) | P2 | ❌ |
| OC-25 | Products | Rename changes the web address | Name edited | Old links 404 | Lost traffic | — | Keep the slug, or redirect (OD-6) | P3 | ❌ |
| OC-26 | Frontend | Bag/product page image fallback | Missing file | Alt text shown | — | Card only | Fallback image | P3 | ❌ |
| OC-27 | Orders | Notes last-save-wins | Two staff editing | A note lost | None for money | — | Append-only notes | P3 | ❌ |
| OC-28 | Cart | Tabs don't sync | Two tabs | Stale bag view | None (server checks) | — | `storage` event | P3 | ❌ |
| OC-29 | Legal | Terms, privacy, returns missing | Launch | Trust, compliance | — | — | Owner content (OD-2) | P2 | ❌ |
| OC-30 | Config | Store WhatsApp number blank | Both setting and env empty | Broken wa.me links | Lost orders | Preflight blocker | — | P3 | ✅ (existing) |

## Failure-Mode Matrix

| Operation | Failure | State preserved? | User message | Recovery | Staff visibility |
|---|---|---|---|---|---|
| Checkout | Double submit / lost response | Yes (same order) | Resumes the order | Automatic | — |
| Checkout | Stock gone | Yes (no order) | Size named; review bag | Choose again | — |
| Checkout | No delivery option | Yes | Online orders paused + WhatsApp | Owner switches one on | **Studio home** |
| Payment | Prompt unanswered, asks again | Yes | Approve the first prompt | Wait 150 s / answer | — |
| Payment | Daraja timeout | Yes (placeholder) | May have been sent; don't pay again | Callback adopts / closed at 30 min | **Unmatched list** if orphaned |
| Payment | Callback lost | Yes (pending) | Still confirming + Check | Reconcile | Over 24 h: **Studio home** |
| Payment | Paid twice | Yes (booked) | Paid | Refund | Refunds queue |
| Stock | Race for the last unit | Yes | Sold out | Choose another | — |
| Stock | Late payment, stock gone | Yes (money booked) | Paid; studio in touch | Refund / swap / wait | Attention |
| Refund | Double click | Yes (one refund) | — | — | Refunds queue |
| Delivery | Quote not priced | Yes | Studio pricing delivery | Studio sets the fee | Attention |
| Bespoke | Upload/save failed | Yes (nothing saved) | Nothing was sent; try again | Retry | — |
| Sales Desk | Cash retry / two tablets | Yes (one booking) | Already paid on another screen | Refresh | — |
| Sales Desk | Prompt timed out | Yes (placeholder) | May have arrived; check status | Check / wait | Sale shows the prompt pending |
| Register | Variance grows during close | Yes (still open) | Count again or reason + PIN | Recount | Variance on the Studio home |
| Uploads | Storage failure | Yes (old image kept) | Error | Retry | — |
| Mail | SMTP down | Yes (job failed) | Receipt delayed | `queue:retry` | **Studio home** |
| Queue | Worker stopped | Yes (jobs wait) | Receipt delayed | Restart | **Studio home** |
| Scheduler | Cron stopped | Yes (holds lapse by time) | Payment may confirm later | Restart cron | **Studio home** |

## Dependency Matrix

| Feature | Backend dependency | External dependency | Config | What breaks | Fallback |
|---|---|---|---|---|---|
| M-Pesa checkout | `MpesaController`, settlement | Daraja STK + callback reachability | `MPESA_*`, callback token/URL, allowed IPs | No prompts / no confirmation | WhatsApp ordering (auto-offered when M-Pesa is unavailable) |
| Reconciliation | `mpesa:reconcile` | Daraja STK query | cron | Lost callbacks unresolved | Studio warning; statement |
| WhatsApp ordering | Checkout, `WhatsAppOrderMessage` | WhatsApp | Store WhatsApp number, instructions | Orders go nowhere | Preflight blocker |
| Delivery/pickup | `DeliveryOption`, `Location` | — | Options, pickup address | Can't check out | Message + Studio warning |
| Receipts and alerts | Mailables (queued) | SMTP | `MAIL_*`, alert email | No email | Receipt link and tracking; Studio warning |
| Holds expiry | `stock:release-expired` | — | cron | Statuses stale | Availability lapses by time |
| Images | `ImageOptimizer` | Public disk (local/S3) | `FILESYSTEM_DISK_PUBLIC` | Uploads fail | Old image kept |
| Sales Desk | `CounterSaleService`, `TillService` | Daraja (prompts only) | PINs, shop policy | Prompts | Cash, manual code |
| Overdue resolution | `serana:resolve-overdue` | — | cron, resolution days | Decisions not escalated | Attention list |
| Race safety | All money/stock writers | MariaDB ≥ 11.6 | `innodb_snapshot_isolation=ON` | Double refunds possible | Preflight blocks deploy |

## Workflow Friction Matrix

| Workflow | Current steps | Unnecessary step | Improvement | Final steps |
|---|---|---|---|---|
| Online purchase | Bag → checkout (choice, details) → Pay → PIN → confirmation | None found; a prompt retry used to add a second payment | One prompt at a time; a price change is shown before paying | Same, safer |
| Product creation | One form: details, sizes, counted or made to order, image | — (one-step since `c3a8f19`) | Address clash explained before any upload | Same |
| Order fulfilment | Open order → next action button | Stage dropdown offered impossible stages for collected orders | Read-only after handover | Same or fewer |
| Bespoke quote | Brief → quote in Studio → pay link on WhatsApp | — | — | Same |
| Refund | Refunds queue → record (method, code, reason) | — | — | Same |
| Sales Desk sale | Find → add → continue → tender → receipt | Retrying a lost tender could double-book | Safe retry with a plain answer | Same |
| Pickup | Find by reference + phone → take balance → hand over | — | Can't be repeated | Same |
| Register close | Count → (reason + PIN if over alert) → Z report | — | Re-check under the lock | Same |

---

## Remaining P0

None.

## Remaining P1

None in code. Operations P1 (unchanged from rc.2): the controlled production test with live M-Pesa, cron, worker and mail has not run (`docs/audit/CONTROLLED-PRODUCTION-TEST-REPORT.md`).

## Remaining P2

- OC-21: no error alerting (500s and database errors are only in logs). Simplest fix: an uptime check on `/` plus a daily log-error mail.
- OC-22: no scheduled off-server backups or restore drill.
- OC-04: a prompt still pending after 24 h is visible but has no in-app resolution for shop orders (OD-1).
- OC-23 / OC-24: Studio and shop load every product.
- OC-29: terms, privacy and returns pages.
- OC-20: managers can change the store WhatsApp number (now audited; OD-4).

## Owner Decisions Required

| # | Decision | Why it matters | Default until decided |
|---|---|---|---|
| OD-1 | How to settle M-Pesa money confirmed only on the statement (a prompt over 24 h, or an unmatched payment) on a **shop** order | Today staff can record manual payments only on bespoke and WhatsApp orders | The owner contacts the customer; refund or manual handling outside the app |
| OD-2 | Returns, refunds timing, privacy, terms | Customer trust; legal | Not shown |
| OD-3 | Delivery zones, fees, ETAs; the pickup address | Checkout can't sell without them (now flagged) | Must be set before launch |
| OD-4 | Should only the owner change the store WhatsApp number and alert email? | Customers send orders and money to that number | Managers can; every change is in Activity |
| OD-5 | `MPESA_PROMPT_WINDOW_SECONDS` (150 s) | Longer is safer against double payment, shorter lets a customer retry sooner after a lost callback | 150 s |
| OD-6 | Keep a product's web address when it is renamed? | Shared links | The address follows the name |
| OD-7 | Hold lengths, deposit %, refund days, cashier discount limit, variance alert | Policy | Current defaults |
| OD-8 | Open registration and email verification | Account quality | Open, unverified |
| OD-9 | Drops refund policy | Drops stay off until decided | Off |

## Full Test Results

Run on 2026-10-22, on this branch with every change above:

| Gate | Result |
|---|---|
| Laravel suite, SQLite | **595 passed**, 20 skipped (MariaDB-only races), 0 failed |
| Laravel suite, MariaDB 11.8 | **615 passed**, 0 failed (concurrency races included) |
| MariaDB with `STOCK_V2=on LEDGER_READS=true` | **615 passed**, 0 failed |
| New regression tests (`OperationalConstraintsTest`) | 21 passed |
| New race test (`ConcurrencyTest::test_two_cash_tenders_racing_for_one_sale_book_the_cash_once`) | Passed 6/6 runs with the fix; **fails without it** (both tenders booked KSh 2,500) |
| Frontend (`node --test tests/Frontend`) | 49/49 |
| `npm run build` | OK |
| `config:cache`, `route:cache`, `view:cache` | OK (cleared afterwards) |
| `serana:ledger:verify` (local dev DB) | 0 differences |
| `serana:ledger-check` | 227 orders, 0 mismatches |
| `serana:stock:reconcile` | Passed |
| Browser (Chrome via Playwright, ddev) | Studio home shows the system warning (8 stale local sandbox prompts listed); checkout on a 390 px phone: price-change notice, lost-response path ("Still confirming", Check payment status, Close), existing-prompt path (waits, no second prompt). Payment calls were stubbed in the browser; nothing was written. |

Four existing tests encoded the old behaviour (back-to-back prompts, the old PIN message) and were updated: `DepositPaymentTest` ×2, `PaymentAttemptTest::test_paying_an_earlier_stk_attempt_still_settles_the_order` (the retry now comes after the first prompt's window, as a real retry would) and `StaffManagementTest::test_the_owner_sets_a_till_pin_unique_among_active_staff`.

---

## Final Verdict

| Area | Verdict |
|---|---|
| Customer operations | **SMOOTH** (legal pages outstanding) |
| Studio operations | **SMOOTH** |
| Sales Desk | **SMOOTH** |
| Product management | **SMOOTH** |
| Payment | **ROBUST** (statement-only money on shop orders needs OD-1) |
| Stock | **ROBUST** |
| Backend | **ROBUST** (no error alerting or backups yet: operations, not code) |
| Frontend | **CLEAR** (P3 polish listed) |

**OVERALL: OPERATIONALLY COHERENT** for the controlled V1 launch, on these conditions, which still materially affect smooth operation:

1. Delivery options and a pickup address are entered (OD-3). Checkout now refuses clearly without them.
2. Cron and the queue worker run, now visible on the Studio home if they stop.
3. Error alerting and off-server backups are set up (OC-21, OC-22).
4. The owner decides how statement-only M-Pesa money on shop orders is settled (OD-1).
