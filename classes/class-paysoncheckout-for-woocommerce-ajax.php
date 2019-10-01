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
			'pco_wc_checkout_error'        => true,
			'pco_wc_change_payment_method' => true,
			'pco_wc_update_session'        => true,
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
		wp_die();
	}

	/**
	 * Update the Payson order.
	 */
	public static function pco_wc_update_checkout() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'pco_wc_update_checkout' ) ) { // phpcs: ignore.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$subscription = ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) ? true : false;

		// Get the payson order.
		$payson_order_tmp = ( $subscription ) ? PCO_WC()->get_recurring_order->request( WC()->session->get( 'payson_payment_id' ) ) : PCO_WC()->get_order->request( WC()->session->get( 'payson_payment_id' ) );
		if ( is_wp_error( $payson_order_tmp ) ) {
			// If error return error message.
			$code          = $payson_order_tmp->get_error_code();
			$message       = $payson_order_tmp->get_error_message();
			$text          = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			wp_send_json_error( $formated_text );
			wp_die();
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
			wp_die();
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
				'address'   => $payson_order['customer'],
				'changed'   => $changed,
				'pco_nonce' => wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce', true, false ),
			)
		);
		wp_die();
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
		$payson_order = PCO_WC()->get_order->request( WC()->session->get( 'payson_payment_id' ) );
		if ( is_wp_error( $payson_order ) ) {
			// If error return error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			wp_send_json_error( $formated_text );
			wp_die();
		}
		wp_send_json_success( $payson_order );
		wp_die();
	}

	/**
	 * Creates a fallback order on Checkout error JS event.
	 *
	 * @return void
	 */
	public static function pco_wc_checkout_error() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'pco_wc_checkout_error' ) ) { // phpcs: ignore.
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		// Get the payson order.
		$payson_order = PCO_WC()->get_order->request( WC()->session->get( 'payson_payment_id' ) );
		if ( is_wp_error( $payson_order ) ) {
			// If error return error message.
			$code          = $payson_order->get_error_code();
			$message       = $payson_order->get_error_message();
			$text          = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
			$formated_text = sprintf( $text, $code, $message );
			wp_send_json_error( $formated_text );
			wp_die();
		}

		// Get error message.
		if ( ! empty( $_POST['error_message'] ) ) { // Input var okay.
			$error_message = 'Error message: ' . sanitize_text_field( trim( $_POST['error_message'] ) );
		} else {
			$error_message = 'Error message could not be retreived';
		}

		// Create the order and send redirect url.
		$order_id     = PCO_WC()->backup_order->checkout_error( $payson_order, $error_message );
		$order        = wc_get_order( $order_id );
		$redirect_url = $order->get_checkout_order_received_url();
		wp_send_json_success( $redirect_url );
		wp_die();
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
		wp_die();
	}
}
PaysonCheckout_For_WooCommerce_AJAX::init();
