<?php
/**
 * Gift product options per rule (simple, variation, customer choice).
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes and resolves gift SKUs configured on a rule.
 */
final class Gift_Options_Helper {

	public const SELECTION_AUTO     = 'auto';
	public const SELECTION_CUSTOMER = 'customer';

	/**
	 * @param string $selection Raw selection mode.
	 */
	public static function normalize_selection( string $selection ): string {
		$selection = sanitize_key( $selection );

		return self::SELECTION_CUSTOMER === $selection ? self::SELECTION_CUSTOMER : self::SELECTION_AUTO;
	}

	/**
	 * @param mixed $raw Raw gift options from import or form.
	 * @return array<int, array{product_id: int, variation_id: int}>
	 */
	public static function sanitize_options( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$options = array();
		$seen    = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$product_id   = absint( $row['product_id'] ?? 0 );
			$variation_id = absint( $row['variation_id'] ?? 0 );

			if ( $product_id <= 0 ) {
				continue;
			}

			$key = self::option_key(
				array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
				)
			);

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$options[]    = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			);
		}

		return $options;
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 * @return array<int, array{product_id: int, variation_id: int}>
	 */
	public static function get_options( array $rule ): array {
		$options = self::sanitize_options( $rule['gift_options'] ?? array() );

		if ( ! empty( $options ) ) {
			return $options;
		}

		$product_id = absint( $rule['product_id'] ?? 0 );

		if ( $product_id <= 0 ) {
			return array();
		}

		return array(
			array(
				'product_id'   => $product_id,
				'variation_id' => absint( $rule['variation_id'] ?? 0 ),
			),
		);
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 */
	public static function requires_customer_choice( array $rule ): bool {
		return self::SELECTION_CUSTOMER === self::normalize_selection( (string) ( $rule['gift_selection'] ?? self::SELECTION_AUTO ) );
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 * @return array{product_id: int, variation_id: int}|null
	 */
	public static function get_auto_option( array $rule ): ?array {
		$options = self::get_options( $rule );

		return $options[0] ?? null;
	}

	/**
	 * @param array{product_id: int, variation_id: int} $option Gift option.
	 */
	public static function option_key( array $option ): string {
		$product_id   = absint( $option['product_id'] ?? 0 );
		$variation_id = absint( $option['variation_id'] ?? 0 );

		if ( $variation_id > 0 ) {
			return $product_id . ':' . $variation_id;
		}

		return (string) $product_id;
	}

	/**
	 * @param array{product_id: int, variation_id: int} $option Gift option.
	 * @param bool                                      $strict Require variation for variable parents.
	 */
	public static function option_is_purchasable( array $option, bool $strict = false ): bool {
		$product_id   = absint( $option['product_id'] ?? 0 );
		$variation_id = absint( $option['variation_id'] ?? 0 );

		if ( $product_id <= 0 ) {
			return false;
		}

		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );

			return $variation instanceof \WC_Product_Variation
				&& $variation->get_parent_id() === $product_id
				&& $variation->is_purchasable();
		}

		$product = wc_get_product( $product_id );

		if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() ) {
			return false;
		}

		if ( $strict && $product->is_type( 'variable' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 */
	public static function rule_has_purchasable_option( array $rule ): bool {
		foreach ( self::get_options( $rule ) as $option ) {
			if ( self::option_is_purchasable( $option ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 * @return int[]
	 */
	public static function get_line_ids_for_rule( array $rule ): array {
		$ids = array();

		foreach ( self::get_options( $rule ) as $option ) {
			$variation_id = absint( $option['variation_id'] ?? 0 );
			$product_id   = absint( $option['product_id'] ?? 0 );

			if ( $variation_id > 0 ) {
				$ids[] = $variation_id;
			}

			if ( $product_id > 0 ) {
				$ids[] = $product_id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 * @return int[]
	 */
	public static function get_exclusion_ids_for_rule( array $rule ): array {
		return self::get_line_ids_for_rule( $rule );
	}

	/**
	 * @param array<string, mixed>              $cart_item Cart line.
	 * @param array{product_id: int, variation_id: int} $option    Gift option.
	 */
	public static function line_matches_option( array $cart_item, array $option ): bool {
		$variation_id = absint( $option['variation_id'] ?? 0 );
		$product_id   = absint( $option['product_id'] ?? 0 );
		$line_variation = absint( $cart_item['variation_id'] ?? 0 );
		$line_product   = absint( $cart_item['product_id'] ?? 0 );

		if ( $variation_id > 0 ) {
			return $line_variation === $variation_id;
		}

		if ( $line_variation > 0 ) {
			return false;
		}

		return $line_product === $product_id;
	}

	/**
	 * @param array<string, mixed> $cart_item Cart line.
	 * @param array<string, mixed> $rule      Rule payload.
	 */
	public static function line_matches_rule( array $cart_item, array $rule ): bool {
		foreach ( self::get_options( $rule ) as $option ) {
			if ( self::line_matches_option( $cart_item, $option ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $rule         Rule payload.
	 * @param int                  $product_id   Parent or simple product ID.
	 * @param int                  $variation_id Variation ID.
	 * @return array{product_id: int, variation_id: int}|null
	 */
	public static function find_option( array $rule, int $product_id, int $variation_id = 0 ): ?array {
		foreach ( self::get_options( $rule ) as $option ) {
			if ( self::line_matches_option(
				array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
				),
				$option
			) ) {
				return $option;
			}
		}

		return null;
	}

	/**
	 * @param array{product_id: int, variation_id: int} $option Gift option.
	 * @return array{product_id: int, quantity: int, variation_id: int, variation: array<string, string>, cart_item_data: array<string, mixed>}
	 */
	public static function get_add_to_cart_args( array $option ): array {
		$product_id   = absint( $option['product_id'] ?? 0 );
		$variation_id = absint( $option['variation_id'] ?? 0 );
		$variation    = array();

		if ( $variation_id > 0 ) {
			$variation_product = wc_get_product( $variation_id );

			if ( $variation_product instanceof \WC_Product_Variation ) {
				$variation = $variation_product->get_attributes();
			}
		}

		return array(
			'product_id'      => $product_id,
			'quantity'        => 1,
			'variation_id'    => $variation_id,
			'variation'       => $variation,
			'cart_item_data'  => array(),
		);
	}

	/**
	 * @param array{product_id: int, variation_id: int} $option Gift option.
	 */
	public static function get_display_name( array $option ): string {
		$variation_id = absint( $option['variation_id'] ?? 0 );
		$product_id   = absint( $option['product_id'] ?? 0 );

		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );

			if ( $variation instanceof \WC_Product ) {
				return wp_strip_all_tags( $variation->get_name() );
			}
		}

		$product = wc_get_product( $product_id );

		if ( $product instanceof \WC_Product ) {
			return wp_strip_all_tags( $product->get_name() );
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $rule Rule payload.
	 */
	public static function validate_for_save( array $rule ): ?string {
		$options = self::get_options( $rule );

		if ( empty( $options ) ) {
			return __( 'Add at least one gift product option.', 'phoenix-wp-gift' );
		}

		if ( self::requires_customer_choice( $rule ) && count( $options ) < 2 ) {
			return __( 'Customer choice requires at least two gift options.', 'phoenix-wp-gift' );
		}

		$valid_count = 0;

		foreach ( $options as $option ) {
			if ( ! self::option_is_purchasable( $option, true ) ) {
				continue;
			}

			++$valid_count;
		}

		if ( $valid_count <= 0 ) {
			return __( 'Each gift option must be a purchasable simple product or a specific variation.', 'phoenix-wp-gift' );
		}

		return null;
	}
}
