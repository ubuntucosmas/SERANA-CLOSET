# Size quantities: current implementation and proposed improvements

Reviewed 21 October 2026. This is a research and implementation proposal; no stock quantities, tracking flags or selling modes were changed.

Implementation follow-up: the four priorities are now covered by the [size quantities workflow](../ux/SIZE-QUANTITIES.md). The findings below describe the pre-change audit.

## Finding

Per-size stock already exists. Improve the existing stock workflow and its safeguards rather than introduce another quantity field or a parallel inventory system.

A read-only check of the local DDEV application returned:

| Measure | Local result |
| --- | ---: |
| Per-size selling enabled | Yes |
| Products | 70 |
| Products with counted-stock tracking | 62 |
| Active size/part variants of tracked products | 235 |
| Variants with a count timestamp at the shop | 60 |
| Variants without a count timestamp at the shop | 175 |
| Locations / online fulfilment locations | 1 / 1 |

These figures describe the local database, not production. An absent count timestamp does not prove zero stock: receiving creates movements without stamping a physical count.

## How staff enter quantities today

Owner or manager: Studio → Stock → search and expand a product.

1. If it is marked Made to order but should sell from counted stock, choose Stock it instead and supply a reason.
2. Check the offered sizes. Offer a new size if needed; adding a size does not add units.
3. For the first verified inventory, choose Count on each size, enter the total unsold units counted, and save. Include a real zero when a size was checked and none exists.
4. For a new delivery, choose Receive and enter only the additional units arriving. For example, receiving 3 when 4 are recorded leaves 7.
5. For damaged or missing units being removed from the selling pool, choose Write off and enter the units to remove with a reason.
6. Review the per-size stock history and resulting available balance.

Count replaces the recorded quantity; Receive adds to it. They must remain separate actions. Cashiers can select sizes and change sale quantities in POS, but cannot change inventory. The owner alone controls switching between the old batch model and per-size selling. Locally, that switch is already on.

## Existing implementation

- `app/Models/ProductVariant.php`: identity is product + garment part + size; SKU and barcode identify the variant. A set has separate top and bottom sizes.
- `app/Models/StockLevel.php`: quantity per variant and location.
- `app/Models/StockReservation.php`: order holds.
- `app/Models/StockMovement.php`: additions and deductions with actor, reason and time.
- `app/Services/StockService.php`: common stock writer; transactions and ordered level locks; negative balances rejected; counts recorded as correction deltas.
- `app/Http/Controllers/Admin/StockController.php`: count, receive, write-off, size offering and tracking endpoints. Current stock-entry actions target the first online fulfilment location.
- `app/Support/StudioStock.php`: size rows with on hand, held, available, count timestamp, and the latest 50 movements per product.
- `resources/js/Pages/Admin/Partials/StockPanel.vue`: one size-entry form at a time.
- `app/Http/Controllers/Pos/SaleController.php`: returns per-size availability at the shop to the cashier.
- `resources/js/Support/posStock.js`: totals use of a shared variant across cart lines, including sets that share a top or bottom size.

For the current unsold-stock pool:

`available = max(0, recorded on hand − active holds)`

Availability is calculated, not an independently editable field. A set requires both selected parts: 4 medium tops and 2 large bottoms can support at most 2 additional medium-top/large-bottom sets before other holds or cart demands.

## Gaps and their consequences

### 1. Product setup and receiving require too many disconnected steps

The product editor directs users to Stock but does not provide a size/quantity matrix. Stock entry saves one size at a time; adding a new size and entering its quantity are separate operations. The Products → Manage stock action opens the stock section without selecting that product.

Proposal: an explicit Sizes & stock action on the product, a focused product stock view, and a matrix for initial quantities or a delivery. Keep initial count and receiving clearly named.

### 2. Counts lack protection against outdated screens

The server locks the current row before applying an absolute count, which protects transactional writes. However, the request contains no expected stock version or movement checkpoint. A user can open a count at 5, a sale reduces it to 4, and saving the old count of 5 can restore the sold unit. Locks alone cannot detect that the operator's observation is outdated.

Proposal: send a per-row stock version or movement checkpoint with each count. Reject stale counts and show what changed. A real stocktake also needs a defined counting boundary: pause movements for the counted area briefly, or reconcile movements against a recorded count time. Version checks alone do not resolve stock moving during the physical count.

### 3. Receiving lacks request deduplication

The current move endpoint has no idempotency key. The UI disables submission during processing, but an identical retried request can still create a second receipt.

Proposal: one operation UUID per submission, persisted with a unique constraint and payload fingerprint. Return the original result for a matching retry; reject reuse with different contents. For a multi-size delivery, create an operation header and movement rows together in one transaction. The same supplier reference can legitimately cover partial receipts, so it should not be the sole deduplication key.

### 4. “On hand” needs an explicit counting definition

`StockService::commit()` creates negative sale movements when payment commits the stock, before later handover. Paid goods waiting for pickup can therefore still be physically present even though this balance no longer includes them.

Proposal for the first release: label the count as unsold stock; instruct staff to exclude paid orders awaiting collection and isolate those goods physically. Show a separate paid-awaiting-pickup indicator where reliable order-line data permits it. If full physical inventory is required later, introduce explicit committed/awaiting-fulfilment stock and migrate the lifecycle carefully. Never deduct the same goods again at handover.

### 5. Incomplete setup is not prominent enough

The UI shows last-count status, but does not offer a dedicated completion queue after per-size mode is already enabled. New sizes start without units. Some uncounted sizes may have received movements, so they need inspection rather than automatic zeros.

Proposal: filters for Needs initial count, Zero available and Held exceeds stock. Distinguish Not counted from Counted zero. Make setup completion visible alongside product publication and tracking choices.

### 6. Variants need stronger identity rules

The model has size and garment part, but no separate colour dimension. Size entry trims whitespace but does not establish a canonical case. SKU normalization strips punctuation, so distinct size labels can collide on the unique SKU constraint. Offering or withdrawing a size acts on that label across both parts of a set.

Proposal: validate normalized size labels and SKU collisions with a clear message. Make actions explicit: offer a size for both set parts, or manage one specific variant. Keep existing IDs/history stable. If colour changes the physical stock pool, introduce colour variants deliberately; a product photo or free-text colour choice is not enough to track separate colour quantities.

### 7. Secondary improvements

The stock service supports transfers, but the current stock-entry UI targets one shop and has no location selector. Do not present multi-location stock entry until location selection and permissions are implemented. Add searchable/paginated history before calling the current 50-row preview a complete inventory ledger. Build CSV import only after batch validation, preview and deduplication are in place.

## Recommended implementation

### First: make the existing workflow discoverable and accurate

- Product → Sizes & stock opens the chosen product.
- Clear Stocked / Made to order choice, distinct from whether the product is offered for sale.
- Table: Size, recorded unsold quantity, held, available, last count, action.
- Visible Count existing stock, Receive delivery and Write off actions with examples.
- Explicit treatment of paid goods awaiting pickup.
- Needs initial count queue; zero remains distinct from blank.

### Second: safe batch entry

A delivery matrix should show:

| Size | Current recorded units | Units received | After receipt |
| --- | ---: | ---: | ---: |
| S | 2 | 3 | 5 |
| M | 4 | 6 | 10 |
| L | 1 | 2 | 3 |

For sets, show Top and Bottom groups rather than a single ambiguous set quantity. Require a reason; optionally capture supplier/delivery reference. Preview changes before saving. Blank means no change, and count zero means an intentional observed zero.

Extend the existing controller/service with a batch operation, rather than invoke one HTTP request per size. Validate all rows, reject duplicates, authorize the operation, lock affected levels in a consistent order, check count versions, and apply all rows atomically. Record a shared operation ID and the existing per-variant movements. Reuse `StockService` and account for its legacy rebaseline behavior; review lock ordering and run real-database concurrency tests before releasing the batch writer.

### Third: improve operational reporting

Add low-stock thresholds per size, receipt references, searchable movement history and barcode-assisted entry. Add colour/location complexity only when it represents how the shop actually stores and sells goods.

## Required verification for changes

- Initial count, counted zero, receiving and write-offs update the correct size only.
- A rejected row leaves the entire batch unchanged.
- Retrying a receipt does not increase stock twice.
- An old count cannot overwrite a newer sale or stock adjustment.
- Holds reduce availability; stock below held quantity is flagged for resolution.
- POS and website cannot both purchase the final unit.
- Shared set components are enforced across cart lines.
- Paid pickup goods cannot re-enter availability through a routine shelf count.
- Cashiers cannot change stock; owner/manager actions retain actor and reason.
- Different garment parts, normalized labels and any future colour/location remain distinct.
- Batch locking/rebaseline behavior is checked on MariaDB, not inferred from SQLite.

Existing verification run during this audit: 41 tests passed, 155 assertions, covering Studio stock entry, stock service rules, reservations, cutover and stock mode. These isolated tests confirm existing behavior; they do not verify the proposed safeguards or replace real-database concurrency checks.

## Research sources and implications

1. [Shopify inventory fundamentals](https://help.shopify.com/en/manual/inventory-and-locations/fundamentals): quantities belong to variants; separate quantities in stock from quantities still sellable. This supports the existing per-size architecture.
2. [Shopify inventory lifecycle and states](https://shopify.dev/docs/apps/build/orders-fulfillment/inventory-management-apps): separates incoming, available, committed and unavailable stock. This highlights the need to define paid-but-uncollected goods and to keep incoming deliveries unavailable until received. These are reference concepts, not a claim that Serana implements Shopify's state model.
3. [Shopify absolute quantity updates](https://shopify.dev/docs/api/admin-graphql/latest/mutations/inventorySetQuantities): documents compare-and-swap protection for counts and idempotent requests. This supports the proposed safeguards against outdated counts and duplicate submissions. Serana remains a Laravel application; no Shopify integration is proposed.

Recommendation: complete the current count/setup review, then implement the focused size matrix and batch receiving together with stale-count and retry protection. Preserve the existing inventory service and stock history.
