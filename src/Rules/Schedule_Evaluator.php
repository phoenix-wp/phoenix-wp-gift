<?php
/**
 * Evaluates date and weekday restrictions for gift rules.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Schedule window checks using the WordPress site timezone.
 */
final class Schedule_Evaluator {

	/**
	 * Whether the current moment falls inside the rule schedule.
	 *
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function rule_matches_schedule( array $rule ): bool {
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );

		if ( ! self::is_within_date_range( $rule, $now, $timezone ) ) {
			return false;
		}

		return self::is_allowed_weekday( $rule, $now );
	}

	/**
	 * @param array<string, mixed>     $rule     Rule data.
	 * @param \DateTimeImmutable       $now      Current moment.
	 * @param \DateTimeZone            $timezone Site timezone.
	 */
	private static function is_within_date_range( array $rule, \DateTimeImmutable $now, \DateTimeZone $timezone ): bool {
		$date_start = (string) ( $rule['date_start'] ?? '' );
		$date_end   = (string) ( $rule['date_end'] ?? '' );

		if ( '' === $date_start && '' === $date_end ) {
			return true;
		}

		if ( '' !== $date_start && '' !== $date_end && $date_start > $date_end ) {
			return false;
		}

		if ( '' !== $date_start ) {
			$start = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_start . ' 00:00:00', $timezone );

			if ( ! $start instanceof \DateTimeImmutable || $now < $start ) {
				return false;
			}
		}

		if ( '' !== $date_end ) {
			$end = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_end . ' 23:59:59', $timezone );

			if ( ! $end instanceof \DateTimeImmutable || $now > $end ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 * @param \DateTimeImmutable   $now  Current moment.
	 */
	private static function is_allowed_weekday( array $rule, \DateTimeImmutable $now ): bool {
		$weekdays = self::normalize_weekdays( $rule['weekdays'] ?? null );

		if ( count( $weekdays ) >= 7 ) {
			return true;
		}

		if ( empty( $weekdays ) ) {
			return false;
		}

		$current_day = (int) $now->format( 'w' );

		return in_array( $current_day, $weekdays, true );
	}

	/**
	 * @param mixed $raw Raw weekdays value.
	 * @return int[] 0 (Sunday) through 6 (Saturday).
	 */
	public static function normalize_weekdays( $raw ): array {
		if ( null === $raw ) {
			return range( 0, 6 );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$days = array();

		foreach ( $raw as $day ) {
			$value = absint( $day );

			if ( $value >= 0 && $value <= 6 ) {
				$days[] = $value;
			}
		}

		sort( $days );

		return array_values( array_unique( $days ) );
	}

	/**
	 * @param string $raw Raw date (Y-m-d).
	 */
	public static function sanitize_date( string $raw ): string {
		$value = sanitize_text_field( $raw );

		if ( '' === $value ) {
			return '';
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

		if ( ! $date instanceof \DateTimeImmutable || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}

		return $value;
	}

	/**
	 * Whether the rule has any schedule restriction configured.
	 *
	 * @param array<string, mixed> $rule Rule data.
	 */
	public static function has_schedule_restriction( array $rule ): bool {
		if ( '' !== (string) ( $rule['date_start'] ?? '' ) || '' !== (string) ( $rule['date_end'] ?? '' ) ) {
			return true;
		}

		return count( self::normalize_weekdays( $rule['weekdays'] ?? null ) ) < 7;
	}
}
