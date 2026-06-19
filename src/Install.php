<?php
/**
 * Plugin activation lifecycle.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift;

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation and deactivation.
 */
final class Install {

	public const MIN_WP  = '6.7';
	public const MIN_PHP = '8.2';

	public static function register_hooks(): void {
		register_activation_hook( PHOENIX_GIFT_FOR_WOOCOMMERCE_FILE, array( self::class, 'activate' ) );
		register_deactivation_hook( PHOENIX_GIFT_FOR_WOOCOMMERCE_FILE, array( self::class, 'deactivate' ) );
	}

	public static function activate(): void {
		if ( ! self::requirements_met() ) {
			deactivate_plugins( PHOENIX_GIFT_FOR_WOOCOMMERCE_BASENAME );
			wp_die(
				esc_html__( 'PhoenixWP Gift requires WordPress 6.7+, PHP 8.2+, and WooCommerce.', 'phoenix-gift-for-woocommerce' ),
				esc_html__( 'Plugin Activation Error', 'phoenix-gift-for-woocommerce' ),
				array( 'back_link' => true )
			);
		}

		Settings::instance()->maybe_set_defaults();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function requirements_met(): bool {
		global $wp_version;

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return false;
		}

		if ( isset( $wp_version ) && version_compare( $wp_version, self::MIN_WP, '<' ) ) {
			return false;
		}

		if ( ! phoenix_wp_gift_is_woocommerce_active() ) {
			return false;
		}

		return true;
	}
}
