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
			$payment_id   = filter_input( INPUT_GET, 'checkout', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$subscription = false;
		} elseif ( isset( $_GET['subscription'] ) ) {
			$payment_id   = filter_input( INPUT_GET, 'subscription', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$subscription = true;
		} elseif ( isset( $_GET['payment'] ) ) {
			$payment_id   = filter_input( INPUT_GET, 'payment', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
			$payson_order = PCO_WC()->get_recurring_payment->request( $payment_id );
			$this->process_recurring_payment( $payment_id, $payson_order );
			header( 'HTTP/1.1 200 OK' );
			die();
		}

		PaysonCheckout_For_WooCommerce_Logger::log( 'Notification Listener hit: ' . wp_json_encode( $_GET ) . ' URL: ' . wc_get_var( $_SERVER['REQUEST_URI'] ) );
		$payson_order = pco_wc_get_order( $payment_id, $subscription );

		$status = 'HTTP/1.1 404 Not Found';
		if ( isset( $payment_id ) ) {
			if ( is_wp_error( $payson_order ) ) {
				PaysonCheckout_For_WooCommerce_Logger::log( 'Could not get order in notification callback. Payment ID: ' . $payment_id . 'Is subscription order: ' . $subscription );
			} else {
				$order = pco_get_order_by_payson_id( $payment_id );
				if ( empty( $order ) ) {
					PaysonCheckout_For_WooCommerce_Logger::log( 'Could not get WooCommerce order by payment id. No matching order found for payment id: ' . $payment_id );
				} elseif ( 'readyToShip' === $payson_order['status'] || 'customerSubscribed' === $payson_order['status'] ) {
					$this->maybe_schedule_callback( $payment_id );
					$status = 'HTTP/1.1 200 OK';
				}
			}
		}

		header( $status );
		exit;
	}

	/**
	 * Maybe schedules a callback handler.
	 *
	 * Only schedules one if there are none already pending for the same payment id.
	 *
	 * @param string $payment_id The Payson payment ID.
	 * @return void
	 */
	private function maybe_schedule_callback( $payment_id ) {
		$as_args          = array(
			'hook'   => 'pco_check_for_order',
			'status' => ActionScheduler_Store::STATUS_PENDING,
		);
		$scheduled_action = as_get_scheduled_actions( $as_args, OBJECT );

		/**
		* Loop all actions to check if this one has been scheduled already.
		*
		* @var ActionScheduler_Action $action The action from the Action scheduler.
		*/
		foreach ( $scheduled_action as $action ) {
			$action_args = $action->get_args();
			if ( $payment_id === $action_args['payment_id'] ) {
				PaysonCheckout_For_WooCommerce_Logger::log( "CALLBACK [action_scheduler]: The Payson order $payment_id has already been scheduled for processing." );
				return;
			}
		}

		$result = as_schedule_single_action(
			time() + 120,
			'pco_check_for_order',
			array(
				'payment_id' => $payment_id,
			)
		);

		if ( 0 === $result ) {
			PaysonCheckout_For_WooCommerce_Logger::log( "CALLBACK [action_scheduler]: There is no scheduled action for the Payson order $payment_id, and a new failed to be scheduled." );
		}
	}

	public function pco_check_for_order_callback( $payment_id ) {
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
			$order = wc_get_order( $order_id );

			$subscription_id  = $order->get_meta( '_payson_subscription_id' );
			$order_payment_id = $order->get_meta( '_payson_checkout_id' );

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
			PaysonCheckout_For_WooCommerce_Logger::log( 'Error processing recurring payment. Error message: ' . $payson_order->get_error_message() . ', Payment ID: ' . $payment_id );
			return false;
		}

		$order = $this->get_wc_order_by_payment_id( $payment_id );
		if ( ! $order ) {
			PaysonCheckout_For_WooCommerce_Logger::log( 'Error processing recurring payment. WooCommerce order not found. Payment ID: ' . $payment_id );
			return false;
		}

		if ( empty( $order ) ) {
			PaysonCheckout_For_WooCommerce_Logger::log( 'Could not get WooCommerce order by payment id. No matching order found for payment id: ' . $payment_id );
			return false;
		}

		if ( 'readyToShip' === $payson_order['status'] ) {
			PaysonCheckout_For_WooCommerce_Logger::log( 'Recurring payment order approved by Payson: ' . $payment_id );
			$order->add_order_note( sprintf( __( 'Subscription payment approved by Payson. Payson order id: %s', 'payson-checkout-for-woocommerce' ), $payson_order['id'] ) );
			$order->update_meta_data( '_payson_renewal_confirmed', true );
			$order->save();
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
