<?php
/**
 * Plugin Name:       PhoenixWP Gift Product
 * Plugin URI:        https://phoenixwp.com
 * Description:       Add a free gift product to WooCommerce carts (Free tier). PhoenixWP extension.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Tested up to:      7.0
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Author:            PhoenixWP
 * Author URI:        https://phoenixwp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       phoenix-wp-gift
 * Domain Path:       /languages
 *
 * @package PhoenixWP\Gift
 */

defined( 'ABSPATH' ) || exit;

define( 'PHOENIX_WP_GIFT_VERSION', '1.0.0' );
define( 'PHOENIX_WP_GIFT_FILE', __FILE__ );
define( 'PHOENIX_WP_GIFT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PHOENIX_WP_GIFT_URL', plugin_dir_url( __FILE__ ) );
define( 'PHOENIX_WP_GIFT_BASENAME', plugin_basename( __FILE__ ) );

require_once PHOENIX_WP_GIFT_PATH . 'includes/freemius-gift.php';

$autoload = PHOENIX_WP_GIFT_PATH . 'vendor/autoload.php';

if ( is_readable( $autoload ) ) {
	require_once $autoload;
} else {
	require_once PHOENIX_WP_GIFT_PATH . 'includes/autoload-fallback.php';
	phoenix_wp_gift_register_autoload_fallback();
}

\PhoenixWP\Gift\Install::register_hooks();

\PhoenixWP\Gift\Plugin::instance();
