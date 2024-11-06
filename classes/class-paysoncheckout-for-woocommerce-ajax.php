<?php
/**
 * Ajax class file.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ajax class.
 */
class PaysonCheckout_For_WooCommerce_AJAX extends WC_AJAX {
	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'pco_wc_address_changed'       => true,
			'pco_wc_update_checkout'       => true,
			'pco_wc_get_order'             => true,
			'pco_wc_change_payment_method' => true,
			'pco_wc_update_session'        => true,
			'pco_wc_log_js'                => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Updates the customer with address data.
	 */
	public static function pco_wc_address_changed() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'pco_wc_address_changed' ) ) { // phpcs: ignore.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$address       = $_POST['address'];
		$customer_data = array();

		if ( isset( $address['Email'] ) ) {
			$customer_data['billing_email'] = $address['Email'];
		}
		if ( isset( $address['PostalCode'] ) ) {
			$customer_data['billing_postcode']  = $address['PostalCode'];
			$customer_data['shipping_postcode'] = $address['PostalCode'];
		}
		if ( isset( $address['FirstName'] ) ) {
			$customer_data['billing_first_name']  = $address['FirstName'];
			$customer_data['shipping_first_name'] = $address['FirstName'];
		}
		if ( isset( $address['LastName'] ) ) {
			$customer_data['billing_last_name']  = $address['LastName'];
			$customer_data['shipping_last_name'] = $address['LastName'];
		}
		if ( isset( $address['Street'] ) ) {
			$customer_data['billing_address_1']  = $address['Street'];
			$customer_data['shipping_address_1'] = $address['Street'];
		}
		if ( isset( $address['City'] ) ) {
			$customer_data['billing_city']  = $address['City'];
			$customer_data['shipping_city'] = $address['City'];
		}
		if ( isset( $address['CountryCode'] ) ) {
			$customer_data['billing_country']  = $address['CountryCode'];
			$customer_data['shipping_country'] = $address['CountryCode'];
		}
		if ( isset( $address['Phone'] ) ) {
			$customer_data['billing_phone'] = $address['Phone'];
		}

		WC()->customer->set_props( $customer_data );
		WC()->customer->save();
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_totals();

		wp_send_json_success();
	}

	/**
	 * Update the Payson order.
	 */
	public static function pco_wc_update_checkout() {

		if ( ! wp_verify_nonce( $_POST['nonce'], 'pco_wc_update_checkout' ) ) { // phpcs: ignore.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		if ( ! pco_compare_currencies() ) {
			wp_send_json_success(
				array(
					'currenciesChanged' => 'currenciesChanged',
				)
			);
		}

		$subscription = ( class_exists( 'WC_Subscriptions_Cart' ) && ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal() ) ) ? true : false;

		if ( ! $subscription && ! WC()->cart->needs_payment() ) {
			wp_send_json_success(
				array(
					'refreshZeroAmount' => 'refreshZeroAmount',
				)
			);
		}

		// Get the payson order.
		$payson_order_tmp = ( $subscription ) ? PCO_WC()->get_recurring_order->request( WC()->session->get( 'payson_payment_id' ) ) : PCO_WC()->get_order->request( WC()->session->get( 'payson_payment_id' ) );
		if ( is_wp_error( $payson_order_tmp ) ) {
			// If error return error message.
			$code          = $payson_order_tmp->get_error_code();
			$message       = $payson_order_tmp->get_error_message();
			$text          = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			wp_send_json_error( $formated_text );
		}

		// If the order status is readyToShip, it means the purchase has already been completed. Let's try to redirect the user to confirmation page.
		if ( 'readyToShip' === $payson_order_tmp['status'] ) {
			$order = pco_get_order_by_payson_id( $payson_order_tmp['id'] );
			if ( ! empty( $order ) ) {
				wp_safe_redirect( $order->get_checkout_order_received_url() );
				exit;
			}

			// As a default, if the order cannot be found, respond with an error. This should trigger a reload on the frontend that should detect this order is already completed.
			wp_send_json_error( __( 'The purchase has already been completed.', 'payson-checkout-for-woocommerce' ) );
		}

		// Get needed variables from the payson order.
		$payson_data = array(
			'status'   => $payson_order_tmp['status'],
			'customer' => $payson_order_tmp['customer'],
		);

		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		// Update the payson order.
		$payson_order = ( $subscription ) ? PCO_WC()->update_recurring_order->request( null, $payson_data ) : PCO_WC()->update_order->request( null, $payson_data );

		if ( is_wp_error( $payson_order ) ) {
			// If error return error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			wp_send_json_error( $formated_text );
		}

		// If update order returned false, then we did not need to update the order so we need to use the tmp order from the get.
		if ( $payson_order ) {
			$payson_order = $payson_order_tmp;
		}

		if ( false === $payson_order ) {
			$changed = false;
		} else {
			$changed = true;
		}

		wp_send_json_success(
			array(
				'address'   => is_array( $payson_order ) ? $payson_order['customer'] : null,
				'changed'   => $changed,
				'pco_nonce' => wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce', true, false ),
			)
		);
	}

	/**
	 * Gets the Payson order.
	 *
	 * @return void
	 */
	public static function pco_wc_get_order() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'pco_wc_get_order' ) ) { // phpcs: ignore.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		// Get the payson order.
		$subscription = ( class_exists( 'WC_Subscriptions_Cart' ) && ( WC_Subscriptions_Cart::cart_contains_subscription() || wcs_cart_contains_renewal() ) ) ? true : false;

		$payson_order = ( $subscription ) ? PCO_WC()->get_recurring_order->request( WC()->session->get( 'payson_payment_id' ) ) : PCO_WC()->get_order->request( WC()->session->get( 'payson_payment_id' ) );
		if ( is_wp_error( $payson_order ) ) {
			// If error return error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			wp_send_json_error( $formated_text );
		}

		// Add - as last name if its missing. Happens when its a B2B purchase.
		if ( empty( $payson_order['customer']['lastName'] ) ) {
			$payson_order['customer']['lastName'] = '-';
		}

		wp_send_json_success( $payson_order );
	}

	/**
	 * Changes the selected payment method based on the posted params.
	 *
	 * @return void
	 */
	public static function pco_wc_change_payment_method() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'pco_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

		if ( ! empty( $_POST['order_id'] ) ) { // Handle pay for order page switch payment method.
			$order = wc_get_order( $_POST['order_id'] );
			if ( 'false' === $_POST['pco'] ) {
				$first_gateway = reset( $available_gateways );
				if ( 'paysoncheckout' !== $first_gateway->id ) {
					$order->set_payment_method( $first_gateway->id );
				} else {
					$second_gateway = next( $available_gateways );
					$order->set_payment_method( $second_gateway->id );
				}
			} else {
				$order->set_payment_method( 'paysoncheckout' );
			}
			$order->save();
			$redirect = $order->get_checkout_payment_url();
			$data     = array(
				'redirect' => $redirect,
			);
			wp_send_json_success( $data );
		} else { // Handle checkout page switch payment method.
			if ( 'false' === $_POST['pco'] ) {
				// Set chosen payment method to first gateway that is not PaysonCheckout for WooCommerce.
				$first_gateway = reset( $available_gateways );
				if ( 'paysoncheckout' !== $first_gateway->id ) {
					WC()->session->set( 'chosen_payment_method', $first_gateway->id );
				} else {
					$second_gateway = next( $available_gateways );
					WC()->session->set( 'chosen_payment_method', $second_gateway->id );
				}
			} else {
				WC()->session->set( 'chosen_payment_method', 'paysoncheckout' );
			}

			WC()->payment_gateways()->set_current_gateway( $available_gateways );
			$redirect = wc_get_checkout_url();
			$data     = array(
				'redirect' => $redirect,
			);
			wp_send_json_success( $data );
		}
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function pco_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'pco_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$posted_message    = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$payson_payment_id = WC()->session->get( 'payson_payment_id' );
		$message           = "Frontend JS $payson_payment_id: $posted_message";
		PaysonCheckout_For_WooCommerce_Logger::log( $message );
		wp_send_json_success();
	}
}
PaysonCheckout_For_WooCommerce_AJAX::init();
