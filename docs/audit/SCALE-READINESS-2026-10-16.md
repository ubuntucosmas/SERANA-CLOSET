# Serana Closet: Traffic and Database Readiness

**Date:** 2026-10-16 · **Question:** what happens with 1,000 users at once? · **Method:** measured on the local ddev stack (8 PHP-FPM workers, MariaDB 11.8). Production numbers scale with the server's worker count; the bottlenecks found don't.

## Verdict

**Ready for 1,000 active shoppers, after the fixes below**, on a modest server sized as recommended. Two things would have broken first, and both are fixed:

1. Per-IP rate limits would have locked out real customers sharing mobile-network IPs.
2. The Studio would have become unusable once a few thousand orders existed.

## Measured

| Test | Result |
|---|---|
| Queries per public page | 1–6, no repeated statements, 10–30 ms server time warm |
| Sustained load, 50 concurrent visitors (mixed pages, own sessions) | **214 req/s**, p95 262 ms, **0 errors** (231 req/s with `php artisan optimize`) |
| Sustained load, 150 concurrent visitors | 176 req/s, p95 1.0 s (queueing on 8 workers), **0 errors** |
| 30 and 100 shoppers racing for the last 3 units | **Exactly 3 held, no overselling**, everyone else told "only 0 available". Row locks in checkout hold under contention. |
| Studio with 3,100 orders, before → after | **5.75 MB / 1.03 s → 124 KB / 0.09 s** (browser full load 0.8 s) |

1,000 people actively browsing (a click every ~5 s) is about 200 req/s, which the local 8-worker stack already serves without errors.

## Fixed today

| Problem | Impact at scale | Fix |
|---|---|---|
| **Rate limits counted per IP only** | Kenyan mobile users share public IPs (carrier NAT). In the race test, 10 of 30 shoppers got HTTP 429. The payment-status poll (12/min per paying shopper) allowed 30/min per IP, so **3 people paying on one network were locked out mid-payment**. | Named limiters counted **per browser session**, with a high per-IP ceiling as the abuse backstop (`AppServiceProvider::configureRateLimits`). Re-run: 0 × 429. |
| **Studio sent every order** with full details | About 1.9 KB per order: unusable on a phone within weeks at volume | Server-side paging (30 at a time, newest first), search (name, email, code, item, phone in any format), filters and archive view. Any order opens by link (`?order=ID`) or from Home shortcuts; the editor stays fresh after saving. Tests: `StudioOrderListTest`. |
| **Tracking lookup did a full table scan** (`UPPER(cipher)`) | Every lookup reads every order | Plain equality on the unique index (the column is case-insensitive anyway). |
| **Stock-expiry job scanned all reservations every minute** | Grows forever | Index `(status, expires_at)`, plus `(status, created_at)` on orders. Migration `2026_10_16_000003_add_scale_indexes`. |

## Recommended production setup

| Item | Recommendation | Why |
|---|---|---|
| Server | 2 vCPU / 4 GB RAM to start; PHP-FPM `pm.max_children` about 20–30 (about 60 MB per worker) | About 500+ req/s for these pages. Scale workers before anything else. |
| OPcache and caches | OPcache on; run `php artisan optimize` on every deploy | Measured +8% throughput |
| Cache, rate limits, sessions | **Redis** for `CACHE_STORE` (and optionally sessions and queue) | Every rate-limited request writes the limiter to the cache. On the database driver, that's extra writes under load. |
| Database | MariaDB/MySQL with `max_connections` ≥ PHP workers + queue workers + 20; daily off-server backups | — |
| Email | A **transactional provider** (Brevo, Mailgun, Amazon SES, Postmark) on the brand domain | Personal Gmail SMTP stops at about 500 recipients a day. Each order can send 2–3 emails plus owner copies. |
| Queue workers | 2 workers under supervisor, `--tries=3` | Receipts and order emails go out promptly at peaks |
| Logs | `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning` | A single growing `laravel.log` can fill the disk |
| CDN | Cloudflare (or similar) in front: static assets cached, DDoS protection | Takes the bulk of bytes (images, JS) off the server |
| Monitoring | Uptime check on `/up` (built in) every minute; alert on `queue:failed` growth | Know before customers do |
| M-Pesa | The callback URL must be public HTTPS and reachable | Without callbacks, payments rely on reconciliation, which Safaricom limits to about 5 status checks a minute. |

## Owner decision

- **Drop the leftover Telescope tables** (`telescope_entries`, `_tags`, `_monitoring`): **44 of the database's 47 MB**. The package is no longer installed. Debug logs like these typically contain full request data, possibly customer details, so this is a privacy cleanup too. It's not reversible, so it needs your OK.

## Known limits (fine for launch)

- The shop grid loads the whole catalogue in one request. That's fine for dozens of products, and worth paging past about 300.
- Subscribers are sent in full (email and date only) for the CSV export. That's fine into the tens of thousands.
