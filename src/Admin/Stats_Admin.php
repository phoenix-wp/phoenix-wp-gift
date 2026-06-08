<?php
/**
 * Gift redemption statistics admin section.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Admin;

use PhoenixWP\Gift\Stats\Gift_Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Renders basic gift stats for paid orders.
 */
final class Stats_Admin {

	public static function render_section(): void {
		if ( ! phoenix_wp_gift_is_pro_active( 'stats' ) ) {
			return;
		}

		$summary = ( new Gift_Stats() )->get_summary();

		echo '<hr /><h2>' . esc_html__( 'Statistics', 'phoenix-wp-gift' ) . '</h2>';
		echo '<p class="description">';
		echo esc_html__(
			'Gift redemptions in paid orders. Counts per rule are available for new orders after this update; older orders may appear as legacy gifts.',
			'phoenix-wp-gift'
		);
		echo '</p>';

		if ( empty( $summary['rows'] ) ) {
			echo '<p><em>' . esc_html__( 'No gift redemptions in the last 90 days.', 'phoenix-wp-gift' ) . '</em></p>';
			return;
		}

		echo '<table class="widefat striped phoenix-wp-gift-stats-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Rule', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Last 30 days', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Last 90 days', 'phoenix-wp-gift' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $summary['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $row['label'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) (int) ( $row['count_30'] ?? 0 ) ) . '</td>';
			echo '<td>' . esc_html( (string) (int) ( $row['count_90'] ?? 0 ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody><tfoot><tr>';
		echo '<th>' . esc_html__( 'Total', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html( (string) (int) ( $summary['total_30'] ?? 0 ) ) . '</th>';
		echo '<th>' . esc_html( (string) (int) ( $summary['total_90'] ?? 0 ) ) . '</th>';
		echo '</tr></tfoot></table>';
	}
}
