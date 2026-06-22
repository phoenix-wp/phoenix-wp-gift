<?php
/**
 * Freemius SDK bootstrap for PhoenixWP Gift Product.
 *
 * Connect opt-in on first activation — see phoenix-wp-core/docs/FREEMIUS-CONNECT-OPTIN.md
 *
 * @package PhoenixWP\Gift
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'phoenix_wp_gift_fs' ) ) {
	/**
	 * Freemius SDK instance for Gift (product 31421).
	 *
	 * @return \Freemius
	 */
	function phoenix_wp_gift_fs() {
		global $phoenix_wp_gift_fs;

		if ( ! isset( $phoenix_wp_gift_fs ) ) {
			if ( ! defined( 'WP_FS__PRODUCT_31421_MULTISITE' ) ) {
				define( 'WP_FS__PRODUCT_31421_MULTISITE', true );
			}

			require_once PHOENIX_GIFT_FOR_WOOCOMMERCE_PATH . 'vendor/freemius/start.php';

			$parent_slug = ( defined( 'PHOENIX_WP_CORE_VERSION' ) || class_exists( \PhoenixWP\Core\Plugin::class, false ) )
				? 'phoenix-wp-core'
				: 'phoenix-gift-for-woocommerce';

			$init = array(
				'id'                  => '31421',
				'slug'                => 'phoenix-gift-for-woocommerce',
				'type'                => 'plugin',
				'public_key'          => 'pk_9aad59dcbbfc8507b58947ba8d61a',
				'is_premium'          => false,
				'premium_suffix'      => 'Pro',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'has_free_plan'       => true,
				'is_org_compliant'    => true,
				'trial'               => array(
					'days'               => 0,
					'is_require_payment' => false,
				),
				'menu'                => array(
					'slug'       => 'phoenix-gift-for-woocommerce',
					// Must be a path relative to wp-admin (admin.php?page=…), not the slug alone.
					'first-path' => 'admin.php?page=phoenix-gift-for-woocommerce',
					'account'    => true,
					'contact'    => false,
					'support'    => false,
					'parent'     => array(
						'slug' => $parent_slug,
					),
				),
				'is_live'             => true,
			);

			if ( defined( 'PHOENIX_GIFT_FOR_WOOCOMMERCE_FS_SECRET_KEY' ) && PHOENIX_GIFT_FOR_WOOCOMMERCE_FS_SECRET_KEY !== '' ) {
				$init['secret_key'] = PHOENIX_GIFT_FOR_WOOCOMMERCE_FS_SECRET_KEY;
			}

			$phoenix_wp_gift_fs = fs_dynamic_init( $init );

			$phoenix_wp_gift_fs->add_filter( 'pricing/show_annual_in_monthly', '__return_false' );
		}

		return $phoenix_wp_gift_fs;
	}

	phoenix_wp_gift_fs();

	/**
	 * Fires after Freemius SDK is loaded for Gift.
	 */
	do_action( 'phoenix_wp_gift_fs_loaded' );
}
