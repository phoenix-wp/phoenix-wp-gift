<?php
/**
 * Pro gift rules admin UI.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Admin;

use PhoenixWP\Gift\Rules\Audience_Evaluator;
use PhoenixWP\Gift\Rules\Cart_Content_Evaluator;
use PhoenixWP\Gift\Rules\Gift_Options_Helper;
use PhoenixWP\Gift\Rules\Rules_Repository;
use PhoenixWP\Gift\Rules\Schedule_Evaluator;
use PhoenixWP\Gift\Rules\Upgrade_Group_Helper;
use PhoenixWP\Gift\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the Pro rules list and edit form.
 */
final class Rules_Admin {

	private const ACTION_SAVE      = 'phoenix_wp_gift_save_rule';
	private const ACTION_DELETE    = 'phoenix_wp_gift_delete_rule';
	private const ACTION_DUPLICATE = 'phoenix_wp_gift_duplicate_rule';
	private const NONCE_ACTION     = 'phoenix_wp_gift_rules';

	public static function register_hooks(): void {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( self::class, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( self::class, 'handle_delete' ) );
		add_action( 'admin_post_' . self::ACTION_DUPLICATE, array( self::class, 'handle_duplicate' ) );
		add_action( 'wp_ajax_phoenix_wp_gift_variations', array( self::class, 'ajax_variations' ) );
	}

	public static function render_section(): void {
		if ( ! phoenix_wp_gift_is_pro_active( 'multiple_rules' ) ) {
			return;
		}

		$repository = Rules_Repository::instance();

		if ( $repository->prune_legacy_duplicate_rule() ) {
			echo '<div class="notice notice-info is-dismissible"><p>';
			echo esc_html__(
				'Removed the duplicate “Default gift rule” that mirrored the free settings. Use Pro rules below only.',
				'phoenix-wp-gift'
			);
			echo '</p></div>';
		}

		$repository->maybe_migrate_from_settings();

		$edit_id = isset( $_GET['gift_rule'] ) ? sanitize_key( wp_unslash( (string) $_GET['gift_rule'] ) ) : '';
		$rule    = 'new' === $edit_id
			? Rules_Repository::instance()->get_rule_defaults()
			: ( Rules_Repository::instance()->get( $edit_id ) ?? null );

		echo '<hr /><h2>' . esc_html__( 'Pro rules', 'phoenix-wp-gift' ) . '</h2>';
		echo '<p class="description">';
		echo esc_html__(
			'Create multiple gift rules. Rules in the same gift tier group compete (highest threshold wins). Different tier groups stack as separate gifts.',
			'phoenix-wp-gift'
		);
		echo '</p>';
		echo '<p class="description">';
		echo esc_html__(
			'Progress hint (Pro): [phoenix_wp_gift_progress] — live cart progress. Customer gift choice (Pro): set “Customer chooses” below; picker appears on the classic cart/checkout or via [phoenix_wp_gift_choice] (recommended for Cart blocks).',
			'phoenix-wp-gift'
		);
		echo '</p>';

		self::maybe_render_upgrade_hint();

		self::render_notices();
		self::render_rules_table();

		if ( null !== $rule || 'new' === $edit_id ) {
			self::render_rule_form( is_array( $rule ) ? $rule : Rules_Repository::instance()->get_rule_defaults() );
		} else {
			$base_url = admin_url( 'admin.php?page=' . Menu::PAGE_SLUG . '&gift_rule=new' );
			printf(
				'<p><a class="button button-primary" href="%1$s">%2$s</a></p>',
				esc_url( $base_url ),
				esc_html__( 'Add rule', 'phoenix-wp-gift' )
			);
		}
	}

	private static function maybe_render_upgrade_hint(): void {
		$rules = Rules_Repository::instance()->get_all_for_admin();

		if ( count( $rules ) < 2 ) {
			return;
		}

		$upgrade_count = 0;

		foreach ( $rules as $rule ) {
			if ( Rules_Repository::COMBINE_UPGRADE === sanitize_key( (string) ( $rule['combine_mode'] ?? '' ) ) ) {
				++$upgrade_count;
			}
		}

		if ( $upgrade_count > 0 ) {
			return;
		}

		echo '<div class="notice notice-warning inline"><p>';
		echo esc_html__(
			'Multiple rules are set to Additional gift. For tiered gifts (50 € → A, 100 € → B), edit each rule and choose Upgrade in the same upgrade group.',
			'phoenix-wp-gift'
		);
		echo '</p></div>';
	}

	private static function render_notices(): void {
		if ( empty( $_GET['gift_rules_notice'] ) ) {
			return;
		}

		$code = sanitize_key( wp_unslash( (string) $_GET['gift_rules_notice'] ) );
		$map  = array(
			'saved'      => __( 'Rule saved.', 'phoenix-wp-gift' ),
			'deleted'    => __( 'Rule deleted.', 'phoenix-wp-gift' ),
			'duplicated' => __( 'Rule duplicated.', 'phoenix-wp-gift' ),
			'error'      => __( 'Could not complete the action. Check rule name and gift product, then try again.', 'phoenix-wp-gift' ),
			'not_found'  => __( 'Rule not found. It may have been removed already.', 'phoenix-wp-gift' ),
		);

		if ( ! isset( $map[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === $code ? 'error' : 'success',
			esc_html( $map[ $code ] )
		);
	}

	private static function render_rules_table(): void {
		$rules = Rules_Repository::instance()->get_all_for_admin();

		if ( empty( $rules ) ) {
			echo '<p><em>' . esc_html__( 'No Pro rules yet. Add a rule or save the free settings above to migrate a default rule.', 'phoenix-wp-gift' ) . '</em></p>';
			return;
		}

		$all = array_values( $rules );

		usort(
			$all,
			static function ( array $a, array $b ): int {
				$priority_a = absint( $a['priority'] ?? 10 );
				$priority_b = absint( $b['priority'] ?? 10 );

				if ( $priority_a === $priority_b ) {
					return strcmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) );
				}

				return $priority_a <=> $priority_b;
			}
		);

		echo '<table class="widefat striped phoenix-wp-gift-rules-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Gift product', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Condition', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Audience', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Schedule', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Combine', 'phoenix-wp-gift' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'phoenix-wp-gift' ) . '</th>';
		echo '</tr></thead><tbody>';

		$rules_by_id = $rules;

		foreach ( $all as $rule ) {
			$id         = (string) ( $rule['id'] ?? '' );
			$edit_url = admin_url( 'admin.php?page=' . Menu::PAGE_SLUG . '&gift_rule=' . rawurlencode( $id ) );

			echo '<tr>';
			echo '<td><strong>' . esc_html( (string) ( $rule['name'] ?? '' ) ) . '</strong></td>';
			echo '<td>' . esc_html( ! empty( $rule['enabled'] ) ? __( 'Active', 'phoenix-wp-gift' ) : __( 'Inactive', 'phoenix-wp-gift' ) ) . '</td>';
			echo '<td>' . esc_html( self::describe_gift_products( $rule ) ) . '</td>';
			echo '<td>' . esc_html( self::describe_condition( $rule ) ) . '</td>';
			echo '<td>' . esc_html( Audience_Evaluator::get_display_label( $rule ) ) . '</td>';
			echo '<td>' . esc_html( self::describe_schedule( $rule ) ) . '</td>';
			echo '<td>' . esc_html( self::describe_combine_mode( $rule, $rules_by_id ) ) . '</td>';
			echo '<td class="phoenix-wp-gift-rule-actions">';
			printf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $edit_url ),
				esc_html__( 'Edit', 'phoenix-wp-gift' )
			);
			echo ' | ';
			printf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::action_url( self::ACTION_DUPLICATE, $id ) ),
				esc_html__( 'Duplicate', 'phoenix-wp-gift' )
			);
			echo ' | ';
			printf(
				'<a href="%1$s" onclick="return confirm(%2$s);">%3$s</a>',
				esc_url( self::action_url( self::ACTION_DELETE, $id ) ),
				wp_json_encode( __( 'Delete this rule?', 'phoenix-wp-gift' ) ),
				esc_html__( 'Delete', 'phoenix-wp-gift' )
			);
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function render_rule_form( array $rule ): void {
		$trigger_type = sanitize_key( (string) ( $rule['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ) );
		$is_new       = '' === (string) ( $rule['id'] ?? '' );
		$cancel_url   = admin_url( 'admin.php?page=' . Menu::PAGE_SLUG );
		$all_rules    = Rules_Repository::instance()->get_all();

		echo '<h3>' . esc_html( $is_new ? __( 'Add rule', 'phoenix-wp-gift' ) : __( 'Edit rule', 'phoenix-wp-gift' ) ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="phoenix-wp-gift-rule-form">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '" />';

		if ( ! $is_new ) {
			printf(
				'<input type="hidden" name="rule[id]" value="%1$s" />',
				esc_attr( (string) ( $rule['id'] ?? '' ) )
			);
		}

		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row"><label for="phoenix-wp-gift-rule-name">' . esc_html__( 'Rule name', 'phoenix-wp-gift' ) . '</label></th><td>';
		printf(
			'<input type="text" class="regular-text" id="phoenix-wp-gift-rule-name" name="rule[name]" value="%1$s" required />',
			esc_attr( (string) ( $rule['name'] ?? '' ) )
		);
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Active', 'phoenix-wp-gift' ) . '</th><td>';
		printf(
			'<label><input type="checkbox" name="rule[enabled]" value="1" %1$s /> %2$s</label>',
			checked( ! empty( $rule['enabled'] ), true, false ),
			esc_html__( 'Enable this rule', 'phoenix-wp-gift' )
		);
		echo '</td></tr>';

		self::render_combine_mode_field( $rule );
		self::render_tier_group_field( $rule, $all_rules );
		self::render_gift_options_fields( $rule );
		self::render_gift_label_field( $rule );
		self::render_audience_fields( $rule );
		self::render_condition_fields( $rule, $trigger_type );
		self::render_cart_content_fields( $rule );
		self::render_schedule_fields( $rule );

		echo '<tr><th scope="row">' . esc_html__( 'Advanced', 'phoenix-wp-gift' ) . '</th><td>';
		echo '<details class="phoenix-wp-gift-advanced">';
		echo '<summary>' . esc_html__( 'Tie-breaker priority (optional)', 'phoenix-wp-gift' ) . '</summary>';
		printf(
			'<p><label for="phoenix-wp-gift-rule-priority">%1$s</label><br /><input type="number" min="0" step="1" class="small-text" id="phoenix-wp-gift-rule-priority" name="rule[priority]" value="%2$s" /></p>',
			esc_html__( 'Priority', 'phoenix-wp-gift' ),
			esc_attr( (string) absint( $rule['priority'] ?? 10 ) )
		);
		echo '<p class="description">' . esc_html__( 'Only used when two rules in the same tier group have the exact same threshold. Lower number wins. Leave at 10 in most shops.', 'phoenix-wp-gift' ) . '</p>';
		echo '</details></td></tr>';

		echo '</table>';

		submit_button( $is_new ? __( 'Add rule', 'phoenix-wp-gift' ) : __( 'Save rule', 'phoenix-wp-gift' ) );
		printf(
			' <a class="button button-secondary" href="%1$s">%2$s</a>',
			esc_url( $cancel_url ),
			esc_html__( 'Cancel', 'phoenix-wp-gift' )
		);
		echo '</form>';
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function render_gift_label_field( array $rule ): void {
		echo '<tr><th scope="row"><label for="phoenix-wp-gift-rule-label">' . esc_html__( 'Gift label', 'phoenix-wp-gift' ) . '</label></th><td>';
		printf(
			'<input type="text" class="regular-text" id="phoenix-wp-gift-rule-label" name="rule[gift_label]" value="%1$s" placeholder="%2$s" />',
			esc_attr( (string) ( $rule['gift_label'] ?? '' ) ),
			esc_attr__( 'Free gift', 'phoenix-wp-gift' )
		);
		echo '<p class="description">' . esc_html__( 'Optional per rule. Leave empty to use the global gift label above.', 'phoenix-wp-gift' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * @param array<string, mixed> $rule         Rule data.
	 * @param string               $trigger_type Active trigger type.
	 */
	private static function render_condition_fields( array $rule, string $trigger_type ): void {
		echo '<tr><th scope="row">' . esc_html__( 'Condition', 'phoenix-wp-gift' ) . '</th><td>';
		printf(
			'<fieldset><label><input type="radio" class="phoenix-wp-gift-rule-trigger" name="rule[trigger_type]" value="%1$s" %2$s /> %3$s</label><br />',
			esc_attr( Settings::TRIGGER_SUBTOTAL ),
			checked( $trigger_type, Settings::TRIGGER_SUBTOTAL, false ),
			esc_html__( 'Minimum gross cart subtotal', 'phoenix-wp-gift' )
		);
		printf(
			'<label><input type="radio" class="phoenix-wp-gift-rule-trigger" name="rule[trigger_type]" value="%1$s" %2$s /> %3$s</label></fieldset>',
			esc_attr( Settings::TRIGGER_ITEM_QUANTITY ),
			checked( $trigger_type, Settings::TRIGGER_ITEM_QUANTITY, false ),
			esc_html__( 'Minimum number of items in cart', 'phoenix-wp-gift' )
		);
		echo '</td></tr>';

		$subtotal_style = Settings::TRIGGER_SUBTOTAL === $trigger_type ? '' : ' style="display:none;"';
		echo '<tr class="phoenix-wp-gift-rule-trigger-subtotal"' . $subtotal_style . '><th scope="row"><label for="phoenix-wp-gift-rule-min-subtotal">' . esc_html__( 'Minimum subtotal', 'phoenix-wp-gift' ) . '</label></th><td>';
		printf(
			'<input type="number" step="0.01" min="0" class="regular-text" id="phoenix-wp-gift-rule-min-subtotal" name="rule[min_subtotal]" value="%1$s" />',
			esc_attr( (string) ( $rule['min_subtotal'] ?? '0' ) )
		);
		echo '</td></tr>';

		$quantity_style = Settings::TRIGGER_ITEM_QUANTITY === $trigger_type ? '' : ' style="display:none;"';
		echo '<tr class="phoenix-wp-gift-rule-trigger-quantity"' . $quantity_style . '><th scope="row"><label for="phoenix-wp-gift-rule-min-quantity">' . esc_html__( 'Minimum item quantity', 'phoenix-wp-gift' ) . '</label></th><td>';
		printf(
			'<input type="number" step="1" min="0" class="regular-text" id="phoenix-wp-gift-rule-min-quantity" name="rule[min_item_quantity]" value="%1$s" />',
			esc_attr( (string) absint( $rule['min_item_quantity'] ?? 0 ) )
		);
		echo '</td></tr>';
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function render_gift_options_fields( array $rule ): void {
		$selection = Gift_Options_Helper::normalize_selection( (string) ( $rule['gift_selection'] ?? Gift_Options_Helper::SELECTION_AUTO ) );
		$options   = Gift_Options_Helper::get_options( $rule );

		if ( empty( $options ) ) {
			$options = array(
				array(
					'product_id'   => 0,
					'variation_id' => 0,
				),
			);
		}

		echo '<tr><th scope="row">' . esc_html__( 'Gift products', 'phoenix-wp-gift' ) . '</th><td>';

		echo '<fieldset class="phoenix-wp-gift-selection-mode">';
		printf(
			'<label><input type="radio" class="phoenix-wp-gift-selection" name="rule[gift_selection]" value="%1$s" %2$s /> %3$s</label><br />',
			esc_attr( Gift_Options_Helper::SELECTION_AUTO ),
			checked( $selection, Gift_Options_Helper::SELECTION_AUTO, false ),
			esc_html__( 'Automatic — add the first gift option to the cart', 'phoenix-wp-gift' )
		);
		printf(
			'<label><input type="radio" class="phoenix-wp-gift-selection" name="rule[gift_selection]" value="%1$s" %2$s /> %3$s</label>',
			esc_attr( Gift_Options_Helper::SELECTION_CUSTOMER ),
			checked( $selection, Gift_Options_Helper::SELECTION_CUSTOMER, false ),
			esc_html__( 'Customer chooses — shopper picks 1 of N on the cart page', 'phoenix-wp-gift' )
		);
		echo '</fieldset>';

		echo '<div class="phoenix-wp-gift-options" id="phoenix-wp-gift-options">';

		foreach ( $options as $index => $option ) {
			self::render_gift_option_row( (int) $index, $option );
		}

		echo '</div>';
		printf(
			'<p><button type="button" class="button button-secondary phoenix-wp-gift-add-option">%s</button></p>',
			esc_html__( 'Add gift option', 'phoenix-wp-gift' )
		);
		echo '<p class="description">';
		echo esc_html__(
			'Use simple products or specific variations. Variable products need a variation per option. Customer choice requires at least two options.',
			'phoenix-wp-gift'
		);
		echo '</p>';
		echo '<template id="phoenix-wp-gift-option-template">';
		self::render_gift_option_row( '__INDEX__', array( 'product_id' => 0, 'variation_id' => 0 ), true );
		echo '</template>';
		echo '</td></tr>';
	}

	/**
	 * @param int|string           $index  Row index or template placeholder.
	 * @param array<string, mixed> $option Gift option.
	 * @param bool                 $template Whether this row is rendered inside a template.
	 */
	private static function render_gift_option_row( int|string $index, array $option, bool $template = false ): void {
		$product_id   = absint( $option['product_id'] ?? 0 );
		$variation_id = absint( $option['variation_id'] ?? 0 );
		$index_attr   = $template ? '__INDEX__' : (string) $index;

		echo '<div class="phoenix-wp-gift-option-row" data-index="' . esc_attr( $index_attr ) . '">';
		echo '<label class="screen-reader-text">' . esc_html__( 'Gift product', 'phoenix-wp-gift' ) . '</label>';
		self::render_gift_product_select( $product_id, 'rule[gift_options][' . $index_attr . '][product_id]' );
		echo '<label class="screen-reader-text">' . esc_html__( 'Variation', 'phoenix-wp-gift' ) . '</label>';
		self::render_gift_variation_select( $product_id, $variation_id, 'rule[gift_options][' . $index_attr . '][variation_id]' );
		printf(
			'<button type="button" class="button-link-delete phoenix-wp-gift-remove-option" aria-label="%1$s">&times;</button>',
			esc_attr__( 'Remove gift option', 'phoenix-wp-gift' )
		);
		echo '</div>';
	}

	private static function render_gift_product_select( int $selected_id, string $field_name ): void {
		$products = wc_get_products(
			array(
				'limit'  => 200,
				'status' => 'publish',
				'type'   => array( 'simple', 'variable' ),
				'return' => 'objects',
			)
		);

		printf( '<select class="phoenix-wp-gift-option-product regular-text" name="%s">', esc_attr( $field_name ) );
		echo '<option value="">' . esc_html__( '— Select product —', 'phoenix-wp-gift' ) . '</option>';

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			printf(
				'<option value="%1$d" data-type="%4$s" %2$s>%3$s (#%1$d)</option>',
				esc_attr( (string) $product->get_id() ),
				selected( $selected_id, $product->get_id(), false ),
				esc_html( $product->get_name() ),
				esc_attr( $product->get_type() )
			);
		}

		echo '</select>';
	}

	private static function render_gift_variation_select( int $product_id, int $selected_variation_id, string $field_name ): void {
		$style = 'simple';

		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );

			if ( $product instanceof \WC_Product && $product->is_type( 'variable' ) ) {
				$style = '';
			}
		}

		printf(
			'<select class="phoenix-wp-gift-option-variation regular-text" name="%1$s"%2$s>',
			esc_attr( $field_name ),
			'' !== $style ? ' style="display:none;"' : ''
		);
		echo '<option value="0">' . esc_html__( '— Select variation —', 'phoenix-wp-gift' ) . '</option>';

		if ( $product_id > 0 ) {
			$product = wc_get_product( $product_id );

			if ( $product instanceof \WC_Product_Variable ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );

					if ( ! $variation instanceof \WC_Product_Variation ) {
						continue;
					}

					printf(
						'<option value="%1$d" %2$s>%3$s</option>',
						esc_attr( (string) $variation_id ),
						selected( $selected_variation_id, $variation_id, false ),
						esc_html( $variation->get_name() )
					);
				}
			}
		}

		echo '</select>';
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function describe_gift_products( array $rule ): string {
		if ( Gift_Options_Helper::requires_customer_choice( $rule ) ) {
			return sprintf(
				/* translators: %d: number of gift options */
				__( 'Customer choice (%d options)', 'phoenix-wp-gift' ),
				count( Gift_Options_Helper::get_options( $rule ) )
			);
		}

		$option = Gift_Options_Helper::get_auto_option( $rule );

		if ( null === $option ) {
			return __( 'Not set', 'phoenix-wp-gift' );
		}

		$label = Gift_Options_Helper::get_display_name( $option );

		if ( '' === $label ) {
			return __( 'Not set', 'phoenix-wp-gift' );
		}

		$count = count( Gift_Options_Helper::get_options( $rule ) );

		if ( $count > 1 ) {
			return sprintf(
				/* translators: 1: gift name, 2: option count */
				__( '%1$s (+%2$d more)', 'phoenix-wp-gift' ),
				$label,
				$count - 1
			);
		}

		return $label;
	}

	public static function ajax_variations(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		$product_id = isset( $_GET['product_id'] ) ? absint( wp_unslash( (string) $_GET['product_id'] ) ) : 0;
		$product    = $product_id > 0 ? wc_get_product( $product_id ) : null;

		if ( ! $product instanceof \WC_Product_Variable ) {
			wp_send_json_success( array( 'variations' => array() ) );
		}

		$variations = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof \WC_Product_Variation || ! $variation->is_purchasable() ) {
				continue;
			}

			$variations[] = array(
				'id'    => $variation_id,
				'label' => $variation->get_name(),
			);
		}

		wp_send_json_success( array( 'variations' => $variations ) );
	}

	/**
	 * @param array<string, mixed>               $rule        Rule data.
	 * @param array<string, array<string, mixed>> $rules_by_id All rules keyed by ID.
	 */
	private static function describe_combine_mode( array $rule, array $rules_by_id = array() ): string {
		$mode       = sanitize_key( (string) ( $rule['combine_mode'] ?? Rules_Repository::COMBINE_ADDITIONAL ) );
		$group_slug = sanitize_key( (string) ( $rule['upgrade_group'] ?? Upgrade_Group_Helper::DEFAULT_GROUP ) );
		$group_name = Upgrade_Group_Helper::get_display_label( $group_slug, $rules_by_id );

		if ( Rules_Repository::COMBINE_UPGRADE === $mode ) {
			return sprintf(
				/* translators: %s: gift tier group label */
				__( 'Upgrade · %s', 'phoenix-wp-gift' ),
				$group_name
			);
		}

		return sprintf(
			/* translators: %s: gift tier group label */
			__( 'Additional · %s', 'phoenix-wp-gift' ),
			$group_name
		);
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function render_combine_mode_field( array $rule ): void {
		$combine_mode = sanitize_key( (string) ( $rule['combine_mode'] ?? Rules_Repository::COMBINE_UPGRADE ) );

		echo '<tr><th scope="row">' . esc_html__( 'When this rule matches', 'phoenix-wp-gift' ) . '</th><td>';
		printf(
			'<fieldset><label><input type="radio" class="phoenix-wp-gift-combine-mode" name="rule[combine_mode]" value="%1$s" %2$s /> %3$s</label><br />',
			esc_attr( Rules_Repository::COMBINE_ADDITIONAL ),
			checked( $combine_mode, Rules_Repository::COMBINE_ADDITIONAL, false ),
			esc_html__( 'Additional gift — baseline gift; replaced when an Upgrade rule in the same group matches', 'phoenix-wp-gift' )
		);
		printf(
			'<label><input type="radio" class="phoenix-wp-gift-combine-mode" name="rule[combine_mode]" value="%1$s" %2$s /> %3$s</label></fieldset>',
			esc_attr( Rules_Repository::COMBINE_UPGRADE ),
			checked( $combine_mode, Rules_Repository::COMBINE_UPGRADE, false ),
			esc_html__( 'Upgrade — replace the best matching rule in the same upgrade group', 'phoenix-wp-gift' )
		);
		echo '<p class="description">' . esc_html__( 'Example: Additional at 3 items + Upgrade at 100 € in group “default” — gift A until 100 €, then gift B. Parallel campaigns: use separate group names (e.g. bonus).', 'phoenix-wp-gift' ) . '</p>';
		echo '</td></tr>';
	}

	/**
	 * @param array<string, mixed>               $rule      Rule data.
	 * @param array<string, array<string, mixed>> $all_rules All rules.
	 */
	private static function render_tier_group_field( array $rule, array $all_rules ): void {
		$group_value = sanitize_key( (string) ( $rule['upgrade_group'] ?? Upgrade_Group_Helper::DEFAULT_GROUP ) );

		if ( '' === $group_value ) {
			$group_value = Upgrade_Group_Helper::DEFAULT_GROUP;
		}

		$group_label = Upgrade_Group_Helper::get_display_label( $group_value, $all_rules );

		echo '<tr class="phoenix-wp-gift-upgrade-group-row"><th scope="row"><label for="phoenix-wp-gift-rule-upgrade-group">' . esc_html__( 'Gift tier group', 'phoenix-wp-gift' ) . '</label></th><td>';
		printf(
			'<input type="text" class="regular-text" id="phoenix-wp-gift-rule-upgrade-group" name="rule[upgrade_group]" value="%1$s" list="phoenix-wp-gift-tier-group-suggestions" autocomplete="off" placeholder="%2$s" />',
			esc_attr( $group_value ),
			esc_attr__( 'default', 'phoenix-wp-gift' )
		);
		echo '<datalist id="phoenix-wp-gift-tier-group-suggestions">';

		foreach ( Upgrade_Group_Helper::get_suggested_group_slugs( $all_rules ) as $suggestion ) {
			printf(
				'<option value="%1$s" label="%2$s"></option>',
				esc_attr( $suggestion ),
				esc_attr( Upgrade_Group_Helper::get_display_label( $suggestion, $all_rules ) )
			);
		}

		echo '</datalist>';
		echo '<p class="description">';
		echo esc_html__(
			'Same group = one gift wins (highest threshold). Different groups = gifts stack. Use “default” for standard tiers, or a short slug such as summer-sale or black-week.',
			'phoenix-wp-gift'
		);
		echo ' ';
		printf(
			/* translators: %s: human-readable tier group label */
			esc_html__( 'Admin label: %s.', 'phoenix-wp-gift' ),
			$group_label
		);
		echo '</p>';
		echo '</td></tr>';
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function render_cart_content_fields( array $rule ): void {
		$product_ids  = Cart_Content_Evaluator::sanitize_product_ids( $rule['require_product_ids'] ?? array() );
		$category_ids = Cart_Content_Evaluator::sanitize_term_ids( $rule['require_category_ids'] ?? array(), 'product_cat' );
		$tag_ids      = Cart_Content_Evaluator::sanitize_term_ids( $rule['require_tag_ids'] ?? array(), 'product_tag' );

		echo '<tr><th scope="row">' . esc_html__( 'Cart must contain', 'phoenix-wp-gift' ) . '</th><td>';
		echo '<fieldset class="phoenix-wp-gift-cart-content">';

		$products = wc_get_products(
			array(
				'limit'  => 200,
				'status' => 'publish',
				'type'   => array( 'simple', 'variable' ),
				'return' => 'objects',
			)
		);

		echo '<p><label for="phoenix-wp-gift-require-products">' . esc_html__( 'Products (optional)', 'phoenix-wp-gift' ) . '</label><br />';
		echo '<select class="phoenix-wp-gift-term-select" id="phoenix-wp-gift-require-products" name="rule[require_product_ids][]" multiple size="6">';

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			printf(
				'<option value="%1$d" %2$s>%3$s (#%1$d)</option>',
				(int) $product->get_id(),
				selected( in_array( $product->get_id(), $product_ids, true ), true, false ),
				esc_html( $product->get_name() )
			);
		}

		echo '</select></p>';

		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

		if ( is_array( $categories ) && ! empty( $categories ) ) {
			echo '<p><span>' . esc_html__( 'Categories (optional)', 'phoenix-wp-gift' ) . '</span>';
			echo '<span class="phoenix-wp-gift-term-list">';

			foreach ( $categories as $category ) {
				if ( ! $category instanceof \WP_Term ) {
					continue;
				}

				printf(
					'<label class="phoenix-wp-gift-term-option"><input type="checkbox" name="rule[require_category_ids][]" value="%1$d" %2$s /> %3$s</label>',
					(int) $category->term_id,
					checked( in_array( (int) $category->term_id, $category_ids, true ), true, false ),
					esc_html( $category->name )
				);
			}

			echo '</span></p>';
		}

		$tags = get_terms(
			array(
				'taxonomy'   => 'product_tag',
				'hide_empty' => false,
			)
		);

		if ( is_array( $tags ) && ! empty( $tags ) ) {
			echo '<p><span>' . esc_html__( 'Tags (optional)', 'phoenix-wp-gift' ) . '</span>';
			echo '<span class="phoenix-wp-gift-term-list">';

			foreach ( $tags as $tag ) {
				if ( ! $tag instanceof \WP_Term ) {
					continue;
				}

				printf(
					'<label class="phoenix-wp-gift-term-option"><input type="checkbox" name="rule[require_tag_ids][]" value="%1$d" %2$s /> %3$s</label>',
					(int) $tag->term_id,
					checked( in_array( (int) $tag->term_id, $tag_ids, true ), true, false ),
					esc_html( $tag->name )
				);
			}

			echo '</span></p>';
		}

		echo '<p class="description">' . esc_html__( 'All selected filters must match (AND). Within each list, one matching cart line is enough. Leave empty for no extra cart content requirement.', 'phoenix-wp-gift' ) . '</p>';
		echo '</fieldset></td></tr>';
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function render_audience_fields( array $rule ): void {
		$audience   = Audience_Evaluator::normalize_audience( $rule['audience'] ?? Audience_Evaluator::AUDIENCE_ANY );
		$user_roles = Audience_Evaluator::sanitize_roles( $rule['user_roles'] ?? array() );
		$roles_style = Audience_Evaluator::AUDIENCE_LOGGED_IN === $audience ? '' : ' style="display:none;"';

		echo '<tr><th scope="row">' . esc_html__( 'Customers', 'phoenix-wp-gift' ) . '</th><td>';
		echo '<fieldset class="phoenix-wp-gift-rule-audience">';

		printf(
			'<label><input type="radio" class="phoenix-wp-gift-audience" name="rule[audience]" value="%1$s" %2$s /> %3$s</label><br />',
			esc_attr( Audience_Evaluator::AUDIENCE_ANY ),
			checked( $audience, Audience_Evaluator::AUDIENCE_ANY, false ),
			esc_html__( 'Everyone (guests and logged-in customers)', 'phoenix-wp-gift' )
		);
		printf(
			'<label><input type="radio" class="phoenix-wp-gift-audience" name="rule[audience]" value="%1$s" %2$s /> %3$s</label><br />',
			esc_attr( Audience_Evaluator::AUDIENCE_LOGGED_IN ),
			checked( $audience, Audience_Evaluator::AUDIENCE_LOGGED_IN, false ),
			esc_html__( 'Logged-in customers only', 'phoenix-wp-gift' )
		);
		printf(
			'<label><input type="radio" class="phoenix-wp-gift-audience" name="rule[audience]" value="%1$s" %2$s /> %3$s</label>',
			esc_attr( Audience_Evaluator::AUDIENCE_GUEST ),
			checked( $audience, Audience_Evaluator::AUDIENCE_GUEST, false ),
			esc_html__( 'Guests only (not logged in)', 'phoenix-wp-gift' )
		);

		echo '<p class="phoenix-wp-gift-role-filters"' . $roles_style . '><span>' . esc_html__( 'Limit to roles (optional)', 'phoenix-wp-gift' ) . '</span><br />';

		foreach ( wp_roles()->roles as $role_slug => $role_data ) {
			if ( ! is_array( $role_data ) ) {
				continue;
			}

			printf(
				'<label class="phoenix-wp-gift-user-role"><input type="checkbox" name="rule[user_roles][]" value="%1$s" %2$s /> %3$s</label> ',
				esc_attr( $role_slug ),
				checked( in_array( $role_slug, $user_roles, true ), true, false ),
				esc_html( translate_user_role( (string) ( $role_data['name'] ?? $role_slug ) ) )
			);
		}

		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Leave all roles unchecked to allow any logged-in customer. Role filters apply only when “Logged-in customers only” is selected.', 'phoenix-wp-gift' ) . '</p>';
		echo '</fieldset></td></tr>';
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function render_schedule_fields( array $rule ): void {
		$date_start = (string) ( $rule['date_start'] ?? '' );
		$date_end   = (string) ( $rule['date_end'] ?? '' );
		$weekdays   = Schedule_Evaluator::normalize_weekdays( $rule['weekdays'] ?? null );

		echo '<tr><th scope="row">' . esc_html__( 'Schedule', 'phoenix-wp-gift' ) . '</th><td>';
		echo '<fieldset class="phoenix-wp-gift-rule-schedule">';

		echo '<p><label for="phoenix-wp-gift-rule-date-start">' . esc_html__( 'Start date', 'phoenix-wp-gift' ) . '</label><br />';
		printf(
			'<input type="date" id="phoenix-wp-gift-rule-date-start" name="rule[date_start]" value="%1$s" />',
			esc_attr( $date_start )
		);
		echo '</p>';

		echo '<p><label for="phoenix-wp-gift-rule-date-end">' . esc_html__( 'End date', 'phoenix-wp-gift' ) . '</label><br />';
		printf(
			'<input type="date" id="phoenix-wp-gift-rule-date-end" name="rule[date_end]" value="%1$s" />',
			esc_attr( $date_end )
		);
		echo '</p>';

		echo '<p><span class="phoenix-wp-gift-weekdays-label">' . esc_html__( 'Weekdays', 'phoenix-wp-gift' ) . '</span><br />';
		echo '<span class="phoenix-wp-gift-weekdays">';

		foreach ( self::get_weekday_choices() as $day_value => $day_label ) {
			printf(
				'<label class="phoenix-wp-gift-weekday"><input type="checkbox" name="rule[weekdays][]" value="%1$d" %2$s /> %3$s</label> ',
				(int) $day_value,
				checked( in_array( (int) $day_value, $weekdays, true ), true, false ),
				esc_html( $day_label )
			);
		}

		echo '</span></p>';
		echo '<p class="description">';
		echo esc_html__(
			'Uses your WordPress site timezone. Leave dates empty for no date limit. Keep all weekdays checked for no weekday limit. Scheduled rules get their own tier group from the rule name so they stack with standard tiers. Set gift tier group to “default” only if this campaign should replace another tier gift.',
			'phoenix-wp-gift'
		);
		echo '</p>';
		echo '</fieldset></td></tr>';
	}

	/**
	 * Weekday choices in Monday-first order (values follow PHP date( "w" ): 0 = Sunday).
	 *
	 * @return array<int, string>
	 */
	private static function get_weekday_choices(): array {
		return array(
			1 => __( 'Monday', 'phoenix-wp-gift' ),
			2 => __( 'Tuesday', 'phoenix-wp-gift' ),
			3 => __( 'Wednesday', 'phoenix-wp-gift' ),
			4 => __( 'Thursday', 'phoenix-wp-gift' ),
			5 => __( 'Friday', 'phoenix-wp-gift' ),
			6 => __( 'Saturday', 'phoenix-wp-gift' ),
			0 => __( 'Sunday', 'phoenix-wp-gift' ),
		);
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function describe_schedule( array $rule ): string {
		if ( ! Schedule_Evaluator::has_schedule_restriction( $rule ) ) {
			return __( 'Always', 'phoenix-wp-gift' );
		}

		$parts = array();
		$start = (string) ( $rule['date_start'] ?? '' );
		$end   = (string) ( $rule['date_end'] ?? '' );

		if ( '' !== $start && '' !== $end ) {
			$parts[] = sprintf(
				/* translators: 1: start date, 2: end date */
				__( '%1$s – %2$s', 'phoenix-wp-gift' ),
				self::format_admin_date( $start ),
				self::format_admin_date( $end )
			);
		} elseif ( '' !== $start ) {
			$parts[] = sprintf(
				/* translators: %s: start date */
				__( 'From %s', 'phoenix-wp-gift' ),
				self::format_admin_date( $start )
			);
		} elseif ( '' !== $end ) {
			$parts[] = sprintf(
				/* translators: %s: end date */
				__( 'Until %s', 'phoenix-wp-gift' ),
				self::format_admin_date( $end )
			);
		}

		$weekdays = Schedule_Evaluator::normalize_weekdays( $rule['weekdays'] ?? null );

		if ( count( $weekdays ) < 7 && ! empty( $weekdays ) ) {
			$labels = array();

			foreach ( self::get_weekday_choices() as $day_value => $day_label ) {
				if ( in_array( (int) $day_value, $weekdays, true ) ) {
					$labels[] = $day_label;
				}
			}

			if ( ! empty( $labels ) ) {
				$parts[] = implode( ', ', $labels );
			}
		} elseif ( empty( $weekdays ) ) {
			$parts[] = __( 'No weekdays selected', 'phoenix-wp-gift' );
		}

		return implode( ' · ', $parts );
	}

	private static function format_admin_date( string $ymd ): string {
		$timestamp = strtotime( $ymd . ' 00:00:00' );

		if ( false === $timestamp ) {
			return $ymd;
		}

		return date_i18n( get_option( 'date_format' ), $timestamp );
	}

	/**
	 * @param array<string, mixed> $rule Rule data.
	 */
	private static function describe_condition( array $rule ): string {
		$trigger = sanitize_key( (string) ( $rule['trigger_type'] ?? Settings::TRIGGER_SUBTOTAL ) );
		$parts   = array();

		if ( Settings::TRIGGER_ITEM_QUANTITY === $trigger ) {
			$minimum = absint( $rule['min_item_quantity'] ?? 0 );

			if ( $minimum <= 0 ) {
				$parts[] = __( 'Any item in cart', 'phoenix-wp-gift' );
			} else {
				$parts[] = sprintf(
					/* translators: %d: minimum item count */
					__( 'Min. %d items', 'phoenix-wp-gift' ),
					$minimum
				);
			}
		} else {
			$minimum = (float) ( $rule['min_subtotal'] ?? 0 );

			if ( $minimum <= 0 ) {
				$parts[] = __( 'Any cart value', 'phoenix-wp-gift' );
			} else {
				$parts[] = sprintf(
					/* translators: %s: formatted money amount */
					__( 'Min. %s gross', 'phoenix-wp-gift' ),
					wp_strip_all_tags( wc_price( $minimum ) )
				);
			}
		}

		$cart_content_label = Cart_Content_Evaluator::get_display_label( $rule );

		if ( '' !== $cart_content_label ) {
			$parts[] = $cart_content_label;
		}

		return implode( ' + ', $parts );
	}

	public static function handle_save(): void {
		self::assert_cap_and_nonce();

		$raw = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();

		if ( ! isset( $raw['weekdays'] ) ) {
			$raw['weekdays'] = array();
		}

		if ( ! isset( $raw['user_roles'] ) ) {
			$raw['user_roles'] = array();
		}

		if ( ! isset( $raw['require_product_ids'] ) ) {
			$raw['require_product_ids'] = array();
		}

		if ( ! isset( $raw['require_category_ids'] ) ) {
			$raw['require_category_ids'] = array();
		}

		if ( ! isset( $raw['require_tag_ids'] ) ) {
			$raw['require_tag_ids'] = array();
		}

		if ( ! isset( $raw['gift_options'] ) ) {
			$raw['gift_options'] = array();
		}

		$rule = Rules_Repository::instance()->sanitize_rule( $raw );

		if ( '' === trim( (string) ( $rule['name'] ?? '' ) ) ) {
			self::redirect( 'error' );
		}

		$validation_error = Gift_Options_Helper::validate_for_save( $rule );

		if ( null !== $validation_error ) {
			self::redirect( 'error' );
		}

		Rules_Repository::instance()->save( $rule );

		self::redirect( 'saved' );
	}

	public static function handle_delete(): void {
		self::assert_cap_and_nonce();

		$id = isset( $_GET['rule_id'] ) ? sanitize_key( wp_unslash( (string) $_GET['rule_id'] ) ) : '';

		if ( '' === $id ) {
			self::redirect( 'error' );
		}

		if ( null === Rules_Repository::instance()->get( $id ) ) {
			self::redirect( 'not_found' );
		}

		Rules_Repository::instance()->delete( $id );

		self::redirect( 'deleted' );
	}

	public static function handle_duplicate(): void {
		self::assert_cap_and_nonce();

		$id = isset( $_GET['rule_id'] ) ? sanitize_key( wp_unslash( (string) $_GET['rule_id'] ) ) : '';

		if ( '' === $id || null === Rules_Repository::instance()->duplicate( $id ) ) {
			self::redirect( 'error' );
		}

		self::redirect( 'duplicated' );
	}

	private static function assert_cap_and_nonce(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage gift rules.', 'phoenix-wp-gift' ) );
		}

		check_admin_referer( self::NONCE_ACTION );
	}

	private static function action_url( string $action, string $rule_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => $action,
					'rule_id' => $rule_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION
		);
	}

	private static function redirect( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'               => Menu::PAGE_SLUG,
					'gift_rules_notice'  => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
