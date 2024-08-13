<?php // phpcs:ignore
/**
 * Plugin Name:     PaysonCheckout for WooCommerce
 * Plugin URI:      http://krokedil.com/
 * Description:     Provides a PaysonCheckout payment gateway for WooCommerce.
 * Version:         3.8.0
 * Author:          Krokedil
 * Author URI:      http://krokedil.com/
 * Developer:       Krokedil
 * Developer URI:   http://krokedil.com/
 * Text Domain:     woocommerce-gateway-paysoncheckout
 * Domain Path:     /languages
 *
 * WC requires at least: 4.0
 * WC tested up to: 9.2.0
 *
 * Copyright:       © 2016-2024 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package PaysonCheckout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'PAYSONCHECKOUT_VERSION', '3.8.0' );
define( 'PAYSONCHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
define( 'PAYSONCHECKOUT_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'PAYSONCHECKOUT_LIVE_ENV', 'https://api.payson.se/2.0/' );
define( 'PAYSONCHECKOUT_TEST_ENV', 'https://test-api.payson.se/2.0/' );

if ( ! class_exists( 'PaysonCheckout_For_WooCommerce' ) ) {

	/**
	 * Main class for the plugin.
	 */
	class PaysonCheckout_For_WooCommerce {
		/**
		 * The reference the *Singleton* instance of this class.
		 *
		 * @var $instance
		 */
		protected static $instance;

		/**
		 * Main request class.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Request
		 */
		public $requests;

		/**
		 * Class for request create order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Create_Order
		 */
		public $create_order;
		/**
		 * Class for request update order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Update_Order
		 */
		public $update_order;
		/**
		 * Class for request manage order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Manage_Order
		 */
		public $manage_order;
		/**
		 * Class for request update order reference.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Update_Reference
		 */
		public $update_reference;
		/**
		 * Class for request get order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Get_Order
		 */
		public $get_order;
		/**
		 * Class for request refund order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Refund_Order
		 */
		public $refund_order;
		/**
		 * Class for request create recurring order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Create_Recurring_Order
		 */
		public $create_recurring_order;
		/**
		 * Class for request update recurring order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Update_Recurring_Order
		 */
		public $update_recurring_order;
		/**
		 * Class for request update recurring order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Update_Recurring_Reference
		 */
		public $update_recurring_reference;
		/**
		 * Class for request get order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Get_Recurring_Order
		 */
		public $get_recurring_order;
		/**
		 * Class for request create recurring payment.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Create_Recurring_Payment
		 */
		public $recurring_payment;
		/**
		 * Class for request get payment.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Get_Recurring_Payment
		 */
		public $get_recurring_payment;
		/**
		 * Class for request update recurring order.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Update_Recurring_Payment
		 */
		public $update_recurring_payment;

		/**
		 * Helper class for cart management.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Helper_Cart
		 */
		public $cart_items;
		/**
		 * Helper class for merchant object.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Helper_Merchant
		 */
		public $merchant_urls;
		/**
		 * Class to generate customer data for requests.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Helper_Customer
		 */
		public $customer;
		/**
		 * Class to generate gui data for requests.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Helper_GUI
		 */
		public $gui;
		/**
		 * Helper class for merchant object.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Helper_Agreement
		 */
		public $agreement;
		/**
		 * Helper class for order management.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Helper_Order
		 */
		public $order_items;

		/**
		 * Order management class.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Order_Management
		 */
		public $order_management;
		/**
		 * PaysonCheckout Subscription class.
		 *
		 * @var PaysonCheckout_For_WooCommerce_Subscriptions
		 */
		public $subscriptions;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			// Initiate the plugin.
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return self::$instance The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}
		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		public function __wakeup() {
			wc_doing_it_wrong( __FUNCTION__, __( 'Nope' ), '1.0' );
		}

		/**
		 * Initiates the plugin.
		 *
		 * @return void
		 */
		public function init() {

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			load_plugin_textdomain( 'woocommerce-gateway-paysoncheckout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );

			$this->include_files();

			// Load scripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );

			// Set variables for shorthand access to classes.
			// Requests.
			$this->requests                   = new PaysonCheckout_For_WooCommerce_Request();
			$this->create_order               = new PaysonCheckout_For_WooCommerce_Create_Order();
			$this->update_order               = new PaysonCheckout_For_WooCommerce_Update_Order();
			$this->manage_order               = new PaysonCheckout_For_WooCommerce_Manage_Order();
			$this->update_reference           = new PaysonCheckout_For_WooCommerce_Update_Reference();
			$this->get_order                  = new PaysonCheckout_For_WooCommerce_Get_Order();
			$this->refund_order               = new PaysonCheckout_For_WooCommerce_Refund_Order();
			$this->create_recurring_order     = new PaysonCheckout_For_WooCommerce_Create_Recurring_Order();
			$this->update_recurring_order     = new PaysonCheckout_For_WooCommerce_Update_Recurring_Order();
			$this->update_recurring_reference = new PaysonCheckout_For_WooCommerce_Update_Recurring_Reference();
			$this->get_recurring_order        = new PaysonCheckout_For_WooCommerce_Get_Recurring_Order();
			$this->recurring_payment          = new PaysonCheckout_For_WooCommerce_Create_Recurring_Payment();
			$this->get_recurring_payment      = new PaysonCheckout_For_WooCommerce_Get_Recurring_Payment();
			$this->update_recurring_payment   = new PaysonCheckout_For_WooCommerce_Update_Recurring_Payment();

			// Request Helpers.
			$this->cart_items    = new PaysonCheckout_For_WooCommerce_Helper_Cart();
			$this->merchant_urls = new PaysonCheckout_For_WooCommerce_Helper_Merchant();
			$this->customer      = new PaysonCheckout_For_WooCommerce_Helper_Customer();
			$this->gui           = new PaysonCheckout_For_WooCommerce_Helper_GUI();
			$this->agreement     = new PaysonCheckout_For_WooCommerce_Helper_Agreement();
			$this->order_items   = new PaysonCheckout_For_WooCommerce_Helper_Order();

			// Classes.
			$this->order_management = new PaysonCheckout_For_WooCommerce_Order_Management();
			$this->subscriptions    = new PaysonCheckout_For_WooCommerce_Subscriptions();
			do_action( 'payson_initiated' );
		}

		/**
		 * Includes the files for the plugin
		 *
		 * @return void
		 */
		public function include_files() {
			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			// Includes Classes.
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-gateway.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-templates.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-logger.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-ajax.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-callbacks.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-confirmation.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-order-management.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-status.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/class-paysoncheckout-for-woocommerce-subscriptions.php';

			// Request classes.
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/class-paysoncheckout-for-woocommerce-request.php';
			// Checkout.
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/checkout/class-paysoncheckout-for-woocommerce-create-order.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/checkout/class-paysoncheckout-for-woocommerce-update-order.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/checkout/class-paysoncheckout-for-woocommerce-update-reference.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/checkout/class-paysoncheckout-for-woocommerce-get-order.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/checkout/class-paysoncheckout-for-woocommerce-refund-order.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/checkout/class-paysoncheckout-for-woocommerce-manage-order.php';
			// Recurring.
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/recurring/class-paysoncheckout-for-woocommerce-create-recurring-order.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/recurring/class-paysoncheckout-for-woocommerce-update-recurring-order.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/recurring/class-paysoncheckout-for-woocommerce-update-recurring-reference.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/recurring/class-paysoncheckout-for-woocommerce-get-recurring-order.php';
			// Payments.
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/payment/class-paysoncheckout-for-woocommerce-create-recurring-payment.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/payment/class-paysoncheckout-for-woocommerce-get-recurring-payment.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/payment/class-paysoncheckout-for-woocommerce-update-recurring-payment.php';

			// Includes request helpers.
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/helpers/class-paysoncheckout-for-woocommerce-helper-cart.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/helpers/class-paysoncheckout-for-woocommerce-helper-headers.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/helpers/class-paysoncheckout-for-woocommerce-helper-merchant.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/helpers/class-paysoncheckout-for-woocommerce-helper-customer.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/helpers/class-paysoncheckout-for-woocommerce-helper-gui.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/helpers/class-paysoncheckout-for-woocommerce-helper-agreement.php';
			include_once PAYSONCHECKOUT_PATH . '/classes/requests/helpers/class-paysoncheckout-for-woocommerce-helper-order.php';

			// Include include files.
			include_once PAYSONCHECKOUT_PATH . '/includes/paysoncheckout-for-woocommerce-functions.php';

			add_action( 'before_woocommerce_init', array( $this, 'declare_wc_compatibility' ) );
		}

		public function declare_wc_compatibility() {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}

		/**
		 * Adds plugin action links
		 *
		 * @param array $links Plugin action link before filtering.
		 *
		 * @return array Filtered links.
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-paysoncheckout' ) . '</a>',
				'<a href="http://krokedil.se/">' . __( 'Support', 'woocommerce-gateway-paysoncheckout' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$section_slug = 'paysoncheckout';
			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Loads the needed scripts for PaysonCheckout.
		 */
		public function load_scripts() {
			if ( ! is_checkout() ) {
				return;
			}

			// We don't want to load scripts since, when you pick Payson as the gateway you want to change to, the script will trigger
			// "the pco_wc_change_payment_method" AJAX event which is not what we want since Woo will consider the change as "successful".
			// This results in process_payment not being triggered, and the customer is redirected back to the subscription view page.
			if ( PaysonCheckout_For_WooCommerce_Subscriptions::is_change_payment_method() ) {
				return;
			}

				// Check if we are on the confirmation page or not so we can load the correct JS file for the page.
			if ( ! isset( $_GET['pco_confirm'] ) ) {
				// Checkout script.
				wp_register_script(
					'pco_wc',
					PAYSONCHECKOUT_URL . '/assets/js/pco_checkout.js',
					array( 'jquery' ),
					PAYSONCHECKOUT_VERSION,
					true
				);

				$standard_woo_checkout_fields = array( 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_address_2', 'billing_postcode', 'billing_city', 'billing_phone', 'billing_email', 'billing_state', 'billing_country', 'billing_company', 'shipping_first_name', 'shipping_last_name', 'shipping_address_1', 'shipping_address_2', 'shipping_postcode', 'shipping_city', 'shipping_state', 'shipping_country', 'shipping_company', 'terms', 'terms-field', '_wp_http_referer', 'ship_to_different_address' );
				$order_id                     = null;
				$is_order_pay                 = false;
				if ( is_wc_endpoint_url( 'order-pay' ) ) {
					$order_id     = absint( get_query_var( 'order-pay', 0 ) );
					$is_order_pay = true;
				}
				$params = array(
					'ajax_url'                     => admin_url( 'admin-ajax.php' ),
					'select_another_method_text'   => __( 'Select another payment method', 'woocommerce-gateway-paysoncheckout' ),
					'standard_woo_checkout_fields' => $standard_woo_checkout_fields,
					'address_changed_url'          => WC_AJAX::get_endpoint( 'pco_wc_address_changed' ),
					'address_changed_nonce'        => wp_create_nonce( 'pco_wc_address_changed' ),
					'update_order_url'             => WC_AJAX::get_endpoint( 'pco_wc_update_checkout' ),
					'update_order_nonce'           => wp_create_nonce( 'pco_wc_update_checkout' ),
					'change_payment_method_url'    => WC_AJAX::get_endpoint( 'pco_wc_change_payment_method' ),
					'change_payment_method_nonce'  => wp_create_nonce( 'pco_wc_change_payment_method' ),
					'get_order_url'                => WC_AJAX::get_endpoint( 'pco_wc_get_order' ),
					'get_order_nonce'              => wp_create_nonce( 'pco_wc_get_order' ),
					'log_to_file_url'              => WC_AJAX::get_endpoint( 'pco_wc_log_js' ),
					'log_to_file_nonce'            => wp_create_nonce( 'pco_wc_log_js' ),
					'submit_order'                 => WC_AJAX::get_endpoint( 'checkout' ),
					'order_id'                     => $order_id,
					'is_order_pay'                 => $is_order_pay,
				);
				wp_localize_script(
					'pco_wc',
					'pco_wc_params',
					$params
				);
				wp_enqueue_script( 'pco_wc' );
			}

			wp_register_style(
				'pco',
				PAYSONCHECKOUT_URL . '/assets/css/pco_style.css',
				array(),
				PAYSONCHECKOUT_VERSION
			);
			wp_enqueue_style( 'pco' );
		}
	}
	PaysonCheckout_For_WooCommerce::get_instance();

	/**
	 * Main instance PaysonCheckout_For_WooCommerce.
	 *
	 * Returns the main instance of PaysonCheckout_For_WooCommerce.
	 *
	 * @return PaysonCheckout_For_WooCommerce
	 */
	function PCO_WC() { // phpcs:ignore
		return PaysonCheckout_For_WooCommerce::get_instance();
	}
}
