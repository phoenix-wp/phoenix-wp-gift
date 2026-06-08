<?php
/**
 * REST endpoint for live gift choice UI updates.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Serves gift choice HTML for the current cart session.
 */
final class Gift_Choice_Rest {

	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'phoenix-wp-gift/v1',
			'/gift-choice',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_choices' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'rule' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			'phoenix-wp-gift/v1',
			'/gift-choice/select',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'select_gift' ),
				'permission_callback' => array( self::class, 'verify_rest_nonce' ),
			)
		);
	}

	public static function verify_rest_nonce(): bool {
		$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_WP_NONCE'] ) )
			: '';

		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function get_choices( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			return new \WP_REST_Response(
				array(
					'html'  => '',
					'empty' => true,
				),
				200
			);
		}

		self::ensure_cart_loaded();

		$rule_filter = sanitize_key( (string) $request->get_param( 'rule' ) );
		$html        = Gift_Choice::render_pending_inner( $rule_filter, false );

		return new \WP_REST_Response(
			array(
				'html'  => $html,
				'empty' => '' === $html,
			),
			200
		);
	}

	/**
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function select_gift( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			return new \WP_REST_Response(
				array(
					'message' => __( 'Gift Pro is not available on this site.', 'phoenix-wp-gift' ),
				),
				403
			);
		}

		self::ensure_cart_loaded();

		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$rule_id      = sanitize_key( (string) ( $params['rule_id'] ?? '' ) );
		$option_key   = sanitize_text_field( (string) ( $params['gift_option_key'] ?? '' ) );
		$options_map  = isset( $params['gift_options_map'] ) && is_array( $params['gift_options_map'] )
			? $params['gift_options_map']
			: array();

		$result = Gift_Choice::process_customer_choice( $rule_id, $option_key, $options_map );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => (string) ( $result['message'] ?? '' ),
				'html'    => '',
				'empty'   => true,
			),
			200
		);
	}

	private static function ensure_cart_loaded(): void {
		if ( ! function_exists( 'WC' ) || ! WC() instanceof \WooCommerce ) {
			return;
		}

		if ( null === WC()->session ) {
			WC()->initialize_session();
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}
	}
}
