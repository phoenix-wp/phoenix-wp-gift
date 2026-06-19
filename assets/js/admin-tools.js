/**
 * Confirm destructive rule import actions.
 *
 * @package PhoenixWP\Gift
 */
( function () {
	'use strict';

	const form = document.querySelector( '.phoenix-gift-for-woocommerce-import-form' );

	if ( ! form ) {
		return;
	}

	form.addEventListener( 'submit', function ( event ) {
		const replaceInput = form.querySelector( 'input[name="import_mode"][value="replace"]' );

		if ( ! replaceInput || ! replaceInput.checked ) {
			return;
		}

		const message =
			typeof window.phoenixWpGiftTools !== 'undefined' && window.phoenixWpGiftTools.replaceConfirm
				? window.phoenixWpGiftTools.replaceConfirm
				: 'Replace all existing gift rules with the imported file? This cannot be undone.';

		if ( ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );
} )();
