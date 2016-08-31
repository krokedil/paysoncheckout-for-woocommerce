<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Base class for the plugin
 *
 * Used to register payson-incomplete order status.
 *
 * @class WC_PaysonCheckout
 * @version 1.0.0
 * @package WC_PaysonCheckout/Classes
 * @category Class
 * @author Krokedil
 */
class WC_PaysonCheckout {
	
	/**
	 * WC_PaysonCheckout constructor.
	 */
	public function __construct() {
		$paysoncheckout_settings = get_option( 'woocommerce_paysoncheckout_settings' );
		$this->debug	= $paysoncheckout_settings['debug'];

		// Register new order status
		add_action( 'init', array( $this, 'register_payson_incomplete_order_status' ) );	
		add_filter( 'wc_order_statuses', array( $this, 'add_payson_incomplete_to_order_statuses' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this,'payson_incomplete_payment_complete' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'payson_incomplete_payment_complete' ) );
	}
	
	
	/**
	 * Register Payson Incomplete order status
	 *
	 * @since  0.1
	 **/
	public function register_payson_incomplete_order_status() {
		if ( 'yes' == $this->debug ) {
			$show_in_admin_status_list = true;
		} else {
			$show_in_admin_status_list = false;
		}
		
		register_post_status( 'wc-payson-incomplete', array(
			'label'                     => 'Payson incomplete',
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => $show_in_admin_status_list,
			'label_count'               => _n_noop( 'Payson incomplete <span class="count">(%s)</span>', 'Payson incomplete <span class="count">(%s)</span>' ),
		) );
		
	}
	
	/**
	 * Add KCO Incomplete to list of order status
	 *
	 * @since  0.1
	 **/
	public function add_payson_incomplete_to_order_statuses( $order_statuses ) {
		// Add this status only if not in account page (so it doesn't show in My Account list of orders)
		if ( ! is_account_page() ) {
			$order_statuses['wc-payson-incomplete'] = 'Incomplete PaysonCheckout';
		}
		return $order_statuses;
	}

	/**
	 * Allows $order->payment_complete to work for Payson incomplete orders
	 *
	 * @since  0.1
	 **/
	public function payson_incomplete_payment_complete( $order_statuses ) {
		$order_statuses[] = 'payson-incomplete';
		return $order_statuses;
	}

}
$wc_paysoncheckout = new WC_PaysonCheckout;