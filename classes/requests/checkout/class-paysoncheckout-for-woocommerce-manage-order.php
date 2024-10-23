<?php
/**
 * Manage order request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for request manage order.
 */
class PaysonCheckout_For_WooCommerce_Manage_Order extends PaysonCheckout_For_WooCommerce_Request {
	/**
	 * Makes the request
	 *
	 * @param string|null $order_id The WooCommerce order id.
	 * @param array       $payson_data The Payson order data.
	 * @return array
	 */
	public function request( $order_id = null, $payson_data = null, $payment_id = null ) {
		$payment_id         = ( null === $payment_id ) ? WC()->session->get( 'payson_payment_id' ) : $payment_id;
		$request_url        = $this->environment . 'Checkouts/' . $payment_id;
		$request_args       = apply_filters( 'pco_manage_order_args', $this->get_request_args( $order_id, $payson_data, $payment_id ) );
		$response           = wp_remote_request( $request_url, $request_args );
		$code               = wp_remote_retrieve_response_code( $response );
		$formatted_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'PUT', 'Payson manage order request.', $request_args, $request_url, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );
		return $formatted_response;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @param array  $payson_data The Payson order data.
	 * @param string $payment_id The Payson payment id.
	 * @return array
	 */
	public function get_request_args( $order_id, $payson_data, $payment_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'PUT',
			'body'    => wp_json_encode( $payson_data ),
		);
	}
}
