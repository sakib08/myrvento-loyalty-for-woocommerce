=== Myrvento Loyalty for WooCommerce ===
Contributors: sakibbd08
Tags: woocommerce, loyalty, points, referrals, analytics
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later

WooCommerce loyalty, sales, operations, analytics, revenue intelligence, and an on-store AI commerce brain.

== Description ==

Myrvento Loyalty for WooCommerce awards purchase, review, signup, birthday, social, campaign, and referral points to the same customer wallet. Referral bonuses never use a separate balance.

Analytics covers revenue trends, AOV, LTV, repeat purchase, cohorts, retention, churn, product profitability, campaign ROI, email performance, funnel conversion, and attribution.

The Myrvento Brain scores churn, next purchase, and high-value customers, suggests prices and discounts, and forecasts seasonal demand plus inventory — from WooCommerce orders, with an optional LLM only for narration.

== Installation ==

1. Install and activate WooCommerce.
2. Upload Myrvento Loyalty for WooCommerce through Plugins → Add New → Upload Plugin, or copy the plugin folder into `wp-content/plugins/`.
3. Activate Myrvento Loyalty for WooCommerce. WooCommerce must already be active.
4. Open Myrvento Loyalty for WooCommerce in wp-admin. The dashboard is the home screen. Points, sales, operations, analytics, revenue, AI, and Help are separate menus.
5. Customers use My Account → Loyalty and Referrals.

== Frequently Asked Questions ==

= Does Myrvento Loyalty for WooCommerce require WooCommerce? =

Yes. WooCommerce must be active. The plugin follows High-Performance Order Storage. WordPress 6.0+ and PHP 7.4+ are required. Shop managers need the `manage_woocommerce` capability.

= Do referrals use a separate balance? =

No. Purchases, reviews, signups, birthdays, social shares, campaigns, and referrals all write to one points ledger. There is no referral wallet and no cash payout.

= When are points awarded? =

When an order reaches the status chosen in Settings. Completed is the default. Cancelling or refunding that order reverses the points from it. Set expiration days in Settings. Zero means points do not expire.

= Does the AI need an API key? =

No. Churn, next purchase, high-value scores, price suggestions, and demand forecasts run on the store from WooCommerce orders. Optional narration uses the WordPress AI Client and the provider the site owner connected in WordPress. This plugin does not store a provider key. Suggested prices are never applied to products.

= Why are the reports empty? =

A store with no paid orders shows empty states. Reports count WooCommerce paid statuses. Drafts and unpaid checkouts are left out of revenue.

= Why do the My Account links 404? =

Loyalty and Referrals are WooCommerce account endpoints. Activating the plugin refreshes permalinks. If the links still 404, open Settings → Permalinks and save once.

== Screenshots ==

1. Dashboard for the last 30 days: revenue, abandoned carts, win-back customers, and the demand forecast.
2. Points earn rules for purchases, reviews, signups, birthdays, and social shares.
3. VIP tiers qualified by spending, order count, or lifetime points.
4. Rewards redeemed from the same points balance.
5. Referral campaign, share link, and referral history.
6. Sales queues for abandoned carts, upsell and cross-sell pairs, and quiet customers.
7. Analytics for revenue, customers, products, and marketing.
8. Myrvento Brain cards for churn, pricing, and inventory. Prices are suggestions only.

== Source code ==

The human-readable source for the compiled admin UI lives in the `admin/src/` directory inside this plugin (React/JSX, CSS). Storefront source lives in `frontend/src/`. The minified files in `assets/` are generated from that source.

= Build tools =

Node.js and npm. Webpack 5 bundles the scripts. Babel transpiles JSX and modern JavaScript. PostCSS, Autoprefixer, and Tailwind CSS compile the stylesheets.

= Regenerating build/ assets =

From this plugin directory, after Node.js and npm are installed:

1. `npm install`
2. `npm run build`

`npm run build` writes the admin files to `assets/admin/` (`ciwp-admin.js`, `ciwp-admin.css`) and the storefront files to `assets/frontend/` (`ciwp.js`, `ciwp.css`). `npm run build:admin` and `npm run build:frontend` rebuild one of those bundles.

== Third-party licenses ==

The following libraries are compiled into the files in `assets/`. Each is MIT licensed.

* React and React DOM — https://github.com/facebook/react/blob/main/LICENSE
* qrcode (node-qrcode) — https://github.com/soldair/node-qrcode/blob/master/license

Webpack, Babel, Tailwind CSS, PostCSS, and Autoprefixer are build tools only. They are not included in the plugin zip.

== External services ==

This plugin does not call an AI provider itself. Optional narration goes through the WordPress AI Client. Share buttons open the customer's own browser.

**WordPress AI Client (optional).** Off by default. When an administrator enables narration, Myrvento Loyalty for WooCommerce asks WordPress to rewrite Myrvento Brain card wording. WordPress sends that prompt to the AI provider the site owner connected in WordPress settings. The plugin does not store a provider API key and does not choose the provider URL. The prompt includes store metrics and insight copy, which can include a customer display name. No request is sent when narration is off, when WordPress AI is unavailable, or when the site has no provider. On-store churn, pricing, and inventory models never leave the site.

The connected provider's terms and privacy policy apply. Those are chosen by the site owner in WordPress, not by this plugin.

**Referral share links (optional, customer-initiated).** Loyalty and referral screens can open WhatsApp, Facebook, or X with the store name and the customer’s referral URL. Nothing is sent until the customer chooses a share button. The link opens in the browser; the plugin does not contact those services itself.

* WhatsApp terms: https://www.whatsapp.com/legal/terms-of-service
* WhatsApp privacy: https://www.whatsapp.com/legal/privacy-policy
* Facebook terms: https://www.facebook.com/terms.php
* Facebook privacy: https://www.facebook.com/privacy/policy/
* X terms: https://x.com/en/tos
* X privacy: https://x.com/en/privacy

== Changelog ==

= 0.1.1 =
* Display name is Myrvento Loyalty for WooCommerce. Text domain is myrvento-loyalty-for-woocommerce.
* Optional narration uses the WordPress AI Client. The plugin no longer stores a provider key or calls a provider URL.
* Referral codes and visitor fingerprints use a plugin-owned secret.

= 0.1.0 =
* Initial loyalty + referral core.
* Analytics and revenue intelligence: revenue, customers, products, and marketing reports.
* Myrvento Brain: predictive churn / next purchase / high-value, pricing suggestions, seasonal and inventory forecasts.
* Dashboard plus Sales & conversion (abandoned cart, upsell, cross-sell, recovery), WooCommerce operations, and a Revenue intelligence screen for LTV, retention, churn, attribution, profitability, and forecasting.
* Help screen with guides for loyalty, referrals, sales, analytics, AI, and the customer account.
* Display name is Myrvento Loyalty for WooCommerce. Styles use the ciwp- prefix. Text domain is myrvento-loyalty-for-woocommerce.
