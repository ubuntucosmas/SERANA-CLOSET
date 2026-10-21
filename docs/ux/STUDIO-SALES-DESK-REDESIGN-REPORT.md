# SERANA-CLOSET — STUDIO & SALES DESK UX REDESIGN REPORT

Implemented against the existing Laravel/Inertia/Vue application. The [current-state audit and role/job matrix](studio-sales-desk-audit.md) record the inspected architecture, workflows and financial-policy discrepancy before implementation.

## Terminology Decision

**Sales Desk** names the transaction workspace; **Register** names its session; **Cash drawer** refers to physical cash. Internal Till classes, route names, database tables and persisted register keys remain intact. Existing `Till 1` keys display as Register 1 in Sales Desk, Home and session history. Payment-attention language describes customer obligations rather than settlement machinery.

## First-Principles Jobs

Owner: see exceptions and decide who must act. Manager: progress orders using payment, brief, stock and handover facts. Cashier: find → add → pay → confirm → next customer. The audit includes information needed, primary action, failure risk, current screen and previous problem for each job.

## Previous Information Architecture

Studio grouped daily work, money, inventory, setup and content. Bespoke shared the generic order list. Settings stacked unrelated configuration. Home placed weekly figures before urgent work. Sales Desk was distinct technically, but used Sell, Orders, Drawer and Till terminology inconsistently.

## New Information Architecture

Home; Selling; Catalogue; Customers & content; Operations; Configuration. Bespoke has a server-filtered workspace. Categories remain contextual to Products. Delivery and pickup are within Settings. Payment problems are an order work view and Home attention items. Disabled Drops stay out of navigation, quick actions and returned campaign data.

## Studio Navigation

Overview; Orders / Bespoke / Refunds; Products / Stock; Photo gallery / Email subscribers; Sales Desk History / Staff / Activity; Store settings. Abilities filter navigation. Open Sales Desk is an explicit product switch. Optional workflow guidance is collapsed to leave operational content first.

## Studio Home / Attention

Next decision and payment/refund attention precede metrics. Financial items retain customer, order, amount, reason, age and direct review action. Home shows active order queues by default; Show all queues reveals empty queues. Low stock and register differences remain alongside the work. Completed and overlapping payment subqueues no longer compete in the default Home queue list. Cancelled-order refunds remain in Home attention and Refunds; the active Orders Attention view excludes cancelled work.

## Orders

Task views: Needs attention, New / to start, In production, Ready for pickup, Ready to ship, Completed and All. Existing oldest-first sorting, search and 30-order pagination remain. Rows now show amount received, balance or refund owed, and the server-presented next action. Bespoke rows surface occasion and deadline when supplied. Ready to ship excludes pickup orders.

## Order Detail

A four-part money summary appears before detailed requirements and editing: total, received, balance due, refund owed. Shared fulfilment labels distinguish finished pickup work from dispatch. The server supplies the next action and explains payment/deposit blockers using existing backend checks. Routine next-stage actions use the existing save endpoint. Quote guidance focuses its field. Collect at Sales Desk opens the existing order directly using the supported order deep link. Detailed notes, evidence, quote controls and audited overrides remain available.

## Payments / Refunds

A canonical operational payment presenter prioritises refund obligations and unresolved decisions before a Paid badge, and recognises part payment. Customer tracking wording remains unchanged. Existing refund reasons, source payment evidence, partial refunds, method/reference validation and recorded refund receipts remain in the established queue. Payment review and money recording still use the original services and permissions.

## Products

The existing searchable operational table remains, with image, garment type, category, price, availability and stock. Product-workspace responses include size SKUs and search matches those SKUs. Removed the duplicate View sizes control from the product-name cell; Manage stock remains in the action column. Existing archive, restore, preview and edit controls remain.

## Stock

Uses Reserved consistently alongside On hand and server-calculated Available. A size whose reservations exceed on-hand stock explicitly says it needs review. Existing location, counted state, receiving, counting, write-off reasons and movement history remain. Cutover and reconciliation controls retain owner-only visibility and server authorization.

## Bespoke

The dedicated workspace always filters to bespoke jobs, including partial refreshes. It hides irrelevant shop/pre-order type chips. Existing brief, measurements, reference images, occasion, deadline, quote, deposit, payment link and production controls remain in one order detail workspace. Server guidance prioritises adding the quote, receiving the deposit and advancing the next production stage. No unsupported quote-item or scheduling database was introduced.

## Staff

Owner-only staff management retains name, role, suspension, last login, one-time-password state, PIN reset and audited access changes. Create staff sign-in now appears above the staff list, with optional temporary-password and confirmation fields; leaving both blank generates a password. Issued sign-in details remain visible until dismissed or the page is left, and chosen passwords are hashed without being written to audit records. Sales Desk wording replaces Till in PIN and policy controls. Existing staff cards now provide owner-only detail edits, temporary password resets, session revocation and redacted access history. Self-service credential changes are audited as well. See [Staff access management](STAFF-ACCESS-MANAGEMENT.md).

## Activity

Action and changed-field identifiers render as readable words, including Register in place of Till. Actor, time, subject, reason and before/after values remain visible together. Raw payloads and credentials are not made the default interface. Existing date, actor, reference and stock-movement filters remain.

## Settings

Store & contact, Delivery & pickup, Payments and Bespoke are separate task sections. Owner-only payment and policy sections use existing abilities. Forms retain their established save actions and error handling. Register policy remains in the existing owner-only Staff workspace; no additional access authority was introduced.

## Sales Desk Naming

Page title, navigation, unlock language, session history, PIN labels and printed register summary use Sales Desk / Register terminology. Physical cash labels remain explicit. Persisted register identifiers and all technical Till contracts remain compatible.

## Sales Desk Shell

Distinct transaction shell, operator and open/closed register status; no Studio charts. New sale, Session sales, Order pickup, Custom orders and Register are separate modes. Desktop/tablet keep catalogue and cart side by side; payment uses a focused single column. Workspace instructions are optional.

## New Sale

Optional customer details and supported discount approvals remain. Cart lines show unit and line totals; quantity targets are larger. The cart total is labelled a preview. Continue to payment confirms price and stock on the server. After confirmed payment, New sale is primary and View / print receipt is secondary. Starting the next sale reloads products and focuses search.

## Product Selection

Search supports name, partial SKU, size and category, with category filter buttons. Existing exact SKU/barcode behaviour remains. Cards expose available sizes and unavailable products cannot be added. Single-option products retain one-action addition; multi-size products use the existing size picker. Server stock remains authoritative at checkout.

## Payment

Server total, received and balance are explicit. Cash received produces a change preview, with confirmed change shown only after the backend responds. Payment buttons show recording feedback. A shared in-flight guard rejects overlapping mutations without issuing another request. Pending M-Pesa disables another prompt and method switching; manual status checks and established polling remain. Connection ambiguity does not manufacture payment success.

## Pickup

New sale and Order pickup stay separate. Existing reference/phone lookup, server-calculated money position, handover eligibility and customer verification remain authoritative. Hand over order is available only when permitted and a phone is present. Studio's collection action links directly to the supported Sales Desk order recovery flow. Existing returns remain contextual in the order workspace.

## Register Open/Close

Opening cash, cashier and server-provided session timestamp remain visible. Cash removal retains reason, manager approval and now an explicit confirmation. Closeout shows expected, counted and live difference; every visible nonzero difference requires an explanation in the interface. Closing waits for the register report and displays Closing register feedback. Backend approval thresholds remain unchanged; see Remaining Business Decisions.

## Register History

Sales Desk History retains staff, dates, register state, expected/count/difference, explanation, unfinished sales and printable session summary. Date/staff/state filtering and pagination remain. Pagination has a valid named group for assistive technology.

## Role-Based UX

Owner receives all existing abilities. Managers retain Studio and Sales Desk but cannot see owner Staff navigation or payment configuration. Cashiers use Sales Desk with appropriate order recovery and pickup access; Studio navigation is absent. Existing route middleware, operator PIN permissions and backend guards remain authoritative.

## Shared Components

Added OrderMoney for the four financial positions. Reused useCurrency across operational money displays and shared fulfilment labels in order detail. Added operationalPaymentStatus to centralise internal obligation-first badges. A shared registerLabel formatter changes displayed names without changing session keys. Existing StatusBadge, Modal, table classes, state colours, focus styles and operational surface tokens remain canonical.

## Performance Improvements

Unrelated gallery images, archived gallery, subscribers, archived products, refund queues and campaign reservations are no longer fetched for every full Studio visit. Closures skip that work on partial requests. SKU relations load in the Products workspace. Existing lazy Stock/Staff/Activity/Register panels, order pagination and server filtering remain. Products and Home aggregates are still loaded across Studio visits; further pagination/deferred-prop work should be measured against actual catalogue size.

## Accessibility

Retained labelled controls, native modal focus/Escape behaviour, visible keyboard focus, text status labels and live feedback. Decorative shell/tab icons are hidden from assistive technology. Search receives focus on opening and next sale. Fixed invalid ARIA naming on history pagination and raised dark-theme secondary-text contrast in both workspaces. Automated axe results and their tested surfaces are recorded in verification; automated scans do not establish complete WCAG compliance.

## Responsive Behaviour

Checked 1440px desktop, 768px tablet and 375px mobile. Sales Desk splits catalogue/cart from tablet width, uses a focused payment column and stacks on phones. Studio retains desktop sidebar and mobile navigation. Order detail and money summary adapt to narrow widths. Tables retain contained scrolling where necessary rather than causing whole-page overflow.

## Workflow Simplifications

Urgent work before metrics; active queues before empty ones; readable financial obligations in lists; server-guided next stage; direct Studio-to-pickup recovery; focused settings sections; size/category/SKU product search; change preview; primary next-sale action; visible closeout difference.

## Redundant UI Removed

Duplicate product stock control; always-expanded workspace instructions; unrelated settings forms competing for attention; disabled campaign shortcut/data; duplicated Home attention subqueues; completed-history queue in daily-work triage; receipt competing with next sale; default technical activity identifiers.

## Backend Rules Preserved

No changes to financial/stock schemas or to OrderSettlementService, StockService, TillService, CounterSaleService or ShopDeskService. One additive users.session_version migration supports staff-session revocation. Ledger authority, payment references/idempotency, refunds, order locking, stock commit/reservation rules, cash audit, owner overrides and role gates remain intact. Read-only queue/presentation changes do not authorize transitions. TillController's opening response now includes existing server session metadata.

## Test Results

See [verification notes and screenshots](verification/README.md) and [machine-readable browser results](verification/browser-results.json). Final backend validation: 555 tests passed (9,431 assertions), 17 MariaDB-only concurrency tests skipped, 11 frontend test files passed, and production build passed. The 56-page browser matrix and six final register-label checks passed with no page overflow or uncaught JavaScript errors; desktop light/dark axe scans reported no findings. Browser interactions replay server-generated in-memory fixtures; backend integration tests validate actual endpoints. No live store database or real M-Pesa transactions were used.

## Remaining Business Decisions

1. **Every cash difference on every client:** the UI requires an explanation for every visible difference; the established server rule only requires explanation and manager approval above the configured threshold. Enforcing explanations for all API clients requires a separate financial-policy change. Current behaviour, risk and proposed change were documented before implementation in the audit.
2. **Additional cash methods:** cash-added and standalone register-adjustment workflows were not added because the current backend does not support them. Do not imply they exist in the interface.
3. **Further bespoke capability:** itemised quotes, enforced scheduling/deadline policy would require separate product/backend work; owner password reset is now implemented. Existing quote, brief, deposit, PIN and account-recovery capabilities remain available.
