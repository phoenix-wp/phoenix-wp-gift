/**
 * Adds phoenix-wp-gift-cart-item class on Cart/Checkout block line items.
 *
 * @package PhoenixWP\Gift
 */
( function () {
	'use strict';

	if (
		typeof window.wc === 'undefined' ||
		! window.wc.blocksCheckout ||
		typeof window.phoenixWpGift === 'undefined'
	) {
		return;
	}

	const giftProductId = parseInt( window.phoenixWpGift.giftProductId, 10 );

	if ( ! giftProductId ) {
		return;
	}

	const { registerCheckoutFilters } = window.wc.blocksCheckout;

	registerCheckoutFilters( 'phoenix-wp-gift', {
		cartItemClass( defaultValue, extensions, args ) {
			const itemId = parseInt( args?.cartItem?.id, 10 );

			if ( itemId !== giftProductId ) {
				return defaultValue;
			}

			return ( defaultValue + ' phoenix-wp-gift-cart-item' ).trim();
		},
	} );
} )();
