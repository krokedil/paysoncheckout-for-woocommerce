<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Cancel Payson reservation
 *
 * Check if order was created using Payson and if yes, cancel Payson reservation when WooCommerce order is marked
 * cancel.
 *
 * @class WC_PaysonCheckout_Cancel_Reservation
 * @version 1.0.0
 * @package WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author Krokedil
 */
class WC_PaysonCheckout_Cancel_Reservation {

	/** @var int */
	private $order_id = '';

	/** @var bool */
	private $order_management = false;

	/**
	 * WC_PaysonCheckout_Cancel_Reservation constructor.
	 */
	public function __construct() {
		$paysoncheckout_settings = get_option( 'woocommerce_paysoncheckout_settings' );
		$this->order_management  = 'yes' == $paysoncheckout_settings['order_management'] ? true : false;
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_reservation' ) );
	}

	/**
	 * Process reservation cancellation.
	 *
	 * @param $order_id
	 */
	public function cancel_reservation( $order_id ) {
		$this->order_id = $order_id;
		$order          = wc_get_order( $this->order_id );
		// If this order wasn't created using PaysonCheckout payment method, bail.
		if ( 'paysoncheckout' != $order->payment_method ) {
			return;
		}
		// If this reservation was already cancelled, do nothing.
		if ( get_post_meta( $this->order_id, '_paysoncheckout_reservation_cancelled', true ) ) {
			$order->add_order_note( __( 'Could not cancel PaysonCheckout reservation, PaysonCheckout reservation is already cancelled.', 'woocommerce-gateway-paysoncheckout' ) );

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
		$payson_embedded_status = $checkout_temp_obj->status;
		WC_Gateway_PaysonCheckout::log( 'Payson object before CancelCheckout: ' . var_export( $checkout_temp_obj, true ) );
		try {
			$response = $payson_api->CancelCheckout( $checkout_temp_obj );
			if ( 'canceled' == $response->status ) {
				// Add time stamp, used to prevent duplicate cancellations for the same order.
				update_post_meta( $this->order_id, '_paysoncheckout_reservation_cancelled', current_time( 'mysql' ) );
				// Add Payson order status
				update_post_meta( krokedil_get_order_id( $order ), '_paysoncheckout_order_status', $response->status );
				$order->add_order_note( __( 'PaysonCheckout reservation was successfully cancelled.', 'woocommerce-gateway-paysoncheckout' ) );
			} else {
				$order->add_order_note( __( 'PaysonCheckout reservation could not be cancelled.', 'woocommerce-gateway-paysoncheckout' ) );
			}
		} catch ( Exception $e ) {
			WC_Gateway_PaysonCheckout::log( $e->getMessage() );
			$order->add_order_note( sprintf( __( 'PaysonCheckout reservation could not be cancelled, reason: %s.', 'woocommerce-gateway-paysoncheckout' ), $e->getMessage() ) );
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

$wc_paysoncheckout_cancel_reservation = new WC_PaysonCheckout_Cancel_Reservation;
