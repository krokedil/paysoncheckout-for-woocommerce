<?php
/**
 * Subscription class.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PaysonCheckout Subscription class.
 */
class PaysonCheckout_For_WooCommerce_Subscriptions {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		// add_action( 'woocommerce_thankyou_paysoncheckout', array( $this, 'set_recurring_token_for_order' ) );
		// add_action( 'woocommerce_scheduled_subscription_payment_paysoncheckout', array( $this, 'trigger_scheduled_payment' ), 10, 2 );
		// add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_recurring_token' ) );
		// add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_pco_recurring_token_update' ), 45, 2 );
	}
}
