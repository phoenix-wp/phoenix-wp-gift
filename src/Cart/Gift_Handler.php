<?php
/**
 * Adds and prices the free gift in the cart.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Cart;

use PhoenixWP\Gift\Rules\Condition_Evaluator;
use PhoenixWP\Gift\Frontend\Gift_Choice;
use PhoenixWP\Gift\Rules\Gift_Options_Helper;
use PhoenixWP\Gift\Rules\Rule_Resolver;
use PhoenixWP\Gift\Rules\Rules_Repository;
use PhoenixWP\Gift\Rules\Audience_Evaluator;
use PhoenixWP\Gift\Rules\Cart_Content_Evaluator;
use PhoenixWP\Gift\Rules\Schedule_Evaluator;
use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Handles automatic gift product in WooCommerce cart.
 */
final class Gift_Handler {

	public const CART_FLAG    = 'phoenix_wp_gift';
	public const CART_RULE_ID = 'phoenix_wp_gift_rule_id';

	private const CHOICE_SESSION_PREFIX = 'phoenix_wp_gift_chosen_';

	private static ?self $instance = null;

	private bool $processing = false;

	private bool $in_recalc = false;

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
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_gift_zero_pricing' ), 99999 );

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'sync_gifts_before_totals' ), 99998 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'sync_gifts_after_totals' ), 5 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'reorder_gift_lines_to_end' ), 15 );

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
	 * Removes outdated gifts early; additions still run after totals are final.
	 */
	public function sync_gifts_before_totals( \WC_Cart $cart ): void {
		if ( $this->in_recalc || $this->processing ) {
			return;
		}

		$this->sync_gift_in_cart( $cart, 'remove_only' );
	}

	/**
	 * Syncs gifts after WooCommerce has calculated accurate line totals.
	 */
	public function sync_gifts_after_totals( \WC_Cart $cart ): void {
		if ( $this->in_recalc ) {
			return;
		}

		if ( ! $this->sync_gift_in_cart( $cart, 'both' ) ) {
			return;
		}

		$this->in_recalc = true;
		$cart->calculate_totals();
		$this->in_recalc = false;
	}

	/**
	 * Resolves applicable gift rules for the current cart (after upgrade groups).
	 *
	 * @param \WC_Cart|null $cart Cart instance.
	 * @return array{
	 *   matching: array<string, array<string, mixed>>,
	 *   raw_matching: array<string, array<string, mixed>>,
	 *   schedule_blocked: array<int, string>,
	 *   audience_blocked: array<int, string>,
	 *   cart_content_blocked: array<int, string>
	 * }
	 */
	public function resolve_applicable_rules( ?\WC_Cart $cart = null ): array {
		$cart = $cart ?? ( function_exists( 'WC' ) ? WC()->cart : null );

		$empty = array(
			'matching'               => array(),
			'raw_matching'           => array(),
			'schedule_blocked'       => array(),
			'audience_blocked'       => array(),
			'cart_content_blocked'   => array(),
		);

		if ( ! $cart instanceof \WC_Cart ) {
			return $empty;
		}

		$repository         = Rules_Repository::instance();
		$using_stored_rules = phoenix_wp_gift_uses_pro_rules();

		if ( ! $using_stored_rules && ! Settings::instance()->is_enabled() ) {
			return $empty;
		}

		$rules                = $repository->get_runtime_rules();
		$raw_matching         = array();
		$schedule_blocked     = array();
		$audience_blocked     = array();
		$cart_content_blocked = array();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$rule_id = (string) ( $rule['id'] ?? '' );

			if ( '' === $rule_id ) {
				continue;
			}

			if ( ! Audience_Evaluator::rule_matches_audience( $rule ) ) {
				$audience_blocked[] = $rule_id;
				continue;
			}

			if ( ! Schedule_Evaluator::rule_matches_schedule( $rule ) ) {
				$schedule_blocked[] = $rule_id;
				continue;
			}

			if ( ! Gift_Options_Helper::rule_has_purchasable_option( $rule ) ) {
				continue;
			}

			if ( ! Cart_Content_Evaluator::rule_matches_cart_content( $rule, $cart ) ) {
				$cart_content_blocked[] = $rule_id;
				continue;
			}

			if ( Condition_Evaluator::rule_matches_cart_trigger( $rule, $cart ) ) {
				$raw_matching[ $rule_id ] = $rule;
			}
		}

		return array(
			'matching'               => Rule_Resolver::resolve_applicable_rules( $raw_matching ),
			'raw_matching'           => $raw_matching,
			'schedule_blocked'       => $schedule_blocked,
			'audience_blocked'       => $audience_blocked,
			'cart_content_blocked'   => $cart_content_blocked,
		);
	}

	/**
	 * Adds or removes gift line items based on active rules and cart conditions.
	 *
	 * @param string $phase `both`, `remove_only`, or `add_only`.
	 * @return bool Whether the cart contents were modified.
	 */
	public function sync_gift_in_cart( \WC_Cart $cart, string $phase = 'both' ): bool {
		if ( ! $this->should_run_cart_logic() ) {
			return false;
		}

		if ( $this->processing ) {
			return false;
		}

		$this->processing = true;
		$modified         = false;

		$using_stored_rules = phoenix_wp_gift_uses_pro_rules();
		$resolved           = $this->resolve_applicable_rules( $cart );

		if ( ! $using_stored_rules && ! Settings::instance()->is_enabled() ) {
			$modified = $this->remove_all_gifts_from_cart( $cart ) || $modified;
			$this->processing = false;

			return $modified;
		}

		$matching             = $resolved['matching'];
		$raw_matching         = $resolved['raw_matching'];
		$schedule_blocked     = $resolved['schedule_blocked'];
		$audience_blocked     = $resolved['audience_blocked'];
		$cart_content_blocked = $resolved['cart_content_blocked'];

		phoenix_wp_gift_log(
			'Gift rule resolution.',
			'info',
			array(
				'raw_rule_ids'          => array_keys( $raw_matching ),
				'schedule_blocked_ids'  => $schedule_blocked,
				'audience_blocked_ids'    => $audience_blocked,
				'cart_content_blocked_ids' => $cart_content_blocked,
				'applicable_rule_ids'   => array_keys( $matching ),
				'suppressed_rule_ids' => array_keys( Rule_Resolver::get_suppressed_rules( $raw_matching, $matching ) ),
				'loser_product_ids'   => Rule_Resolver::get_loser_product_ids( $raw_matching, $matching ),
				'rule_groups'         => array_map(
					static function ( array $rule ): array {
						return array(
							'combine_mode'  => Rule_Resolver::get_combine_mode( $rule ),
							'upgrade_group' => Rule_Resolver::get_upgrade_group( $rule ),
						);
					},
					$raw_matching
				),
			)
		);

		$this->prune_stale_customer_choices( $matching );

		if ( 'add_only' !== $phase ) {
			foreach ( $this->collect_gift_line_keys_to_remove( $cart, $matching, $raw_matching, $using_stored_rules ) as $key ) {
				$cart->remove_cart_item( $key );
				$modified = true;
			}

			foreach ( $matching as $rule_id => $rule ) {
				if ( ! Gift_Options_Helper::requires_customer_choice( $rule ) ) {
					continue;
				}

				if ( $this->customer_choice_is_fulfilled( $cart, $rule_id, $rule ) ) {
					continue;
				}

				$before_count = count( $cart->get_cart() );
				$this->remove_gifts_for_rule( $cart, $rule_id );
				$this->clear_customer_choice( $rule_id );

				if ( count( $cart->get_cart() ) < $before_count ) {
					$modified = true;
					Gift_Choice::clear_gift_added_notices();

					phoenix_wp_gift_log(
						'Removed unconfirmed customer-choice gift line.',
						'info',
						array( 'rule_id' => $rule_id )
					);
				}
			}
		}

		if ( 'remove_only' === $phase ) {
			$this->processing = false;

			return $modified;
		}

		foreach ( $matching as $rule_id => $rule ) {
			if ( Gift_Options_Helper::requires_customer_choice( $rule ) ) {
				continue;
			}

			$option = Gift_Options_Helper::get_auto_option( $rule );

			if ( null === $option || ! Gift_Options_Helper::option_is_purchasable( $option ) ) {
				continue;
			}

			if ( $this->cart_contains_gift_for_rule( $cart, $rule_id, $rule ) ) {
				$this->ensure_gift_line_flags_for_rule( $cart, $rule_id, $rule );
				continue;
			}

			$this->add_gift_option_to_cart( $cart, $rule_id, $option );
			$modified = true;

			phoenix_wp_gift_log(
				'Gift product added to cart.',
				'info',
				array(
					'product_id'   => absint( $option['product_id'] ?? 0 ),
					'variation_id' => absint( $option['variation_id'] ?? 0 ),
					'rule_id'      => $rule_id,
				)
			);
		}

		$this->processing = false;

		return $modified;
	}

	/**
	 * Keeps gift line items at the bottom of cart and mini-cart listings.
	 */
	public function reorder_gift_lines_to_end( \WC_Cart $cart ): void {
		if ( ! $this->should_run_cart_logic() || ! $this->has_active_gift_configuration() ) {
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

		if ( $this->has_active_gift_configuration() ) {
			$this->ensure_all_gift_line_flags( WC()->cart );
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

		if ( ! empty( $values[ self::CART_RULE_ID ] ) ) {
			$cart_item[ self::CART_RULE_ID ] = sanitize_key( (string) $values[ self::CART_RULE_ID ] );
		}
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
		if ( ! $this->is_cart_display_context() || ! $this->is_managed_gift_product( $product ) ) {
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

		return $name . $this->get_gift_label_markup( $cart_item );
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
	 * HTML badge for the gift label (per rule or global fallback).
	 *
	 * @param array<string, mixed> $cart_item Cart line.
	 */
	private function get_gift_label_markup( array $cart_item ): string {
		return sprintf(
			' <span class="phoenix-wp-gift-label">%s</span>',
			esc_html( $this->get_gift_label_for_cart_item( $cart_item ) )
		);
	}

	/**
	 * @param array<string, mixed> $cart_item Cart line.
	 */
	private function get_gift_label_for_cart_item( array $cart_item ): string {
		$rule_id = sanitize_key( (string) ( $cart_item[ self::CART_RULE_ID ] ?? '' ) );

		if ( '' !== $rule_id && 'legacy_free' !== $rule_id ) {
			$rule = Rules_Repository::instance()->get( $rule_id );

			if ( is_array( $rule ) ) {
				$label = trim( (string) ( $rule['gift_label'] ?? '' ) );

				if ( '' !== $label ) {
					return $label;
				}
			}
		}

		return Settings::instance()->get_gift_label();
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

		if ( ! empty( $values[ self::CART_RULE_ID ] ) ) {
			$item->add_meta_data( self::CART_RULE_ID, sanitize_key( (string) $values[ self::CART_RULE_ID ] ), true );
		}
	}

	public function filter_order_line_subtotal( string $subtotal, \WC_Order_Item_Product $item, \WC_Order $order ): string {
		unset( $order );

		if ( $item->get_meta( self::CART_FLAG ) ) {
			return wc_price( 0 );
		}

		return $subtotal;
	}

	/**
	 * Tags gift rows after session reload (legacy lines without rule metadata).
	 */
	private function ensure_all_gift_line_flags( \WC_Cart $cart ): void {
		$managed_ids = Rules_Repository::instance()->get_managed_product_ids();

		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ self::CART_FLAG ] ) ) {
				continue;
			}

			foreach ( $managed_ids as $gift_id ) {
				if ( $this->line_matches_gift_product( $cart_item, $gift_id ) ) {
					$cart->cart_contents[ $cart_item_key ][ self::CART_FLAG ] = true;
					break;
				}
			}
		}
	}

	/**
	 * Determines which plugin-managed gift lines should be removed after rule resolution.
	 *
	 * @param array<string, array<string, mixed>> $matching           Applicable rules.
	 * @param array<string, array<string, mixed>> $raw_matching       All matching rules.
	 * @param bool                                  $using_stored_rules Whether Pro rules drive the cart.
	 * @return string[]
	 */
	private function collect_gift_line_keys_to_remove( \WC_Cart $cart, array $matching, array $raw_matching, bool $using_stored_rules ): array {
		$keys_to_remove  = array();
		$loser_products  = array_fill_keys( Rule_Resolver::get_loser_product_ids( $raw_matching, $matching ), true );
		$orphan_products = array_fill_keys( $this->get_orphan_gift_product_ids( $matching, $using_stored_rules ), true );

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( ! $this->is_plugin_gift_line( $item ) ) {
				continue;
			}

			if ( $this->should_keep_gift_line( $item, $matching ) ) {
				continue;
			}

			$keys_to_remove[] = $key;

			phoenix_wp_gift_log(
				'Gift line scheduled for removal.',
				'info',
				array(
					'cart_item_key' => $key,
					'rule_id'       => (string) ( $item[ self::CART_RULE_ID ] ?? '' ),
					'product_id'    => $this->get_cart_line_product_id( $item ),
					'loser_product' => isset( $loser_products[ $this->get_cart_line_product_id( $item ) ] ),
					'orphan_product' => isset( $orphan_products[ $this->get_cart_line_product_id( $item ) ] ),
				)
			);
		}

		return array_values( array_unique( $keys_to_remove ) );
	}

	/**
	 * Gift product IDs that may linger from legacy free settings while Pro rules are active.
	 *
	 * @param array<string, array<string, mixed>> $matching Applicable rules.
	 * @return int[]
	 */
	private function get_orphan_gift_product_ids( array $matching, bool $using_stored_rules ): array {
		if ( ! $using_stored_rules ) {
			return array();
		}

		$applicable_products = array();

		foreach ( $matching as $rule ) {
			foreach ( Gift_Options_Helper::get_line_ids_for_rule( $rule ) as $line_id ) {
				$applicable_products[ $line_id ] = true;
			}
		}

		$free_product_id = Settings::instance()->get_product_id();

		if ( $free_product_id > 0 && ! isset( $applicable_products[ $free_product_id ] ) ) {
			return array( $free_product_id );
		}

		return array();
	}

	/**
	 * Whether a cart line was added or managed by this plugin as a gift.
	 *
	 * @param array<string, mixed> $item Cart line.
	 */
	private function is_plugin_gift_line( array $item ): bool {
		if ( ! empty( $item[ self::CART_FLAG ] ) || ! empty( $item[ self::CART_RULE_ID ] ) ) {
			return true;
		}

		$line_product_id = $this->get_cart_line_product_id( $item );

		if ( $line_product_id <= 0 ) {
			return false;
		}

		foreach ( Rules_Repository::instance()->get_all() as $rule ) {
			if ( Gift_Options_Helper::line_matches_rule( $item, $rule ) ) {
				return true;
			}
		}

		$free_product_id = Settings::instance()->get_product_id();

		if ( $free_product_id > 0 && $line_product_id === $free_product_id && phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			return true;
		}

		return $this->is_gift_cart_item( $item );
	}

	/**
	 * @param array<string, mixed> $item Cart line.
	 */
	private function get_cart_line_product_id( array $item ): int {
		return absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );
	}

	/**
	 * @param array<string, mixed>              $item     Cart line.
	 * @param array<string, array<string, mixed>> $matching Applicable rules.
	 */
	private function should_keep_gift_line( array $item, array $matching ): bool {
		$rule_id = (string) ( $item[ self::CART_RULE_ID ] ?? '' );

		if ( '' !== $rule_id ) {
			if ( ! isset( $matching[ $rule_id ] ) ) {
				return false;
			}

			$rule = $matching[ $rule_id ];

			if ( Gift_Options_Helper::requires_customer_choice( $rule ) ) {
				if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
					return false;
				}

				if ( ! $this->customer_choice_is_fulfilled( WC()->cart, $rule_id, $rule ) ) {
					return false;
				}

				$chosen_option = $this->get_remembered_choice_option( $rule_id, $rule );

				return null !== $chosen_option && Gift_Options_Helper::line_matches_option( $item, $chosen_option );
			}

			return Gift_Options_Helper::line_matches_rule( $item, $rule );
		}

		foreach ( $matching as $rule ) {
			if ( Gift_Options_Helper::requires_customer_choice( $rule ) ) {
				continue;
			}

			if ( Gift_Options_Helper::line_matches_rule( $item, $rule ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string               $rule_id Rule identifier.
	 * @param array<string, mixed> $rule    Rule payload.
	 */
	private function ensure_gift_line_flags_for_rule( \WC_Cart $cart, string $rule_id, array $rule ): void {
		foreach ( $cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( ! Gift_Options_Helper::line_matches_rule( $cart_item, $rule ) ) {
				continue;
			}

			$cart->cart_contents[ $cart_item_key ][ self::CART_FLAG ]    = true;
			$cart->cart_contents[ $cart_item_key ][ self::CART_RULE_ID ] = $rule_id;
		}
	}

	/**
	 * Adds a configured gift option to the cart with plugin flags.
	 *
	 * @param array{product_id: int, variation_id: int} $option Gift option.
	 */
	public function add_gift_option_to_cart( \WC_Cart $cart, string $rule_id, array $option ): void {
		$args = Gift_Options_Helper::get_add_to_cart_args( $option );
		$args['cart_item_data'][ self::CART_FLAG ]    = true;
		$args['cart_item_data'][ self::CART_RULE_ID ] = sanitize_key( $rule_id );

		$cart->add_to_cart(
			$args['product_id'],
			$args['quantity'],
			$args['variation_id'],
			$args['variation'],
			$args['cart_item_data']
		);
	}

	/**
	 * Removes gift lines tied to a single rule.
	 */
	public function remove_gifts_for_rule( \WC_Cart $cart, string $rule_id ): void {
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( (string) ( $item[ self::CART_RULE_ID ] ?? '' ) !== $rule_id ) {
				continue;
			}

			$cart->remove_cart_item( $key );
		}
	}

	public function remember_customer_choice( string $rule_id, string $option_key ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( self::CHOICE_SESSION_PREFIX . sanitize_key( $rule_id ), sanitize_text_field( $option_key ) );
	}

	public function get_remembered_customer_choice( string $rule_id ): string {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return '';
		}

		return sanitize_text_field( (string) WC()->session->get( self::CHOICE_SESSION_PREFIX . sanitize_key( $rule_id ), '' ) );
	}

	public function clear_customer_choice( string $rule_id ): void {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->__unset( self::CHOICE_SESSION_PREFIX . sanitize_key( $rule_id ) );
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 * @return array{product_id: int, variation_id: int}|null
	 */
	public function get_remembered_choice_option( string $rule_id, array $rule ): ?array {
		$option_key = $this->get_remembered_customer_choice( $rule_id );

		if ( '' === $option_key ) {
			return null;
		}

		foreach ( Gift_Options_Helper::get_options( $rule ) as $option ) {
			if ( Gift_Options_Helper::option_key( $option ) === $option_key ) {
				return $option;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 */
	public function customer_choice_is_fulfilled( \WC_Cart $cart, string $rule_id, array $rule ): bool {
		$option = $this->get_remembered_choice_option( $rule_id, $rule );

		if ( null === $option ) {
			return false;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( (string) ( $item[ self::CART_RULE_ID ] ?? '' ) !== $rule_id ) {
				continue;
			}

			if ( empty( $item[ self::CART_FLAG ] ) ) {
				continue;
			}

			if ( Gift_Options_Helper::line_matches_option( $item, $option ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, array<string, mixed>> $matching Applicable rules.
	 */
	private function prune_stale_customer_choices( array $matching ): void {
		foreach ( Rules_Repository::instance()->get_all() as $rule_id => $rule ) {
			if ( ! Gift_Options_Helper::requires_customer_choice( $rule ) ) {
				continue;
			}

			if ( ! isset( $matching[ $rule_id ] ) ) {
				$this->clear_customer_choice( (string) $rule_id );
				Gift_Choice::clear_gift_added_notices();
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

		if ( ! $this->has_active_gift_configuration() ) {
			return false;
		}

		foreach ( Rules_Repository::instance()->get_managed_product_ids() as $gift_id ) {
			if ( $this->line_matches_gift_product( $cart_item, $gift_id ) ) {
				return true;
			}
		}

		return false;
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

	private function is_managed_gift_product( \WC_Product $product ): bool {
		return in_array( $product->get_id(), Rules_Repository::instance()->get_managed_product_ids(), true );
	}

	/**
	 * Whether quantity controls should be locked for a gift product in the cart.
	 */
	private function should_lock_gift_product_quantity( \WC_Product $product ): bool {
		if ( ! $this->has_active_gift_configuration() ) {
			return false;
		}

		if ( ! $this->is_managed_gift_product( $product ) ) {
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

	public function cart_contains_gift_for_rule( \WC_Cart $cart, string $rule_id, array $rule ): bool {
		if ( Gift_Options_Helper::requires_customer_choice( $rule ) ) {
			return $this->customer_choice_is_fulfilled( $cart, $rule_id, $rule );
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( ! Gift_Options_Helper::line_matches_rule( $item, $rule ) ) {
				continue;
			}

			$item_rule_id = (string) ( $item[ self::CART_RULE_ID ] ?? '' );

			if ( $item_rule_id === $rule_id || ( '' === $item_rule_id && $this->is_gift_cart_item( $item ) ) ) {
				return true;
			}
		}

		return false;
	}

	private function remove_all_gifts_from_cart( \WC_Cart $cart ): bool {
		$modified = false;

		foreach ( $cart->get_cart() as $key => $item ) {
			if ( $this->is_gift_cart_item( $item ) ) {
				$cart->remove_cart_item( $key );
				$modified = true;
			}
		}

		return $modified;
	}

	private function has_active_gift_configuration(): bool {
		if ( phoenix_wp_gift_uses_pro_rules() ) {
			return true;
		}

		return Settings::instance()->is_enabled() && Settings::instance()->get_product_id() > 0;
	}
}
