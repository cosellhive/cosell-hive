# 06 — Staging Adapter (hub swap)

Maps to hub H2-04. The plugin now speaks signed HTTP to staging/production with no feature code touched.

## What it does

`RestHubClient` implements the same `HubClientInterface` the mock does: catalog upserts (decimal → minor-unit conversion), click tracking, order reports, and entitlement fetches — all HMAC-signed with the site secret, identified by `X-Site-Id`, 15s timeouts, fail-closed responses. `HubClientFactory` selects REST automatically once onboarding has stored credentials; the `cosell_hive_hub_client_class` filter still overrides.

## How to use it

1. Provide the hub base + signup token (both filters, staging values in `wp-config.php`, never in the DB):
   `add_filter( 'cosell_hive_hub_base', fn() => 'https://api.staging.example' );`
   `add_filter( 'cosell_hive_hub_signup_token', fn() => '...' );`
2. Run onboarding activation once. Registration is best-effort — the response carries a `hub` flag (also shown in the UI as "hub" vs "local mock").
3. From the next request, all hub traffic is live. Percent `18` converts to minor `1800`, flat `3.50` to `350`, matching hub math.

Unreachable hub degrades to mock shapes (entitlements, empty tokens) rather than fatal errors — the store keeps selling.

## Reference

**Files:** `includes/Hub/RestHubClient.php`, `includes/Hub/HubClientFactory.php`, `modules/Affiliate/OnboardingController.php`, `src/admin/onboarding.tsx`, `uninstall.php`.

**Verify:** `composer lint` · smoke · point at local hub, register, publish, confirm the hub row.
