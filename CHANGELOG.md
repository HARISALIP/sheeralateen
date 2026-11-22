# Sheera OMS — Changelog, Architecture Decisions & Cleanup Log

This document records the architectural decisions, newly added features, and codebase cleanups executed during the **Branch-Level Inventory Management & Shopify Synchronization** project.

---

## 📋 Table of Contents
1. [Core Architectural Decisions](#1-core-architectural-decisions)
2. [Phase 5: Inventory Engine Additions](#2-phase-5-inventory-engine-additions)
3. [Database Migrations Summary](#3-database-migrations-summary)
4. [File System Cleanups & Archived Scripts](#4-file-system-cleanups--archived-scripts)
5. [Security Hardening & Safeguards](#5-security-hardening--safeguards)

---

## 1. Core Architectural Decisions

### A. The 10-Location Shopify Limit Solution
- **Context**: The store runs on Shopify Basic/Standard, which strictly caps active inventory locations at **10**. Sheera operates **31 physical branches** in Jeddah & Makkah.
- **Decision**: Avoid purchasing expensive Shopify Plus ($2,000+/mo). Instead, establish the **Sheera OMS database as the Master Source of Truth for all 31 branches**.
- **Result**:
  - All 31 branches have independent stock tracking in `branch_inventory`.
  - The 10 branches that correspond to physical Shopify locations synchronize two-way with Shopify GraphQL.
  - The remaining 21 branches operate with full stock management and audit trails within OMS.

### B. Option B Storefront Architecture (Zero Customer Disruption)
- **Context**: Customers ordering bakery items online typically do not know or select physical branch names (storefront pickup widgets were intentionally removed to reduce friction).
- **Decision**: Customers shop normally. Address geocoding (`GeoService`) automatically resolves the nearest branch upon order placement.
- **Safety Net**: If the assigned branch happens to be out of stock of an ordered item, an amber warning box is surfaced to both the Branch Manager and Super Admin (`order_details.php`), identifying the out-of-stock product and recommending the closest branch that has available inventory.

---

## 2. Phase 5: Inventory Engine Additions

| File | Purpose |
|---|---|
| `database/inventory_migration.sql` | Creates `products_catalog`, `branch_inventory`, and `inventory_logs` without touching existing tables. |
| `core/InventoryService.php` | Encapsulates all inventory mutations, stock delta adjustments, audit logging, and order stock validation. |
| `manager/inventory.php` | Branch manager stock screen (Search, Filter, Quick Set 0, Add/Deduct modal, and live Audit History). |
| `admin/inventory.php` | Super Admin master view across all branches with branch selector and catalog sync trigger. |
| `admin/shopify_catalog_sync.php` | Directly connects to Shopify Admin API to fetch new products, images, prices, and variant IDs. |
| `admin/shopify_auto_map_locations.php` | Interface to map active Shopify Location IDs to OMS branch codes. |
| `manager/order_details.php` | Injected with real-time stock verification against branch inventory slots. |
| `admin/order_details.php` | Injected with real-time stock verification and alternative branch suggestion. |

---

## 3. Database Migrations Summary

All migration files are organized in the `/database` directory:

| Migration File | Description |
|---|---|
| `database/schema.sql` | Complete base schema (branches, users, orders, order_items, notes, history, sync_queue, webhook_logs). |
| `database/inventory_migration.sql` | New Phase 5 inventory tables (`products_catalog`, `branch_inventory`, `inventory_logs`). |
| `database/migration_add_coords.sql` | Adds GPS coordinates (`latitude`, `longitude`) to branches for Haversine routing. |
| `database/migration_add_leajlak_shop_id.sql` | Adds branch-specific `leajlak_shop_id` for delivery dispatch. |
| `database/migration_add_captain_info.sql` | Adds `leajlak_captain_name` and `phone` to orders. |
| `database/migration_leajlak_status.sql` | Adds delivery tracking status column to orders. |
| `database/migration_add_remember_tokens.sql` | Persistent Remember Me token authentication. |
| `database/migration_set_jeddah_timezone.sql` | Configures Saudi Arabia (`Asia/Riyadh`) timezone settings. |

---

## 4. File System Cleanups & Archived Scripts

Over 35 temporary, duplicate, and one-off debug scripts from previous integration phases were removed from the project root and secured into `.archived_scripts/`:

### Archived Diagnostic & Test Scripts:
- `check_token.php`, `check_secret.php`: Previously exposed sensitive API keys in plain text.
- `debug_webhook.php`, `check_webhooks.php`: Previously printed raw customer payload logs.
- `force_oauth.php`, `test_shopify.php`: Previously initiated OAuth or API calls without session authentication.
- `test_db.php`, `test_query.php`, `test_search.php`, `test_update.php`: Legacy database diagnostics.
- `check_orders_table.php`, `check_orders_table_2.php`, `check_orders_1108.php`: Old debugging queries.
- `copy_ui.php`, `copy_ui_2.php`, `fix.php`: Temporary copy/paste scripts.
- `scratch_fetch_order.php`, `scratch_links.json`, `payload.json`: Temporary JSON dumps.
- `add_column.php`, `add_captain_columns.php`, `migrate_coords.php`, `update_coords.php`: Manual ad-hoc ALTER table scripts (now consolidated into `database/`).

---

## 5. Security Hardening & Safeguards

1. **Web-Access Denied on Archive**:
   - The `.archived_scripts/` directory is locked with a strict Apache directive:
     ```apache
     Order allow,deny
     Deny from all
     ```
2. **CSRF Validation on Mutations**:
   - Every stock update and catalog sync verifies a cryptographically secure session token via `csrf_verify()`.
3. **Session Role Isolation**:
   - Branch managers can only view and mutate rows where `branch_id == $_SESSION['branch_id']`. Cross-branch tampering is strictly rejected at the query level.
4. **Token Encryption**:
   - Shopify Admin API tokens are encrypted at rest using AES-256-CBC via `APP_SECRET`.
