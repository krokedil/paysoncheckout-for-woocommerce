<?php
/**
 * Get cart helper class.
 *
 * @package PaysonCheckout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for cart management.
 */
class PaysonCheckout_For_WooCommerce_Helper_Cart {
	/**
	 * Gets formated cart items.
	 *
	 * @param object $cart The WooCommerce cart object.
	 * @return array Formated cart items.
	 */
	public function get_cart_items( $cart = null ) {
		$formated_cart_items = array();

		if ( null === $cart ) {
			$cart = WC()->cart->get_cart();
		}

		// Get cart items.
		foreach ( $cart as $cart_item ) {
			$formated_cart_items[] = $this->get_cart_item( $cart_item );
		}

		// Get cart fees.
		$cart_fees = WC()->cart->get_fees();
		foreach ( $cart_fees as $fee ) {
			$formated_cart_items[] = $this->get_fee( $fee );
		}

		// Get cart shipping.
		if ( WC()->cart->needs_shipping() ) {
			$shipping = $this->get_shipping();
			if ( null !== $shipping ) {
				$formated_cart_items[] = $shipping;
			}
		}

		return $formated_cart_items;
	}

	/**
	 * Gets formated cart item.
	 *
	 * @param object $cart_item WooCommerce cart item object.
	 * @return array Formated cart item.
	 */
	public function get_cart_item( $cart_item ) {
		if ( $cart_item['variation_id'] ) {
			$product = wc_get_product( $cart_item['variation_id'] );
		} else {
			$product = wc_get_product( $cart_item['product_id'] );
		}
		return array(
			'name'      => $this->get_product_name( $cart_item ), // String.
			'unitPrice' => $this->get_product_unit_price( $cart_item ), // Float.
			'quantity'  => $cart_item['quantity'], // Float.
			'taxRate'   => $this->get_product_tax_rate( $cart_item ), // Float.
			'reference' => $this->get_product_sku( $product ), // String.
		);
	}

	/**
	 * Gets the product name.
	 *
	 * @param object $cart_item The cart item.
	 * @return string
	 */
	public function get_product_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$cart_item_name = $cart_item_data->get_name();
		$item_name      = apply_filters( 'pco_cart_item_name', $cart_item_name, $cart_item );
		return strip_tags( $item_name );
	}

	/**
	 * Gets the products unit price.
	 *
	 * @param object $cart_item The cart item.
	 * @return float
	 */
	public function get_product_unit_price( $cart_item ) {
		$item_subtotal = ( $cart_item['line_total'] + $cart_item['line_tax'] ) / $cart_item['quantity'];
		return round( $item_subtotal, 2 );
	}

	/**
	 * Gets the tax rate for the product.
	 *
	 * @param object $cart_item The cart item.
	 * @return float
	 */
	public function get_product_tax_rate( $cart_item ) {
		if ( 0 === intval( $cart_item['line_total'] ) ) {
			return 0;
		}
		return round( $cart_item['line_tax'] / $cart_item['line_total'], 2 );
	}

	/**
	 * Get the product SKU (defaults to ID).
	 *
	 * @param object $product The WooCommerce Product.
	 * @return string
	 */
	public function get_product_sku( $product ) {
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return $item_reference;
	}

	/**
	 * Formats the fee.
	 *
	 * @param object $fee A WooCommerce Fee.
	 * @return array
	 */
	public function get_fee( $fee ) {
		return array(
			'name'      => $fee->name, // String.
			'unitPrice' => $fee->amount + $fee->tax, // Float.
			'quantity'  => 1, // Float.
			'taxRate'   => $fee->tax / $fee->amount, // Float.
			'reference' => 'fee|' . $fee->id, // String.
		);
	}

	/**
	 * Formats the shipping.
	 *
	 * @return array
	 */
	public function get_shipping() {
		$packages        = WC()->shipping->get_packages();
		$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = ( ! empty( $chosen_methods ) ) ? $chosen_methods[0] : null;
		foreach ( $packages as $i => $package ) {
			foreach ( $package['rates'] as $method ) {
				if ( $chosen_shipping === $method->id ) {
					if ( $method->cost > 0 ) {
						return array(
							'name'      => $method->label, // String.
							'unitPrice' => $method->cost + array_sum( $method->taxes ), // Float.
							'quantity'  => 1, // Float.
							'taxRate'   => array_sum( $method->taxes ) / $method->cost, // Float.
							'reference' => 'shipping|' . $method->id, // String.
							'type'      => 'service',
						);
					} else {
						return array(
							'name'      => $method->label, // String.
							'unitPrice' => 0, // Float.
							'quantity'  => 1, // Float.
							'taxRate'   => 0, // Float.
							'reference' => 'shipping|' . $method->id, // String.
							'type'      => 'service',
						);
					}
				}
			}
		}
	}
}
