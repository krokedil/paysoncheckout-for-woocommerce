jQuery(function($) {
	const pco_wc = {
		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			// Set the WooCommerce order comment.
			pco_wc.setOrderComment();

			// Submit the form.
			pco_wc.submit();
		},

		/*
		 * Prepares and submits the form. 
		 */
		submit: function() {
			// Check any terms checkboxes.
			$('input#terms').prop('checked', true);
			// Submit the form.
			$('form[name="checkout"]').submit();
		},

		checkoutError: function() {
			let error_message = $( ".woocommerce-NoticeGroup-checkout" ).text();
			$.ajax({
				type: 'POST',
				url: pco_wc_params.checkout_error_url,
				data: {
					error_message: error_message,
					nonce: pco_wc_params.checkout_error_nonce,
				},
				dataType: 'json',
				success: function(data) {
				},
				error: function(data) {
				},
				complete: function(data) {
					if (true === data.responseJSON.success) {
						window.location.href = data.responseJSON.data;
					}
				}
			});
		},

		/**
		 * Sets the order comment, and removes the local storage after.
		 */
		setOrderComment: function() {
			$('#order_comments').val( localStorage.getItem( 'pco_wc_order_comment' ) );
			localStorage.removeItem( 'pco_wc_order_comment' );
		},
		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			$(document).ready( pco_wc.documentReady() );

			// On checkout error.
			//$(document).on( 'checkout_error', pco_wc.checkoutError() );
			$( document ).on( 'checkout_error', function () {
				pco_wc.checkoutError();
			});
		},
	}
	pco_wc.init();
	let pco_process_text = pco_wc_params.modal_text;
	$( 'body' ).append( $( '<div class="pco-modal"><div class="pco-modal-content">' + pco_process_text + '</div></div>' ) );
});