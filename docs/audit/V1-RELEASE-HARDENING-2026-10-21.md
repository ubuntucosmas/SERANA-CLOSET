# V1 freeze and release hardening (2026-10-21)

This follows the first-principles current-state review (`SERANA-CURRENT-STATE-REPORT.md`). It freezes the V1 boundary, fixes the remaining money-path gap, and records the release decisions.

## 1. The frozen V1 boundary

| Area | In V1 | How |
|---|---|---|
| **Public customer** | Home, shop, categories, product page, per-size choice, bag and cart, delivery or pickup choice, checkout, M-Pesa STK with retry and reconciliation, confirmation, receipt, order tracking, account and Your Orders, bespoke request, quote, signed pay link, deposit and balance by M-Pesa, contact and WhatsApp | Live |
| **Studio** | Overview and attention list, orders (including bespoke), payment problems, refunds, products, stock, gallery, subscribers, staff and roles, activity history, settings, delivery and collection options | Live, staff only |
| **Till (POS)** | Counter sales, returns, click & collect, bespoke intake, paper slips, drawers and Z reports | **Included but controlled.** Internal staff only (`pos.sell`). It shares the money, stock and audit writers with the website (see §7), so it can't contradict the web orders. Public launch doesn't depend on more till work. |
| **Off for V1** | Drops (pre-orders), Stripe, card/PDQ, multi-currency, AI stylist, translations/RTL, VAT, KRA eTIMS, online discounts, more than one shop, offline till, loyalty, wishlist, reviews | `DROPS_ENABLED=false`. The rest isn't in the code. |

## 2. The P1: an order with money on it is never abandoned

**What was wrong.** A new checkout in the same browser expired that browser's earlier unpaid shop orders, to free their stock (`CheckoutController::supersedeAbandonedOrders`). It treated "not paid in full" as "unpaid". A shop order paid short (or swapped to a dearer piece) was therefore expired and its stock released. The money stayed in the ledger, but the order then appeared in neither the payment-problems list nor the Refunds queue.

**The same pattern elsewhere.** These paths closed a not-fully-paid order without checking for money received:

| Path | Before | Now |
|---|---|---|
| Checkout supersede | Expired part-paid orders | Only orders with no money and no open payment decision; the update is conditional, so a payment booked meanwhile keeps the order |
| WhatsApp-order expiry (`stock:release-expired`) | Expired part-paid WhatsApp orders after their hold | Same guard. A part-paid WhatsApp order now takes the part-paid route: once the balance window passes, what was received becomes a refund owed |
| Switching an order to WhatsApp | Allowed on a part-paid order | Refused. The balance is paid by M-Pesa through the pay link |
| Studio → cancel a WhatsApp order | Cancelled a part-paid order | Refused, with a reason |
| Studio → archive | Archived a part-paid order, which took it out of the expiry job | Refused until the money is settled or refunded |

**Already correct, unchanged:**
- The M-Pesa branch of the expiry job.
- `markUnpaid`.
- Till void and close-of-drawer void (they lock the order and check `amount_paid`).
- Bespoke cancellation (`cancelBespoke`, by its deposit rule).
- The overdue resolver (makes a refund owed).
- Purge (refused once there is any payment).
- The Drops closing job (touches drops, not orders).

**One definition.** `CustomOrder::scopeUnfunded()` / `isUnfunded()` means `amount_paid <= 0` and no open resolution. `amount_paid` is derived from the ledger (payments − refunds) on every money write, whatever `LEDGER_READS` says. The expiry job, checkout, the WhatsApp switch, Studio cancel and Studio archive all use it.

**Tests:** `tests/Feature/MoneyNeverAbandonedTest.php` (12 tests). Each checks the order, its holds, the stock left, the ledger rows and the Studio's lists afterwards, not only the HTTP answer. With the fix reverted, 7 of the first 11 fail.

| Case | Test |
|---|---|
| A. unpaid abandoned → superseded, unit freed | `test_an_unpaid_abandoned_order_is_superseded` |
| B, D, G, H. part paid (balance due) → kept, hold and ledger unchanged | `test_a_part_paid_order_is_never_superseded` |
| C. fully paid → kept | `test_a_paid_order_is_never_superseded` |
| E. open payment decision → kept, still on payment problems | `test_an_order_awaiting_a_payment_decision_is_never_superseded` |
| F. a new checkout, then the old order still pays its balance and settles once | `test_the_part_paid_order_still_takes_its_balance_after_a_new_checkout` |
| I. balance never comes → refund owed, in the Studio Refunds queue | `test_an_unpaid_balance_becomes_a_visible_refund_not_a_silent_expiry` |
| WhatsApp switch, cancel, archive, expiry | `test_a_part_paid_order_cannot_switch_to_whatsapp`, `test_a_part_paid_whatsapp_order_is_not_expired_or_cancelled`, `test_an_unpaid_whatsapp_order_still_expires_and_cancels` |

F, as designed: a new checkout is a separate order (the customer may be buying something else). The part-paid order is never replaced; it keeps its hold, its pay link and its balance prompt.

## 3. V1 money invariants and what guards them

| # | Invariant | Enforced by | Tested by |
|---|---|---|---|
| 1 | The payment ledger is authoritative | `OrderSettlementService` is its only writer; `OrderLedger::derive`; legacy columns are projections | `SettlementLedgerTest`, `LedgerCheckTest`, and `LedgerVerifier` after **every** test (`tests/TestCase.php`) |
| 2 | An order with net money received can't silently disappear | `CustomOrder::unfunded()` on every expire, cancel, supersede and archive path; the part-paid route to a refund | `MoneyNeverAbandonedTest` |
| 3 | An order can't become unpaid after settlement | `markUnpaid` updates only `is_paid = 0 AND amount_paid <= 0`; the callback's already-paid branch | `test_a_failed_callback_never_unsettles_a_paid_order`, `test_an_unanswered_second_prompt_on_a_paid_order_just_fails`, `test_cancelled_retry_does_not_fail_order_with_a_live_attempt` |
| 4 | Stock commits once | Commit runs only when the order *becomes* paid; holds move to `committed`; unique attempt and reference keys | `test_reconciliation_settles_paid_retry_after_payment_failed`, `ConcurrencyTest` (MariaDB races) |
| 5 | A duplicate payment creates a visible obligation | Booked; `balance_due < 0` puts it in the Refunds queue ("Paid twice") | `DuplicatePaymentTest`, `RefundQueueTest`, `test_a_second_paid_prompt_on_a_paid_order_is_found_and_owed_back` |
| 6 | An underpayment is a balance due, not a failed order | The shop short-payment rule (balance hold, pay link) | `ShopAmountMismatchTest::test_paying_less…` |
| 7 | An overpayment records the excess owed back | Settled + refund owed | `ShopAmountMismatchTest::test_paying_more…` |
| 8 | A late payment with the stock gone becomes an explicit decision | `commitReservations` refuses; `resolution=needs_resolution/stock_gone` | `LatePaymentStockGoneTest` |
| 9 | Callback and reconciliation may arrive in either order | Booking is idempotent per attempt (DB unique `payment_attempt_id`) and per M-Pesa code | `test_reconciliation_settles_paid_retry_after_payment_failed` (reconcile, reconcile, then late callback) |
| 10 | Staff can't fake a settlement | `is_paid` can't be set in the Studio; manual payments need `money.record` and the exact amount, are booked through settlement and audited | `test_mpesa_shop_orders_still_cannot_be_hand_marked_paid`, `StudioOrderIntegrityTest`, `RoleAccessTest` |

## 4. Duplicate payment with a lost callback (was P2): fixed

`mpesa:reconcile` used to skip pending prompts on orders that were already paid. A second prompt the customer also paid, whose callback was lost, was never asked about, so the money was invisible.

**The fix is one filter.** Reconciliation now asks about every pending prompt from the last 24 hours, whatever the order's state. A confirmed second payment is booked exactly like a duplicate callback: the order stays paid, stock is untouched, and the excess joins the Refunds queue as "Paid twice". A failed second prompt changes nothing. Both cases are tested.

**Remaining, accepted for the controlled release:**
- A prompt still pending after 24 hours is not queried again, and nothing alerts. Detection: the Daraja portal statement against `payment_attempts` with `status='pending'` older than a day. One SQL query belongs in the operations runbook.
- A payment confirmed by reconciliation has no M-Pesa code (STK Query doesn't return one). The Studio can see the code on the M-Pesa statement.

## 5. Staff accounts, settings, storage

- **Staff self-delete: fixed.**
  - Owner, manager and cashier accounts can no longer be deleted from the profile page: the server returns 403 and the form is hidden.
  - Staff leave by being suspended in Studio → Staff, so the ledger, audit log and till history keep their names.
  - Customers can still delete their own accounts.
  - Tests: `ReleaseSafetyTest`.
- **Public settings: fixed.**
  - Pages now carry only an explicit allowlist (`ThemeSetting::PUBLIC_KEYS`, plus the legacy `cat_*_bg` banners).
  - The owner's notification inbox is sent only to staff with Studio access (the Settings screen edits it).
  - Shop and order policy, payment switches, the stock mode and custom-order options never leave the server.
  - Tests: `ReleaseSafetyTest`.
- **Storage portability: fixed.**
  - Bespoke brief image links use the configured public disk (`PublicUrl::absolute`: APP_URL + `/storage/…` locally, the disk URL on S3/R2).
  - The favicon no longer gets `/storage/` twice.
  - Tests cover the local disk and a configured R2-style URL.

## 6. Stock mode and ledger reads for the release

**Stock: the first production release sells from the old batch numbers (`STOCK_V2=setting`, Studio switch off).** Stock per size is switched on only after this process:
1. A physical stock take, per size, at the shop.
2. Studio → Stock: confirm each product's sizes (variants). Products made to order are marked untracked.
3. Enter each size's count (Studio → Stock → Count, with a reason). Never guess.
4. `php artisan serana:stock:reconcile` passes.
5. `php artisan serana:stock:cutover` (preview) lists nothing blocking. It refuses while any tracked size is uncounted, any live hold can't be read as a size, or any sale since the count can't be replayed. The old batch numbers must agree.
6. The owner checks the shop's per-size availability in the Studio and on the product pages.
7. `php artisan serana:stock:cutover --confirm --by=<owner email>` (or the Studio switch).
8. **Rollback:** `serana:stock:cutover --rollback --by=<owner> --reason="…"` (or the Studio switch). `batch_sold` is kept up to date under per-size stock, so the old model sells correctly straight away. `STOCK_V2=off` is the emergency brake without a deploy.

**Ledger reads: V1 stays on `LEDGER_READS=false`.** Money is already written through the ledger, and the legacy columns are projected from it on every write. They are verified equal after every test and by `serana:ledger:verify` (0 differences locally). Switching reads at launch adds risk and no reliability: the answers are identical by construction. Switch only after a production release cycle with `serana:ledger:verify` at 0 on real data. Then A5 (dropping the legacy columns) comes later.

## 7. Till (POS) and delivery checks

**Till.** Every till action goes through the same writers as the website:
- Money: `OrderSettlementService`.
- Stock: `StockService` (or the product locks in the old model).
- Cash: `TillService`.
- Accountability: `Audit`, with role gates `pos.sell` / `pos.approve`.

These follow the one global lock order. That is checked statically (`TillLockOrderTest`) and raced against website checkouts on MariaDB (`ConcurrencyTest`). Counter holds are voided at drawer close only when nothing was paid. Counter orders are excluded from the web expiry job. **No shared-integrity issue was found, so it stays as controlled internal functionality.**

**Delivery** (verified in code and `DeliveryOptionsTest`):
- Options come from the `delivery_options` table, managed in Studio → Settings by the owner, with a reason.
- The fee is computed on the server (`DeliveryOption::feeFor`); any fee the client sends is ignored.
- Pickup and quote options are stored with fee 0.
- A delivery order can't be collected at the till, can't be marked ready for pickup, and must ship.
- A pickup order can't be shipped; it is collected only at its shop, at the till, after the phone check and with nothing owed.

**OWNER CONFIGURATION REQUIRED** before launch: delivery zones, fees, free-over thresholds and ETAs; the pickup shop's address. Only local QA values exist.

## 8. Release-relevant duplication

| Finding | Class |
|---|---|
| Two definitions of "money received" (expiry job vs checkout) | **Fixed before RC** (one scope) |
| Settings sent to the browser: the whole table | **Fixed before RC** (allowlist) |
| Media URL built by hand in the brief and the favicon | **Fixed before RC** (`PublicUrl`) |
| Reconciliation's paid-order filter vs the callback's duplicate rule | **Fixed before RC** (same booking path) |
| Availability in product JSON-LD reads the old batch model under per-size stock | Safe post-V1 (SEO hint only; checkout re-checks per size) |
| Size defaults in `ProductVariant` and `sizes.js` | Safe post-V1 (pinned by node tests) |
| Status labels on server and client; client branches on legacy status strings | Safe post-V1 (A5) |
| Price: catalogue re-read at checkout; the bag is display only | No conflict |
| Delivery total: one delivery line, server-priced | No conflict |
| Permissions: one matrix (`Role::MATRIX`), UI hides what the server refuses | No conflict |
| `MpesaWebhookHandled` dispatched with no listener; the B-01 test asserts it as "the receipt" | Safe post-V1 (receipts ride on `PaymentRecorded`, tested by `OrderReceiptEmailTest`) |

## 9. Pull requests in V1

All four open PRs are in the frozen boundary, and they are stacked: each one is built on the one before.

| PR | Contents | In V1 because | Depends on |
|---|---|---|---|
| #5 D1 | Stock per size (switch, cutover, Studio → Stock, size picker) | Per-size selection is in the boundary; production starts on the old model, with the cutover ready | master |
| #6 E | Staff, till, counter sales, returns, collect | Staff and roles are in the boundary; collection of pickup orders happens at the till | #5 |
| #7 | Frontend alignment (owner's layout, Studio wiring, till recovery) | The current storefront and Studio | #6 |
| #8 D2 | Delivery and pickup options, delivery line, ready for pickup (+ Codex's collection) | Delivery and pickup are in the boundary | #7 |
| #9 | This hardening | P1 fix and release safety | #8 |

Leaving out the till (#6) would mean rewriting #7 and #8, which build on its staff, collection and Studio wiring. The till is safe to ship as controlled internal functionality (§7), so the stack is merged as it is, in order, with merge commits, so the tested history is preserved.
