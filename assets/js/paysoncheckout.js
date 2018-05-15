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

	function maybe_post_form() {
        if ( wc_paysoncheckout.payment_successful == '1' ) {
            $('form.woocommerce-checkout').css("display", "none");
            // Block the body to prevent customers from doing something
		   
			$('body').block({
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
            
			// Check Terms checkbox, if it exists
			if ($("form.checkout #terms").length > 0) {
				$("form.checkout #terms").prop("checked", true);
			}
			console.log( 'post form' );
			payson_post_form();
            
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
		maybe_post_form();

		// Hide pay for order button and terms checkbox if this is a pay for order page and PCO is selected payment method
		if ( "paysoncheckout" === $("input[name='payment_method']:checked").val() && 'yes' == wc_paysoncheckout.pay_for_order ) {
			$('.wc-terms-and-conditions').hide();
			$('#place_order').hide();
		}
	});

	// Suspend Payson Checkout during WooCommerce checkout update
    $(document).on('update_checkout', function () {
        if ("paysoncheckout" === $("input[name='payment_method']:checked").val() && checkout_initiated == 'yes' && wc_paysoncheckout.payment_successful == 0 ) {
            sendLockDown();
        }
    });

	// When payment method is changed:
	//
	// - Change body class (CSS uses body class to hide and show elements)
	// - If changing to PaysonCheckout trigger update_checkout
	$(document.body).on("change", "input[name='payment_method']", function (event) {

		// Is this a pay for order page?
		if( 'yes' == wc_paysoncheckout.pay_for_order ) {

			// Show/Hide pay for order button and terms checkbox depending on what payment method that is selected
			if ( "paysoncheckout" === $("input[name='payment_method']:checked").val() ) {
				$('.wc-terms-and-conditions').hide();
				$('#place_order').hide();
			} else {
				$('.wc-terms-and-conditions').show();
				$('#place_order').show();
			}

			// Don't update adress in WC since we're on a pay for order page
			return;
		}

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

		// Don't update if we're on a pay for order page
		if( 'yes' == wc_paysoncheckout.pay_for_order ) {
			return;
		}

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
		console.log('order comment update');
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

	function payson_post_form() {
		
        var data = {
            'action': 'payson_get_customer_data'
        };
        jQuery.post(wc_paysoncheckout.ajax_url, data, function (data) {
            if (true === data.success) {
				console.log('payson_post_form request success');
				console.log(data.data);
				console.log(data.data.customer_data.billingFirstName);
				console.log('data.data.nonce ' + data.data.nonce);
				var datastring = 'billing_first_name=' + data.data.customer_data.billingFirstName +
				'&billing_last_name=' + data.data.customer_data.billingLastName +
				'&billing_address_1=' + data.data.customer_data.billingAddress +
				'&billing_postcode=' + data.data.customer_data.billingPostalCode +
				'&billing_city=' + data.data.customer_data.billingCity +
				'&billing_country=' + data.data.customer_data.billingCounry +
				'&billing_phone=' + data.data.customer_data.phone +
				'&billing_email=' + data.data.customer_data.email +
				'&shipping_first_name=' + data.data.customer_data.billingFirstName +
				'&shipping_last_name=' + data.data.customer_data.billingLastName +
				'&shipping_country=' + data.data.customer_data.shippingCounry +
				'&shipping_address_1=' + data.data.customer_data.billingAddress +
				'&shipping_postcode=' + data.data.customer_data.billingPostalCode +
				'&shipping_city=' + data.data.customer_data.billingCity +
				'&shipping_method%5B0%5D=' + data.data.shipping +
				'&ship_to_different_address=1' +
				'&payment_method=paysoncheckout&terms=on' +
				'&terms-field=1&_wpnonce=' + data.data.nonce;
				
				if(data.data.customer_data.billingAddress2 != null) {
					datastring = datastring + '&billing_address_2=' + data.data.customer_data.billingAddress2;
				}
				if(data.data.customer_data.shippingAddress2 != null) {
					datastring = datastring + '&shipping_address_2=' + data.data.customer_data.shippingAddress2;
				}
                
                if(data.data.order_note != 'undefined'){
                    datastring = datastring + '&order_comments=' + data.data.order_note;
                }
				
                    jQuery.ajax({
                    type: 'POST',
                    url: wc_checkout_params.checkout_url,
                    data: datastring,
                    dataType: 'json',
                    success: function (result) {
                        try {
                            if ('success' === result.result) {
                                if (-1 === result.redirect.indexOf('https://') || -1 === result.redirect.indexOf('http://')) {
                                    window.location = result.redirect;
                                } else {
                                    window.location = decodeURI(result.redirect);
                                }
                            } else if ('failure' === result.result) {
                                throw 'Result failure';
                            } else {
                                throw 'Invalid response';
                            }
                        } catch (err) {
							console.log(wc_checkout_params.checkout_url);
                            // Reload page
                            if (true === result.reload) {
                                window.location.reload();
                                return;
                            }
                            // Trigger update in case we need a fresh nonce
                            if (true === result.refresh) {
                                jQuery(document.body).trigger('update_checkout');
                            }
                            // Add new errors
                            if (result.messages) {
                                console.log(result.messages);
                            } else {
                                console.log(wc_checkout_params.i18n_checkout_error);
                            }
                            checkout_error();
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        //wc_checkout_form.submit_error('<div class="woocommerce-error">' + errorThrown + '</div>');
					}
					
				});
				
            } else {
                console.log('payson_post_form error');
                window.location.href = data.data.redirect_url;
            }
        });
	}

	function checkout_error() {
		console.log('checkout error');
		if ("paysoncheckout" === $("input[name='payment_method']:checked").val()) {
			$.ajax(
	            wc_paysoncheckout.ajax_url,
	            {
	                type: "POST",
	                dataType: "json",
	                async: true,
	                data: {
	                    action:		"payson_on_checkout_error",
	                },
	                success: function (data) {
					},
					error: function (data) {
					},
					complete: function (data) {
						console.log('paysoncheckout checkout error');
						console.log(data.responseJSON);
						window.location.href = data.responseJSON.data.redirect;
					}
	            }
	        );
		}
	}
	
}(jQuery));
