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
	public function get_customer_data( $payson_data = null, $order_id = null ) {
		// If we dont have an order from Payson.
		if ( empty( $payson_data ) ) {
			$options = get_option( 'woocommerce_paysoncheckout_settings' );
			$type    = ( isset( $options['default_customer_type'] ) && 'b2b' === $options['default_customer_type'] ) ? 'business' : 'person';
			$order   = wc_get_order( $order_id );

			return array(
				'city'           => ! empty( $order ) ? $order->get_billing_city() : WC()->customer->get_billing_city(),
				'countryCode'    => ! empty( $order ) ? $order->get_billing_country() : WC()->customer->get_billing_country(),
				'email'          => ! empty( $order ) ? $order->get_billing_email() : WC()->customer->get_billing_email(),
				'firstName'      => ! empty( $order ) ? $order->get_billing_first_name() : WC()->customer->get_billing_first_name(),
				'lastName'       => ! empty( $order ) ? $order->get_billing_last_name() : WC()->customer->get_billing_last_name(),
				'postalCode'     => ! empty( $order ) ? $order->get_billing_postcode() : WC()->customer->get_billing_postcode(),
				'street'         => ! empty( $order ) ? $order->get_billing_address_1() : WC()->customer->get_billing_address_1(),
				'phone'          => ! empty( $order ) ? $order->get_billing_phone() : WC()->customer->get_billing_phone(),
				'type'           => $type,
				'IdentityNumber' => '',
			);

		}

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
