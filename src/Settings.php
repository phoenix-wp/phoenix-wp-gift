<?php
/**
 * Plugin settings.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift;

defined( 'ABSPATH' ) || exit;

/**
 * Manages phoenix_wp_gift_settings option.
 */
final class Settings {

	public const OPTION_KEY = 'phoenix_wp_gift_settings';

	public const TRIGGER_SUBTOTAL      = 'subtotal';
	public const TRIGGER_ITEM_QUANTITY = 'item_quantity';

	private static ?self $instance = null;

	private ?array $cache = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	/**
	 * @return array<string, mixed>
	 */
	public function get_defaults(): array {
		return array(
			'enabled'           => false,
			'product_id'        => 0,
			'trigger_type'      => self::TRIGGER_SUBTOTAL,
			'min_subtotal'      => '0',
			'min_item_quantity' => 0,
			'gift_label'        => '',
		);
	}

	public function maybe_set_defaults(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option( self::OPTION_KEY, $this->get_defaults(), '', false );
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION_KEY, array() );
			$this->cache = array_merge( $this->get_defaults(), is_array( $stored ) ? $stored : array() );
		}

		return $this->cache;
	}

	public function get( string $key, mixed $default = null ): mixed {
		$all = $this->get_all();

		return $all[ $key ] ?? $default;
	}

	/**
	 * @param array<string, mixed> $values Settings to save.
	 */
	public function update( array $values ): bool {
		$merged      = array_merge( $this->get_all(), $values );
		$merged      = $this->sanitize( $merged );
		$this->cache = $merged;

		return update_option( self::OPTION_KEY, $merged, false );
	}

	/**
	 * @param array<string, mixed> $values Raw values.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $values ): array {
		$defaults = $this->get_defaults();

		$trigger = sanitize_key( (string) ( $values['trigger_type'] ?? $defaults['trigger_type'] ) );

		if ( ! in_array( $trigger, array( self::TRIGGER_SUBTOTAL, self::TRIGGER_ITEM_QUANTITY ), true ) ) {
			$trigger = self::TRIGGER_SUBTOTAL;
		}

		return array(
			'enabled'           => ! empty( $values['enabled'] ),
			'product_id'        => absint( $values['product_id'] ?? $defaults['product_id'] ),
			'trigger_type'      => $trigger,
			'min_subtotal'      => wc_format_decimal( (string) ( $values['min_subtotal'] ?? $defaults['min_subtotal'] ) ),
			'min_item_quantity' => max( 0, absint( $values['min_item_quantity'] ?? $defaults['min_item_quantity'] ) ),
			'gift_label'        => sanitize_text_field( (string) ( $values['gift_label'] ?? $defaults['gift_label'] ) ),
		);
	}

	public function flush_cache(): void {
		$this->cache = null;
	}

	public function is_enabled(): bool {
		return (bool) $this->get( 'enabled', false );
	}

	public function get_product_id(): int {
		return absint( $this->get( 'product_id', 0 ) );
	}

	public function get_trigger_type(): string {
		$trigger = sanitize_key( (string) $this->get( 'trigger_type', self::TRIGGER_SUBTOTAL ) );

		return in_array( $trigger, array( self::TRIGGER_SUBTOTAL, self::TRIGGER_ITEM_QUANTITY ), true )
			? $trigger
			: self::TRIGGER_SUBTOTAL;
	}

	public function uses_subtotal_trigger(): bool {
		return self::TRIGGER_SUBTOTAL === $this->get_trigger_type();
	}

	public function uses_item_quantity_trigger(): bool {
		return self::TRIGGER_ITEM_QUANTITY === $this->get_trigger_type();
	}

	public function get_min_subtotal(): float {
		return (float) $this->get( 'min_subtotal', 0 );
	}

	public function get_min_item_quantity(): int {
		return max( 0, absint( $this->get( 'min_item_quantity', 0 ) ) );
	}

	/**
	 * Custom gift label or default translatable string.
	 */
	public function get_gift_label(): string {
		$label = trim( (string) $this->get( 'gift_label', '' ) );

		if ( '' !== $label ) {
			return $label;
		}

		return __( 'Free gift', 'phoenix-wp-gift' );
	}
}
