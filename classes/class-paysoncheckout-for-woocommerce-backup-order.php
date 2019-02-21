<?php
/**
 * Backup order creation class file.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Backup order creation class
 */
class PaysonCheckout_For_WooCommerce_Backup_Order {

	/**
	 * Create backup order on checkout error.
	 *
	 * @param array  $payson_order The Payson order object.
	 * @param string $error_message The checkout error message.
	 * @return string
	 */
	public function checkout_error( $payson_order, $error_message ) {
		// Create order.
		$this->create_order();

		// Add cart data to order.
		$this->add_items_to_local_order();
		$this->add_order_fees();
		$this->add_order_shipping();
		$this->add_order_tax_rows();
		$this->add_order_coupons();
		$this->add_order_payment_method();

		// Add Payson data to order.
		$this->add_customer_data_to_local_order();

		// Calculate order totals and save order.
		$this->calculate_order_totals();

		// Do payment_complete and set status to on-hold.
		$this->order->payment_complete( $payson_order['purchaseId'] );
		// Translators: $error_message.
		$note = sprintf( __( 'This order was made as a fallback due to an error in the checkout (%s). Please verify the order with Collector.', 'woocommerce-payson-checkout' ), $error_message );
		$this->order->add_order_note( $note );
		$this->order->set_stauts( 'on-hold' );

		// Return order ID.
		return $this->order->get_id();
	}

	/**
	 * Create WooCommerce order
	 *
	 * @return object $order WC_Order object.
	 */
	public function create_order() {
		$order       = wc_create_order();
		$this->order = $order;
		return $order;
	}

	/**
	 * Add cart items to order.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function add_items_to_local_order( $order ) {
		// Remove items as to stop the item lines from being duplicated.
		$order->remove_order_items();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) { // Store the line items to the new/resumed order.
			$item_id = $order->add_product( $values['data'], $values['quantity'], array(
				'variation' => $values['variation'],
				'totals'    => array(
					'subtotal'     => $values['line_subtotal'],
					'subtotal_tax' => $values['line_subtotal_tax'],
					'total'        => $values['line_total'],
					'tax'          => $values['line_tax'],
					'tax_data'     => $values['line_tax_data'],
				),
			) );
			if ( ! $item_id ) {
				$order->add_order_note( 'Error: Unable to add cart items in Create Local Order Fallback.' );
			}
			do_action( 'woocommerce_add_order_item_meta', $item_id, $values, $cart_item_key ); // Allow plugins to add order item meta.
		}
	}

	/**
	 * Add cart fees to order.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function add_order_fees( $order ) {
		$order_id = $order->get_id();
		foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
			$item_id = $order->add_fee( $fee );
			if ( ! $item_id ) {
				$order->add_order_note( 'Error: Unable to add cart fees in Create Local Order Fallback.' );
			}
			// Allow plugins to add order item meta to fees.
			do_action( 'woocommerce_add_order_fee_meta', $order_id, $item_id, $fee, $fee_key );
		}
	}

	/**
	 * Add cart shipping to order.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function add_order_shipping( $order ) {
		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}
		$order_id              = $order->get_id();
		$this_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );
		WC()->cart->calculate_shipping();
		// Store shipping for all packages.
		foreach ( WC()->shipping->get_packages() as $package_key => $package ) {
			if ( isset( $package['rates'][ $this_shipping_methods[ $package_key ] ] ) ) {
				$item_id = $order->add_shipping( $package['rates'][ $this_shipping_methods[ $package_key ] ] );
				if ( ! $item_id ) {
					$order->add_order_note( 'Error: Unable to add cart shipping in Create Local Order Fallback.' );
				}
				// Allows plugins to add order item meta to shipping.
				do_action( 'woocommerce_add_shipping_order_item', $order_id, $item_id, $package_key );
			}
		}
	}

	/**
	 * Add cart taxes to order.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function add_order_tax_rows( $order ) {
		// Store tax rows.
		foreach ( array_keys( WC()->cart->taxes + WC()->cart->shipping_taxes ) as $tax_rate_id ) {
			if ( $tax_rate_id && ! $order->add_tax( $tax_rate_id, WC()->cart->get_tax_amount( $tax_rate_id ), WC()->cart->get_shipping_tax_amount( $tax_rate_id ) ) && apply_filters( 'woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated' ) !== $tax_rate_id ) {
				$order->add_order_note( 'Error: Unable to add taxes in Create Local Order Fallback.' );
			}
		}
	}

	/**
	 * Add cart coupons to order.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function add_order_coupons( $order ) {
		foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
			if ( ! $order->add_coupon( $code, WC()->cart->get_coupon_discount_amount( $code ) ) ) {
				$order->add_order_note( 'Error: Unable to add cart coupons in Create Local Order Fallback.' );
			}
		}
	}

	/**
	 * Set payment method for order.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function add_order_payment_method( $order ) {
		$available_gateways = WC()->payment_gateways->payment_gateways();
		$payment_method     = $available_gateways['paysoncheckout'];
		$order->set_payment_method( $payment_method );
	}

	/**
	 * Add cart items to order.
	 *
	 * @param object $order WC_Order object.
	 * @param array  $payson_order The Payson order.
	 * @return void
	 */
	public function add_customer_data_to_local_order( $order, $payson_order ) {
		$address_data = $payson_order['customer'];

		// Set billing data.
		$order->set_billing_first_name( $address_data['firstName'] );
		$order->set_billing_last_name( $address_data['lastName'] );
		$order->set_billing_address_1( $address_data['street'] );
		$order->set_billing_city( $address_data['city'] );
		$order->set_billing_postalcode( $address_data['postalCode'] );
		$order->set_billing_country( $address_data['countryCode'] );
		$order->set_billing_email( $address_data['email'] );
		$order->set_billing_phone( $address_data['phone'] );

		// Set shipping data.
		$order->set_shipping_first_name( $address_data['firstName'] );
		$order->set_shipping_last_name( $address_data['lastName'] );
		$order->set_shipping_address_1( $address_data['street'] );
		$order->set_shipping_city( $address_data['city'] );
		$order->set_shipping_postalcode( $address_data['postalCode'] );
		$order->set_shipping_country( $address_data['countryCode'] );
		$order->set_shipping_email( $address_data['email'] );
		$order->set_shipping_phone( $address_data['phone'] );

		// Set post metas.
		update_post_meta( $order_id, '_payson_payment_id', $payson_order['id'] );
		update_post_meta( $order_id, '_created_via_payson_fallback', 'yes' );

		// Set customer id.
		$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
	}

	/**
	 * Calculate order totals and save.
	 *
	 * @param object $order WC_Order object.
	 * @return void
	 */
	public function calculate_order_totals( $order ) {
		$order->calculate_totals();
		$order->save();
	}
}
