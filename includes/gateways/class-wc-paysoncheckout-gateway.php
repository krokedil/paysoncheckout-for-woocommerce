<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
add_action( 'plugins_loaded', 'init_wc_gateway_paysoncheckout_class' );
add_filter( 'woocommerce_payment_gateways', 'add_paysoncheckout_method' );
/**
 * Initialize PaysonCheckout payment gateway
 *
 * @wp_hook plugins_loaded
 */
function init_wc_gateway_paysoncheckout_class() {
	/**
	 * PaysonCheckout 2.0 Payment Gateway.
	 *
	 * Provides PaysonCheckout 2.0 Payment Gateway for WooCommerce.
	 *
	 * @class       WC_Gateway_PaysonCheckout
	 * @extends     WC_Payment_Gateway
	 * @version     0.1
	 * @author      Krokedil
	 */
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	class WC_Gateway_PaysonCheckout extends WC_Payment_Gateway {

		/** @var WC_Logger Logger instance */
		public static $log = false;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			$this->id                 = 'paysoncheckout';
			$this->method_title       = __( 'Payson', 'woocommerce-gateway-paysoncheckout' );
			$this->icon               = '';
			$this->has_fields         = true;
			$this->method_description = __( 'Allows payments through ' . $this->method_title . '.', 'woocommerce-gateway-paysoncheckout' );

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
			$this->supports = array( 'products' );

			// Actions.
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Scripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			// Thank you page.
			add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'payson_thankyou_order_received_text' ), 10, 2 );
			add_action( 'woocommerce_thankyou_paysoncheckout', array( $this, 'payson_thankyou' ) );
		}

		/**
		 * Logging method.
		 *
		 * @param string $message
		 */
		public static function log( $message ) {
			$paysoncheckout_settings = get_option( 'woocommerce_paysoncheckout_settings' );
			if ( $paysoncheckout_settings['debug'] === 'yes' ) {
				if ( empty( self::$log ) ) {
					self::$log = new WC_Logger();
				}
				self::$log->add( 'paysoncheckout', $message );
			}
		}

		/**
		 * Check if this gateway is enabled and available in the user's country
		 */
		function is_available() {
			if ( 'yes' === $this->enabled ) {
				if ( ! is_admin() ) {
					// Currency check.
					if ( ! in_array( get_woocommerce_currency(), array( 'EUR', 'SEK' ) ) ) {
						return false;
					}

					// Required fields check.
					if ( ! $this->merchant_id || ! $this->api_key ) {
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
		 * Remove thank you page order received text if PaysonCheckout is the selected payment method.
		 *
		 * @param $text
		 * @param $order
		 *
		 * @return string
		 */
		public function payson_thankyou_order_received_text( $text, $order ) {
			if ( 'paysoncheckout' == WC()->session->get( 'chosen_payment_method' ) ) {
				return '';
			}

			return $text;
		}

		/**
		 * Add PaysonCheckout iframe to thankyou page.
		 */
		public function payson_thankyou( $order_id ) {
			if ( $_GET['paysonorder'] ) {

				remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
				include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
				$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
				$checkout   = $payson_api->get_notification_checkout( $_GET['paysonorder'] );

				if ( 'canceled' === $checkout->status ) {
					WC()->session->__unset( 'payson_checkout_id' );
					WC()->session->__unset( 'ongoing_payson_order' );

					wc_add_notice( __( 'Order was cancelled.', 'woocommerce-gateway-paysoncheckout' ), 'error' );
					wp_safe_redirect( wc_get_cart_url() );
				} else {
					WC_Gateway_PaysonCheckout::log( 'Posted checkout info in thank you page: ' . var_export( $checkout, true ) );

					if ( 'readyToShip' === $checkout->status ) {
						$order = wc_get_order( $order_id );
						$payson_response_handler = new WC_PaysonCheckout_Response_Handler();
						$payson_response_handler->ready_to_ship_cb( $order, $checkout );
					}

					echo '<div class="paysoncheckout-container" style="width:100%; margin-left:auto; margin-right:auto;">';
					echo $checkout->snippet;
					echo '</div>';
					WC()->session->__unset( 'payson_checkout_id' );
					WC()->session->__unset( 'ongoing_payson_order' );
				}
			}
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'          => array(
					'title'   => __( 'Enable/Disable', 'woocommerce-gateway-paysoncheckout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable ' . $this->method_title, 'woocommerce-gateway-paysoncheckout' ),
					'default' => 'yes'
				),
				'title'            => array(
					'title'       => __( 'Title', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => __( $this->method_title, 'woocommerce-gateway-paysoncheckout' ),
					'desc_tip'    => true,
				),
				'description'      => array(
					'title'       => __( 'Description', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'textarea',
					'default'     => __( 'Pay with Payson via invoice, card, direct bank payments, part payment and sms.', 'woocommerce-gateway-paysoncheckout' ),
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paysoncheckout' ),
				),
				'merchant_id'      => array(
					'title'       => __( 'Agent ID', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'description' => __( '', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => '',
				),
				'api_key'          => array(
					'title'       => __( 'API Key', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'description' => __( '', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => '',
				),
				'testmode'         => array(
					'title'   => __( 'Testmode', 'woocommerce-gateway-paysoncheckout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable PaysonCheckout testmode', 'woocommerce-gateway-paysoncheckout' ),
					'default' => 'no',
				),
				'order_management' => array(
					'title'   => __( 'Enable Order Management', 'woocommerce-gateway-paysoncheckout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Payson order capture on WooCommerce order completion and Payson order cancellation on WooCommerce order cancellation', 'woocommerce-gateway-paysoncheckout' ),
					'default' => 'yes'
				),
				'color_scheme'     => array(
					'title'       => __( 'Color Scheme', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'select',
					'options'     => array(
						'Gray'           => __( 'Gray', 'woocommerce-gateway-paysoncheckout' ),
						'Blue'           => __( 'Blue', 'woocommerce-gateway-paysoncheckout' ),
						'White'          => __( 'White', 'woocommerce-gateway-paysoncheckout' ),
						'GrayTextLogos'  => __( 'GrayTextLogos', 'woocommerce-gateway-paysoncheckout' ),
						'BlueTextLogos'  => __( 'BlueTextLogos', 'woocommerce-gateway-paysoncheckout' ),
						'WhiteTextLogos' => __( 'WhiteTextLogos', 'woocommerce-gateway-paysoncheckout' )
					),
					'description' => __( 'Different color schemes for how the embedded PaysonCheckout iframe should be displayed.', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => 'gray',
					'desc_tip'    => true
				),
				'request_phone'    => array(
					'title'   => __( 'Request phone', 'woocommerce-gateway-paysoncheckout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Check this box to require the customer to fill in his phone number.', 'woocommerce-gateway-paysoncheckout' ),
					'default' => 'no'
				),
				'debug'            => array(
					'title'       => __( 'Debug Log', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log ' . $this->method_title . ' events in <code>%s</code>', 'woocommerce-gateway-paysoncheckout' ), wc_get_log_file_path( 'paysoncheckout' ) )
				),
			);
		}

		function enqueue_scripts() {
			if ( is_checkout() ) {
				$theme            = wp_get_theme();
				$theme_name       = $theme->name;
				wp_register_script( 'wc_paysoncheckout', PAYSONCHECKOUT_URL . '/assets/js/paysoncheckout.js', array( 'jquery' ), PAYSONCHECKOUT_VERSION, true );
				wp_localize_script( 'wc_paysoncheckout', 'wc_paysoncheckout', array(
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'select_another_method_text' => __( 'Select another payment method', 'woocommerce-gateway-paysoncheckout' ),
					'debug'                      => $this->debug,
					'wc_payson_checkout_nonce'   => wp_create_nonce( 'wc_payson_checkout_nonce' )
				) );
				wp_enqueue_script( 'wc_paysoncheckout' );
				wp_register_style( 'wc_paysoncheckout', PAYSONCHECKOUT_URL . '/assets/css/paysoncheckout.css', array(), PAYSONCHECKOUT_VERSION );
				wp_enqueue_style( 'wc_paysoncheckout' );
			}
		}

	}
}

/**
 * Add PaysonCheckout 2.0 payment gateway
 *
 * @wp_hook woocommerce_payment_gateways
 * @param  array $methods All registered payment methods
 * @return array $methods All registered payment methods
 */
function add_paysoncheckout_method( $methods ) {
	$methods[] = 'WC_Gateway_PaysonCheckout';

	return $methods;
}
