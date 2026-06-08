<?php
/**
 * Free-tier cart trigger checks (subtotal or item quantity).
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates whether the free gift rule matches the current cart.
 */
final class Free_Rule_Evaluator {

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function rule_matches_cart( array $rule, \WC_Cart $cart ): bool {
		$product_id = absint( $rule['product_id'] ?? 0 );

		if ( $product_id <= 0 || empty( $rule['enabled'] ) ) {
			return false;
		}

		$gift_ids = Rules_Repository::instance()->get_managed_product_ids();
		$trigger  = sanitize_key( (string) ( $rule['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ) );

		if ( Settings::TRIGGER_ITEM_QUANTITY === $trigger ) {
			return self::cart_meets_item_quantity( $rule, $cart, $gift_ids );
		}

		return self::cart_meets_subtotal( $rule, $cart, $gift_ids );
	}

	/**
	 * @param int[] $gift_product_ids Gift product IDs to exclude from trigger math.
	 */
	private static function cart_meets_subtotal( array $rule, \WC_Cart $cart, array $gift_product_ids ): bool {
		$minimum = (float) ( $rule['min_subtotal'] ?? 0 );

		if ( $minimum <= 0 ) {
			return ! self::cart_is_empty_except_gifts( $cart, $gift_product_ids );
		}

		return self::get_subtotal_excluding_gifts( $cart, $gift_product_ids ) >= $minimum;
	}

	/**
	 * @param int[] $gift_product_ids Gift product IDs to exclude from trigger math.
	 */
	private static function cart_meets_item_quantity( array $rule, \WC_Cart $cart, array $gift_product_ids ): bool {
		$minimum = max( 0, absint( $rule['min_item_quantity'] ?? 0 ) );
		$count   = self::get_item_quantity_excluding_gifts( $cart, $gift_product_ids );

		if ( $minimum <= 0 ) {
			return $count > 0;
		}

		return $count >= $minimum;
	}

	/**
	 * @param int[] $gift_product_ids Gift product IDs to exclude.
	 */
	private static function get_item_quantity_excluding_gifts( \WC_Cart $cart, array $gift_product_ids ): int {
		$total = 0;

		foreach ( $cart->get_cart() as $item ) {
			if ( self::line_is_gift_product( $item, $gift_product_ids ) ) {
				continue;
			}

			$total += max( 0, absint( $item['quantity'] ?? 0 ) );
		}

		return $total;
	}

	/**
	 * @param int[] $gift_product_ids Gift product IDs to exclude.
	 */
	private static function get_subtotal_excluding_gifts( \WC_Cart $cart, array $gift_product_ids ): float {
		$total = 0.0;

		foreach ( $cart->get_cart() as $item ) {
			if ( self::line_is_gift_product( $item, $gift_product_ids ) ) {
				continue;
			}

			$line_subtotal = (float) ( $item['line_subtotal'] ?? 0 );
			$line_tax      = (float) ( $item['line_subtotal_tax'] ?? 0 );

			if ( $line_subtotal > 0 || $line_tax > 0 ) {
				$total += $line_subtotal + $line_tax;
				continue;
			}

			$product = $item['data'] ?? null;

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$qty = max( 0, absint( $item['quantity'] ?? 0 ) );

			if ( $qty <= 0 ) {
				continue;
			}

			$total += (float) wc_get_price_including_tax( $product, array( 'qty' => $qty ) );
		}

		return $total;
	}

	/**
	 * @param int[] $gift_product_ids Gift product IDs to exclude.
	 */
	private static function cart_is_empty_except_gifts( \WC_Cart $cart, array $gift_product_ids ): bool {
		foreach ( $cart->get_cart() as $item ) {
			if ( ! self::line_is_gift_product( $item, $gift_product_ids ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param int[] $gift_product_ids Gift product IDs.
	 */
	private static function line_is_gift_product( array $item, array $gift_product_ids ): bool {
		$line_id = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );

		return $line_id > 0 && in_array( $line_id, $gift_product_ids, true );
	}
}
