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
		add_action( 'init', array( $this, 'set_current_user' ) );
		add_action( 'woocommerce_api_pco_wc_validation', array( $this, 'validate_cb' ) );
		add_action( 'woocommerce_api_pco_wc_notification', array( $this, 'notification_cb' ) );
		add_action( 'pco_check_for_order', array( $this, 'pco_check_for_order_callback' ), 10, 2 );
		$this->needs_login = 'no' === get_option( 'woocommerce_enable_guest_checkout' ) ? true : false; // Needs to be logged in order to checkout.
	}

	/**
	 * Handles validation callbacks.
	 *
	 * @return void
	 */
	public function validate_cb() {
		// Set the payment/subscription id
		if ( isset( $_GET['checkout'] ) ) {
			$payment_id   = $_GET['checkout'];
			$subscription = false;
		} elseif ( isset( $_GET['subscription'] ) ) {
			$payment_id   = $_GET['subscription'];
			$subscription = true;
		}

		$this->payson_order = pco_wc_get_order( $payment_id );
		// Check if we have a session id.
		$this->check_session_id();

		// Check if the order has a payment id set in the confirmation URL.
		// $this->check_payment_id_in_order();
		// Check coupons.
		$this->check_cart_coupons();

		// Check for error notices from WooCommerce.
		$this->check_woo_notices();

		// Check order amount match. Only normal orders, since Payson does not save a subscription amount.
		if ( ! $subscription ) {
			$this->check_order_amount();
		}

		// Check that all items are still in stock.
		$this->check_all_in_stock();

		// Subscription specific controlls.
		if ( $subscription || $this->needs_login ) {
			$this->check_if_user_exists_and_logged_in();
		}

		// Check if order is still valid.
		if ( $this->order_is_valid ) {
			$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'CALLBACK - GET', 'Payson Validation callback', $_GET, 'OK', 200 );
			PaysonCheckout_For_WooCommerce_Logger::log( $log );
			header( 'HTTP/1.0 200 OK' );
		} else {
			$log = PaysonCheckout_For_WooCommerce_Logger::format_log( $payment_id, 'CALLBACK - GET', 'Payson Validation callback', $_GET, $this->validation_messages, 400 );
			PaysonCheckout_For_WooCommerce_Logger::log( $log );
			if ( isset( $this->validation_messages['amount_error_totals'] ) ) {
				unset( $this->validation_messages['amount_error_totals'] );
			}
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
		if ( isset( $_GET['checkout'] ) ) {
			$payment_id   = $_GET['checkout'];
			$subscription = false;
		} elseif ( isset( $_GET['subscription'] ) ) {
			$payment_id   = $_GET['subscription'];
			$subscription = true;
		}

		if ( isset( $payment_id ) ) {

			$payson_order = pco_wc_get_order( $payment_id, $subscription );
			if ( is_wp_error( $payson_order ) ) {
				PaysonCheckout_For_WooCommerce_Logger::log( 'Could not get order in notification callback. Payment ID: ' . $payment_id . 'Is subscription order: ' . $subscription );
			} else {
				if ( 'readyToShip' === $payson_order['status'] || 'customerSubscribed' === $payson_order['status'] ) {
					PaysonCheckout_For_WooCommerce_Logger::log( 'Notification Listener hit: ' . json_encode( $_GET ) . ' URL: ' . $_SERVER['REQUEST_URI'] );
					wp_schedule_single_event( time() + 120, 'pco_check_for_order', array( $payment_id, $subscription ) );
				}
				header( 'HTTP/1.1 200 OK' );
			}
		}
	}

	public function pco_check_for_order_callback( $payment_id, $subscription ) {
		$query          = new WC_Order_Query(
			array(
				'limit'          => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'ids',
				'payment_method' => 'paysoncheckout',
				'date_created'   => '>' . ( time() - MONTH_IN_SECONDS ),
			)
		);
		$orders         = $query->get_orders();
		$order_id_match = '';

		foreach ( $orders as $order_id ) {
			if ( $subscription ) {
				$order_payment_id = get_post_meta( $order_id, '_payson_subscription_id', true );
			} else {
				$order_payment_id = get_post_meta( $order_id, '_payson_checkout_id', true );
			}

			if ( $order_payment_id === $payment_id ) {
				$order_id_match = $order_id;
				break;
			}
		}

		// Did we get a match?
		if ( $order_id_match ) {
			$order = wc_get_order( $order_id_match );

			if ( $order ) {
				PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. Payment id ' . $payment_id . '. already exist in order ID ' . $order_id_match );
			} else {
				// No order, why?
				PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. Payment id ' . $payment_id . '. already exist in order ID ' . $order_id_match . '. But we could not instantiate an order object' );
			}
		} else {
			// No order found - create a new
			PaysonCheckout_For_WooCommerce_Logger::log( 'API-callback hit. We could NOT find Payment id ' . $payment_id . '. Starting backup order creation...' );
			$this->backup_order_creation( $payment_id, $subscription );
		}
	}

	/**
	 * Backup order creation, in case checkout process failed.
	 *
	 * @param string $payment_id
	 * @param bool   $subscription
	 * @return void
	 */
	public function backup_order_creation( $payment_id, $subscription ) {
		// Get payson order
		$payson_order = pco_wc_get_order( $payment_id, $subscription );

		// Process order.
		$order = $this->process_order( $payson_order, $subscription );

		// Send order number to Payson
		if ( ! $subscription ) {
			if ( is_object( $order ) ) {
				PCO_WC()->update_reference->request( $order->get_id(), $payson_order );
			}
		}

	}

	/**
	 * Processes WooCommerce order on backup order creation.
	 *
	 * @param array $payson_order
	 * @return void
	 */
	private function process_order( $payson_order, $subscription ) {
		$order = wc_create_order( array( 'status' => 'pending' ) );

		if ( is_wp_error( $order ) ) {
			PaysonCheckout_For_WooCommerce_Logger::log( 'Backup order creation. Error - could not create order. ' . var_export( $order->get_error_message(), true ) );
		} else {
			PaysonCheckout_For_WooCommerce_Logger::log( 'Backup order creation - order ID - ' . $order->get_id() . ' - created.' );
		}

		$order_id = $order->get_id();

		$order->set_billing_first_name( sanitize_text_field( $payson_order['customer']['firstName'] ) );
		$order->set_billing_last_name( sanitize_text_field( $payson_order['customer']['lastName'] ) );
		$order->set_billing_country( sanitize_text_field( $payson_order['customer']['countryCode'] ) );
		$order->set_billing_address_1( sanitize_text_field( $payson_order['customer']['street'] ) );
		$order->set_billing_city( sanitize_text_field( $payson_order['customer']['city'] ) );
		$order->set_billing_postcode( sanitize_text_field( $payson_order['customer']['postalCode'] ) );
		$order->set_billing_phone( sanitize_text_field( $payson_order['customer']['phone'] ) );
		$order->set_billing_email( sanitize_text_field( $payson_order['customer']['email'] ) );

		$order->set_shipping_first_name( sanitize_text_field( $payson_order['customer']['firstName'] ) );
		$order->set_shipping_last_name( sanitize_text_field( $payson_order['customer']['lastName'] ) );
		$order->set_shipping_country( sanitize_text_field( $payson_order['customer']['countryCode'] ) );
		$order->set_shipping_address_1( sanitize_text_field( $payson_order['customer']['street'] ) );
		$order->set_shipping_city( sanitize_text_field( $payson_order['customer']['city'] ) );
		$order->set_shipping_postcode( sanitize_text_field( $payson_order['customer']['postalCode'] ) );

		update_post_meta( $order->get_id(), '_shipping_phone', sanitize_text_field( $payson_order['customer']['phone'] ) );
		update_post_meta( $order->get_id(), '_shipping_email', sanitize_text_field( $payson_order['customer']['email'] ) );
		$order->set_created_via( 'pco_checkout_backup_order_creation' );
		$order->set_currency( isset( $payson_order['order']['currency'] ) ? sanitize_text_field( strtoupper( $payson_order['order']['currency'] ) ) : '' );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );

		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['paysoncheckout'];
		$order->set_payment_method( $payment_method );

		if ( ! $subscription ) {
			$this->process_order_lines( $payson_order, $order );
			$order->set_shipping_total( self::get_shipping_total( $payson_order ) );
			$order->set_cart_tax( self::get_cart_contents_tax( $payson_order ) );
			$order->set_shipping_tax( self::get_shipping_tax_total( $payson_order ) );
			$order->set_total( $payson_order['order']['totalPriceIncludingTax'] );
		}

		// Make sure to run Sequential Order numbers if plugin exsists.
		if ( class_exists( 'WC_Seq_Order_Number_Pro' ) ) {
			$sequential = new WC_Seq_Order_Number_Pro();
			$sequential->set_sequential_order_number( $order_id );
		} elseif ( class_exists( 'WC_Seq_Order_Number' ) ) {
			$sequential = new WC_Seq_Order_Number();
			$sequential->set_sequential_order_number( $order_id, get_post( $order_id ) );
		}

		update_post_meta( $order_id, '_payson_checkout_id', $payson_order['id'] );
		update_post_meta( $order_id, '_payson_date_paid', date( 'Y-m-d H:i:s' ) );

		$order->calculate_totals();
		$order->save();

		if ( ! $subscription && 'readyToShip' === $payson_order['status'] ) {
			$order->payment_complete( $payson_order['purchaseId'] );
			$order->add_order_note( __( 'Order created via Payson Checkout API callback. Please verify the order in Payson system.', 'woocommerce-gateway-paysoncheckout' ) );
		} elseif ( $subscription ) {
			$order->update_status( 'failed', __( 'Payson Checkout does not have support for creating subscription orders via Payson Checkout API callback. Please verify the subscription order in Payson system.', 'woocommerce-gateway-paysoncheckout' ) );
		}

		if ( ! $subscription ) {
			$payson_order_total = floatval( round( $payson_order['order']['totalPriceIncludingTax'], 2 ) );
			$woo_order_total    = floatval( round( $order->get_total(), 2 ) );
			if ( $woo_order_total !== $payson_order_total ) {
				$order->update_status( 'on-hold', sprintf( __( 'Order needs manual review, WooCommerce total and Payson total do not match. Payson order total: %s.', 'woocommerce-gateway-paysoncheckout' ), $payson_order_total ) );
				PaysonCheckout_For_WooCommerce_Logger::log( 'Order total mismatch in order:' . $order->get_order_number() . '. Woo order total: ' . $woo_order_total . '. Payson order total: ' . $payson_order_total );
			}
		}

		return $order;

	}

	/**
	 * Processes cart contents on backup order creation.
	 *
	 * @param array    $payson_order
	 * @param WC_Order $order
	 * @return void
	 */
	private function process_order_lines( $payson_order, $order ) {
		PaysonCheckout_For_WooCommerce_Logger::log( 'Processing order lines (from Payson order) during backup order creation for Payson order ID ' . $payson_order['id'] );
		foreach ( $payson_order['order']['items'] as $cart_item ) {
			if ( strpos( $cart_item['reference'], 'shipping|' ) !== false ) {
				// Shipping
				$trimmed_cart_item_reference = str_replace( 'shipping|', '', $cart_item['reference'] );
				$method_id                   = substr( $trimmed_cart_item_reference, 0, strpos( $trimmed_cart_item_reference, ':' ) );
				$instance_id                 = substr( $trimmed_cart_item_reference, strpos( $trimmed_cart_item_reference, ':' ) + 1 );
				$rate                        = new WC_Shipping_Rate( $trimmed_cart_item_reference, $cart_item['name'], $cart_item['totalPriceExcludingTax'], array(), $method_id, $instance_id );
				$item                        = new WC_Order_Item_Shipping();
				$item->set_props(
					array(
						'method_title' => $rate->label,
						'method_id'    => $rate->id,
						'total'        => wc_format_decimal( $rate->cost ),
						'taxes'        => $rate->taxes,
						'meta_data'    => $rate->get_meta_data(),
					)
				);
				$order->add_item( $item );
			} elseif ( strpos( $cart_item['reference'], 'fee|' ) !== false ) {
				// Fee
				$trimmed_cart_item_id = str_replace( 'fee|', '', $cart_item['reference'] );
				$tax_class            = '';
				try {
					$args = array(
						'name'      => $cart_item['name'],
						'tax_class' => $tax_class,
						'subtotal'  => $cart_item['totalPriceExcludingTax'],
						'total'     => $cart_item['totalPriceExcludingTax'],
						'quantity'  => $cart_item['quantity'],
					);
					$fee  = new WC_Order_Item_Fee();
					$fee->set_props( $args );
					$order->add_item( $fee );
				} catch ( Exception $e ) {
					PaysonCheckout_For_WooCommerce_Logger::log( 'Backup order creation error add fee error: ' . $e->getCode() . ' - ' . $e->getMessage() );
				}
			} else {
				// Product items
				if ( wc_get_product_id_by_sku( $cart_item['reference'] ) ) {
					$id = wc_get_product_id_by_sku( $cart_item['reference'] );
				} else {
					$id = $cart_item['reference'];
				}
				try {
					$product = wc_get_product( $id );
					$args    = array(
						'name'         => $product->get_name(),
						'tax_class'    => $product->get_tax_class(),
						'product_id'   => $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(),
						'variation_id' => $product->is_type( 'variation' ) ? $product->get_id() : 0,
						'variation'    => $product->is_type( 'variation' ) ? $product->get_attributes() : array(),
						'subtotal'     => ( $cart_item['totalPriceExcludingTax'] ),
						'total'        => ( $cart_item['totalPriceExcludingTax'] ),
						'quantity'     => $cart_item['quantity'],
					);
					$item    = new WC_Order_Item_Product();
					$item->set_props( $args );
					$item->set_backorder_meta();
					$item->set_order_id( $order->get_id() );
					$item->calculate_taxes();
					$item->save();
					$order->add_item( $item );
				} catch ( Exception $e ) {
					PaysonCheckout_For_WooCommerce_Logger::log( 'Backup order creation error add to cart error: ' . $e->getCode() . ' - ' . $e->getMessage() );
				}
			}
		}

	}

	/**
	 * Get the shipping total including tax of Payson order.
	 *
	 * @param array $payson_order
	 * @return void
	 */
	private static function get_shipping_total( $payson_order ) {
		$shipping_total = 0;
		foreach ( $payson_order['order']['items'] as $cart_item ) {
			if ( strpos( $cart_item['reference'], 'shipping|' ) !== false ) {
				$shipping_total += $cart_item['totalPriceIncludingTax'];
			}
		}
		if ( $shipping_total > 0 ) {
			$shipping_total = $shipping_total;
		}
		return $shipping_total;
	}

	/**
	 * Get the cart contents tax of Payson order.
	 *
	 * @param array $payson_order
	 * @return void
	 */
	private static function get_cart_contents_tax( $payson_order ) {
		$cart_contents_tax = 0;
		foreach ( $payson_order['order']['items'] as $cart_item ) {
			if ( strpos( $cart_item['reference'], 'shipping|' ) === false && strpos( $cart_item['reference'], 'fee|' ) === false ) {
				$cart_contents_tax += $cart_item['totalTaxAmount'];
			}
		}
		if ( $cart_contents_tax > 0 ) {
			$cart_contents_tax = $cart_contents_tax;
		}
		return $cart_contents_tax;
	}

	/**
	 * Get the shipping tax total of Payson order.
	 *
	 * @param array $payson_order
	 * @return void
	 */
	private static function get_shipping_tax_total( $payson_order ) {
		$shipping_tax_total = 0;
		foreach ( $payson_order['order']['items'] as $cart_item ) {
			if ( strpos( $cart_item['reference'], 'shipping|' ) !== false ) {
				$shipping_tax_total += $cart_item['totalTaxAmount'];
			}
		}
		if ( $shipping_tax_total > 0 ) {
			$shipping_tax_total = $shipping_tax_total;
		}
		return $shipping_tax_total;
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
			$this->order_is_valid                             = false;
			$this->validation_messages['amount_error']        = __( 'Missmatch between the Payson and WooCommerce order total.', 'woocommerce-gateway-payson' );
			$this->validation_messages['amount_error_totals'] = 'Woo Total: ' . $woo_total . ' Payson total: ' . $payson_total;
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

	/**
	 * Checks if the email exists as a user and if they are logged in.
	 *
	 * @return void
	 */
	public function check_if_user_exists_and_logged_in() {
		// Check if the email exists as a user.
		$user = email_exists( $this->payson_order['customer']['email'] );

		// If not false, user exists. Check if the session id matches the User id.
		if ( false !== $user ) {
			if ( $user != $_GET['pco_session_id'] ) {
				$this->order_is_valid                    = false;
				$this->validation_messages['user_login'] = __( 'An account already exists with this email. Please login to complete the purchase.', 'woocommerce-gateway-payson' );
			}
		}
	}

	/**
	 * Sets the current user for the callback.
	 *
	 * @return void
	 */
	public function set_current_user() {
		if ( isset( $_GET['pco_session_id'] ) ) {
			wp_set_current_user( $_GET['pco_session_id'] );
		}
	}
}
new PaysonCheckout_For_WooCommerce_Callbacks();
