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

		add_action( 'wp_ajax_wc_paysoncheckout_create_order', array( $this, 'create_local_order' ) );
		add_action( 'wp_ajax_nopriv_wc_paysoncheckout_create_order', array( $this, 'create_local_order' ) );
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

}

$wc_paysoncheckout_ajax = new WC_PaysonCheckout_Ajax();
