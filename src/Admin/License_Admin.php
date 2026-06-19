<?php
/**
 * License key entry for Gift Pro (Freemius).
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Admin;

use PhoenixWP\Gift\Freemius\License_Bridge;

defined( 'ABSPATH' ) || exit;

/**
 * Registers license submenu and handles manual license activation.
 */
final class License_Admin {

	public const PAGE_SLUG = 'phoenix-gift-for-woocommerce-license';

	public const ACTION_ACTIVATE = 'phoenix_wp_gift_activate_license';

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 26 );
		add_action( 'admin_post_' . self::ACTION_ACTIVATE, array( $this, 'handle_activate' ) );
	}

	/**
	 * Adds License submenu next to the main Gift screen.
	 */
	public function register_menu(): void {
		if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
			return;
		}

		$parent = phoenix_wp_gift_is_core_active()
			? Menu::CORE_MENU_SLUG
			: Menu::PAGE_SLUG;

		add_submenu_page(
			$parent,
			__( 'Gift License', 'phoenix-gift-for-woocommerce' ),
			__( 'License', 'phoenix-gift-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Renders the dedicated license page.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Gift Pro License', 'phoenix-gift-for-woocommerce' ) . '</h1>';
		License_Bridge::render_license_panel( self::PAGE_SLUG );
		echo '</div>';
	}

	/**
	 * Activates a license key submitted from plugin or Core settings.
	 */
	public function handle_activate(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to activate licenses.', 'phoenix-gift-for-woocommerce' ) );
		}

		check_admin_referer( self::ACTION_ACTIVATE );

		$license_key = isset( $_POST['license_key'] )
			? sanitize_text_field( wp_unslash( (string) $_POST['license_key'] ) )
			: '';

		$result  = License_Bridge::activate_license_key( $license_key );
		$referer = wp_get_referer();
		$base    = is_string( $referer ) && '' !== $referer
			? $referer
			: admin_url( 'admin.php?page=' . self::PAGE_SLUG );

		if ( ! empty( $result['success'] ) ) {
			wp_safe_redirect( add_query_arg( 'phoenix_gift_license', 'activated', $base ) );
			exit;
		}

		$message = License_Bridge::get_activation_error_message( $result );
		wp_safe_redirect(
			add_query_arg(
				'phoenix_gift_license_error',
				rawurlencode( $message ),
				$base
			)
		);
		exit;
	}
}
