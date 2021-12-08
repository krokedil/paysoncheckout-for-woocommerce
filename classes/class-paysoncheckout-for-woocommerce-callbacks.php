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
			$payson_order = pco_wc_get_order( $payment_id, $subscription );
		} elseif ( isset( $_GET['subscription'] ) ) {
			$payment_id   = $_GET['subscription'];
			$subscription = true;
			$payson_order = pco_wc_get_order( $payment_id, $subscription );
		} elseif ( isset( $_GET['payment'] ) ) {
			$payment_id   = $_GET['payment'];
			$subscription = true;
			$payson_order = PCO_WC()->get_recurring_payment->request( $payment_id );
			$this->process_recurring_payment( $payment_id, $payson_order );
			header( 'HTTP/1.1 200 OK' );
			die();
		}

		if ( isset( $payment_id ) ) {
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
		$order = $this->get_wc_order_by_payment_id( $payment_id );

		// Did we get a match?
		if ( $order ) {
			PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. Payment id ' . $payment_id . '. already exist in order ID ' . $order->get_id() );
			$order_confirmation = PaysonCheckout_For_WooCommerce_Confirmation::get_instance();

			if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
				$result = true;
			} else {
				$result = $order_confirmation->confirm_payson_order( $order->get_id() );
			}

			if ( $result ) {
				$order->add_order_note( __( 'Order confirmed on a callback from Payson.', 'woocommerce-gateway-payson' ) );
				PaysonCheckout_For_WooCommerce_Logger::log( 'Order confirmed on a callback from Payson. Payment id: ' . $payment_id . ' Order id: ' . $order->get_id() );
			}
		} else {
			PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. We could NOT find Payment id ' . $payment_id );
		}
	}

	/**
	 * Gets the WooCommerce order from a Payson payment id.
	 *
	 * @param string $payment_id The Payson payment id.
	 * @return WC_Order
	 */
	public function get_wc_order_by_payment_id( $payment_id ) {
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
			$subscription_id  = get_post_meta( $order_id, '_payson_subscription_id', true );
			$order_payment_id = get_post_meta( $order_id, '_payson_checkout_id', true );

			if ( $order_payment_id === $payment_id || $subscription_id === $payment_id ) {
				$order_id_match = $order_id;
				break;
			}
		}

		if ( empty( $order_id_match ) ) {
			return null;
		}
		return wc_get_order( $order_id_match );
	}

	/**
	 * Handles the callback for recurring payments to complete the subscription renewal attached to the order.
	 *
	 * @param string $payment_id The Payson Payment id.
	 * @param array  $payson_order The Payson order.
	 * @return bool
	 */
	public function process_recurring_payment( $payment_id, $payson_order ) {
		if ( is_wp_error( $payson_order ) ) {
			return false;
		}

		$order = $this->get_wc_order_by_payment_id( $payment_id );

		if ( 'readyToShip' === $payson_order['status'] ) {
			PaysonCheckout_For_WooCommerce_Logger::log( 'Recurring payment order approved by Payson: ' . $payment_id );
			$order->add_order_note( sprintf( __( 'Subscription payment approved by Payson. Payson order id: %s', 'payson-checkout-for-woocommerce' ), $payson_order['id'] ) );
			update_post_meta( $order->get_id(), '_payson_renewal_confirmed', true );
			if ( ! wcs_order_contains_renewal( $order ) ) {
				$order->payment_complete( $payment_id );
			}
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_complete( $payson_order['purchaseId'] );
			}
		} elseif ( 'denied' === $payson_order['status'] ) {
			PaysonCheckout_For_WooCommerce_Logger::log( 'Recurring payment order denied by Payson: ' . $payment_id );
			$order->add_order_note( sprintf( __( 'Subscription payment denied by Payson. Payson order id: %s', 'payson-checkout-for-woocommerce' ), $payson_order['id'] ) );
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_failed();
			}
		}

		return true;
	}
}
new PaysonCheckout_For_WooCommerce_Callbacks();
