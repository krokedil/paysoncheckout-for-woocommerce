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
	 * Messages for recurring errors.
	 *
	 * @var array
	 */
	private $error_message;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_reservation' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'activate_reservation' ) );

		$this->error_message = array(
			'refund_negative' => __( 'Please make sure that you only use positive numbers when initiating a refund. Using negative numbers can cause problems.', 'payson-checkout-for-woocommerce' ),
		);

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
		if ( ! empty( $order->get_meta('_paysoncheckout_reservation_activated') ) ) {
			return;
		}

		// Check if the order has been paid.
		if ( empty( $order->get_date_paid() ) ) {
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
		$payment_id = $order->get_meta('_payson_checkout_id');
		if ( empty( $payment_id ) ) {
			$order->add_order_note( __( 'PaysonCheckout reservation could not be cancelled. Missing Payson payment id.', 'woocommerce-gateway-paysoncheckout' ) );
			return;
		}

		// If this reservation was already cancelled, do nothing.
		if ( $order->get_meta( '_paysoncheckout_reservation_cancelled') ) {
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

			// Check if the order has already been canceled through the merchant portal.
		} elseif ( isset( $payson_order_tmp['history']['canceled'] ) ) {
			$order->update_meta_data( '_paysoncheckout_reservation_cancelled', ( new DateTime( $payson_order_tmp['history']['canceled'] ) )->format( 'Y-m-d H:i:s' )  );
			$order->update_meta_data( '_paysoncheckout_order_status', $payson_tmp_order['status'] );
			$order->add_order_note( __( 'PaysonCheckout reservation was successfully cancelled.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->save();
			return;
		}

		// Set new order status.
		$payson_order_tmp['status'] = 'canceled';

		// Cancel the order.
		$payson_order = ( $subscription ) ? PCO_WC()->update_recurring_payment->request( $order_id, $payson_order_tmp, $payment_id ) : PCO_WC()->manage_order->request( $order_id, $payson_order_tmp, $payment_id );

		// Check if we where successfull.
		if ( ! is_wp_error( $payson_order ) && 'canceled' == $payson_order['status'] ) {
			// Add time stamp, used to prevent duplicate cancellations for the same order.
			$order->update_meta_data( '_paysoncheckout_reservation_cancelled', current_time( 'mysql' ) );
			// Add Payson order status.
			$order->update_meta_data( '_paysoncheckout_order_status', $payson_order['status'] );
			$order->save();
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

		// If it is a subscription, check if the order has been confirmed.
		if (
			class_exists( 'WC_Subscriptions' )
			&& wcs_order_contains_renewal( $order )
			&& empty( $order->get_meta( '_payson_renewal_confirmed' ) )

		) {
			$order->add_order_note( __( 'Please wait for Payson to confirm the order before processing the order.', 'woocommerce-gateway-payson' ) );
			$order->set_status( 'on-hold' );
			$order->save();
			return;
		}

		// Check payson settings to see if we have the ordermanagement enabled.
		$payson_settings  = get_option( 'woocommerce_paysoncheckout_settings' );
		$order_management = 'yes' === $payson_settings['order_management'] ? true : false;
		if ( ! $order_management ) {
			return;
		}

		$subscription = $this->check_if_subscription( $order );
		// If this is a free subscription then stop here.
		if ( $subscription && 0 >= $order->get_total() ) {
			return;
		}

		// Check if we have a payment id.
		$payment_id = $order->get_meta( '_payson_checkout_id' );

		if ( empty( $payment_id ) ) {
			$order->add_order_note( __( 'PaysonCheckout reservation could not be activated. Missing Payson payment id.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->set_status( 'on-hold' );
			$order->save();
			return;
		}

		// If this reservation was already activated, do nothing.
		if ( $order->get_meta( '_paysoncheckout_reservation_activated' ) ) {
			 $order->add_order_note( __( 'Could not activate PaysonCheckout reservation, PaysonCheckout reservation is already activated.', 'woocommerce-gateway-paysoncheckout' ) );
			 $order->save();
			return;
		}

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
			$order->save();
			return;

			// Check if the order has already been activated through the merchant portal.
		} elseif ( isset( $payson_order_tmp['history']['shipped'] ) ) {
			$order->update_meta_data('_paysoncheckout_reservation_activated', ( new DateTime( $payson_order_tmp['history']['shipped'] ) )->format( 'Y-m-d H:i:s' ) );
			$order->update_meta_data('_paysoncheckout_order_status', $payson_order_tmp['status'] );
			$order->add_order_note( __( 'PaysonCheckout reservation was successfully activated.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->save();
			return;

		}
		// Set new order status.
		$payson_order_tmp['status'] = 'shipped';

		// Cancel the order.
		$payson_order = ( $subscription ) ? PCO_WC()->update_recurring_payment->request( $order_id, $payson_order_tmp, $payment_id ) : PCO_WC()->manage_order->request( $order_id, $payson_order_tmp, $payment_id );

		// Check if we where successfull.
		if ( ! is_wp_error( $payson_order ) && 'shipped' == $payson_order['status'] ) {
			// Add time stamp, used to prevent duplicate activations for the same order.
			$order->update_meta_data('_paysoncheckout_reservation_activated', current_time( 'mysql' ) );

			// Add Payson order status.
			$order->update_meta_data('_paysoncheckout_order_status', $payson_order['status'] );
			$order->add_order_note( __( 'PaysonCheckout reservation was successfully activated.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->save();

		} elseif ( is_wp_error( $payson_order ) ) {
			// If error save error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error on Payson activate order: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			$order->set_status( 'on-hold' );
			$order->save();
		} else {
			$order->add_order_note( __( 'PaysonCheckout reservation could not be activatied.', 'woocommerce-gateway-paysoncheckout' ) );
			$order->set_status( 'on-hold' );
			$order->save();
		}
	}

	/**
	 * Refunds the payments.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return bool Did the refund go through okay?
	 */
	public function refund_payment( $order_id ) {
		$order        = wc_get_order( $order_id );
		$payment_id   = $order->get_meta( '_payson_checkout_id' );
		$subscription = $this->check_if_subscription( $order );

		// Get the Payson order.
		$payson_order_tmp = ( $subscription ) ? PCO_WC()->get_recurring_payment->request( $payment_id ) : PCO_WC()->get_order->request( $payment_id );

		// Fetch the most recent, current refund.
		$refund_order = $order->get_refunds()[0];

		// Check if the order has already been _fully_ refunded through the merchant portal. This does not account for part refunds.
		if ( floatval( $order->get_total() === $payson_order_tmp['order']['totalCreditedAmount'] ) ) {
			$order->add_order_note( __( 'PaysonCheckout reservation was successfully refunded for ', 'woocommerce-gateway-paysoncheckout' ) . wc_price( abs( $refund_order->get_total() ) ) );
			return true;
		}

		foreach ( $payson_order_tmp['order']['items'] as $key => $payson_item ) {
			$continue = false;
			foreach ( $refund_order->get_items() as $refund_item ) {
				$product = $refund_item->get_product();
				if ( $product->get_sku() === $payson_item['reference'] || (string) $product->get_id() === $payson_item['reference'] ) {
					$product_total = $refund_item->get_total();
					$product_tax   = $refund_item->get_total_tax();

					// Check if the customer has entered a negative value (e.g., -50).
					if ( $product_total > 0 || $product_tax > 0 ) {
						$order->add_order_note( $this->error_message['refund_negative'] );
						$order->save();
						return new WP_Error( 'refund_negative', $this->error_message['refund_negative'] );
					}

					$payson_item['creditedAmount']              = $payson_item['creditedAmount'] + abs( $product_total + $product_tax );
					$payson_order_tmp['order']['items'][ $key ] = $payson_item;
					$continue                                   = true;
					break;
				}
			}

			if ( $continue ) {
				continue;
			}

			$refund_shipping = $refund_order->get_shipping_method();

			if ( html_entity_decode( $payson_item['name'] ) === html_entity_decode( $refund_shipping ) ) {
				$shipping_total = $refund_order->get_shipping_total();
				$shipping_tax   = $refund_order->get_shipping_tax();

				// Check if the customer has entered a negative value.
				if ( $shipping_total > 0 || $shipping_tax > 0 ) {
					$order->add_order_note( $this->error_message['refund_negative'] );
					$order->save();
					return new WP_Error( 'refund_negative', $this->error_message['refund_negative'] );
				}

				$payson_item['creditedAmount']              = $payson_item['creditedAmount'] + abs( $shipping_total + $shipping_tax );
				$payson_order_tmp['order']['items'][ $key ] = $payson_item;
				$continue                                   = true;
				break;
			}

			if ( $continue ) {
				continue;
			}

			foreach ( $refund_order->get_fees() as $refund_fee ) {
				if ( $payson_item['name'] === $refund_fee->get_name() ) {
					$payson_item['creditedAmount']              = $payson_item['creditedAmount'] + abs( $refund_fee->get_total() + $refund_fee->get_total_tax() );
					$payson_order_tmp['order']['items'][ $key ] = $payson_item;
					$continue                                   = true;
					break;
				}
			}
		}

		$payson_order_tmp['order']['totalCreditedAmount'] = $payson_order_tmp['order']['totalCreditedAmount'] + abs( $refund_order->get_total() );
		$payson_order                                     = PCO_WC()->refund_order->request( $order_id, $payson_order_tmp, $payment_id, $subscription );

		if ( is_wp_error( $payson_order ) ) {
			// If error, save error message and return WP_Error.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error on Payson refund: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			$order->add_order_note( $formated_text );
			return new WP_Error( $code, strip_tags( $formated_text ) );
		}

		// If Payson do not accept the refund, the totalCreditedAmount we sent, and the one they respond with, will not match.
		if ( $payson_order_tmp['order']['totalCreditedAmount'] !== $payson_order['order']['totalCreditedAmount'] ) {
			$order->add_order_note( __( 'Credited amount mismatch', 'payson-checkout-for-woocommerce' ) );
			return new WP_Error( __( 'Credited amount mismatch', 'payson-checkout-for-woocommerce' ) );
		}

		$order->add_order_note( __( 'PaysonCheckout reservation was successfully refunded for ', 'woocommerce-gateway-paysoncheckout' ) . wc_price( abs( $refund_order->get_total() ) ) );
		return true;
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
