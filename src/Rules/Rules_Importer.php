<?php
/**
 * Imports gift rules from JSON.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Parses and validates exported rule payloads.
 */
final class Rules_Importer {

	public const MODE_MERGE   = 'merge';
	public const MODE_REPLACE = 'replace';

	/**
	 * @param string $json Raw JSON string.
	 * @return array<string, mixed>|null
	 */
	public static function parse_payload( string $json ): ?array {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['rules'] ) || ! is_array( $decoded['rules'] ) ) {
			return null;
		}

		return $decoded;
	}

	/**
	 * @param array<int, mixed> $raw_rules Rules from export file.
	 * @param string            $mode      merge|replace.
	 * @return array{imported: int, skipped: int}
	 */
	public static function import_rules( array $raw_rules, string $mode = self::MODE_MERGE ): array {
		$repository = Rules_Repository::instance();
		$mode       = self::MODE_REPLACE === $mode ? self::MODE_REPLACE : self::MODE_MERGE;
		$existing = self::MODE_MERGE === $mode ? $repository->get_all() : array();
		$prepared = array();
		$skipped  = 0;

		foreach ( $raw_rules as $raw_rule ) {
			if ( ! is_array( $raw_rule ) ) {
				++$skipped;
				continue;
			}

			$rule = $repository->sanitize_rule( $raw_rule );

			if ( '' === trim( (string) ( $rule['name'] ?? '' ) ) || absint( $rule['product_id'] ?? 0 ) <= 0 ) {
				++$skipped;
				continue;
			}

			if ( Rules_Repository::LEGACY_FREE_RULE_ID === (string) ( $rule['id'] ?? '' ) ) {
				$rule['id'] = '';
			}

			$rule_id = (string) ( $rule['id'] ?? '' );

			if ( self::MODE_MERGE === $mode && '' !== $rule_id && isset( $existing[ $rule_id ] ) ) {
				$rule['id'] = '';
			}

			$prepared[] = $rule;
		}

		if ( self::MODE_REPLACE === $mode ) {
			$repository->replace_all( $prepared );

			return array(
				'imported' => count( $prepared ),
				'skipped'  => $skipped,
			);
		}

		$imported = 0;

		foreach ( $prepared as $rule ) {
			if ( $repository->save( $rule ) ) {
				++$imported;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}
}
