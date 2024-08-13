jQuery( function ( $ ) {
    const pco_wc = {
        bodyEl: $( "body" ),
        checkoutFormSelector: "form.checkout",

        // Order notes.
        orderNotesValue: "",
        orderNotesSelector: "textarea#order_comments",
        orderNotesEl: $( "textarea#order_comments" ),

        // Payment method
        paymentMethodEl: $( 'input[name="payment_method"]' ),
        paymentMethod: "",
        selectAnotherSelector: "#paysoncheckout-select-other",

        // Address data.
        addressData: [],

        // Extra checkout fields.
        blocked: false,
        extraFieldsSelectorText:
            'div#pco-extra-checkout-fields input[type="text"], div#pco-extra-checkout-fields input[type="password"], div#pco-extra-checkout-fields textarea, div#pco-extra-checkout-fields input[type="email"], div#pco-extra-checkout-fields input[type="tel"]',
        extraFieldsSelectorNonText:
            'div#pco-extra-checkout-fields select, div#pco-extra-checkout-fields input[type="radio"], div#pco-extra-checkout-fields input[type="checkbox"], div#pco-extra-checkout-fields input.checkout-date-picker, input#terms input[type="checkbox"]',

        /*
         * Document ready function.
         * Runs on the $(document).ready event.
         */
        documentReady: function () {
            pco_wc.pcoFreeze()
            // Extra checkout fields.
            pco_wc.moveExtraCheckoutFields()
        },

        /*
         * Check if Payson is the selected gateway.
         */
        checkIfPaysonSelected: function () {
            if ( pco_wc.paymentMethodEl.length > 0 ) {
                pco_wc.paymentMethod = pco_wc.paymentMethodEl.filter( ":checked" ).val()
                if ( "paysoncheckout" === pco_wc.paymentMethod ) {
                    return true
                }
            }
            return false
        },

        /*
         * Runs when PaysonEmbeddedAddressChanged is triggered ( address changed ).
         */
        addressChanged: function ( data /* Address from Payson */ ) {
            pco_wc.pcoFreeze()
            let address = data.detail
            $.ajax( {
                type: "POST",
                url: pco_wc_params.address_changed_url,
                data: {
                    address: address,
                    nonce: pco_wc_params.address_changed_nonce,
                },
                dataType: "json",
                success: function ( data ) {},
                error: function ( data ) {},
                complete: function ( data ) {
                    // Set address data.
                    pco_wc.addressData = address
                    if ( pco_wc.addressData !== null ) {
                        pco_wc.setAddressData()
                    }
                },
            } )
        },

        /*
         * Runs when PaysonEmbeddedPaymentInitiated is triggered.
         */
        paymentInitiated: function ( event ) {
            event.preventDefault()

            // Get payson order and submit the WC order.
            pco_wc.getPaysonOrder()
        },

        paymentInitiationVerified: function () {
            let iframe = document.getElementById( "paysonIframe" )
            iframe.contentWindow.postMessage( "paymentInitiationVerified", "*" )
        },
        paymentInitiationCancelled: function () {
            let iframe = document.getElementById( "paysonIframe" )
            iframe.contentWindow.postMessage( "paymentInitiationCancelled", "*" )
        },

        failOrder: function ( event, error_message ) {
            // Send false and cancel
            this.paymentInitiationCancelled()

            // Renable the form.
            $( "body" ).trigger( "updated_checkout" )
            $( pco_wc.checkoutFormSelector ).removeClass( "processing" )
            $( pco_wc.checkoutFormSelector ).unblock()
            $( ".woocommerce-checkout-review-order-table" ).unblock()

            // Print error messages, and trigger checkout_error, and scroll to notices.
            $( ".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message" ).remove()
            $( "form.checkout" ).prepend(
                '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + "</div>",
            ) // eslint-disable-line max-len
            $( "form.checkout" ).removeClass( "processing" ).unblock()
            $( "form.checkout" ).find( ".input-text, select, input:checkbox" ).trigger( "validate" ).blur()
            $( document.body ).trigger( "checkout_error", [ error_message ] )
            $( "html, body" ).animate(
                {
                    scrollTop: $( "form.checkout" ).offset().top - 100,
                },
                1000,
            )
        },

        getPaysonOrder: function () {
            $.ajax( {
                type: "POST",
                url: pco_wc_params.get_order_url,
                data: {
                    nonce: pco_wc_params.get_order_nonce,
                },
                dataType: "json",
                success: function ( data ) {},
                error: function ( data ) {},
                complete: function ( data ) {
                    if ( data.responseJSON.data.hasOwnProperty( "customer" ) ) {
                        // set data from wc form
                        pco_wc.fillForm( data.responseJSON.data.customer )
                        // Submit wc order.
                        pco_wc.submitForm()
                    }
                },
            } )
        },

        fillForm: function ( customer ) {
            var firstName = "firstName" in customer ? customer.firstName : ""
            var lastName = "lastName" in customer ? customer.lastName : ""
            var city = "city" in customer ? customer.city : ""
            var countryCode = "countryCode" in customer ? customer.countryCode : ""
            var email = "email" in customer ? customer.email : ""
            var phone = "phone" in customer ? customer.phone : ""
            var postalCode = "postalCode" in customer ? customer.postalCode : ""
            var street = "street" in customer ? customer.street : ""

            // billing first name
            $( "#billing_first_name" ).val( firstName )
            // shipping first name
            $( "#shipping_first_name" ).val( firstName )

            if ( customer.type === "business" ) {
                lastName = "-"
            }
            // billing last name
            $( "#billing_last_name" ).val( lastName )
            // shipping last name.
            $( "#shipping_last_name" ).val( lastName )
            // billing country
            $( "#billing_country" ).val( countryCode )
            // shipping country
            $( "#shipping_country" ).val( countryCode )
            // billing street
            $( "#billing_address_1" ).val( street )
            // shipping street
            $( "#shipping_address_1" ).val( street )
            // billing city
            $( "#billing_city" ).val( city )
            // shipping city
            $( "#shipping_city" ).val( city )
            // billing postal code
            $( "#billing_postcode" ).val( postalCode )
            // shipping postal code
            $( "#shipping_postcode" ).val( postalCode )
            // billing phone
            $( "#billing_phone" ).val( phone )
            // billing email
            $( "#billing_email" ).val( email )
        },

        submitForm: function () {
            if ( 0 < $( "form.checkout #terms" ).length ) {
                $( "form.checkout #terms" ).prop( "checked", true )
            }
            pco_wc.submitOrder()
        },

        /*
         * Update the payson order. Happens on updated_checkout event.
         */
        updatePaysonOrder: function () {
            $.ajax( {
                type: "POST",
                url: pco_wc_params.update_order_url,
                data: {
                    nonce: pco_wc_params.update_order_nonce,
                },
                dataType: "json",
                success: function ( data ) {
                    let pcoNonce = data.data.pco_nonce
                    $( "#pco-nonce-wrapper" ).html( pcoNonce ) // Updates the nonce used on checkout
                },
                error: function ( data ) {},
                complete: function ( data ) {
                    let result = data.responseJSON
                    if ( ! result.success ) {
                        // Remove any old errors from Payson and print new error.
                        $( ".payson-error" ).remove()
                        $( ".woocommerce-checkout-review-order-table" ).after(
                            '<ul class="payson-error woocommerce-error" role="alert"><li>' + result.data + "</li></ul>",
                        )
                    } else {
                        if ( result.success && result.data.refreshZeroAmount ) {
                            window.location.reload()
                        }

                        if ( result.success && result.data.currenciesChanged ) {
                            window.location.reload()
                        }

                        // Set address data if we have it.
                        if ( result.data.address !== null ) {
                            pco_wc.addressData = result.data.address
                            pco_wc.setAddressData()
                        }
                        // Update the iFrame if needed.
                        if ( result.data.changed === true ) {
                            pco_wc.pcoUpdate()
                        } else {
                            pco_wc.pcoResume()
                        }
                        // Remove any error messages.
                        $( ".payson-error" ).remove()
                    }
                },
            } )
        },

        /*
         * Sets the WooCommerce form field data.
         */
        setAddressData: function () {
            // Billing fields.
            $( "#billing_first_name" ).val( pco_wc.addressData.firstName )
            $( "#billing_last_name" ).val( pco_wc.addressData.lastName )
            $( "#billing_address_1" ).val( pco_wc.addressData.street )
            $( "#billing_city" ).val( pco_wc.addressData.city )
            $( "#billing_postcode" ).val( pco_wc.addressData.postalCode )
            $( "#billing_phone" ).val( pco_wc.addressData.phone )
            $( "#billing_email" ).val( pco_wc.addressData.email )

            // Shipping fields.
            $( "#shipping_first_name" ).val( pco_wc.addressData.firstName )
            $( "#shipping_last_name" ).val( pco_wc.addressData.lastName )
            $( "#shipping_address_1" ).val( pco_wc.addressData.street )
            $( "#shipping_city" ).val( pco_wc.addressData.city )
            $( "#shipping_postcode" ).val( pco_wc.addressData.postalCode )

            // Only set country fields if we have data in them.
            if ( pco_wc.addressData.countryCode ) {
                $( "#billing_country" ).val( pco_wc.addressData.countryCode )
                $( "#shipping_country" ).val( pco_wc.addressData.countryCode )
            }

            // Update the checkout for postcode based shipping and resume the iFrame.
            pco_wc.bodyEl.trigger( "update_checkout" )
        },

        /*
         * Runs when PaysonEmbeddedCheckoutResult is triggered ( payment status changed ).
         */
        checkoutResult: function ( data /* Result from Payson */ ) {
            console.log( "Payment status changed." )
        },

        // When "Change to another payment method" is clicked.
        changeFromPco: function ( e ) {
            e.preventDefault()

            $( pco_wc.checkoutFormSelector ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )

            $.ajax( {
                type: "POST",
                dataType: "json",
                data: {
                    pco: false,
                    order_id: pco_wc_params.order_id,
                    nonce: pco_wc_params.change_payment_method_nonce,
                },
                url: pco_wc_params.change_payment_method_url,
                success: function ( data ) {},
                error: function ( data ) {},
                complete: function ( data ) {
                    window.location.href = data.responseJSON.data.redirect
                },
            } )
        },

        // When payment method is changed to PCO in regular WC Checkout page.
        maybeChangeToPco: function () {
            if ( "paysoncheckout" === $( this ).val() ) {
                $( pco_wc.checkoutFormSelector ).block( {
                    message: null,
                    overlayCSS: {
                        background: "#fff",
                        opacity: 0.6,
                    },
                } )

                $( ".woocommerce-info" ).remove()

                $.ajax( {
                    type: "POST",
                    data: {
                        pco: true,
                        order_id: pco_wc_params.order_id,
                        nonce: pco_wc_params.change_payment_method_nonce,
                    },
                    dataType: "json",
                    url: pco_wc_params.change_payment_method_url,
                    success: function ( data ) {},
                    error: function ( data ) {},
                    complete: function ( data ) {
                        window.location.href = data.responseJSON.data.redirect
                    },
                } )
            }
        },

        /**
         * Updates the order comment local storage.
         */
        updateOrderComment: function () {
            let val = $( "#order_comments" ).val()
            localStorage.setItem( "pco_wc_order_comment", val )
        },

        /*
         * Locks the iFrame.
         */
        pcoFreeze: function () {
            let iframe = document.getElementById( "paysonIframe" )
            iframe.contentWindow.postMessage( "lock", "*" )
        },

        /*
         * Unlocks the iFrame.
         */
        pcoResume: function () {
            if ( ! pco_wc.blocked ) {
                let iframe = document.getElementById( "paysonIframe" )
                iframe.contentWindow.postMessage( "release", "*" )
            }
        },

        /*
         * Updates the iFrame.
         */
        pcoUpdate: function () {
            if ( ! pco_wc.blocked ) {
                let iframe = document.getElementById( "paysonIframe" )
                iframe.contentWindow.postMessage( "updatePage", "*" )
            }
        },

        /**
         * Moves all non standard fields to the extra checkout fields.
         */
        moveExtraCheckoutFields: function () {
            // Move order comments.
            try {
                $( ".woocommerce-additional-fields" ).appendTo( "#pco-extra-checkout-fields" )
                let form = $( 'form[name="checkout"] input, form[name="checkout"] select, textarea' )
                for ( let i = 0; i < form.length; i++ ) {
                    let name = form[ i ].name
                    // Check if field is inside the order review.
                    if ( $( "table.woocommerce-checkout-review-order-table" ).find( form[ i ] ).length ) {
                        continue
                    }

                    // Check if this is a standard field.
                    if ( -1 === $.inArray( name, pco_wc_params.standard_woo_checkout_fields ) ) {
                        // This is not a standard Woo field, move to our div.
                        if ( 0 < $( "p#" + name + "_field" ).length ) {
                            $( "p#" + name + "_field" ).appendTo( "#pco-extra-checkout-fields" )
                        } else {
                            $( 'input[name="' + name + '"]' )
                                .closest( "p" )
                                .appendTo( "#pco-extra-checkout-fields" )
                        }
                    }
                }
            } catch ( err ) {
                console.log( "Failed to move order comments: ", err )
            }
        },

        /**
         * Submit the order using the WooCommerce AJAX function.
         */
        submitOrder: function () {
            $( ".woocommerce-checkout-review-order-table" ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )
            $.ajax( {
                type: "POST",
                url: pco_wc_params.submit_order,
                data: $( "form.checkout" ).serialize(),
                dataType: "json",
                success: function ( data ) {
                    try {
                        if ( "success" === data.result ) {
                            pco_wc.logToFile(
                                'Successfully placed order. Sending "paymentInitiationVerified" to Payson',
                            )
                            pco_wc.paymentInitiationVerified()
                        } else {
                            throw "Result failed"
                        }
                    } catch ( err ) {
                        if ( data.messages ) {
                            pco_wc.logToFile( "Checkout error | " + data.messages )
                            pco_wc.failOrder( "submission", data.messages )
                        } else {
                            pco_wc.logToFile( "Checkout error | No message" )
                            pco_wc.failOrder(
                                "submission",
                                '<div class="woocommerce-error">' + "Checkout error" + "</div>",
                            )
                        }
                    }
                },
                error: function ( data ) {
                    let public_message = "Error: " + data.status
                    try {
                        public_message = $( data.responseText ).text()
                        pco_wc.logToFile( "AJAX error | " + JSON.stringify( data ) )
                    } catch {
                        pco_wc.logToFile( "AJAX error | Failed to parse error message." )
                    }
                    pco_wc.failOrder( "ajax-error", public_message )
                },
            } )
        },

        /**
         * Logs the message to the PaysonCheckout log in WooCommerce.
         * @param {string} message
         */
        logToFile: function ( message ) {
            $.ajax( {
                url: pco_wc_params.log_to_file_url,
                type: "POST",
                dataType: "json",
                data: {
                    message: message,
                    nonce: pco_wc_params.log_to_file_nonce,
                },
            } )
        },

        /*
         * Initiates the script and sets the triggers for the functions.
         */
        init: function () {
            // Check if payson is the selected payment method before we do anything.
            if ( pco_wc.checkIfPaysonSelected() ) {
                $( document ).ready( pco_wc.documentReady() )

                // Update Checkout.
                pco_wc.bodyEl.on( "update_checkout", pco_wc.pcoFreeze )
                // Updated Checkout.
                pco_wc.bodyEl.on( "updated_checkout", pco_wc.updatePaysonOrder )

                // Change from PCO.

                // Catch changes to order notes.
                pco_wc.bodyEl.on( "change", "#order_comments", pco_wc.updateOrderComment )

                // Payson Event listeners.
                document.addEventListener( "PaysonEmbeddedAddressChanged", function ( data ) {
                    pco_wc.addressChanged( data )
                } )
                document.addEventListener( "PaysonEmbeddedCheckoutResult", function ( data ) {
                    pco_wc.checkoutResult( data )
                } )
                document.addEventListener( "PaysonEmbeddedPaymentInitiated", function ( event ) {
                    pco_wc.paymentInitiated( event )
                } )
            }
            pco_wc.bodyEl.on( "click", pco_wc.selectAnotherSelector, pco_wc.changeFromPco )
            pco_wc.bodyEl.on( "change", 'input[name="payment_method"]', pco_wc.maybeChangeToPco )
        },
    }
    pco_wc.init()
} )
