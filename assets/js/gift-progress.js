/**
 * Refreshes gift progress hints when the cart changes (classic + blocks).
 *
 * @package PhoenixWP\Gift
 */
( function ( $ ) {
	'use strict';

	const config = window.phoenixWpGiftProgress || {};

	if ( ! config.restUrl ) {
		return;
	}

	let refreshTimer = null;
	let blocksLastSig = '';
	let blocksInitialized = false;

	function getRoots() {
		return document.querySelectorAll( '.phoenix-wp-gift-progress-root' );
	}

	function scheduleRefresh() {
		if ( refreshTimer ) {
			clearTimeout( refreshTimer );
		}

		refreshTimer = setTimeout( refreshAll, 300 );
	}

	function applyResponse( root, data ) {
		const html = data && typeof data.html === 'string' ? data.html : '';
		const empty = ! html || ( data && data.empty );

		root.innerHTML = html;
		root.classList.toggle( 'phoenix-wp-gift-progress-root--empty', empty );
	}

	async function refreshRoot( root ) {
		const url = new URL( config.restUrl, window.location.origin );

		url.searchParams.set( 'rule', root.getAttribute( 'data-rule' ) || '' );
		url.searchParams.set( 'bar', root.getAttribute( 'data-bar' ) || '1' );
		url.searchParams.set( 'class', root.getAttribute( 'data-class' ) || '' );

		try {
			const response = await fetch( url.toString(), {
				method: 'GET',
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': config.nonce || '',
				},
			} );

			if ( ! response.ok ) {
				return;
			}

			const data = await response.json();
			applyResponse( root, data );
		} catch ( error ) {
			// Ignore network errors; the next cart event will retry.
		}
	}

	function refreshAll() {
		const roots = getRoots();

		if ( ! roots.length ) {
			return;
		}

		roots.forEach( function ( root ) {
			refreshRoot( root );
		} );
	}

	function initClassicCartEvents() {
		$( document.body ).on(
			'updated_wc_div updated_cart_totals wc_fragments_refreshed added_to_cart removed_from_cart wc_cart_emptied',
			scheduleRefresh
		);
	}

	function getBlocksCartSignature( cartData ) {
		if ( ! cartData || ! Array.isArray( cartData.items ) ) {
			return '';
		}

		return cartData.items
			.map( function ( item ) {
				return String( item.id ) + ':' + String( item.quantity );
			} )
			.join( '|' );
	}

	function initBlocksCartEvents() {
		if ( blocksInitialized ) {
			return;
		}

		if (
			typeof window.wp === 'undefined' ||
			! window.wp.data ||
			typeof window.wp.data.subscribe !== 'function'
		) {
			return;
		}

		const select = window.wp.data.select;

		if ( typeof select !== 'function' ) {
			return;
		}

		const cartStore = select( 'wc/store/cart' );

		if ( ! cartStore || typeof cartStore.getCartData !== 'function' ) {
			return;
		}

		blocksInitialized = true;
		blocksLastSig = getBlocksCartSignature( cartStore.getCartData() );

		window.wp.data.subscribe( function () {
			const store = select( 'wc/store/cart' );

			if ( ! store || typeof store.getCartData !== 'function' ) {
				return;
			}

			const signature = getBlocksCartSignature( store.getCartData() );

			if ( signature === blocksLastSig ) {
				return;
			}

			blocksLastSig = signature;
			scheduleRefresh();
		} );
	}

	function retryBlocksInit( attemptsLeft ) {
		initBlocksCartEvents();

		if ( blocksInitialized || attemptsLeft <= 0 ) {
			return;
		}

		window.setTimeout( function () {
			retryBlocksInit( attemptsLeft - 1 );
		}, 500 );
	}

	function init() {
		if ( ! getRoots().length ) {
			return;
		}

		initClassicCartEvents();
		retryBlocksInit( 12 );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( jQuery );
