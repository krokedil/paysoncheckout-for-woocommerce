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
class WC_PaysonCheckout_Ajax_Handler {


	/** @var string */
	private $posted = '';

	/**
	 * WC_PaysonCheckout_Ajax_Handler constructor.
	 *
	 */
	public function __construct() {
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'update_order' ) );
	}


	public function update_order( $posted ) {
		$wc_order 		= new WC_PaysonCheckout_WC_Order();
		$order_id		= $wc_order->update_or_create_local_order();
		
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$checkout = $payson_api->get_checkout( $order_id );
		
	}
	
	
}
$wc_paysoncheckout_ajax_handler = new WC_PaysonCheckout_Ajax_Handler();