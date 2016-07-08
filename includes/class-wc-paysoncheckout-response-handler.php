<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Handle responses from Payson
 *
 * @class    WC_PaysonCheckout_Response_Handler
 * @version  1.0.0
 * @package  WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Response_Handler {
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		
		// Notification listener
		add_action( 'woocommerce_api_wc_gateway_paysoncheckout', array( $this, 'notification_listener' ) );
	}
	
	
	/**
	 * Notification listener.
	 */
	public function notification_listener() {
		WC_Gateway_PaysonCheckout::log( 'Notification callback for order: ' . $_GET['checkout'] );
		
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api 	= new WC_PaysonCheckout_Setup_Payson_API();
		$checkout 		= $payson_api->get_notification_checkout( $_GET['checkout'] );
		WC_Gateway_PaysonCheckout::log( 'Posted notification info: ' . var_export( $checkout, true ) );
		$order			= wc_get_order( $checkout->merchant->reference);
		WC_Gateway_PaysonCheckout::log( 'Posted reference: ' . var_export( $checkout->merchant->reference, true ) );
		WC_Gateway_PaysonCheckout::log( 'Posted status: ' . var_export( $checkout->status, true ) );
		if ( method_exists( $this, 'payment_status_' . $checkout->status ) ) {
			call_user_func( array( $this, 'payment_status_' . $checkout->status ), $order, $checkout );
		}	
	}
	
	
	/**
	 * Handle a completed payment.
	 * @param WC_Order $order
	 * @param object PaysonCheckout order $checkout
	 */
	protected function payment_status_readyToShip( $order, $checkout ) {
		WC_Gateway_PaysonCheckout::log( 'Payment status readyToShip callback.' );
		
		if ( $order->has_status( 'completed' ) ) {
			WC_Gateway_PaysonCheckout::log( 'Aborting, Order #' . $order->id . ' is already complete.' );
			exit;
		}
		
		if ( 'readyToShip' === $checkout->status ) {
			
			// Add order addresses
			$this->add_order_addresses( $order, $checkout );
			
			// Add order customer info
			//$this->add_order_customer_info( $order, $checkout );
			
			// Change the order status to Processing/Completed in WooCommerce
			$order->payment_complete( $checkout->id );
		}
	}
	
	
	/**
	 * Adds order addresses to local order.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 * @param  object $checkout PaysonCheckout order.
	 */
	public function add_order_addresses( $order, $checkout ) {
		
		$order_id = $order->id;
		
		// Add customer billing address - retrieved from callback from Payson
		update_post_meta( $order_id, '_billing_first_name', $checkout->customer->firstName );
		update_post_meta( $order_id, '_billing_last_name', $checkout->customer->lastName );
		update_post_meta( $order_id, '_billing_address_1', $checkout->customer->street );
		update_post_meta( $order_id, '_billing_postcode', $checkout->customer->postalCode );
		update_post_meta( $order_id, '_billing_city', $checkout->customer->city );
		update_post_meta( $order_id, '_billing_country', $checkout->customer->countryCode );
		update_post_meta( $order_id, '_billing_email', $checkout->customer->email );
		update_post_meta( $order_id, '_billing_phone', $checkout->customer->phone );
		
		// Add customer shipping address - retrieved from callback from Payson
		update_post_meta( $order_id, '_shipping_first_name', $checkout->customer->firstName );
		update_post_meta( $order_id, '_shipping_last_name', $checkout->customer->lastName );
		update_post_meta( $order_id, '_shipping_address_1', $checkout->customer->street );
		update_post_meta( $order_id, '_shipping_postcode', $checkout->customer->postalCode );
		update_post_meta( $order_id, '_shipping_city', $checkout->customer->city );
		update_post_meta( $order_id, '_shipping_country', $checkout->customer->countryCode );
		// Store PaysonCheckout locale
		update_post_meta( $order_id, '_klarna_locale', $checkout->gui->locale );
	}
	
	
	/**
	 * Adds customer info to local order.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param  object $order Local WC order.
	 * @param  object $checkout PaysonCheckout order.
	 */
	public function add_order_customer_info( $order, $checkout ) {
		$order_id = $order->id;
		// Store user id in order so the user can keep track of track it in My account
		if ( email_exists( $checkout->customer->email ) ) {
			
			$user = get_user_by( 'email', $checkout->customer->email );
			
			$this->customer_id = $user->ID;
			update_post_meta( $order->id, '_customer_user', $this->customer_id );
		} else {
			// Create new user
			$checkout_settings = array();
			if ( 'yes' == $checkout_settings['create_customer_account'] ) {
				$password     = '';
				$new_customer = $this->create_new_customer( $checkout->customer->email, $checkout->customer->email, $password );
				if ( 0 == $new_customer ) { // Creation failed
					$order->add_order_note( sprintf( __( 'Customer creation failed. Check error log for more details.', 'klarna' ) ) );
					$this->customer_id = 0;
				} else { // Creation succeeded
					$order->add_order_note( sprintf( __( 'New customer created (user ID %s).', 'klarna' ), $new_customer ) );
					// Add customer billing address - retrieved from callback from Klarna
					update_user_meta( $new_customer, 'billing_first_name', $checkout->customer->firstName );
					update_user_meta( $new_customer, 'billing_last_name', $checkout->customer->lastName );
					update_user_meta( $new_customer, 'billing_address_1', $checkout->customer->street );
					update_user_meta( $new_customer, 'billing_postcode', $checkout->customer->postalCode );
					update_user_meta( $new_customer, 'billing_city', $checkout->customer->city );
					update_user_meta( $new_customer, 'billing_country', $checkout->customer->countryCode );
					update_user_meta( $new_customer, 'billing_email', $checkout->customer->email );
					update_user_meta( $new_customer, 'billing_phone', $checkout->customer->phone );
					
					// Shipping
					update_user_meta( $new_customer, 'shipping_first_name', $checkout->customer->firstName );
					update_user_meta( $new_customer, 'shipping_last_name', $checkout->customer->lastName );
					update_user_meta( $new_customer, 'shipping_address_1', $checkout->customer->street );
					update_user_meta( $new_customer, 'shipping_postcode', $checkout->customer->postalCode );
					update_user_meta( $new_customer, 'shipping_city', $checkout->customer->city );
					update_user_meta( $new_customer, 'shipping_country', $checkout->customer->countryCode );
					$this->customer_id = $new_customer;
				}
				update_post_meta( $order->id, '_customer_user', $this->customer_id );
			}
		}
	}
}
$wc_paysoncheckout_response_handler = new WC_PaysonCheckout_Response_Handler();