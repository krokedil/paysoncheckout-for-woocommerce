<?php
/**
 * Order management class file.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Order management class.
 */
class PaysonCheckout_For_WooCommerce_Order_Management {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_reservation' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'activate_reservation' ) );
	}

	/**
	 * Cancels the order with Payson.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return void
	 */
	public function cancel_reservation( $order_id ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using PaysonCheckout payment method, bail.
		if ( 'paysoncheckout' != $order->get_payment_method() ) {
			return;
		}

		// Check payson settings to see if we have the ordermanagement enabled.
		$payson_settings  = get_option( 'woocommerce_paysoncheckout_settings' );
		$order_management = 'yes' === $payson_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		$subscription = $this->check_if_subscription( $order );

		// Check if we have a payment id.
		$payment_id = get_post_meta( $order_id, '_payson_checkout_id', true );
		if ( empty( $payment_id ) ) {
			$order->add_order_note( __( 'PaysonCheckout reservation could not be cancelled. Missing Payson payment id.', 'woocommerce-gateway-paysoncheckout' ) );
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( get_post_meta( $order_id, '_paysoncheckout_reservation_cancelled', true ) ) {
			$order->add_order_note( __( 'Could not cancel PaysonCheckout reservation, PaysonCheckout reservation is already cancelled.', 'woocommerce-gateway-paysoncheckout' ) );
			return;
		}

		// Get the Payson order.
		$payson_order_tmp = ( $subscription ) ? PCO_WC()->get_recurring_payment->request( $payment_id ) : PCO_WC()->get_order->request( $payment_id );
		if ( is_wp_error( $payson_order_tmp ) ) {
			// If error save error message.
			$code          = $payson_order_tmp->get_error_code();
			$message       = $payson_order_tmp->get_error_message();
			$text          = __( 'Payson API Error on get Payson order before cancel: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			return;
		}
		// Set new order status.
		$payson_order_tmp['status'] = 'canceled';

		// Cancel the order.
		$payson_order = ( $subscription ) ? PCO_WC()->update_recurring_payment->request( $order_id, $payson_order_tmp, $payment_id ) : PCO_WC()->manage_order->request( $order_id, $payson_order_tmp, $payment_id );

		// Check if we where successfull.
		if ( ! is_wp_error( $payson_order ) && 'canceled' == $payson_order['status'] ) {
			// Add time stamp, used to prevent duplicate cancellations for the same order.
			update_post_meta( $order_id, '_paysoncheckout_reservation_cancelled', current_time( 'mysql' ) );
			// Add Payson order status.
			update_post_meta( $order_id, '_paysoncheckout_order_status', $payson_order['status'] );
			$order->add_order_note( __( 'PaysonCheckout reservation was successfully cancelled.', 'woocommerce-gateway-paysoncheckout' ) );
		} elseif ( is_wp_error( $payson_order ) ) {
			// If error save error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error on Payson cancel order: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
		} else {
			$order->add_order_note( __( 'PaysonCheckout reservation could not be cancelled.', 'woocommerce-gateway-paysoncheckout' ) );
		}
	}

	/**
	 * Activate the order with Payson.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return void
	 */
	public function activate_reservation( $order_id ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using PaysonCheckout payment method, bail.
		if ( 'paysoncheckout' != $order->get_payment_method() ) {
			return;
		}

		// Check payson settings to see if we have the ordermanagement enabled.
		$payson_settings  = get_option( 'woocommerce_paysoncheckout_settings' );
		$order_management = 'yes' === $payson_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		// Check if we have a payment id.
		$payment_id = get_post_meta( $order_id, '_payson_checkout_id', true );
		if ( empty( $payment_id ) ) {
			$order->add_order_note( __( 'PaysonCheckout reservation could not be activated. Missing Payson payment id.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->set_status( 'on-hold' );
			return;
		}

		// If this reservation was already activated, do nothing.
		if ( get_post_meta( $order_id, '_paysoncheckout_reservation_activated', true ) ) {
			$order->add_order_note( __( 'Could not activate PaysonCheckout reservation, PaysonCheckout reservation is already activated.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->set_status( 'on-hold' );
			return;
		}

		$subscription = $this->check_if_subscription( $order );
		// Get the Payson order.
		$payson_order_tmp = ( $subscription ) ? PCO_WC()->get_recurring_payment->request( $payment_id ) : PCO_WC()->get_order->request( $payment_id );
		if ( is_wp_error( $payson_order_tmp ) ) {
			// If error save error message.
			$code          = $payson_order_tmp->get_error_code();
			$message       = $payson_order_tmp->get_error_message();
			$text          = __( 'Payson API Error on get Payson order before activation: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			$order->set_status( 'on-hold' );
			return;
		}
		// Set new order status.
		$payson_order_tmp['status'] = 'shipped';

		// Cancel the order.
		$payson_order = ( $subscription ) ? PCO_WC()->update_recurring_payment->request( $order_id, $payson_order_tmp, $payment_id ) : PCO_WC()->manage_order->request( $order_id, $payson_order_tmp, $payment_id );

		// Check if we where successfull.
		if ( ! is_wp_error( $payson_order ) && 'shipped' == $payson_order['status'] ) {
			// Add time stamp, used to prevent duplicate activations for the same order.
			update_post_meta( $order_id, '_paysoncheckout_reservation_activated', current_time( 'mysql' ) );
			// Add Payson order status.
			update_post_meta( $order_id, '_paysoncheckout_order_status', $payson_order['status'] );
			$order->add_order_note( __( 'PaysonCheckout reservation was successfully activated.', 'woocommerce-gateway-paysoncheckout' ) );
		} elseif ( is_wp_error( $payson_order ) ) {
			// If error save error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error on Payson activate order: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			$order->set_status( 'on-hold' );
		} else {
			$order->add_order_note( __( 'PaysonCheckout reservation could not be activatied.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->set_status( 'on-hold' );
		}
	}

	/**
	 * Refunds the full amount.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return boolean
	 */
	public function refund_full_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		// If this order wasn't created using PaysonCheckout payment method, bail.
		if ( 'paysoncheckout' != $order->get_payment_method() ) {
			return false;
		}

		// Check payson settings to see if we have the ordermanagement enabled.
		$payson_settings  = get_option( 'woocommerce_paysoncheckout_settings' );
		$order_management = 'yes' === $payson_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return false;
		}

		// Check if we have a payment id.
		$payment_id = get_post_meta( $order_id, '_payson_checkout_id', true );
		if ( empty( $payment_id ) ) {
			$order->add_order_note( __( 'PaysonCheckout order could not be refunded. Missing Payson payment id.', 'woocommerce-gateway-paysoncheckout' ) );
			return false;
		}

		$subscription = $this->check_if_subscription( $order );

		// Get the Payson order.
		$payson_order_tmp = ( $subscription ) ? PCO_WC()->get_recurring_payment->request( $payment_id ) : PCO_WC()->get_order->request( $payment_id );
		if ( is_wp_error( $payson_order_tmp ) ) {
			// If error save error message.
			$code          = $payson_order_tmp->get_error_code();
			$message       = $payson_order_tmp->get_error_message();
			$text          = __( 'Payson API Error on get Payson order before refund: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			return false;
		}

		if ( 'shipped' === $payson_order_tmp['status'] || 'paidToAccount' === $payson_order_tmp['status'] ) {
			$updated_items = array();
			foreach ( $payson_order_tmp['order']['items'] as $item ) {
				$item['creditedAmount'] = ( $item['unitPrice'] * $item['quantity'] );
				array_push( $updated_items, $item );
			}
			$payson_order_tmp['order']['items'] = $updated_items;
			$payson_order                       = PCO_WC()->refund_order->request( $order_id, $payson_order_tmp, $payment_id, $subscription );
			if ( is_wp_error( $payson_order ) ) {
				// If error save error message and return false.
				$code          = $payson_order_tmp->get_error_code();
				$message       = $payson_order_tmp->get_error_message();
				$text          = __( 'Payson API Error on Payson refund: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
				$formated_text = sprintf( $text, $code, $message );
				$order->add_order_note( $formated_text );
				return false;
			}
			$order->add_order_note( __( 'PaysonCheckout reservation was successfully refunded.', 'woocommerce-gateway-paysoncheckout' ) );
			return true;
		}
		$order->add_order_note( __( 'PaysonCheckout order could not be refunded.', 'woocommerce-gateway-paysoncheckout' ) );
		return false;

	}

	/**
	 * Checks if the order is a subscription order or not
	 *
	 * @param object $order WC_Order object.
	 * @return boolean
	 */
	public function check_if_subscription( $order ) {
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_renewal( $order ) ) {
			return true;
		}
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
			return true;
		}
		return false;
	}
}
