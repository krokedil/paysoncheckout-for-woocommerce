jQuery(function($) {
	const pco_wc = {
		bodyEl: $('body'),
		checkoutFormSelector: 'form.checkout',

		// Order notes.
		orderNotesValue: '',
		orderNotesSelector: 'textarea#order_comments',
		orderNotesEl: $('textarea#order_comments'),

		// Payment method
		paymentMethodEl: $('input[name="payment_method"]'),
		paymentMethod: '',
		selectAnotherSelector: '#paysoncheckout-select-other',

		// Address data.
		addressData: [],

		// Extra checkout fields.
		needsUpdate: false,
		extraFieldsSelectorText: 'div#pco-extra-checkout-fields input[type="text"], div#pco-extra-checkout-fields input[type="password"], div#pco-extra-checkout-fields textarea, div#pco-extra-checkout-fields input[type="email"], div#pco-extra-checkout-fields input[type="tel"]',
		extraFieldsSelectorNonText: 'div#pco-extra-checkout-fields select, div#pco-extra-checkout-fields input[type="radio"], div#pco-extra-checkout-fields input[type="checkbox"], div#pco-extra-checkout-fields input.checkout-date-picker, input#terms input[type="checkbox"]',


		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			pco_wc.pcoFreeze;
			
			// Extra checkout fields.
			pco_wc.setFormFieldValues();
			pco_wc.moveExtraCheckoutFields();
			pco_wc.checkFormData();
			$('#order_comments').val( localStorage.getItem( 'pco_wc_order_comment' ) );
		},

		/*
		 * Check if Payson is the selected gateway.
		 */
		checkIfPaysonSelected: function() {
			if (pco_wc.paymentMethodEl.length > 0) {
				pco_wc.paymentMethod = pco_wc.paymentMethodEl.filter(':checked').val();
				if( 'paysoncheckout' === pco_wc.paymentMethod ) {
					return true;
				}
			} 
			return false;
		},

		/*
		 * Runs when PaysonEmbeddedAddressChanged is triggered ( address changed ).
		 */
		addressChanged: function( data /* Address from Payson */ ) {
			let address = data.detail;
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
						console.log( result.data );
						// Set address data if we have it.
						if( result.data.address !== null ) {
							pco_wc.addressData = result.data.address;
							pco_wc.setAddressData();
						}
						// Update the iFrame if needed.
						if( result.data.changed === true ) {
							pco_wc.pcoUpdate();
						}
						// Remove any error messages and resume the iFrame.
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

		// When "Change to another payment method" is clicked.
		changeFromPco: function(e) {
			e.preventDefault();

			$(pco_wc.checkoutFormSelector).block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$.ajax({
				type: 'POST',
				dataType: 'json',
				data: {
					pco: false,
					nonce: pco_wc_params.change_payment_method_nonce
				},
				url: pco_wc_params.change_payment_method_url,
				success: function (data) {},
				error: function (data) {},
				complete: function (data) {
					console.log( data );
					window.location.href = data.responseJSON.data.redirect;
				}
			});
		},

		// When payment method is changed to PCO in regular WC Checkout page.
		maybeChangeToPco: function() {
			if ( 'paysoncheckout' === $(this).val() ) {

				$(pco_wc.checkoutFormSelector).block({
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6
					}
				});

				$('.woocommerce-info').remove();

				$.ajax({
					type: 'POST',
					data: {
						pco: true,
						nonce: pco_wc_params.change_payment_method_nonce
					},
					dataType: 'json',
					url: pco_wc_params.change_payment_method_url,
					success: function (data) {},
					error: function (data) {},
					complete: function (data) {
						window.location.href = data.responseJSON.data.redirect;
					}
				});
			}
		},

		/**
		 * Updates the order comment local storage.
		 */
		updateOrderComment: function() {
			let val = $('#order_comments').val();
			localStorage.setItem( 'pco_wc_order_comment', val );
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

		/**
		 * Checks for form Data on the page, and sets the checkout fields session storage.
		 */
		checkFormData: function() {
			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
				let requiredFields = [];
				let fieldData = {};
				// Get all form fields.
				for ( i = 0; i < form.length; i++ ) { 
					// Check if the form has a name set.
					if ( form[i]['name'] !== '' ) {
						let name    = form[i]['name'];
						let field = $('*[name="' + name + '"]');
						let required = ( $('p#' + name + '_field').hasClass('validate-required') ? true : false );
						// Only keep track of non standard WooCommerce checkout fields
						if ($.inArray(name, pco_wc_params.standard_woo_checkout_fields) == '-1' && name.indexOf('[qty]') < 0 && name.indexOf( 'shipping_method' ) < 0 && name.indexOf( 'payment_method' ) < 0 ) {
							// Only keep track of required fields for validation.
							if ( required === true ) {
								requiredFields.push(name);
							}
							// Get the value from the field.
							let value = '';
							if( field.is(':checkbox') ) {
								if( field.is(':checked') ) {
									value = form[i].value;
								}
							} else if( field.is(':radio') ) {
								if( field.is(':checked') ) {
									value = $( 'input[name="' + name + '"]:checked').val();
								}
							} else {
								value = form[i].value
							}
							// Set field data with values.
							fieldData[name] = value;
						}
					}
				}
				sessionStorage.setItem( 'PCORequiredFields', JSON.stringify( requiredFields ) );
				sessionStorage.setItem( 'PCOFieldData', JSON.stringify( fieldData ) );
				pco_wc.needsUpdate = true;
				pco_wc.validateRequiredFields();
		},

		/**
		 * Validates the required fields, checks if they have a value set.
		 */
		validateRequiredFields: function() {
			// Get data from session storage.
			let requiredFields = JSON.parse( sessionStorage.getItem( 'PCORequiredFields' ) );
			let fieldData = JSON.parse( sessionStorage.getItem( 'PCOFieldData' ) );
			// Check if all data is set for required fields.
			let allValid = true;
			for( i = 0; i < requiredFields.length; i++ ) {
				fieldName = requiredFields[i];
				if ( '' === fieldData[fieldName] ) {
					allValid = false;
				}
			}
			pco_wc.updateSession( allValid );
		},

		/**
		 * Updates the session with the correct boolean.
		 * 
		 * @param {boolean} allValid 
		 */
		updateSession: function( allValid ) {
			console.log( 'updating' );
			if ( false === pco_wc.needsUpdate ) {
				return;
			}
			// Update the session with the current value.
			$.ajax({
				type: 'POST',
				url: pco_wc_params.update_session_url,
				data: {
					bool: allValid,
					nonce: pco_wc_params.update_session_nonce
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					pco_wc.needsUpdate = false;
				}
			});
		},

		/**
		 * Sets the form fields values from the session storage.
		 */
		setFormFieldValues: function() {
			let form_data = JSON.parse( sessionStorage.getItem( 'PCOFieldData' ) );
			$.each( form_data, function( name, value ) {
				let field = $('*[name="' + name + '"]');
				let saved_value = value;
				// Check if field is a checkbox
				if( field.is(':checkbox') ) {
					if( saved_value !== '' ) {
						field.prop('checked', true);
					}
				} else if( field.is(':radio') ) {
					for ( x = 0; x < field.length; x++ ) {
						if( field[x].value === value ) {
							$(field[x]).prop('checked', true);
						}
					}
				} else {
					field.val( saved_value );
				}

			});
		},

		/**
		 * Moves all non standard fields to the extra checkout fields.
		 */
		moveExtraCheckoutFields: function() {
			// Move order comments.
			$('.woocommerce-additional-fields').appendTo('#pco-extra-checkout-fields');

			let form = $('form[name="checkout"] input, form[name="checkout"] select, textarea');
			for ( i = 0; i < form.length; i++ ) {
				let name = form[i]['name'];
				// Check if this is a standard field.
				if ( $.inArray( name, pco_wc_params.standard_woo_checkout_fields ) === -1 ) {
					// This is not a standard Woo field, move to our div.
					$('p#' + name + '_field').appendTo('#pco-extra-checkout-fields');
				}
			}
		},

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			// Check if payson is the selected payment method before we do anything.
			if( pco_wc.checkIfPaysonSelected() ) {
				$(document).ready( pco_wc.documentReady() );
				// Payson Event listeners.
				document.addEventListener( 'PaysonEmbeddedAddressChanged', function( data ) { pco_wc.addressChanged( data ) } );
				document.addEventListener( 'PaysonEmbeddedCheckoutResult', function( data ) { pco_wc.checkoutResult( data ) } );

				// Update Checkout.
				pco_wc.bodyEl.on('update_checkout', pco_wc.pcoFreeze );
				// Updated Checkout.
				pco_wc.bodyEl.on('updated_checkout', pco_wc.updatePaysonOrder );

				// Change from PCO.
				pco_wc.bodyEl.on('click', pco_wc.selectAnotherSelector, pco_wc.changeFromPco);

				// Catch changes to order notes.
				pco_wc.bodyEl.on('change', '#order_comments', pco_wc.updateOrderComment);

				// Extra checkout fields.
				pco_wc.bodyEl.on('blur', pco_wc.extraFieldsSelectorText, pco_wc.checkFormData);
				pco_wc.bodyEl.on('change', pco_wc.extraFieldsSelectorNonText, pco_wc.checkFormData);
				pco_wc.bodyEl.on('click', 'input#terms', pco_wc.checkFormData);

			}
			pco_wc.bodyEl.on('change', 'input[name="payment_method"]', pco_wc.maybeChangeToPco);
		},
	}
	pco_wc.init();
});
