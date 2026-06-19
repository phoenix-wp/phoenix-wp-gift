/**
 * Refreshes and submits customer gift choice pickers (classic + blocks).
 *
 * @package PhoenixWP\Gift
 */
( function ( $ ) {
	'use strict';

	const config = window.phoenixWpGiftChoice || {};

	if ( ! config.restUrl ) {
		return;
	}

	let refreshTimer = null;
	let blocksLastSig = '';
	let blocksInitialized = false;
	let isSubmitting = false;

	function getCartAnchor() {
		return document.querySelector(
			'.wp-block-woocommerce-cart, .wp-block-woocommerce-checkout, form.woocommerce-cart-form'
		);
	}

	function ensureRoot() {
		let root = document.querySelector( '.phoenix-gift-for-woocommerce-choice-root' );

		if ( root ) {
			return root;
		}

		const anchor = getCartAnchor();

		if ( ! anchor || ! anchor.parentNode ) {
			return null;
		}

		root = document.createElement( 'div' );
		root.className = 'phoenix-gift-for-woocommerce-choice-root phoenix-gift-for-woocommerce-choice-root--empty';
		root.setAttribute( 'data-rule', '' );
		anchor.parentNode.insertBefore( root, anchor );

		return root;
	}

	function scheduleRefresh() {
		if ( refreshTimer ) {
			clearTimeout( refreshTimer );
		}

		refreshTimer = setTimeout( refreshChoice, 300 );
	}

	function applyResponse( root, data ) {
		const html = data && typeof data.html === 'string' ? data.html : '';
		const empty = ! html || ( data && data.empty );

		root.innerHTML = html;
		root.classList.toggle( 'phoenix-gift-for-woocommerce-choice-root--empty', empty );
	}

	function showNotice( message, type ) {
		if ( ! message ) {
			return;
		}

		const wrapper =
			document.querySelector( '.woocommerce-notices-wrapper' ) ||
			document.querySelector( '.wc-block-components-notices' ) ||
			document.querySelector( '.wp-block-woocommerce-cart' );

		if ( ! wrapper ) {
			return;
		}

		const notice = document.createElement( 'div' );
		notice.className = 'woocommerce-' + type;
		notice.setAttribute( 'role', 'alert' );
		notice.textContent = message;
		wrapper.prepend( notice );
	}

	function refreshBlocksCart() {
		if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
			const dispatch = window.wp.data.dispatch( 'wc/store/cart' );

			if ( dispatch && typeof dispatch.invalidateResolutionForStore === 'function' ) {
				dispatch.invalidateResolutionForStore();
			}
		}

		$( document.body ).trigger( 'wc_fragment_refresh' );
		$( document.body ).trigger( 'added_to_cart' );
	}

	async function refreshChoice() {
		if ( isSubmitting ) {
			return;
		}

		const root = ensureRoot();

		if ( ! root ) {
			return;
		}

		const url = new URL( config.restUrl, window.location.origin );
		url.searchParams.set( 'rule', root.getAttribute( 'data-rule' ) || '' );

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
			// Retry on the next cart event.
		}
	}

	function collectOptionsMap( form ) {
		const map = {};

		form.querySelectorAll( 'input[name^="gift_options_map"]' ).forEach( function ( input ) {
			const name = input.getAttribute( 'name' ) || '';
			const match = name.match( /^gift_options_map\[([^\]]+)\]\[(product_id|variation_id)\]$/ );

			if ( ! match ) {
				return;
			}

			if ( ! map[ match[1] ] ) {
				map[ match[1] ] = {};
			}

			map[ match[1] ][ match[2] ] = input.value;
		} );

		return map;
	}

	async function submitChoiceForm( form ) {
		if ( isSubmitting || ! config.selectUrl ) {
			return;
		}

		const ruleId = form.querySelector( 'input[name="rule_id"]' );
		const selected = form.querySelector( 'input[name="gift_option_key"]:checked' );

		if ( ! selected ) {
			showNotice( config.i18n && config.i18n.chooseOption ? config.i18n.chooseOption : 'Please choose a gift option.', 'error' );
			return;
		}

		const submitButton = form.querySelector( '.phoenix-gift-for-woocommerce-choice__submit' );

		isSubmitting = true;

		if ( submitButton ) {
			submitButton.disabled = true;
		}

		try {
			const response = await fetch( config.selectUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce || '',
				},
				body: JSON.stringify( {
					rule_id: ruleId ? ruleId.value : '',
					gift_option_key: selected.value,
					gift_options_map: collectOptionsMap( form ),
				} ),
			} );

			const data = await response.json();

			if ( ! response.ok ) {
				showNotice(
					data && data.message ? data.message : ( config.i18n && config.i18n.genericError ? config.i18n.genericError : 'Could not add the gift.' ),
					'error'
				);
				return;
			}

			showNotice(
				data && data.message ? data.message : ( config.i18n && config.i18n.added ? config.i18n.added : 'Gift added.' ),
				'success'
			);

			refreshBlocksCart();
			await refreshChoice();
		} catch ( error ) {
			showNotice( config.i18n && config.i18n.genericError ? config.i18n.genericError : 'Could not add the gift.', 'error' );
		} finally {
			isSubmitting = false;

			if ( submitButton ) {
				submitButton.disabled = false;
			}
		}
	}

	function initChoiceFormHandler() {
		document.addEventListener(
			'submit',
			function ( event ) {
				const form = event.target.closest( '.phoenix-gift-for-woocommerce-choice__form' );

				if ( ! form ) {
					return;
				}

				event.preventDefault();
				submitChoiceForm( form );
			},
			true
		);
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

	function retryBlocksInit( attemptsLeft ) {
		if ( blocksInitialized || attemptsLeft <= 0 ) {
			return;
		}

		if (
			typeof window.wp === 'undefined' ||
			! window.wp.data ||
			typeof window.wp.data.subscribe !== 'function'
		) {
			window.setTimeout( function () {
				retryBlocksInit( attemptsLeft - 1 );
			}, 500 );
			return;
		}

		const select = window.wp.data.select;
		const cartStore = select( 'wc/store/cart' );

		if ( ! cartStore || typeof cartStore.getCartData !== 'function' ) {
			window.setTimeout( function () {
				retryBlocksInit( attemptsLeft - 1 );
			}, 500 );
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

	function init() {
		ensureRoot();
		initChoiceFormHandler();
		initClassicCartEvents();
		retryBlocksInit( 12 );
		refreshChoice();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( jQuery );
