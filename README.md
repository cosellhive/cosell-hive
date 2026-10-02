# CoSellHive

Turn any WooCommerce store into a node in a shared affiliate marketplace. Store owners publish products with a commission offer; affiliates promote them with tracked links and earn from resulting sales — without touching fulfillment, payment, or inventory.

Positioning: ShareASale / Refersion marketplace, native to WordPress — install a plugin instead of signing up for a SaaS dashboard.

> Full PRD: [`plan/prd/CoSellHive — PRD.md`](plan/prd/CoSellHive%20—%20PRD.md) · Mockups: [`plan/mockup-html/`](plan/mockup-html/) · Build plan: [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md) · Feature docs: [`docs/features/`](docs/features/)

## Architecture

SaaS-minimal free connector. The plugin is a **thin reporter** — the central hub owns the commission ledger, catalog, ranking, and fraud models. Premium tiers are enforced API-side via entitlements, never via PHP checks that could be stripped (wp.org code is GPL by definition).

- `cosell-hive.php` — thin bootstrap (wp-erp pattern: final singleton, service container)
- `includes/` — PSR-4 `CoSellHive\`: Core, Hub (interface + mock adapter), License (interface + stub), Tracking, Commission, Admin, Api
- `modules/` — PSR-4 `CoSellHive\Modules\`: `Store`, `Affiliate` (feature modules)
- `src/` — React + TypeScript + Tailwind (`@wordpress/scripts`, scoped to `#cosell-hive-root`); `build/` is the compiled output
- `plan/` — PRD, HTML mockups, screenshots (reference only, not shipped)
- `docs/features/` — one doc per shipped feature: what it does + how to use it

Design patterns: Singleton (bootstrap), Factory + Strategy (hub client, commission calculators), Repository (`$wpdb`), Observer (Woo hooks → sync/report), State Machine (commission lifecycle), Adapter (license/hub providers).

## Requirements

- WordPress 6.2+, PHP 7.4+, WooCommerce (store-side features only; affiliates work without it)
- Composer 2, Node 18+

## Local development

```bash
# PHP deps + code standards
composer install
composer lint        # PHPCS (WPCS: Core + Extra + Docs)

# Frontend — note --include=dev (required if your npm config sets omit=dev)
npm install --include=dev
npm run build        # wp-scripts → build/
npm run build:tailwind
npx tsc --noEmit     # typecheck
```

`composer lint:fix` auto-fixes what PHPCS can. Keep every new string wrapped (`__()` / `esc_*()`), every input sanitized, every form nonce-checked, every query prepared — wp.org review depends on it.

## Docs

- [`IMPLEMENTATION_PLAN.md`](IMPLEMENTATION_PLAN.md) — phased plan with checkboxes, updated as work lands
- [`docs/features/`](docs/features/) — per-feature usage docs (added when each feature ships)
- [`readme.txt`](readme.txt) — wp.org listing copy (separate format, keep in sync on release)

## License

GPL-2.0-or-later (see plugin headers and `readme.txt`).
