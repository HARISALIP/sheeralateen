# Sheera Lateen — Order & Multi-Branch Inventory Management System (OMS)

A production Order Management System (OMS) and Multi-Branch Inventory engine built for **Sheera Lateen** bakery chains in Jeddah & Makkah, Saudi Arabia.

The system connects a **Shopify e-commerce storefront**, **31 physical bakery branches**, and **Leajlak last-mile logistics (DSP)** into a unified real-time dashboard.

---

## 🏗️ Architecture & High-Level Flow

```
[ Shopify Storefront ]
        │
        ▼ (Orders Webhook / Polling)
[ Sheera OMS Core ] ─── GeoService (GPS Geocoding / Nearest Branch Haversine)
        │
        ├─► [ Branch Manager Portal ] ──► Live Stock (0/Set/Add), Out-of-Stock Safety
        │
        ├─► [ Super Admin Master Portal ] ──► All 31 Branches, Catalog Sync, Location Map
        │
        └─► [ Leajlak Logistics API ] ──► Auto-dispatch captains when Order is "Ready"
```

---

## 📂 Project Directory Structure

```
sheeralateen/
├── admin/                    # Super Admin dashboard, reports, settings, master inventory
│   ├── includes/             # Shared admin navigation (sidebar, topbar, header, footer)
│   ├── inventory.php         # Master inventory management across all 31 branches
│   ├── shopify_catalog_sync.php # Pulls catalog, variants & images directly from Shopify
│   ├── shopify_auto_map_locations.php # One-time mapping of Shopify locations to branches
│   ├── orders.php            # Master order processing, filtering & status updates
│   ├── order_details.php     # Full order view with Option B out-of-stock warning banner
│   ├── branches.php          # CRUD branches, GPS coordinates & Leajlak shop IDs
│   ├── managers.php          # CRUD branch managers and assignment
│   └── settings.php          # Core system settings (currency, timezone, tokens)
│
├── manager/                  # Scoped portal for individual Branch Managers
│   ├── includes/             # Manager navigation (sidebar, topbar, header, footer)
│   ├── dashboard.php         # Branch KPIs (Today's orders, pending, delivered)
│   ├── inventory.php         # Branch stock control (Quick Set 0, Update Qty, Audit Log)
│   ├── orders.php            # Orders assigned strictly to this manager's branch
│   └── order_details.php     # Order fulfillment view with Leajlak dispatch & stock alert
│
├── core/                     # Reusable business logic classes & singletons
│   ├── bootstrap.php         # Application initialization, security, session & autoload
│   ├── Database.php          # PDO Singleton connection manager
│   ├── Auth.php              # Authentication, role verification (super_admin vs branch_manager)
│   ├── InventoryService.php  # Stock adjustments, audit trails, and order stock checks
│   ├── ShopifyService.php    # REST & GraphQL client for Shopify Admin API
│   ├── ShopifySyncService.php# Two-way order import, line item sync & fulfillment push
│   ├── LeajlakService.php    # Last-mile delivery dispatch, shop creation, captain tracking
│   ├── GeoService.php        # Nominatim geocoding & SQL Haversine nearest branch algorithm
│   ├── SyncQueue.php         # DB-backed background async queue with exponential backoff
│   ├── ActivityLogger.php    # System-wide immutable activity audit logs
│   └── helpers.php           # Flash messages, XSS escaping, token AES-256 encryption
│
├── api/                      # Public/Authenticated APIs
│   ├── branches.php          # Public JSON list of active branches with GPS coordinates
│   ├── nearest_branches.php  # Haversine-based nearest branch API for storefront widgets
│   └── check_new_orders.php  # Real-time polling API for new incoming orders
│
├── webhooks/                 # External webhook receivers
│   ├── shopify.php           # HMAC-verified Shopify order & inventory webhook gateway
│   └── leajlak.php           # Delivery status updates from Leajlak delivery captains
│
├── database/                 # Database schema and historical migrations
│   ├── schema.sql            # Base schema (branches, users, orders, queue, logs)
│   ├── inventory_migration.sql # Phase 5 inventory tables (catalog, branch stock, audit)
│   └── seed_*.sql / *.php    # Seeds for branches, managers, and product catalog
│
├── shopify-theme/            # Liquid snippets and CSS for Shopify Theme integration
│   ├── snippets/store-pickup-widget.liquid # Store pickup selector modal
│   └── assets/store-pickup.css             # Widget styling
│
├── config/                   # Hostinger database credentials & encryption secrets
│   └── config.php
│
└── .archived_scripts/        # Archived diagnostic and one-off setup scripts (Web-protected)
```

---

## 🔑 Key Features & How They Work

### 1. Multi-Branch Inventory Management (Bypassing Shopify Basic Limits)
- **Problem**: Shopify Basic only permits up to 10 inventory locations, but Sheera has 31 branches.
- **Solution**: The OMS serves as the **Master Brain** for all 31 branches via `branch_inventory`.
- **Branch Managers**: Can tap **"Set Out of Stock" (0)** or **"Update Qty"** with mandatory reasons.
- **Audit Ledger**: All changes are recorded in `inventory_logs` (User, previous qty, new qty, reason, timestamp).

### 2. Smart Order Out-of-Stock Warning (Option B)
- When a customer orders online, `GeoService` assigns the order to the closest branch.
- If that branch has 0 stock of any ordered item, `order_details.php` displays an amber warning banner:
  > *"⚠️ Stock Warning: [Item] is out of stock at this branch. Suggested Alternative: [Neighboring Branch] has stock ([X] available)."*
- The manager/admin can dispatch the delivery captain to pick up from the neighboring branch without canceling the customer's order.

### 3. Shopify Integration & Sync
- **Orders Ingestion**: Incoming webhooks or polling fetch orders, geocodes shipping addresses, and auto-routes orders.
- **Catalog Refresh**: Super Admin can click `Sync Products from Shopify` in `admin/inventory.php` to automatically pull new products, images, and variants into the OMS catalog.
- **Locations**: The 10 available Shopify locations are linked in `branches.shopify_location_id` and updated via Shopify GraphQL mutations.

### 4. Leajlak Delivery Automation
- Moving an order to **"Ready"** automatically creates a delivery task in the Leajlak DSP system.
- Captain details (name, phone) and delivery status (`Out For Delivery`, `Delivered`) stream back into the OMS in real time via `webhooks/leajlak.php`.

---

## 🚀 Deployment & Maintenance

- **Hosting**: Hostinger Cloud PHP 8.1+ / MariaDB.
- **CI/CD**: Git repository connected directly to Hostinger auto-deployment.
  ```bash
  git push origin main
  # Hostinger automatically pulls to public_html within seconds
  ```
- **Cron Jobs**:
  - `GET /admin/shopify_import_cron.php?secret={CRON_SECRET}` (Every 5 mins)
  - `GET /admin/shopify_queue_cron.php?secret={CRON_SECRET}` (Every 1 min)
