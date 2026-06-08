<?php
/**
 * Exports gift rules as JSON.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a portable JSON export of Pro gift rules.
 */
final class Rules_Exporter {

	public const SCHEMA_VERSION = '1.0';

	/**
	 * @return array<string, mixed>
	 */
	public static function build_payload(): array {
		$rules = array_values( Rules_Repository::instance()->get_all_for_admin() );

		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'plugin_version' => PHOENIX_WP_GIFT_VERSION,
			'rules'          => $rules,
		);
	}

	public static function to_json(): string {
		$payload = self::build_payload();

		return (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}
