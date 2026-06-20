<?php
/**
 * PSR-4 fallback autoloader when Composer vendor/ is not present.
 *
 * @package PhoenixWP\Gift
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the extension PSR-4 autoloader.
 */
function phoenix_wp_gift_register_autoload_fallback(): void {
	static $registered = false;

	if ( $registered ) {
		return;
	}

	$registered = true;

	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'PhoenixWP\\Gift\\';

			if ( ! str_starts_with( $class, $prefix ) ) {
				return;
			}

			$relative    = substr( $class, strlen( $prefix ) );
			$premium_file = PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'premium/src/' . str_replace( '\\', '/', $relative ) . '.php';
			$free_file    = PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

			if (
				defined( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_HAS_PREMIUM' )
				&& PHOENIX_GIFT_FOR_WOOCOMMERCE_HAS_PREMIUM
				&& is_readable( $premium_file )
			) {
				require_once $premium_file;

				return;
			}

			if ( is_readable( $free_file ) ) {
				require_once $free_file;
			}
		}
	);

	require_once PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'src/functions.php';
}
