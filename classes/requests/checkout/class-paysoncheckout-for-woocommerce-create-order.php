<?php
/**
 * Create order request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for request create order.
 */
class PaysonCheckout_For_WooCommerce_Create_Order extends PaysonCheckout_For_WooCommerce_Request {
	/**
	 * Makes the request
	 *
	 * @return array
	 */
	public function request() {
		$request_url  = $this->enviroment . 'Checkouts';
		$request_args = apply_filters( 'pco_create_order_args', $this->get_request_args() );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );
		$payment_id   = 'NULL';
		if ( isset( json_decode( wp_remote_retrieve_body( $response ), true )['id'] ) ) {
			$payment_id = json_decode( wp_remote_retrieve_body( $response ), true )['id'];
		}

		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'POST', 'Payson create order request.', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		return array(
			'merchant' => PCO_WC()->merchant_urls->get_merchant_urls(),
			'order'    => array(
				'currency' => get_woocommerce_currency(),
				'items'    => PCO_WC()->cart_items->get_cart_items(),
			),
			'gui'      => PCO_WC()->gui->get_gui(),
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
