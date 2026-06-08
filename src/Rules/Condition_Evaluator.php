<?php
/**
 * Evaluates whether a gift rule matches the current cart.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Cart condition checks for gift rules.
 */
final class Condition_Evaluator {

	/**
	 * Whether the cart satisfies a rule's trigger conditions.
	 *
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function rule_matches( array $rule, \WC_Cart $cart ): bool {
		$product_id = absint( $rule['product_id'] ?? 0 );

		if ( $product_id <= 0 ) {
			return false;
		}

		if ( ! Audience_Evaluator::rule_matches_audience( $rule ) ) {
			return false;
		}

		if ( ! Schedule_Evaluator::rule_matches_schedule( $rule ) ) {
			return false;
		}

		return self::rule_matches_cart_trigger( $rule, $cart );
	}

	/**
	 * Cart trigger only (subtotal or item quantity), without schedule checks.
	 *
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function rule_matches_cart_trigger( array $rule, \WC_Cart $cart ): bool {
		$product_id = absint( $rule['product_id'] ?? 0 );

		if ( $product_id <= 0 ) {
			return false;
		}

		if ( ! Cart_Content_Evaluator::rule_matches_cart_content( $rule, $cart ) ) {
			return false;
		}

		$trigger = sanitize_key( (string) ( $rule['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ) );

		if ( Settings::TRIGGER_ITEM_QUANTITY === $trigger ) {
			return self::cart_meets_item_quantity( $rule, $cart, $product_id );
		}

		return self::cart_meets_subtotal( $rule, $cart, $product_id );
	}

	/**
	 * @param array<string, mixed> $rule      Rule data.
	 * @param \WC_Cart             $cart      Cart instance.
	 * @param int                  $gift_id   Gift product ID for this rule.
	 */
	private static function cart_meets_subtotal( array $rule, \WC_Cart $cart, int $gift_id ): bool {
		$minimum = (float) ( $rule['min_subtotal'] ?? 0 );

		if ( $minimum <= 0 ) {
			return ! self::cart_is_empty_except_gifts( $cart, self::get_managed_product_ids( $rule ) );
		}

		return self::get_subtotal_excluding_gifts( $cart, self::get_managed_product_ids( $rule ) ) >= $minimum;
	}

	/**
	 * @param array<string, mixed> $rule      Rule data.
	 * @param \WC_Cart             $cart      Cart instance.
	 * @param int                  $gift_id   Gift product ID for this rule.
	 */
	private static function cart_meets_item_quantity( array $rule, \WC_Cart $cart, int $gift_id ): bool {
		$minimum = max( 0, absint( $rule['min_item_quantity'] ?? 0 ) );
		$count   = self::get_item_quantity_excluding_gifts( $cart, self::get_managed_product_ids( $rule ) );

		if ( $minimum <= 0 ) {
			return $count > 0;
		}

		return $count >= $minimum;
	}

	/**
	 * Product IDs treated as gifts when excluding from trigger calculations.
	 *
	 * @param array<string, mixed> $rule Current rule.
	 * @return int[]
	 */
	private static function get_managed_product_ids( array $rule ): array {
		$ids = Rules_Repository::instance()->get_managed_product_ids();

		$rule_product = absint( $rule['product_id'] ?? 0 );

		if ( $rule_product > 0 && ! in_array( $rule_product, $ids, true ) ) {
			$ids[] = $rule_product;
		}

		return $ids;
	}

	/**
	 * @param int[] $gift_product_ids Gift product IDs to exclude.
	 */
	public static function get_item_quantity_excluding_gifts( \WC_Cart $cart, array $gift_product_ids ): int {
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
	public static function get_subtotal_excluding_gifts( \WC_Cart $cart, array $gift_product_ids ): float {
		$total = 0.0;

		foreach ( $cart->get_cart() as $item ) {
			if ( self::line_is_gift_product( $item, $gift_product_ids ) ) {
				continue;
			}

			$total += self::get_line_gross_subtotal( $item );
		}

		return $total;
	}

	/**
	 * Gross line subtotal from live product data (safe during calculate_totals).
	 *
	 * @param array<string, mixed> $item Cart line.
	 */
	public static function get_line_gross_subtotal( array $item ): float {
		$line_subtotal = (float) ( $item['line_subtotal'] ?? 0 );
		$line_tax      = (float) ( $item['line_subtotal_tax'] ?? 0 );

		if ( $line_subtotal > 0 || $line_tax > 0 ) {
			return $line_subtotal + $line_tax;
		}

		$product = $item['data'] ?? null;

		if ( ! $product instanceof \WC_Product ) {
			return 0.0;
		}

		$qty = max( 0, absint( $item['quantity'] ?? 0 ) );

		if ( $qty <= 0 ) {
			return 0.0;
		}

		return (float) wc_get_price_including_tax( $product, array( 'qty' => $qty ) );
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
	 * @param array<string, mixed> $item             Cart line.
	 * @param int[]                $gift_product_ids Managed gift product IDs.
	 */
	public static function line_is_gift_product( array $item, array $gift_product_ids ): bool {
		if ( ! empty( $item[ \PhoenixWP\Gift\Cart\Gift_Handler::CART_FLAG ] ) ) {
			return true;
		}

		$line_id = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );

		return in_array( $line_id, $gift_product_ids, true )
			|| in_array( absint( $item['product_id'] ?? 0 ), $gift_product_ids, true );
	}
}
