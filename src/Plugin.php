<?php
/**
 * Main plugin bootstrap.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift;

use PhoenixWP\Core\Module_Registry;
use PhoenixWP\Gift\Admin\Menu;
use PhoenixWP\Gift\Admin\Rules_Admin;
use PhoenixWP\Gift\Admin\Tools_Admin;
use PhoenixWP\Gift\Cart\Gift_Handler;
use PhoenixWP\Gift\Frontend\Gift_Choice;
use PhoenixWP\Gift\Frontend\Progress_Shortcode;
use PhoenixWP\Gift\Rules\Rules_Repository;
use PhoenixWP\Gift\Freemius\License_Bridge;

defined( 'ABSPATH' ) || exit;

/**
 * Extension singleton bootstrap.
 */
final class Plugin {

	private static ?self $instance = null;

	private static bool $initialized = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Freemius boots in freemius-gift.php before Plugin::instance(); init immediately when SDK is ready.
		if ( function_exists( 'phoenix_wp_gift_fs' ) ) {
			License_Bridge::init();
		} else {
			add_action( 'phoenix_wp_gift_fs_loaded', array( License_Bridge::class, 'init' ) );
		}

		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_compatibility' ) );
		add_action( 'phoenix_wp_core_register_modules', array( $this, 'register_module' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
		add_action( 'admin_notices', array( $this, 'dependency_notices' ) );
	}

	/**
	 * Declares HPOS and Cart/Checkout Blocks compatibility.
	 */
	public function declare_woocommerce_compatibility(): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PHOENIX_WP_GIFT_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PHOENIX_WP_GIFT_FILE, true );
		}
	}

	private function __clone() {}

	public function __wakeup(): void {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}

	public function init(): void {
		if ( self::$initialized || ! Install::requirements_met() ) {
			return;
		}

		self::$initialized = true;

		load_plugin_textdomain(
			'phoenix-wp-gift',
			false,
			dirname( PHOENIX_WP_GIFT_BASENAME ) . '/languages'
		);

		Settings::instance()->maybe_set_defaults();

		add_filter( 'phoenix_wp_core_feature_tiers', array( $this, 'register_feature_tiers' ) );

		if ( phoenix_wp_gift_is_woocommerce_active() ) {
			Gift_Handler::instance()->init();
			Progress_Shortcode::register_hooks();
			Gift_Choice::register_hooks();
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_blocks_class_assets' ), 20 );
		}

		if ( is_admin() ) {
			Rules_Admin::register_hooks();
			Tools_Admin::register_hooks();
			Menu::instance()->init();
		}

		/**
		 * Fires when PhoenixWP Gift is fully loaded.
		 */
		do_action( 'phoenix_wp_gift_loaded' );
	}

	/**
	 * @param Module_Registry $registry Core registry.
	 */
	public function register_module( Module_Registry $registry ): void {
		$registry->register(
			array(
				'slug'    => 'phoenix-wp-gift',
				'name'    => __( 'PhoenixWP Gift Product', 'phoenix-wp-gift' ),
				'version' => PHOENIX_WP_GIFT_VERSION,
				'type'    => Module_Registry::TYPE_EXTENSION,
				'tier'    => 'free',
				'file'    => PHOENIX_WP_GIFT_FILE,
			)
		);
	}

	/**
	 * Maps Pro feature slugs for this extension.
	 *
	 * @param array<string, string> $map Feature => tier.
	 * @return array<string, string>
	 */
	public function register_feature_tiers( array $map ): array {
		$gift_pro = array(
			'gift_pro'             => 'pro',
			'gift_multiple_rules'  => 'pro',
			'gift_rule_conditions' => 'pro',
			'gift_rule_schedule'   => 'pro',
			'gift_tiered_gifts'    => 'pro',
			'gift_progress_hint'   => 'pro',
			'gift_import_export'   => 'pro',
			'gift_stats'           => 'pro',
			'gift_variations'      => 'pro',
			'gift_customer_choice' => 'pro',
		);

		return array_merge( $map, $gift_pro );
	}

	/**
	 * Enqueues storefront styles when the gift is enabled.
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! phoenix_wp_gift_has_active_configuration() ) {
			return;
		}

		wp_enqueue_style(
			'phoenix-wp-gift',
			PHOENIX_WP_GIFT_URL . 'assets/css/gift.css',
			array(),
			PHOENIX_WP_GIFT_VERSION
		);
	}

	/**
	 * Enqueues Cart/Checkout block script that applies phoenix-wp-gift-cart-item on gift lines.
	 */
	public function enqueue_blocks_class_assets(): void {
		if ( ! phoenix_wp_gift_has_active_configuration() ) {
			return;
		}

		if ( ! Gift_Handler::instance()->blocks_class_script_needed() ) {
			return;
		}

		$dependency = 'wc-cart-checkout-block-frontend';

		if ( ! wp_script_is( $dependency, 'registered' ) ) {
			$dependency = 'wc-blocks-checkout';
		}

		if ( ! wp_script_is( $dependency, 'registered' ) ) {
			return;
		}

		wp_enqueue_script(
			'phoenix-wp-gift-blocks',
			PHOENIX_WP_GIFT_URL . 'assets/js/gift-blocks.js',
			array( $dependency ),
			PHOENIX_WP_GIFT_VERSION,
			true
		);

		wp_localize_script(
			'phoenix-wp-gift-blocks',
			'phoenixWpGift',
			array(
				'giftProductId'  => Settings::instance()->get_product_id(),
				'giftProductIds' => Rules_Repository::instance()->get_managed_product_ids(),
			)
		);
	}

	public function dependency_notices(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! phoenix_wp_gift_is_core_active() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__(
				'PhoenixWP Gift: PhoenixWP Core is not active. Registry and licensing integration are disabled.',
				'phoenix-wp-gift'
			);
			echo '</p></div>';
		}

		if ( ! phoenix_wp_gift_is_woocommerce_active() ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'PhoenixWP Gift requires WooCommerce.', 'phoenix-wp-gift' );
			echo '</p></div>';
		}
	}
}
