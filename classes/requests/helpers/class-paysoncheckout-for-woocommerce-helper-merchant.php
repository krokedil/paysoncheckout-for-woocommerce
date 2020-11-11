<?php
/**
 * Get merchant helper class.
 *
 * @package PaysonCheckout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for merchant object.
 */
class PaysonCheckout_For_WooCommerce_Helper_Merchant {

	/**
	 * Returns the merchant URLs.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array
	 */
	public function get_merchant_urls( $order_id ) {
		// Maybe set the confirmation URI to include payment id.
		$confirmation_uri_args = array( 'pco_confirm' => '1' );
		$confirmation_uri      = add_query_arg(
			$confirmation_uri_args,
			wc_get_checkout_url()
		);

		// If we have an order id, this is a pay for order payment.
		if ( $order_id ) {
			$order            = wc_get_order( $order_id );
			$confirmation_uri = add_query_arg(
				$confirmation_uri_args,
				$order->get_checkout_order_received_url()
			);
		}

		$integration_info = 'krokedil_woocommerce|' . PAYSONCHECKOUT_VERSION . '|' . WC()->version;

		return array(
			'checkoutUri'     => wc_get_checkout_url(), // String.
			'confirmationUri' => $confirmation_uri, // String.
			'notificationUri' => get_home_url() . '/wc-api/PCO_WC_Notification', // String.
			'termsUri'        => get_permalink( wc_get_page_id( 'terms' ) ), // String.
			'partnerId'       => 'Krokedil', // String.
			'integrationInfo' => $integration_info,
		);
	}
}
