<?php
/**
 * Connects Freemius license state to PhoenixWP Core feature gates.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Freemius;

defined( 'ABSPATH' ) || exit;

/**
 * Maps Freemius Pro plan to gift_* feature checks.
 */
final class License_Bridge {

	/**
	 * Registers hooks after SDK load.
	 */
	public static function init(): void {
		add_filter( 'phoenix_wp_core_is_feature_active', array( self::class, 'filter_gift_features' ), 8, 2 );
		add_filter( 'phoenix_wp_core_module_tier', array( self::class, 'filter_module_tier' ), 10, 3 );
		add_filter( 'phoenix_wp_gift_upgrade_url', array( self::class, 'filter_upgrade_url' ) );
	}

	/**
	 * Whether a valid Freemius Pro license is active on this site.
	 */
	public static function is_pro_license_active(): bool {
		if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
			return false;
		}

		$fs = phoenix_wp_gift_fs();

		if ( ! is_object( $fs ) || ! method_exists( $fs, 'can_use_premium_code' ) ) {
			return false;
		}

		return (bool) $fs->can_use_premium_code();
	}

	/**
	 * Activates gift_* features when Freemius Pro is licensed.
	 *
	 * @param bool   $active  Current state.
	 * @param string $feature Feature slug.
	 */
	public static function filter_gift_features( bool $active, string $feature ): bool {
		if ( $active ) {
			return true;
		}

		if ( ! str_starts_with( $feature, 'gift_' ) ) {
			return $active;
		}

		return self::is_pro_license_active();
	}

	/**
	 * Reflects Freemius Pro license in the PhoenixWP dashboard module list.
	 *
	 * @param string               $tier   Registered tier.
	 * @param string               $slug   Module slug.
	 * @param array<string, mixed> $module Module registry entry.
	 */
	public static function filter_module_tier( string $tier, string $slug, array $module ): string {
		if ( 'phoenix-wp-gift' !== $slug ) {
			return $tier;
		}

		return self::is_pro_license_active() ? 'pro' : $tier;
	}

	/**
	 * Uses Freemius upgrade URL when available.
	 *
	 * @param string $url Default marketing URL.
	 */
	public static function filter_upgrade_url( string $url ): string {
		if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
			return $url;
		}

		$fs = phoenix_wp_gift_fs();

		if ( ! is_object( $fs ) || ! method_exists( $fs, 'get_upgrade_url' ) ) {
			return $url;
		}

		$upgrade = (string) $fs->get_upgrade_url();

		return $upgrade !== '' ? $upgrade : $url;
	}
}
