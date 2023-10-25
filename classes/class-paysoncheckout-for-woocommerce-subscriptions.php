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
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_scheduled_subscription_payment_paysoncheckout', array( $this, 'trigger_scheduled_payment' ), 10, 2 );
	}

	/**
	 * Creates a recurring payment with Payson.
	 *
	 * @param string   $renewal_total The total price for the order.
	 * @param WC_Order $renewal_order The WooCommerce order for the renewal.
	 */
	public function trigger_scheduled_payment( $renewal_total, $renewal_order ) {
		$order_id = $renewal_order->get_id();
		$order = wc_get_order( $order_id );

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		reset( $subscriptions );
		$subscription_id = key( $subscriptions );
		$subscription_id = get_post_meta( $order_id, '_payson_subscription_id', true );

		if ( empty( $subscription_id ) ) {
			$subscription_id = get_post_meta( WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id ), '_payson_subscription_id', true );
			$order->update_meta_data('_payson_subscription_id', $subscription_id );
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
}
