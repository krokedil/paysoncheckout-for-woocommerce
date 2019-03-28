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
		$this->supports = array( 'products', 'refunds' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return boolean
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! is_admin() ) {
				// Currency check.
				if ( ! in_array( get_woocommerce_currency(), array( 'EUR', 'SEK' ), true ) ) {
					return false;
				}
				// Required fields check.
				if ( ! $this->merchant_id || ! $this->api_key ) {
					return false;
				}
				// Don't display the payment method if we have an order with to low amount.
				if ( WC()->cart->total < 4 && 'SEK' === get_woocommerce_currency() ) {
					return false;
				}
				if ( WC()->cart->total === 0 && 'EUR' === get_woocommerce_currency() ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_src   = 'https://www.payson.se/sites/all/files/images/external/payson.png';
		$icon_width = '85';
		$icon_html  = '<img src="' . $icon_src . '" alt="PaysonCheckout 2.0" style="max-width:' . $icon_width . 'px"/>';
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

		// Save payment type and run $order->payment_complete() if all looks good.
		if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
			$process_payment = $this->process_payson_payment_in_order( $order_id );
			if ( true !== $process_payment ) {
				return;
			}
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Process refund request.
	 *
	 * @param string $order_id The WooCommerce order ID.
	 * @param float  $amount The amount to be refunded.
	 * @param string $reasson The reasson given for the refund.
	 */
	public function process_refund( $order_id, $amount = null, $reasson = '' ) {
		$order = wc_get_order( $order_id );
		// Refund full amount.
		if ( $amount === $order->get_total() ) {
			return PCO_WC()->order_management->refund_full_payment( $order_id );
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
	 * Processes the Payson Payment and sets post metas.
	 *
	 * @param string $order_id The WooCommerce order id.
	 * @return bool|string
	 */
	public function process_payson_payment_in_order( $order_id ) {
		$payment_id   = WC()->session->get( 'payson_payment_id' );
		$payson_order = PCO_WC()->get_order->request( $payment_id );
		$order        = wc_get_order( $order_id );
		if ( is_array( $payson_order ) && 'readyToShip' === $payson_order['status'] ) {
			// Update the payson order with woocommerce order id.
			$payson_order = PCO_WC()->update_reference->request( $order_id, $payson_order );
			// Check for error.
			if ( is_wp_error( $payson_order ) ) {
				// If error save error message.
				$code          = $payson_order->get_error_code();
				$message       = $payson_order->get_error_message();
				$text          = __( 'Payson API Error on set order reference: ', 'payson-checkout-for-woocommerce' ) . '%s %s';
				$formated_text = sprintf( $text, $code, $message );
				$order->add_order_note( $formated_text );
				$order->set_status( 'on-hold' );

				return false;
			}
			// Set post meta and complete order.
			update_post_meta( $order_id, '_payson_checkout_id', $payment_id );
			$order->payment_complete( $payson_order['purchaseId'] );
			// Unset sessions.
			pco_wc_unset_sessions();
			return true;
		} else {
			// If failed then extract error message and return. Its not used right now, but might be used later.
			$error_message = __( 'Error processing order. Please try again', 'woocommerce-gateway-paysoncheckout' );
			if ( is_wp_error( $payson_order ) ) {
				$error_message = $payson_order->get_error_message();
			}
			return $error_message;
		}
	}
}

/**
 * Add PaysonCheckout 2.0 payment gateway
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
