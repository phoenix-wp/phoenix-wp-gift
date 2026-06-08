<?php
/**
 * Resolves which matching rules actually apply (stack vs upgrade groups).
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Picks one winner per upgrade group; groups stack with each other.
 */
final class Rule_Resolver {

	/**
	 * Filters raw matches to the gifts that should be in the cart.
	 *
	 * Rules sharing an upgrade group always compete (highest threshold wins).
	 * Rules in different groups may stack.
	 *
	 * @param array<string, array<string, mixed>> $matching_rules Rules that match the cart, keyed by ID.
	 * @return array<string, array<string, mixed>>
	 */
	public static function resolve_applicable_rules( array $matching_rules ): array {
		if ( empty( $matching_rules ) ) {
			return array();
		}

		$groups = array();

		foreach ( $matching_rules as $rule_id => $rule ) {
			$group = self::get_upgrade_group( $rule );

			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array();
			}

			$groups[ $group ][ $rule_id ] = $rule;
		}

		$resolved = array();

		foreach ( $groups as $rules ) {
			if ( 1 === count( $rules ) ) {
				$rule = reset( $rules );
				$resolved[ (string) ( $rule['id'] ?? key( $rules ) ) ] = $rule;
				continue;
			}

			$upgrade_rules = array_filter(
				$rules,
				static fn( array $rule ): bool => self::is_upgrade_rule( $rule )
			);

			// When an Upgrade rule matches, Additional rules in the same group are replaced.
			$candidates = ! empty( $upgrade_rules ) ? $upgrade_rules : $rules;
			$best       = self::pick_best_upgrade_rule( $candidates );

			if ( null !== $best ) {
				$resolved[ (string) $best['id'] ] = $best;
			}
		}

		return $resolved;
	}

	/**
	 * Rules that matched the cart but were dropped by upgrade-group resolution.
	 *
	 * @param array<string, array<string, mixed>> $raw_matching  All matching rules.
	 * @param array<string, array<string, mixed>> $applicable    Rules that should stay in the cart.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_suppressed_rules( array $raw_matching, array $applicable ): array {
		$suppressed = array();

		foreach ( $raw_matching as $rule_id => $rule ) {
			if ( ! isset( $applicable[ $rule_id ] ) ) {
				$suppressed[ $rule_id ] = $rule;
			}
		}

		return $suppressed;
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function is_upgrade_rule( array $rule ): bool {
		return Rules_Repository::COMBINE_UPGRADE === self::get_combine_mode( $rule );
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function get_combine_mode( array $rule ): string {
		$mode = sanitize_key( (string) ( $rule['combine_mode'] ?? Rules_Repository::COMBINE_ADDITIONAL ) );

		if ( ! in_array( $mode, array( Rules_Repository::COMBINE_ADDITIONAL, Rules_Repository::COMBINE_UPGRADE ), true ) ) {
			return Rules_Repository::COMBINE_ADDITIONAL;
		}

		return $mode;
	}

	/**
	 * Product IDs that must not stay in the cart after upgrade resolution.
	 *
	 * @param array<string, array<string, mixed>> $raw_matching All matching rules.
	 * @param array<string, array<string, mixed>> $applicable  Applicable rules.
	 * @return int[]
	 */
	public static function get_loser_product_ids( array $raw_matching, array $applicable ): array {
		$losers  = array();
		$winners = self::get_upgrade_winners_by_group( $applicable );

		foreach ( $winners as $group => $winner ) {
			$winner_id       = (string) ( $winner['id'] ?? '' );
			$winner_line_ids = Gift_Options_Helper::get_line_ids_for_rule( $winner );

			foreach ( $raw_matching as $rule_id => $rule ) {
				if ( $rule_id === $winner_id || self::get_upgrade_group( $rule ) !== $group ) {
					continue;
				}

				foreach ( Gift_Options_Helper::get_line_ids_for_rule( $rule ) as $line_id ) {
					if ( ! in_array( $line_id, $winner_line_ids, true ) ) {
						$losers[] = $line_id;
					}
				}
			}

			foreach ( Rules_Repository::instance()->get_all() as $rule_id => $rule ) {
				if ( $rule_id === $winner_id || empty( $rule['enabled'] ) || self::get_upgrade_group( $rule ) !== $group ) {
					continue;
				}

				foreach ( Gift_Options_Helper::get_line_ids_for_rule( $rule ) as $line_id ) {
					if ( ! in_array( $line_id, $winner_line_ids, true ) ) {
						$losers[] = $line_id;
					}
				}
			}
		}

		return array_values( array_unique( $losers ) );
	}

	/**
	 * @param array<string, array<string, mixed>> $applicable Applicable rules.
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_upgrade_winners_by_group( array $applicable ): array {
		$winners = array();

		foreach ( $applicable as $rule ) {
			$group = self::get_upgrade_group( $rule );

			$winners[ $group ] = $rule;
		}

		return $winners;
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function get_upgrade_group( array $rule ): string {
		$group = sanitize_key( (string) ( $rule['upgrade_group'] ?? 'default' ) );

		return '' !== $group ? $group : 'default';
	}

	/**
	 * Highest threshold wins; equal thresholds favor lower priority number.
	 *
	 * @param array<string, array<string, mixed>> $rules Rules in one upgrade group.
	 * @return array<string, mixed>|null
	 */
	private static function pick_best_upgrade_rule( array $rules ): ?array {
		$best       = null;
		$best_score = -1.0;
		$best_prio  = PHP_INT_MAX;

		foreach ( $rules as $rule ) {
			$score    = self::rule_threshold_score( $rule );
			$priority = absint( $rule['priority'] ?? 10 );

			if (
				$score > $best_score
				|| ( $score === $best_score && $priority < $best_prio )
			) {
				$best       = $rule;
				$best_score = $score;
				$best_prio  = $priority;
			}
		}

		return $best;
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function rule_threshold_score( array $rule ): float {
		$trigger = sanitize_key( (string) ( $rule['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ) );

		if ( Settings::TRIGGER_ITEM_QUANTITY === $trigger ) {
			return (float) max( 0, absint( $rule['min_item_quantity'] ?? 0 ) );
		}

		return (float) ( $rule['min_subtotal'] ?? 0 );
	}
}
