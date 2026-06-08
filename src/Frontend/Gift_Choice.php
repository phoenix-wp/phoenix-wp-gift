<?php

/**

 * Customer gift choice UI and submission handler.

 *

 * @package PhoenixWP\Gift

 */



declare(strict_types=1);



namespace PhoenixWP\Gift\Frontend;



use PhoenixWP\Gift\Cart\Gift_Handler;

use PhoenixWP\Gift\Rules\Gift_Options_Helper;



defined( 'ABSPATH' ) || exit;



/**

 * Renders pickers for rules with customer gift selection.

 */

final class Gift_Choice {



	private const ACTION = 'phoenix_wp_gift_select_gift';

	private const NONCE  = 'phoenix_wp_gift_choice';



	public static function register_hooks(): void {

		add_shortcode( 'phoenix_wp_gift_choice', array( self::class, 'render_shortcode' ) );

		add_action( 'woocommerce_before_cart', array( self::class, 'render_cart_choices' ), 12 );

		add_action( 'woocommerce_before_checkout_form', array( self::class, 'render_cart_choices' ), 8 );

		add_filter( 'render_block', array( self::class, 'inject_block_cart_choices' ), 10, 2 );

		add_action( 'wp_loaded', array( self::class, 'handle_submission' ), 20 );

		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_assets' ), 25 );

		Gift_Choice_Rest::register_hooks();

	}



	/**

	 * @param array<string, string>|string $atts Shortcode attributes.

	 */

	public static function render_shortcode( $atts = array() ): string {

		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {

			return '';

		}



		$atts = shortcode_atts(

			array(

				'rule' => '',

			),

			$atts,

			'phoenix_wp_gift_choice'

		);



		return self::render_choice_root( sanitize_key( (string) $atts['rule'] ) );

	}



	public static function render_cart_choices(): void {

		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {

			return;

		}



		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_choice_root().

		echo self::render_choice_root();

	}



	/**

	 * Injects a live-updatable root wrapper before WooCommerce Cart/Checkout blocks.

	 *

	 * @param string               $content Block HTML.

	 * @param array<string, mixed> $block   Block data.

	 */

	public static function inject_block_cart_choices( string $content, array $block ): string {

		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {

			return $content;

		}



		$block_name = (string) ( $block['blockName'] ?? '' );



		if ( ! in_array( $block_name, array( 'woocommerce/cart', 'woocommerce/checkout' ), true ) ) {

			return $content;

		}



		return self::render_choice_root() . $content;

	}



	public static function render_choice_root( string $rule_filter = '' ): string {

		$inner       = self::render_pending_inner( $rule_filter );

		$root_class  = array( 'phoenix-wp-gift-choice-root' );



		if ( '' === $inner ) {

			$root_class[] = 'phoenix-wp-gift-choice-root--empty';

		}



		return sprintf(

			'<div class="%1$s" data-rule="%2$s">%3$s</div>',

			esc_attr( implode( ' ', $root_class ) ),

			esc_attr( $rule_filter ),

			$inner

		);

	}



	public static function render_pending_inner( string $rule_filter = '', bool $log_debug = true ): string {

		$pending = self::get_pending_choice_rules( $rule_filter, $log_debug );



		if ( empty( $pending ) ) {

			return '';

		}



		ob_start();



		foreach ( $pending as $rule_id => $rule ) {

			self::render_choice_form( $rule_id, $rule );

		}



		return (string) ob_get_clean();

	}



	/**

	 * @return array<string, array<string, mixed>>

	 */

	private static function get_pending_choice_rules( string $rule_filter = '', bool $log_debug = true ): array {

		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {

			return array();

		}



		$handler  = Gift_Handler::instance();

		$resolved = $handler->resolve_applicable_rules( WC()->cart );

		$pending  = array();



		$debug_customer_rules = array();



		foreach ( $resolved['matching'] as $rule_id => $rule ) {

			if ( '' !== $rule_filter && $rule_id !== $rule_filter ) {

				continue;

			}



			if ( ! Gift_Options_Helper::requires_customer_choice( $rule ) ) {

				continue;

			}



			$debug_customer_rules[] = $rule_id;



			if ( $handler->cart_contains_gift_for_rule( WC()->cart, $rule_id, $rule ) ) {

				continue;

			}



			$pending[ $rule_id ] = $rule;

		}



		if ( $log_debug ) {

			$choice_debug = array();



			foreach ( $debug_customer_rules as $customer_rule_id ) {

				$customer_rule = $resolved['matching'][ $customer_rule_id ] ?? array();

				$choice_debug[ $customer_rule_id ] = array(

					'remembered_choice' => $handler->get_remembered_customer_choice( $customer_rule_id ),

					'fulfilled'         => is_array( $customer_rule ) && $handler->customer_choice_is_fulfilled( WC()->cart, $customer_rule_id, $customer_rule ),

				);

			}



			phoenix_wp_gift_log(

				'Gift choice pending check.',

				'info',

				array(

					'customer_rule_ids' => $debug_customer_rules,

					'pending_rule_ids'  => array_keys( $pending ),

					'matching_rule_ids' => array_keys( $resolved['matching'] ),

					'choice_state'      => $choice_debug,

				)

			);

		}



		return $pending;

	}



	/**

	 * @param array<string, mixed> $rule Rule payload.

	 */

	private static function render_choice_form( string $rule_id, array $rule ): void {

		$options = array_values(

			array_filter(

				Gift_Options_Helper::get_options( $rule ),

				static fn( array $option ): bool => Gift_Options_Helper::option_is_purchasable( $option )

			)

		);



		if ( empty( $options ) ) {

			return;

		}



		$rule_name = trim( (string) ( $rule['name'] ?? '' ) );



		echo '<div class="phoenix-wp-gift-choice" data-rule-id="' . esc_attr( $rule_id ) . '">';



		if ( '' !== $rule_name ) {

			printf(

				'<p class="phoenix-wp-gift-choice__title"><strong>%s</strong></p>',

				esc_html( $rule_name )

			);

		}



		echo '<p class="phoenix-wp-gift-choice__text">' . esc_html__( 'Choose your free gift:', 'phoenix-wp-gift' ) . '</p>';

		echo '<form method="post" class="phoenix-wp-gift-choice__form">';

		wp_nonce_field( self::NONCE );

		echo '<input type="hidden" name="phoenix_wp_gift_choice_action" value="' . esc_attr( self::ACTION ) . '" />';

		echo '<input type="hidden" name="rule_id" value="' . esc_attr( $rule_id ) . '" />';

		echo '<ul class="phoenix-wp-gift-choice__options">';



		foreach ( $options as $index => $option ) {

			$product_id   = absint( $option['product_id'] ?? 0 );

			$variation_id = absint( $option['variation_id'] ?? 0 );

			$label        = Gift_Options_Helper::get_display_name( $option );

			$input_id     = 'phoenix-wp-gift-choice-' . esc_attr( $rule_id ) . '-' . (string) $index;



			echo '<li>';

			printf(

				'<label for="%1$s"><input type="radio" id="%1$s" name="gift_option_key" value="%2$s" %3$s /> %4$s</label>',

				esc_attr( $input_id ),

				esc_attr( Gift_Options_Helper::option_key( $option ) ),

				checked( 0 === $index, true, false ),

				esc_html( $label )

			);

			echo '<input type="hidden" name="gift_options_map[' . esc_attr( Gift_Options_Helper::option_key( $option ) ) . '][product_id]" value="' . esc_attr( (string) $product_id ) . '" />';

			echo '<input type="hidden" name="gift_options_map[' . esc_attr( Gift_Options_Helper::option_key( $option ) ) . '][variation_id]" value="' . esc_attr( (string) $variation_id ) . '" />';

			echo '</li>';

		}



		echo '</ul>';

		printf(

			'<p><button type="submit" class="button alt phoenix-wp-gift-choice__submit">%s</button></p>',

			esc_html__( 'Add gift to cart', 'phoenix-wp-gift' )

		);

		echo '</form>';

		echo '</div>';

	}



	public static function clear_gift_added_notices(): void {

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {

			return;

		}



		$notices = WC()->session->get( 'wc_notices', array() );



		if ( ! is_array( $notices ) || empty( $notices['success'] ) ) {

			return;

		}



		$needle = __( 'Your free gift was added to the cart.', 'phoenix-wp-gift' );



		$notices['success'] = array_values(

			array_filter(

				$notices['success'],

				static function ( $notice ) use ( $needle ): bool {

					$text = is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice;



					return ! str_contains( wp_strip_all_tags( $text ), wp_strip_all_tags( $needle ) );

				}

			)

		);



		if ( empty( $notices['success'] ) ) {

			unset( $notices['success'] );

		}



		WC()->session->set( 'wc_notices', $notices );

	}



	/**
	 * @param array<string, mixed> $options_map Gift option map from the form.
	 * @return array{message: string}|\WP_Error
	 */
	public static function process_customer_choice( string $rule_id, string $option_key, array $options_map ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart instanceof \WC_Cart ) {
			return new \WP_Error(
				'phoenix_wp_gift_no_cart',
				__( 'Your cart is not available. Please reload the page and try again.', 'phoenix-wp-gift' )
			);
		}

		if ( '' === $rule_id ) {
			return new \WP_Error(
				'phoenix_wp_gift_missing_rule',
				__( 'Please choose a gift option.', 'phoenix-wp-gift' )
			);
		}

		$resolved = Gift_Handler::instance()->resolve_applicable_rules( WC()->cart );

		if ( ! isset( $resolved['matching'][ $rule_id ] ) ) {
			return new \WP_Error(
				'phoenix_wp_gift_unavailable',
				__( 'This gift is no longer available for your cart.', 'phoenix-wp-gift' )
			);
		}

		$rule = $resolved['matching'][ $rule_id ];

		if ( ! Gift_Options_Helper::requires_customer_choice( $rule ) ) {
			return new \WP_Error(
				'phoenix_wp_gift_not_choice_rule',
				__( 'This gift does not require a customer selection.', 'phoenix-wp-gift' )
			);
		}

		if ( '' === $option_key || ! isset( $options_map[ $option_key ] ) || ! is_array( $options_map[ $option_key ] ) ) {
			return new \WP_Error(
				'phoenix_wp_gift_missing_option',
				__( 'Please choose a gift option.', 'phoenix-wp-gift' )
			);
		}

		$row    = $options_map[ $option_key ];
		$option = Gift_Options_Helper::find_option(
			$rule,
			absint( $row['product_id'] ?? 0 ),
			absint( $row['variation_id'] ?? 0 )
		);

		if ( null === $option || ! Gift_Options_Helper::option_is_purchasable( $option ) ) {
			return new \WP_Error(
				'phoenix_wp_gift_invalid_option',
				__( 'The selected gift is not available.', 'phoenix-wp-gift' )
			);
		}

		$handler = Gift_Handler::instance();
		$handler->remove_gifts_for_rule( WC()->cart, $rule_id );
		$handler->remember_customer_choice( $rule_id, Gift_Options_Helper::option_key( $option ) );
		$handler->add_gift_option_to_cart( WC()->cart, $rule_id, $option );
		WC()->cart->calculate_totals();

		if ( WC()->session ) {
			WC()->session->set_customer_session_cookie( true );
		}

		return array(
			'message' => __( 'Your free gift was added to the cart.', 'phoenix-wp-gift' ),
		);
	}

	public static function handle_submission(): void {
		if ( empty( $_POST['phoenix_wp_gift_choice_action'] ) || self::ACTION !== sanitize_key( wp_unslash( (string) $_POST['phoenix_wp_gift_choice_action'] ) ) ) {
			return;
		}

		check_admin_referer( self::NONCE );

		$rule_id    = isset( $_POST['rule_id'] ) ? sanitize_key( wp_unslash( (string) $_POST['rule_id'] ) ) : '';
		$option_key = isset( $_POST['gift_option_key'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['gift_option_key'] ) ) : '';
		$map        = isset( $_POST['gift_options_map'] ) && is_array( $_POST['gift_options_map'] ) ? wp_unslash( $_POST['gift_options_map'] ) : array();

		$result = self::process_customer_choice( $rule_id, $option_key, $map );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			self::redirect_back();

			return;
		}

		wc_add_notice( (string) ( $result['message'] ?? '' ), 'success' );
		self::redirect_back();
	}



	public static function maybe_enqueue_assets(): void {

		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {

			return;

		}



		if ( ! phoenix_wp_gift_has_active_configuration() ) {

			return;

		}



		if ( ! function_exists( 'is_cart' ) || ( ! is_cart() && ! is_checkout() ) ) {

			return;

		}



		wp_enqueue_script(

			'phoenix-wp-gift-choice',

			PHOENIX_WP_GIFT_URL . 'assets/js/gift-choice.js',

			array( 'jquery' ),

			PHOENIX_WP_GIFT_VERSION,

			true

		);



		wp_localize_script(

			'phoenix-wp-gift-choice',

			'phoenixWpGiftChoice',

			array(
				'restUrl'   => rest_url( 'phoenix-wp-gift/v1/gift-choice' ),
				'selectUrl' => rest_url( 'phoenix-wp-gift/v1/gift-choice/select' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'i18n'      => array(
					'added'        => __( 'Your free gift was added to the cart.', 'phoenix-wp-gift' ),
					'genericError' => __( 'Could not add the gift. Please try again.', 'phoenix-wp-gift' ),
					'chooseOption' => __( 'Please choose a gift option.', 'phoenix-wp-gift' ),
				),
			)

		);

	}



	private static function redirect_back(): void {

		$redirect = wp_get_referer();



		if ( ! is_string( $redirect ) || '' === $redirect ) {

			$redirect = wc_get_cart_url();

		}



		wp_safe_redirect( $redirect );

		exit;

	}

}


