=== Phoenix Gift for WooCommerce ===
Contributors: phoenixwp
Tags: woocommerce, gift, free gift, cart, promotion
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.2
WC requires at least: 8.0
WC tested up to: 10.8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically add a free gift to the WooCommerce cart when a subtotal or item-quantity threshold is met. By PhoenixWP.

== Description ==

PhoenixWP Gift adds one free gift line to the cart when your rule is met. The gift price is zero, quantity is locked to 1, and the gift line is sorted to the end of the cart.

**Free version**

* One rule (minimum gross subtotal **or** minimum item quantity)
* One simple gift product
* HPOS and Cart/Checkout Blocks compatible
* Optional badge label in mini cart and classic checkout
* CSS class `phoenix-gift-for-woocommerce-cart-item` for theme styling on cart and block checkout

**Gift Pro** (annual license at [phoenixwp.com](https://phoenixwp.com/preise/)): multiple rules, customer gift choice, live progress hint, import/export, and statistics. Activate your license under **PhoenixWP Gift → License** (standalone) or **PhoenixWP → Gift → License** / **PhoenixWP → Settings → Extension licenses** when Core is installed.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/phoenix-gift-for-woocommerce/` or install from the WordPress plugin directory.
2. Activate the plugin through the **Plugins** menu.
3. Ensure **WooCommerce** is active.
4. Go to **PhoenixWP Gift** (or **PhoenixWP → Gift** with Core), enable the rule, choose a gift product, and set your threshold.

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

Documentation and support: https://phoenixwp.com/support/ — shorter reference in `docs/FAQ.md` inside the plugin.

= Why is the gift not added to the cart? =

Check that the rule is enabled, the gift product is purchasable, and the threshold is met (gross subtotal excluding the gift line, or item quantity excluding the gift).

== Screenshots ==

1. Gift settings — enable rule, choose gift product, set threshold
2. Cart with free gift line and optional badge in mini cart

== Changelog ==

= 1.0.0 =
* Launch release: free tier with one gift rule (subtotal or quantity threshold). Tested on WordPress 7.0 and WooCommerce 10.8.1.
* HPOS and WooCommerce Cart/Checkout Blocks compatibility.
* Gift badge in mini cart and classic checkout; CSS hook for cart and blocks.
* Internal gift order meta hidden from customer emails and PDF documents (invoices, packing slips).
* Freemius licensing for Gift Pro (multiple rules, customer choice, progress, tools).
* Standalone admin menu, License submenu, and manual license key entry (no Core required).

== Upgrade Notice ==

= 1.0.1 =
wp.org compliance: Pro code removed from free package; dual-build WpOrg/Freemius.

= 1.0.0 =
Initial release on WordPress.org.
