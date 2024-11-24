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

		// Set the return_url for change payment method.
		add_filter( 'pco_create_recurring_order_args', array( $this, 'set_subscription_order_redirect_urls' ) );

		// On successful payment method change, the customer is redirected back to the subscription view page. We need to handle the redirect and create a recurring token.
		add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'handle_redirect_from_change_payment_method' ) );

		// CHANGE PAYMENT METHOD ONLY: Delay updating the subscription's payment method until payment confirmation.
		// This ensures that if the customer cancels the action, the subscription retains its existing payment method.
		add_filter( 'woocommerce_subscriptions_update_payment_via_pay_shortcode', array( $this, 'handle_change_payment_method' ), 10, 2 );
	}

	/**
	 * Creates a recurring payment with Payson.
	 *
	 * @param string   $renewal_total The total price for the order.
	 * @param WC_Order $renewal_order The WooCommerce order for the renewal.
	 * @return void
	 */
	public function trigger_scheduled_payment( $renewal_total, $renewal_order ) {
		$order_id = $renewal_order->get_id();
		$order    = wc_get_order( $order_id );

		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
		reset( $subscriptions );
		$subscription_id = key( $subscriptions );
		$subscription_id = $order->get_meta( '_payson_subscription_id' );

		if ( empty( $subscription_id ) ) {
			$subscription    = self::get_parent_order( $order_id );
			$subscription_id = $subscription->get_meta( '_payson_subscription_id' );
		}

		// If the subscription_id is still empty, most likely, subscription was created using a different payment method.
		// We need to create a new recurring token.
		if ( empty( $subscription_id ) ) {
			$subscription = PCO_WC()->create_recurring_order->request( $order_id );
		}
		$order->update_meta_data( '_payson_subscription_id', $subscription_id );
		$order->save();

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
	 * Set the session URLs for change payment method request.
	 *
	 * Used for changing payment method.
	 *
	 * @param array $request The request data.
	 * @return array
	 */
	public function set_subscription_order_redirect_urls( $request ) {
		if ( ! self::is_change_payment_method() ) {
			return $request;
		}

		$body                                = json_decode( $request['body'], true );
		$subscription                        = self::get_subscription( absint( get_query_var( 'order-pay', 0 ) ) );
		$body['merchant']['confirmationUri'] = add_query_arg( 'pco_confirm', 1, $subscription->get_view_order_url() );
		$body['merchant']['checkoutUri']     = $subscription->get_checkout_payment_url();
		$request['body']                     = wp_json_encode( $body );

		return $request;
	}

	/**
	 * Handle the redirect from the change payment method page.
	 *
	 * @param int $order_id The subscription/order ID.
	 * @return void
	 */
	public function handle_redirect_from_change_payment_method( $order_id ) {
		$is_confirm = wc_get_var( $_REQUEST['pco_confirm'] );
		if ( ! empty( $is_confirm ) ) {
			$subscription = self::get_subscription( $order_id );
			$subscription->set_payment_method( 'paysoncheckout' );

			$subscription_id = $subscription->get_meta( '_payson_checkout_id' );
			$subscription->update_meta_data( '_payson_subscription_id', $subscription_id );

			// translators: %s Subscription id.
			$note = sprintf( __( 'Subscription payment method changed to Payson. Subscription id: %s.', 'payson-checkout-for-woocommerce' ), $subscription_id );
			$subscription->add_order_note( $note );
			$subscription->save();

			if ( function_exists( 'wc_print_notice' ) ) {
				wc_print_notice( __( 'Subscription payment method changed to Payson.' ), 'success' );
			}
		}
	}

	/**
	 * Do not change payment method until the subscription has been confirmed as paid.
	 *
	 * @param bool   $change_payment_method Whether to change the payment method.
	 * @param string $new_payment_method The new payment method.
	 * @return bool
	 */
	public function handle_change_payment_method( $change_payment_method, $new_payment_method ) {
		if ( 'paysoncheckout' === $new_payment_method ) {
			$change_payment_method = false;
		}

		return $change_payment_method;
	}

	/**
	 * Get a subscription's parent order.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return WC_Order|false The parent order or false if none is found.
	 */
	public static function get_parent_order( $order_id ) {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		foreach ( $subscriptions as $subscription ) {
			$parent_order = $subscription->get_parent();
			return $parent_order;
		}

		return false;
	}

	/**
	 * Retrieve a WC_Subscription from order ID.
	 *
	 * @param int $order_id  Woo order ID.
	 * @return bool|WC_Subscription The subscription object, or false if it cannot be found.
	 */
	public static function get_subscription( $order_id ) {
		return ! function_exists( 'wcs_get_subscription' ) ? false : wcs_get_subscription( $order_id );
	}

	/**
	 * Check if the current request is for changing the payment method.
	 *
	 * @return bool
	 */
	public static function is_change_payment_method() {
		return isset( $_GET['change_payment_method'] );
	}

	/**
	 * Check if an order contains a subscription.
	 *
	 * @param WC_Order $order The WooCommerce order or leave empty to use the cart (default).
	 * @return bool
	 */
	public static function order_has_subscription( $order ) {
		if ( empty( $order ) ) {
			return false;
		}

		return ( ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) ) ) || ( function_exists( 'wcs_is_subscription' ) && wcs_is_subscription( $order ) ) );
	}

	/**
	 * Check if a cart contains a subscription.
	 *
	 * @return bool
	 */
	public static function cart_has_subscription() {
		if ( ! is_checkout() ) {
			return false;
		}

		return ( class_exists( 'WC_Subscriptions_Cart' ) && ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal() ) ) || ( function_exists( 'wcs_cart_contains_failed_renewal_order_payment' ) && wcs_cart_contains_failed_renewal_order_payment() );
	}
}
