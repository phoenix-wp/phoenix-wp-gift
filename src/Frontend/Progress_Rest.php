<?php
/**
 * REST endpoint for live gift progress updates.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Serves progress HTML for the current cart session.
 */
final class Progress_Rest {

	public static function register_hooks(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			'phoenix-wp-gift/v1',
			'/progress',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_progress' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'rule'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'bar'   => array(
						'type'    => 'string',
						'default' => '1',
					),
					'class' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request REST request.
	 */
	public static function get_progress( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! phoenix_wp_gift_is_pro_active( 'progress_hint' ) ) {
			return new \WP_REST_Response(
				array(
					'html'  => '',
					'empty' => true,
				),
				200
			);
		}

		self::ensure_cart_loaded();

		$atts = array(
			'rule'  => (string) $request->get_param( 'rule' ),
			'bar'   => (string) $request->get_param( 'bar' ),
			'class' => sanitize_text_field( (string) $request->get_param( 'class' ) ),
		);

		$html = Progress_Shortcode::render_inner( $atts );

		return new \WP_REST_Response(
			array(
				'html'  => $html,
				'empty' => '' === $html,
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
