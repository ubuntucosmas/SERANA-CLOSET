# Phase D2: browser verification, delivery and collection (2026-10-21)

This was run in Chrome on the local ddev site.
- **Snapshot:** taken first, as `pre-d2-qa-20261021-0936`. The new migration was then run.
- **Accounts:** the QA owner, a shopper's own phone-sized browser, and the shop tablet with the QA cashier's PIN.
- **Payments:** they arrive as Safaricom's callback. ddev has no outbound DNS, so no STK prompt was sent.

## Results (18 checks, all pass)

| Area | Check | Evidence |
|---|---|---|
| Studio | The owner adds a zone: "QA-D2 Nairobi CBD", KSh 300, free from 20,000, next working day. A reason is required, and the change is audited. | `01` |
| Studio | The owner gives the shop its collection address, so pickup is offered | `01` |
| Checkout | The options show their prices: the zone at KSh 300, collect from the shop free with its address, and a quote | `02` |
| Checkout | The summary adds the delivery before paying: 6,500 + 300 = **6,800** | `02` |
| Checkout | Pickup hides the address fields and shows where to collect | — |
| Checkout | A quote sends the order on WhatsApp: "priced on WhatsApp, so this order is sent there" | `03` |
| Orders | A zone order totals 6,800, with a KSh 300 delivery line, and is paid in full | — |
| Orders | A pickup order costs nothing extra and is paid | — |
| Orders | A quote order is a WhatsApp order with no delivery price yet | — |
| Studio | The quote order says "Delivery: to be quoted. The customer can't pay until you price it." | `04` |
| Studio | **Price delivery** (KSh 800, with a reason) puts it in the total: 7,300 | `04` |
| Studio | A pickup order offers "Ready for pickup", not "Shipped". Saving it makes it ready. | — |
| Email | The customer gets "Ready for pickup — SRN-…" (seen in Mailpit) | — |
| Till | The reference and phone find it, and it is handed over (collected) | `05` |
| Ledger | `serana:ledger-check`: **0 mismatches** (223 orders) | `ledger-check.txt` |
| Stock | `serana:stock:reconcile`: **passed** | `stock-reconcile.txt` |

## Found by this walkthrough and fixed

- **An order opened from a link (`?order=`) was missing its delivery and seller.** The Studio list and a link now share one list of what an order carries.
- **On a quote order, the Studio offered to confirm payment before the total was known.** The server now refuses it ("Price the delivery first"), and the form is hidden until delivery is priced.
- **The till's new "Find and continue an order" heading** (Codex) used the site's oversized `h2`.
