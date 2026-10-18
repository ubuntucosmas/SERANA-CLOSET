# Serana manager workflow

The Studio Overview is the daily decision desk. Counts describe current active records, not promised deadlines. Open a queue to see matching orders oldest-first. Search, type filters and archive selection further narrow that list; the Overview count describes the full active queue.

| Sequence | Work list | Manager decision | Record before leaving |
| --- | --- | --- | --- |
| 1 | Payment problems | What evidence explains a failure or amount mismatch? | Receipt/reference and agreed next step in private notes. Check repeated-payment alerts separately. |
| 2 | Needs a quote | Are the brief, measurements, fabric and timing agreed? | Quote plus customer discussion and next step. |
| 3 | Awaiting payment | Has the correct amount actually arrived? | Use the existing payment confirmation workflow where available. Never treat a production update as payment evidence. |
| 4 | Paid · start work | Is the order ready for packing or production? | Validate items, sizes, delivery address and production requirements. |
| 5 | In production | What work has advanced and what is blocked? | Appropriate stage, internal handover notes and relevant progress photos. Payment may still be outstanding on custom work. |
| 6 | Ready to dispatch | Is the correct parcel ready for handover? | Confirm delivery details; mark dispatched after actual handover. |
| 7 | Review stock | What should be replenished, hidden or corrected? | Preview the saved product, then edit price, images, details or availability. |

Use internal notes in the form “Decision — reason — responsible person — next step — agreed date”. Notes are editable working records, not an immutable audit log. Preserve useful previous notes when appending a handover. Do not place private payment details in customer-facing progress content.

## Reading the numbers

- New orders and paid order value use rolling seven-day windows.
- Paid order value uses `paid_at` and includes archived paid orders. It is not profit, payment-attempt totals, a bank balance or a refund-adjusted ledger.
- Queue counts and filters share server-side rules. Production can overlap the awaiting-payment queue for custom work.
- The attention snapshot is limited and is separate from full queue totals. Repeated-payment alerts require manual reconciliation; the interface does not claim a refund was completed.
- Refresh figures to retrieve a new snapshot. The displayed time uses Africa/Nairobi.

## Implementation boundaries

No bulk financial updates, automatic dispatch, customer messages, deadlines, refunds or new staff permissions are introduced. Existing payment and order protections remain in use. There is no immutable activity history or staff assignment system in this change.

Verification: production frontend build; existing frontend tests; 26 Studio feature tests with 355 assertions, including queue/list consistency and fulfilment protections. Browser interaction and production deployment are not verified by these checks.
