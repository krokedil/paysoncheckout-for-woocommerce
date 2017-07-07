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
}

$wc_paysoncheckout_ajax = new WC_PaysonCheckout_Ajax();
