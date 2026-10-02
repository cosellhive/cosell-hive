# 01 — Store Module: publish products to the marketplace

Maps to mockups **01 (publish product)** and **02 (store dashboard)**, PRD §5.1.

## What it does

Lets a store owner mark any WooCommerce product *available for affiliation* with a commission offer (flat or percent + optional cap). Saving the product pushes its state to the hub catalog via `HubClient::upsert_listing()` and records a local status (`draft` / `pending` / `paused`). The store dashboard lists every opted-in product with its commission, affiliate count, sales, and status.

New listings land in `pending` — they go `live` only through the Phase 2 admin approval queue.

## How to use it

1. Open **Products → edit a product → CoSellHive tab**.
2. Check **Available for affiliation**, pick **Percentage** or **Flat amount**, enter the **Commission rate** (percent: 0–100) and an optional **Cap per order**.
3. **Update** the product. The panel shows the resulting **Marketplace status** (`pending` review, or `paused` if unpublished/out of stock) and **Marketplace ID** once the hub acknowledges.
4. Open **CoSellHive** (top-level menu) for the dashboard: stat tiles (active listings, affiliates, GMV, commission paid) plus the listings table.

Invalid input (e.g. 150%) keeps the previous value and shows an admin error notice — the bad value is never saved or reported.

## Auto-delist rules (no manual step)

A listing drops to `paused` automatically when the product is unpublished (draft/pending/trash) or out of stock. Restocking or re-publishing re-syncs it to `pending` (still needs approval — it never self-approves to `live`). Unchecking *Available for affiliation* collapses the listing to `draft` and skips hub reporting.

## Reference

**Post meta** (all prefixed `_cosell_hive_`):

| Key | Meaning |
| --- | --- |
| `_cosell_hive_enabled` | `yes` = opted in |
| `_cosell_hive_commission_type` | `flat` \| `percent` |
| `_cosell_hive_commission_value` | raw rate |
| `_cosell_hive_commission_cap` | per-order cap, empty = none |
| `_cosell_hive_status` | `draft` \| `pending` \| `paused` (+ `live`/`rejected` from Phase 2) — system-managed, read-only in UI |
| `_cosell_hive_hub_id` | hub catalog ID, read-only in UI |

**Hooks observed:** `woocommerce_product_data_tabs`, `woocommerce_product_data_panels`, `woocommerce_process_product_meta` (nonce: Woo's `woocommerce_save_data` + `edit_product` cap), `woocommerce_product_set_stock`, `woocommerce_product_status_changed`, `before_woocommerce_init` (HPOS `custom_order_tables` + `cart_checkout_blocks` compatibility).

**REST:** `GET /cosell-hive/v1/dashboard` — gated by `cosell_hive_store` / `manage_woocommerce` / `manage_options`. Returns `{ stats, listings[] }`. `affiliates`, `sales_30d`, GMV and payout stats are `0` stubs until the Phase 4 commission ledger exists; the response shape is final.

**Files:** `modules/Store/{Module,ProductMeta,SyncService,DashboardController}.php`, `src/admin/store-dashboard.tsx`, `includes/Core/Assets.php` (Tailwind `assets/css/admin.css` per-screen).

**Verify:** `composer lint` · `npx tsc --noEmit` · `npm run build` · enable the tab on a variable/simple product, toggle stock to 0 and back, watch status flip `pending` ↔ `paused`.
