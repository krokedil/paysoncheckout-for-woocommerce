<?php
/**
 * Get agreement helper class.
 *
 * @package PaysonCheckout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for merchant object.
 */
class PaysonCheckout_For_WooCommerce_Helper_Agreement {

	/**
	 * Returns the agreement.
	 *
	 * @return array
	 */
	public function get_agreement() {
		return array(
			'currency' => get_woocommerce_currency(),
		);
	}
}
