<?php
/**
 * Update order reference request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for request update order reference.
 */
class PaysonCheckout_For_WooCommerce_Update_Reference extends PaysonCheckout_For_WooCommerce_Request {
	/**
	 * Makes the request
	 *
	 * @param string|null $order_id The WooCommerce order id.
	 * @param array       $payson_data The Payson order data.
	 * @return array|WP_Error|false If the request could not be issued, FALSE is returned. Otherwise, WP_Error or an array.
	 */
	public function request( $order_id = null, $payson_data = null ) {
		$payment_id   = ! empty( WC()->session ) && ! empty( WC()->session->get( 'payson_payment_id' ) ) ? WC()->session->get( 'payson_payment_id' ) : $payson_data['id'];
		$request_url  = $this->environment . 'Checkouts/' . $payment_id;
		$request_args = apply_filters( 'pco_update_order_args', $this->get_request_args( $order_id, $payson_data ) );
		if ( isset( WC()->session ) ) {
			if ( WC()->session->get( 'pco_wc_update_md5' ) && WC()->session->get( 'pco_wc_update_md5' ) === md5( serialize( $request_args ) ) ) {
				return false;
			}
		}

		// If the PID is missing, and we have an WP_Error, an internal server error has most likely happened.
		if ( empty( $payment_id ) && is_wp_error( $payson_data ) ) {
			$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'PUT', 'Payson update reference request.', $request_args, $request_url, json_encode( $payson_data, true ), 500 );
			PaysonCheckout_For_WooCommerce_Logger::log( $log );
			return $payson_data;
		}

		$response           = wp_remote_request( $request_url, $request_args );
		$code               = wp_remote_retrieve_response_code( $response );
		$formatted_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'PUT', 'Payson update reference request.', $request_args, $request_url, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );
		return $formatted_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @param array  $payson_data The Payson order data.
	 * @return array
	 */
	public function get_body( $order_id, $payson_data ) {
		// Set the merchant reference of the order.
		$order                                      = wc_get_order( $order_id );
		$order_number                               = $order->get_order_number();
		$payson_data['merchant']['reference']       = $order_number;
		$payson_data['merchant']['confirmationUri'] = add_query_arg(
			array(
				'pco_confirm'  => 'yes',
				'pco_order_id' => WC()->session->get( 'payson_payment_id' ),
			),
			$order->get_checkout_order_received_url()
		);

		return $payson_data;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @param array  $payson_data The Payson order data.
	 * @param string $payment_id The Payson payment id.
	 * @return array
	 */
	public function get_request_args( $order_id, $payson_data ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'PUT',
			'body'    => wp_json_encode( $this->get_body( $order_id, $payson_data ) ),
			'timeout' => apply_filters( 'pco_request_timeout', 10 ),
		);
	}
}
