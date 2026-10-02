# CoSellHive — Implementation Plan

> Source: `plan/prd/CoSellHive — PRD.md` + 7 mockups in `plan/mockup-html/`.
> Model: SaaS-minimal free connector on wp.org. Hub owns ledger/catalog/ranking. Tiers enforced API-side via entitlements.
> Stack: PHP 7.4+ OOP (wp-erp-style singleton + container), React + TypeScript + Tailwind via `@wordpress/scripts`, PHPCS/WPCS, Composer PSR-4.
> Conventions: prefix `cosell_hive_` / `COSELL_HIVE_`, textdomain `cosell-hive`, slug `cosell-hive`, tables `wp_cosell_*`.

Progress legend: `[ ]` todo, `[x]` done. Update this file as tasks complete.

## Track B — SaaS hub (separate private repo)

> Lives at `CoSellHive/cosell-hive-hub` (proprietary, NestJS API + BullMQ workers + Postgres + Next.js console). Full plan: hub repo `HUB_PLAN.md`. Entry gate: **H0 contract freeze** (`packages/contracts/openapi.yaml`) — plugin `MockHubClient` conforms to it, then one adapter swap connects staging. Plugin V1 loop finishes against the mock in parallel.

---

## Phase 0 — Skeleton (approved scope, build now)

> Status: DONE 2026-10-02 — `tsc` clean, `npm run build` emits 6 entries, `phpcs` clean, `php -l` clean, stubbed-WP smoke harness `SMOKE OK`. Live activation pending Local site DB.

- [x] 0.1 Bootstrap + packaging
  - [x] 0.1.1 `cosell-hive.php` — header, ABSPATH guard, GPL header, `final class CoSellHive`, `::init()`, `cosell_hive()` accessor, constants
  - [x] 0.1.2 `uninstall.php` + `readme.txt` (wp.org format) + `languages/cosell-hive.pot` placeholder
  - [x] 0.1.3 `composer.json` (PSR-4 `CoSellHive\` → `includes/`, scripts: `lint`, `lint:fix`) + `phpcs.xml` (WordPress-Core/Extra/Docs)
  - [x] 0.1.4 `.gitignore` (vendor, node_modules, build maps) — no secrets in repo
- [x] 0.2 Core PHP shell (no business logic)
  - [x] 0.2.1 `includes/Core/Plugin.php` — setup/install/includes/instantiate/actions/load_modules, `__get/__isset` container
  - [x] 0.2.2 `includes/Core/Installer.php` — activate: roles `ch_store`/`ch_affiliate`, caps, DB version option, cron schedule; deactivate: clear cron
  - [x] 0.2.3 `includes/Core/I18n.php` — `load_plugin_textdomain`
  - [x] 0.2.4 `includes/Hub/HubClientInterface.php` + `MockHubClient.php` + `HubClientFactory.php`
  - [x] 0.2.5 `includes/License/LicenseClientInterface.php` + `StubLicenseClient.php` (always valid locally, heartbeat no-op)
  - [x] 0.2.6 `includes/Admin/Menu.php` — placeholder menus (Store, Affiliate, Approval, Wallet, Onboarding) with capability checks
  - [x] 0.2.7 `includes/functions-helpers.php` — `cosell_hive()`, version/asset helpers, all prefixed + escaped
- [x] 0.3 Frontend build (empty mount points, build must pass)
  - [x] 0.3.1 `package.json` (`@wordpress/scripts`, `typescript`, `tailwindcss`), `tsconfig.json`, `tailwind.config.js` (scoped prefix), `src/styles/tailwind.css`
  - [x] 0.3.2 Entries: `src/admin/onboarding.tsx`, `store-dashboard.tsx`, `approval-queue.tsx`, `src/affiliate/feed.tsx`, `wallet.tsx`, `links.tsx`, `src/store/publish-panel.tsx`, `src/lib/api.ts` (apiFetch + nonce)
  - [x] 0.3.3 Enqueue wiring in `includes/Core/Assets.php` — one handle per screen, `wp_set_script_translations`, no CDN scripts
- [x] 0.4 Verification gate
  - [x] 0.4.1 `composer lint` (PHPCS) clean
  - [x] 0.4.2 `npm run build` clean, no `build/` committed without `src/`
  - [ ] 0.4.3 Activates clean on fresh WP + Woo, no PHP notices, uninstall removes options/roles

## Phase 1 — Store module

> Status: DONE 2026-10-02 — native Woo product tab (HPOS-declared), SyncService + auto-delist, dashboard REST + React table, Tailwind CSS per-screen. `phpcs`/`tsc`/`build`/smoke green. Feature doc: `docs/features/01-store-module.md`. Deviation: product fields are native Woo inputs, not a React mount (fewer failure modes, same mockup-01 data).

- [x] 1.1 Product meta (`_cosell_hive_*`: enabled, type flat|percent, value, cap) on Woo product panel (React mount in classic + HPOS-compatible hooks)
- [x] 1.2 `modules/Store/{Module,ProductMeta,SyncService}.php` — `woocommerce_product_object_updated` + stock hook → `HubClient::upsertListing()` (mock), reconcile-poll stub via Action Scheduler
- [x] 1.3 Store dashboard REST `GET /cosell-hive/v1/dashboard` + React table (mockup 02)
- [x] 1.4 Auto-delist rules (out-of-stock, unpublish, lapsed tier) — status → `paused`

## Phase 2 — Admin approval queue

> Status: DONE 2026-10-02 — `wp_cosell_listings` + repository, approve/reject REST + hub notify, React queue with static high-commission flag. `phpcs`/`tsc`/`build`/smoke green. Feature doc: `docs/features/02-approval-queue.md`.

- [x] 2.1 `wp_cosell_listings` table + `ListingRepository` (`$wpdb->prepare` throughout)
- [x] 2.2 `ApprovalQueueController` + `GET/POST /cosell-hive/v1/listings/(approve|reject)` (nonce + `manage_options`)
- [x] 2.3 React queue UI (mockup 05) incl. anomaly-warning banner (static threshold in V1)

## Phase 3 — Affiliate feed + links + embed

> Status: DONE 2026-10-02 — feed REST + React cards, link minting + embed generator, shortcode + dynamic block sharing one server renderer, click-time-token go redirector. `phpcs`/`tsc`/`build`/smoke green. Feature doc: `docs/features/03-affiliate-feed-links.md`.

- [x] 3.1 Feed `GET /cosell-hive/v1/listings` (search, category, min-commission) + React cards (mockup 03)
- [x] 3.2 Tracked-link generator + copy button (mockup 06) via `HubClient::createToken()`
- [x] 3.3 Shortcode `[cosell_product id="" style="card"]` + Gutenberg `product-card` block (`block.json`, `render_callback`, server-rendered)

## Phase 4 — Tracking + commission engine

> Status: DONE 2026-10-02 — clicks + commissions tables, token capture chain (session → cookie, window-validated), full state machine incl. COD rules + post-payout negative carry, daily cron (advance/expire/backfill), HMAC seam, live dashboard tiles. `phpcs`/smoke green. Feature doc: `docs/features/04-tracking-commissions.md`.

- [x] 4.1 `wp_cosell_clicks`, `wp_cosell_commissions` tables + repositories
- [x] 4.2 `/go/ch/<token>` redirector: hub `trackClick` → 302 to store URL + `?ch_token=`; cookie fallback, 30-day window, last-click
- [x] 4.3 `OrderReporter`: `woocommerce_checkout_create_order` writes `_cosell_hive_token`; `woocommerce_order_status_changed` → signed `POST` (HMAC + timestamp, 5-min replay window)
- [x] 4.4 `CommissionService` state machine (`pending→confirmed→holding→payable→paid`, `→reversed`); COD holds until `completed`; refund → `reversed`, post-payout → negative forward balance
- [x] 4.5 `Reconciler` daily cron + escalation ladder (notice → auto-pause → suspend), tolerance for lag

## Phase 5 — Wallet + payouts + onboarding

- [ ] 5.1 `wp_cosell_payouts` + `WalletService`/`PayoutService` (balances from `payable` only; PII encrypted)
- [ ] 5.2 Manual payout flow: request → admin approve → mark paid (mockup 04)
- [ ] 5.3 Onboarding wizard (mockup 07): license key → domain-lock stub → role select → entitlement fetch; heartbeat daily cron, graceful degradation

## Phase 6 — wp.org hardening

- [ ] 6.1 Full PHPCS + `Plugin Check` green, HPOS + multisite notes, `WP_DEBUG` clean
- [ ] 6.2 `wp-env` matrix (PHP 7.4/8.x, WP latest-1), uninstall verification, privacy/pot docs
- [ ] 6.3 Pilot freeze: 10–20 stores, manual curation default-on

## Later (out of scope now)

- V2: self-serve open marketplace, search/filters, Stripe/PayPal rails, platform-fee line item live, AI fit-feed (6.1) + copy gen (6.2)
- V3: fraud scoring (6.4) → holding extension, advisor (6.3), NL search (6.5), analytics
- V4: storefront generator (6.6), multi-currency, public API/webhooks, non-WP widget, reputation

## Open decisions (stubbed, not blocking)

- Hub OpenAPI contract freeze (mock adapter covers V1)
- License provider: Freemius vs EDD SL vs custom (interface stubbed)
- Payout regions for V1 pilot (default: manual bank/bKash/Nagad + PayPal)
