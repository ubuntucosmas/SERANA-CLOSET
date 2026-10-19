# Phase B close-out: browser verification (2026-10-19)

Local ddev site, Chrome via Playwright. The data comes from `Batch2ScenarioSeeder` plus one walk-through order, `SRN-E1504801-2026`, quoted at KSh 10,000 with a custom KSh 4,000 deposit. The Studio was used as a dedicated QA owner account.

**How payments were simulated.** ddev has no outbound DNS, so the browser's STK request was intercepted. The payment attempt was created with the amount from the server's own rule (`MpesaController::amountDue`), then a real Safaricom-format callback was posted to the token-guarded endpoint. Receipts were read back from Mailpit; the PDFs were read with `pdftotext`.

## Results

| Area | Check | Result | Evidence |
|---|---|---|---|
| Studio | Quote saved as an order line (KSh 10,000) | ✅ | `01-studio-quoted.png` |
| Studio | Custom deposit KSh 4,000 with a reason, audited (`order.deposit_set`, "QA: returning client") | ✅ | `02-studio-custom-deposit.png` |
| Studio | Copy pay link puts a signed `/pay/SRN-…?expires=…&signature=…` link on the clipboard | ✅ | `03-studio-pay-link-actions.png` |
| Studio | WhatsApp goes to the customer (`wa.me/254708374149`) with the reference, total KSh 10,000, deposit KSh 4,000, balance after KSh 6,000, the link and the deposit rule | ✅ | text below |
| Guard | Designing refused before the deposit: "Take the deposit (KSh 4,000) before work starts." Stage unchanged | ✅ | `04-guard-designing-before-deposit.png` |
| Pay page | At 375px: lines, total, paid, balance and deposit rule; **Pay deposit KSh 4,000** and **Pay in full KSh 10,000**; no email, phone or address; no horizontal scroll | ✅ | `05-pay-page-phone.png` |
| Pay page | Tampered signature: 403, only "This link has expired… Ask the studio…", no order data | ✅ | `06-pay-link-tampered-phone.png` |
| Pay page | Expired link: same | ✅ | `07-pay-link-expired-phone.png` |
| Deposit | The page sends `pay=deposit`, never an amount; the server asks KSh 4,000; the callback books it; the page confirms; ledger `deposit_paid`, 4,000 paid, 6,000 due | ✅ | `08-pay-deposit-received-phone.png` |
| Receipt 1 | Email "Payment received — SRN-…": this payment KSh 4,000, total KSh 10,000, paid to date KSh 4,000, balance KSh 6,000, reference QABDEP4000, receipt `-P1`, deposit rule, owner in Bcc. PDF shows the same | ✅ | `08-receipt-deposit-email.png`, `08-receipt-deposit.pdf` |
| Studio | Part-paid order reads **Deposit Paid** (after the fix below) | ✅ | `09-studio-deposit-paid-label.png` |
| Guard | Designing, then completed, allowed once the deposit is paid | ✅ | audit trail |
| Guard | Shipped refused: "KSh 6,000 is still owed. Take the balance before it leaves the studio." | ✅ | `10-guard-shipped-with-balance.png` |
| Override | Owner ships with "QA: courier collects balance on delivery". Audited `fulfilment.override` with before `{status: completed, balance_due: 6000}` and after `{status: shipped}` | ✅ | `11-owner-override-shipped.png` |
| Balance | `pay=balance`; the server asks KSh 6,000; booked; ledger `paid`, balance 0 | ✅ | `12-pay-balance-received-phone.png` |
| Receipt 2 | Email "Your Serana receipt — SRN-…": this payment KSh 6,000, paid to date KSh 10,000, **Paid in full**, QABBAL6000, receipt `-P2`. PDF shows the same | ✅ | `12-receipt-balance-email.png`, `12-receipt-balance.pdf` |
| Ledger | `serana:ledger-check` 143 orders, 0 mismatches; `serana:ledger:verify` 0 differences | ✅ | after the walk-through |

**WhatsApp text as sent:**
```
Hello QA-B,

Your Serana Closet quote is ready (order SRN-E1504801-2026, Hoodie & Jogger Set).

Total: KSh 10,000
Deposit to start: KSh 4,000 · Balance after: KSh 6,000

Pay securely by M-Pesa here: https://serana-closet.ddev.site/pay/SRN-E1504801-2026?expires=…&signature=…

Deposit refundable until work starts (designing); non-refundable after.
```

## Broken, and fixed (commit `d8f72ef`, tests first where testable)

1. **A part-paid bespoke order said "Quote Ready"** in the Studio, on tracking and in the customer's orders. It now says "Deposit Paid", with the balance due. New frontend unit tests cover `orderStatus.js`. Tracking and the customer's order list now include `amount_paid` and `balance_due` (feature test).
2. **The pay page nested a second `<main>`** inside the layout's. It now has one landmark. The existing Checkout page has the same pattern and is left as is: it carries someone else's uncommitted edits.
3. **Studio light theme:** the WhatsApp and Record payment buttons were nearly invisible (pale emerald on pale emerald). They now use the colour the light theme maps to readable green. Checked by screenshot.
4. **The deposit placeholder was cut off.** It's shorter now.

## Notes

- **The receipt email's heading** still says "Payment confirmed. … your order is in our hands" for a deposit as well. The figures underneath are right; the heading wording could distinguish a deposit later.
- **The walk-through order** is left in the local data as evidence: shipped, paid in full, with two payments and their audit trail.
