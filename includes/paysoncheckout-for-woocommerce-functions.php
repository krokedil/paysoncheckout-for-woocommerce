<?php
/**
 * Functions file for the plugin.
 *
 * @package PaysonCheckout/Includes
 */

/**
 * Prints the PaysonCheckout snippet.
 *
 * @return void
 */
function pco_wc_show_snippet() {
	if ( ! isset( $_GET['pco_confirm'] ) ) {
		$payson_order = pco_wc_maybe_create_payson_order();
		if ( is_wp_error( $payson_order ) ) {
			// If error print error message.
			$code    = $payson_order->get_error_code();
			$message = $payson_order->get_error_message();
			$text    = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s'
			?>
			<ul class="woocommerce-error" role="alert">
				<li><?php echo sprintf( $text, $code, $message ); ?></li>
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
 * Maybe creates the Payson order.
 *
 * @return array
 */
function pco_wc_maybe_create_payson_order() {
	// Check if we have a payment id. If we do get the order.
	if ( WC()->session->get( 'payson_payment_id' ) ) {
		$payson_order = pco_wc_get_order();
	} else {
		// Else create the order and maybe set payment id.
		$payson_order = pco_wc_create_order();
		if ( is_array( $payson_order ) && isset( $payson_order['id'] ) ) {
			WC()->session->set( 'payson_payment_id', $payson_order['id'] );
		}
	}

	return $payson_order;
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
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'klarna-checkout-for-woocommerce' );
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
function maybe_show_validation_error_message() {
	if ( isset( $_GET['pco_validation_error'] ) && is_checkout() ) {
		$errors = json_decode( base64_decode( $_GET['pco_validation_error'] ), true );
		foreach ( $errors as $error ) {
			wc_add_notice( $error, 'error' );
		}
	}
}

/**
 * Creates either a normal order or a subscription order.
 *
 * @return array
 */
function pco_wc_create_order() {
	// Check if the cart has a subscription.
	if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
		return PCO_WC()->create_recurring_order->request();
	}
	return PCO_WC()->create_order->request();
}

/**
 * Gets either a normal order or a subscription order.
 *
 * @return array
 */
function pco_wc_get_order() {
	// Check if the cart has a subscription.
	if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
		return PCO_WC()->get_recurring_order->request( WC()->session->get( 'payson_payment_id' ) );
	}
	return PCO_WC()->get_order->request( WC()->session->get( 'payson_payment_id' ) );
}
