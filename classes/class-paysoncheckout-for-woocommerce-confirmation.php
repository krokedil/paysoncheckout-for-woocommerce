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

		$order = wc_get_order( $order_id );

		// Check that the order status is correct before continuing.
		if ( $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
			return;
		}

		// Confirm the order.
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
			$this->confirm_recurring_payson_order( $order_id );
		}

		pco_wc_unset_sessions();
	}

	/**
	 * Processes the Payson Payment and sets post metas.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return bool|string
	 */
	public function confirm_recurring_payson_order( $order_id ) {
		$order           = wc_get_order( $order_id );
		$subscription_id = WC()->session->get( 'payson_payment_id' );
		$subcriptions    = wcs_get_subscriptions_for_order( $order_id );
		foreach ( $subcriptions as $subcription ) {
			update_post_meta( $subcription->get_id(), '_payson_subscription_id', $subscription_id );
		}

		// If subscription is free, then return true.
		if ( 0 >= $order->get_total() ) {
			update_post_meta( $order_id, '_payson_subscription_id', $subscription_id );
			$order->payment_complete( $subscription_id );
			return true;
		}
		// Make payment.
		$payson_order = PCO_WC()->recurring_payment->request( $subscription_id, $order_id );
		if ( is_wp_error( $payson_order ) ) {
			// If error save error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error on make recurring payment: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			$order->set_status( 'on-hold' );

			return false;
		}

		// Save meta data to order and subscriptions.
		update_post_meta( $order_id, '_payson_subscription_id', $subscription_id );
		update_post_meta( $order_id, '_payson_checkout_id', $payson_order['id'] );

		$order->add_order_note( __( 'Subscription payment made with Payson, subscription ID: ', 'payson-checkout-for-woocommerce' ) . $subscription_id );

		// Set payment complete if all is successfull.
		$order->payment_complete( $payson_order['purchaseId'] );
		return true;
	}

}
PaysonCheckout_For_WooCommerce_Confirmation::get_instance();
