<?php
/**
 * Adds and prices the free gift in the cart.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Cart;

use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles automatic gift product in WooCommerce cart.
 */
final class Gift_Handler {

	public const CART_FLAG = 'phoenix_wp_gift';

	private static ?self $instance = null;

	private bool $processing = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	/**
	 * Registers cart and checkout hooks.
	 */
	public function init(): void {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'enforce_gift_single_quantity' ), 15 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'sync_gift_in_cart' ), 20 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'reorder_gift_lines_to_end' ), 25 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_gift_zero_pricing' ), 99999 );

		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'on_cart_loaded_from_session' ), 20 );

		add_filter( 'woocommerce_add_cart_item', array( $this, 'apply_gift_cart_item_pricing' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_gift_cart_item_from_session' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_product', array( $this, 'filter_cart_item_product' ), 999, 3 );

		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_gift_product_price' ), 999, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( $this, 'filter_gift_product_price' ), 999, 2 );
		add_filter( 'woocommerce_product_get_sale_price', array( $this, 'filter_gift_product_price' ), 999, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_gift_product_price' ), 999, 2 );

		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 999, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'filter_cart_item_subtotal' ), 999, 3 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'filter_cart_item_quantity_html' ), 999, 3 );
		add_filter( 'woocommerce_widget_cart_item_quantity', array( $this, 'filter_widget_cart_item_quantity' ), 999, 3 );
		add_filter( 'woocommerce_quantity_input_args', array( $this, 'filter_quantity_input_args' ), 999, 2 );
		add_filter( 'woocommerce_is_sold_individually', array( $this, 'filter_gift_sold_individually' ), 999, 2 );
		add_filter( 'woocommerce_update_cart_validation', array( $this, 'validate_gift_quantity_update' ), 999, 4 );

		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_cart_item_quantity_updated' ), 999, 4 );

		add_filter( 'woocommerce_store_api_product_quantity_minimum', array( $this, 'filter_store_api_gift_quantity_minimum' ), 999, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $this, 'filter_store_api_gift_quantity_maximum' ), 999, 3 );
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'filter_store_api_gift_quantity_editable' ), 999, 3 );

		add_filter( 'woocommerce_cart_item_class', array( $this, 'filter_cart_item_class' ), 999, 3 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'filter_cart_item_name' ), 999, 3 );

		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'set_order_line_item_zero' ), 10, 4 );
		add_filter( 'woocommerce_order_formatted_line_subtotal', array( $this, 'filter_order_line_subtotal' ), 10, 3 );
	}

	/**
	 * Adds or removes gift line item based on settings and cart subtotal.
	 */
	public function sync_gift_in_cart( \WC_Cart $cart ): void {
		if ( ! $this->should_run_cart_logic() ) {
			return;
		}

		if ( $this->processing ) {
			return;
		}

		$this->processing = true;

		$gift_id = Settings::instance()->get_product_id();

		if ( ! Settings::instance()->is_enabled() || $gift_id <= 0 ) {
			$this->remove_gift_from_cart( $cart );
			$this->processing = false;
			return;
		}

		$product = wc_get_product( $gift_id );

		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() ) {
			$this->remove_gift_from_cart( $cart );
			$this->processing = false;
			return;
		}

		if ( ! $this->cart_meets_minimum( $cart, $gift_id ) ) {
			$this->remove_gift_from_cart( $cart );
			$this->processing = false;
			return;
		}

		$this->ensure_gift_line_flags( $cart, $gift_id );

		if ( ! $this->cart_contains_gift( $cart, $gift_id ) ) {
			$cart->add_to_cart(
				$product->get_id(),
				1,
				0,
				array(),
				array(
					self::CART_FLAG => true,
				)
			);
			phoenix_wp_gift_log( 'Gift product added to cart.', 'info', array( 'product_id' => $gift_id ) );
		}

		$this->processing = false;
	}

	/**
	 * Keeps gift line items at the bottom of cart and mini-cart listings.
	 */
	public function reorder_gift_lines_to_end( \WC_Cart $cart ): void {
		if ( ! $this->should_run_cart_logic() || ! Settings::instance()->is_enabled() ) {
			return;
		}

		if ( count( $cart->cart_contents ) < 2 ) {
			return;
		}

		$regular_lines = array();
		$gift_lines    = array();

		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( $this->is_gift_cart_item( $cart_item ) ) {
				$gift_lines[ $cart_item_key ] = $cart_item;
				continue;
			}

			$regular_lines[ $cart_item_key ] = $cart_item;
		}

		if ( empty( $gift_lines ) ) {
			return;
		}

		$cart->cart_contents = array_merge( $regular_lines, $gift_lines );
	}

	/**
	 * Free tier: gift quantity is always 1 (not increaseable in Free or Pro via quantity stepper on gift lines).
	 */
	public function enforce_gift_single_quantity( \WC_Cart $cart ): void {
		if ( ! $this->should_run_cart_logic() ) {
			return;
		}

		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( ! $this->is_gift_cart_item( $cart_item ) ) {
				continue;
			}

			if ( (int) $cart_item['quantity'] !== 1 ) {
				$cart->set_quantity( $cart_item_key, 1, false );
			}
		}
	}

	/**
	 * Resets gift quantity immediately after any cart update (classic + Store API).
	 *
	 * @param string   $cart_item_key Cart item key.
	 * @param int      $quantity      New quantity.
	 * @param int      $old_quantity  Previous quantity.
	 * @param \WC_Cart $cart          Cart instance.
	 */
	public function on_cart_item_quantity_updated( string $cart_item_key, int $quantity, int $old_quantity, \WC_Cart $cart ): void {
		unset( $old_quantity );

		$cart_item = $cart->get_cart_item( $cart_item_key );

		if ( ! is_array( $cart_item ) || ! $this->is_gift_cart_item( $cart_item ) ) {
			return;
		}

		if ( (int) $quantity === 1 ) {
			return;
		}

		$cart->set_quantity( $cart_item_key, 1, false );
	}

	/**
	 * Replaces quantity controls with fixed "1" on the cart page (Free tier).
	 *
	 * @param string               $quantity_html Markup.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $cart_item     Cart item.
	 */
	public function filter_cart_item_quantity_html( string $quantity_html, string $cart_item_key, array $cart_item ): string {
		if ( ! $this->is_gift_cart_item( $cart_item ) ) {
			return $quantity_html;
		}

		return sprintf(
			'<span class="phoenix-wp-gift-quantity">%s</span><input type="hidden" name="cart[%s][qty]" value="1" />',
			esc_html( '1' ),
			esc_attr( $cart_item_key )
		);
	}

	/**
	 * Locks quantity inputs for the gift product (cart/checkout forms).
	 *
	 * @param array<string, mixed> $args    Quantity field args.
	 * @param \WC_Product          $product Product.
	 * @return array<string, mixed>
	 */
	public function filter_quantity_input_args( array $args, \WC_Product $product ): array {
		if ( ! $this->is_cart_display_context() ) {
			return $args;
		}

		if ( ! $this->should_lock_gift_product_quantity( $product ) ) {
			return $args;
		}

		$args['min_value']   = 1;
		$args['max_value']   = 1;
		$args['input_value'] = 1;
		$args['readonly']    = true;

		return $args;
	}

	/**
	 * Marks gift lines as sold individually (classic cart + Store API blocks).
	 *
	 * @param bool             $sold_individually Current value.
	 * @param \WC_Product|null $product           Product instance.
	 */
	public function filter_gift_sold_individually( bool $sold_individually, $product ): bool {
		if ( $sold_individually ) {
			return $sold_individually;
		}

		if ( ! $product instanceof \WC_Product ) {
			return $sold_individually;
		}

		if ( ! $this->should_lock_gift_product_quantity( $product ) ) {
			return $sold_individually;
		}

		return true;
	}

	/**
	 * Blocks cart updates that change gift quantity (Free tier).
	 *
	 * @param bool                 $passed        Validation result.
	 * @param string               $cart_item_key Cart item key.
	 * @param array<string, mixed> $values        Cart item values.
	 * @param int                  $quantity      Requested quantity.
	 */
	public function validate_gift_quantity_update( bool $passed, string $cart_item_key, array $values, int $quantity ): bool {
		unset( $values );

		if ( ! $passed ) {
			return $passed;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $passed;
		}

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );

		if ( ! is_array( $cart_item ) || ! $this->is_gift_cart_item( $cart_item ) ) {
			return $passed;
		}

		if ( $quantity !== 1 ) {
			return false;
		}

		return $passed;
	}

	/**
	 * Store API (Cart/Checkout blocks): minimum quantity for gift lines.
	 *
	 * @param int|float            $minimum   Current minimum.
	 * @param \WC_Product          $product   Product.
	 * @param array<string, mixed>|null $cart_item Cart item when in cart.
	 * @return int|float
	 */
	public function filter_store_api_gift_quantity_minimum( $minimum, \WC_Product $product, ?array $cart_item ) {
		if ( $this->should_lock_gift_store_api_quantity( $product, $cart_item ) ) {
			return 1;
		}

		return $minimum;
	}

	/**
	 * Store API (Cart/Checkout blocks): maximum quantity for gift lines.
	 *
	 * @param int|float            $maximum   Current maximum.
	 * @param \WC_Product          $product   Product.
	 * @param array<string, mixed>|null $cart_item Cart item when in cart.
	 * @return int|float
	 */
	public function filter_store_api_gift_quantity_maximum( $maximum, \WC_Product $product, ?array $cart_item ) {
		if ( $this->should_lock_gift_store_api_quantity( $product, $cart_item ) ) {
			return 1;
		}

		return $maximum;
	}

	/**
	 * Store API (Cart/Checkout blocks): disable quantity stepper for gift lines.
	 *
	 * @param bool                 $editable  Whether quantity is editable.
	 * @param \WC_Product          $product   Product.
	 * @param array<string, mixed>|null $cart_item Cart item when in cart.
	 */
	public function filter_store_api_gift_quantity_editable( bool $editable, \WC_Product $product, ?array $cart_item ): bool {
		if ( $this->should_lock_gift_store_api_quantity( $product, $cart_item ) ) {
			return false;
		}

		return $editable;
	}

	/**
	 * Forces zero line totals after WooCommerce and other plugins calculate prices.
	 */
	public function apply_gift_zero_pricing( \WC_Cart $cart ): void {
		if ( ! $this->should_run_cart_logic() ) {
			return;
		}

		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( ! $this->is_gift_cart_item( $cart_item ) ) {
				continue;
			}

			$this->zero_product_object( $cart->cart_contents[ $cart_item_key ]['data'] ?? null );

			$cart->cart_contents[ $cart_item_key ]['line_subtotal']     = 0;
			$cart->cart_contents[ $cart_item_key ]['line_total']        = 0;
			$cart->cart_contents[ $cart_item_key ]['line_subtotal_tax'] = 0;
			$cart->cart_contents[ $cart_item_key ]['line_tax']          = 0;
			$cart->cart_contents[ $cart_item_key ]['line_tax_data']     = array(
				'total'    => array(),
				'subtotal' => array(),
			);
		}
	}

	/**
	 * Re-applies zero price after session load (cart + mini-cart).
	 */
	public function on_cart_loaded_from_session(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$gift_id = Settings::instance()->get_product_id();

		if ( Settings::instance()->is_enabled() && $gift_id > 0 ) {
			$this->ensure_gift_line_flags( WC()->cart, $gift_id );
			$this->reorder_gift_lines_to_end( WC()->cart );
		}

		foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( ! $this->is_gift_cart_item( $cart_item ) ) {
				continue;
			}

			$this->zero_product_object( WC()->cart->cart_contents[ $cart_item_key ]['data'] ?? null );
		}
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 * @param string               $cart_item_key Cart item key.
	 * @return array<string, mixed>
	 */
	public function apply_gift_cart_item_pricing( array $cart_item, string $cart_item_key ): array {
		unset( $cart_item_key );

		if ( ! $this->is_gift_cart_item( $cart_item ) ) {
			return $cart_item;
		}

		$this->zero_product_object( $cart_item['data'] ?? null );

		return $cart_item;
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 * @param array<string, mixed> $values Session values.
	 * @param string               $cart_item_key Cart item key.
	 * @return array<string, mixed>
	 */
	public function restore_gift_cart_item_from_session( array $cart_item, array $values, string $cart_item_key ): array {
		unset( $cart_item_key );

		if ( empty( $values[ self::CART_FLAG ] ) ) {
			return $cart_item;
		}

		$cart_item[ self::CART_FLAG ] = true;
		$this->zero_product_object( $cart_item['data'] ?? null );

		return $cart_item;
	}

	/**
	 * @param \WC_Product|null $product   Product in cart row.
	 * @param array            $cart_item Cart item.
	 * @param string           $cart_item_key Cart item key.
	 */
	public function filter_cart_item_product( ?\WC_Product $product, array $cart_item, string $cart_item_key ): ?\WC_Product {
		unset( $cart_item_key );

		if ( $product instanceof \WC_Product && $this->is_gift_cart_item( $cart_item ) ) {
			$this->zero_product_object( $product );
		}

		return $product;
	}

	/**
	 * @param mixed       $price   Raw price.
	 * @param \WC_Product $product Product instance.
	 * @return mixed
	 */
	public function filter_gift_product_price( mixed $price, \WC_Product $product ): mixed {
		if ( ! $this->is_cart_display_context() || ! $this->is_configured_gift_product( $product ) ) {
			return $price;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return $price;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! $this->is_gift_cart_item( $cart_item ) ) {
				continue;
			}

			if ( ! isset( $cart_item['data'] ) || ! $cart_item['data'] instanceof \WC_Product ) {
				continue;
			}

			if ( $cart_item['data']->get_id() === $product->get_id() ) {
				return 0;
			}
		}

		return $price;
	}

	public function filter_cart_item_price( string $price, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );

		if ( $this->is_gift_cart_item( $cart_item ) ) {
			return wc_price( 0 );
		}

		return $price;
	}

	public function filter_cart_item_subtotal( string $subtotal, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );

		if ( $this->is_gift_cart_item( $cart_item ) ) {
			return wc_price( 0 );
		}

		return $subtotal;
	}

	/**
	 * Mini-cart: fixed quantity × 0,00 € (Free tier).
	 *
	 * @param string               $html          Default quantity HTML.
	 * @param array<string, mixed> $cart_item     Cart item.
	 * @param string               $cart_item_key Cart item key.
	 */
	public function filter_widget_cart_item_quantity( string $html, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );

		if ( ! $this->is_gift_cart_item( $cart_item ) ) {
			return $html;
		}

		return sprintf(
			'<span class="phoenix-wp-gift-quantity">%s &times; %s</span>',
			esc_html( '1' ),
			wp_kses_post( wc_price( 0 ) )
		);
	}

	/**
	 * Adds CSS class to gift cart rows (classic cart).
	 *
	 * @param string               $class       CSS classes.
	 * @param array<string, mixed> $cart_item   Cart item.
	 * @param string               $cart_item_key Cart item key.
	 */
	public function filter_cart_item_class( string $class, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );

		if ( ! $this->is_gift_cart_item( $cart_item ) ) {
			return $class;
		}

		return trim( $class . ' phoenix-wp-gift-cart-item' );
	}

	/**
	 * Appends configurable gift label where the classic PHP name filter is reliable.
	 *
	 * @param string               $name          Product name HTML.
	 * @param array<string, mixed> $cart_item     Cart item.
	 * @param string               $cart_item_key Cart item key.
	 */
	public function filter_cart_item_name( string $name, array $cart_item, string $cart_item_key ): string {
		unset( $cart_item_key );

		if ( ! $this->is_gift_cart_item( $cart_item ) || ! $this->should_append_gift_label_to_line_name() ) {
			return $name;
		}

		return $name . $this->get_gift_label_markup();
	}

	/**
	 * Only append the visible label in low-conflict WooCommerce templates.
	 *
	 * Cart page, Cart block, and Checkout block ignore woocommerce_cart_item_name or strip HTML.
	 * The CSS class phoenix-wp-gift-cart-item still applies everywhere for theme styling.
	 */
	private function should_append_gift_label_to_line_name(): bool {
		if ( is_cart() ) {
			return false;
		}

		if ( is_checkout() && $this->is_checkout_blocks_page() ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether Cart or Checkout blocks need the class-only frontend script.
	 */
	public function blocks_class_script_needed(): bool {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) ) {
			return false;
		}

		if ( is_cart() && \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_cart_block_default() ) {
			return true;
		}

		return is_checkout() && \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default();
	}

	/**
	 * Whether the checkout page uses the WooCommerce Checkout block.
	 */
	private function is_checkout_blocks_page(): bool {
		if ( ! is_checkout() ) {
			return false;
		}

		if ( class_exists( '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils' ) ) {
			return \Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils::is_checkout_block_default();
		}

		$post = get_post();

		return $post instanceof \WP_Post && function_exists( 'has_block' ) && has_block( 'woocommerce/checkout', $post );
	}

	/**
	 * HTML badge for the configured gift label.
	 */
	private function get_gift_label_markup(): string {
		return sprintf(
			' <span class="phoenix-wp-gift-label">%s</span>',
			esc_html( Settings::instance()->get_gift_label() )
		);
	}

	public function set_order_line_item_zero( \WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order ): void {
		unset( $cart_item_key, $order );

		if ( empty( $values[ self::CART_FLAG ] ) && ! $this->is_gift_cart_item( $values ) ) {
			return;
		}

		$item->set_subtotal( 0 );
		$item->set_total( 0 );
		$item->set_subtotal_tax( 0 );
		$item->set_total_tax( 0 );
		$item->add_meta_data( self::CART_FLAG, '1', true );
	}

	public function filter_order_line_subtotal( string $subtotal, \WC_Order_Item_Product $item, \WC_Order $order ): string {
		unset( $order );

		if ( $item->get_meta( self::CART_FLAG ) ) {
			return wc_price( 0 );
		}

		return $subtotal;
	}

	/**
	 * Tags configured gift product rows (e.g. after session reload without flag).
	 */
	private function ensure_gift_line_flags( \WC_Cart $cart, int $gift_id ): void {
		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( $this->line_matches_gift_product( $cart_item, $gift_id ) ) {
				$cart->cart_contents[ $cart_item_key ][ self::CART_FLAG ] = true;
			}
		}
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 */
	private function is_gift_cart_item( array $cart_item ): bool {
		if ( ! empty( $cart_item[ self::CART_FLAG ] ) ) {
			return true;
		}

		if ( ! Settings::instance()->is_enabled() ) {
			return false;
		}

		return $this->line_matches_gift_product( $cart_item, Settings::instance()->get_product_id() );
	}

	/**
	 * @param array<string, mixed> $cart_item Cart item.
	 */
	private function line_matches_gift_product( array $cart_item, int $gift_id ): bool {
		if ( $gift_id <= 0 ) {
			return false;
		}

		$line_id = (int) ( $cart_item['variation_id'] ?: $cart_item['product_id'] );

		return $line_id === $gift_id || (int) $cart_item['product_id'] === $gift_id;
	}

	private function is_configured_gift_product( \WC_Product $product ): bool {
		return Settings::instance()->get_product_id() === $product->get_id();
	}

	/**
	 * Whether quantity controls should be locked for a gift product in the cart.
	 */
	private function should_lock_gift_product_quantity( \WC_Product $product ): bool {
		if ( ! Settings::instance()->is_enabled() ) {
			return false;
		}

		if ( ! $this->is_configured_gift_product( $product ) ) {
			return false;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! $this->is_gift_cart_item( $cart_item ) ) {
				continue;
			}

			$line_product = $cart_item['data'] ?? null;

			if ( $line_product instanceof \WC_Product && $line_product->get_id() === $product->get_id() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether Store API quantity controls should be locked for a gift line.
	 *
	 * @param \WC_Product               $product   Product.
	 * @param array<string, mixed>|null $cart_item Cart item when in cart.
	 */
	private function should_lock_gift_store_api_quantity( \WC_Product $product, ?array $cart_item ): bool {
		if ( is_array( $cart_item ) && $this->is_gift_cart_item( $cart_item ) ) {
			return true;
		}

		return $this->should_lock_gift_product_quantity( $product );
	}

	private function zero_product_object( mixed $product ): void {
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->set_sale_price( '' );
	}

	private function should_run_cart_logic(): bool {
		return ! ( is_admin() && ! wp_doing_ajax() );
	}

	private function is_cart_display_context(): bool {
		if ( wp_doing_ajax() || is_cart() || is_checkout() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return did_action( 'woocommerce_before_calculate_totals' ) > 0;
	}

	private function cart_meets_minimum( \WC_Cart $cart, int $gift_id ): bool {
		if ( Settings::instance()->uses_item_quantity_trigger() ) {
			return $this->cart_meets_item_quantity( $cart, $gift_id );
		}

		return $this->cart_meets_subtotal( $cart, $gift_id );
	}

	private function cart_meets_subtotal( \WC_Cart $cart, int $gift_id ): bool {
		$minimum = Settings::instance()->get_min_subtotal();

		if ( $minimum <= 0 ) {
			return ! $this->cart_is_empty_except_gift( $cart, $gift_id );
		}

		return $this->get_subtotal_excluding_gift( $cart, $gift_id ) >= $minimum;
	}

	private function cart_meets_item_quantity( \WC_Cart $cart, int $gift_id ): bool {
		$minimum = Settings::instance()->get_min_item_quantity();
		$count   = $this->get_item_quantity_excluding_gift( $cart, $gift_id );

		if ( $minimum <= 0 ) {
			return $count > 0;
		}

		return $count >= $minimum;
	}

	private function get_item_quantity_excluding_gift( \WC_Cart $cart, int $gift_id ): int {
		$total = 0;

		foreach ( $cart->get_cart() as $item ) {
			if ( $this->is_gift_cart_item( $item ) || $this->line_matches_gift_product( $item, $gift_id ) ) {
				continue;
			}

			$total += max( 0, (int) ( $item['quantity'] ?? 0 ) );
		}

		return $total;
	}

	private function get_subtotal_excluding_gift( \WC_Cart $cart, int $gift_id ): float {
		$total = 0.0;

		foreach ( $cart->get_cart() as $item ) {
			if ( $this->is_gift_cart_item( $item ) ) {
				continue;
			}

			if ( $this->line_matches_gift_product( $item, $gift_id ) ) {
				continue;
			}

			// Gross (brutto): line subtotal including line tax — Free tier uses gross thresholds only.
			$total += (float) $item['line_subtotal'] + (float) ( $item['line_subtotal_tax'] ?? 0 );
		}

		return $total;
	}

	private function cart_is_empty_except_gift( \WC_Cart $cart, int $gift_id ): bool {
		foreach ( $cart->get_cart() as $item ) {
			if ( $this->is_gift_cart_item( $item ) || $this->line_matches_gift_product( $item, $gift_id ) ) {
				continue;
			}

			return false;
		}

		return true;
	}

	private function cart_contains_gift( \WC_Cart $cart, int $gift_id ): bool {
		foreach ( $cart->get_cart() as $item ) {
			if ( $this->is_gift_cart_item( $item ) || $this->line_matches_gift_product( $item, $gift_id ) ) {
				return true;
			}
		}

		return false;
	}

	private function remove_gift_from_cart( \WC_Cart $cart ): void {
		$gift_id = Settings::instance()->get_product_id();

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $this->is_gift_cart_item( $item ) || $this->line_matches_gift_product( $item, $gift_id ) ) {
				$cart->remove_cart_item( $key );
			}
		}
	}
}
