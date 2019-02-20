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
		$payson_order = PCO_WC()->get_order->request( WC()->session->get( 'payson_payment_id' ) );
	} else {
		// Else create the order and maybe set payment id.
		$payson_order = PCO_WC()->create_order->request();
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
