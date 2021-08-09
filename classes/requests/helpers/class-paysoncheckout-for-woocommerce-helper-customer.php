<?php
/**
 * Gets the customer data for a request.
 *
 * @package PaysonCheckout/Classes/Requests/Helpers
 */

/**
 * Class to generate customer data for requests.
 */
class PaysonCheckout_For_WooCommerce_Helper_Customer {
	/**
	 * Returns the customer data.
	 *
	 * @param array $payson_data The payson order data.
	 * @return array
	 */
	public function get_customer_data( $payson_data = null ) {
		// If we dont have an order from Payson.
		if ( null === $payson_data ) {
			$options = get_option( 'woocommerce_paysoncheckout_settings' );
			$type    = ( isset( $options['default_customer_type'] ) && 'b2b' === $options['default_customer_type'] ) ? 'business' : 'person';
			return array(
				'city'           => WC()->customer->get_billing_city(),
				'countryCode'    => WC()->customer->get_billing_country(),
				'email'          => WC()->customer->get_billing_email(),
				'firstName'      => WC()->customer->get_billing_first_name(),
				'lastName'       => WC()->customer->get_billing_last_name(),
				'postalCode'     => WC()->customer->get_billing_postcode(),
				'street'         => WC()->customer->get_billing_address_1(),
				'phone'          => WC()->customer->get_billing_phone(),
				'type'           => $type,
				'IdentityNumber' => '',
			);
		} else {
			return array(
				'city'           => $payson_data['customer']['city'],
				'countryCode'    => $payson_data['customer']['countryCode'],
				'email'          => $payson_data['customer']['email'],
				'firstName'      => $payson_data['customer']['firstName'],
				'lastName'       => $payson_data['customer']['lastName'],
				'postalCode'     => $payson_data['customer']['postalCode'],
				'phone'          => $payson_data['customer']['phone'],
				'street'         => $payson_data['customer']['street'],
				'type'           => $payson_data['customer']['type'],
				'IdentityNumber' => $payson_data['customer']['identityNumber'],
			);
		}
	}
}
