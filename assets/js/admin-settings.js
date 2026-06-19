( function () {
	'use strict';

	function toggleTriggerRows() {
		var subtotalRadio = document.querySelector(
			'input[name="phoenix_wp_gift_settings[trigger_type]"][value="subtotal"]'
		);
		var subtotalRow = document.querySelector( '.phoenix-gift-for-woocommerce-trigger-subtotal' );
		var quantityRow = document.querySelector( '.phoenix-gift-for-woocommerce-trigger-quantity' );

		if ( ! subtotalRadio || ! subtotalRow || ! quantityRow ) {
			return;
		}

		var useSubtotal = subtotalRadio.checked;
		subtotalRow.style.display = useSubtotal ? '' : 'none';
		quantityRow.style.display = useSubtotal ? 'none' : '';
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var radios = document.querySelectorAll(
			'input[name="phoenix_wp_gift_settings[trigger_type]"]'
		);

		if ( ! radios.length ) {
			return;
		}

		radios.forEach( function ( radio ) {
			radio.addEventListener( 'change', toggleTriggerRows );
		} );

		toggleTriggerRows();
	} );
} )();
