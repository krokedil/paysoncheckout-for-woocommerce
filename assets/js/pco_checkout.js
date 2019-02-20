jQuery(function($) {
	const pco_wc = {
		bodyEl: $('body'),

		// Order notes.
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#payson-checkout-select-other',

		// Address data.
		addressData: [],

		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			pco_wc.pcoFreeze;
		},

		/*
		 * Check if Payson is the selected gateway.
		 */
		checkIfPaysonSelected: function() {
			if (pco_wc.paymentMethodEl.length > 0) {
				pco_wc.paymentMethod = pco_wc.paymentMethodEl.filter(':checked').val();
				return false;
			} else {
				pco_wc.paymentMethod = 'paysoncheckout';
				return true;
			}
		},

		/*
		 * Runs when PaysonEmbeddedAddressChanged is triggered ( address changed ).
		 */
		addressChanged: function( data /* Address from Payson */ ) {
			let address = data.detail;
			console.table( address );
			$.ajax({
				type: 'POST',
				url: pco_wc_params.address_changed_url,
				data: {
					address: address,
					nonce: pco_wc_params.address_changed_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					// Set address data.
					pco_wc.addressData = address;
					pco_wc.setAddressData();

					// Update the checkout for postcode based shipping and resume the iFrame.
					pco_wc.bodyEl.trigger('update_checkout');
				}
			});
		},

		/*
		 * Update the payson order. Happens on updated_checkout event.
		 */
		updatePaysonOrder: function() {
			$.ajax({
				type: 'POST',
				url: pco_wc_params.update_order_url,
				data: {
					nonce: pco_wc_params.update_order_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					let result = data.responseJSON;
					if( ! result.success ) {
						// Remove any old errors from Payson and print new error.
						$('.payson-error').remove();
						$('.woocommerce-checkout-review-order-table').after( '<ul class="payson-error woocommerce-error" role="alert"><li>' + result.data + '</li></ul>' );
					} else {
						// Set address data if we have it.
						if( result.hasOwnProperty( 'data' ) ) {
							pco_wc.addressData = result.data;
							pco_wc.setAddressData();
						}
						// Update the iFrame, remove any error messages and resume the iFrame.
						pco_wc.pcoUpdate();
						$('.payson-error').remove();
						pco_wc.pcoResume();
					}
				}
			});
		},

		/*
		 * Sets the WooCommerce form field data.
		 */
		setAddressData: function() {
			// Billing fields.
			$('#billing_first_name').val( pco_wc.addressData.firstName );
			$('#billing_last_name').val( pco_wc.addressData.lastName );
			$('#billing_address_1').val( pco_wc.addressData.street );
			$('#billing_city').val( pco_wc.addressData.city );
			$('#billing_postcode').val( pco_wc.addressData.postalCode );
			$('#billing_phone').val( pco_wc.addressData.phone )
			$('#billing_email').val( pco_wc.addressData.email );

			// Shipping fields.
			$('#shipping_first_name').val( pco_wc.addressData.firstName );
			$('#shipping_last_name').val( pco_wc.addressData.lastName );
			$('#shipping_address_1').val( pco_wc.addressData.street );
			$('#shipping_city').val( pco_wc.addressData.city );
			$('#shipping_postcode').val( pco_wc.addressData.postalCode );

			// Only set country fields if we have data in them.
			if ( pco_wc.addressData.countryCode ) {
				$('#billing_country').val( pco_wc.addressData.countryCode );
				$('#shipping_country').val( pco_wc.addressData.countryCode );
			}

		},

		/*
		 * Runs when PaysonEmbeddedCheckoutResult is triggered ( payment status changed ).
		 */
		checkoutResult: function( data /* Result from Payson */ ) {
			console.log( 'Payment status changed.' );
		},

		/*
		 * Locks the iFrame. 
		 */
		pcoFreeze: function() {
			let iframe = document.getElementById('paysonIframe');
			iframe.contentWindow.postMessage('lock', '*');
		},

		/*
		 * Unlocks the iFrame. 
		 */
		pcoResume: function() {
			let iframe = document.getElementById('paysonIframe');
    		iframe.contentWindow.postMessage('release', '*');
		},

		/*
		 * Updates the iFrame.
		 */
		pcoUpdate: function() {
			let iframe = document.getElementById('paysonIframe');
    		iframe.contentWindow.postMessage('updatePage', '*');
		},

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			$(document).ready( pco_wc.documentReady() );

			// Payson Event listeners.
			document.addEventListener( 'PaysonEmbeddedAddressChanged', function( data ) { pco_wc.addressChanged( data ) } );
			document.addEventListener( 'PaysonEmbeddedCheckoutResult', function( data ) { pco_wc.checkoutResult( data ) } );

			// Update Checkout.
			pco_wc.bodyEl.on('update_checkout', pco_wc.pcoFreeze );
			// Updated Checkout.
			pco_wc.bodyEl.on('updated_checkout', pco_wc.updatePaysonOrder );
		},
	}
	pco_wc.init();
});