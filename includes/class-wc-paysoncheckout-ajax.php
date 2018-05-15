<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 *
 * @class    WC_PaysonCheckout_Ajax_Handler
 * @version  1.0.0
 * @package  WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Ajax {

	/**
	 * WC_PaysonCheckout_Ajax constructor.
	 *
	 */
	public function __construct() {
		add_action( 'wp_ajax_wc_paysoncheckout_iframe', array( $this, 'get_paysoncheckout_iframe' ) );
		add_action( 'wp_ajax_nopriv_wc_paysoncheckout_iframe', array( $this, 'get_paysoncheckout_iframe' ) );

		// Ajax for Address Update JS call from Payson
		add_action( 'wp_ajax_payson_address_changed_callback', array( $this, 'payson_address_changed_callback' ) );
		add_action( 'wp_ajax_nopriv_payson_address_changed_callback', array( $this, 'payson_address_changed_callback' ) );

		// Ajax to add order notes as a session for the customer
		add_action( 'wp_ajax_payson_customer_order_note', array( $this, 'payson_add_customer_order_note' ) );
		add_action( 'wp_ajax_nopriv_payson_customer_order_note', array( $this, 'payson_add_customer_order_note' ) );

		// Ajax to change payment method
		add_action( 'wp_ajax_payson_change_payment_method', array( $this, 'payson_change_payment_method' ) );
		add_action( 'wp_ajax_nopriv_payson_change_payment_method', array( $this, 'payson_change_payment_method' ) );

		// Ajax to update checkout
		add_action( 'wp_ajax_payson_update_checkout', array( $this, 'update_checkout' ) );
		add_action( 'wp_ajax_nopriv_payson_update_checkout', array( $this, 'update_checkout' ) );

		// Ajax to get customer data
		add_action( 'wp_ajax_payson_get_customer_data', array( $this, 'get_customer_data' ) );
		add_action( 'wp_ajax_nopriv_payson_get_customer_data', array( $this, 'get_customer_data' ) );

		// Ajax to get customer data
		add_action( 'wp_ajax_payson_on_checkout_error', array( $this, 'on_checkout_error' ) );
		add_action( 'wp_ajax_nopriv_payson_on_checkout_error', array( $this, 'on_checkout_error' ) );
		
	}

	/*
	 * Retrieve and print the PaysonCheckout iframe.
	 */
	public function get_paysoncheckout_iframe() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_payson_checkout_nonce' ) ) {
			exit( 'Nonce can not be verified.' );
		}

		$wc_order = new WC_PaysonCheckout_WC_Order();
		$order_id = $wc_order->update_or_create_local_order();

		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$checkout   = $payson_api->get_checkout( $order_id );

		$iframe = '<div class="paysoncheckout-container" style="width:100%;  margin-left:auto; margin-right:auto;">';
		if ( is_wp_error( $checkout ) ) {
			$iframe .= $checkout->get_error_message();
		} else {
			$iframe .= $checkout->snippet;
		}
		$iframe .= '</div>';

		wp_send_json_success( array( 'iframe' => $iframe, 'order_id' => $order_id ) );
		wp_die();
	}

	/**
	 * Updates local order with address received from Payson
	 * Changes the order status to Pending
	 *
	 * @since  0.8.3
	 **/
	public function payson_address_changed_callback() {
		if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'wc_payson_checkout_nonce' ) ) { // Input var okay.
			exit( 'Nonce can not be verified.' );
		}

		$address  = $_POST['address']; // Input var okay.
		$order_id = WC()->session->get( 'ongoing_payson_order' );
		$order    = wc_get_order( $order_id );

		$order->update_status( 'pending', __( 'Address Update callback from Payson.', 'woocommerce-gateway-paysoncheckout' ) );

		// Set customer session information.
		if ( WC()->customer->get_shipping_country() !== $address['CountryCode'] || WC()->customer->get_shipping_postcode() !== $address['PostalCode'] ) {
			WC()->customer->set_billing_country( $address['CountryCode'] );
			WC()->customer->set_shipping_country( $address['CountryCode'] );

			WC()->customer->set_billing_postcode( $address['PostalCode'] );
			WC()->customer->set_shipping_postcode( $address['PostalCode'] );

			WC()->cart->calculate_shipping();

			WC()->customer->set_calculated_shipping( true );
		}

		// Add customer billing address.
		update_post_meta( $order_id, '_billing_first_name', $address['FirstName'] );
		update_post_meta( $order_id, '_billing_last_name', $address['LastName'] );
		update_post_meta( $order_id, '_billing_address_1', $address['Street'] );
		update_post_meta( $order_id, '_billing_postcode', $address['PostalCode'] );
		update_post_meta( $order_id, '_billing_city', $address['City'] );
		update_post_meta( $order_id, '_billing_country', $address['CountryCode'] );
		update_post_meta( $order_id, '_billing_email', $address['Email'] );

		// Add customer shipping address.
		update_post_meta( $order_id, '_shipping_first_name', $address['FirstName'] );
		update_post_meta( $order_id, '_shipping_last_name', $address['LastName'] );
		update_post_meta( $order_id, '_shipping_address_1', $address['Street'] );
		update_post_meta( $order_id, '_shipping_postcode', $address['PostalCode'] );
		update_post_meta( $order_id, '_shipping_city', $address['City'] );
		update_post_meta( $order_id, '_shipping_country', $address['CountryCode'] );

		$data['order_id'] = $order_id;
		wp_send_json_success( $data );
		wp_die();
	}

	public function payson_add_customer_order_note() {
		WC()->session->set( 'payson_customer_order_note', $_POST['order_note'] );
		wp_send_json_success();
		wp_die();
	}

	public static function payson_change_payment_method() {
		
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();
		
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( 'false' === $_POST['paysoncheckout'] ) {
			// Set chosen payment method to first gateway that is not Klarna Checkout for WooCommerce.
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
		$data = array(
			'redirect' => $redirect,
		);
		
		wp_send_json_success( $data );
		wp_die();
	}

	public static function update_checkout() {
		WC()->cart->calculate_shipping();
		WC()->cart->calculate_fees();
		WC()->cart->calculate_totals();

		// If update checkout changed cart total to below 4 SEK or 0 EUR reload the checkout page
		if( ( WC()->cart->total < 4 && 'SEK' == get_woocommerce_currency() ) ||  WC()->cart->total == 0 && 'EUR' == get_woocommerce_currency() ) {
			$return = array();
			$return['redirect_url'] = wc_get_checkout_url();
			wp_send_json_error( $return );
			wp_die();
		}

		$wc_order = new WC_PaysonCheckout_WC_Order();
		$order_id = $wc_order->update_or_create_local_order();

		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$checkout   = $payson_api->update_checkout( $order_id );
		if( is_wp_error( $checkout ) ) {
			$return = array();
			$return['redirect_url'] = wc_get_checkout_url();
			wp_send_json_error( $return );
			wp_die();
		} else {
			wp_send_json_success();
			wp_die();
		}
	}

	public static function get_customer_data() {

		$payson_checkout_id = WC()->session->get( 'payson_checkout_id' );
		WC_Gateway_PaysonCheckout::log( 'Payment successful triggered for payson id: ' . $payson_checkout_id . '. Starting WooCommerce checkout form processing...' );
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$checkout   = $payson_api->get_notification_checkout( $payson_checkout_id );
		
		if( is_wp_error( $checkout ) ) {
			WC_Gateway_PaysonCheckout::log( 'Payment successful triggered for payson id: ' . $payson_checkout_id . ' but request to Payson failed.' );
			$return = array();
			$return['redirect_url'] = wc_get_checkout_url();
			wp_send_json_error( $return );
			wp_die();
		} else {
			$order_id = WC()->session->get( 'ongoing_payson_order' );
			self::prepare_local_order_before_form_processing( $order_id, $payson_checkout_id );

			$return = array();
			$return['customer_data'] = self::verify_customer_data( $checkout );
			$return['nonce'] = wp_create_nonce( 'woocommerce-process_checkout' );
			if ( null != WC()->session->get( 'payson_customer_order_note' ) ) {
				$return['order_note'] = WC()->session->get( 'payson_customer_order_note' );
			} else {
				$return['order_note'] = '';
			}
			$shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
			$return['shipping'] = $shipping_methods[0];
			
			wp_send_json_success( $return );
			wp_die();
		}
	}

	// Helper function to prepare the local order before processing the order form
	public function prepare_local_order_before_form_processing( $order_id, $payson_checkout_id ) {
		WC_Gateway_PaysonCheckout::log( 'order_id in prepare_local_order_before_form_processing: ' . $order_id);
		// Update cart hash
		update_post_meta( $order_id, '_cart_hash', md5( json_encode( wc_clean( WC()->cart->get_cart_for_session() ) ) . WC()->cart->total ) );
		// Set the paymentID as a meta value to be used later for reference
		update_post_meta( $order_id, '_payson_checkout_id', $payson_checkout_id );
		
		// Order ready for processing
		WC()->session->set( 'order_awaiting_payment', $order_id );
	}

	public function verify_customer_data( $checkout ) {
		$billing_first_name     = isset( $checkout->customer->firstName ) ? $checkout->customer->firstName : '.';
		$billing_last_name      = isset( $checkout->customer->lastName ) ? $checkout->customer->lastName : '.';
		$billing_address     = isset( $checkout->customer->street ) ? $checkout->customer->street : '.';
		$billing_postal_code      = isset( $checkout->customer->postalCode ) ? $checkout->customer->postalCode : '11111';
		$billing_city     = isset( $checkout->customer->city ) ? $checkout->customer->city : '.';
		$billing_country      = isset( $checkout->customer->countryCode ) ? $checkout->customer->countryCode : '.';
		$billing_phone      = isset( $checkout->customer->phone ) ? $checkout->customer->phone : '';
		$billing_email      = isset( $checkout->customer->email ) ? $checkout->customer->email : 'test@test.se';

		$customer_information = array(
			'billingFirstName'      =>  $billing_first_name,
			'billingLastName'       =>  $billing_last_name,
			'billingAddress'        =>  $billing_address,
			'billingPostalCode'     =>  $billing_postal_code,
			'billingCity'           =>  $billing_city,
			'billingCounry'           =>  $billing_country,
			'shippingFirstName'     =>  $billing_first_name,
			'shippingLastName'      =>  $billing_last_name,
			'shippingAddress'       =>  $billing_address,
			'shippingPostalCode'    =>  $billing_postal_code,
			'shippingCity'          =>  $billing_city,
			'shippingCounry'           =>  $billing_country,
			'phone'                 =>  $billing_phone,
			'email'                 =>  $billing_email,
		);
		return $customer_information;
	}

	/**
	 * Handles WooCommerce checkout error, after Payson order has already been created.
	 */
	public function on_checkout_error() {
		WC_Gateway_PaysonCheckout::log( 'Starting order finalization after order submission failure...' );
		$wc_order = new WC_PaysonCheckout_WC_Order();
		$order_id = $wc_order->update_or_create_local_order();
		$order = wc_get_order( $order_id );

		$order->add_order_note( sprintf(
			__( 'WooCommerce order finalized via submission backup.', 'krokedil-ecster-pay-for-woocommerce' ),
			$order_id
		) );
		update_post_meta( $order_id, '_payson_osf', true );
		
		$redirect_url = $order->get_checkout_order_received_url();
		//$redirect_url 	= wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
		$redirect_url = add_query_arg( array(
						    'payson-osf' => 'true',
						), $redirect_url );
		
		wp_send_json_success( array( 'redirect' => $redirect_url ) );
		wp_die();
	}

}

$wc_paysoncheckout_ajax = new WC_PaysonCheckout_Ajax();
