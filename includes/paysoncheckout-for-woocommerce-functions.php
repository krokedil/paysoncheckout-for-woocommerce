<?php
/**
 * Functions file for the plugin.
 *
 * @package PaysonCheckout/Includes
 */

/**
 * Prints the PaysonCheckout snippet.
 *
 * @param boolean $subscription If this is a subscription order or not.
 * @return void
 */
function pco_wc_show_snippet( $subscription = false ) {
	$subscription = PaysonCheckout_For_WooCommerce_Subscriptions::cart_has_subscription();
	if ( ! isset( $_GET['pco_confirm'] ) ) {
		$payson_order = pco_wc_maybe_create_payson_order( $subscription );
		if ( is_wp_error( $payson_order ) ) {
			// If error print error message.
			$code    = $payson_order->get_error_code();
			$message = $payson_order->get_error_message();
			$text    = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s'
			?>
			<ul class="woocommerce-error" role="alert">
				<li><?php printf( $text, $code, $message ); ?></li>
			</ul>
			<?php
			// Then unset the payment id session to force a new order to be created on page load.
			WC()->session->__unset( 'payson_payment_id' );
		} else {
			$snippet = $payson_order['snippet'];
			echo $snippet;
		}
	}
}

/**
 * Prints the PaysonCheckout snippet for on pay for order page.
 *
 * @return void
 */
function pco_wc_show_pay_for_order_snippet() {
	if ( ! isset( $_GET['pco_confirm'] ) ) {
		$order_id = absint( get_query_var( 'order-pay', 0 ) );

		// Create the order and maybe set payment id.
		$payson_order = pco_wc_create_order( $order_id );
		$order        = wc_get_order( $order_id );

		if ( is_array( $payson_order ) && isset( $payson_order['id'] ) ) {
			$order->update_meta_data( '_payson_checkout_id', $payson_order['id'] );
			$order->save();
		}
		if ( is_wp_error( $payson_order ) ) {
			// If error print error message.
			$code    = $payson_order->get_error_code();
			$message = $payson_order->get_error_message();
			$text    = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s'
			?>
		<ul class="woocommerce-error" role="alert">
			<li><?php printf( $text, $code, $message ); ?></li>
		</ul>
			<?php
			// Remove the post meta so that we can create a new order with payson on an error.
			$order->delete_meta_data( '_payson_checkout_id' );
			$order->save();
		} else {
			$snippet = $payson_order['snippet'];
			echo $snippet;
		}
	}
}

/**
 * Prints the thank you page snippet for PaysonCheckout. Does not display error messages.
 *
 * @param int  $order_id WooCommerce order id.
 * @param bool $subscription is this a subscription order.
 * @return void
 */
function pco_wc_thankyou_page_snippet( $order_id, $subscription ) {
	$order = wc_get_order( $order_id );
	if ( $subscription ) {
		$payment_id = $order->get_meta( '_payson_subscription_id' );

	} else {
		$payment_id = $order->get_meta( '_payson_checkout_id' );
	}
	$payson_order = pco_wc_get_order( $payment_id, $subscription );

	if ( ! is_wp_error( $payson_order ) ) {
		$snippet = $payson_order['snippet'];
		echo $snippet;
	}
}

/**
 * Maybe creates the Payson order.
 *
 * @param boolean $subscription If this is a subscription order or not.
 * @return array|WP_Error
 */
function pco_wc_maybe_create_payson_order( $subscription = false ) {
	// Check if we have a payment id. If we do get the order. Also check if previous session was a subscription or not.
	if ( ( WC()->session->get( 'payson_payment_id' ) && WC()->session->get( 'payson_subscription' ) === $subscription ) || is_order_received_page() ) {

		// Check if the initial selected currency for the order has been changed - if so, force a new checkout session.
		if ( ! pco_compare_currencies() ) {
			pco_wc_force_new_checkout_session();
		}

		$payson_order = pco_wc_get_order( null, $subscription );

		// Check if Payson order is WP_Error.
		if ( is_wp_error( $payson_order ) ) {
			pco_wc_force_new_checkout_session();
			return $payson_order;
		}

		/* The order might have already been completed, but the customer was not redirected to the confirmation page. */
		if ( 'readyToShip' === $payson_order['status'] ) {
			$order = wc_get_orders(
				array(
					'payment_method' => 'paysoncheckout',
					'meta_key'       => '_payson_checkout_id',
					'meta_value'     => WC()->session->get( 'payson_payment_id' ),
					'meta_compare'   => '=',
					'limit'          => 1,
				)
			);

			if ( count( $order ) > 0 ) {

				wp_safe_redirect(
					add_query_arg(
						array(
							'key'          => $order[0]->get_order_key(),
							'pco_confirm'  => 'yes',
							'pco_order_id' => WC()->session->get( 'payson_payment_id' ),
						),
						$order[0]->get_checkout_order_received_url()
					)
				);

				pco_confirm_payson_order( WC()->session->get( 'payson_payment_id' ), $order[0]->get_id() );
				exit;
			}
		}

		// Check if the order has a valid status and not on confirmation page or thank you page..
		if ( ! is_order_received_page() && ! pco_check_valid_order_status( $payson_order ) && ! isset( $_GET['pco_confirm'] ) ) {
			// If not clear session and rerun function to get a new order.
			pco_wc_force_new_checkout_session();
		}
	} else {
		// Else create the order and maybe set payment id.
		$payson_order = pco_wc_create_order();
		if ( is_array( $payson_order ) && isset( $payson_order['id'] ) ) {
			WC()->session->set( 'payson_payment_id', $payson_order['id'] );
			WC()->session->set( 'payson_subscription', $subscription );
		}
	}

	return $payson_order;
}

/**
 * Forces a new checkout session from Payson.
 *
 * @return void|array
 */
function pco_wc_force_new_checkout_session() {
	// Check if we have done this before, if we have do nothing.
	if ( did_action( 'pco_forced_new_session' ) < 1 ) {
		do_action( 'pco_forced_new_session' );
		WC()->session->__unset( 'payson_payment_id' );
		return pco_wc_maybe_create_payson_order();
	}
}

/**
 * Unsets all sessions for the plugin.
 *
 * @return void
 */
function pco_wc_unset_sessions() {
	WC()->session->__unset( 'payson_payment_id' );
	WC()->session->__unset( 'pco_wc_update_md5' );
}

/**
 * Shows select another payment method button on Payson Checkout page.
 *
 * @return void
 */
function pco_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_paysoncheckout_settings' );
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'woocommerce-gateway-paysoncheckout' );
		?>
		<p class="paysoncheckout-select-other-wrapper">
			<a class="checkout-button button" href="#" id="paysoncheckout-select-other">
				<?php echo $select_another_method_text; ?>
			</a>
		</p>
		<?php
	}
}

/**
 * Maybe shows error messages if any are set.
 *
 * @return void
 */
function pco_maybe_show_validation_error_message() {
	$validation_errors = wc_get_var( $_GET['pco_validation_error'] );
	if ( $validation_errors && is_checkout() ) {
		$errors = json_decode( base64_decode( $validation_errors ), true );
		foreach ( $errors as $error ) {
			wc_add_notice( $error, 'error' );
		}
	}
}

/**
 * Creates either a normal order or a subscription order.
 *
 * @param string $order_id The WooCommerce order id.
 * @return array|WP_Error
 */
function pco_wc_create_order( $order_id = null ) {
	if ( PaysonCheckout_For_WooCommerce_Subscriptions::is_change_payment_method() ) {
		return PCO_WC()->create_recurring_order->request();
	}

	if ( empty( $order_id ) ) {
		// Check if the cart has a subscription.
		if ( PaysonCheckout_For_WooCommerce_Subscriptions::cart_has_subscription() ) {
			return PCO_WC()->create_recurring_order->request();
		}
		return PCO_WC()->create_order->request();
	} else {
		$order = wc_get_order( $order_id );

		// Check if the order has a subscription.
		if ( PaysonCheckout_For_WooCommerce_Subscriptions::order_has_subscription( $order ) ) {
			$subscription_id = WC()->session->get( 'payson_payment_id' );
			return PCO_WC()->recurring_payment->request( $subscription_id, $order_id );
		}
		return PCO_WC()->create_order->request( $order_id );
	}
}

/**
 * Gets either a normal order or a subscription order.
 *
 * @param string  $payment_id The payment id from Payson.
 * @param boolean $subscription If this is a subscription order or not.
 * @return array
 */
function pco_wc_get_order( $payment_id = null, $subscription = false ) {
	$payment_id = ( null === $payment_id ) ? WC()->session->get( 'payson_payment_id' ) : $payment_id;
	// Check if the cart has a subscription.
	if ( PaysonCheckout_For_WooCommerce_Subscriptions::cart_has_subscription() || $subscription ) {
		return PCO_WC()->get_recurring_order->request( $payment_id );
	}
	return PCO_WC()->get_order->request( $payment_id );
}

/**
 * Checks the order if it has an invalid status for the checkout process.
 *
 * @param array $payson_order The payson order object.
 * @return boolean
 */
function pco_check_valid_order_status( $payson_order ) {
	$invalid_status = array( 'expired', 'canceled', 'denied', 'paidToAccount', 'shipped', 'readyToShip' );
	// Return false if this is a WP error.
	if ( is_wp_error( $payson_order ) ) {
		return false;
	}
	$payson_order_status = $payson_order['status'];
	// If the order status is an invalid status, return false.
	if ( in_array( $payson_order_status, $invalid_status, true ) ) {
		return false;
	}
	// If we get here return true.
	return true;
}


/**
 * Confirms and finishes the Payson Order for processing.
 *
 * @param string $pco_order_id payson order id.
 * @param int    $order_id The WooCommerce Order id.
 * @return void
 */
function pco_confirm_payson_order( $pco_order_id, $order_id = null ) {
	if ( $order_id ) {
		$order = wc_get_order( $order_id );
		// If the order is already completed, return.
		if ( null !== $order->get_date_paid() ) {
			return;
		}

		// Get the Payson order.
		$payson_order = pco_wc_get_order( $pco_order_id );
		if ( ! is_wp_error( $payson_order ) ) {
			if ( 'readyToShip' === $payson_order['status'] ) {
				$order->payment_complete( $pco_order_id );
				$order->add_order_note( __( 'Payment via PaysonCheckout, order ID: ', 'payson-checkout-for-woocommerce' ) . $pco_order_id );
			} else {
				$order->set_status( 'on-hold', __( 'Invalid status for payson order', 'payson-checkout-for-woocommerce' ) );
			}
		} else {
			$order->set_status( 'on-hold', __( 'Waiting for verification from Payson notification callback', 'payson-checkout-for-woocommerce' ) );
		}

		$order->save();
	}
}

/**
 * Checks whether the initial currency for the order has remained the same throughout the checkout process.
 * If the currency changes, a new Payson Checkout session will be forced.
 *
 * @return bool
 */
function pco_compare_currencies() {
	$currency = WC()->session->get( 'pco_selected_currency' );
	if ( ! empty( $currency ) && strtolower( $currency ) !== strtolower( get_woocommerce_currency() ) ) {
		return false;
	}
	return true;
}

/**
 * Get the Woo order by Payson order ID.
 *
 * @param string $payson_order_id The Payson order ID.
 * @return WC_Order|false The Woo order or false if not found.
 */
function pco_get_order_by_payson_id( $payson_order_id ) {
	$meta_key = '_payson_checkout_id';
	$orders   = wc_get_orders(
		array(
			'meta_key'   => $meta_key,
			'meta_value' => $payson_order_id,
			'limit'      => 1,
			'orderby'    => 'date',
			'order'      => 'DESC',
		)
	);

	$order = reset( $orders );
	if ( empty( $order ) || $payson_order_id !== $order->get_meta( $meta_key ) ) {
		return false;
	}

	return $order;
}