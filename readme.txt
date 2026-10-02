=== CoSellHive ===
Contributors: cosellhive
Tags: woocommerce, affiliate, marketplace, commission, payouts
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn any WooCommerce store into a node in a shared affiliate marketplace.

== Description ==

CoSellHive lets store owners publish WooCommerce products with a commission offer into a curated marketplace, and lets affiliates pull those products onto their own sites with tracked links. Checkout stays on the store's own site — the affiliate never touches fulfillment, and the store never manages one-off relationships.

V1 (this plugin) is a free SaaS-minimal connector:

* Store: mark products available for affiliation (flat or percent commission), publish to marketplace for review.
* Admin: approve or reject listings.
* Affiliate: browse curated listings, generate tracked links and product embeds.
* Tracking: server-issued click tokens written to order meta, commission state machine (pending, confirmed, holding, payable, paid, reversed).
* Wallet: manual payout requests approved by admin.
* Onboarding: license activation plus Store or Affiliate role selection.

Premium tiers (listing limits, automated payouts, AI matching and copy) are enforced server-side via hub entitlements — the plugin degrades gracefully when offline and never blocks checkout.

Requires WooCommerce for store-side features. Affiliate browsing works without WooCommerce.

== Installation ==

1. Upload `cosell-hive` to `/wp-content/plugins/` or install from Plugins > Add New.
2. Activate the plugin.
3. Open CoSellHive > Onboarding, enter your license key (or start a free trial) and choose Store or Affiliate.
4. Stores: edit a WooCommerce product, enable CoSellHive, set commission, publish to marketplace.

== Frequently Asked Questions ==

= Do I need WooCommerce? =
Only for publishing products as a store. Affiliates can browse and generate links without it.

= Where does checkout happen? =
Always on the store owner's own site. The plugin only tracks the referral token.

= How are commissions paid in V1? =
Manual payout requests approved by the site admin. Automated rails arrive in V2.

== Screenshots ==

1. Store publish panel with commission type and cap.
2. Store dashboard with listings, affiliates, GMV.
3. Affiliate marketplace feed.
4. Affiliate wallet and payout history.
5. Admin approval queue.
6. Tracked links and embed generator.
7. Onboarding with license activation and role selection.

== Changelog ==

= 0.1.0 =
* Initial Phase 0 skeleton: bootstrap, roles, hub/license stubs, React mount points.
