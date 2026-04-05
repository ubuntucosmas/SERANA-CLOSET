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

- **Backend**: Laravel 11.x (Inertia.js)
- **Frontend**: Vue 3 (Composition API)
- **Styling**: Tailwind CSS (Custom "Neon Dark" Design System)
- **State Management**: Pinia (Cart & Session Persistence)
- **Database**: SQLite (Production-ready for High-Performance caching)

## 🚀 Getting Started

1.  **Install Dependencies**:
    ```bash
    composer install
    npm install
    ```
2.  **Environment Setup**:
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```
3.  **Database Initialisation**:
    ```bash
    php artisan migrate:fresh --seed
    ```
4.  **Launch**:
    ```bash
    npm run dev
    php artisan serve
    ```

## 🔐 Administrative Access
Admin users can access the **Studio Dashboard** (`/studio`) to manage:
- Active Commissions & Leads.
- Catalog Inventory & Scarcity Batching.
- Artisan Capture Uploads (Order Progress).

---
**Standard: Built by Hand. Perfected by Code.**
**Proprietary to Serana Closet © 2026**
