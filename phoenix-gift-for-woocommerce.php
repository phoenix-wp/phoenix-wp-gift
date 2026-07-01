<?php
/**
 * Plugin Name:       Phoenix Gift for WooCommerce
 * Plugin URI:        https://phoenixwp.com/phoenix-wp-gift/
 * Description:       Add a free gift product to WooCommerce carts (Free tier). PhoenixWP extension.
 * Version:           1.0.3
 * Requires at least: 6.7
 * Tested up to:      7.0
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   10.9.1
 * Author:            PhoenixWP
 * Author URI:        https://phoenixwp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       phoenix-gift-for-woocommerce
 * Domain Path:       /languages
 *
 * @package PhoenixWP\Gift
 */

defined( 'ABSPATH' ) || exit;

define( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_VERSION', '1.0.3' );
define( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_FILE', __FILE__ );
define( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_URL', plugin_dir_url( __FILE__ ) );
define( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_BASENAME', plugin_basename( __FILE__ ) );
define(
	'PHOENIX_GIFT_FOR_WOOCOMMERCE_HAS_PREMIUM',
	is_readable( PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'premium/bootstrap.php' )
);

require_once PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'includes/freemius-gift.php';

$phoenix_gift_for_woocommerce_autoload = PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'vendor/autoload.php';

if ( is_readable( $phoenix_gift_for_woocommerce_autoload ) ) {
	require_once $phoenix_gift_for_woocommerce_autoload;
}

require_once PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'includes/autoload-fallback.php';
phoenix_wp_gift_register_autoload_fallback();

\PhoenixWP\Gift\Install::register_hooks();

if ( PHOENIX_GIFT_FOR_WOOCOMMERCE_HAS_PREMIUM ) {
	require_once PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'premium/bootstrap.php';
}

\PhoenixWP\Gift\Plugin::instance();
