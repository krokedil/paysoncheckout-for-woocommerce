jQuery(function($) {
	const pco_wc = {
		/*
		 * Document ready function. 
		 * Runs on the $(document).ready event.
		 */
		documentReady: function() {
			// Add the modal window to the page.
			pco_wc.addModal();
			// Submit the form.
			pco_wc.submit()
		},

		/*
		 * Adds the modal window to the page. 
		 */
		addModal: function() {
	
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

		/*
		 * Initiates the script and sets the triggers for the functions.
		 */
		init: function() {
			$(document).ready( pco_wc.documentReady() );
		},
	}
	pco_wc.init();
	let pco_process_text = pco_wc_params.modal_text;
	$( 'body' ).append( $( '<div class="pco-modal"><div class="pco-modal-content">' + pco_process_text + '</div></div>' ) );
});