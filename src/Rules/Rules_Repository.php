<?php
/**
 * Free-tier gift rule resolution (settings-based single rule).
 *
 * Pro CRUD lives in premium/src/Rules/Rules_Repository.php (never in public GitHub).
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the single free-tier gift rule from plugin settings.
 */
final class Rules_Repository {

	public const LEGACY_FREE_RULE_ID = 'legacy_free';

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {}

	private function __clone() {}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_runtime_rules(): array {
		return array( $this->build_rule_from_settings() );
	}

	/**
	 * @return int[]
	 */
	public function get_managed_product_ids(): array {
		$product_id = Settings::instance()->get_product_id();

		return $product_id > 0 ? array( $product_id ) : array();
	}

	public function has_stored_rules(): bool {
		return false;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		$id = sanitize_key( $id );

		if ( self::LEGACY_FREE_RULE_ID !== $id ) {
			return null;
		}

		return $this->build_rule_from_settings();
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_rule_from_settings(): array {
		$settings = Settings::instance()->get_all();
		$trigger  = sanitize_key( (string) ( $settings['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ) );

		if ( ! in_array( $trigger, array( Settings::TRIGGER_SUBTOTAL, Settings::TRIGGER_ITEM_QUANTITY ), true ) ) {
			$trigger = Settings::TRIGGER_SUBTOTAL;
		}

		return array(
			'id'                => self::LEGACY_FREE_RULE_ID,
			'name'              => __( 'Gift rule', 'phoenix-gift-for-woocommerce' ),
			'enabled'           => ! empty( $settings['enabled'] ),
			'priority'          => 10,
			'product_id'        => absint( $settings['product_id'] ?? 0 ),
			'variation_id'      => 0,
			'trigger_type'      => $trigger,
			'min_subtotal'      => (string) ( $settings['min_subtotal'] ?? '0' ),
			'min_item_quantity' => absint( $settings['min_item_quantity'] ?? 0 ),
			'gift_label'        => (string) ( $settings['gift_label'] ?? '' ),
		);
	}
}
