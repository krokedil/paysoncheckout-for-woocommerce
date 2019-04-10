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
	 * @return array
	 */
	public function get_merchant_urls() {
		// Maybe set the confirmation URI to include payment id.
		$confirmation_uri_args = array( 'pco_confirm' => '1' );
		if ( WC()->session->get( 'payson_payment_id' ) ) {
			$confirmation_uri_args['pco_payment_id'] = WC()->session->get( 'payson_payment_id' );
		}
		$confirmation_uri = add_query_arg(
			$confirmation_uri_args,
			wc_get_checkout_url()
		);

		// Set validation URI query args.
		$validation_uri_args = array( 'pco_session_id' => PCO_WC()->session->get_session_id() );
		$validation_uri      = add_query_arg(
			$validation_uri_args,
			get_home_url() . '/wc-api/PCO_WC_Validation'
		);

		$integration_info = 'krokedil_woocommerce|' . PAYSONCHECKOUT_VERSION . '|' . WC()->version;

		return array(
			'checkoutUri'     => wc_get_checkout_url(), // String.
			'confirmationUri' => $confirmation_uri, // String.
			'notificationUri' => get_home_url() . '/wc-api/PCO_WC_Notification', // String.
			'termsUri'        => get_permalink( wc_get_page_id( 'terms' ) ), // String.
			'validationUri'   => $validation_uri, // String.
			'partnerId'       => 'Krokedil', // String.
			'integrationInfo' => $integration_info,
		);
	}
}
