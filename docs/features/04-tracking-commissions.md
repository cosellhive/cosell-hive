# 04 — Tracking, Commission Engine & Reconciler

Maps to PRD §5.2 (commission state machine), §5.3 (tracking & attribution), §10 (enforcement reporting).

## What it does

Closes the money loop on the store side. Affiliate clicks are logged locally with hashed identifiers; checkout captures the attribution token (URL param → cookie fallback, last-click wins, 30-day window); each attributed order creates a commission row that walks the full state machine; a daily cron advances holdings, expires stale COD, and self-heals rows the live hooks missed. Every order event is also reported to the hub — the plugin never settles, only reports.

## How it flows

1. Visitor clicks an affiliate link → `/go/ch/<ref>/` mints a hub token, stores a click row (`token, product, affiliate, ip/ua hashes`), sets a 30-day `HttpOnly + SameSite=Lax` cookie, and 302s to the product page with `?ch_token=`.
2. At checkout the token is captured into the Woo session (cookie as fallback) and written to order meta `_cosell_hive_token` — but only if the token exists in the click ledger and is inside the attribution window.
3. On creation the order gets a `pending` commission row. On `processing`/`on-hold`/`completed` it moves to `holding` with `holding_until = now + holding_days` (default 7, filter `cosell_hive_setting_holding_days`). **COD stays `pending` until `completed`.**
4. The daily `cosell_hive_daily_maintenance` cron moves elapsed holdings to `payable` and reverses COD pendings older than `cod_expiry_days` (default 30).
5. `cancelled`/`refunded`/`failed` reverses any pre-`paid` commission. Refund **after** payout marks `reversed` with a negative `adjustment_minor` carried forward — never a clawback demand (consumed by the Phase 5 wallet).

The cross-store mismatch ladder (notice → pause → suspend) is hub-side (Track B reconciler); the local worker fires `cosell_hive_reconcile_run` with pass counts for the hub adapter to observe.

## Money math (V1, hub owns this at scale)

Base = attributed product's **line subtotal** (variation-aware). Flat = once per order. Percent = base × rate. Capped per order when a cap is set. All stored as **minor-unit integers** (`amount_minor`, `order_total_minor`), matching the hub contract. Assumes 2dp currencies.

## Reference

**Tables:** `wp_cosell_clicks` (`token` unique, `product_id`, `affiliate_id`, hashes, no raw IPs) · `wp_cosell_commissions` (one row per order — `order_id` unique; `status`, `holding_until`, `hub_id`, `note`, `adjustment_minor`; indexes on `(affiliate_id, status)` and `(status, holding_until)`).

**Statuses:** `pending → confirmed → holding → payable → paid`, `→ reversed` from any pre-paid state.

**Hooks observed:** `wp` (token capture), `woocommerce_checkout_create_order` (meta write + record), `woocommerce_order_status_changed` (hub report + transition). Settings via `cosell_hive_get_setting()` (`holding_days`, `cod_expiry_days`, `attribution_days`) — filterable now, settings UI later.

**Transport seam:** `Hub\Signature` implements the PRD §10.2 scheme (`X-Signature: HMAC(timestamp.body)`, 5-min replay window) for the future REST hub client; the mock ignores signatures. Order reports already include `timestamp`.

**Files:** `includes/Repository/{ClickRepository,CommissionRepository}.php`, `includes/Commission/CommissionService.php`, `includes/Tracking/{OrderReporter,Reconciler}.php`, `includes/Hub/Signature.php`, `includes/functions-helpers.php`, `modules/Store/{Module,DashboardController}.php` (live tiles), `includes/Tracking/Redirector.php` (click persistence + cookie), `includes/Core/Installer.php` (tables + cron schedule).

**Verify:** `composer lint` · smoke (`SMOKE OK` covers signature roundtrip + cron passes) · end-to-end on a live site: click a go-link, check cookie + click row, place a test order, confirm token meta + `pending` row, move to `processing` → `holding`, refund → `reversed`.
