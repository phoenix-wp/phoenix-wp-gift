<?php
/**
 * Stores and resolves gift rules (Pro) and free-tier fallback.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Rules;

use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD and runtime resolution for gift rules.
 */
final class Rules_Repository {

	public const OPTION_KEY = 'phoenix_wp_gift_rules';

	public const COMBINE_ADDITIONAL = 'additional';

	public const COMBINE_UPGRADE = 'upgrade';

	/**
	 * Synthetic ID for the free-settings fallback rule (never stored alongside Pro rules).
	 */
	public const LEGACY_FREE_RULE_ID = 'legacy_free';

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
	public function get_rule_defaults(): array {
		return array(
			'id'                => '',
			'name'              => '',
			'enabled'           => true,
			'priority'          => 10,
			'product_id'        => 0,
			'variation_id'      => 0,
			'gift_selection'    => Gift_Options_Helper::SELECTION_AUTO,
			'gift_options'      => array(),
			'trigger_type'      => Settings::TRIGGER_SUBTOTAL,
			'min_subtotal'      => '0',
			'min_item_quantity' => 0,
			'gift_label'        => '',
			'combine_mode'      => self::COMBINE_UPGRADE,
			'upgrade_group'     => 'default',
			'date_start'        => '',
			'date_end'          => '',
			'weekdays'          => array( 0, 1, 2, 3, 4, 5, 6 ),
			'audience'          => Audience_Evaluator::AUDIENCE_ANY,
			'user_roles'            => array(),
			'require_product_ids'   => array(),
			'require_category_ids'  => array(),
			'require_tag_ids'       => array(),
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all(): array {
		if ( null === $this->cache ) {
			$stored = get_option( self::OPTION_KEY, array() );
			$rules  = is_array( $stored ) ? $stored : array();

			$this->cache = array();

			foreach ( $rules as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}

				$sanitized = $this->sanitize_rule( $rule );
				$id        = (string) ( $sanitized['id'] ?? '' );

				if ( '' !== $id ) {
					$this->cache[ $id ] = $sanitized;
				}
			}
		}

		$this->maybe_repair_auto_stack_groups();
		$this->maybe_repair_scheduled_rule_groups();

		return $this->cache;
	}

	/**
	 * Merges auto-assigned stack_* groups (1.2.7) back into default when a default-group rule exists.
	 */
	private function maybe_repair_auto_stack_groups(): void {
		if ( null === $this->cache || empty( $this->cache ) ) {
			return;
		}

		$has_default_group_rule = false;

		foreach ( $this->cache as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			if ( 'default' === sanitize_key( (string) ( $rule['upgrade_group'] ?? 'default' ) ) ) {
				$has_default_group_rule = true;
				break;
			}
		}

		if ( ! $has_default_group_rule ) {
			return;
		}

		$changed = false;

		foreach ( $this->cache as $id => $rule ) {
			$group = sanitize_key( (string) ( $rule['upgrade_group'] ?? '' ) );

			if ( self::COMBINE_ADDITIONAL !== sanitize_key( (string) ( $rule['combine_mode'] ?? '' ) ) ) {
				continue;
			}

			if ( 'stack_' . $id !== $group ) {
				continue;
			}

			$this->cache[ $id ]['upgrade_group'] = 'default';
			$changed                             = true;
		}

		if ( $changed ) {
			update_option( self::OPTION_KEY, array_values( $this->cache ), false );
		}
	}

	/**
	 * Moves scheduled rules out of the default upgrade group so they stack with tier rules.
	 */
	private function maybe_repair_scheduled_rule_groups(): void {
		if ( null === $this->cache || empty( $this->cache ) ) {
			return;
		}

		$changed = false;

		foreach ( $this->cache as $id => $rule ) {
			if ( ! Schedule_Evaluator::has_schedule_restriction( $rule ) ) {
				continue;
			}

			if ( 'default' !== sanitize_key( (string) ( $rule['upgrade_group'] ?? 'default' ) ) ) {
				continue;
			}

			$this->cache[ $id ]['upgrade_group'] = self::scheduled_upgrade_group_for( $rule, (string) $id, $this->cache );
			$changed                             = true;
		}

		foreach ( $this->cache as $id => $rule ) {
			$group = sanitize_key( (string) ( $rule['upgrade_group'] ?? '' ) );

			if ( ! Upgrade_Group_Helper::is_legacy_auto_group( $group, (string) $id ) ) {
				continue;
			}

			$this->cache[ $id ]['upgrade_group'] = Upgrade_Group_Helper::campaign_group_from_rule_name(
				(string) ( $rule['name'] ?? '' ),
				(string) $id,
				Upgrade_Group_Helper::collect_group_slugs( $this->cache, (string) $id )
			);
			$changed                             = true;
		}

		if ( $changed ) {
			update_option( self::OPTION_KEY, array_values( $this->cache ), false );
		}
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		$id = sanitize_key( $id );

		return $this->get_all()[ $id ] ?? null;
	}

	/**
	 * Rules used by the cart engine for the current request.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_runtime_rules(): array {
		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			return array( $this->build_rule_from_settings() );
		}

		$rules = $this->get_enabled_rules_sorted();

		if ( ! empty( $rules ) ) {
			return $rules;
		}

		return array( $this->build_rule_from_settings() );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function has_stored_rules(): bool {
		return ! empty( $this->get_all() );
	}

	/**
	 * Rules for admin lists (hides the legacy duplicate when real Pro rules exist).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all_for_admin(): array {
		$rules = $this->get_all();

		if ( count( $rules ) < 2 || ! isset( $rules[ self::LEGACY_FREE_RULE_ID ] ) ) {
			return $rules;
		}

		unset( $rules[ self::LEGACY_FREE_RULE_ID ] );

		return $rules;
	}

	/**
	 * Removes the migrated legacy_free rule when dedicated Pro rules exist.
	 */
	public function prune_legacy_duplicate_rule(): bool {
		$all = $this->get_all();

		if ( count( $all ) < 2 || ! isset( $all[ self::LEGACY_FREE_RULE_ID ] ) ) {
			return false;
		}

		return $this->delete( self::LEGACY_FREE_RULE_ID );
	}

	public function get_enabled_rules_sorted(): array {
		$rules = array_values(
			array_filter(
				$this->get_all(),
				static fn( array $rule ): bool => ! empty( $rule['enabled'] )
			)
		);

		if ( count( $rules ) > 1 ) {
			$rules = array_values(
				array_filter(
					$rules,
					static fn( array $rule ): bool => self::LEGACY_FREE_RULE_ID !== (string) ( $rule['id'] ?? '' )
				)
			);
		}

		usort(
			$rules,
			static function ( array $a, array $b ): int {
				$priority_a = absint( $a['priority'] ?? 10 );
				$priority_b = absint( $b['priority'] ?? 10 );

				if ( $priority_a === $priority_b ) {
					return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
				}

				return $priority_a <=> $priority_b;
			}
		);

		return $rules;
	}

	/**
	 * @return int[]
	 */
	public function get_managed_product_ids(): array {
		$ids = array();

		foreach ( $this->get_runtime_rules() as $rule ) {
			$ids = array_merge( $ids, Gift_Options_Helper::get_line_ids_for_rule( $rule ) );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Creates the first Pro rule from legacy flat settings when none exist.
	 */
	public function maybe_migrate_from_settings(): bool {
		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			return false;
		}

		if ( ! empty( $this->get_all() ) ) {
			return false;
		}

		$settings = Settings::instance();

		if ( ! $settings->is_enabled() || $settings->get_product_id() <= 0 ) {
			return false;
		}

		$rule                   = $this->build_rule_from_settings();
		$rule['id']             = $this->generate_id();
		$rule['name']           = __( 'Default gift rule', 'phoenix-wp-gift' );
		$rule['combine_mode']   = self::COMBINE_UPGRADE;
		$rule['upgrade_group']  = 'default';

		return $this->save( $rule );
	}

	/**
	 * @param array<string, mixed> $rule Raw rule data.
	 */
	public function save( array $rule ): bool {
		$sanitized = $this->sanitize_rule( $rule );
		$id        = (string) ( $sanitized['id'] ?? '' );

		if ( '' === $id ) {
			$sanitized['id'] = $this->generate_id();
			$id              = (string) $sanitized['id'];
		}

		$all = $this->get_all();

		if (
			Schedule_Evaluator::has_schedule_restriction( $sanitized )
			&& Upgrade_Group_Helper::DEFAULT_GROUP === sanitize_key( (string) ( $sanitized['upgrade_group'] ?? Upgrade_Group_Helper::DEFAULT_GROUP ) )
		) {
			$sanitized['upgrade_group'] = self::scheduled_upgrade_group_for( $sanitized, $id, $all );
		}

		$all[ $id ]  = $sanitized;
		$this->cache = $all;

		update_option( self::OPTION_KEY, array_values( $all ), false );

		return true;
	}

	public function delete( string $id ): bool {
		$id = sanitize_key( $id );

		if ( '' === $id ) {
			return false;
		}

		$all = $this->get_all();

		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}

		unset( $all[ $id ] );
		$this->cache = $all;

		update_option( self::OPTION_KEY, array_values( $all ), false );

		return true;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function duplicate( string $id ): ?array {
		$source = $this->get( $id );

		if ( null === $source ) {
			return null;
		}

		$copy = $source;
		$copy['id']       = $this->generate_id();
		$copy['name']     = trim( (string) ( $source['name'] ?? '' ) ) . ' ' . __( '(copy)', 'phoenix-wp-gift' );
		$copy['priority'] = absint( $source['priority'] ?? 10 ) + 1;

		if ( ! $this->save( $copy ) ) {
			return null;
		}

		return $copy;
	}

	/**
	 * Replaces all stored rules in one write (import replace mode).
	 *
	 * @param array<int, array<string, mixed>> $rules Rule payloads.
	 */
	public function replace_all( array $rules ): bool {
		$all = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$sanitized = $this->sanitize_rule( $rule );

			if ( '' === trim( (string) ( $sanitized['name'] ?? '' ) ) || absint( $sanitized['product_id'] ?? 0 ) <= 0 ) {
				continue;
			}

			if ( '' === (string) ( $sanitized['id'] ?? '' ) ) {
				$sanitized['id'] = $this->generate_id();
			}

			$id = (string) $sanitized['id'];

			$all[ $id ] = $sanitized;
		}

		$this->cache = $all;

		update_option( self::OPTION_KEY, array_values( $all ), false );

		return true;
	}

	public function flush_cache(): void {
		$this->cache = null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function build_rule_from_settings(): array {
		$settings = Settings::instance()->get_all();

		return $this->sanitize_rule(
			array(
				'id'                => self::LEGACY_FREE_RULE_ID,
				'name'              => __( 'Gift rule', 'phoenix-wp-gift' ),
				'enabled'           => ! empty( $settings['enabled'] ),
				'priority'          => 10,
				'product_id'        => absint( $settings['product_id'] ?? 0 ),
				'trigger_type'      => (string) ( $settings['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ),
				'min_subtotal'      => (string) ( $settings['min_subtotal'] ?? '0' ),
				'min_item_quantity' => absint( $settings['min_item_quantity'] ?? 0 ),
				'gift_label'        => (string) ( $settings['gift_label'] ?? '' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $rule Raw rule.
	 * @return array<string, mixed>
	 */
	public function sanitize_rule( array $rule ): array {
		$defaults = $this->get_rule_defaults();
		$trigger  = sanitize_key( (string) ( $rule['trigger_type'] ?? $defaults['trigger_type'] ) );

		if ( ! in_array( $trigger, array( Settings::TRIGGER_SUBTOTAL, Settings::TRIGGER_ITEM_QUANTITY ), true ) ) {
			$trigger = Settings::TRIGGER_SUBTOTAL;
		}

		$id = sanitize_key( (string) ( $rule['id'] ?? '' ) );

		$combine_mode = sanitize_key( (string) ( $rule['combine_mode'] ?? $defaults['combine_mode'] ) );

		if ( ! in_array( $combine_mode, array( self::COMBINE_ADDITIONAL, self::COMBINE_UPGRADE ), true ) ) {
			$combine_mode = self::COMBINE_ADDITIONAL;
		}

		$upgrade_group = sanitize_key( (string) ( $rule['upgrade_group'] ?? $defaults['upgrade_group'] ) );

		if ( '' === $upgrade_group ) {
			$upgrade_group = 'default';
		}

		$weekdays = array_key_exists( 'weekdays', $rule )
			? Schedule_Evaluator::normalize_weekdays( $rule['weekdays'] )
			: Schedule_Evaluator::normalize_weekdays( $defaults['weekdays'] );

		$audience = Audience_Evaluator::normalize_audience( $rule['audience'] ?? $defaults['audience'] );
		$roles    = Audience_Evaluator::sanitize_roles( $rule['user_roles'] ?? $defaults['user_roles'] );

		if ( Audience_Evaluator::AUDIENCE_LOGGED_IN !== $audience ) {
			$roles = array();
		}

		$gift_options = Gift_Options_Helper::sanitize_options( $rule['gift_options'] ?? array() );
		$product_id   = absint( $rule['product_id'] ?? $defaults['product_id'] );
		$variation_id = absint( $rule['variation_id'] ?? $defaults['variation_id'] );

		if ( empty( $gift_options ) && $product_id > 0 ) {
			$gift_options[] = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
			);
		}

		if ( ! empty( $gift_options ) ) {
			$product_id   = absint( $gift_options[0]['product_id'] ?? 0 );
			$variation_id = absint( $gift_options[0]['variation_id'] ?? 0 );
		}

		return array(
			'id'                => $id,
			'name'              => sanitize_text_field( (string) ( $rule['name'] ?? $defaults['name'] ) ),
			'enabled'           => ! empty( $rule['enabled'] ),
			'priority'          => max( 0, absint( $rule['priority'] ?? $defaults['priority'] ) ),
			'product_id'        => $product_id,
			'variation_id'      => $variation_id,
			'gift_selection'    => Gift_Options_Helper::normalize_selection( (string) ( $rule['gift_selection'] ?? $defaults['gift_selection'] ) ),
			'gift_options'      => $gift_options,
			'trigger_type'      => $trigger,
			'min_subtotal'      => wc_format_decimal( (string) ( $rule['min_subtotal'] ?? $defaults['min_subtotal'] ) ),
			'min_item_quantity' => max( 0, absint( $rule['min_item_quantity'] ?? $defaults['min_item_quantity'] ) ),
			'gift_label'        => sanitize_text_field( (string) ( $rule['gift_label'] ?? $defaults['gift_label'] ) ),
			'combine_mode'      => $combine_mode,
			'upgrade_group'     => $upgrade_group,
			'date_start'        => Schedule_Evaluator::sanitize_date( (string) ( $rule['date_start'] ?? $defaults['date_start'] ) ),
			'date_end'          => Schedule_Evaluator::sanitize_date( (string) ( $rule['date_end'] ?? $defaults['date_end'] ) ),
			'weekdays'          => $weekdays,
			'audience'          => $audience,
			'user_roles'            => $roles,
			'require_product_ids'   => Cart_Content_Evaluator::sanitize_product_ids( $rule['require_product_ids'] ?? $defaults['require_product_ids'] ),
			'require_category_ids'  => Cart_Content_Evaluator::sanitize_term_ids( $rule['require_category_ids'] ?? $defaults['require_category_ids'], 'product_cat' ),
			'require_tag_ids'       => Cart_Content_Evaluator::sanitize_term_ids( $rule['require_tag_ids'] ?? $defaults['require_tag_ids'], 'product_tag' ),
		);
	}

	private function generate_id(): string {
		return 'rule_' . wp_generate_password( 8, false, false );
	}

	/**
	 * @param array<string, mixed>               $rule      Rule data.
	 * @param string                           $rule_id   Rule ID.
	 * @param array<string, array<string, mixed>> $all_rules All stored rules.
	 */
	private static function scheduled_upgrade_group_for( array $rule, string $rule_id, array $all_rules ): string {
		return Upgrade_Group_Helper::campaign_group_from_rule_name(
			(string) ( $rule['name'] ?? '' ),
			$rule_id,
			Upgrade_Group_Helper::collect_group_slugs( $all_rules, $rule_id )
		);
	}
}
