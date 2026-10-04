=== CoSellHive ===
Contributors: tanmjay
Tags: woocommerce, affiliate, marketplace, commission, referral
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Publish WooCommerce products with a commission offer to a shared affiliate marketplace, or promote other stores' products with tracked links.

== Description ==

CoSellHive connects your site to the CoSellHive affiliate marketplace.

**For store owners (requires WooCommerce)**
* Mark products as available for affiliation with a flat or percentage commission.
* Publish products to the marketplace for review.
* Checkout always stays on your own site.

**For affiliates (WooCommerce not required)**
* Browse curated marketplace listings.
* Generate tracked links and product embeds.
* Request payouts from your wallet.

**For site admins**
* Approve or reject marketplace listings.
* Review commissions and approve manual payout requests.

CoSellHive is a connector: the CoSellHive Hub (a hosted service) keeps the shared catalog, commission records and fraud checks. See "External services" below.

== External services ==

This plugin connects to the CoSellHive Hub, a hosted service operated by CoSellHive, at `hub.cosellhive.com` and `api.cosellhive.com`. The service is required for the marketplace features (listings, commissions, payouts, license activation).

* Terms of Service: https://cosellhive.com/tos
* Privacy Policy: https://cosellhive.com/privacy-policy

Data is sent only after the site owner completes onboarding, and only for the features they use:

* **License activation / onboarding:** license key (optional), site URL, and site role (store or affiliate).
* **Publishing a product (store role):** product title, price, currency, stock status, and commission terms.
* **Referral and order events:** click token, referring product, order ID, order total, commission status.
* **Affiliate and payout actions:** affiliate account identifier and payout requests. Payout details are stored encrypted on your site.

No data is sent on plugin activation.

== Installation ==

1. Upload the `cosell-hive` folder to `/wp-content/plugins/`, or install from Plugins > Add New.
2. Activate the plugin on each site (multisite: per-site activation only).
3. Open CoSellHive > Onboarding, connect your account, and choose Store or Affiliate.
4. Stores: edit a WooCommerce product, enable CoSellHive, set a commission and publish.

== Frequently Asked Questions ==

= Do I need WooCommerce? =
Only to publish products as a store. Affiliates can browse and generate links without it.

= Where does checkout happen? =
Always on the store owner's own site. The plugin only records the referral token.

= How are commissions paid? =
Through manual payout requests approved by the site admin.

= What data does the plugin store? =
Click tokens with hashed (never raw) IP and user-agent data, commission records per attributed order, and encrypted payout details. Use Tools > Export Personal Data / Erase Personal Data to export or anonymize affiliate data.

= What happens if the Hub is unreachable? =
The plugin degrades gracefully and never blocks checkout.

= Does it work on multisite? =
Activate per site. Network-wide activation is not supported.

== Source code ==

The compiled files in `build/` are generated from the human-readable source in `src/` (TypeScript, React, Tailwind CSS). Full source and build instructions: https://github.com/cosellhive/cosell-hive
Build: `npm install --include=dev && npm run build && npm run build:tailwind`.

== Changelog ==

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 1.0.0 =
First public release.
