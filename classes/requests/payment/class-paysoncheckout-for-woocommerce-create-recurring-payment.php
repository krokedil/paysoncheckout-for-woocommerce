<?php
/**
 * Create recurring payment request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for request create recurring payment.
 */
class PaysonCheckout_For_WooCommerce_Create_Recurring_Payment extends PaysonCheckout_For_WooCommerce_Request {
	/**
	 * Makes the request
	 *
	 * @param string $subscription_id The PaysonCheckout ID for the recurring order.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array
	 */
	public function request( $subscription_id, $order_id ) {
		$request_url  = $this->enviroment . 'RecurringPayments';
		$request_args = apply_filters( 'pco_create_recurring_payment_args', $this->get_request_args( $subscription_id, $order_id ) );
		$response     = wp_remote_request( $request_url, $request_args );
		$code         = wp_remote_retrieve_response_code( $response );
		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $subscription_id, 'POST', 'Payson create recurring payment request.', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );

		$formated_response = $this->process_response( $response, $request_args, $request_url );
		if ( is_wp_error( $formated_response ) || ( isset( $formated_response['status'] ) && 'denied' === $formated_response['status'] ) ) {
			$data              = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$formated_response = new WP_Error( wp_remote_retrieve_response_code( $response ), __( 'Scheduled payment denied by Payson.', 'woocommerce-gateway-paysoncheckout' ), $data );
		}

		return $formated_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param string $subscription_id The PaysonCheckout ID for the recurring order.
	 * @return array
	 */
	public function get_body( $subscription_id, $order_id ) {

		$body = array(
			'subscriptionId'  => $subscription_id,
			'notificationUri' => get_home_url() . '/wc-api/PCO_WC_Notification',
			'merchant'        => PCO_WC()->merchant_urls->get_merchant_urls( $order_id ),
			'order'           => array(
				'currency' => get_woocommerce_currency(),
				'items'    => PCO_WC()->order_items->get_order_items( $order_id ),
			),
		);

		if ( null !== $order_id ) {
			$order                         = wc_get_order( $order_id );
			$order_number                  = $order->get_order_number();
			$body['merchant']['reference'] = $order_number;
		}

		return $body;
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @return array
	 */
	public function get_request_args( $subscription_id, $order_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'POST',
			'body'    => wp_json_encode( $this->get_body( $subscription_id, $order_id ) ),
		);
	}
}
