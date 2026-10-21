# Clean-slate V1 install (`v1.0.0-rc.2`)

Production starts from an **empty database**. Nothing from development is carried over: no orders, payments, users, stock, uploads, jobs, sessions or cache.

**The first real M-Pesa payment is the data freeze point.**
- **Before it:** resetting the production database is allowed.
- **After it:** never run `migrate:fresh`, `db:wipe`, table truncation or seeders against production again. Only normal migrations, through `deploy/deploy.sh`.

Rehearsed locally on 2026-10-21 against a new empty MariaDB database. The results:
- 51 migrations ran.
- Every table was empty except two structural defaults: one shop location with no address, and two delivery options (pickup, "quote on WhatsApp").
- `serana:admin` created the owner.
- `serana:stock:cutover` was ready immediately on the empty catalogue and switched on.
- `serana:ledger:verify` reported 0 differences with ledger reads on, and `serana:stock:reconcile` passed.
- There were no failed jobs.

## 0. Before anything
- [ ] `v1.0.0-rc.2` exists on origin and points at `master` (PRs #5 → #9 merged in order).
- [ ] The database being replaced holds **no real transaction**. Check it:
  - M-Pesa payment codes in `order_payments` match **sandbox** codes only;
  - `MPESA_ENV` was never `production` on it;
  - no live till session was ever opened.
- [ ] If any real payment exists, **stop**: it is production history.

## 1. Server and database
1. A new, empty MariaDB **≥ 11.6** database with `innodb_snapshot_isolation=ON`. Create a new database; don't clean the old one row by row.
2. Check out the tag: `git fetch --tags && git checkout v1.0.0-rc.2` (first install), or `deploy/deploy.sh v1.0.0-rc.2`.
3. Fill `.env` from `deploy/env.production.example` with real values only. Never copy sandbox keys or the dev `.env`.
   - `APP_ENV=production`, `APP_DEBUG=false`, an https `APP_URL`, `SESSION_SECURE_COOKIE=true`, `LOG_STACK=daily`, `LOG_LEVEL=warning`.
   - `FILESYSTEM_DISK_PUBLIC` set explicitly (`public` + `php artisan storage:link`, or `s3` with the R2 keys and `AWS_URL`).
   - The mail sender on the brand domain (SPF, DKIM, DMARC), and `STORE_NOTIFICATION_EMAIL`.
   - `MPESA_ENV=production`, the live consumer key and secret, shortcode, passkey, and a new `MPESA_CALLBACK_TOKEN` (`php -r "echo bin2hex(random_bytes(24));"`).
   - `MPESA_ALLOWED_IPS` (Safaricom's published ranges), plus `TRUSTED_PROXIES` if a CDN or load balancer is in front.
   - `DROPS_ENABLED=false`, `LEDGER_READS=false`, `STOCK_V2=setting`.
4. `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
5. `php artisan key:generate` (first install only), then `php artisan migrate --force`. **Never** run `db:seed`: the seeders are demo data and refuse to run outside local anyway.
6. `php artisan optimize`
7. Cron: `deploy/crontab`. Workers: `deploy/supervisor-serana-worker.conf`.
8. Web server: HTTPS, upload limits (`client_max_body_size 64m`, `post_max_size` ≥ 64M), and security headers (HSTS at least).

## 2. People
- `php artisan serana:admin <owner email> --name="<owner name>" --generate`. Store the password in a password manager.
- Create other staff only if launch needs them, in Studio → Staff (manager, cashier), each with their own till PIN.
- No demo accounts exist on a fresh database. Preflight blocks the old demo password if one ever appears.

## 3. Settings (owner decisions, entered in the Studio)
**Store settings:**
- Name, tagline and contacts: WhatsApp, care phone, contact email and address, social links.
- Notification inbox.
- Logo, favicon and home images.

**Payments:**
- M-Pesa on; WhatsApp on or off.
- WhatsApp hold hours and payment instructions.
- Run **Test M-Pesa connection**.

**Order policy:** deposit %, pay-link days, refund working days, resolution days, balance hold hours.

**Delivery and collection:**
- The shop address. Pickup stays hidden until it is set.
- Delivery zones with fee, free-over threshold and ETA.
- Keep or switch off the WhatsApp-quote option.

**Till rules:** cashier discount limit and cap, variance alert, PIN timeouts, receipt footer.

**Custom-order options:** garments, fabrics, colours.

Publish the terms, privacy and returns wording before public launch. It isn't in the app.

## 4. Stock per size from day one, then the catalogue
Do this **before** entering any product:
1. Studio → Stock → switch stock per size on, or `php artisan serana:stock:cutover --confirm --by=<owner email>`. On an empty catalogue there is nothing to count, so it is ready immediately.
2. Rollback, if ever needed: the same switch, or `--rollback --by=<owner> --reason="…"`. Every count and sale also updates the old model's batch numbers, so the old model sells correctly straight away. `STOCK_V2=off` is the emergency brake.

Then, for each **real** launch product:
1. Studio → Products: name, price (whole KES), description, category, garment type (`set` = top + bottom, `jogger` = bottom, `hoodie` = any single piece), sizes (or `One size`), images.
2. Studio → Stock: **mark it tracked** (a new product is made to order, which means unlimited, until you do), and check its sizes.
3. Count each size from the physical stock take at the shop (**Count**, with a reason such as "Opening stock take 2026-10-xx").
4. Check the product page shows the right sizes and what is left.

Then:
- `php artisan serana:stock:reconcile` must pass.
- `php artisan serana:ledger:verify` must report 0 differences.

## 5. Gate before the first real payment
- [ ] correct tag
- [ ] fresh database
- [ ] migrations complete
- [ ] no seeders run
- [ ] real owner created
- [ ] real settings entered
- [ ] real catalogue
- [ ] real stock counted
- [ ] Drops off: `/drops` returns 404, not in the menu or sitemap
- [ ] live M-Pesa connection test passes
- [ ] HTTPS
- [ ] the scheduler heartbeat moves every minute
- [ ] queue workers are up
- [ ] a mail test arrives: `php artisan serana:mail-test <owner email>`
- [ ] an uploaded product image loads from its public URL
- [ ] `php artisan queue:failed` is empty
- [ ] **`php artisan serana:preflight` is all green**

## 6. Controlled live tests (`docs/audit/CONTROLLED-PRODUCTION-TEST-REPORT.md`)
Use one clearly named low-value test product (for example "Launch test — KSh 10"), tracked, with a count of 2.

1. **Pay.** Check the size, stock, delivery/pickup and total; the STK prompt; the callback; the ledger row; that the order is paid; stock committed once; the receipt email + PDF; tracking; the Studio order.
2. **B-01.** Cancel the first prompt on the phone, retry, pay the second. Expect one order, two attempts, one payment, one stock commit, one receipt.
3. **Reconcile.** Confirm `mpesa:reconcile` runs every minute (scheduler output). A missed callback is settled within about 2–3 minutes.
4. **Refund path.** Record the refunds for the test payments in Studio → Refunds (method, M-Pesa code, reason) so the books close cleanly. Then archive the test product, or turn it into a real one.

The first live payment is the freeze point: from here on, preserve everything.

## 7. After the freeze point
- Deploys only through `deploy/deploy.sh <tag>`, which takes a backup and then migrates.
- Off-server backups on a schedule, with one restore tested.
- After a release cycle with `serana:ledger:verify` at 0, `LEDGER_READS=true` may be set (no deploy needed; set it back to false to roll back).
