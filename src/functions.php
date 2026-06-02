<?php
/**
 * Global helper functions.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * Whether PhoenixWP Core is active.
 */
function phoenix_wp_gift_is_core_active(): bool {
	return defined( 'PHOENIX_WP_CORE_VERSION' ) || class_exists( \PhoenixWP\Core\Plugin::class );
}

/**
 * Whether the private premium package is present in this build.
 */
function phoenix_wp_gift_premium_loaded(): bool {
	return defined( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_HAS_PREMIUM' ) && PHOENIX_GIFT_FOR_WOOCOMMERCE_HAS_PREMIUM;
}

/**
 * Whether Pro stored rules (not free settings) drive the storefront.
 */
function phoenix_wp_gift_uses_pro_rules(): bool {
	if ( ! phoenix_wp_gift_premium_loaded() || ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
		return false;
	}

	$repository = \PhoenixWP\Gift\Rules\Rules_Repository::instance();

	return method_exists( $repository, 'has_stored_rules' ) && $repository->has_stored_rules();
}

/**
 * Whether any gift rule or free settings are configured for the storefront.
 */
function phoenix_wp_gift_has_active_configuration(): bool {
	if ( phoenix_wp_gift_uses_pro_rules() ) {
		return count( \PhoenixWP\Gift\Rules\Rules_Repository::instance()->get_enabled_rules_sorted() ) > 0;
	}

	return \PhoenixWP\Gift\Settings::instance()->is_enabled() && \PhoenixWP\Gift\Settings::instance()->get_product_id() > 0;
}

/**
 * Whether WooCommerce is active.
 */
function phoenix_wp_gift_is_woocommerce_active(): bool {
	return class_exists( \WooCommerce::class );
}

/**
 * Whether a Pro feature is active.
 *
 * @param string $feature Feature slug without plugin prefix.
 */
function phoenix_wp_gift_is_pro_active( string $feature ): bool {
	if ( ! phoenix_wp_gift_premium_loaded() ) {
		return false;
	}

	$feature = 'gift_' . sanitize_key( $feature );

	if ( class_exists( \PhoenixWP\Gift\Freemius\License_Bridge::class ) && \PhoenixWP\Gift\Freemius\License_Bridge::is_pro_license_active() ) {
		return true;
	}

	if ( function_exists( 'phoenix_wp_core_is_feature_active' ) ) {
		return phoenix_wp_core_is_feature_active( $feature );
	}

	return false;
}

/**
 * URL for Gift Pro upgrade (marketing landing).
 */
function phoenix_wp_gift_get_upgrade_url(): string {
	/**
	 * Filters the Gift Pro upgrade URL shown in admin.
	 *
	 * @param string $url Default phoenixwp.com product landing.
	 */
	return (string) apply_filters( 'phoenix_wp_gift_upgrade_url', 'https://phoenixwp.com/phoenix-wp-gift/' );
}

/**
 * URL for public help documentation (phoenixwp.com).
 */
function phoenix_wp_gift_get_docs_url(): string {
	/**
	 * Filters the Gift documentation URL shown in admin.
	 *
	 * @param string $url Default phoenixwp.com docs (DE).
	 */
	return (string) apply_filters( 'phoenix_wp_gift_docs_url', 'https://phoenixwp.com/phoenix-wp-gift/' );
}

/**
 * Prints a safe inline style attribute for toggled admin rows.
 */
function phoenix_wp_gift_echo_hidden_style_attr( bool $hidden ): void {
	if ( $hidden ) {
		echo ' style="' . esc_attr( 'display:none;' ) . '"';
	}
}

/**
 * Debug log via Core when available.
 */
function phoenix_wp_gift_log( string $message, string $level = 'info', array $context = array() ): void {
	if ( function_exists( 'phoenix_wp_core_log' ) ) {
		phoenix_wp_core_log( '[Gift] ' . $message, $level, $context );
	}
}
