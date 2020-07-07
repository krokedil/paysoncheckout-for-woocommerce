<?php
/**
 * Confirmation class file.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Confirmation class.
 */
class PaysonCheckout_For_WooCommerce_Confirmation {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'pco_confirm_order' ) );
	}

	/**
	 * Confirm order
	 */
	public function pco_confirm_order() {
		$pco_confirm  = filter_input( INPUT_GET, 'pco_confirm', FILTER_SANITIZE_STRING );
		$pco_order_id = filter_input( INPUT_GET, 'pco_order_id', FILTER_SANITIZE_STRING );
		$order_key    = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );

		// Return if we dont have our parameters set.
		if ( empty( $pco_confirm ) || empty( $pco_order_id ) || empty( $order_key ) ) {
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );

		// Return if we cant find an order id.
		if ( empty( $order_id ) ) {
			return;
		}

		// Confirm the order.
		pco_confirm_payson_order( $pco_order_id, $order_id );
		pco_wc_unset_sessions();
	}

}
PaysonCheckout_For_WooCommerce_Confirmation::get_instance();
