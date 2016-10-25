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
		if ('' === wc_paysoncheckout_html) {
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
						$('div#customer_details_payson').html(response.data);
						wc_paysoncheckout_html = response.data;
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
		// If switching to Ecster, update checkout
		if ("paysoncheckout" === event.target.value) {
			$("body").trigger("update_checkout");
		}
	});

	// When checkout gets updated
	$(document.body).on("updated_checkout", function () {
		if ("paysoncheckout" === $("input[name='payment_method']:checked").val()) {
			// Remove the "choose another payment method" and Payson container to prevent duplication
			$('form.woocommerce-checkout .paysoncheckout-pay-choose-other').remove();
			$('div#customer_details_payson').remove();

			// Move order review table
			$('table.woocommerce-checkout-review-order-table').addClass('paysoncheckout-cart').detach().appendTo('form.woocommerce-checkout');

			// Move order notes
			$('#order_comments_field').addClass('paysoncheckout-order-notes').detach().appendTo('form.woocommerce-checkout');

			// Add "choose another payment method" link
			if ($("input[name='payment_method']").length > 1) {
				$('<p><a href="#" class="button">' + wc_paysoncheckout.select_another_method_text + '</a></p>').appendTo('form.woocommerce-checkout').addClass('paysoncheckout-choose-other');
			}

			$('form.woocommerce-checkout').append('<div id="customer_details_payson"></div>');

			if (!wc_paysoncheckout_loaded) {
				wc_paysoncheckout_get_iframe();
			} else {
				$('div#customer_details_payson').html(wc_paysoncheckout_html);
			}
		}

		wc_paysoncheckout_body_class();
	});

	$(document.body).on('click', '.paysoncheckout-choose-other a', function (e) {
		e.preventDefault();

		$('.paysoncheckout-cart').detach().prependTo('#order_review');
		$('.paysoncheckout-order-notes').detach().appendTo('.woocommerce-shipping-fields');
		$('.paysoncheckout-choose-other').remove();

		// Deselect Ecster and select the first available non-Ecster method
		$("input[name='payment_method']:checked").prop('checked', false);
		if ("paysoncheckout" === $("input[name='payment_method']:eq(0)").val()) {
			$("input[name='payment_method']:eq(1)").prop("checked", true);
		}

		$("body").trigger("update_checkout");

		wc_paysoncheckout_body_class();
	});

	$(document).on('PaysonEmbeddedAddressChanged', function(data) {
		if ('yes' === wc_paysoncheckout.debug) {
			console.log('PaysonEmbeddedAddressChanged', data.detail);
		}
	});

	$(document).on('PaysonEmbeddedCheckoutResult', function(data) {
		if ('yes' === wc_paysoncheckout.debug) {
			console.log('PaysonEmbeddedCheckoutResult', data);
		}
	});
}(jQuery));
