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
 * Class for request cancelling a recurring order.
 */
class PaysonCheckout_For_WooCommerce_Cancel_Recurring_Order extends PaysonCheckout_For_WooCommerce_Request {


	/**
	 * Makes the request
	 *
	 * @param string|null $subscription_id The WooCommerce order id.
	 * @return array
	 */
	public function request( $subscription ) {
		$pco_subscription_id = $subscription->get_parent()->get_meta( 'pco_subscription_id' );
		$request_url         = $this->environment . 'RecurringSubscriptions/' . $pco_subscription_id;
		$request_args        = apply_filters( 'pco_update_recurring_order_args', $this->get_request_args( $subscription, $pco_subscription_id ) );

		$response           = wp_remote_request( $request_url, $request_args );
		$code               = wp_remote_retrieve_response_code( $response );
		$formatted_response = $this->process_response( $response, $request_args, $request_url );

		// Log the request.
		$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $pco_subscription_id, 'PUT', 'Payson cancel recurring order request.', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );
		PaysonCheckout_For_WooCommerce_Logger::log( $log );

		return $formatted_response;
	}

	/**
	 * Gets the request body.
	 *
	 * @param WC_Subscription $subscription The WooCommerce order id.
	 * @param string          $pco_subscription_id The Payson payment order id.
	 * @return array
	 */
	public function get_body( $subscription, $pco_subscription_id ) {
		return array(
			'id'              => $pco_subscription_id,
			'subscriptionId'  => $pco_subscription_id,
			'purchaseId'      => $subscription->get_order_number(),
			'order'           => $subscription,
			'notificationUri' => PCO_WC()->merchant_urls->get_merchant_urls( $subscription->get_id() )['notificationUri'],
			'status'          => 'canceled',
		);
	}

	/**
	 * Gets the request args for the API call.
	 *
	 * @param WC_Subscription $subscription The subscription.
	 * @param string          $pco_subscription_id The Payson payment id.
	 * @return array
	 */
	public function get_request_args( $subscription, $pco_subscription_id ) {
		return array(
			'headers' => $this->get_headers(),
			'method'  => 'PUT',
			'body'    => wp_json_encode( $this->get_body( $subscription, $pco_subscription_id ) ),
		);
	}
}
