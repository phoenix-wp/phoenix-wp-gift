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
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'PhoenixWP\\Gift\\';

			if ( ! str_starts_with( $class, $prefix ) ) {
				return;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$file     = PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);

	require_once PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'src/functions.php';
}
