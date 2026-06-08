<?php
/**
 * Aggregates gift redemption counts from orders.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Stats;

use PhoenixWP\Gift\Cart\Gift_Handler;
use PhoenixWP\Gift\Rules\Rules_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Counts gift line items in paid orders for the last 30 and 90 days.
 */
final class Gift_Stats {

	/**
	 * @return array<int, string>
	 */
	private function get_paid_statuses(): array {
		if ( function_exists( 'wc_get_is_paid_statuses' ) ) {
			return wc_get_is_paid_statuses();
		}

		return array( 'processing', 'completed' );
	}

	/**
	 * @return array{
	 *   rows: array<int, array{rule_id: string, label: string, count_30: int, count_90: int}>,
	 *   total_30: int,
	 *   total_90: int
	 * }
	 */
	public function get_summary(): array {
		$counts_30 = $this->count_gifts_for_days( 30 );
		$counts_90 = $this->count_gifts_for_days( 90 );
		$rule_ids  = array_unique( array_merge( array_keys( $counts_30 ), array_keys( $counts_90 ) ) );
		$rows      = array();

		foreach ( $rule_ids as $rule_id ) {
			$rows[] = array(
				'rule_id'  => $rule_id,
				'label'    => $this->resolve_rule_label( $rule_id ),
				'count_30' => (int) ( $counts_30[ $rule_id ] ?? 0 ),
				'count_90' => (int) ( $counts_90[ $rule_id ] ?? 0 ),
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				if ( $a['count_90'] === $b['count_90'] ) {
					return strcmp( $a['label'], $b['label'] );
				}

				return $b['count_90'] <=> $a['count_90'];
			}
		);

		return array(
			'rows'     => $rows,
			'total_30' => array_sum( $counts_30 ),
			'total_90' => array_sum( $counts_90 ),
		);
	}

	/**
	 * @return array<string, int>
	 */
	private function count_gifts_for_days( int $days ): array {
		$days   = max( 1, $days );
		$counts = array();
		$after  = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$page   = 1;

		do {
			$orders = wc_get_orders(
				array(
					'status'       => $this->get_paid_statuses(),
					'date_created' => '>' . $after,
					'limit'        => 100,
					'page'         => $page,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'return'       => 'objects',
				)
			);

			foreach ( $orders as $order ) {
				if ( ! $order instanceof \WC_Order ) {
					continue;
				}

				foreach ( $order->get_items() as $item ) {
					if ( ! $item instanceof \WC_Order_Item_Product ) {
						continue;
					}

					if ( '1' !== (string) $item->get_meta( Gift_Handler::CART_FLAG ) ) {
						continue;
					}

					$rule_id = sanitize_key( (string) $item->get_meta( Gift_Handler::CART_RULE_ID ) );

					if ( '' === $rule_id ) {
						$rule_id = '_product_' . absint( $item->get_product_id() );
					}

					$counts[ $rule_id ] = (int) ( $counts[ $rule_id ] ?? 0 ) + 1;
				}
			}

			++$page;
		} while ( count( $orders ) === 100 );

		return $counts;
	}

	private function resolve_rule_label( string $rule_id ): string {
		if ( str_starts_with( $rule_id, '_product_' ) ) {
			$product_id = absint( substr( $rule_id, 9 ) );
			$product    = $product_id > 0 ? wc_get_product( $product_id ) : null;

			if ( $product instanceof \WC_Product ) {
				return sprintf(
					/* translators: %s: product name */
					__( 'Legacy gift (%s)', 'phoenix-wp-gift' ),
					$product->get_name()
				);
			}

			return __( 'Legacy gift (unknown product)', 'phoenix-wp-gift' );
		}

		$rule = Rules_Repository::instance()->get( $rule_id );

		if ( is_array( $rule ) && '' !== trim( (string) ( $rule['name'] ?? '' ) ) ) {
			return (string) $rule['name'];
		}

		return $rule_id;
	}
}
