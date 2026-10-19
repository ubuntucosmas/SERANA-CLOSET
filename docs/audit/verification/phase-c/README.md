# Phase C: browser verification (2026-10-19)

Local ddev site, Chrome via Playwright, as a dedicated QA owner account. The database was snapshotted first (`pre-phase-c-qa-20261019-2040`). The run below is a clean one from that snapshot; earlier runs found the problems listed under "Found and fixed".

**How it was driven.**
- **Scenario orders** (all named "QA-C …") were created by a setup script through the app's own models and services.
- **Payments** arrived as Safaricom sends them: real-format callbacks posted to the token-guarded endpoint. ddev has no outbound DNS, so no STK prompt was sent.
- **The two jobs** (`stock:release-expired`, `serana:resolve-overdue`) were run by hand after moving the relevant timestamps back in the database.
- **Every human decision** was made in the Studio in the browser: reverse, Mark refunded, refund/swap/wait, lower a quote, cancel.
- **Customer emails** were read back from Mailpit, and the refund PDFs with `pdftotext`.

## Ledger check before and after

| | `serana:ledger-check` | `serana:ledger:verify` |
|---|---|---|
| Before | **2 mismatches** in 146 orders. Two legacy QA double bookings: `SRN-A683D91A-2026` (code QKWA123TEST, also on SRN-3B4F7954-2026) and `SRN-C1B7341C-2026` (TJQA7PAID1, also on SRN-6E4E6E7F-2026) | 0 differences |
| After | **0 mismatches** in 157 orders | 0 differences, no shared-code note |

The owner reversed both double bookings in the Studio, each with a reason (`02`, `03`).

## Results (41 checks, all pass)

| Scenario | Check | Evidence |
|---|---|---|
| Home | Payment problems list with its count (13), oldest first | `01`, `17` |
| Step 0 | Owner reverses both legacy double bookings with a reason; the flag clears | `02`, `03` |
| Duplicate | A replayed callback changes nothing ("Already Processed") | log |
| Duplicate | Second prompt paid → recorded, `refund_due` KSh 3,000, cause "Paid twice" | `04` |
| Duplicate | The receipt for that payment says "We received this payment twice. KSh 3,000 will be refunded to you within 2 working days" | `20-email-receipt-paid-twice.html` |
| Duplicate | Refunds queue: prefilled WhatsApp "…Your payment came through twice. We will refund KSh 3,000 to you within 2 working days." | `04` |
| Partial → full refund | Mark refunded KSh 1,000 by M-Pesa: still `refund_due`, KSh 2,000 left in the queue | `05`, `06` |
| Partial → full refund | Then KSh 2,000 in cash: `paid`, nothing owed, off the queue | `16` |
| Partial → full refund | Two refund receipts, owner in Bcc. PDFs `-R1` "Still to refund KSh 2,000" and `-R2` "Nothing, fully refunded" | `21-*.html`, `22-refund-receipt-1.pdf`, `22-refund-receipt-2.pdf` |
| Refund code | Reusing a refund code is refused: "This M-Pesa code is already recorded on order SRN-…" | `07` |
| Late, sold out | Four late payments, each for a jacket whose last unit had sold: each recorded (KSh 5,000), `needs_resolution`/`stock_gone`, `batch_sold` stays 1 of 1 | log |
| Late, sold out | Customer told what happened and the options, and that it becomes a full refund after 3 days | `23-email-sold-out-after-late-payment.html` |
| Late → full refund | Decision box; reason; cancelled, KSh 5,000 owed back | `08` |
| Late → swap | Swap to a KSh 6,000 coat (size L): KSh 1,000 balance, coat held | `09` |
| Late → swap | Pay page offers the KSh 1,000 balance | `10` |
| Late → swap | Balance paid by callback: order `paid`, coat sold | log |
| Late → wait | Wait for the next batch: open, `waiting_restock`, reason noted | `11` |
| Late → nobody decides | After 3 days `serana:resolve-overdue` cancels it, KSh 5,000 owed back ("Sold out after a late payment") | log, `16` |
| Mismatch over | KSh 6,000 against a KSh 5,000 prompt: paid, stock sold, KSh 1,000 owed back. The receipt says so | `24-email-receipt-overpaid.html` |
| Mismatch under | KSh 4,000: `deposit_paid`, stock held 24 hours (1,439 minutes left) | log |
| Mismatch under | Balance email with a pay button; the pay page offers "Pay the balance KSh 1,000" | `25-email-balance-due.html`, `12` |
| Mismatch under, expiry | Unpaid after the day: expired, KSh 4,000 owed back ("Balance not paid in time"), hold released | log, `16` |
| Quote lowered | 6,000 paid, quote 10,000 → 5,000. The Studio warns and asks for a reason first; `refund_due` KSh 1,000 | `13` |
| Quote lowered | Shipping refused: "KSh 1,000 is owed back to the customer. Record the refund before it leaves the studio." | `13` |
| Quote lowered | Pay page: "To be refunded to you KSh 1,000 · KSh 1,000 will be refunded to you within 2 working days. Nothing more to pay." | `14` |
| Cancel before designing | All KSh 5,000 owed back. Email: "Your deposit of KSh 5,000 will be refunded to you within 2 working days" | `26-email-cancelled-before-designing.html` |
| Cancel after designing | Deposit KSh 5,000 kept as a retained-deposit line with the reason; KSh 1,000 above it owed back. The email says which applied | `15`, `27-email-cancelled-after-designing.html` |
| Phone width | Refunds queue at 390px: no horizontal scroll | `18` |

**Race tests (MariaDB, real parallel workers, in the suite):**
- two Mark-refunded clicks for the whole amount book one refund;
- two late payments for the last unit sell it once, and the other waits for the studio.

## Found and fixed during the walk-through

- **Icons rendered as raw words.** `currency_exchange`, `gavel` and `error` aren't in the Material Symbols subset the site loads, and the word broke the sidebar. Now uses `paid`, `hourglass_top` and `warning`, which are in it (`7c0b773`).
- **Amber text was unreadable on the light Studio theme.** Now uses the shade the theme remaps for contrast (`7c0b773`).
- **`serana:ledger:verify` still listed a shared M-Pesa code after the owner reversed it.** It now uses the same rule as `ledger-check` (`978148d`).

**Found while updating an old test:** a fractional amount (KSh 4,999.75 against a KSh 5,000 prompt) would have rounded into a full shop settlement under the new over/under rule. Fractional amounts keep the old flag-for-review rule (`5ea00dd`).

**Not an app problem:** the first run's expiry checks used MySQL `NOW()`, which is Nairobi time in ddev; the app stores UTC. The harness now uses `UTC_TIMESTAMP()`. No app query uses `NOW()`.
