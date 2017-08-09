(function ($) {
	'use strict';

	var wc_paysoncheckout_html = '';
	var wc_paysoncheckout_loaded = false;

	var wc_paysoncheckout_body_class = function wc_paysoncheckout_body_class() {
		if ("paysoncheckout" === $("input[name='payment_method']:checked").val()) {
			$("body").addClass("paysoncheckout-selected").removeClass("paysoncheckout-deselected");
		} else {
			$("body").removeClass("paysoncheckout-selected").addClass("paysoncheckout-deselected");
		}
	};

	var wc_paysoncheckout_get_iframe = function wc_paysoncheckout_get_iframe() {
		if ( !wc_paysoncheckout_loaded ) {
			wc_paysoncheckout_loaded = true;
		} else {
			$.ajax(
				wc_paysoncheckout.ajax_url,
				{
					type: "POST",
					dataType: "json",
					async: true,
					data: {
						action: "wc_paysoncheckout_iframe",
						nonce: wc_paysoncheckout.wc_payson_checkout_nonce
					},
					success: function(response) {
						// console.log(response.data);
						console.log(wc_paysoncheckout_loaded);
						$('div#customer_details_payson').html(response.data.iframe);
						wc_paysoncheckout_html = response.data.iframe;
						wc_paysoncheckout_loaded = true;
					}
				}
			);
		}
	};

	// Set body class when DOM is ready
	$(document).ready(function () {
		wc_paysoncheckout_body_class();
		if ("paysoncheckout" === $("input[name='payment_method']:checked").val()) {
			// Get iframe if not fetched yet
			if (!wc_paysoncheckout_loaded) {
				wc_paysoncheckout_get_iframe();
			}
		}
	});

	// When payment method is changed:
	//
	// - Change body class (CSS uses body class to hide and show elements)
	// - If changing to PaysonCheckout trigger update_checkout
	$(document.body).on("change", "input[name='payment_method']", function (event) {
		if ("paysoncheckout" === event.target.value) {
			// Get iframe if not fetched yet
			if (!wc_paysoncheckout_loaded) {
				wc_paysoncheckout_get_iframe();
			}
			$("body").trigger("update_checkout");
		}
	});

	// When checkout gets updated
	$(document.body).on("updated_checkout", function (event, data) {
		if ("paysoncheckout" === $("input[name='payment_method']:checked").val()) {
			// Remove the "choose another payment method" and Payson container to prevent duplication
			$('form.woocommerce-checkout .paysoncheckout-choose-other').remove();
			$('div#customer_details_payson').remove();

			// Move order review table
			$('table.woocommerce-checkout-review-order-table').addClass('paysoncheckout-cart').detach().appendTo('form.woocommerce-checkout');

			// Move order notes
			$('#order_comments_field').addClass('paysoncheckout-order-notes').detach().appendTo('form.woocommerce-checkout');

			// Add "choose another payment method" link
			if ($("input[name='payment_method']").length > 1) {
				if (!$(".paysoncheckout-choose-other").length) {
					$('<p><a href="#" class="button">' + wc_paysoncheckout.select_another_method_text + '</a></p>').appendTo('form.woocommerce-checkout').addClass('paysoncheckout-choose-other');
				}
			}

			$('form.woocommerce-checkout').append('<div id="customer_details_payson"></div>');

			wc_paysoncheckout_get_iframe();
		}

		wc_paysoncheckout_body_class();
	});

	$(document.body).on('click', '.paysoncheckout-choose-other a', function (e) {
		e.preventDefault();

		$('.paysoncheckout-cart').detach().prependTo('#order_review');
		$('.paysoncheckout-order-notes').detach().appendTo('.woocommerce-shipping-fields');
		$('.paysoncheckout-choose-other').remove();

		// Deselect Payson and select the first available non-Payson method
		$("input[name='payment_method']:checked").prop('checked', false);
		if ("paysoncheckout" === $("input[name='payment_method']:eq(0)").val()) {
			$("input[name='payment_method']:eq(1)").prop("checked", true);
		}

		$("body").trigger("update_checkout");

		wc_paysoncheckout_body_class();
	});
	
	// When Address update event is triggered
	$(document).on('PaysonEmbeddedAddressChanged', function(data) {
		var should_update = false;

		if ('yes' === wc_paysoncheckout.debug) {
			console.log('PaysonEmbeddedAddressChanged', data.detail);
		}

		// Check if new postcode and country are the same as old ones.
		if ( data.detail.CountryCode !== $('#billing_country').val() || data.detail.PostalCode !== $('input#billing_postcode').val() ) {
			var iframe = document.getElementById('paysonIframe');
			iframe.contentWindow.postMessage('lock', '*');

			should_update = true;
		}

		$('#billing_country').val(data.detail.CountryCode);
		$('#shipping_country').val(data.detail.CountryCode);

		$('input#billing_postcode').val(data.detail.PostalCode);
		$('input#shipping_postcode').val(data.detail.PostalCode);

		$.ajax(
			wc_paysoncheckout.ajax_url,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action  : 'payson_address_changed_callback',
					address : data.detail,
					nonce   : wc_paysoncheckout.wc_payson_checkout_nonce
				},
				success: function(response) {
					if ('yes' === wc_paysoncheckout.debug) {
						console.log('Address update AJAX sucess');
						console.log(response);
					}

					if (should_update) {
						$("body").trigger("update_checkout")
					}
				},
				error: function(response) {
					if ('yes' === wc_paysoncheckout.debug) {
						console.log('Address update AJAX error');
						console.log(response);
					}
				},
				complete: function() {
					if ('yes' === wc_paysoncheckout.debug) {
						console.log('Address update AJAX complete');
					}
				}
			}
		);

		// var iframe = document.getElementById('paysonIframe');
		// iframe.contentWindow.postMessage('lock', '*');
		// iframe.contentWindow.postMessage('updatePage', '*');
		// iframe.contentWindow.postMessage('release', '*');
	});

	$(document).on('PaysonEmbeddedCheckoutResult', function(data) {
		if ('yes' === wc_paysoncheckout.debug) {
			console.log('PaysonEmbeddedCheckoutResult', data);
		}
	});

	$('#order_comments').focusout(function(){
		var text = $('#order_comments').val();
		if( text.length > 0 ) {
            $.ajax(
                wc_paysoncheckout.ajax_url,
                {
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action  : 'payson_customer_order_note',
                        order_note : text,
                    },
                    success: function(response) {
                    }
                }
            );
        }
	});
}(jQuery));
