<?php
/**
 * Human-friendly labels and slugs for gift tier groups.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Upgrade groups are internal slugs; labels are for admin display only.
 */
final class Upgrade_Group_Helper {

	public const DEFAULT_GROUP = 'default';

	/**
	 * @param array<string, array<string, mixed>> $rules_by_id Rules keyed by ID.
	 */
	public static function get_display_label( string $group_slug, array $rules_by_id = array() ): string {
		$group = sanitize_key( $group_slug );

		if ( '' === $group || self::DEFAULT_GROUP === $group ) {
			return __( 'Standard tiers', 'phoenix-wp-gift' );
		}

		if ( preg_match( '/^schedule_rule_(.+)$/', $group, $matches ) ) {
			$rule = $rules_by_id[ $matches[1] ] ?? null;

			if ( is_array( $rule ) && '' !== trim( (string) ( $rule['name'] ?? '' ) ) ) {
				return sprintf(
					/* translators: %s: rule name */
					__( 'Campaign: %s', 'phoenix-wp-gift' ),
					(string) $rule['name']
				);
			}
		}

		if ( preg_match( '/^stack_rule_(.+)$/', $group, $matches ) ) {
			$rule = $rules_by_id[ $matches[1] ] ?? null;

			if ( is_array( $rule ) && '' !== trim( (string) ( $rule['name'] ?? '' ) ) ) {
				return sprintf(
					/* translators: %s: rule name */
					__( 'Parallel: %s', 'phoenix-wp-gift' ),
					(string) $rule['name']
				);
			}
		}

		return self::slug_to_readable_label( $group );
	}

	public static function slug_to_readable_label( string $slug ): string {
		$slug = str_replace( '_', '-', sanitize_key( $slug ) );

		return ucwords( str_replace( '-', ' ', $slug ) );
	}

	/**
	 * Builds a unique campaign group slug from the rule name.
	 *
	 * @param string   $rule_name       Rule name.
	 * @param string   $rule_id         Rule ID (excluded from uniqueness check).
	 * @param string[] $existing_groups Group slugs already in use.
	 */
	public static function campaign_group_from_rule_name( string $rule_name, string $rule_id, array $existing_groups ): string {
		$base = sanitize_title( $rule_name );

		if ( '' === $base ) {
			$base = 'campaign';
		}

		$candidate = sanitize_key( $base );
		$used      = array_values(
			array_unique(
				array_filter(
					array_map( 'sanitize_key', $existing_groups ),
					static fn( string $group ): bool => '' !== $group
				)
			)
		);

		if ( ! in_array( $candidate, $used, true ) ) {
			return $candidate;
		}

		$suffix = 2;

		while ( in_array( $candidate . '-' . $suffix, $used, true ) ) {
			++$suffix;
		}

		return sanitize_key( $candidate . '-' . $suffix );
	}

	/**
	 * @param array<string, array<string, mixed>> $all_rules     All rules.
	 * @param string                              $exclude_id    Rule ID to skip.
	 * @return string[]
	 */
	public static function collect_group_slugs( array $all_rules, string $exclude_id = '' ): array {
		$groups = array();

		foreach ( $all_rules as $id => $rule ) {
			if ( (string) $id === $exclude_id ) {
				continue;
			}

			$group = sanitize_key( (string) ( $rule['upgrade_group'] ?? self::DEFAULT_GROUP ) );

			if ( '' !== $group ) {
				$groups[] = $group;
			}
		}

		return array_values( array_unique( $groups ) );
	}

	/**
	 * Whether the slug is a legacy auto-generated technical group name.
	 */
	public static function is_legacy_auto_group( string $group_slug, string $rule_id ): bool {
		$group = sanitize_key( $group_slug );

		return 'schedule_rule_' . $rule_id === $group || 'stack_' . $rule_id === $group;
	}

	/**
	 * @param array<string, array<string, mixed>> $all_rules All rules.
	 * @return string[] Unique group slugs for datalist suggestions.
	 */
	public static function get_suggested_group_slugs( array $all_rules ): array {
		$groups = array( self::DEFAULT_GROUP );

		foreach ( $all_rules as $rule ) {
			$group = sanitize_key( (string) ( $rule['upgrade_group'] ?? self::DEFAULT_GROUP ) );

			if ( '' !== $group ) {
				$groups[] = $group;
			}
		}

		sort( $groups );

		return array_values( array_unique( $groups ) );
	}
}
