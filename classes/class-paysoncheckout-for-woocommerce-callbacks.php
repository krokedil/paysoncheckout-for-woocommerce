<?php
/**
 * Handles callbacks for the plugin.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback class.
 */
class PaysonCheckout_For_WooCommerce_Callbacks {
	/**
	 * Order is valid flag.
	 *
	 * @var boolean
	 */
	public $order_is_valid = true;

	/**
	 * Validation messages.
	 *
	 * @var array
	 */
	public $validation_messages = array();

	/**
	 * The Payson order
	 *
	 * @var array The Payson order object.
	 */
	public $payson_order = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_pco_wc_validation', array( $this, 'validate_cb' ) );
		add_action( 'woocommerce_api_pco_wc_notification', array( $this, 'notification_cb' ) );
	}

	/**
	 * Handles validation callbacks.
	 *
	 * @return void
	 */
	public function validate_cb() {
		// Get the payson order.
		$payment_id         = $_GET['checkout'];
		$this->payson_order = PCO_WC()->get_order->request( $payment_id );

		// Check if we have a session id.
		$this->check_session_id();

		// Check if the order has a payment id set in the confirmation URL.
		// $this->check_payment_id_in_order();
		// Check coupons.
		$this->check_cart_coupons();

		// Check for error notices from WooCommerce.
		$this->check_woo_notices();

		// Check order amount match.
		$this->check_order_amount();

		// Check that all items are still in stock.
		$this->check_all_in_stock();

		// Check if order is still valid.
		if ( $this->order_is_valid ) {
			$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $_GET['checkout'], 'CALLBACK - GET', 'Payson Validation callback', $_GET, 'OK', 200 );
			PaysonCheckout_For_WooCommerce_Logger::log( $log );
			header( 'HTTP/1.0 200 OK' );
		} else {
			$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $_GET['checkout'], 'CALLBACK - GET', 'Payson Validation callback', $_GET, $this->validation_messages, 400 );
			PaysonCheckout_For_WooCommerce_Logger::log( $log );
			$redirect = add_query_arg(
				'pco_validation_error',
				base64_encode( json_encode( $this->validation_messages ) ),
				wc_get_checkout_url()
			);
			header( 'HTTP/1.0 303 See Other' );
			header( 'Location: ' . $redirect );
		}
	}

	/**
	 * Handles notification callbacks.
	 *
	 * @return void
	 */
	public function notification_cb() {
		header( 'HTTP/1.0 200 OK' );
	}

	/**
	 * Checks if we have a session id set.
	 *
	 * @return void
	 */
	public function check_session_id() {
		if ( ! isset( $_GET['pco_session_id'] ) ) {
			$this->order_is_valid                            = false;
			$this->validation_messages['missing_session_id'] = __( 'No session ID detected.', 'woocommerce-gateway-payson' );
		}
	}

	/**
	 * Checks if we have updated the confirmation URL with a payment id.
	 *
	 * @return void
	 */
	public function check_payment_id_in_order() {
		// Check the merchant URL.
		if ( is_wp_error( $this->payson_order ) || ! strpos( $this->payson_order['merchant']['confirmationUri'], 'pco_payment_id' ) ) {
			$this->order_is_valid                            = false;
			$this->validation_messages['missing_payment_id'] = __( 'No payment ID set in confirmation URL.', 'woocommerce-gateway-payson' );
		}
	}

	/**
	 * Check cart coupons for errors.
	 *
	 * @return void
	 */
	public function check_cart_coupons() {
		foreach ( WC()->cart->get_applied_coupons() as $code ) {
			$coupon = new WC_Coupon( $code );
			if ( ! $coupon->is_valid() ) {
				$this->order_is_valid                      = false;
				$this->validation_messages['coupon_error'] = WC_Coupon::E_WC_COUPON_INVALID_REMOVED;
			}
		}
	}

	/**
	 * Checks for any WooCommerce error notices from the session.
	 *
	 * @return void
	 */
	public function check_woo_notices() {
		$errors = wc_get_notices( 'error' );
		if ( ! empty( $errors ) ) {
			$this->order_is_valid = false;
			foreach ( $errors as $error ) {
				$this->validation_messages['wc_notice'] = $error;
			}
		}
	}

	/**
	 * Checks if Payson order total equals the current cart total.
	 *
	 * @return void
	 */
	public function check_order_amount() {
		$payson_total = floatval( $this->payson_order['order']['totalPriceIncludingTax'] );
		$woo_total    = floatval( WC()->cart->get_total( 'payson_validation' ) );
		if ( $woo_total !== $payson_total ) {
			$this->order_is_valid                      = false;
			$this->validation_messages['amount_error'] = __( 'Missmatch between the Payson and WooCommerce order total.', 'woocommerce-gateway-payson' );
		}
	}

	/**
	 * Checks if all cart items are still in stock.
	 *
	 * @return void
	 */
	public function check_all_in_stock() {
		$stock_check = WC()->cart->check_cart_item_stock();
		if ( true !== $stock_check ) {
			$this->order_is_valid                      = false;
			$this->validation_messages['amount_error'] = __( 'Not all items are in stock.', 'woocommerce-gateway-payson' );
		}
	}
}
new PaysonCheckout_For_WooCommerce_Callbacks();
