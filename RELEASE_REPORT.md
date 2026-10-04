# CoSellHive 1.0.0 — WordPress.org Release Report

Branch: `release/1.0.0-wporg-prep` (12 commits, one per phase).
Submission zip: `dist/cosell-hive.zip` (not committed) — 104 files, 120 KB, root folder `cosell-hive/`.

## 0. Owner decisions taken during prep

- **Requires at least → 6.3 (not 6.0).** `blocks/product-card/block.json` uses `apiVersion: 3`, which needs WP 6.3+. Keeping 6.0 would let WP 6.0–6.2 installs activate a plugin whose block registration requires 6.3. Header, readme, and README all say 6.3.
- **Tested up to → 7.1.** `api.wordpress.org/core/version-check/1.7/` reports latest released `7.1.2`; the Local dev site runs WP 7.1.2 and all runtime tests below ran on it.

## 1. What changed, per phase

- **Phase 1 (3e63322)** — Header rewritten (CoSellHive, Tanmay Kirtania, GPL-3.0-or-later, 1.0.0, WP 6.3, PHP 8.1); `COSELL_HIVE_VERSION`/`MIN_PHP`/`DB_VERSION` → 1.0.0/8.1/1.0.0; `composer.json` license + `php >=8.1`; `package.json` version + license; `readme.txt` stable tag/license; `LICENSE` replaced with unmodified GPL-3.0 text; README requirements/license.
- **Phase 2 (ea4a698)** — `ch_store`/`ch_affiliate` → `cs_hive_store`/`cs_hive_affiliate`; `?ch_token=` → `?cs_hive_token=`; `/go/ch/` → `/go/cs-hive/`; `Installer::migrate_legacy_prefixes()` (idempotent, runs on DB-version bump); docs/plan references updated.
- **Phase 3 (137d571)** — Named bootstrap functions (`cosell_hive_bootstrap/activate/deactivate`); missing-autoload and PHP/WP version guards with admin notice instead of fatal; removed closure hooks and the `wp_die` auto-deactivator; Woo-missing admin notice, affiliate features keep working; HPOS + checkout-blocks compatibility already declared and kept.
- **Phase 4 (75d249e)** — `HubClientFactory` defaults to `RestHubClient` (mock only under `WP_DEBUG` + `COSELL_HIVE_USE_MOCK_HUB`); new `RestLicenseClient` + `LicenseClientFactory` (stub only under `WP_DEBUG` + `COSELL_HIVE_USE_STUB_LICENSE`); onboarding license key optional, hub registered first, failures degrade to free tier; heartbeat handles `WP_Error`; payout secrets moved from AES-256-CBC to AES-256-GCM with random nonce.
- **Phase 5 (5cc55f2)** — Click IP/UA stored as HMAC-SHA256 with `wp_salt('auth')` (was bare SHA-256); `capture_token` only persists validated in-window tokens; go redirector falls through to the product page when the hub yields no token; explicit `'sslverify' => true` on all hub requests; approval approve/reject routes declare the `reason` arg; removed `console.log` from publish-panel.
- **Phase 6 (06edcf3)** — New `CoSellHive\Admin\Settings` page (CoSellHive > Settings) with the `delete_data_on_uninstall` checkbox (default off, nonce + `manage_options` via Settings API); `uninstall.php` rewritten self-contained (transients + cron always; full removal only when the flag is on; per-site loop on multisite; new + legacy roles); new `Core\Schema` single source of truth for table suffixes/cron hooks; network activation refuses install with a notice; runtime no-ops with a notice on sites without install.
- **Phase 7 (8ca8f37)** — Privacy policy text now names the `cosell_hive_token` cookie (purpose, 30-day default lifetime), the `cosell_hive_tracking_enabled` consent filter, HMAC hashing, and links `https://cosellhive.com/privacy-policy`; exporter/eraser accept `$page` and batch at 100 rows (clicks included in export; eraser also clears order `_cosell_hive_token` meta via WC CRUD); tracking gated behind `cosell_hive_tracking_enabled` in both redirector and capture.
- **Phase 8 (b20e862)** — Removed manual `load_plugin_textdomain` (deleted `Core\I18n`, translate.wordpress.org auto-loads on WP 6.3+); all React strings wrapped with `__()`/`sprintf()` (`@wordpress/i18n`, text domain literal); `wp_set_script_translations` already per handle; new `bin/extract-ts-strings.mjs` (wp-cli skips TSX) merged into `languages/cosell-hive.pot` (174 msgids, `msgfmt -c` valid); rebuilt `build/` + `assets/css/admin.css`.
- **Phase 9 (e141901)** — `readme.txt` rewritten to the brief template (Contributors `tanmjay`, 5 tags, external-services section with code-verified payload lists); README requirements/license/roles/uninstall/build updated.
- **Phase 10 (9fc67b4)** — `.distignore` per template (+ `bin/`); `/dist/` gitignored; release build (`composer install --no-dev -o`, `npm ci`, `npm run build`, `build:tailwind`); `dist/cosell-hive.zip` verified (104 files, no forbidden paths, no dev packages).
- **Phase 11 (73cac47)** — Fixed role-migration user query (`get_users(['role' => $old])`; the `fields=>['ID']` variant returns objects, so no user ever migrated — verified broken then fixed live); prefixed uninstall globals; standard ABSPATH guard in `Schema.php`. Live matrix on Local (WP 7.1.2, PHP 8.2, Woo 10.3.7): activate → tables/roles/routes OK, zero CoSellHive debug-log entries; role migration verified (`ch_store` → `cs_hive_store`, legacy role removed); uninstall setting-off keeps data + clears cron; setting-on drops tables/options/roles.
- **Phase 12 (7b52094)** — `.wordpress-org/README.txt` checklist; all icons/banners/screenshots missing (owner to supply; `plan/screenshots/*` are mockup exports, not shippable).

## 2. Verified answers

- **Requires at least:** 6.3 (see §0; `apiVersion: 3` in `blocks/product-card/block.json`).
- **Tested up to:** 7.1 (7.1.2 released per version-check API; runtime matrix ran on local WP 7.1.2).
- **Outbound request inventory** (all `sslverify: true`, timeout 15s, hosts restricted to the `cosell_hive_hub_base` filter default `https://api.cosellhive.com`):
  - `POST /v1/listings/upsert` — on product sync/status/stock change (user action-driven): product_id, title, price_minor, currency, stock, commission terms/status.
  - `POST /v1/clicks` — on go-link hit (visitor action): listing_id, affiliate_site_id. Local click row + cookie written regardless; hub failure still 302s to the product.
  - `POST /v1/events/order` — on Woo order status change: order_id, token, status, amount_minor, currency, is_cod, buyer_ref (SHA-256 of billing email, never raw).
  - `GET /v1/me/entitlements` — onboarding/admin screens.
  - `POST /v1/feed/rank`, `GET /v1/feed/search`, `POST /v1/copy/generate` — affiliate UI actions only.
  - `POST /v1/license/activate|validate` — onboarding + daily heartbeat (license key + site URL).
  - `POST /v1/sites/register` — onboarding only, only when a signup token is provided via filter. Nothing is sent on activation.
- **Trial/license behavior (plain language):** there is no trial and no expiry anywhere. The license key is optional; without one (or when the hub is unreachable) the site runs the free tier. `StubLicenseClient` always returned valid/free and is now debug-only. "Start a free trial" (`src/admin/onboarding.tsx`) is a hint pointing at the CoSellHive account site — no local trial logic exists. Nothing in the free plugin locks or expires; premium limits are hub-side entitlements. No WP.org trialware issue found.
- **Secrets/encryption:** payout details use AES-256-GCM (random 12-byte nonce, 16-byte tag), key via `hash_hkdf('sha256', AUTH_KEY.SECURE_AUTH_SALT)`; fails closed on default/empty salts; only `****1234` masks ever displayed or exported; eraser wipes `details_enc`. Hub install secret stored in options (standard for API credentials), never logged. No secrets in repo, logs, or responses.

## 3. Security audit table

| Area | Finding | Severity | Status |
|---|---|---|---|
| REST permissions | All routes have real callbacks; mutating/admin routes capability-gated; no `__return_true` | — | Pass |
| Nonces | REST cookie auth uses `wp_rest` nonce via apiFetch middleware; product save uses Woo core nonce + `edit_product` cap; settings via Settings API | — | Pass |
| Input | Superglobals unslashed + sanitized; path IDs regex-constrained; `reason` arg declared | — | Fixed |
| Click PII | Bare SHA-256(IP/UA) → HMAC-SHA256 with `wp_salt('auth')`; no proxy headers trusted | Medium | Fixed |
| Token cookie | Set only for validated in-window tokens; `HttpOnly`, `SameSite=Lax`, `Secure` on SSL; consent filter | Medium | Fixed |
| Hub-down go links | Returned empty (dead end) → now 302s to product unattributed | Medium | Fixed |
| Transport | `sslverify` now explicit on all hub calls; HMAC `timestamp.body`, 5-min skew, `hash_equals` | — | Fixed |
| SQL | All dynamic SQL via `$wpdb->prepare`; table names from `$wpdb->prefix` + internal constants (Plugin Check table-name warnings are false positives) | Low | Justified |
| Redirects | `wp_safe_redirect` to product permalink only; no user-supplied destination | — | Pass |
| Commissions | Server-side state machine; paid/reversed terminal; post-payout refund = negative carry, never clawback | — | Pass |
| Payouts | Admin-only transitions; amounts from `payable` rows; details never in responses/logs | — | Pass |
| Roles | Least privilege (`read` + one custom cap); menus `read`, decisions `manage_options`/`manage_woocommerce` | — | Pass |
| Debug leftovers | No `localhost`/staging URLs, creds, `console.log`, `eval`, or obfuscation in shipped files | — | Pass |

## 4. Tool results

- **PHPCS (`composer lint`, WPCS 3 + PHPCompatibilityWP):** clean before first commit and after every phase (`PHPCS:0`).
- **`tsc --noEmit`:** clean. **`npm run build`:** success, no material warnings.
- **Plugin Check 2.1.0 (local WP 7.1.2):** 0 errors in shipped code. 5 remaining ERRORs are dev-tree-only artifacts excluded from the zip (`dist/` zip, `.DS_Store` ×2, `plan/prd/*` ×2). 134 WARNINGs, all in justified buckets: direct `$wpdb` calls on custom tables, interpolated table names from internal constants, uninstall LIKE-cleanup, `$wpdb->options` transients cleanup, dev-only markdown/hidden files.
- **License audit:** `composer licenses --no-dev` → root package only, GPL-3.0-or-later, zero runtime deps. `license-checker --production` → only the private root package flagged (no license field parse for SPDX string; no third-party code bundled — `@wordpress/*` are WP-core externals). No images/fonts in `assets/`/`build/`/`blocks/` (CSS + `block.json` only; the single `https://` URL is the block-schema identifier, not a loaded asset). No `THIRD-PARTY-LICENSES.txt` needed.
- **License file check:** `LICENSE` is the unmodified GPL-3.0 text (35,149 bytes).

## 5. Open questions / TODO(owner)

- Supply `.wordpress-org/` icons, banners, and real screenshots (all missing).
- Confirm the `api.cosellhive.com` ↔ `hub.cosellhive.com` host split matches the deployed Hub before submission (code defaults to `api.`, filterable).
- `TODO(owner)`: none in shipped files. UNVERIFIED: multisite network-activation path (no multisite locally); PHP 8.1/8.4-8.5 runtimes (matrix ran on PHP 8.2 CLI); WP 6.3 floor (matrix ran on 7.1.2 only).

## 6. Known risks for WP.org review (ranked)

1. **Mock/stub files ship in the zip** (`MockHubClient.php`, `StubLicenseClient.php`) — reachable only via `WP_DEBUG` + explicit constants; production adapters are default. Reviewers sometimes flag dead code paths; the report documents the gating.
2. **Direct `$wpdb` usage + interpolated table names** — inherent to custom tables; values are internal constants, all values prepared. Expect warnings, not errors.
3. **`Requires at least: 6.3`** — correct per `apiVersion: 3`, but narrows the install base vs the original 6.0 ask.
4. **No real screenshots yet** — readme correctly omits the section, but listings without screenshots convert poorly.
5. **Signup-token distribution** — hub registration needs an out-of-band token via the `cosell_hive_hub_signup_token` filter; without it the plugin runs free-tier local defaults. Document for reviewers if questioned.

## 7. Zip listing (`unzip -l dist/cosell-hive.zip`)

104 files, 315,487 bytes uncompressed. Root folder `cosell-hive/` containing: `cosell-hive.php`, `readme.txt`, `README.md`, `LICENSE`, `composer.json`, `uninstall.php`, `includes/`, `modules/`, `blocks/`, `build/`, `assets/`, `languages/`, `vendor/` (autoloader only — `autoload.php` + `composer/*`, no third-party or dev packages). Excluded: `.git*`, `node_modules`, `tests`, `plan`, `docs`, `IMPLEMENTATION_PLAN.md`, `RELEASE_REPORT.md`, `COSELLHIVE_WPORG_RELEASE_PREP.md`, `composer.lock`, `package*.json`, `phpcs.xml`, `tsconfig.json`, `webpack/tailwind` configs, `src`, `bin`, `*.map`, `.wordpress-org`, `dist`.
Archive:  dist/cosell-hive.zip
  Length      Date    Time    Name
---------  ---------- -----   ----
        0  10-04-2026 13:59   cosell-hive/
        0  10-02-2026 16:02   cosell-hive/blocks/
        0  10-02-2026 16:02   cosell-hive/blocks/product-card/
      489  10-02-2026 16:02   cosell-hive/blocks/product-card/block.json
    35149  10-04-2026 05:02   cosell-hive/LICENSE
        0  10-02-2026 16:24   cosell-hive/includes/
      715  10-02-2026 16:11   cosell-hive/includes/functions-helpers.php
        0  10-02-2026 16:24   cosell-hive/includes/Repository/
     7330  10-04-2026 13:45   cosell-hive/includes/Repository/CommissionRepository.php
     7167  10-04-2026 13:45   cosell-hive/includes/Repository/ListingRepository.php
     3670  10-04-2026 13:45   cosell-hive/includes/Repository/ClickRepository.php
     4573  10-04-2026 13:45   cosell-hive/includes/Repository/PayoutRepository.php
        0  10-02-2026 16:11   cosell-hive/includes/Commission/
     7623  10-02-2026 16:11   cosell-hive/includes/Commission/CommissionService.php
        0  10-04-2026 13:49   cosell-hive/includes/Core/
     4515  10-04-2026 13:49   cosell-hive/includes/Core/Plugin.php
     9479  10-04-2026 13:48   cosell-hive/includes/Core/Privacy.php
     1184  10-04-2026 14:03   cosell-hive/includes/Core/Schema.php
     3140  10-04-2026 14:06   cosell-hive/includes/Core/Installer.php
     1919  10-02-2026 15:39   cosell-hive/includes/Core/Assets.php
        0  10-04-2026 05:13   cosell-hive/includes/License/
      904  10-01-2026 19:43   cosell-hive/includes/License/StubLicenseClient.php
      625  10-01-2026 19:43   cosell-hive/includes/License/LicenseClientInterface.php
     3588  10-04-2026 13:44   cosell-hive/includes/License/RestLicenseClient.php
     2275  10-04-2026 05:14   cosell-hive/includes/License/Heartbeat.php
      801  10-04-2026 05:13   cosell-hive/includes/License/LicenseClientFactory.php
        0  10-02-2026 16:24   cosell-hive/includes/Security/
     3134  10-04-2026 05:15   cosell-hive/includes/Security/Crypto.php
        0  10-02-2026 16:01   cosell-hive/includes/Marketplace/
     2543  10-02-2026 16:01   cosell-hive/includes/Marketplace/ListingPresenter.php
        0  10-04-2026 13:45   cosell-hive/includes/Admin/
     2917  10-04-2026 13:45   cosell-hive/includes/Admin/Settings.php
     6498  10-04-2026 13:44   cosell-hive/includes/Admin/ApprovalQueueController.php
     1551  10-01-2026 19:43   cosell-hive/includes/Admin/Menu.php
        0  10-02-2026 16:02   cosell-hive/includes/Shortcodes/
     5712  10-02-2026 16:07   cosell-hive/includes/Shortcodes/ProductCard.php
        0  10-02-2026 16:24   cosell-hive/includes/Wallet/
     2893  10-02-2026 16:24   cosell-hive/includes/Wallet/WalletService.php
     5849  10-02-2026 16:24   cosell-hive/includes/Wallet/PayoutService.php
        0  10-02-2026 16:11   cosell-hive/includes/Tracking/
     2695  10-02-2026 16:11   cosell-hive/includes/Tracking/Reconciler.php
     4558  10-04-2026 13:48   cosell-hive/includes/Tracking/Redirector.php
     5939  10-04-2026 13:48   cosell-hive/includes/Tracking/OrderReporter.php
        0  10-02-2026 17:26   cosell-hive/includes/Hub/
     8550  10-04-2026 13:44   cosell-hive/includes/Hub/RestHubClient.php
     1078  10-04-2026 05:12   cosell-hive/includes/Hub/HubClientFactory.php
     2425  10-03-2026 07:37   cosell-hive/includes/Hub/MockHubClient.php
     1551  10-03-2026 07:37   cosell-hive/includes/Hub/HubClientInterface.php
     2027  10-02-2026 16:59   cosell-hive/includes/Hub/Signature.php
        0  10-02-2026 16:30   cosell-hive/languages/
    16738  10-04-2026 13:55   cosell-hive/languages/cosell-hive.pot
     4294  10-04-2026 13:56   cosell-hive/README.md
     4192  10-04-2026 14:03   cosell-hive/uninstall.php
     3651  10-04-2026 13:46   cosell-hive/cosell-hive.php
     3722  10-04-2026 13:56   cosell-hive/readme.txt
        0  10-04-2026 13:58   cosell-hive/build/
      132  10-04-2026 13:58   cosell-hive/build/cosell-hive-wallet.asset.php
     1551  10-04-2026 13:58   cosell-hive/build/cosell-hive-product-card.js
     3549  10-04-2026 13:58   cosell-hive/build/cosell-hive-setup.js
     6644  10-04-2026 13:58   cosell-hive/build/cosell-hive-wallet.js
     4431  10-04-2026 13:58   cosell-hive/build/cosell-hive-feed.js
      132  10-04-2026 13:58   cosell-hive/build/cosell-hive-dashboard.asset.php
     2756  10-04-2026 13:58   cosell-hive/build/cosell-hive-dashboard.js
      132  10-04-2026 13:58   cosell-hive/build/cosell-hive-links.asset.php
      134  10-04-2026 13:58   cosell-hive/build/cosell-hive-product-card.asset.php
      132  10-04-2026 13:58   cosell-hive/build/cosell-hive-review.asset.php
      132  10-04-2026 13:58   cosell-hive/build/cosell-hive-setup.asset.php
     4058  10-04-2026 13:58   cosell-hive/build/cosell-hive-review.js
     6001  10-04-2026 13:58   cosell-hive/build/cosell-hive-links.js
      132  10-04-2026 13:58   cosell-hive/build/cosell-hive-feed.asset.php
        0  10-02-2026 16:01   cosell-hive/modules/
        0  10-03-2026 07:38   cosell-hive/modules/Affiliate/
     2640  10-02-2026 16:03   cosell-hive/modules/Affiliate/LinksController.php
     3536  10-02-2026 16:01   cosell-hive/modules/Affiliate/FeedController.php
     2497  10-03-2026 07:38   cosell-hive/modules/Affiliate/CopyController.php
     1116  10-03-2026 07:38   cosell-hive/modules/Affiliate/Module.php
     5015  10-04-2026 13:44   cosell-hive/modules/Affiliate/OnboardingController.php
     4962  10-03-2026 07:37   cosell-hive/modules/Affiliate/DiscoveryController.php
     6781  10-02-2026 16:25   cosell-hive/modules/Affiliate/WalletController.php
        0  10-02-2026 15:39   cosell-hive/modules/Store/
     7208  10-02-2026 15:38   cosell-hive/modules/Store/ProductMeta.php
     4182  10-02-2026 16:12   cosell-hive/modules/Store/DashboardController.php
     2640  10-04-2026 05:10   cosell-hive/modules/Store/Module.php
     3576  10-02-2026 15:55   cosell-hive/modules/Store/SyncService.php
        0  10-02-2026 15:39   cosell-hive/assets/
        0  10-02-2026 16:02   cosell-hive/assets/css/
     3271  10-04-2026 13:58   cosell-hive/assets/css/admin.css
      977  10-02-2026 16:02   cosell-hive/assets/css/frontend.css
      871  10-04-2026 04:48   cosell-hive/composer.json
        0  10-04-2026 14:07   cosell-hive/vendor/
      771  10-01-2026 19:45   cosell-hive/vendor/autoload.php
        0  10-04-2026 13:59   cosell-hive/vendor/composer/
      139  10-01-2026 19:45   cosell-hive/vendor/composer/autoload_namespaces.php
     1070  10-01-2026 19:45   cosell-hive/vendor/composer/LICENSE
    16378  10-01-2026 19:45   cosell-hive/vendor/composer/ClassLoader.php
      247  10-04-2026 14:07   cosell-hive/vendor/composer/autoload_psr4.php
     4246  10-04-2026 14:07   cosell-hive/vendor/composer/autoload_classmap.php
      925  10-04-2026 13:57   cosell-hive/vendor/composer/platform_check.php
     5974  10-04-2026 14:07   cosell-hive/vendor/composer/autoload_static.php
     1672  10-01-2026 19:45   cosell-hive/vendor/composer/autoload_real.php
       70  10-04-2026 14:07   cosell-hive/vendor/composer/installed.json
      222  10-01-2026 19:45   cosell-hive/vendor/composer/autoload_files.php
    16143  10-01-2026 19:45   cosell-hive/vendor/composer/InstalledVersions.php
      779  10-04-2026 14:07   cosell-hive/vendor/composer/installed.php
---------                     -------
   315487                     104 files
