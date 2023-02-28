<?php
/**
 * Get order helper class.
 *
 * @package PaysonCheckout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class for order management.
 */
class PaysonCheckout_For_WooCommerce_Helper_Order {
	/**
	 * Gets formated order items.
	 *
	 * @param int $order_id The WooCommerce order object.
	 * @return array Formated order items.
	 */
	public function get_order_items( $order_id ) {
		$formated_order_items = array();
		$order                = wc_get_order( $order_id );
		// Get order items.
		$order_items = $order->get_items();
		foreach ( $order_items as $order_item ) {
			$formated_order_items[] = $this->get_order_item( $order, $order_item );
		}

		// Get order fees.
		$order_fees = $order->get_fees();
		foreach ( $order_fees as $fee ) {
			$formated_order_items[] = $this->get_fee( $fee, $order );
		}

		// Get order shipping.
		if ( $order->get_shipping_method() ) {
			$shipping = $this->get_shipping( $order );
			if ( null !== $shipping ) {
				$formated_order_items[] = $shipping;
			}
		}

		return $formated_order_items;
	}

	/**
	 * Gets formated order item.
	 *
	 * @param object $order_item WooCommerce order item object.
	 * @return array Formated order item.
	 */
	public function get_order_item( $order, $order_item ) {
		return array(
			'name'      => $this->get_product_name( $order_item ), // String.
			'unitPrice' => $this->get_product_unit_price( $order_item ), // Float.
			'quantity'  => $order_item->get_quantity(), // Float.
			'taxRate'   => $this->get_product_tax_rate( $order, $order_item ), // Float.
			'reference' => $this->get_product_sku( $order_item ), // String.
		);
	}

	/**
	 * Gets the product name.
	 *
	 * @param object $order_item The order item.
	 * @return string
	 */
	public function get_product_name( $order_item ) {
		$item_name = $order_item->get_name();
		return strip_tags( $item_name );
	}

	/**
	 * Gets the products unit price.
	 *
	 * @param object $order_item The order item.
	 * @return float
	 */
	public function get_product_unit_price( $order_item ) {
		$item_subtotal = ( $order_item->get_total() + $order_item->get_total_tax() ) / $order_item->get_quantity();
		return round( $item_subtotal, 2 );
	}

	/**
	 * Gets the tax rate for the product.
	 *
	 * @param object $order The order item.
	 * @param object $order_item The WooCommerce order item.
	 * @return float
	 */
	public function get_product_tax_rate( $order, $order_item ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			foreach ( $order_item->get_taxes()['total'] as $key => $value ) {
				if ( '' !== $value ) {
					if ( $rate_id === $key ) {
						return round( WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] / 100, 2 );
					}
				}
			}
		}
		// If we get here, there is no tax set for the order item. Return zero.
		return 0;
	}

	/**
	 * Formats the fee.
	 *
	 * @param object $fee A WooCommerce Fee.
	 * @return array
	 */
	public function get_fee( $fee, $order ) {
		return array(
			'name'      => $fee->get_name(), // String.
			'unitPrice' => $fee->get_amount() + $fee->get_total_tax(), // Float.
			'quantity'  => 1, // Float.
			'taxRate'   => $this->get_product_tax_rate( $order, $fee ), // Float.
			'reference' => $fee->get_id(), // String.
		);
	}

	/**
	 * Formats the shipping.
	 *
	 * @return array
	 */
	public function get_shipping( $order ) {
		if ( $order->get_shipping_total() > 0 ) {
			return array(
				'name'      => $order->get_shipping_method(), // String.
				'unitPrice' => $order->get_shipping_total() + $order->get_shipping_tax(), // Float.
				'quantity'  => 1, // Float.
				'taxRate'   => ( '0' !== $order->get_shipping_tax() ) ? $this->get_product_tax_rate( $order, current( $order->get_items( 'shipping' ) ) ) : 0, // Float.
				'reference' => __( 'Shipping', 'payson-checkout-for-woocommerce' ), // String.
			);
		} else {
			return array(
				'name'      => $order->get_shipping_method(), // String.
				'unitPrice' => 0, // Float.
				'quantity'  => 1, // Float.
				'taxRate'   => 0, // Float.
				'reference' => __( 'Shipping', 'payson-checkout-for-woocommerce' ), // String.
			);
		}
	}

	/**
	 * Get the product SKU (defaults to ID).
	 *
	 * @param WC_Order_Item_Product $order_item The WooCommerce Product.
	 * @return string
	 */
	public function get_product_sku( $order_item ) {
		$product = $order_item->get_product();
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return $item_reference;
	}
}
