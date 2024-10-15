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
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	public function request( $order_id = null ) {
		$request_url  = $this->environment . 'Checkouts';
		$request_args = apply_filters( 'pco_create_order_args', $this->get_request_args( $order_id ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );
		$payment_id   = 'NULL';
		if ( isset( json_decode( wp_remote_retrieve_body( $response ), true )['id'] ) ) {
			$payment_id = json_decode( wp_remote_retrieve_body( $response ), true )['id'];
		}

		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'POST', 'Payson create order request.', $request_args, $request_url, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );

		$formatted_response = $this->process_response( $response, $request_args, $request_url );

		// If this is a pay for order, save the payment id to the order.
		if ( ! empty( $order_id ) ) {
			$order = wc_get_order( $order_id );
			$order->update_meta_data( '_payson_checkout_id', $formatted_response['id'] );
			$order->save();
		}
		return $formatted_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_body( $order_id ) {

		// Store the initial currency.
		WC()->session->set( 'pco_selected_currency', get_woocommerce_currency() );

		return array(
			'merchant' => PCO_WC()->merchant_urls->get_merchant_urls( $order_id ),
			'customer' => PCO_WC()->customer->get_customer_data(),
			'order'    => array(
				'currency' => get_woocommerce_currency(),
				'items'    => ( null === $order_id ) ? PCO_WC()->cart_items->get_cart_items() : PCO_WC()->order_items->get_order_items( $order_id ),
			),
			'gui'      => PCO_WC()->gui->get_gui(),
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_request_args( $order_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( $this->get_body( $order_id ) ),
		);
	}
}
