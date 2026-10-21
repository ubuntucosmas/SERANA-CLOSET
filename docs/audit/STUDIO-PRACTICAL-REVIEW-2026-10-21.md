# Serana Studio: practical completeness review

Reviewed 21 October 2026, against commit `8e313e1`.

## Verdict and scope

The POS frontend **exists**, and the main Studio screens are connected to backend actions. Staff management, till sessions, counter sales, receipts, returns, collection, bespoke intake, refunds and stock adjustments are implemented. Earlier reports that POS/staff screens did not exist are obsolete. The missing Stock import and payment-permission visibility have also been addressed; the swap form now submits separate top/bottom sizes.

This is not yet a complete, consistently simple business workspace. Route coverage is different from completing a person's task. Existing browser evidence in `verification/phase-e/` and `verification/frontend-align/` covers selected successful workflows; it does not establish all error, recovery, historical-reporting and accessibility paths.

Method: inspect routes, controllers, services, Studio shell and panels, POS screen, existing design/audit documents and verification records. Fresh checks: eight frontend test files pass and the production build passes. No fresh authenticated browser walkthrough or payment transaction was performed for this review. Findings below are source-confirmed behavior or explicitly labelled recommendations. No application behavior was changed.

## First principles

Each operational view must answer: **who** owns the work; **what** happened or is needed; **when** it happened/is due; **where** the sale, stock or handover belongs; **why** it needs attention; **how** to act and confirm the result. These answers should appear where decisions happen. A large help panel does not compensate for unclear controls.

## Coverage

| Area | Present | Remaining practical limit |
|---|---|---|
| Overview | Queues, payment exceptions, stock alerts, till differences, refresh and definitions | Priorities are queue-based; no complete assignment/deadline management or consolidated net-sales reporting |
| Orders | Server search/filter/load more; separate payment and production information; wide detail modal; quote, deposit, notes, evidence and actions | Free-text swap sizes; navigation and draft preservation need work |
| Payments/refunds | Recording, signed pay links, ledger review, refund queue and settings | No general searchable audit-history screen; POS payment recovery needs improvement |
| Products | Search, compact table, preview, editing and archive/restore | Stock summary still speaks legacy batch language |
| Stock | On hand/held/available per size; count, receive, damage; named adjustment history and switch preflight | History capped at 50 per product; single-shop UI; no transfer/location-management UI |
| POS | PIN unlock, opening float, catalogue/search/scanning, sizes, discounts, sequential split payments, cash change, M-Pesa, exchange credit, void and receipt | Draft recovery, payment polling recovery, catalogue limits, navigation and accessibility gaps |
| Orders desk | Reference/phone collection verification, balance payment, returns/restock/write-off and exchange credit | Online checkout does not yet offer pickup; exchange requires separate return and new-sale steps |
| Bespoke intake | Customer, garment, deadline, measurements, photos, quote and deposit | Reload recovery of the intake/payment context is not provided on this screen |
| Till review | Drawer open/close, payouts, variance/approval, Z report and Studio history | Last 30 sessions only; no date/search/export controls; historical report printing absent in Studio |
| Staff | Add, role change, suspension/reactivation, PINs, one-time password and last login | No searchable activity history, owner-side password reissue control or workforce/task scheduling UI |
| Settings/content | Store appearance, catalogue content, payment/order/customisation settings, gallery and subscribers | Operational till rules live under Staff, while other policies live under Settings |
| Pre-orders | Management screen gated by feature flag | Flag-off also hides management of existing campaigns; decide whether old reservations still need servicing |

Payroll, attendance, purchase orders, supplier management, costing/profit, full multi-branch operation and card-terminal tender are not delivered by the current screens. These are scope decisions, not automatically defects to add to the product.

## Prioritised findings

| Priority | Finding and evidence | User consequence | Practical change |
|---|---|---|---|
| P1 | POS `resume()` fetches a sale once but does not call `watchPrompt()` for a pending M-Pesa prompt (`resources/js/Pages/Pos/Till.vue`). Polling also stops after 40 attempts without a recovery control. | After reload/resume or a slow payment, the cashier can remain on an outdated balance. | Resume pending-payment polling; show last checked time, timeout explanation and Check payment status. Do not invite another charge while one is pending. |
| P1 | The bag/customer/discount fields exist only in Vue refs before `ringUp()`; no draft storage or navigation guard. | Reload before Take payment loses the unsubmitted sale. Today's sales recovers server-created sales only. | Save a clearly identified draft per register/operator, restore or discard explicitly, and warn before leaving unsaved work. |
| P1 | Customer checkout has no delivery-option/pickup selection, while the till has collection actions. | Collection works at the desk but is not an end-to-end online customer journey. | Complete delivery/pickup selection and carry the choice, fee and handover status through checkout, Studio, messages and receipts. |
| P1 | The collection/intake payment context is not restored after reload; Today queries orders attached to the current till session. | A cashier needs manual lookup/reconstruction after interruption; do not claim every payment context is recovered by Today. | Provide Find order / Continue payment for shop and bespoke orders, with reference/customer lookup and accurate live balance. |
| P2 | `currentTab` is local; `goTab()` does not update browser history. Some panels reload with `tab`, others do not (`StudioDashboard.vue`). | Refresh/back/link behavior is inconsistent across sections. | Make section/filter/order state URL-driven; support Back and preserve unsaved drafts explicitly. |
| P2 | POS Today uses `till_session_id`, not calendar day (`CounterSaleService::today`). | Two drawers opened on one date do not produce a complete Today view. | Rename to This drawer, or implement a true date-based Today view with session filters. |
| P2 | Till history uses `take(30)` without pagination/date search (`StudioController::tillHistory`). | Managers cannot review older sessions through this screen. | Date range, operator/register/state filters, pagination and export/print for the chosen session. |
| P2 | Product `stockLine()` checks `batch_limit` and displays Unlimited or available-of-batch (`ProductsPanel.vue`). | Catalogue summaries can conflict conceptually with counted-per-size or made-to-order stock. | Use the active stock mode: Stocked / Made to order, available/held totals and a View sizes action. |
| P2 | Swap sizes are free-text inputs (`StudioDashboard.vue`). | Staff must guess valid sizes and learn availability from failed submissions. | Product-dependent offered-size selectors, separate top/bottom where needed, with availability before Save swap. |
| P2 | Stock history is limited to 50 movements per product (`StudioStock::forStudio`); reasons exist in audit records but there is no general audit viewer. | Who changed what and why cannot be investigated comprehensively in the interface. | Searchable movement/audit history with date, actor, product/order filters and linked records; show history limits meanwhile. |
| P2 | POS catalogue limits results to 60 with no paging/more-results indicator (`SaleController::catalogue`). | Browsing is incomplete; common-name searches can hide matches beyond the limit. | Pagination/load more, visible result count and optional category filter. Newest first does not solve truncation. |
| P2 | Adding the same product/size again increments quantity without checking availability (`addLine`), although the bag's plus button does check the individual line limit. Shared variants across different set combinations are not totalled. | Repeated product selection can bypass the visible quantity limit; shared set components can exceed known availability before ringing up. | Apply one quantity guard to every add/increment path across lines sharing a variant, explain limits inline, and retain server validation for concurrent sales. |
| P2 | Studio More and POS size overlays are custom fixed elements without a focus trap/Escape handler; More's backdrop is a child overlay. | Keyboard dismissal/focus is incomplete; backdrop dismissal needs correction/verification. | Use the established dialog pattern, close control, Escape/backdrop behavior, focus return and scroll containment. |
| P2 | Desktop Studio has many fixed-height sidebar entries without an explicit scrollable navigation region (`AdminShell.vue`). | Short desktop windows risk hiding lower sections or account actions. | Scroll the navigation independently; keep account/till controls reachable. Verify short landscape and zoomed layouts. |
| P2 | Stock/staff/money actions frequently use browser prompts; POS labels include Take it and Close and print Z. `closeTill()` does not invoke print. | Weak decision context and misleading action promises. | Inline reason/approval dialogs; explicit labels such as Record deposit or Close drawer, with a separate Print closing report action. |
| P3 | Till rules live inside Staff; POS has no direct Studio return link or theme switch and forces dark mode. | Policies are hard to locate; management navigation and appearance differ between workspaces. | Move shop policies to Settings with a contextual link; add Studio return for eligible roles; offer readable light/dark choices if shop use requires both. |
| P3 | POS catalogue search shares one global `busy/errors` state with transactional requests and has no stale-response protection. | Slow search responses can overwrite newer results or disturb transactional feedback. | Separate search loading/errors, cancel or sequence search requests, reserve payment feedback for the active payment. |

P1 means complete before describing the workflow as interruption-safe or end-to-end. P2 means a meaningful daily-operation problem. P3 is a consistency/efficiency improvement. Source inspection is not evidence of an actual incorrect charge or stock loss.

## Recommended navigation

Keep one location for each capability; group links instead of duplicating modules:

| Group | Destinations | Core question |
|---|---|---|
| Daily work | Overview, Orders, Sell at till | What must I do now? |
| Money | Refunds, Till sessions | Who is owed, and did the drawer balance? |
| Inventory | Products, Stock | What do we sell and what can we fulfil? |
| Business setup | Staff, Settings | Who may act and which rules apply? |
| Store content | Gallery, Subscribers, enabled Pre-orders | What customer content or campaign am I managing? |

On phones, choose primary destinations by role and actual daily frequency; leave refunds/stock visible as shortcuts when they need attention. Use More for less frequent tasks, not as the only clue that a critical exception exists. Preserve the existing compact tables, numeric alignment, sticky headings and action columns where they already work.

## What to retain, simplify or defer

- Retain identity/PIN checks, distinct payment and production states, reasons for money/stock changes, customer verification at handover, stock holds and backend guards. Each answers a business-control question.
- Retain short contextual screen help. Remove repeated explanatory prose from the main work area when labels and status already communicate it; keep detailed definitions expandable.
- Keep stock-model migration/rollback behind owner setup/advanced controls once operational, rather than making it the recurring main stock task.
- Defer payroll, attendance, multi-branch setup and elaborate reporting until there is an agreed business need. Do not add empty navigation for them.
- Replace ambiguous terminology: Today → This drawer if session-scoped; Till in Studio → Till sessions; Z report → Closing report (Z); Bespoke → Custom orders if that is the language staff use.
- Show statuses in text as well as colour. Reuse green completed, amber needs attention, red failed/blocked, neutral draft/closed; do not conflate payment success with goods handed over.

## Acceptance scenarios for the next implementation

1. Cashier builds a sale, reloads before payment, restores it; then reloads during M-Pesa and sees the final server-confirmed result without resending payment.
2. Customer chooses pickup online; staff finds the order, records any balance, checks identity and records collection. Receipt and Studio agree.
3. Cashier interrupts a bespoke deposit or collection balance; another authorised operator can find the order and continue with the correct balance.
4. Manager finds and prints a drawer from more than 30 sessions ago and traces its variance to the actor/reason.
5. Manager inspects a counted product in Products and Stock; available, held and made-to-order wording agree. A set swap accepts only valid available parts.
6. A phone/keyboard user opens and closes More, size and reason dialogs; focus returns correctly, no action is obscured, and short desktop windows retain all navigation.
7. Back, refresh and shared links return to the intended section/order/filter without silently discarding edits.

These scenarios supplement existing unit/feature tests and should be verified on desktop/tablet/mobile, light/dark where supported, and owner/manager/cashier roles. Do not use real financial transactions for UI verification.


## Implementation follow-up (21 October 2026)

The first operational changes from this review are now implemented:

- POS saves unsubmitted bags per operator/register in tab-scoped storage, excluding PINs; recovery requires an explicit Restore or Discard action. Storage failures are explained. This is not cross-device recovery.
- Pending M-Pesa sales resume polling; a manual status check shows the last successful check and explains connection/time-limit interruptions. The sale payment submit control is blocked while the prompt remains pending. Backend settlement remains authoritative.
- All add/increment paths share a per-variant capacity check, including shared top/bottom combinations. Three regression scenarios cover shared parts and untracked stock.
- Catalogue search uses separate loading/error state and ignores stale responses.
- Studio section changes use Inertia URLs and synchronise with section props; this does not yet add unsaved-form guards.
- Products use the active stock model's language and link to Stock.
- Till history is paginated in groups of 30 with older/newer navigation and totals, covered by a 31-session integration test. Date search/export/printing are still separate work.
- Studio navigation scrolls in short windows; More and the POS size picker use the shared native dialog.
- Session-scoped sales are labelled This drawer; Bespoke becomes Custom orders; the close action says Close drawer rather than promising automatic printing. Studio return is available to eligible POS operators.

Remaining substantial work includes delivery/pickup selection, recovery of collection/custom-payment context, searchable full audit/movement history, offered-size swap selectors, catalogue pagination, historical closing-report printing, operational settings regrouping and full cross-role browser coverage. These changes must not be represented as completing the entire audit.

Validation of this follow-up: nine frontend test files pass, production build passes, Studio statistics tests pass (three tests / 21 assertions), and the new till-history pagination test passes (28 assertions). Chrome using browser-only mocked POS endpoints verified explicit draft restore/discard after reload, resumed pending-payment polling, manual status checks, the pending-payment submit guard, no JavaScript page errors, and no horizontal overflow at 390/1440px. Mock checks make no financial transactions and do not replace authenticated end-to-end payment tests.
