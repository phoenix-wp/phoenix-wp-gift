<?php

/**

 * Admin settings UI under PhoenixWP menu.

 *

 * @package PhoenixWP\Gift

 */



declare(strict_types=1);



namespace PhoenixWP\Gift\Admin;



use PhoenixWP\Gift\Rules\Rules_Repository;
use PhoenixWP\Gift\Settings;



defined( 'ABSPATH' ) || exit;



/**

 * Registers gift plugin settings page.

 */

final class Menu {



	public const CORE_MENU_SLUG = 'phoenix-wp-core';



	public const PAGE_SLUG = 'phoenix-gift-for-woocommerce';

	public const MENU_POSITION = 58;



	private const SETTINGS_GROUP = 'phoenix_wp_gift_settings_group';



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

		add_action( 'admin_menu', array( $this, 'register_menu' ), 25 );

		add_action( 'admin_init', array( $this, 'register_settings' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		License_Admin::instance()->init();

	}



	public function register_menu(): void {

		$page_title = __( 'Gift', 'phoenix-gift-for-woocommerce' );
		$menu_title = __( 'PhoenixWP Gift', 'phoenix-gift-for-woocommerce' );

		if ( ! phoenix_wp_gift_is_core_active() ) {

			add_menu_page(
				$menu_title,
				$menu_title,
				'manage_woocommerce',
				self::PAGE_SLUG,
				array( $this, 'render_page' ),
				'dashicons-cart',
				self::MENU_POSITION
			);

			return;

		}



		add_submenu_page(
			self::CORE_MENU_SLUG,
			$page_title,
			$page_title,
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

	}



	public function register_settings(): void {

		register_setting(

			self::SETTINGS_GROUP,

			Settings::OPTION_KEY,

			array(

				'type'              => 'array',

				'sanitize_callback' => array( Settings::instance(), 'sanitize' ),

			)

		);

	}



	/**

	 * @param string $hook_suffix Current admin page hook.

	 */

	public function enqueue_admin_assets( string $hook_suffix ): void {

		if ( ! str_contains( $hook_suffix, self::PAGE_SLUG ) ) {

			return;

		}



		wp_enqueue_script(
			'phoenix-gift-for-woocommerce-admin',
			PHOENIX_GIFT_FOR_WOOCOMMERCE_URL . 'assets/js/admin-settings.js',
			array(),
			PHOENIX_GIFT_FOR_WOOCOMMERCE_VERSION,
			true
		);

		wp_enqueue_style(
			'phoenix-gift-for-woocommerce-admin',
			PHOENIX_GIFT_FOR_WOOCOMMERCE_URL . 'assets/css/admin.css',
			array(),
			PHOENIX_GIFT_FOR_WOOCOMMERCE_VERSION
		);
	}



	public function render_page(): void {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {

			return;

		}



		$settings     = Settings::instance()->get_all();

		$trigger_type = Settings::instance()->get_trigger_type();

		$option_key   = Settings::OPTION_KEY;



		echo '<div class="wrap">';

		echo '<h1>' . esc_html__( 'PhoenixWP Gift', 'phoenix-gift-for-woocommerce' ) . '</h1>';

		printf(
			'<p><a class="button button-secondary" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
			esc_url( phoenix_wp_gift_get_docs_url() ),
			esc_html__( 'Documentation & FAQ', 'phoenix-gift-for-woocommerce' )
		);

		if ( $this->should_show_free_settings_form() ) {
			echo '<p>' . esc_html__( 'Free tier: one gift product per order with one condition (minimum subtotal or minimum item quantity).', 'phoenix-gift-for-woocommerce' ) . '</p>';
			$this->render_free_settings_form( $settings, $trigger_type, $option_key );
		} else {
			echo '<p class="description">';
			echo esc_html__(
				'Gift Pro is active. Configure gifts in the Pro rules section below — the free settings form is hidden to avoid duplicate rules.',
				'phoenix-gift-for-woocommerce'
			);
			echo '</p>';
		}

		/**
		 * Fires after the free settings form on the Gift admin page (premium renders Pro UI here).
		 */
		do_action( 'phoenix_wp_gift_admin_render_pro_sections' );

		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			echo '<hr /><h2>' . esc_html__( 'Pro', 'phoenix-gift-for-woocommerce' ) . '</h2>';

			$upgrade_url = phoenix_wp_gift_get_upgrade_url();

			echo '<p>' . esc_html__( 'Multiple rules, categories, roles, scheduling, and more.', 'phoenix-gift-for-woocommerce' ) . '</p>';

			printf(
				'<p><a class="button button-secondary" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
				esc_url( $upgrade_url ),
				esc_html__( 'Upgrade to Gift Pro', 'phoenix-gift-for-woocommerce' )
			);

			if ( function_exists( 'phoenix_wp_gift_fs' ) ) {
				\PhoenixWP\Gift\Freemius\License_Bridge::render_license_panel( self::PAGE_SLUG );
			}
		}

		echo '</div>';

	}

	private function should_show_free_settings_form(): bool {
		if ( ! phoenix_wp_gift_premium_loaded() || ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			return true;
		}

		$repository = Rules_Repository::instance();

		return ! ( method_exists( $repository, 'has_stored_rules' ) && $repository->has_stored_rules() );
	}

	/**
	 * @param array<string, mixed> $settings     Plugin settings.
	 * @param string               $trigger_type Active trigger type.
	 * @param string               $option_key   Option key.
	 */
	private function render_free_settings_form( array $settings, string $trigger_type, string $option_key ): void {
		echo '<form method="post" action="options.php">';

		settings_fields( self::SETTINGS_GROUP );



		echo '<table class="form-table" role="presentation">';



		echo '<tr><th scope="row">' . esc_html__( 'Enable gift', 'phoenix-gift-for-woocommerce' ) . '</th><td>';

		printf(

			'<label><input type="checkbox" name="%1$s[enabled]" value="1" %2$s /> %3$s</label>',

			esc_attr( $option_key ),

			checked( ! empty( $settings['enabled'] ), true, false ),

			esc_html__( 'Automatically add the gift product to the cart when conditions are met.', 'phoenix-gift-for-woocommerce' )

		);

		echo '</td></tr>';



		echo '<tr><th scope="row"><label for="phoenix-gift-for-woocommerce-product">' . esc_html__( 'Gift product', 'phoenix-gift-for-woocommerce' ) . '</label></th><td>';

		$this->render_product_select( (int) ( $settings['product_id'] ?? 0 ) );

		echo '</td></tr>';



		echo '<tr><th scope="row">' . esc_html__( 'Condition', 'phoenix-gift-for-woocommerce' ) . '</th><td>';

		printf(

			'<fieldset><label><input type="radio" name="%1$s[trigger_type]" value="%2$s" %3$s /> %4$s</label><br />',

			esc_attr( $option_key ),

			esc_attr( Settings::TRIGGER_SUBTOTAL ),

			checked( $trigger_type, Settings::TRIGGER_SUBTOTAL, false ),

			esc_html__( 'Minimum gross cart subtotal', 'phoenix-gift-for-woocommerce' )

		);

		printf(

			'<label><input type="radio" name="%1$s[trigger_type]" value="%2$s" %3$s /> %4$s</label></fieldset>',

			esc_attr( $option_key ),

			esc_attr( Settings::TRIGGER_ITEM_QUANTITY ),

			checked( $trigger_type, Settings::TRIGGER_ITEM_QUANTITY, false ),

			esc_html__( 'Minimum number of items in cart', 'phoenix-gift-for-woocommerce' )

		);

		echo '<p class="description">' . esc_html__( 'Free tier: choose one trigger type.', 'phoenix-gift-for-woocommerce' ) . '</p>';

		echo '</td></tr>';



		$subtotal_hidden = Settings::TRIGGER_SUBTOTAL !== $trigger_type;

		echo '<tr class="phoenix-gift-for-woocommerce-trigger-subtotal"';
		phoenix_wp_gift_echo_hidden_style_attr( $subtotal_hidden );
		echo '><th scope="row"><label for="phoenix-gift-for-woocommerce-min-subtotal">' . esc_html__( 'Minimum subtotal', 'phoenix-gift-for-woocommerce' ) . '</label></th><td>';

		printf(

			'<input type="number" step="0.01" min="0" class="regular-text" id="phoenix-gift-for-woocommerce-min-subtotal" name="%1$s[min_subtotal]" value="%2$s" />',

			esc_attr( $option_key ),

			esc_attr( (string) ( $settings['min_subtotal'] ?? '0' ) )

		);

		echo '<p class="description">' . esc_html__( 'Gross cart subtotal (incl. tax). Leave 0 for no minimum (gift when cart has any other product).', 'phoenix-gift-for-woocommerce' ) . '</p>';

		echo '</td></tr>';



		$quantity_hidden = Settings::TRIGGER_ITEM_QUANTITY !== $trigger_type;

		echo '<tr class="phoenix-gift-for-woocommerce-trigger-quantity"';
		phoenix_wp_gift_echo_hidden_style_attr( $quantity_hidden );
		echo '><th scope="row"><label for="phoenix-gift-for-woocommerce-min-quantity">' . esc_html__( 'Minimum item quantity', 'phoenix-gift-for-woocommerce' ) . '</label></th><td>';

		printf(

			'<input type="number" step="1" min="0" class="regular-text" id="phoenix-gift-for-woocommerce-min-quantity" name="%1$s[min_item_quantity]" value="%2$s" />',

			esc_attr( $option_key ),

			esc_attr( (string) ( $settings['min_item_quantity'] ?? '0' ) )

		);

		echo '<p class="description">' . esc_html__( 'Sum of quantities for all non-gift products. Leave 0 for any item in cart.', 'phoenix-gift-for-woocommerce' ) . '</p>';

		echo '</td></tr>';



		echo '<tr><th scope="row"><label for="phoenix-gift-for-woocommerce-label">' . esc_html__( 'Gift label', 'phoenix-gift-for-woocommerce' ) . '</label></th><td>';

		printf(

			'<input type="text" class="regular-text" id="phoenix-gift-for-woocommerce-label" name="%1$s[gift_label]" value="%2$s" placeholder="%3$s" />',

			esc_attr( $option_key ),

			esc_attr( (string) ( $settings['gift_label'] ?? '' ) ),

			esc_attr__( 'Free gift', 'phoenix-gift-for-woocommerce' )

		);

		echo '<p class="description">';
		echo esc_html__(
			'Badge in the mini cart and classic (shortcode) checkout only. On the cart page and with Cart/Checkout blocks, use CSS class phoenix-gift-for-woocommerce-cart-item — see Documentation & FAQ (phoenixwp.com).',
			'phoenix-gift-for-woocommerce'
		);
		echo '</p>';

		echo '</td></tr>';



		echo '</table>';



		submit_button();

		echo '</form>';
	}

	/**
	 * Renders WooCommerce product dropdown.
	 */
	private function render_product_select( int $selected_id ): void {

		$products = wc_get_products(

			array(

				'limit'  => 200,

				'status' => 'publish',

				'type'   => array( 'simple' ),

				'return' => 'objects',

			)

		);



		echo '<select name="' . esc_attr( Settings::OPTION_KEY ) . '[product_id]" id="phoenix-gift-for-woocommerce-product" class="regular-text">';



		echo '<option value="0">' . esc_html__( '— Select product —', 'phoenix-gift-for-woocommerce' ) . '</option>';



		foreach ( $products as $product ) {

			if ( ! $product instanceof \WC_Product ) {

				continue;

			}



			printf(

				'<option value="%1$d" %2$s>%3$s (#%1$d)</option>',

				esc_attr( (string) $product->get_id() ),

				selected( $selected_id, $product->get_id(), false ),

				esc_html( $product->get_name() )

			);

		}



		echo '</select>';

	}

}


