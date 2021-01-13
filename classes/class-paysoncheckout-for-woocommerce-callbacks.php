<?php
/**
 * Handles callbacks for the plugin.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback class.
 */
class PaysonCheckout_For_WooCommerce_Callbacks {
	/**
	 * Order is valid flag.
	 *
	 * @var boolean
	 */
	public $order_is_valid = true;

	/**
	 * Validation messages.
	 *
	 * @var array
	 */
	public $validation_messages = array();

	/**
	 * The Payson order
	 *
	 * @var array The Payson order object.
	 */
	public $payson_order = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_pco_wc_notification', array( $this, 'notification_cb' ) );
		add_action( 'pco_check_for_order', array( $this, 'pco_check_for_order_callback' ), 10, 2 );
	}

	/**
	 * Handles notification callbacks.
	 *
	 * @return void
	 */
	public function notification_cb() {
		$payment_id = null;
		if ( isset( $_GET['checkout'] ) ) {
			$payment_id   = $_GET['checkout'];
			$subscription = false;
		} elseif ( isset( $_GET['subscription'] ) ) {
			$payment_id   = $_GET['subscription'];
			$subscription = true;
		}

		if ( isset( $payment_id ) ) {

			$payson_order = pco_wc_get_order( $payment_id, $subscription );
			if ( is_wp_error( $payson_order ) ) {
				PaysonCheckout_For_WooCommerce_Logger::log( 'Could not get order in notification callback. Payment ID: ' . $payment_id . 'Is subscription order: ' . $subscription );
			} else {
				if ( 'readyToShip' === $payson_order['status'] || 'customerSubscribed' === $payson_order['status'] ) {
					PaysonCheckout_For_WooCommerce_Logger::log( 'Notification Listener hit: ' . json_encode( $_GET ) . ' URL: ' . $_SERVER['REQUEST_URI'] );
					wp_schedule_single_event( time() + 120, 'pco_check_for_order', array( $payment_id, $subscription ) );
				}
				header( 'HTTP/1.1 200 OK' );
			}
		}
	}

	public function pco_check_for_order_callback( $payment_id, $subscription ) {
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'paysoncheckout',
				'date_created'   => '>' . ( time() - MONTH_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = '';

		foreach ( $orders as $order_id ) {
			if ( $subscription ) {
				$order_payment_id = get_post_meta( $order_id, '_payson_subscription_id', true );
			} else {
				$order_payment_id = get_post_meta( $order_id, '_payson_checkout_id', true );
			}

			if ( $order_payment_id === $payment_id ) {
				$order_id_match = $order_id;
				break;
			}
		}

		// Did we get a match?
		if ( $order_id_match ) {
			$order = wc_get_order( $order_id_match );

			if ( $order ) {
				PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. Payment id ' . $payment_id . '. already exist in order ID ' . $order_id_match );
			} else {
				// No order, why?
				PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. Payment id ' . $payment_id . '. already exist in order ID ' . $order_id_match . '. But we could not instantiate an order object' );
			}
		} else {
			// No order found - create a new
			PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. We could NOT find Payment id ' . $payment_id );
		}
	}
}
new PaysonCheckout_For_WooCommerce_Callbacks();
