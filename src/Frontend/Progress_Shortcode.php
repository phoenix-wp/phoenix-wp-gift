<?php

/**

 * Shortcode for gift progress hints.

 *

 * @package PhoenixWP\Gift

 */



declare(strict_types=1);



namespace PhoenixWP\Gift\Frontend;



defined( 'ABSPATH' ) || exit;



/**

 * Renders [phoenix_wp_gift_progress].

 */

final class Progress_Shortcode {



	public static function register_hooks(): void {

		add_shortcode( 'phoenix_wp_gift_progress', array( self::class, 'render' ) );

		Progress_Rest::register_hooks();

		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_assets' ), 25 );

	}



	/**

	 * @param array<string, string>|string $atts Shortcode attributes.

	 */

	public static function render( $atts = array() ): string {

		if ( ! phoenix_wp_gift_is_pro_active( 'progress_hint' ) ) {

			return '';

		}



		return self::render_root( self::normalize_atts( $atts ) );

	}



	/**

	 * @param array<string, string> $atts Shortcode attributes.

	 */

	public static function render_root( array $atts ): string {

		$atts  = self::normalize_atts( $atts );

		$inner = self::render_inner( $atts );



		$root_classes = array( 'phoenix-wp-gift-progress-root' );

		if ( '' === $inner ) {
			$root_classes[] = 'phoenix-wp-gift-progress-root--empty';
		}

		ob_start();

		echo '<div class="' . esc_attr( implode( ' ', $root_classes ) ) . '"';

		echo ' data-rule="' . esc_attr( sanitize_key( (string) ( $atts['rule'] ?? '' ) ) ) . '"';

		echo ' data-bar="' . esc_attr( (string) ( $atts['bar'] ?? '1' ) ) . '"';

		echo ' data-class="' . esc_attr( sanitize_html_class( (string) ( $atts['class'] ?? '' ) ) ) . '"';

		echo '>';

		echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_inner().

		echo '</div>';



		return (string) ob_get_clean();

	}



	/**

	 * @param array<string, string> $atts Shortcode attributes.

	 */

	public static function render_inner( array $atts ): string {

		if ( ! phoenix_wp_gift_is_pro_active( 'progress_hint' ) ) {

			return '';

		}



		$atts = self::normalize_atts( $atts );



		$progress = Progress_Calculator::get_next_progress(

			null,

			sanitize_key( (string) ( $atts['rule'] ?? '' ) )

		);



		if ( null === $progress ) {

			return '';

		}



		$html = self::build_html( $progress, $atts );



		/**

		 * Filters the full progress hint HTML.

		 *

		 * @param string               $html     Rendered markup.

		 * @param array<string, mixed> $progress Progress payload.

		 * @param array<string, string> $atts    Shortcode attributes.

		 */

		return (string) apply_filters( 'phoenix_wp_gift_progress_html', $html, $progress, $atts );

	}



	/**

	 * @param array<string, string>|string $atts Shortcode attributes.

	 * @return array<string, string>

	 */

	private static function normalize_atts( $atts ): array {

		return shortcode_atts(

			array(

				'rule'  => '',

				'bar'   => '1',

				'class' => '',

			),

			$atts,

			'phoenix_wp_gift_progress'

		);

	}



	/**

	 * @param array<string, mixed>  $progress Progress payload.

	 * @param array<string, string> $atts     Shortcode attributes.

	 */

	private static function build_html( array $progress, array $atts ): string {

		$message   = Progress_Calculator::build_message( $progress );

		$percent   = max( 0, min( 100, (int) round( (float) ( $progress['percent'] ?? 0 ) ) ) );

		$rule_id   = sanitize_key( (string) ( $progress['rule_id'] ?? '' ) );

		$classes   = array( 'phoenix-wp-gift-progress' );

		$extra     = sanitize_html_class( (string) ( $atts['class'] ?? '' ) );

		$show_bar  = ! in_array( strtolower( (string) ( $atts['bar'] ?? '1' ) ), array( '0', 'false', 'no' ), true );



		if ( '' !== $extra ) {

			$classes[] = $extra;

		}



		ob_start();

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"';



		if ( '' !== $rule_id ) {

			echo ' data-rule-id="' . esc_attr( $rule_id ) . '"';

		}



		echo '>';

		echo '<p class="phoenix-wp-gift-progress__text">' . esc_html( $message ) . '</p>';



		if ( $show_bar ) {

			printf(

				'<div class="phoenix-wp-gift-progress__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%1$d" aria-label="%2$s"><span class="phoenix-wp-gift-progress__bar-fill" style="width:%1$d%%"></span></div>',

				$percent,

				esc_attr( $message )

			);

		}



		echo '</div>';



		return (string) ob_get_clean();

	}



	public static function maybe_enqueue_assets(): void {

		if ( ! phoenix_wp_gift_is_pro_active( 'progress_hint' ) ) {

			return;

		}



		if ( ! phoenix_wp_gift_has_active_configuration() ) {

			return;

		}



		if ( ! self::should_enqueue_progress_script() ) {

			return;

		}



		wp_enqueue_script(

			'phoenix-wp-gift-progress',

			PHOENIX_WP_GIFT_URL . 'assets/js/gift-progress.js',

			array( 'jquery' ),

			PHOENIX_WP_GIFT_VERSION,

			true

		);



		wp_localize_script(

			'phoenix-wp-gift-progress',

			'phoenixWpGiftProgress',

			array(

				'restUrl' => rest_url( 'phoenix-wp-gift/v1/progress' ),

				'nonce'   => wp_create_nonce( 'wp_rest' ),

			)

		);

	}



	private static function should_enqueue_progress_script(): bool {

		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {

			return true;

		}



		global $post;



		if ( $post instanceof \WP_Post && has_shortcode( $post->post_content, 'phoenix_wp_gift_progress' ) ) {

			return true;

		}



		/**

		 * Whether to enqueue the live progress refresh script.

		 *

		 * @param bool $enqueue Default false when shortcode is not on the current page.

		 */

		return (bool) apply_filters( 'phoenix_wp_gift_enqueue_progress_script', false );

	}

}

