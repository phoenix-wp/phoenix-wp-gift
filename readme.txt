=== Phoenix Gift for WooCommerce ===
Contributors: phoenixwp
Donate link: https://phoenixwp.com/preise/
Tags: woocommerce, gift, free gift, cart, promotion
Requires at least: 6.7
Tested up to: 7.0
Stable tag: 1.0.3
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to: 10.9.1

Automatically add a free gift to the WooCommerce cart when a minimum gross cart subtotal or minimum item quantity is met.

== Description ==

Boost your store's average order value with automated checkout incentives. Phoenix Gift for WooCommerce detects when your cart criteria are met and adds a free gift product to the cart. If the cart falls below your threshold, the gift is removed automatically.

The free version allows one active rule based on either a minimum gross subtotal or a minimum item quantity. The gift price is locked to zero, quantity is fixed to 1, and the line is sorted to the end of the cart.

On first activation, you can connect your site via Freemius to manage your plan or enter a license key (optional — the free tier works without a license).

= Free features =

* **One active rule** — minimum gross cart subtotal (including tax) **or** minimum item quantity (one trigger type per rule, not both)
* **One simple gift product** per rule
* **HPOS and Cart/Checkout Blocks** compatible
* Optional **gift label** badge in the mini cart and classic (shortcode) checkout
* CSS class `phoenix-gift-for-woocommerce-cart-item` for styling on cart page and block checkout

**Typical use cases**

* Increase average order value with automatic threshold gifts
* Reward shoppers without manual coupon handling
* Run a simple always-on gift campaign in the free version
* Keep cart logic clean — gift is added when eligible and removed when not

**Compatibility**

* WordPress 6.7+ and WooCommerce 8.0+
* Tested with WooCommerce 10.9.1
* HPOS and Cart/Checkout Blocks compatible
* Standalone plugin — WooCommerce only, no other PhoenixWP plugins required

= Gift Pro =

Gift Pro (annual license at 19 € / 19 $ per year via https://phoenixwp.com/preise/): multiple rules, customer gift choice, live progress hint, import/export, and statistics. Activate your license under **PhoenixWP Gift → License**.

**Advanced features in Gift Pro**

* Unlimited active rules with priority ordering
* Multiple gift products per rule, including variable products
* Customer gift choice (pick 1 of N)
* Advanced conditions: categories, tags, roles, coupons, and campaign dates
* Live progress hint and shortcode messaging before threshold is reached
* Rule import/export and trigger statistics

More on https://phoenixwp.com/phoenix-wp-gift/

= For licensed Pro installations =

If your Gift Pro license is already active, the plugin runs as a full production workflow without additional upsell steps. Use Pro for multi-rule campaigns, advanced targeting, and operational tooling (import/export, statistics).

Premium-ready roadmap: Business Suite integration is planned for a later stage (currently targeted for late 2026). This roadmap is not a live feature yet.

== Installation ==

1. Upload the plugin folder from your PhoenixWP download ZIP to `/wp-content/plugins/phoenix-gift-for-woocommerce/`, or install from the WordPress plugin directory.
2. Activate the plugin through the **Plugins** menu.
3. Ensure **WooCommerce** is active.
4. Complete the optional Freemius connect step, or skip it to use the free tier immediately.
5. Go to **PhoenixWP Gift**, enable the rule, choose a gift product, and set your threshold.

== Frequently Asked Questions ==

= Where is the gift label shown? =

The configurable **Gift label** badge appears in the **mini cart** and **classic (shortcode) checkout** only. It is not shown on the cart page or in the Cart/Checkout blocks, to avoid theme and HTML conflicts.

= How do I highlight the gift on the cart page or with Checkout blocks? =

The plugin adds the CSS class `phoenix-gift-for-woocommerce-cart-item` to the gift line (classic cart and blocks). Add custom CSS under **Appearance → Customize → Additional CSS**.

Example for Cart and Checkout blocks:

`
.phoenix-gift-for-woocommerce-cart-item .wc-block-components-product-name::after {
	content: "Free gift";
	display: inline-block;
	margin-inline-start: 0.35em;
	padding: 0.1em 0.45em;
	font-size: 0.75em;
	font-weight: 600;
	border-radius: 3px;
	background: #e8f5e9;
	color: #2e7d32;
}
`

Replace `Free gift` with your shop wording. This text is fixed in CSS and does not sync with the admin **Gift label** field.

= Is this plugin compatible with Cart and Checkout Blocks? =

Yes. The plugin is compatible with the classic shortcode checkout and the WooCommerce Cart/Checkout Blocks, and declares HPOS compatibility.

= Can I use net cart subtotals in the free version? =

No. The free tier uses the **gross** cart subtotal (including line tax) only.

= Does this plugin require other dependencies? =

WooCommerce is required. The plugin runs standalone with its own admin menu — no other PhoenixWP plugins are needed.

= Does this plugin support gift cards or vouchers? =

No. This is an automated checkout gift based on cart rules — not downloadable vouchers, coupon generators, or balance-based gift cards.

= Why is the gift not added to the cart? =

Check that the rule is enabled, the gift product is purchasable, and the threshold is met (gross subtotal excluding the gift line, or item quantity excluding the gift).

= Does this work with WPML or WooCommerce Multilingual? =

The free gift is added by **product ID**. Translate the **gift product into every active shop language** and keep each translation **published** and **purchasable**. If the gift exists only in the default language, customers who switch language on the storefront will usually **not** see the gift in the cart.

= How do I upgrade to Gift Pro? =

Purchase at https://phoenixwp.com/preise/. You receive a license key by email from Freemius. Enter it under **PhoenixWP Gift → License** — usually no reinstall required.

= What changes when my Gift Pro license is active? =

The same plugin installation stays in place. Pro capabilities are enabled in your existing workflow, including unlimited rules, advanced conditions, and customer gift choice.

= Where can I get support? =

https://phoenixwp.com/support/

== Screenshots ==

1. PhoenixWP Gift settings — enable the rule, pick threshold type, and select the gift product
2. Gift label and threshold options (gross subtotal or minimum item quantity)
3. WooCommerce cart with the free gift line at zero price
4. Mini cart showing the optional gift badge label
5. Cart or Checkout block with the gift line (CSS class hook for theme styling)

== Changelog ==

= 1.0.3 =
* Freemius upgrade pricing: show annual price prominently instead of monthly equivalent.
* Confirmed compatibility with WooCommerce 10.9.1.
* Readme: long-form details, use cases, compatibility, and expanded Gift Pro section.

= 1.0.2 =
* Removed the persistent standalone admin notice on Gift settings screens.
* readme: Pro license activation path simplified (no Core references on wordpress.org).

= 1.0.1 =
* wp.org release build: Pro PHP excluded from the free package (dual-build WpOrg/Freemius).
* Freemius SDK under vendor/freemius with is_org_compliant; Free tier fully usable without a license.

= 1.0.0 =
* Launch release: free tier with one gift rule (subtotal or quantity threshold). Tested on WordPress 7.0 and WooCommerce 10.8.1.
* HPOS and WooCommerce Cart/Checkout Blocks compatibility.
* Gift badge in mini cart and classic checkout; CSS hook for cart and blocks.
* Internal gift order meta hidden from customer emails and PDF documents (invoices, packing slips).
* Freemius licensing for Gift Pro (multiple rules, customer choice, progress, tools).
* Standalone admin menu, License submenu, and manual license key entry.

== Upgrade Notice ==

= 1.0.3 =
Freemius upgrade screen shows the annual license price upfront.

= 1.0.2 =
Cleaner admin UI and wordpress.org description (no Core references).

= 1.0.1 =
wp.org compliance: Pro code removed from free package; dual-build WpOrg/Freemius.

= 1.0.0 =
Initial release on WordPress.org.
