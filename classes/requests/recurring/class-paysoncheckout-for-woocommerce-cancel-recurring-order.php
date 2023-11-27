<?php
/**
 * Cancel recurring order request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for request cancel recurring order.
 */
class PaysonCheckout_For_WooCommerce_Cancel_Recurring_Payment extends PaysonCheckout_For_WooCommerce_Request {
	/**
	 * Makes the request
	 *
	 * @return array
	 */
	public function request( $order ) {
		$subscription_id = $order->get_meta( PaysonCheckout_For_WooCommerce_Subscriptions::RECURRING_TOKEN );
		$request_url     = $this->enviroment . 'RecurringSubscriptions/' . $subscription_id;
		$request_args    = apply_filters( 'pco_cancel_recurring_order_args', $this->get_request_args( $order ) );
		$response        = wp_remote_request( $request_url, $request_args );
		$code            = wp_remote_retrieve_response_code( $response );
		$body            = wp_remote_retrieve_body( $response );

		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $subscription_id, 'POST', 'Payson cancel recurring order request.', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );

		$formatted_response = $this->process_response( $response, $request_args, $request_url );
		return $formatted_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @return array
	 */
	public function get_body( $order ) {
		return array(
			'status'    => 'canceled',
			'id'        => $order->get_meta( PaysonCheckout_For_WooCommerce_Subscriptions::RECURRING_TOKEN ),
			'customer'  => PCO_WC()->customer->get_customer_data(),
			'gui'       => PCO_WC()->gui->get_gui(),
			'agreement' => PCO_WC()->agreement->get_agreement(),
			'merchant'  => array_merge(
				PCO_WC()->merchant_urls->get_merchant_urls( null ),
				array(
					'reference' => $order->get_order_number(),
				)
			),
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args( $order ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'PUT',
			'body'    => wp_json_encode( $this->get_body( $order ) ),
		);
	}
}
