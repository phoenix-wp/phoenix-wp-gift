<?php
/**
 * Calculates progress toward the next gift threshold.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Frontend;

use PhoenixWP\Gift\Rules\Audience_Evaluator;
use PhoenixWP\Gift\Rules\Gift_Options_Helper;
use PhoenixWP\Gift\Rules\Cart_Content_Evaluator;
use PhoenixWP\Gift\Rules\Condition_Evaluator;
use PhoenixWP\Gift\Rules\Rules_Repository;
use PhoenixWP\Gift\Rules\Schedule_Evaluator;
use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the closest not-yet-unlocked gift rule for progress hints.
 */
final class Progress_Calculator {

	/**
	 * @param \WC_Cart|null $cart     Cart instance.
	 * @param string        $rule_id  Optional rule ID filter.
	 * @return array<string, mixed>|null
	 */
	public static function get_next_progress( ?\WC_Cart $cart = null, string $rule_id = '' ): ?array {
		if ( ! phoenix_wp_gift_is_pro_active( 'progress_hint' ) ) {
			return null;
		}

		if ( ! phoenix_wp_gift_has_active_configuration() ) {
			return null;
		}

		$cart = $cart ?? ( function_exists( 'WC' ) ? WC()->cart : null );

		if ( ! $cart instanceof \WC_Cart ) {
			return null;
		}

		$rule_id    = sanitize_key( $rule_id );
		$candidates = array();

		foreach ( Rules_Repository::instance()->get_runtime_rules() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$id = (string) ( $rule['id'] ?? '' );

			if ( '' !== $rule_id && $id !== $rule_id ) {
				continue;
			}

			if ( Condition_Evaluator::rule_matches( $rule, $cart ) ) {
				continue;
			}

			if ( ! Audience_Evaluator::rule_matches_audience( $rule ) ) {
				continue;
			}

			if ( ! Schedule_Evaluator::rule_matches_schedule( $rule ) ) {
				continue;
			}

			if ( ! Cart_Content_Evaluator::rule_matches_cart_content( $rule, $cart ) ) {
				continue;
			}

			$gap = self::calculate_trigger_gap( $rule, $cart );

			if ( null === $gap ) {
				continue;
			}

			$gap['rule']    = $rule;
			$gap['rule_id'] = $id;
			$candidates[]   = $gap;
		}

		if ( empty( $candidates ) ) {
			return null;
		}

		usort(
			$candidates,
			static function ( array $a, array $b ): int {
				$percent_a = (float) ( $a['percent'] ?? 0 );
				$percent_b = (float) ( $b['percent'] ?? 0 );

				if ( $percent_a === $percent_b ) {
					return (float) ( $a['gap'] ?? 0 ) <=> (float) ( $b['gap'] ?? 0 );
				}

				return $percent_b <=> $percent_a;
			}
		);

		return $candidates[0];
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 * @return array<string, mixed>|null
	 */
	private static function calculate_trigger_gap( array $rule, \WC_Cart $cart ): ?array {
		$gift_product_ids = self::get_gift_product_ids( $rule );
		$trigger          = sanitize_key( (string) ( $rule['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ) );

		if ( Settings::TRIGGER_ITEM_QUANTITY === $trigger ) {
			$minimum = max( 0, absint( $rule['min_item_quantity'] ?? 0 ) );
			$current = Condition_Evaluator::get_item_quantity_excluding_gifts( $cart, $gift_product_ids );

			if ( $minimum <= 0 ) {
				return $current > 0 ? null : array(
					'type'    => 'quantity',
					'gap'     => 1.0,
					'current' => 0.0,
					'target'  => 1.0,
					'percent' => 0.0,
				);
			}

			if ( $current >= $minimum ) {
				return null;
			}

			$gap = (float) ( $minimum - $current );

			return array(
				'type'    => 'quantity',
				'gap'     => $gap,
				'current' => (float) $current,
				'target'  => (float) $minimum,
				'percent' => min( 99.0, ( $current / $minimum ) * 100 ),
			);
		}

		$minimum = (float) ( $rule['min_subtotal'] ?? 0 );
		$current = Condition_Evaluator::get_subtotal_excluding_gifts( $cart, $gift_product_ids );

		if ( $minimum <= 0 ) {
			return $current > 0 ? null : array(
				'type'    => 'subtotal',
				'gap'     => 0.01,
				'current' => 0.0,
				'target'  => 0.0,
				'percent' => 0.0,
			);
		}

		if ( $current >= $minimum ) {
			return null;
		}

		$gap = max( 0.0, $minimum - $current );

		return array(
			'type'    => 'subtotal',
			'gap'     => $gap,
			'current' => $current,
			'target'  => $minimum,
			'percent' => min( 99.0, ( $current / $minimum ) * 100 ),
		);
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 * @return int[]
	 */
	private static function get_gift_product_ids( array $rule ): array {
		return Gift_Options_Helper::get_exclusion_ids_for_rule( $rule );
	}

	/**
	 * @param array<string, mixed> $progress Progress payload from get_next_progress().
	 */
	public static function get_gift_display_name( array $progress ): string {
		$rule = is_array( $progress['rule'] ?? null ) ? $progress['rule'] : array();
		$label = trim( (string) ( $rule['gift_label'] ?? '' ) );

		if ( '' !== $label ) {
			return $label;
		}

		$product_id = absint( $rule['product_id'] ?? 0 );
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : null;

		if ( $product instanceof \WC_Product ) {
			return $product->get_name();
		}

		return __( 'your free gift', 'phoenix-wp-gift' );
	}

	/**
	 * @param array<string, mixed> $progress Progress payload.
	 */
	public static function build_message( array $progress ): string {
		$gift_name = self::get_gift_display_name( $progress );
		$type      = sanitize_key( (string) ( $progress['type'] ?? 'subtotal' ) );

		if ( 'quantity' === $type ) {
			$gap = max( 1, (int) round( (float) ( $progress['gap'] ?? 1 ) ) );

			$message = sprintf(
				/* translators: 1: number of items still needed, 2: gift name */
				_n(
					'Add %1$d more item to receive %2$s.',
					'Add %1$d more items to receive %2$s.',
					$gap,
					'phoenix-wp-gift'
				),
				$gap,
				$gift_name
			);
		} else {
			$gap = (float) ( $progress['gap'] ?? 0 );

			$message = sprintf(
				/* translators: 1: formatted money amount still needed, 2: gift name */
				__( 'Add %1$s more to your cart to receive %2$s.', 'phoenix-wp-gift' ),
				wp_strip_all_tags( wc_price( $gap ) ),
				$gift_name
			);
		}

		/**
		 * Filters the progress hint message shown by the shortcode.
		 *
		 * @param string               $message  Default message.
		 * @param array<string, mixed> $progress Progress payload.
		 */
		return (string) apply_filters( 'phoenix_wp_gift_progress_message', $message, $progress );
	}
}
