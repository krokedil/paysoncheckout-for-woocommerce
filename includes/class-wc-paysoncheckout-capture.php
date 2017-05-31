<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Capture Payson reservation
 *
 * Check if order was created using Payson and if yes, capture AfterPay reservation when WooCommerce order is marked
 * completed.
 *
 * @class WC_PaysonCheckout_Capture
 * @version 1.0.0
 * @package WC_Gateway_AfterPay/Classes
 * @category Class
 * @author Krokedil
 */
class WC_PaysonCheckout_Capture {

	/** @var int */
	private $order_id = '';

	/** @var bool */
	private $order_management = false;

	/**
	 * WC_PaysonCheckout_Capture constructor.
	 */
	public function __construct() {
		$paysoncheckout_settings = get_option( 'woocommerce_paysoncheckout_settings' );
		$this->debug             = $paysoncheckout_settings['debug'];
		$this->order_management  = 'yes' == $paysoncheckout_settings['order_management'] ? true : false;
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_full' ) );
	}

	/**
	 * Process reservation cancellation.
	 *
	 * @param $order_id
	 */
	public function capture_full( $order_id ) {
		$this->order_id = $order_id;
		$order          = wc_get_order( $this->order_id );

		// If this order wasn't created using PaysonCheckout payment method, bail.
		if ( 'paysoncheckout' != krokedil_get_payment_method( $order ) ) {
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( get_post_meta( $this->order_id, '_paysoncheckout_reservation_captured', true ) ) {
			$order->add_order_note( __( 'Could not capture PaysonCheckout reservation, PaysonCheckout reservation is already captured.', 'woocommerce-gateway-paysoncheckout' ) );

			return;
		}

		// If payment method is set to not capture orders automatically, bail.
		if ( ! $this->order_management ) {
			return;
		}

		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api        = new WC_PaysonCheckout_Setup_Payson_API();
		$payson_api        = $payson_api->set_payson_api();
		$checkout_temp_obj = $payson_api->GetCheckout( $this->get_checkout_id() );

		try {
			$response = $payson_api->ShipCheckout( $checkout_temp_obj );
			if ( 'shipped' == $response->status ) {
				// Add time stamp, used to prevent duplicate cancellations for the same order.
				update_post_meta( $this->order_id, '_paysoncheckout_reservation_captured', current_time( 'mysql' ) );
				// Add Payson order status
				update_post_meta( krokedil_get_order_id( $order ), '_paysoncheckout_order_status', $response->status );
				$order->add_order_note( sprintf( __( 'PaysonCheckout reservation was successfully captured.', 'woocommerce-gateway-paysoncheckout' ), '' ) );
			} else {
				$order->add_order_note( __( 'PaysonCheckout reservation could not be captured.', 'woocommerce-gateway-paysoncheckout' ) );
			}
		} catch ( Exception $e ) {
			WC_Gateway_PaysonCheckout::log( $e->getMessage() );
			$order->add_order_note( sprintf( __( 'PaysonCheckout reservation could not be captured, reason: %s.', 'woocommerce-gateway-paysoncheckout' ), $e->getMessage() ) );
		}
	}

	/**
	 * Grab Payson Checkout ID.
	 *
	 * @return string
	 */
	public function get_checkout_id() {
		return get_post_meta( $this->order_id, '_payson_checkout_id', true );
	}

}

$wc_paysoncheckout_capture = new WC_PaysonCheckout_Capture;
