/**
 * Adds phoenix-gift-for-woocommerce-cart-item class on Cart/Checkout block line items.
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

	const giftProductIds = Array.isArray( window.phoenixWpGift.giftProductIds )
		? window.phoenixWpGift.giftProductIds.map( function ( id ) {
				return parseInt( id, 10 );
		  } )
		: [];

	const legacyGiftProductId = parseInt( window.phoenixWpGift.giftProductId, 10 );

	if ( legacyGiftProductId && ! giftProductIds.includes( legacyGiftProductId ) ) {
		giftProductIds.push( legacyGiftProductId );
	}

	if ( ! giftProductIds.length ) {
		return;
	}

	const { registerCheckoutFilters } = window.wc.blocksCheckout;

	registerCheckoutFilters( 'phoenix-gift-for-woocommerce', {
		cartItemClass( defaultValue, extensions, args ) {
			const itemId = parseInt( args?.cartItem?.id, 10 );

			if ( ! giftProductIds.includes( itemId ) ) {
				return defaultValue;
			}

			return ( defaultValue + ' phoenix-gift-for-woocommerce-cart-item' ).trim();
		},
	} );
} )();
