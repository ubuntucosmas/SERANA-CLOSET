# Frontend audit, first principles (2026-10-21)

**Method.**
- Every server route was listed (113). Each one has a screen or link that uses it, so nothing built is unreachable.
- Each screen was then walked asking: **who** uses it, **what** for, **when**, **where** they find it, and **why** each element exists.
- Anything with no answer is cut. A step a real user needs, with no screen, is a gap.

## Verdict

The till and the Studio cover the main paths. They are **not complete**: there are 6 real gaps (P1) and 6 things to simplify or correct (P2). Click & collect is only half a feature until D2 lets a customer choose it at checkout.

## P1 — a real user gets stuck

| # | Who and when | What happens today | Fix |
|---|---|---|---|
| 1 | Cashier, the tablet reloads or the browser closes mid-sale | The sale is lost from the screen, but it still holds its stock until the drawer closes. There is no way to get back to it. | **Today's sales** on the till: each sale's time, reference, total and state. Open one to keep taking payment, reprint, or void it. |
| 2 | Cashier, a customer asks for a copy of their receipt | No way to find a past sale's receipt | Today's sales and the return search both get **Print receipt**. |
| 3 | Cashier, the customer changes their mind after "Take payment" | The only option is to void, then rebuild the bag by hand | **Change items**: voids the unpaid sale and puts the same pieces back in the bag. |
| 4 | Owner, the next morning: "Did the drawer balance? Who closed it?" | Z reports exist only on the till screen at the moment of closing. The Studio has no history. | Studio → **Till** section: past sessions with who opened and closed them, expected, counted, the variance and its reason, and the Z report. A variance over the alert shows on the Overview. |
| 5 | Owner, reading orders in the Studio | Till sales, collections and paper slips look like any shop order. Nothing says "sold at the till", by whom, or the slip number. | An order shows **where it was sold** ("Till · Wanjiku" or "Paper slip B-0142") on the list and in the detail. The list gains a "Till" filter. |
| 6 | Customer, at checkout | Can't choose "collect from the shop", so click & collect can't start online | **D2** (delivery and collection options): its own phase and PR, now unblocked. |

## P2 — simplify or correct

| # | Element | Problem (why it fails the question) | Decision |
|---|---|---|---|
| 7 | Till: register picker | Only "Till 1" exists, and a second register can't be created. It answers no question. | **Hide** unless two registers are open. |
| 8 | Till: "Phone, for the receipt by SMS" | We send no SMS; it is untrue. A customer's email, which does get a receipt, has no field. | Rename to "Phone (to find the sale later)", and **add Email** ("emailed receipt"). |
| 9 | Till: the Sell screen is empty until you type | A cashier must know a name to start | **Show the pieces on load** (in-stock first). The search narrows them. |
| 10 | Till: the Z report can't be printed | The drawer count is kept on paper by most shops | **Print Z report** button (print styles hide the rest). |
| 11 | Studio: Pre-orders section while Drops are off (`DROPS_ENABLED=false` for launch) | It manages a feature customers can't see | Show it only when Drops are on, or when drops already exist. |
| 12 | Till: the paper-slip link inside the bag | Fine, but it says nothing about who may use it | Say "manager's PIN needed" up front, so a cashier isn't surprised. |

## Checked, and kept as is

- **Lock screen, PIN pad, idle lock.** Answers who is at the till.
- **Tender choices.**
  - Cash shows the change.
  - The M-Pesa code is checked as unique.
  - The M-Pesa prompt shows its progress.
  - Exchange credit is used only after a return.
- **Collect.** Reference and phone together; the balance first; "Hand it over" only when nothing is owed.
- **Returns.** Per line: how many come back, restock or write off, the reason, the refund method, and a manager PIN for a cashier.
- **Bespoke.** The website's fields, a quote, then the deposit shown at half the quote.
- **Studio:** Staff (owner only), Stock, Refunds, and Overview attention.
- **Storefront.** It is the owner's redesign in progress. This audit only checked that every link leads somewhere and that Drops hide when off, and both are true.

## Found while fixing (browser walkthrough)

| # | What | Fix |
|---|---|---|
| 13 | With nothing typed, the till listed pieces alphabetically and stopped at 60, so in a full catalogue most pieces never appeared | With nothing typed, the **newest pieces come first**; a search still reads alphabetically. |
| 14 | The Studio's order list showed a paid till sale as "Awaiting fulfilment": the list didn't know the till's **collected** stage | It now reads **Collected**, and a voided sale reads **Cancelled**, not "Not started". |

## Status (2026-10-21)

- **Done, tested and walked through in the browser:** items 1–5 and 7–14. See `verification/frontend-align/`.
- **Still to do:** item 6, collection at checkout. That is D2.

## Order of work

1. P1 items 1–5 and P2 items 7–12, on `frontend/align`, tests first. Both databases, with a browser walkthrough.
2. D2 (item 6) as its own phase and PR, as planned.
