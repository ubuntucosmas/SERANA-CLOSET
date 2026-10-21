# Phase D1: browser verification, stock per size (2026-10-20)

This was run on the local ddev site in Chrome via Playwright, using a dedicated QA owner account. The database was snapshotted first (`pre-phase-d1-qa-20261020-0946`), and the run below starts from that snapshot.

**Data.**
- **Walkthrough pieces:** "QA-D Linen Hoodie" (sizes S, M and L) and "QA-D Linen Set" (top and bottom, each in M and L).
- **Other products:** their stock take was entered in bulk by script, through the same `StockService::count` the Stock screen uses, as the QA owner, with the reason "Opening stock take (entered in bulk)".
- **Payments:** these arrived as Safaricom sends them. The script created a payment-attempt row, then posted a real-format callback to the token-guarded endpoint. ddev has no outbound DNS, so no STK prompt was sent.
- **Shoppers:** each shopper had their own browser session and checked out through the storefront's own axios client.

## Before and after

| | `serana:stock:reconcile` | `serana:ledger-check` | `serana:ledger:verify` |
|---|---|---|---|
| Before | Passed (stock per size off). It listed 50 uncounted sizes, which is expected before a stock take | 0 mismatches, 160 orders | 0 differences |
| After | **Passed with stock per size on** | **0 mismatches**, 165 orders | 0 differences |

## Results (34 checks, all pass)

| Area | Check | Evidence |
|---|---|---|
| Stock screen | Off before the switch, uncounted sizes listed | `01` |
| Stock take | Count S 0, M 2, L 1 per size, each with a reason | `02` |
| Receive | +1 L ("Batch from the workshop"), so L is 2 | `02` |
| Write off | Writing off an S when none is on hand is refused: "Only 0 of QA-D Linen Hoodie (Size S) is on hand at Serana Studio." | `03` |
| History | Every change shows who made it and why, newest first | `04`, `14` |
| Switch | Refused while a shopper is paying with a hold that has no readable size ("Let them finish or expire first"). This used two leftover Phase C QA orders whose holds had no size | `05` |
| Switch | Once those holds expired, the switch is offered, confirmed by the owner, audited (`stock.cutover`), and reconcile passes | `06` |
| Size picker | On a phone: S struck through and disabled ("Sold out in S"), with "Only 2 left in M · Only 2 left in L" | `07` |
| Bag | M stops at the 2 left: + is disabled, with "That's all we have of this piece right now" | `08` |
| Checkout | Shoppers A and B each get an M | log |
| Checkout | Shopper C is told "QA-D Linen Hoodie in Size M just sold out", and can still buy an L | log |
| Size picker | M now sold out; L still offered | `09` |
| Sale | A pays: one sale movement (−1 M) at the shop; M on hand 1 | log, `14` |
| Late payment | B's hold lapses, shopper E takes that M, then B's payment arrives. The order waits for a decision: "QA-D Linen Hoodie (Size M) sold out". Nothing goes below zero | log |
| Swap | In the Studio, B is swapped to the set, top M and bottom L, using the new two-size fields. B owes KSh 4,000; the set is held by size | `11` |
| Swap | B pays the balance: the set's top M and bottom L are sold | `12` |
| Old bag | A bag line saved without a size shows "Choose a size" in the bag; checkout answers "Choose a size for QA-D Linen Hoodie." for that line | `10` |
| Rollback | Owner switches back, with a reason (audited `stock.rollback`). The old model shows the same total stock per size had | `13` |
| Rollback | The old model sells an L meanwhile (no stock movement) | log |
| On again | The screen lists "1 sale(s) since a count will be applied". Switching on writes "Sold before the switch" once for that L. Reconcile passes | `14` |
| Phone | Stock screen at 390px: no horizontal scroll | `15` |

**Not checked in this walkthrough:**
- **Multi-shop allocation:** a second location exists in the model and in tests only, with no UI.
- **Barcode label printing:** waits for the till phase. SKUs and barcode values are generated and shown.
- **Rollback of the two D1 migrations:** checked by hand on the dev MariaDB (`migrate:rollback --step=2`, then `migrate`), not in the suite. A schema change inside the suite's transactions breaks on MariaDB.

## Found and fixed during D1

- **Concurrency:** stock decisions made while holding a size's lock now use locking reads. One early MariaDB run sold the last M twice. It couldn't be reproduced, so the reads were made safe by construction rather than left to chance.
- **The cutover:**
  - Only live holds without a size block the switch. Old released holds on long-expired orders would otherwise have blocked it for ever.
  - If a late payment arrives for such a hold, it waits for the studio. It's never sold without a stock movement.
  - The Studio no longer offers the switch while a live hold has no size. Confirming was already refused in that case.
- **Variants:**
  - A product created without a garment type got variants for a single piece, while the database records a set. They now match.
  - A product whose garment type changes withdraws its old parts' sizes.
- **Sets:** after the backfill splits an old set hold into a top row and a bottom row, the old model (still live until the switch) counts the set once and sells it once. Two lapsed holds of one product are checked against the old limit together.
- **Labels:** a one-size piece read "Size One size". It now reads "One size", and sold-out notices give its name alone.
- **Performance:** with stock per size on, product lists queried availability per product, size and location. They now take two queries per list.
- **Stock history:** a sale's reason repeated the order reference shown next to it.
- **Swap box:** swapping to a set now asks for its top and bottom sizes.
- **Process (my error, corrected):**
  - Your uncommitted edits to `StudioDashboard.vue` had been swept into two of my commits. Those commits were rebuilt with only my changes before anything was pushed, and your edits are back as uncommitted work.
  - The local dev database was wiped once by `migrate:fresh --env=testing`; with no `.env.testing`, it targeted the dev database. It was restored from a snapshot and the QA data re-created.
