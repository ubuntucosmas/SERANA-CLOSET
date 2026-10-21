# Phase E: browser verification, the till (2026-10-20)

This was run on the local ddev site in Chrome via Playwright. The database was snapshotted first (`pre-phase-e-qa-20261020-2012`), and then the three Phase E migrations were run on it.

**Data and accounts.**
- **The shop tablet** signs in as a cashier account, "QA-E Shop tablet".
- **The people at the till** unlock it with their own PINs: "QA-E Wanjiku" (cashier) and "QA-E Akinyi" (manager, who approves).
- **Pieces:** each run gets fresh ones, "QA-E Wool Jacket ‹run›" (S, M, L; 3 each) and "QA-E Linen Set ‹run›" (top and bottom in M and L; 2 each). They are received through `StockService::receive`.
- **The online order** to collect is a real storefront checkout. Its short M-Pesa payment (3,000 of 6,500) arrives as Safaricom's callback; ddev has no outbound DNS, so no STK prompt was sent.

## Results (21 checks, all pass)

| Area | Check | Evidence |
|---|---|---|
| Sign-in | A cashier signs straight in to `/pos`, which loads locked | `01` |
| PIN | A wrong PIN is refused ("That PIN doesn't match anyone."); the cashier's unlocks the till as her | — |
| Drawer | Opened with a 5,000 float | `02` |
| Sell | The size sheet shows what this shop has per size; a set picks top and bottom | `03`, `04` |
| Sell | The jacket (M) and the set (top M, bottom L) come to 15,500 | `04` |
| Cash | 16,000 handed over: 15,500 booked, change 500 shown, and the sale is paid | `05` |
| Receipt | 80mm receipt: the lines, cash, tendered 16,000, change 500, and who served | `06` |
| Scan | A scanned barcode (EAN-13) puts that size straight on the sale | — |
| Split tender | 4,000 by M-Pesa code, then 2,500 cash | `07` |
| Collect | The reference with the wrong phone finds nothing; with the right phone it shows "KSh 3,500 is still owed. Take it first." | `08` |
| Collect | The 3,500 is taken in cash and the order handed over (with a note): it is collected and paid | `09` |
| Return | One jacket comes back from sale 1, restocked, with 6,500 cash from the drawer on the manager's PIN | `10` |
| Bespoke | A gown quoted at 12,000; the deposit (half) is 6,000, taken in cash | `11` |
| Drawer | Pay out 300 on the manager's PIN. The drawer then equals the float + 15,500 + 2,500 + 3,500 + 6,000 − 6,500 − 300 | `12` |
| Close | Counted exactly: balanced, Z report | `13` |
| Lock | Locks on demand; the manager unlocks at phone width with no sideways scroll | `14` |
| Ledger | `serana:ledger-check`: **0 mismatches** (188 orders). `serana:ledger:verify`: 0 differences | `ledger-check.txt` |
| Stock | `serana:stock:reconcile`: **passed**, with stock per size on | `stock-reconcile.txt` |

The reconcile output lists the QA pieces as "Not counted yet". That is expected: they were received, never counted, and it is informational. Both till sessions closed with a variance of 0.

## Found by this walk-through and fixed before commit

- **Taking money did nothing.** The screen passed a sale where it expected a reference to it, and the script error was swallowed. The tender now names what it is paying (sale, collection or deposit). Any script error now shows on screen instead of failing silently.
- **Abandoned sales held stock for ever.** Unpaid sales left over from the aborted first runs were counted as sales on the Z report, and counter holds never expire on their own. Now:
  - closing the drawer voids its sales that never took money, which puts their stock back ("Not paid when Till 1 closed");
  - the Z report counts a sale only once it takes money;
  - the Z report lists part-paid ones as still owed.
  - Covered by `CounterSaleTest::test_closing_voids_sales_never_paid_and_lists_those_part_paid`.
- **Light-theme colours.** The till followed the site's light theme (dark text on dark panels). The till is now always dark, and the site theme is restored when you leave it.
- **Layout.**
  - The register picker was squeezed into a pill.
  - The cart heading took the site's large `h2` size.
  - Missing product photos showed broken images.
  - Four icons were missing from the Material Symbols subset.
- **A cashier landed on the customer dashboard** after signing in. They now land on the till.

## Review follow-ups (tests, both databases)

| Question | Answer | Tests |
|---|---|---|
| What is "credit"? | Exchange credit only: what a returned sale owes back pays for a new one. It is capped at the smaller of what is owed back and what the new sale owes. Kept, and relabelled on screen; no tab exists. | `ReturnsTest` (exchange; capped at what is owed back) |
| A slip for a piece sold online since | Before: refused as "sold out", so the slip couldn't be recorded. Now: always recorded. A paid online order gives up its unit and goes to `needs_resolution`/`stock_gone`; an unpaid hold gives way, and a later payment is stock-gone under Phase C; a shortfall is a count correction flagged "count it". Stock never goes below zero. | `ShopDeskTest` (3 cases, both stock models); `ConcurrencyTest` slip vs payment, both orders (MariaDB) |
| Close and money | A sale with any payment, even one fully refunded, is never voided. Part-paid sales are on the Z report. | `CounterSaleTest::test_closing_never_voids_a_sale_that_took_any_money` |
| PIN | 5 wrong PINs lock the pad for 5 minutes (audited). A PIN is never a password, and unlocking signs no one in to the Studio. | `TillTest` (lockout, reset, never a sign-in) |

## Re-running

```
ddev exec php artisan tinker --execute 'require storage_path("app/qa-phase-e.php");'   # staff, PINs, pieces (the script does this itself)
node --use-system-ca qa-e/1-till.mjs                                                     # from the session scratchpad
```
