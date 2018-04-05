<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handle responses from Payson
 *
 * @class    WC_PaysonCheckout_Response_Handler
 * @version  1.0.0
 * @package  WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Response_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Notification listener.
		add_action( 'woocommerce_api_wc_gateway_paysoncheckout', array( $this, 'notification_listener' ) );
		add_action( 'payson_ready_to_ship_notification', array( $this, 'ready_to_ship_cb' ), 10, 2 );
	}

	/**
	 * Notification listener.
	 */
	public function notification_listener() {
		WC_Gateway_PaysonCheckout::log( 'Notification callback for order: ' . $_GET['checkout'] );
		
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$checkout   = $payson_api->get_notification_checkout( $_GET['checkout'] );

		//WC_Gateway_PaysonCheckout::log( 'Posted notification info: ' . var_export( $checkout, true ) );

		$order = wc_get_order( $_GET['wc_order'] );
		
		if ( $order ) {
			switch ( $checkout->status ) {
				case 'readyToShip':
					$this->ready_to_ship_scheduler( sanitize_text_field( $_GET['wc_order'] ), sanitize_text_field( $_GET['checkout'] ) );
					break;
				case 'paidToAccount':
					// $this->paid_to_account_cb( $order, $checkout );
					break;
				case 'expired':
					$this->expired_cb( $order );
					break;
				case 'denied':
					$this->denied_cb( $order );
					break;
				case 'canceled':
					$this->denied_cb( $order );
					break;
			}
		}
	}

	public function ready_to_ship_scheduler( $order_id, $checkout_id ) {
		wp_schedule_single_event( time() + 120, 'payson_ready_to_ship_notification', array( $order_id, $checkout_id ) );
	}

	/**
	 * Handle a completed payment.
	 *
	 * @param WC_Order $order    WooCommerce order.
	 * @param object   $checkout PaysonCheckout resource.
	 */
	public function ready_to_ship_cb( $order_id, $checkout_id ) {
		WC_Gateway_PaysonCheckout::log( 'Payment status readyToShip callback.' );

		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$checkout   = $payson_api->get_notification_checkout( $checkout_id );
		$order 		= wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			exit;
		}

		if ( $order->has_status( array( 'processing', 'completed' ) ) ) {
			WC_Gateway_PaysonCheckout::log( 'Aborting, Order #' . krokedil_get_order_id( $order ) . ' is already complete.' );
			header( 'HTTP/1.0 200 OK' );
			return;

		}

		// Add Payson order status.
		update_post_meta( krokedil_get_order_id( $order ), '_paysoncheckout_order_status', $checkout->status );

		// Change the order status to Processing/Completed in WooCommerce.
		if ( 'readyToShip' === $checkout->status ) {
			if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
				WC_Gateway_PaysonCheckout::log('Order status not set correctly for order ' . $order->get_order_number() . ' during checkout process. Setting order status to Processing/Completed.');
				$order->payment_complete( $checkout->purchaseId );
				$order->add_order_note( __( 'Order status changed via API callback.', 'woocommerce-gateway-paysoncheckout' ) );
			}
		} else {
			$order->add_order_note( __( 'Payson Ready to Ship API callback but Payson order status is set to: ' . $checkout->status, 'woocommerce-gateway-paysoncheckout' ) );
		}

		header( 'HTTP/1.0 200 OK' );
		exit;
	}

	/**
	 * Handle an expired PaysonCheckout resource.
	 * Force deletes WooCommerce order, skipping Trash.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	protected function expired_cb( $order ) {
		if( $order->has_status( 'payson-incomplete' ) ) {
			wp_delete_post( krokedil_get_order_id( $order ), true );
		}
		header( 'HTTP/1.0 200 OK' );
	}

	/**
	 * Handle a denied PaysonCheckout payment.
	 * Marks WooCommerce order as cancelled and adds order note.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	protected function denied_cb( $order ) {
		$order->cancel_order( __( 'PaysonCheckout payment was denied.', 'woocommerce-gateway-paysoncheckout' ) );
		header( 'HTTP/1.0 200 OK' );
	}

	/**
	 * Adds order addresses to local order.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  object $order    Local WC order.
	 * @param  object $checkout PaysonCheckout order.
	 */
	public function add_order_addresses( $order, $checkout ) {
		$order_id = krokedil_get_order_id( $order );


		// Add customer billing address - retrieved from callback from Payson.
		update_post_meta( $order_id, '_billing_first_name', $checkout->customer->firstName );
		update_post_meta( $order_id, '_billing_last_name', $checkout->customer->lastName );
		update_post_meta( $order_id, '_billing_address_1', $checkout->customer->street );
		update_post_meta( $order_id, '_billing_postcode', $checkout->customer->postalCode );
		update_post_meta( $order_id, '_billing_city', $checkout->customer->city );
		update_post_meta( $order_id, '_billing_country', $checkout->customer->countryCode );
		update_post_meta( $order_id, '_billing_email', $checkout->customer->email );
		update_post_meta( $order_id, '_billing_phone', $checkout->customer->phone );

		// Add customer shipping address - retrieved from callback from Payson.
		update_post_meta( $order_id, '_shipping_first_name', $checkout->customer->firstName );
		update_post_meta( $order_id, '_shipping_last_name', $checkout->customer->lastName );
		update_post_meta( $order_id, '_shipping_address_1', $checkout->customer->street );
		update_post_meta( $order_id, '_shipping_postcode', $checkout->customer->postalCode );
		update_post_meta( $order_id, '_shipping_city', $checkout->customer->city );
		update_post_meta( $order_id, '_shipping_country', $checkout->customer->countryCode );

		// Store PaysonCheckout locale.
		update_post_meta( $order_id, '_payson_locale', $checkout->gui->locale );
	}
}

$wc_paysoncheckout_response_handler = new WC_PaysonCheckout_Response_Handler();
