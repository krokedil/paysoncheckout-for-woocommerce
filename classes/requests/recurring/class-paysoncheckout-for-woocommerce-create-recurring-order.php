<?php
/**
 * Create recurring order request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for request create recurring order.
 */
class PaysonCheckout_For_WooCommerce_Create_Recurring_Order extends PaysonCheckout_For_WooCommerce_Request {
	/**
	 * Makes the request
	 *
	 * @return array
	 */
	public function request() {
		$request_url  = $this->environment . 'RecurringSubscriptions';
		$request_args = apply_filters( 'pco_create_recurring_order_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );
		$payment_id   = 'NULL';
		if ( isset( json_decode( wp_remote_retrieve_body( $response ), true )['id'] ) ) {
			$payment_id = json_decode( wp_remote_retrieve_body( $response ), true )['id'];
		}

		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'POST', 'Payson create recurring order request.', $request_args, $request_url, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );

		$formatted_response = $this->process_response( $response, $request_args, $request_url );
		return $formatted_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		return array(
			'merchant'  => PCO_WC()->merchant_urls->get_merchant_urls( null ),
			'customer'  => PCO_WC()->customer->get_customer_data(),
			'gui'       => PCO_WC()->gui->get_gui(),
			'agreement' => PCO_WC()->agreement->get_agreement(),
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( $this->get_body() ),
		);
	}
}
