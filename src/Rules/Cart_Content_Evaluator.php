<?php
/**
 * Evaluates required products, categories, and tags in the cart.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Additional cart content requirements (AND with subtotal / quantity triggers).
 */
final class Cart_Content_Evaluator {

	/**
	 * Whether the cart satisfies optional product / category / tag requirements.
	 *
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function rule_matches_cart_content( array $rule, \WC_Cart $cart ): bool {
		$product_ids  = self::sanitize_product_ids( $rule['require_product_ids'] ?? array() );
		$category_ids = self::sanitize_term_ids( $rule['require_category_ids'] ?? array(), 'product_cat' );
		$tag_ids      = self::sanitize_term_ids( $rule['require_tag_ids'] ?? array(), 'product_tag' );

		if ( empty( $product_ids ) && empty( $category_ids ) && empty( $tag_ids ) ) {
			return true;
		}

		$gift_product_ids = Rules_Repository::instance()->get_managed_product_ids();
		$rule_product     = absint( $rule['product_id'] ?? 0 );

		if ( $rule_product > 0 && ! in_array( $rule_product, $gift_product_ids, true ) ) {
			$gift_product_ids[] = $rule_product;
		}

		if ( ! empty( $product_ids ) && ! self::cart_contains_product( $cart, $product_ids, $gift_product_ids ) ) {
			return false;
		}

		if ( ! empty( $category_ids ) && ! self::cart_contains_terms( $cart, $category_ids, 'product_cat', $gift_product_ids ) ) {
			return false;
		}

		if ( ! empty( $tag_ids ) && ! self::cart_contains_terms( $cart, $tag_ids, 'product_tag', $gift_product_ids ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param mixed $raw Raw ID list.
	 * @return int[]
	 */
	public static function sanitize_product_ids( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();

		foreach ( $raw as $id ) {
			$product_id = absint( $id );

			if ( $product_id <= 0 ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( $product instanceof \WC_Product && $product->is_purchasable() ) {
				$ids[] = $product_id;
			}
		}

		sort( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param mixed  $raw      Raw ID list.
	 * @param string $taxonomy Product taxonomy.
	 * @return int[]
	 */
	public static function sanitize_term_ids( $raw, string $taxonomy ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();

		foreach ( $raw as $id ) {
			$term_id = absint( $id );

			if ( $term_id <= 0 ) {
				continue;
			}

			$term = get_term( $term_id, $taxonomy );

			if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
				$ids[] = $term_id;
			}
		}

		sort( $ids );

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function has_cart_content_restriction( array $rule ): bool {
		return ! empty( self::sanitize_product_ids( $rule['require_product_ids'] ?? array() ) )
			|| ! empty( self::sanitize_term_ids( $rule['require_category_ids'] ?? array(), 'product_cat' ) )
			|| ! empty( self::sanitize_term_ids( $rule['require_tag_ids'] ?? array(), 'product_tag' ) );
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function get_display_label( array $rule ): string {
		$parts = array();

		$product_ids = self::sanitize_product_ids( $rule['require_product_ids'] ?? array() );

		if ( ! empty( $product_ids ) ) {
			$names = array();

			foreach ( $product_ids as $product_id ) {
				$product = wc_get_product( $product_id );

				if ( $product instanceof \WC_Product ) {
					$names[] = $product->get_name();
				}
			}

			if ( ! empty( $names ) ) {
				$parts[] = sprintf(
					/* translators: %s: comma-separated product names */
					__( 'Product: %s', 'phoenix-wp-gift' ),
					implode( ', ', $names )
				);
			}
		}

		$category_label = self::get_term_names_label( $rule['require_category_ids'] ?? array(), 'product_cat', __( 'Category', 'phoenix-wp-gift' ), __( 'Categories', 'phoenix-wp-gift' ) );

		if ( '' !== $category_label ) {
			$parts[] = $category_label;
		}

		$tag_label = self::get_term_names_label( $rule['require_tag_ids'] ?? array(), 'product_tag', __( 'Tag', 'phoenix-wp-gift' ), __( 'Tags', 'phoenix-wp-gift' ) );

		if ( '' !== $tag_label ) {
			$parts[] = $tag_label;
		}

		return implode( ' + ', $parts );
	}

	/**
	 * @param mixed  $raw           Raw term IDs.
	 * @param string $taxonomy      Taxonomy name.
	 * @param string $singular_label Singular label.
	 * @param string $plural_label   Plural label.
	 */
	private static function get_term_names_label( $raw, string $taxonomy, string $singular_label, string $plural_label ): string {
		$term_ids = self::sanitize_term_ids( $raw, $taxonomy );

		if ( empty( $term_ids ) ) {
			return '';
		}

		$names = array();

		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );

			if ( $term instanceof \WP_Term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}

		if ( empty( $names ) ) {
			return '';
		}

		$label = 1 === count( $names ) ? $singular_label : $plural_label;

		return sprintf(
			/* translators: 1: Category or tag label, 2: comma-separated term names */
			__( '%1$s: %2$s', 'phoenix-wp-gift' ),
			$label,
			implode( ', ', $names )
		);
	}

	/**
	 * @param int[] $required_ids     Required product IDs.
	 * @param int[] $gift_product_ids Gift lines to ignore.
	 */
	private static function cart_contains_product( \WC_Cart $cart, array $required_ids, array $gift_product_ids ): bool {
		foreach ( $cart->get_cart() as $item ) {
			if ( Condition_Evaluator::line_is_gift_product( $item, $gift_product_ids ) ) {
				continue;
			}

			$line_ids = self::get_line_product_ids( $item );

			foreach ( $required_ids as $required_id ) {
				if ( in_array( $required_id, $line_ids, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @param int[]  $required_term_ids Required term IDs.
	 * @param string $taxonomy          Taxonomy name.
	 * @param int[]  $gift_product_ids  Gift lines to ignore.
	 */
	private static function cart_contains_terms( \WC_Cart $cart, array $required_term_ids, string $taxonomy, array $gift_product_ids ): bool {
		foreach ( $cart->get_cart() as $item ) {
			if ( Condition_Evaluator::line_is_gift_product( $item, $gift_product_ids ) ) {
				continue;
			}

			$line_ids = self::get_line_product_ids( $item );

			foreach ( $line_ids as $line_id ) {
				if ( has_term( $required_term_ids, $taxonomy, $line_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Parent and variation IDs for a cart line.
	 *
	 * @param array<string, mixed> $item Cart line.
	 * @return int[]
	 */
	private static function get_line_product_ids( array $item ): array {
		$product_id   = absint( $item['product_id'] ?? 0 );
		$variation_id = absint( $item['variation_id'] ?? 0 );
		$ids          = array();

		if ( $product_id > 0 ) {
			$ids[] = $product_id;
		}

		if ( $variation_id > 0 ) {
			$ids[] = $variation_id;
		}

		return array_values( array_unique( $ids ) );
	}
}
