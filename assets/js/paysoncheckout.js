(function ($) {
	'use strict';

	var checkout_initiated = wc_paysoncheckout.checkout_initiated;

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

	function update_checkout() {
        if( checkout_initiated == 'yes' && wc_paysoncheckout.payment_successful == 0 ) {
			
			//sendLockDown();
            var data = {
                'action': 'payson_update_checkout'
            };
            jQuery.post(wc_paysoncheckout.ajax_url, data, function (data) {
                if (true === data.success) {
					console.log('update success');
                    sendUpdate();
					sendRelease();
                } else {
                    console.log('update error');
                    window.location.href = data.data.redirect_url;
                }

			});
			
			
        } else {
            checkout_initiated = 'yes';
        }
	}
	
	// Lock the iframe object during updates
	function sendLockDown() {
        var iframe = document.getElementById('paysonIframe');
		iframe.contentWindow.postMessage('lock', '*');
		console.log('sendLockDown');
	}
	
	// Release the iframe object after update
	function sendRelease() {
        var iframe = document.getElementById('paysonIframe');
		iframe.contentWindow.postMessage('release', '*');
		console.log('sendRelease');
	}
	// Update the iframe after update
	function sendUpdate() {
        var iframe = document.getElementById('paysonIframe');
        iframe.contentWindow.postMessage('updatePage', '*');
    }

	// Set body class when DOM is ready
	$(document).ready(function () {
		wc_paysoncheckout_body_class();
		if ("paysoncheckout" === $("input[name='payment_method']:checked").val()) {
			// Get iframe if not fetched yet
			if (!wc_paysoncheckout_loaded) {
				//wc_paysoncheckout_get_iframe();
			}
		}
	});

	// Suspend Payson Checkout during WooCommerce checkout update
    $(document).on('update_checkout', function () {
        if ("paysoncheckout" === $("input[name='payment_method']:checked").val() && checkout_initiated == 'yes') {
            sendLockDown();
        }
    });

	// When payment method is changed:
	//
	// - Change body class (CSS uses body class to hide and show elements)
	// - If changing to PaysonCheckout trigger update_checkout
	$(document.body).on("change", "input[name='payment_method']", function (event) {
		if ("paysoncheckout" === event.target.value) {
			
			$('form.checkout').block({
                message: "",
                baseZ: 99999,
                overlayCSS:
                    {
                        background: "#fff",
                        opacity: 0.6
                    },
                css: {
                    padding:        "20px",
                    zindex:         "9999999",
                    textAlign:      "center",
                    color:          "#555",
                    backgroundColor:"#fff",
                    cursor:         "wait",
                    lineHeight:		"24px",
                }
            });
           
            $.ajax(
                wc_paysoncheckout.ajax_url,
                {
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action  		: 'payson_change_payment_method',
						paysoncheckout 	: true,
						nonce   		: wc_paysoncheckout.wc_payson_checkout_nonce
                    },
                    success: function (data) {
                        console.log(data);
                        window.location.href = data.data.redirect;
                    }
            	}
            );
		}
	});

	// When checkout gets updated
	$(document.body).on("updated_checkout", function (event, data) {
		if ("paysoncheckout" === $("input[name='payment_method']:checked").val()) {
			update_checkout();
		}
		wc_paysoncheckout_body_class();
	});

	$(document.body).on('click', '#payson-checkout-select-other', function (e) {
		e.preventDefault();
		
		$.ajax(
			wc_paysoncheckout.ajax_url,
			{
				type: 'POST',
				dataType: 'json',
				data: {
					action  		: 'payson_change_payment_method',
					paysoncheckout 	: false,
					nonce   		: wc_paysoncheckout.wc_payson_checkout_nonce
				},
				success: function(response) {
					if ('yes' === wc_paysoncheckout.debug) {
						console.log('Change payment method sucess');
					}

					$('body').removeClass('paysoncheckout-selected');
					window.location.href = response.data.redirect;
				},
				error: function(response) {
					if ('yes' === wc_paysoncheckout.debug) {
						console.log('Change payment method error');
						console.log(response);
					}
				},
				complete: function() {
					if ('yes' === wc_paysoncheckout.debug) {
						console.log('Change payment method complete');
					}
				}
			}
		);
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
