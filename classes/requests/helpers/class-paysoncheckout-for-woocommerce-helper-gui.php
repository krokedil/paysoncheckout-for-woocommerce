<?php
/**
 * Gets the gui data for a request.
 *
 * @package PaysonCheckout/Classes/Requests/Helpers
 */

/**
 * Class to generate gui data for requests.
 */
class PaysonCheckout_For_WooCommerce_Helper_GUI {
	/**
	 * Returns the gui data.
	 *
	 * @return array
	 */
	public function get_gui() {
		$payson_settings = get_option( 'woocommerce_paysoncheckout_settings' );

		return array(
			'colorScheme'  => ( isset( $payson_settings['color_scheme'] ) ? $payson_settings['color_scheme'] : 'white' ), // String.
			'requestPhone' => 'required' === get_option( 'woocommerce_checkout_phone_field', 'required' ), // Bool.
			'locale'       => $this->get_payson_language(), // String.
			'countries'    => $this->get_shipping_countries(), // Array.
		);
	}

	/**
	 * Gets the accepted format for the Payson language code from WooCommerce.
	 *
	 * @return string $payson_language The Payson language code.
	 */
	public function get_payson_language() {
		$iso_code      = preg_split( '/[_-]/', get_locale() );
		$shop_language = $iso_code[0];
		switch ( $shop_language ) {
			case 'sv':
				return 'sv';
			case 'fi':
				return 'fi';
			case 'es':
				return 'es';
			case 'de':
				return 'de';
			case 'da':
				return 'da';
			case 'nb':
			case 'nn':
				return 'no';
			default:
				return 'en';
		}
	}

	/**
	 * Gets the allowed countries for the Payson Checkout.
	 *
	 * @return array $countries An array of the allowed countries for the WooCommerce store.
	 */
	public function get_shipping_countries() {
		// Add shipping countries.
		$wc_countries = new WC_Countries();
		$countries    = array_keys( $wc_countries->get_allowed_countries() );
		return $countries;
	}
}
