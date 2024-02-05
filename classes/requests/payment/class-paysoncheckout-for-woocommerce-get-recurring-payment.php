<?php
/**
 * Get recurring payment request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for request get payment.
 */
class PaysonCheckout_For_WooCommerce_Get_Recurring_Payment extends PaysonCheckout_For_WooCommerce_Request {
	/**
	 * Makes the request
	 *
	 * @param string $payment_id The Payson payment id.
	 * @return array
	 */
	public function request( $payment_id = null ) {
		if ( null !== $payment_id ) {
			$request_url  = $this->environment . 'RecurringPayments/' . $payment_id;
			$request_args = apply_filters( 'pco_get_order_args', $this->get_request_args() );
			$response     = wp_remote_request( $request_url, $request_args );
			$code         = wp_remote_retrieve_response_code( $response );

			// Log the request.
			$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'GET', 'Payson get recurring payment request.', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
			PaysonCheckout_For_WooCommerce_Logger::log( $log );

			$formatted_response = $this->process_response( $response, $request_args, $request_url );
			return $formatted_response;
		} else {
			return new WP_Error( '400', 'Missing payment id.' );
		}
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'GET',
		);
	}
}
