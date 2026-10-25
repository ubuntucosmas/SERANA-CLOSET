# V1 controlled launch: live checklist

For the owner and whoever runs the server. Each step must happen on the **production** server with real settings: it can't be proved on a laptop. Tick each box, and write the date and who did it. If a result differs from "Expected", stop and keep the evidence (screenshot, M-Pesa SMS, the order reference).

Order matters: 1–4 before any real money, 5–12 with one low-value real payment each (KSh 10–50), 13 last.

## 1. Server and configuration

- [ ] Deploy the release tag with `deploy/deploy.sh <tag>` (it backs up, migrates, builds and runs preflight).
- [ ] `.env` is filled from `deploy/env.production.example` (live M-Pesa keys, SMTP, `APP_KEY`), and a copy is saved in the owner's password manager.
- [ ] Cron: the `schedule:run` line and the backup line from `deploy/crontab`, as the web user.
- [ ] Queue worker: `deploy/supervisor-serana-worker.conf` installed; `supervisorctl status` shows it RUNNING.
- [ ] External uptime monitor (e.g. UptimeRobot, Better Stack, the host's own) on `https://<domain>/health` every 5 minutes, alerting the owner's phone or email. **Expected:** `{"status":"ok","failing":[]}`.
- [ ] Off-server backups set up (`deploy/RESTORE.md`, "Setting it up"). The first run ends `Backup OK … off-server: true`.
- [ ] Restore drill on the server or a staging copy (`deploy/RESTORE.md`, "Restore drill"). **Expected:** `Restore drill passed`. Record the date in RESTORE.md.

## 2. Studio settings (owner)

- [ ] Settings → Store details: WhatsApp number, call phone, contact email, **order alert email** (the owner's inbox), address. Only the owner can change these.
- [ ] Settings → Delivery and collection: the pickup shop with its **address**, each delivery zone with its real fee and ETA, a "quote" option for anywhere else if wanted. Deactivate anything not offered.
- [ ] Settings → Payments: M-Pesa on, Test connection → "Connected to Safaricom" (live). WhatsApp ordering on or off as decided, with payment instructions.
- [ ] Settings → Orders and Shop: deposit %, pay-link days, refund days, holds, cashier discount limit, variance alert.
- [ ] Staff: each person's own account and Sales Desk PIN. No shared accounts.

## 3. Preflight

- [ ] `php artisan serana:preflight` → **"Ready to take orders"**, exit code 0. Copy the whole output into the launch record: every line, not a summary.
- [ ] The Studio home shows no "System" box. If it does, each line says what to fix.

## 4. Watch the background jobs work

- [ ] Preflight lists `mpesa:reconcile`, `stock:release-expired`, `scheduler-heartbeat` as having run within the last few minutes; `operator-alerts` and `drops:close-expired` within 11 minutes; `serana:resolve-overdue` within two hours.
- [ ] Stop the queue worker (`supervisorctl stop serana-worker:*`), place a WhatsApp order, wait 16 minutes. **Expected:** Studio home "Emails are not being sent", `/health` → 503 `queue`, uptime monitor alerts. Start the worker again: the email arrives and the warning goes.
- [ ] (Optional, off-hours) Comment out the cron line for 6 minutes. **Expected:** Studio home "Background checks have stopped", `/health` → 503 `scheduler`, an alert. Restore the cron line straight away.

## 5. Mail

- [ ] `php artisan serana:mail-test <owner inbox>` (or a WhatsApp order) arrives, not in spam.
- [ ] A paid order's receipt arrives at the customer address, with the owner in Bcc.
- [ ] "Forgot password" for a staff account arrives and the link works.

## 6. One real product, through the Studio only

- [ ] Products → New: real name, category, price, description, garment type, sizes, photos. Stock → count the opening stock per size.
- [ ] Check it on the Shop, the product page (sizes as counted), the Sales Desk search, Stock and Studio Products.
- [ ] Edit the description, change the price, replace a photo, receive 1 more unit, archive it, restore it. The shop follows each change.

## 7. Golden online sale (KSh 10–50 product or zone)

- [ ] Shop → product → size → bag → checkout → pickup (or a zone) → M-Pesa → approve on the phone.
- [ ] **Expected:** confirmation shows the right total and fee; one order; one payment with the M-Pesa code; stock down by one; receipt email; `/track` with reference + phone shows it paid; Studio shows it paid and ready for fulfilment.

## 8. B-01: cancel, then pay a retry

- [ ] Checkout → M-Pesa → **cancel** on the phone → wait for "Payment was not completed" → Resume Payment → approve.
- [ ] **Expected:** same order reference; first prompt failed, second paid (order → payment history); stock committed once; one receipt.

## 9. Prompt still pending

- [ ] Checkout → M-Pesa → **don't answer** → close and reopen the payment window → press Pay again within 2 minutes.
- [ ] **Expected:** "A payment prompt was sent to the number ending … has not been answered yet", and no second prompt arrives on the phone. Then answer or cancel the first one.

## 10. Golden Sales Desk sale

- [ ] Open the register with the counted float → find the product → size → add → cash (and once by M-Pesa prompt) → receipt prints → next sale.
- [ ] **Expected:** Today's sales lists it; Stock shows one fewer; Activity names the cashier; the register report shows the cash.

## 11. Final unit across channels

- [ ] With exactly one unit left: one person checks out online while another rings it up at the Sales Desk, at the same moment.
- [ ] **Expected:** exactly one succeeds; the other is told it sold out; stock is 0, never negative. (Also proved by `ConcurrencyTest` on MariaDB.)

## 12. Pickup and register close

- [ ] Studio: mark the online order ready for pickup → Sales Desk → Collect → reference + phone → hand over. Try to collect it again. **Expected:** "This order was already collected."
- [ ] Close the register: count the cash. With a difference over the alert, a reason and a manager PIN are asked for. The Z report appears in Studio → Sales Desk History.

## 13. Before public launch

- [ ] Owner-approved Privacy, Terms, Delivery and Returns/Refunds pages are published.
- [ ] The real catalogue and stock are entered; demo data is gone (clean-slate runbook).
- [ ] A week of controlled use with no unexplained Studio warnings.
