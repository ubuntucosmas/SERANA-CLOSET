# Size quantities and cashier access

Owners and managers can open **Studio → Products → Sizes & stock** to go directly to a product, or use **Studio → Stock** and search. Cashiers cannot change stock quantities.

## Receive a delivery

1. Expand the product and choose **Receive delivery**.
2. Enter the new units against each size. Tops and bottoms of a set have separate rows. Leave unchanged sizes blank.
3. Enter a reason and, optionally, a delivery/document reference.
4. Select **Review changes**, check the resulting balances, then **Save stock entry**.

Every row saves together. An invalid row cancels the entire entry. Retrying the same submission does not receive the delivery twice. References appear in the stock movement history.

## Count existing stock

Choose **Count unsold stock**. Enter the total unsold units physically counted, not the number to add. Enter zero only when the size was checked and none was found; leave an unchanged size blank.

Include units reserved for unpaid orders in the count: the system subtracts their holds when calculating availability. Exclude paid orders awaiting pickup: payment has already deducted those units. Keep paid pickups in a separate labelled area. Where sale movements identify pickup units, the size row shows their quantity separately.

Keep the counted area unchanged during the physical count. The confirmation requires acknowledging this and excluding paid pickups. If stock changes after the form opens, the server rejects the count without saving any rows. Choose **Refresh quantities / start again** and recount. A numeric stock version detects changes even when a later adjustment restores the same quantity.

The **Needs initial count**, **Zero available** and **Holds need review** filters help find incomplete setup and exceptions. Receiving units does not certify that a physical count has been completed.

## Write off

Choose **Write off stock**, enter the unsold units removed per size and explain the loss or damage. The server prevents negative balances. Changes below unpaid holds are visible as exceptions needing resolution.

## Website → POS

Signed-in cashiers, managers and owners see **POS** in the website header on larger screens, and **Open POS** in the mobile menu. The link opens the existing Sales Desk and retains its PIN and account checks. Customers do not see staff navigation.

## Implementation and deployment

- Additive migration: `2026_10_21_000004_add_stock_entry_safeguards.php`.
- `stock_levels.version` advances for movements and completed counts.
- `stock_operations` stores a unique entry UUID, canonical request fingerprint, actor, reason, document reference and completion timestamp.
- Both single-size and batch entry routes enforce retry keys; counts require expected versions and pickup exclusion confirmation.
- The batch service reserves/locks the entry UUID, locks affected stock levels in the existing order, checks all count versions, and writes through `StockService` in one transaction. It preserves the legacy batch rebaseline behavior.
- Matching retries return the prior success. Reusing a UUID for different data is rejected. Failed entries roll back their UUID and movements together.
- The inventory lifecycle remains unchanged: payment deducts stock; pickup does not deduct it again. The displayed stock is the unsold pool, not every physical garment still inside the shop.

The migration was applied locally. Existing stock quantities and selling mode were not changed. Production needs the migration and rebuilt frontend assets deployed together.

## Verification

The isolated SQLite backend suite passed 566 tests (9,492 assertions); 19 concurrency tests skip there. MariaDB passed all 11 stock-entry tests and all 19 concurrency tests (30 tests, 149 assertions), including simultaneous retry deduplication, stale counts, final-unit contention between POS and web checkout, and live-payment/count interactions. All 11 frontend test files and the production build passed.

Browser fixtures exercise a multi-size delivery, blank-row omission, explicit zero count, required pickup acknowledgement, stock versions, light/dark form contrast, responsive layouts, and staff/customer website navigation. See `verification/stock-entry-results.json` and `stock-entry-*`, `stock-count-*`, `website-cashier-*` screenshots. Browser requests use synthetic isolated fixtures, not live sales or stock.
