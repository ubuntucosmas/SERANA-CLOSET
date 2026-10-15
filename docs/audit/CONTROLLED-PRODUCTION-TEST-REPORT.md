# SERANA-CLOSET CONTROLLED PRODUCTION TEST REPORT

**Date:** 2026-10-15 · **Prepared from:** the developer workstation (ddev). **No production access was available from this environment.** There is no deployment configuration in the repository, no server credentials and no production database, and no live M-Pesa test was possible. Everything that could be done locally is done and verified. Every production step is marked **NOT EXECUTED**, with the exact procedure in the runbook at the end. Nothing was pushed or deployed.

Status words used: **DONE** (verified here) · **CODE-VERIFIED** (proven by automated tests / local run; production still to confirm) · **NOT EXECUTED** (needs the production host or a phone) · **NEEDS VERIFICATION** (value unknown from here).

---

## Git / Release Revision

**Release candidate: `master` @ the `docs(release)` commit that adds this report.** It sits on top of `7d6210a`; every code commit was individually verified. These commits are local only (`origin/master..HEAD` = 28 commits: 16 earlier unpushed + 12 from this release preparation).

| # | Commit | Purpose | Tree verified |
|---|---|---|---|
| 1 | `68bf573` chore(seed): refuse demo seeders outside local and testing | Seeder safety | build ✅ · 83 tests ✅ |
| 2 | `e712560` fix(payments): settle paid retries and harden the M-Pesa money path | **B-01** + payment integrity | build ✅ · 88 ✅ |
| 3 | `ba0bf28` fix(checkout): enforce stock across size lines and make submissions idempotent | Stock / duplicate-submission integrity | build ✅ · 94 ✅ |
| 4 | `db19b67` feat(seo): server-render page metadata, sitemap and robots | SEO (+ the Blade parse-error fix) | build ✅ · 96 ✅ |
| 5 | `d94c125` fix(studio): protect payment state, commerce totals and uploads | Studio safeguards (+ whole-shilling prices) | build ✅ · 109 ✅ |
| 6 | `350bfa7` feat(storefront): clarify the shopping, payment, tracking and bespoke journeys | Storefront clarity, bag fix, error pages | build ✅ · 109 ✅ · cart 6 ✅ |
| 7 | `f9442b0` fix(drops): pause paid Drops for V1 behind DROPS_ENABLED | Drops disablement | build ✅ · 110 ✅ · cart 6 ✅ |
| 8 | `0f54cc9` chore(v1): remove deferred experiments and dead assets | Removal of experiments | ✅ |
| 9 | `5cb47e3` chore(release): align .env.example with the ddev stack and production flags | Config docs | ✅ |
| 10 | `b310a1a` docs(release): add the system audit, V1 release report and verification evidence | Docs | ✅ |
| 11 | `7d6210a` test(payments): prove the callback fails closed without a configured token | Callback security test | 111 ✅ |
| 12 | docs(release) — this report | Docs | — |

**How each commit was verified:** in an isolated git worktree with its own `vendor` copy (a symlinked `vendor` would have autoloaded the main tree's classes), so each commit's *exact* tree was built and tested, not the working copy. Tightly coupled code and tests stay together. Files that mixed two concerns were split by staging content variants, never by editing the working tree.

**Process note:** one commit was briefly misfiled (the experiments changes landed under the `.env.example` message because a chained command didn't stop on a `git diff --check` failure). It was caught immediately and corrected with a soft reset of the three unpushed commits; no working-tree change, nothing lost. The history above is the corrected one.

## Working Tree Status

**DONE: clean.** `git status` is empty; nothing staged; `git diff --check` is clean across the entire release range.

**Commit hygiene checks (all passed):**
- No `.env`, credentials, tokens, keys or passwords committed.
- `.env.example` holds only Safaricom's **public sandbox** defaults and the ddev local DB password.
- No debug statements.
- No `node_modules`, `vendor` or `public/build`.
- Browser scratch output kept out of the repo, except the small, intentionally retained evidence files under `docs/audit/verification/` (20KB).

## Deployment Backup

**NOT EXECUTED (no production access).** Procedure: runbook step 2. Record timestamp, database name and backup location in the deploy log. If production uses the `public` disk or S3 for uploads, include `storage/app/public` (or confirm bucket versioning).

## Database Migration Result

**NOT EXECUTED.** First run `php artisan migrate:status` on production; what production has actually applied is unknown from here. If production is at `origin/master` (`0e0e894`), these four migrations are pending:

| Migration | Change | Classification |
|---|---|---|
| `2026_06_19_191836_create_drops_table` | New `drops` table; nullable `custom_orders.drop_id` FK (null on delete) | **SAFE** (additive) · backup first |
| `2026_06_19_202937_add_sizes_to_drops_table` | Nullable JSON column on `drops` | **SAFE** |
| `2026_06_20_081837_add_hot_path_indexes_to_custom_orders` | Two indexes on `custom_orders` | **SAFE** (online DDL; small table) |
| `2026_07_07_000001_create_payment_attempts_table` | New `payment_attempts` table | **SAFE** (additive) · **must exist before any live payment** |

None are destructive, none need a maintenance window, none block the deploy. The current code requires all four (`payment_attempts` is on the payment path).

## Environment Readiness

**NEEDS VERIFICATION for every item:** the production `.env` isn't visible from here. Mark each on the server (never paste values into tickets or chat):

| Item | Required value / check | Status |
|---|---|---|
| `APP_ENV` | `production` | NEEDS VERIFICATION |
| `APP_DEBUG` | `false` | NEEDS VERIFICATION |
| `APP_URL` | `https://<real domain>` | NEEDS VERIFICATION |
| `APP_KEY` | Set (never regenerate on an existing install) | NEEDS VERIFICATION |
| `SESSION_SECURE_COOKIE` | `true` | NEEDS VERIFICATION |
| `DB_*` | Production database | NEEDS VERIFICATION |
| `MAIL_*`, `MAIL_FROM_ADDRESS`, `STORE_NOTIFICATION_EMAIL` | Working SMTP; owner copy address | NEEDS VERIFICATION |
| `QUEUE_CONNECTION` | `database` (not `sync`) | NEEDS VERIFICATION |
| `CACHE_STORE` | `database` (needed for scheduler locks and session blocking) | NEEDS VERIFICATION |
| `FILESYSTEM_DISK`, `FILESYSTEM_DISK_PUBLIC` (+ `AWS_*` if S3/R2) | Matches where existing uploads live; `php artisan storage:link` if local | NEEDS VERIFICATION |
| `WHATSAPP_NUMBER` / Studio WhatsApp setting | Real store number | NEEDS VERIFICATION |
| `DROPS_ENABLED` | **unset or `false`** | NEEDS VERIFICATION |
| M-Pesa keys | See below | NEEDS VERIFICATION |

## Scheduler Status

**CODE-VERIFIED · production NOT EXECUTED.** Locally, `schedule:list` shows `stock:release-expired` (every minute), `mpesa:reconcile` (every minute, background, no overlap) and `drops:close-expired` (every 5 min). Production needs exactly one cron entry (runbook step 11). No Redis or Horizon needed.

## Reconciliation Status

**CODE-VERIFIED.** Five regression tests prove B-01: a paid retry is settled after the callback is lost, settles once, commits stock once and fires the receipt event once, and a second run or late callback changes nothing. **Production execution: NOT EXECUTED.** `schedule:list` alone isn't proof, so the runbook (step 11) logs scheduler output for the test window and confirms a `Running … mpesa:reconcile` line appears every minute.

## Queue Worker Status

**CODE-VERIFIED · production NOT EXECUTED.**
- **Queued work:** only `SendOrderConfirmation`, the receipt email.
- **Retries:** 3 attempts, then 30s / 120s backoff.
- **When it fails:** a failure is logged without PII and rethrown, so it lands in `failed_jobs` (the table exists in the base migration). Inspect with `php artisan queue:failed`; retry with `queue:retry all`.
- **Settlement never depends on it:** the paid flip and stock commit happen in one transaction, and the receipt event is dispatched only after commit, so a mail failure cannot touch `is_paid`, stock or the customer's payment state.

## Mail Status

**NOT EXECUTED.** Proven locally only by tests (customer + owner Bcc once; owner-only when there's no customer email; no mail on failed payment; template renders). The real receipt arrives in live test #1.

## M-Pesa Configuration Status

**NEEDS VERIFICATION** (no access to production values):

| Key | Requirement |
|---|---|
| `MPESA_ENV` | `production` |
| `MPESA_CONSUMER_KEY` / `MPESA_CONSUMER_SECRET` | Production Daraja app |
| `MPESA_SHORTCODE` / `MPESA_PASSKEY` | Production values; **not** sandbox `174379` / the public sandbox passkey |
| `MPESA_CALLBACK_TOKEN` | Random 48-hex secret; **empty means every callback is rejected** |
| `MPESA_CALLBACK_URL` | `https://<domain>/payments/mpesa/callback/<token>`, public and reachable by Safaricom |
| `MPESA_ALLOWED_IPS` | Optional; enforced only when `MPESA_ENV=production` |

**Callback security (CODE-VERIFIED, unchanged, fail-closed):**
- **Rejected:** a wrong token (403), no server token configured (403), a URL with no token (404), and a malformed payload (validation; no state change).
- **Never settles:** a wrong or nearby amount, which is flagged as Amount Mismatch with an owner alert.
- **No double settlement:** duplicate and late callbacks don't settle twice; a duplicate success is booked as refund-owed.
- **Confirmed locally:** under cached config, a wrong-token callback over HTTP returns 403.

## Live M-Pesa Test Result

**NOT EXECUTED:** requires the production deploy and a phone. Procedure: runbook step 13.

## B-01 Live Retry Test

**NOT EXECUTED.** The code path is proven by `test_reconciliation_settles_paid_retry_after_payment_failed` and four companion tests. Live procedure: runbook step 14.

## Stock Hold/Expiry Test

**CODE-VERIFIED** (holds, re-acquisition on retry, expiry sparing in-flight payments, combined-size caps, zero-limit = sold out) · **production NOT EXECUTED** (runbook step 15).

## Track Order Result

**CODE-VERIFIED.** The tracking payload reports `is_paid = true` after settlement (B-01 test). In the local Chrome flow, the confirmation screen links to tracking. Production: runbook steps 13–14.

## Studio Result

**CODE-VERIFIED** (tests: `is_paid` can't be overridden; unpaid commerce orders can't be fulfilled; a paid order progresses without total changes; settings reject unknown keys and SVG/PHP uploads; prices must be whole shillings) · **production NOT EXECUTED** (runbook step 16).

## Bespoke Result

**CODE-VERIFIED.** The brief creates an SRN reference; a repeated submission returns the same reference; no payment and no surcharge. The page states "No payment is taken here" (Chrome-verified earlier on this tree). Production: runbook step 17.

## Drops Disabled Verification

**DONE locally under `config:cache` + `route:cache`:**
- `/drops` and Drop detail return 404.
- The sitemap has 0 Drop links.
- Tests prove reserve returns 404 and STK on an unpaid drop order returns 409.
- The nav, home launchpad and hero copy are hidden (Chrome-verified earlier).

Production: re-check in runbook step 18.

## HTTPS/Security Verification

**NOT EXECUTED in production.** Custom 401/403/404/419/429/500/503 pages exist and don't need JavaScript. Stack traces depend on `APP_DEBUG=false`, which must be verified on the server. Procedure: runbook step 19.

## Errors Seen

During this preparation:
1. **P1, fixed (`d94c125`):** the catalogue accepted fractional prices (e.g. 4999.50), but M-Pesa initiation correctly refuses fractional amounts, so such a product could be ordered but never paid. Prices are now validated as whole shillings, with a test.
2. **Process slip, corrected:** the misfiled commit described above.
3. **No test, build or cache failures** on any commit's final tree.

## Remaining P0

**None in code.** The production gates below are unverified rather than failed, but none of them may be skipped: backup, migrations, environment, cron, worker, M-Pesa configuration, admin accounts.

## Remaining P1

**None known in code.** Must be confirmed by the live tests (payment, retry, receipt, Studio).

## Deferred P2/Post-V1

- **Drops:** the failed-target policy, then a refund queue, then enable.
- **Bespoke:** online deposit; Expedite if it's confirmed.
- **Sizing:** extra schemes when such products launch.
- **Order data:** separate payment and fulfilment status columns.
- **Studio:** a refund-owed / duplicate-payment view; protect payment evidence when an order is force-deleted.
- **Notifications:** dispatch messages to customers.
- **Performance:** WebP hero images, subset the icon font, lazy loading.
- **Studio speed:** settings cache, pagination.
- **Tooling:** Pint config with one formatting commit, CI, Larastan.
- **Accounts:** link guest orders to accounts.
- **Only with confirmed demand:** Stripe, multi-currency, i18n, AI.

## Legal Content Status

**BUSINESS CONTENT REQUIRED.** No privacy policy, terms, delivery or returns pages exist, and none were invented. No `#` links are visible. These are acceptable to leave pending for a controlled test, but they're an **explicit requirement before public launch** (a privacy notice is expected under Kenya's Data Protection Act 2019).

---

# Production runbook (for the operator with server access)

Adapt paths to the host. Never echo secrets.

1. **Pre-flight (no changes):** `php artisan --version`, `php artisan migrate:status`, `git rev-parse HEAD` (current production revision, which is the rollback target). Admin inventory: `SELECT id, email, created_at FROM users WHERE role='admin';` and confirm each account. If `admin@serana.closet` / `admin@serana.com` exist, delete them or set a new password (`php artisan tinker` → `User::where('email', …)->first()->update(['password' => Hash::make(…)])`, typed directly, never pasted into logs). Create the owner account the same way. **Never run `db:seed`.**
2. **Backup:** `mysqldump --single-transaction --routines --triggers <db> | gzip > serana-$(date +%F-%H%M).sql.gz` stored off-server. Back up uploads (`storage/app/public`) or confirm S3/R2 versioning. Record timestamp and location.
3. `php artisan down --retry=60` (optional; the migrations are additive).
4. Deploy the release commit (push `master` from the workstation, then pull on the server, or the host's existing method).
5. `composer install --no-dev --optimize-autoloader`
6. `npm ci && npm run build` (or upload `public/build` if building elsewhere)
7. `php artisan migrate --force`, then `migrate:status` (all four above should now read "Ran")
8. Set and verify the production `.env` (checklist above); keep `DROPS_ENABLED` unset or false.
9. `php artisan config:cache && php artisan route:cache && php artisan view:cache` (`storage:link` if using the local public disk)
10. **Queue worker:** systemd/supervisor `php artisan queue:work --tries=3 --max-time=3600`, then `php artisan queue:restart` after every deploy. Cron-only hosts: `* * * * * cd /path && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1`.
11. **Scheduler:** `* * * * * cd /path && php artisan schedule:run >> storage/logs/schedule.log 2>&1` (log during the test window; switch back to `/dev/null` later if wanted). Within 2 minutes, `storage/logs/schedule.log` must show `mpesa:reconcile` and `stock:release-expired` running each minute. That's the proof, not `schedule:list`.
12. `php artisan up`. Restrict who knows the URL until the release decision.
13. **Live test #1:** buy the cheapest product at a low test price, then check:
    - STK reaches the phone; pay.
    - Studio shows Payment Confirmed, and the stock count drops exactly once.
    - Track Order (reference + phone) shows Payment Confirmed, with fulfilment shown separately.
    - The receipt email arrives (and the owner Bcc, if configured).
    - `php artisan queue:failed` is empty.
    - In the database: a single `payment_attempts` row with status `paid`, and the order with `is_paid=1`.
14. **B-01 live:** checkout → cancel the first prompt on the phone → wait for "Payment Failed" → retry → pay. Check:
    - One order.
    - Two attempts: the first `failed`, the second `paid`.
    - The order is paid; stock committed once; one receipt.
    - Track Order and Studio both show paid.
15. **Stock expiry:** start a checkout and abandon it. After 15 minutes (plus one scheduler tick) the order is `Expired`, its hold is released and availability is restored.
16. **Studio** (owner account): orders visible; the payment checkbox and total can't be changed on a paid order; fulfilment moves forward; a product edit saves; a settings save works; the old dev accounts are refused.
17. **Bespoke:** submit a brief; you get an SRN reference, WhatsApp opens, and nothing is charged.
18. **Drops:** `/drops` returns 404; no Drops link in the nav; `/sitemap.xml` has no `/drops`.
19. **HTTPS/security:** every page is HTTPS with no mixed-content warnings in the console; the session cookie has the `Secure` flag; a bad URL shows the branded 404 with no stack trace; `POST /payments/mpesa/callback/wrong` returns 403.
20. **Watch logs during tests:** `storage/logs/laravel.log` for callback, reconcile, queue, mail, DB errors and 500s. (The code logs no PINs, tokens, secrets or receipt bodies.)

## Rollback plan

- **Application:** redeploy the revision recorded in step 1, `composer install`, rebuild assets, `config:cache`, `route:cache`, `view:cache`, `queue:restart`.
- **Database:**
  - **Before any live payment:** `migrate:rollback --step=4` is safe.
  - **After a live payment:** do **not** roll back `payment_attempts`. It holds payment evidence. The four migrations are additive and the previous code ignores the extra tables and columns, so leave the schema in place and roll back the application only.
  - **Restore:** the step-2 dump is the last-resort restore.
- **Configuration:** keep a copy of the previous `.env` and restore it with the previous revision.
- **Worker and cron:** `queue:restart` after rollback; the cron line is revision-independent.

---

**Release recommendation: DO NOT LAUNCH** until the controlled production test above has been run and passes. This is not a defect verdict. The code is deployable, and every commit is verified. But zero production checks have been performed (backup, migration, configuration, cron, worker, live payment, B-01 retry, receipt), and public launch requires that evidence. Once runbook steps 13–19 pass with no P0 or payment/stock/security P1, the expected outcome is **READY FOR LIMITED/BETA USE ONLY**, moving to **READY FOR PUBLIC LAUNCH** when the legal content is published.
