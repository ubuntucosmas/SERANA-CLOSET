# Studio and Sales Desk: current-state audit

Inspected before implementation: routes/web.php; StudioController and Admin panels; AdminShell; PosController, SaleController, DeskController, TillController; StudioWorkflow; OrderSettlementService, StockService, TillService; role and workflow regression tests.

## Jobs and current workflows

| Role | Job | Information needed | Primary action | Failure risk | Current screen | Current problem |
|---|---|---|---|---|---|---|
| Owner | Triage business | Payment evidence, refunds, order queues, low stock, cash differences | Review exception | Missed refund or duplicate collection | Overview | Weekly metrics precede urgent decisions |
| Manager | Progress orders | Brief, quote, paid, balance, items, handover | Next valid stage | Shipping unpaid or wrong handover | Orders / detail modal | Money and next action require inspection |
| Manager | Maintain catalogue | Product, size, available, reserved, on hand | Edit / adjust with reason | Overselling | Products / Stock | Technical cutover language belongs to owner |
| Owner | Manage access | Role, active state, PIN state | Add / suspend / reactivate | Permission leakage | Staff | Register wording inconsistent |
| Owner / Manager | Investigate | Actor, time, action, record, change | Open record | Misreading raw event names | Activity | Dotted action identifiers shown |
| Cashier | Complete walk-in sale | Product, size, stock, price, total, payment confirmation | Find → add → pay → receipt → next sale | Duplicate payment, stale stock | /pos sell | New sale and receipt priorities, weak cash preview |
| Cashier | Hand over pickup | Reference, phone, balance, pickup eligibility | Verify phone / hand over | Wrong customer or unpaid order | /pos orders | Pickup grouped with returns |
| Cashier | Start/end shift | Opening cash, movements, expected, counted, difference | Open / close register | Unexplained variance | /pos drawer | Small differences lack visible live comparison |

## Existing architecture

Studio is one Inertia page with grouped navigation and independently loaded Stock, Staff, Activity and register reports. Orders already use 30-row server pagination, search and queue filters. Most other datasets load on every full visit. Home has payment attention, stock and order queues, but weekly metrics come first. Settings stack unrelated forms. Sales Desk is a separate JSON-driven transaction workspace with PIN operator attribution, idle lock, saved cart drafts, stock-aware sizes, server-created sales, payment polling, pickup phone verification, returns, bespoke intake and closeout.

Money uses OrderSettlementService and audited ledger rows. Stock uses StockService and transaction locks; configurable stock cutover remains an owner operation. TillService owns expected cash and reports. Existing route abilities remain authoritative; cashiers cannot enter Studio.

## Business-rule discrepancy — documented before any financial change

Current behaviour: TillService requires a variance explanation and manager acknowledgement only when the absolute cash difference exceeds ShopPolicy::tillVarianceAlert(). The requested behaviour requires an explanation for every nonzero difference. Risk: silently changing this service would change an established financial policy and current tests. Proposed change: require an explanation in the closeout interface for every visible difference; retain the existing server threshold and explicitly report that enforcing every difference for all clients requires a separate approved policy change. Do not modify TillService in this UX work.

## Constraints

Preserve internal Till identifiers, register keys (including existing `Till 1`), URLs, ledger adapters, payment idempotency, stock commit rules, approval thresholds and audited owner overrides. Never infer confirmed payment from a local preview. No barcode subsystem is required: SKU/barcode matching already exists. No new financial methods or cash-added endpoint should be invented.
