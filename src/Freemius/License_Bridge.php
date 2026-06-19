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

	public const MODULE_SLUG = 'phoenix-gift-for-woocommerce';

	/**
	 * Registers hooks after SDK load.
	 */
	public static function init(): void {
		add_filter( 'phoenix_wp_core_is_feature_active', array( self::class, 'filter_gift_features' ), 8, 2 );
		add_filter( 'phoenix_wp_core_module_tier', array( self::class, 'filter_module_tier' ), 10, 3 );
		add_filter( 'phoenix_wp_gift_upgrade_url', array( self::class, 'filter_upgrade_url' ) );
		add_filter( 'phoenix_wp_core_activate_extension_license', array( self::class, 'filter_core_license_activation' ), 10, 3 );
		add_action( 'phoenix_wp_core_render_extension_license_fields', array( self::class, 'render_core_license_field' ) );
	}

	/**
	 * Activates a Freemius license key on this site.
	 *
	 * @return array<string, mixed>
	 */
	public static function activate_license_key( string $license_key ): array {
		if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'message' => __( 'Freemius SDK is not loaded.', 'phoenix-gift-for-woocommerce' ),
				),
			);
		}

		$license_key = sanitize_text_field( trim( $license_key ) );

		if ( '' === $license_key ) {
			return array(
				'success' => false,
				'error'   => array(
					'message' => __( 'Please enter a license key.', 'phoenix-gift-for-woocommerce' ),
				),
			);
		}

		$fs = phoenix_wp_gift_fs();

		if ( ! is_object( $fs ) || ! method_exists( $fs, 'activate_migrated_license' ) ) {
			return array(
				'success' => false,
				'error'   => array(
					'message' => __( 'License activation is unavailable.', 'phoenix-gift-for-woocommerce' ),
				),
			);
		}

		$result = $fs->activate_migrated_license( $license_key );

		return is_array( $result ) ? $result : array( 'success' => false );
	}

	/**
	 * @param array<string, mixed>|null $result      Prior result.
	 * @param string                    $slug        Extension slug.
	 * @param string                    $license_key License key.
	 * @return array<string, mixed>|null
	 */
	public static function filter_core_license_activation( ?array $result, string $slug, string $license_key ): ?array {
		if ( self::MODULE_SLUG !== $slug ) {
			return $result;
		}

		return self::activate_license_key( $license_key );
	}

	/**
	 * Renders Gift license field on PhoenixWP Core settings.
	 */
	public static function render_core_license_field(): void {
		if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
			return;
		}

		echo '<h3>' . esc_html__( 'Gift Pro', 'phoenix-gift-for-woocommerce' ) . '</h3>';
		self::render_license_panel( 'phoenix-wp-core-settings' );
	}

	/**
	 * Renders license status, key form, and Freemius account link.
	 */
	public static function render_license_panel( string $return_page = '' ): void {
		self::render_license_notices();

		if ( self::is_pro_license_active() ) {
			echo '<p class="description">' . esc_html__( 'Gift Pro license is active on this site.', 'phoenix-gift-for-woocommerce' ) . '</p>';
		} else {
			echo '<p>' . esc_html__( 'Enter your Gift Pro license key from phoenixwp.com. You can also connect or manage your license in the Freemius account screen.', 'phoenix-gift-for-woocommerce' ) . '</p>';
		}

		$action = admin_url( 'admin-post.php' );
		echo '<form method="post" action="' . esc_url( $action ) . '" class="phoenix-gift-license-form" style="max-width:32em;margin:1em 0;">';
		wp_nonce_field( \PhoenixWP\Gift\Admin\License_Admin::ACTION_ACTIVATE );
		echo '<input type="hidden" name="action" value="' . esc_attr( \PhoenixWP\Gift\Admin\License_Admin::ACTION_ACTIVATE ) . '" />';

		if ( '' !== $return_page ) {
			printf(
				'<input type="hidden" name="_wp_http_referer" value="%s" />',
				esc_attr( admin_url( 'admin.php?page=' . sanitize_key( $return_page ) ) )
			);
		}

		echo '<p><label for="phoenix-gift-license-key"><strong>' . esc_html__( 'License key', 'phoenix-gift-for-woocommerce' ) . '</strong></label><br />';
		printf(
			'<input type="text" class="regular-text code" id="phoenix-gift-license-key" name="license_key" value="" autocomplete="off" maxlength="32" placeholder="%s" /></p>',
			esc_attr__( 'XXXX-XXXX-XXXX-XXXX', 'phoenix-gift-for-woocommerce' )
		);
		submit_button( __( 'Activate license', 'phoenix-gift-for-woocommerce' ), 'primary', 'submit', false );
		echo '</form>';

		$account_url = self::get_account_admin_url();
		if ( '' !== $account_url ) {
			printf(
				'<p><a class="button button-secondary" href="%1$s">%2$s</a></p>',
				esc_url( $account_url ),
				esc_html__( 'Open Freemius account', 'phoenix-gift-for-woocommerce' )
			);
		}

		$connect_url = self::get_connect_admin_url();
		if ( '' !== $connect_url && function_exists( 'phoenix_wp_gift_fs' ) ) {
			$fs = phoenix_wp_gift_fs();
			if ( is_object( $fs ) && method_exists( $fs, 'is_registered' ) && ! $fs->is_registered( true ) ) {
				printf(
					'<p><a href="%1$s">%2$s</a></p>',
					esc_url( $connect_url ),
					esc_html__( 'First-time setup: connect plugin (free or license)', 'phoenix-gift-for-woocommerce' )
				);
			}
		}
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
	 * Admin URL for Freemius account / license management.
	 */
	public static function get_account_admin_url(): string {
		if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
			return '';
		}

		$fs = phoenix_wp_gift_fs();

		if ( ! is_object( $fs ) || ! method_exists( $fs, 'get_account_url' ) ) {
			return '';
		}

		return (string) $fs->get_account_url();
	}

	/**
	 * Admin URL for Freemius opt-in / initial license connect screen.
	 */
	public static function get_connect_admin_url(): string {
		if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
			return '';
		}

		$fs = phoenix_wp_gift_fs();

		if ( ! is_object( $fs ) || ! method_exists( $fs, 'get_activation_url' ) ) {
			return '';
		}

		return (string) $fs->get_activation_url( array( 'require_license' => 'true' ) );
	}

	/**
	 * @param array<string, mixed> $result Freemius activation response.
	 */
	public static function get_activation_error_message( array $result ): string {
		if ( isset( $result['error']['message'] ) && is_string( $result['error']['message'] ) ) {
			return $result['error']['message'];
		}

		if ( isset( $result['error'] ) && is_string( $result['error'] ) ) {
			return $result['error'];
		}

		return __( 'License activation failed. Check the key or use the Freemius connect screen.', 'phoenix-gift-for-woocommerce' );
	}

	/**
	 * Prints success/error notices after license form submit.
	 */
	private static function render_license_notices(): void {
		if ( isset( $_GET['phoenix_gift_license'] ) ) {
			$status = sanitize_key( wp_unslash( (string) $_GET['phoenix_gift_license'] ) );
			if ( 'activated' === $status ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html__( 'Gift Pro license activated.', 'phoenix-gift-for-woocommerce' );
				echo '</p></div>';
			}
		}

		if ( isset( $_GET['phoenix_gift_license_error'] ) ) {
			$error = sanitize_text_field( wp_unslash( (string) $_GET['phoenix_gift_license_error'] ) );
			if ( '' !== $error ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
			}
		}
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
		if ( self::MODULE_SLUG !== $slug ) {
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

		return '' !== $upgrade ? $upgrade : $url;
	}
}
