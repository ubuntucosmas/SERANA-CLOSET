# Serana Closet: Go-Live Check (storefront ↔ Studio)

**Date:** 2026-10-17 · **Question:** is the storefront connected to the Studio accurately enough to run the business from it? · **Basis:** code trace of checkout, settlement, expiry, Studio queues and catalogue; local data probe; full test suites; real-browser check of the shop and the Studio. Earlier round: `GO-LIVE-CHECK-2026-10-16.md`.

## Verdict

**The code is ready. The deployment still is not:** the blockers from 2026-10-16 are unchanged, because they are release, server and content work, not code. This round fixed six places where the Studio and the storefront told different stories.

## Verified

| Area | Result |
|---|---|
| PHP tests | **177 passed** (7 new in `StorefrontStudioSyncTest`) |
| Frontend tests | **21 passed** (new `stock.test.mjs`, plus a cart cap case) |
| Production build | Clean |
| Browser check | Shop and Studio show the same stock figures (e.g. "2 of 3 left · 1 on hold" ↔ "Only 2 left"). No console errors. |

## How the storefront feeds the Studio (and where it was inaccurate)

| Flow | Path | Status |
|---|---|---|
| Shop / WhatsApp orders → Studio | Checkout → `CustomOrder` + stock holds → `StudioWorkflow` queues | Sound. Counts and lists share one definition. |
| Payment → Studio | M-Pesa callback / reconciliation / Studio confirm → `OrderSettlementService` | Sound. One settlement path, idempotent. |
| Studio settings → storefront | `StoreProfile`, `PaymentSettings` → shared Inertia props | Sound. One source for every page. |
| Stock → storefront | Shop showed **sold count only**, ignoring unpaid holds | **Fixed** |
| Stock → Studio | Low-stock alert ignored holds; the products table showed only the batch limit | **Fixed** |
| Failed payments → Studio | Never expired: piled up in "Payment problems" | **Fixed** |
| "New orders" figure | Counted abandoned and cancelled checkouts | **Fixed** |
| Custom briefs → owner | No alert; found only by opening the Studio | **Fixed** |

## Fixed in this round

| Sev | Issue | Fix |
|---|---|---|
| P1 | A piece held by an unpaid order (WhatsApp holds last 12–72 h) looked buyable. Shoppers filled a bag, then failed at checkout. | `Product::withAvailability()` sums active holds in the same query (no query per product). Shop, product page, home and bag all use one rule (`resources/js/Support/stock.js`). Last units show "Only N left". |
| P1 | Failed M-Pesa checkouts stayed "Payment Failed" for ever, burying real problems (amount mismatches) in the review queue. Locally, 10 of 17 items. | Expired after the payment window, like unanswered prompts. Nothing was charged. Any prompt in the window blocks expiry, so retries still work. Amount mismatches are never touched. |
| P1 | No owner alert for new custom briefs | A queued email to the notification address carries the brief, an **Open in Studio** link straight to the order, and a WhatsApp link to the customer. |
| P2 | "New orders · last 7 days" counted abandoned and cancelled checkouts | Both are excluded. The help text explains it. |
| P2 | Studio low-stock and products table ignored holds | The table shows "*left* of *limit* · *sold* · *on hold*" and a Sold out badge. The alert notes the holds. |
| P2 | The product update accepted `batch_sold` from a form, so a stale tab could overwrite real sales | Removed. Only settlement moves the sold count. |
| P3 | The Studio's "message customer" link hard-coded the store name and broke on numbers typed without 0/254 | Uses the store profile name and normalises 7…/07…/+254. |

## Release status (updated 2026-10-18)

| Item | Done here | Left for the server (needs access) |
|---|---|---|
| Commit and tag | Committed in reviewed groups; tagged `v1.0.0-rc.1` | Push `master` and the tag; deploy only with `deploy/deploy.sh v1.0.0-rc.1` |
| Admin password | `php artisan serana:admin <email>` creates or resets an admin (hidden prompt or `--generate`, ends other sign-ins). Local seeded admins reset. | On the server: `php artisan serana:admin you@<domain> --name="…"`. Never run the seeders there (they refuse). |
| Production settings | `deploy/env.production.example`; `php artisan serana:preflight` checks every setting and exits non-zero on a blocker | Fill the real `.env`: domain, brand-domain SMTP with SPF/DKIM/DMARC, live Daraja keys when the till is approved (WhatsApp-only until then) |
| Trusted proxies | Only addresses in `TRUSTED_PROXIES` may set `X-Forwarded-*` (unset = trust none; `hardening/batch-1`). Preflight warns about `*` combined with `MPESA_ALLOWED_IPS`, and about forwarded requests from an untrusted address in the last 24 h (the app logs these hourly). | **Nginx facing the internet directly:** leave `TRUSTED_PROXIES` blank. **Behind Cloudflare:** set it to Cloudflare's published ranges, both <https://www.cloudflare.com/ips-v4> and <https://www.cloudflare.com/ips-v6>, comma-separated. Re-check them occasionally, because Cloudflare changes them. Never use `*`. Without this, every shopper appears as a Cloudflare IP (they share one per-IP rate limit) and HTTPS looks like HTTP. **Behind a load balancer:** its private IP or subnet. Then run `php artisan serana:preflight`: no proxy warning. |
| Background jobs | `deploy/crontab`, `deploy/supervisor-serana-worker.conf`; the scheduler writes a heartbeat that preflight checks. Locally ddev now runs both (`.ddev/config.yaml`): the July backlog expired on the first tick. | Install the cron line and the supervisor program |
| Backup, then migrate | `deploy/deploy.sh` dumps the database and uploads first and stops if the dump is empty, then migrates, caches, restarts workers and runs preflight. Local snapshot: `ddev snapshot restore pre-release-2026-10-18`. | Copy the backups off the server |
| Legal pages | — | Owner content: privacy notice, terms, delivery and returns |

Then run the live tests in runbook steps 13–20 (`CONTROLLED-PRODUCTION-TEST-REPORT.md`) before tagging `v1.0.0`.

## Still open after launch

- ~~P2 `trustProxies('*')`: set the host's real proxy IPs.~~ Fixed in code (`1f09def`): set `TRUSTED_PROXIES` on the server (see Trusted proxies above).
- P2 Server-side size validation per garment type at checkout.
- P2 Payments confirmed by reconciliation carry no M-Pesa receipt code.
