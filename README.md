# Serana Closet | The Digital Atelier

Serana Closet is a high-end, Tech-Noir fashion ecosystem merging traditional Kenyan tailoring excellence with futuristic digital precision. This platform is engineered to facilitate elite bespoke commissions and limited-batch ready-to-wear drops.

## 🎭 The Serana Experience (Key Features)

### 1. The Digital Atelier (Custom Orders)
A high-friction-to-high-conversion flow. Clients can upload inspiration, select premium fabrics, and provide precise measurements. The system generates an **Algorithmic WhatsApp Payload** to bridge the gap between digital intent and artisan execution.

### 2. The Serana X-Ray
Every product showcase features a technical "X-Ray" overlay, revealing the hidden structural excellence (stitch density, fabric weight, internal architecture) that justifies a premium price point.

### 3. Studio Pulse & Registry
- **Live Studio Pulse**: Real-time business intelligence reflected in the navigation (Atelier capacity, current batch status).
- **The Registry**: An exclusive "Inner Circle" lead generation system for limited-batch drop notifications.

### 4. Artisan Journey (Orders Vault)
Clients can track their garment's evolution from "Digital Mapping" to "Final Stitch" via the private **Designs Vault**, which archives their personal fit coordinates for future ease-of-order.

## 🛠 Technical Architecture

- **Backend**: Laravel 12 (PHP 8.2) with Inertia.js 2
- **Frontend**: Vue 3 (Composition API), Pinia (cart), Vite 7, Tailwind CSS 3
- **Database**: MariaDB 11.8 (local via ddev, and production). Tests use SQLite in memory by default, and MariaDB in CI.
- **Payments**: M-Pesa STK push (Safaricom Daraja), plus WhatsApp orders confirmed in the Studio
- **Background work**: a scheduler every minute (stock-hold expiry, M-Pesa reconciliation) and a queue worker (receipt and alert emails)

The full map of the codebase is in [`docs/PROJECT_UNDERSTANDING.md`](docs/PROJECT_UNDERSTANDING.md).

## 🚀 Getting Started (local, with ddev)

There is no host PHP: everything runs inside [ddev](https://ddev.com), which also runs the scheduler and the queue worker (`.ddev/config.yaml`).

```bash
ddev start
cp .env.example .env                       # only if .env is missing
ddev composer install
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed       # demo data; seeders refuse to run outside local/testing
npm ci && npm run dev                      # or: npm run build
```

The site is at `https://serana-closet.ddev.site`. Create or reset a Studio admin with `ddev exec php artisan serana:admin you@example.com`.

ddev has no outbound DNS, so real M-Pesa prompts can't be sent locally. The tests fake Daraja.

### Tests

```bash
ddev exec php artisan test        # SQLite (MariaDB-only concurrency tests skip)
node --test 'tests/Frontend/*.test.mjs'

# Same PHP suite on MariaDB. Tests wipe the database, so use one whose name contains "test":
ddev exec 'mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS testing; GRANT ALL ON testing.* TO \"db\"@\"%\""'
ddev exec 'DB_CONNECTION=mariadb DB_DATABASE=testing php artisan test --fail-on-skipped'
```

### Production

See `deploy/` (`deploy.sh`, `crontab`, the supervisor worker, `env.production.example`) and run `php artisan serana:preflight` on the server after every deploy.

## 🔐 Administrative Access
Admin users can access the **Studio Dashboard** (`/studio`) to manage:
- Active Commissions & Leads.
- Catalog Inventory & Scarcity Batching.
- Artisan Capture Uploads (Order Progress).

---
**Standard: Built by Hand. Perfected by Code.**
**Proprietary to Serana Closet © 2026**
