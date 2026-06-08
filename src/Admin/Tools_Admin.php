<?php
/**
 * Import and export tools for Pro gift rules.
 *
 * @package PhoenixWP\Gift
 */

declare(strict_types=1);

namespace PhoenixWP\Gift\Admin;

use PhoenixWP\Gift\Rules\Rules_Exporter;
use PhoenixWP\Gift\Rules\Rules_Importer;
use PhoenixWP\Gift\Rules\Rules_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Admin UI and handlers for JSON rule import/export.
 */
final class Tools_Admin {

	private const ACTION_EXPORT = 'phoenix_wp_gift_export_rules';
	private const ACTION_IMPORT = 'phoenix_wp_gift_import_rules';
	private const NONCE_ACTION  = 'phoenix_wp_gift_tools';

	public static function register_hooks(): void {
		add_action( 'admin_post_' . self::ACTION_EXPORT, array( self::class, 'handle_export' ) );
		add_action( 'admin_post_' . self::ACTION_IMPORT, array( self::class, 'handle_import' ) );
	}

	public static function render_section(): void {
		if ( ! phoenix_wp_gift_is_pro_active( 'import_export' ) ) {
			return;
		}

		echo '<hr /><h2>' . esc_html__( 'Import / Export', 'phoenix-wp-gift' ) . '</h2>';
		echo '<p class="description">';
		echo esc_html__(
			'Back up or migrate Pro gift rules as JSON. Product IDs refer to products on this site — remap products after importing on another shop.',
			'phoenix-wp-gift'
		);
		echo '</p>';

		self::render_notices();

		echo '<div class="phoenix-wp-gift-tools">';

		printf(
			'<p><a class="button button-secondary" href="%1$s">%2$s</a></p>',
			esc_url( self::export_url() ),
			esc_html__( 'Export rules (JSON)', 'phoenix-wp-gift' )
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data" class="phoenix-wp-gift-import-form">';
		wp_nonce_field( self::NONCE_ACTION );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_IMPORT ) . '" />';

		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="phoenix-wp-gift-import-file">' . esc_html__( 'Import file', 'phoenix-wp-gift' ) . '</label></th><td>';
		echo '<input type="file" id="phoenix-wp-gift-import-file" name="import_file" accept="application/json,.json" required />';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Import mode', 'phoenix-wp-gift' ) . '</th><td>';
		echo '<fieldset>';
		printf(
			'<label><input type="radio" name="import_mode" value="%1$s" checked /> %2$s</label><br />',
			esc_attr( Rules_Importer::MODE_MERGE ),
			esc_html__( 'Merge — add imported rules; duplicate IDs get new IDs', 'phoenix-wp-gift' )
		);
		printf(
			'<label><input type="radio" name="import_mode" value="%1$s" /> %2$s</label>',
			esc_attr( Rules_Importer::MODE_REPLACE ),
			esc_html__( 'Replace — remove all existing rules and import the file', 'phoenix-wp-gift' )
		);
		echo '</fieldset></td></tr>';
		echo '</table>';

		submit_button( __( 'Import rules', 'phoenix-wp-gift' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	private static function render_notices(): void {
		if ( empty( $_GET['gift_tools_notice'] ) ) {
			return;
		}

		$code = sanitize_key( wp_unslash( (string) $_GET['gift_tools_notice'] ) );
		$map  = array(
			'imported' => __( 'Rules imported successfully.', 'phoenix-wp-gift' ),
			'error'    => __( 'Import failed. Upload a valid JSON export file and try again.', 'phoenix-wp-gift' ),
		);

		if ( ! isset( $map[ $code ] ) ) {
			if ( ! str_starts_with( $code, 'imported_' ) ) {
				return;
			}

			$parts = explode( '_', $code );

			if ( count( $parts ) < 3 ) {
				return;
			}

			$imported = absint( $parts[1] ?? 0 );
			$skipped  = absint( $parts[2] ?? 0 );

			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: imported count, 2: skipped count */
						__( 'Imported %1$d rule(s). Skipped %2$d invalid row(s).', 'phoenix-wp-gift' ),
						$imported,
						$skipped
					)
				)
			);

			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'error' === $code ? 'error' : 'success',
			esc_html( $map[ $code ] )
		);
	}

	public static function handle_export(): void {
		self::assert_cap_and_nonce();

		if ( ! phoenix_wp_gift_is_pro_active( 'import_export' ) ) {
			wp_die( esc_html__( 'Gift Pro import/export is not available on this site.', 'phoenix-wp-gift' ) );
		}

		$filename = 'phoenix-wp-gift-rules-' . gmdate( 'Y-m-d' ) . '.json';

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo Rules_Exporter::to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	public static function handle_import(): void {
		self::assert_cap_and_nonce();

		if ( ! phoenix_wp_gift_is_pro_active( 'import_export' ) ) {
			wp_die( esc_html__( 'Gift Pro import/export is not available on this site.', 'phoenix-wp-gift' ) );
		}

		if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( wp_unslash( (string) $_FILES['import_file']['tmp_name'] ) ) ) {
			self::redirect( 'error' );
		}

		$json = file_get_contents( wp_unslash( (string) $_FILES['import_file']['tmp_name'] ) );

		if ( ! is_string( $json ) || '' === $json ) {
			self::redirect( 'error' );
		}

		$payload = Rules_Importer::parse_payload( $json );

		if ( null === $payload ) {
			self::redirect( 'error' );
		}

		$mode = isset( $_POST['import_mode'] )
			? sanitize_key( wp_unslash( (string) $_POST['import_mode'] ) )
			: Rules_Importer::MODE_MERGE;

		$result = Rules_Importer::import_rules( $payload['rules'], $mode );

		Rules_Repository::instance()->flush_cache();

		if ( $result['imported'] <= 0 && $result['skipped'] > 0 ) {
			self::redirect( 'error' );
		}

		self::redirect( 'imported_' . (string) $result['imported'] . '_' . (string) $result['skipped'] );
	}

	private static function export_url(): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION_EXPORT,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION
		);
	}

	private static function assert_cap_and_nonce(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage gift tools.', 'phoenix-wp-gift' ) );
		}

		check_admin_referer( self::NONCE_ACTION );
	}

	private static function redirect( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => Menu::PAGE_SLUG,
					'gift_tools_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
