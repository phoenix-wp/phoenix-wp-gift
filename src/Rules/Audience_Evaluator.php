<?php
/**
 * Evaluates login and role restrictions for gift rules.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Audience checks for who may receive a gift rule.
 */
final class Audience_Evaluator {

	public const AUDIENCE_ANY = 'any';

	public const AUDIENCE_LOGGED_IN = 'logged_in';

	public const AUDIENCE_GUEST = 'guest';

	/**
	 * Whether the current visitor matches the rule audience.
	 *
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function rule_matches_audience( array $rule ): bool {
		$audience = self::normalize_audience( $rule['audience'] ?? self::AUDIENCE_ANY );

		if ( self::AUDIENCE_GUEST === $audience ) {
			return ! is_user_logged_in();
		}

		if ( self::AUDIENCE_LOGGED_IN === $audience ) {
			if ( ! is_user_logged_in() ) {
				return false;
			}

			return self::user_matches_roles( $rule );
		}

		return true;
	}

	/**
	 * @param mixed $raw Raw audience value.
	 */
	public static function normalize_audience( $raw ): string {
		$audience = sanitize_key( (string) $raw );

		if ( ! in_array( $audience, array( self::AUDIENCE_ANY, self::AUDIENCE_LOGGED_IN, self::AUDIENCE_GUEST ), true ) ) {
			return self::AUDIENCE_ANY;
		}

		return $audience;
	}

	/**
	 * @param mixed $raw Raw role list.
	 * @return string[]
	 */
	public static function sanitize_roles( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$valid_roles = array_keys( wp_roles()->roles );
		$roles       = array();

		foreach ( $raw as $role ) {
			$role = sanitize_key( (string) $role );

			if ( '' !== $role && in_array( $role, $valid_roles, true ) ) {
				$roles[] = $role;
			}
		}

		sort( $roles );

		return array_values( array_unique( $roles ) );
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function has_audience_restriction( array $rule ): bool {
		$audience = self::normalize_audience( $rule['audience'] ?? self::AUDIENCE_ANY );

		if ( self::AUDIENCE_ANY !== $audience ) {
			return true;
		}

		return ! empty( self::sanitize_roles( $rule['user_roles'] ?? array() ) );
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function user_matches_roles( array $rule ): bool {
		$allowed_roles = self::sanitize_roles( $rule['user_roles'] ?? array() );

		if ( empty( $allowed_roles ) ) {
			return true;
		}

		$user = wp_get_current_user();

		if ( ! $user instanceof \WP_User || 0 === $user->ID ) {
			return false;
		}

		foreach ( $allowed_roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function get_display_label( array $rule ): string {
		$audience = self::normalize_audience( $rule['audience'] ?? self::AUDIENCE_ANY );
		$roles      = self::sanitize_roles( $rule['user_roles'] ?? array() );

		if ( self::AUDIENCE_GUEST === $audience ) {
			return __( 'Guests only', 'phoenix-wp-gift' );
		}

		if ( self::AUDIENCE_LOGGED_IN !== $audience ) {
			return __( 'Everyone', 'phoenix-wp-gift' );
		}

		if ( empty( $roles ) ) {
			return __( 'Logged-in customers', 'phoenix-wp-gift' );
		}

		$labels = array();

		foreach ( $roles as $role ) {
			$labels[] = translate_user_role( wp_roles()->roles[ $role ]['name'] ?? $role );
		}

		return sprintf(
			/* translators: %s: comma-separated role names */
			__( 'Roles: %s', 'phoenix-wp-gift' ),
			implode( ', ', $labels )
		);
	}
}
