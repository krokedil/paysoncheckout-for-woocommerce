<?php
/**
 * Gateway class file.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gateway class.
 */
class PaysonCheckout_For_WooCommerce_Gateway extends WC_Payment_Gateway {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id                 = 'paysoncheckout';
		$this->method_title       = __( 'Payson', 'woocommerce-gateway-paysoncheckout' );
		$this->icon               = '';
		$this->method_description = __( 'Allows payments through ' . $this->method_title . '.', 'woocommerce-gateway-paysoncheckout' ); // phpcs:ignore

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->enabled          = $this->get_option( 'enabled' );
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->merchant_id      = $this->get_option( 'merchant_id' );
		$this->api_key          = $this->get_option( 'api_key' );
		$this->color_scheme     = $this->get_option( 'color_scheme' );
		$this->request_phone    = $this->get_option( 'request_phone' );
		$this->debug            = $this->get_option( 'debug' );
		$this->order_management = $this->get_option( 'order_management' );

		// Supports.
		$this->supports = array(
			'products',
			'refunds',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
		);

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return boolean
	 */
	public function is_available() {
		$is_pay_for_order = false;
		if ( is_wc_endpoint_url( 'order-pay' ) ) {
			$is_pay_for_order = true;
			global $wp;
			$order_id = $wp->query_vars['order-pay'];
			$order    = wc_get_order( $order_id );
		}

		$is_subscription = false;
		if ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$is_subscription = true;
		}

		// Check if is enabled.
		if ( 'yes' !== $this->enabled ) {
			return false;
		}

		// Currency check.
		if ( ! in_array( get_woocommerce_currency(), array( 'EUR', 'SEK' ), true ) ) {
			return false;
		}

		// Required fields check.
		if ( ! $this->merchant_id || ! $this->api_key ) {
			return false;
		}

		// Don't display the payment method if we have an order with to low amount.
		if ( ! $is_subscription ) { // Not needed for subscriptions.
			if ( $is_pay_for_order ) { // Check if is pay for order page.
				if ( $order->get_total() < 4 && 'SEK' === get_woocommerce_currency() ) {
					return false;
				}
				if ( $order->get_total() === 0 && 'EUR' === get_woocommerce_currency() ) {
					return false;
				}
			} else {
				if ( WC()->cart->total < 4 && 'SEK' === get_woocommerce_currency() ) {
					return false;
				}
				if ( WC()->cart->total === 0 && 'EUR' === get_woocommerce_currency() ) {
					return false;
				}
			}
		}

		// All good, return true.
		return true;
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_src   = 'https://www.payson.se/sites/all/files/images/external/payson.png';
		$icon_width = '85';
		$icon_html  = '<img src="' . $icon_src . '" alt="PaysonCheckout" style="max-width:' . $icon_width . 'px"/>';
		return apply_filters( 'wc_payson_icon_html', $icon_html );
	}

	/**
	 * Processes the WooCommerce Payment
	 *
	 * @param string $order_id The WooCommerce order ID.
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		// Confirm the order.
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
			$result = $this->update_recurring_reference( $order_id );
		} else {
			$result = $this->update_order_reference( $order_id );
		}

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return array(
			'result' => 'success',
		);
	}

	/**
	 * Update the recurring reference.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array|WP_Error
	 */
	public function update_recurring_reference( $order_id ) {
		$payment_id   = WC()->session->get( 'payson_payment_id' );
		$payson_order = pco_wc_get_order( $payment_id, true );
		$payson_order = PCO_WC()->update_recurring_reference->request( $order_id, $payson_order );
		update_post_meta( $order_id, '_payson_checkout_id', $payment_id );
		return $payson_order;
	}

	/**
	 * Update the order reference.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return array|WP_Error
	 */
	public function update_order_reference( $order_id ) {
		$payment_id   = WC()->session->get( 'payson_payment_id' );
		$payson_order = pco_wc_get_order( $payment_id );
		$payson_order = PCO_WC()->update_reference->request( $order_id, $payson_order );
		update_post_meta( $order_id, '_payson_checkout_id', $payment_id );
		return $payson_order;
	}

	/**
	 * Process refund request.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param float  $amount The amount to be refunded.
	 * @param string $reason The reason given for the refund.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$order = wc_get_order( $order_id );
		// Refund full amount.
		if ( $amount === $order->get_total() ) {
			return PCO_WC()->order_management->refund_full_payment( $order_id );
		} else {
			// TODO - Refund partial.
			return PCO_WC()->order_management->refund_partial_payment( $order_id );
		}

	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = include PAYSONCHECKOUT_PATH . '/includes/paysoncheckout-for-woocommerce-form-fields.php';
	}

	/**
	 * Shows the snippet on the thankyou page.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return void
	 */
	public function show_thank_you_snippet( $order_id ) {
		// Check if order is subscription.
		$subscription = false;
		$order        = wc_get_order( $order_id );
		if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
			$subscription = true;
		}
		// Show snippet.
		pco_wc_thankyou_page_snippet( $order_id, $subscription );

		// Clear sessionStorage.
		echo '<script>sessionStorage.removeItem("PCORequiredFields")</script>';
		echo '<script>sessionStorage.removeItem("PCOFieldData")</script>';

		// Unset sessions.
		pco_wc_unset_sessions();
	}
}

/**
 * Add PaysonCheckout payment gateway
 *
 * @wp_hook woocommerce_payment_gateways
 * @param  array $methods All registered payment methods.
 * @return array $methods All registered payment methods.
 */
function add_paysoncheckout_method( $methods ) {
	$methods[] = 'PaysonCheckout_For_WooCommerce_Gateway';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'add_paysoncheckout_method' );
