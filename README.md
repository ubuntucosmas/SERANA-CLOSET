# Serana Closet

The shop and back office of Serana Closet, a Nairobi fashion studio. One Laravel app does four jobs:

- **Storefront**: shop ready-to-wear pieces by size, pay by M-Pesa (STK prompt) or order on WhatsApp, deliver or collect from the shop, and track an order with its reference and phone number (`/track`).
- **Custom orders (bespoke)**: the customer sends a brief; the Studio quotes it and sends a pay link for the deposit and balance.
- **Studio** (`/studio`): orders, payments needing a decision, refunds owed, products, stock per size, staff, settings and the activity history.
- **Sales Desk** (`/pos`): the shop tablet. Staff unlock it with a PIN for counter sales (cash, an M-Pesa prompt or a typed M-Pesa code, store credit), pickups, returns and the cash drawer.

Background work matters: a scheduler confirms M-Pesa payments whose callback never came and releases abandoned stock holds, and a queue worker sends receipts and alerts.

## Stack

Laravel 12 (PHP 8.2), Inertia 2, Vue 3, Pinia (the bag), Tailwind 3, Vite 7. MariaDB 11.8 locally and in production; tests run on SQLite by default and on MariaDB in CI. The codebase map is in [`docs/PROJECT_UNDERSTANDING.md`](docs/PROJECT_UNDERSTANDING.md).

## Run it locally (ddev)

There is no PHP on the host: run PHP through ddev. ddev also runs the scheduler and the queue worker (`.ddev/config.yaml`); mail goes to Mailpit.

```bash
ddev start
cp .env.example .env                       # only if .env is missing
ddev composer install
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed       # demo data; the seeders refuse to run outside local/testing
npm ci && npm run dev                      # or: npm run build
ddev exec php artisan serana:admin you@example.com   # create or reset a Studio owner
```

The site is at `https://serana-closet.ddev.site`. ddev has no outbound DNS, so real M-Pesa prompts can't be sent locally; the tests fake Daraja.

## Test it

```bash
ddev exec php artisan test                      # SQLite; the MariaDB-only race tests skip
node --test 'tests/Frontend/*.test.mjs'

# The same PHP suite on MariaDB (runs the concurrency tests). Tests wipe the
# database, so use one whose name contains "test" (TestCase refuses others):
ddev exec 'mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS testing; GRANT ALL ON testing.* TO \"db\"@\"%\""'
ddev exec 'DB_CONNECTION=mariadb DB_DATABASE=testing php artisan test --fail-on-skipped'
```

After every test, `LedgerVerifier` checks that each order still agrees with its ledger, so a test that leaves money inconsistent fails.

## Deploy it

Everything is in [`deploy/`](deploy/):

- [`CLEAN-SLATE-V1-RUNBOOK.md`](deploy/CLEAN-SLATE-V1-RUNBOOK.md): the first install, from an empty database.
- `deploy.sh <tag>`: every later release (backs up, migrates, builds, runs preflight).
- `crontab`: `schedule:run` every minute and the nightly backup. `supervisor-serana-worker.conf`: the queue worker.
- [`RESTORE.md`](deploy/RESTORE.md): backups, the restore drill, and a real restore.
- `env.production.example`: every production setting.
- [`docs/audit/V1-CONTROLLED-LAUNCH-CHECKLIST.md`](docs/audit/V1-CONTROLLED-LAUNCH-CHECKLIST.md): what to prove on the live server before taking real money.

After every deploy, `php artisan serana:preflight` must say "Ready to take orders". An external monitor should watch `/health` (it returns 503 naming what is failing: database, scheduler, queue).

### Services that must run in production

| Service | Why | If it stops |
|---|---|---|
| Web (PHP-FPM + HTTPS) | The shop, Studio and Sales Desk | Site down |
| `schedule:run` every minute | Confirms missed M-Pesa callbacks, expires unpaid checkouts, releases stock holds, emails operator alerts | Paid orders stay "pending"; stock stays held. Studio home and `/health` warn |
| Queue worker | Receipts, staff alerts, password emails | No emails. Studio home and `/health` warn |
| MariaDB ≥ 11.6, `innodb_snapshot_isolation=ON` | Money and stock race safety depends on it | Preflight refuses to pass |
| Nightly backup, copied off the server | Recovery | Studio home warns after 26 hours |

### Settings that change behaviour (`.env`)

| Setting | Production value | What it does |
|---|---|---|
| `STOCK_V2` | `setting` | Stock per size. `setting` lets the owner switch it in the Studio; `on`/`off` force it. |
| `LEDGER_READS` | `false` | Reads order money from the legacy columns; `true` reads the ledger. Both are always written. Switch only after `serana:ledger:verify` reports 0 differences. |
| `DROPS_ENABLED` | `false` | Paid pre-order "Drops". Paused until the refund policy for a failed drop is decided. |
| `MPESA_ENV`, `MPESA_*` | live keys | M-Pesa. `MPESA_CALLBACK_TOKEN` is a secret in the callback URL. |
| `MPESA_PROMPT_WINDOW_SECONDS` | `150` (default) | How long one unanswered prompt blocks another for the same order. |

Everything else a shop owner changes (contact details, delivery zones and fees, payment methods, deposit %, holds, discount limits) is in **Studio → Settings**, not in code.

### Never do this in production

- Never run `migrate:fresh`, `db:wipe`, `db:seed` or truncate tables once the first real M-Pesa payment exists. Only `deploy/deploy.sh` migrations.
- Never run artisan with `--env=testing` on a server or in ddev: there is no `.env.testing`, so it falls back to `.env` and the real database.
- Never turn off `innodb_snapshot_isolation`.
- Never edit money or stock rows by hand. Use the Studio (record payment, reverse, refund, stock count) so the ledger, the audit trail and the stock history stay explainable. `serana:ledger-check` and `serana:stock:reconcile` must stay clean.
- Never set `APP_DEBUG=true` or commit `.env`.

## Where the decisions are

- [`docs/audit/V1-RELEASE-REPORT.md`](docs/audit/V1-RELEASE-REPORT.md): what V1 includes and why.
- [`docs/audit/ISSUE-REGISTER.md`](docs/audit/ISSUE-REGISTER.md): known issues (SC-…).
- [`docs/design/BATCH-2-DESIGN.md`](docs/design/BATCH-2-DESIGN.md): the money, stock and Sales Desk design.
- [`docs/audit/FINAL-CLEANUP-2026-10-25.md`](docs/audit/FINAL-CLEANUP-2026-10-25.md): the last pre-launch cleanup and what to build next (or not).

Proprietary to Serana Closet © 2026.
