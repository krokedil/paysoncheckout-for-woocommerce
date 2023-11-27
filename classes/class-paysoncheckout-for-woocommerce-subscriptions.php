<?php
/**
 * Subscription class.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PaysonCheckout Subscription class.
 */
class PaysonCheckout_For_WooCommerce_Subscriptions {
	public const GATEWAY_ID      = 'paysoncheckout';
	public const RECURRING_TOKEN = '_payson_subscription_id';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_scheduled_subscription_payment_paysoncheckout', array( $this, 'trigger_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_cancelled_' . self::GATEWAY_ID, array( $this, 'cancel_scheduled_payment' ) );
	}

	/**
	 * Creates a recurring payment with Payson.
	 *
	 * @param string   $renewal_total The total price for the order.
	 * @param WC_Order $renewal_order The WooCommerce order for the renewal.
	 */
	public function trigger_scheduled_payment( $renewal_total, $renewal_order ) {
		$order_id = $renewal_order->get_id();
		$order    = wc_get_order( $order_id );

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		reset( $subscriptions );
		$subscription_id = key( $subscriptions );
		$subscription_id = $order->get_meta( '_payson_subscription_id' );

		if ( empty( $subscription_id ) ) {
			$subscription    = wc_get_order( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id ) );
			$subscription_id = $subscription->get_meta( '_payson_subscription_id' );
			$order->update_meta_data( '_payson_subscription_id', $subscription_id );
			$order->save();
		}

		$payson_order = PCO_WC()->recurring_payment->request( $subscription_id, $order_id );
		if ( is_wp_error( $payson_order ) ) {
			// If error save error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error on make recurring payment: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$renewal_order->add_order_note( $formated_text );
			foreach ( $subscriptions as $subscription ) {
				$subscription->payment_failed();
			}
		} else {
			$order->update_meta_data( '_payson_checkout_id', $payson_order['id'] );
			$order->save();
			// translators: %s Payson order id.
			$renewal_order->add_order_note( sprintf( __( 'Pending subscription payment made with Payson, waiting on confirmation from Payson. Payson order id: %s', 'payson-checkout-for-woocommerce' ), $payson_order['id'] ) );
			// Set transaction id and wait for callback to complete the order.
			$renewal_order->set_transaction_id( $payson_order['purchaseId'] );
			$renewal_order->save();
		}
	}

		/**
		 * Cancel the customer token to prevent further payments using the token.
		 *
		 * Note: When changing payment method, WC Subscriptions will cancel the subscription with existing payment gateway (which triggers this functions), and create a new one. Thus the new subscription must generate a new customer token.
		 *
		 * @see WC_Subscriptions_Change_Payment_Gateway::update_payment_method
		 *
		 * @param mixed $subscription WC_Subscription
		 * @return void
		 */
	public function cancel_scheduled_payment( $subscription ) {
		$payment_id = $this->get_recurring_tokens( $subscription->get_id() );

		$response = PCO_WC()->cancel_recurring_payment->request( $subscription );
		if ( ! is_wp_error( $response ) ) {
			$subscription->add_order_note( __( 'Subscription cancelled with Klarna Payments.', 'klarna-payments-for-woocommerce' ) );
		} else {
			$error_message = $response->get_error_message();
			// Translators: Error message.
			$subscription->add_order_note( sprintf( __( 'Subscription cancellation failed with Klarna Payments. Reason: %1$s', 'klarna-payments-for-woocommerce' ), $error_message ) );
		}

		// The session data must be deleted since Klarna doesn't allow reusing a session when generating a new customer token to change payment method.
		$subscription->delete_meta_data( '_kp_session_data' );
		$subscription->save();

	}

		/**
		 * Retrieve the necessary tokens required for subscriptions (unattended) payments.
		 *
		 * @param  int $order_id The WooCommerce order id.
		 * @return string The recurring token. If none is found, an empty string is returned.
		 */
	public static function get_recurring_tokens( $order_id ) {
		$order           = wc_get_order( $order_id );
		$recurring_token = $order->get_meta( self::RECURRING_TOKEN );

		if ( empty( $recurring_token ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			foreach ( $subscriptions as $subscription ) {
				$parent_order    = $subscription->get_parent();
				$recurring_token = $parent_order->get_meta( self::RECURRING_TOKEN );

				if ( ! empty( $recurring_token ) ) {
					break;
				}
			}
		}

		return $recurring_token;
	}
}
