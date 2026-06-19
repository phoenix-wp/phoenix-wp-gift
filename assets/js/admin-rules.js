( function () {
	'use strict';

	const config = window.phoenixWpGiftRules || {};

	function toggleRuleTriggerRows() {
		var subtotalRadio = document.querySelector(
			'input.phoenix-gift-for-woocommerce-rule-trigger[value="subtotal"]'
		);
		var subtotalRow = document.querySelector( '.phoenix-gift-for-woocommerce-rule-trigger-subtotal' );
		var quantityRow = document.querySelector( '.phoenix-gift-for-woocommerce-rule-trigger-quantity' );

		if ( ! subtotalRadio || ! subtotalRow || ! quantityRow ) {
			return;
		}

		var useSubtotal = subtotalRadio.checked;
		subtotalRow.style.display = useSubtotal ? '' : 'none';
		quantityRow.style.display = useSubtotal ? 'none' : '';
	}

	function toggleAudienceRoleFilters() {
		var loggedInRadio = document.querySelector(
			'input.phoenix-gift-for-woocommerce-audience[value="logged_in"]'
		);
		var roleFilters = document.querySelector( '.phoenix-gift-for-woocommerce-role-filters' );

		if ( ! roleFilters ) {
			return;
		}

		var showRoles = loggedInRadio && loggedInRadio.checked;
		roleFilters.style.display = showRoles ? '' : 'none';
	}

	function getOptionsContainer() {
		return document.getElementById( 'phoenix-gift-for-woocommerce-options' );
	}

	function getNextOptionIndex( container ) {
		var maxIndex = -1;

		container.querySelectorAll( '.phoenix-gift-for-woocommerce-option-row' ).forEach( function ( row ) {
			var index = parseInt( row.getAttribute( 'data-index' ), 10 );

			if ( ! isNaN( index ) && index > maxIndex ) {
				maxIndex = index;
			}
		} );

		return maxIndex + 1;
	}

	function replaceIndexInNode( node, index ) {
		if ( node.hasAttribute && node.hasAttribute( 'data-index' ) ) {
			node.setAttribute( 'data-index', String( index ) );
		}

		if ( node.hasAttribute && node.hasAttribute( 'name' ) ) {
			node.setAttribute(
				'name',
				node.getAttribute( 'name' ).replace( /__INDEX__/g, String( index ) )
			);
		}

		Array.prototype.forEach.call( node.children || [], function ( child ) {
			replaceIndexInNode( child, index );
		} );
	}

	function addGiftOptionRow() {
		var container = getOptionsContainer();
		var template = document.getElementById( 'phoenix-gift-for-woocommerce-option-template' );

		if ( ! container || ! template || ! template.content ) {
			return;
		}

		var index = getNextOptionIndex( container );
		var clone = template.content.firstElementChild.cloneNode( true );
		replaceIndexInNode( clone, index );
		container.appendChild( clone );
		bindGiftOptionRow( clone );
	}

	function populateVariationSelect( variationSelect, variations, selectedId ) {
		variationSelect.innerHTML = '';
		var placeholder = document.createElement( 'option' );
		placeholder.value = '0';
		placeholder.textContent = '— Select variation —';
		variationSelect.appendChild( placeholder );

		variations.forEach( function ( variation ) {
			var option = document.createElement( 'option' );
			option.value = String( variation.id );
			option.textContent = variation.label;

			if ( parseInt( selectedId, 10 ) === parseInt( variation.id, 10 ) ) {
				option.selected = true;
			}

			variationSelect.appendChild( option );
		} );
	}

	function loadVariationsForRow( row, selectedVariationId ) {
		var productSelect = row.querySelector( '.phoenix-gift-for-woocommerce-option-product' );
		var variationSelect = row.querySelector( '.phoenix-gift-for-woocommerce-option-variation' );

		if ( ! productSelect || ! variationSelect ) {
			return;
		}

		var productId = parseInt( productSelect.value, 10 );
		var selectedOption = productSelect.options[ productSelect.selectedIndex ];
		var productType = selectedOption ? selectedOption.getAttribute( 'data-type' ) : '';

		if ( ! productId || productType !== 'variable' ) {
			variationSelect.style.display = 'none';
			variationSelect.value = '0';
			return;
		}

		if ( ! config.ajaxUrl ) {
			variationSelect.style.display = '';
			return;
		}

		var url = new URL( config.ajaxUrl, window.location.origin );
		url.searchParams.set( 'action', 'phoenix_wp_gift_variations' );
		url.searchParams.set( 'product_id', String( productId ) );
		url.searchParams.set( 'nonce', config.variationsNonce || '' );

		fetch( url.toString(), {
			credentials: 'same-origin',
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}

				populateVariationSelect(
					variationSelect,
					payload.data.variations || [],
					selectedVariationId || variationSelect.value
				);
				variationSelect.style.display = '';
			} )
			.catch( function () {
				variationSelect.style.display = '';
			} );
	}

	function bindGiftOptionRow( row ) {
		var productSelect = row.querySelector( '.phoenix-gift-for-woocommerce-option-product' );
		var removeButton = row.querySelector( '.phoenix-gift-for-woocommerce-remove-option' );

		if ( productSelect ) {
			productSelect.addEventListener( 'change', function () {
				loadVariationsForRow( row, 0 );
			} );
			loadVariationsForRow( row, row.querySelector( '.phoenix-gift-for-woocommerce-option-variation' )?.value || 0 );
		}

		if ( removeButton ) {
			removeButton.addEventListener( 'click', function () {
				var container = getOptionsContainer();

				if ( ! container ) {
					return;
				}

				if ( container.querySelectorAll( '.phoenix-gift-for-woocommerce-option-row' ).length <= 1 ) {
					return;
				}

				row.remove();
			} );
		}
	}

	function initGiftOptions() {
		var container = getOptionsContainer();

		if ( ! container ) {
			return;
		}

		container.querySelectorAll( '.phoenix-gift-for-woocommerce-option-row' ).forEach( function ( row ) {
			bindGiftOptionRow( row );
		} );

		var addButton = document.querySelector( '.phoenix-gift-for-woocommerce-add-option' );

		if ( addButton ) {
			addButton.addEventListener( 'click', addGiftOptionRow );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var triggerRadios = document.querySelectorAll( 'input.phoenix-gift-for-woocommerce-rule-trigger' );

		triggerRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', toggleRuleTriggerRows );
		} );

		toggleRuleTriggerRows();

		var audienceRadios = document.querySelectorAll( 'input.phoenix-gift-for-woocommerce-audience' );

		audienceRadios.forEach( function ( radio ) {
			radio.addEventListener( 'change', toggleAudienceRoleFilters );
		} );

		toggleAudienceRoleFilters();
		initGiftOptions();
	} );
} )();
