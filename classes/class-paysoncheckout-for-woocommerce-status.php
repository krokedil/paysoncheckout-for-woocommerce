<?php
/**
 * WooCommerce status page extension
 *
 * @class    PaysonCheckout_Status
 * @version  1.0.0
 * @package  PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Class for WooCommerce status page.
 */
class PaysonCheckout_For_WooCommerce_Status {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_system_status_report', array( $this, 'add_status_page_box' ) );
	}

	/**
	 * Adds status page box for Payson.
	 *
	 * @return void
	 */
	public function add_status_page_box() {
		include_once PAYSONCHECKOUT_PATH . '/includes/admin/views/status-report.php';
	}
}
$kco_status = new PaysonCheckout_For_WooCommerce_Status();
