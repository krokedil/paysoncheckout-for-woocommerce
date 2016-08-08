<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Process order lines for sending them to Payson
 *
 * @class    WC_PaysonCheckout_Process_Order_Lines
 * @version  1.0.0
 * @package  WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Process_Order_Lines {

	/**
	 * Get order lines from order or cart
	 *
	 * @param  bool $order_id
	 *
	 * @return array $order_lines
	 */
	public function get_order_lines( $order_id = false ) {
		if ( $order_id ) {
			return $this->get_order_lines_from_order( $order_id );
		} else {
			return $this->get_order_lines_from_cart();
		}
	}

	/**
	 * Process WooCommerce order into Payson order lines
	 *
	 * @param $order_id
	 *
	 * @return array
	 */
	private function get_order_lines_from_order( $order_id ) {
		require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		$order       = new WC_Order( $order_id );
		$order_lines = array();
		
		if( 'EUR' == $order->get_order_currency() ) {
			$payData = new  PaysonEmbedded\PayData(PaysonEmbedded\CurrencyCode::EUR);
		} else {
			$payData = new  PaysonEmbedded\PayData(PaysonEmbedded\CurrencyCode::SEK);
		}

		//$payData->AddOrderItem(new  PaysonEmbedded\OrderItem('discount', -20, 1, 0.1, 'a',PaysonEmbedded\OrderItemType::DISCOUNT));
		//$payData->AddOrderItem(new  PaysonEmbedded\OrderItem('Badboll', 47.2, 1, 0.06, 'sku-test', PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
		

		// Process order lines
		if ( sizeof( $order->get_items() ) > 0 ) {
			
			foreach ( $order->get_items() as $item_key => $item ) {
				$_product      = $order->get_product_from_item( $item );
				$title = $item['name'];
				$price = $order->get_item_total( $item, true );
				$qty = $item['qty'];
				$vat = round( $order->get_item_tax( $item ) / $order->get_item_total( $item, false ), 2 );
				
				
				$sku = $this->get_item_reference( $_product );
				
				$payData->AddOrderItem(new  PaysonEmbedded\OrderItem($title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
				
			}			
		}
		
		// Process shipping
		if ( $order->get_total_shipping() > 0 ) {
			foreach ( $order->get_shipping_methods() as $shipping_method_key => $shipping_method_value ) {
				$shipping_method_tax = array_sum( maybe_unserialize( $shipping_method_value['taxes'] ) );
				
				$title = $shipping_method_value['name'];
				$price = $shipping_method_value['cost'] + $shipping_method_tax;
				$qty = 1;
				$vat = round( $shipping_method_tax / $shipping_method_value['cost'], 2 );
				$sku = 'Shipping';
				$payData->AddOrderItem(new  PaysonEmbedded\OrderItem($title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
				
			}
		}
		
		// Process fees
		$order_fees = $order->get_fees();
		if ( ! empty( $order_fees ) ) {
			foreach ( $order->get_fees() as $order_fee_key => $order_fee_value ) {
				
				$title = $order_fee_value['name'];
				$price = round( ( $order_fee_value['line_tax'] + $order_fee_value['line_total'] ), 2 );
				$qty = 1;
				$vat = round( $order_fee_value['line_tax'] / $order_fee_value['line_total'], 2 );
				$sku = 'Fee';
				$payData->AddOrderItem(new  PaysonEmbedded\OrderItem($title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
				
			}
		}


		return $payData;
	}


	/**
	 * Process WooCommerce cart into Payson order lines
	 *
	 * @return array
	 */
	public function get_order_lines_from_cart() {
		require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		
		if( 'EUR' == get_woocommerce_currency() ) {
			$payData = new  PaysonEmbedded\PayData(PaysonEmbedded\CurrencyCode::EUR);
		} else {
			$payData = new  PaysonEmbedded\PayData(PaysonEmbedded\CurrencyCode::SEK);
		}

		// Process order lines
		if ( sizeof( WC()->cart->cart_contents ) > 0 ) {
			foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
				$_product     		= apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_name      	= apply_filters( 'woocommerce_cart_item_name', $_product->get_title(), $cart_item, $cart_item_key );
				$product_price     	= ($cart_item['line_total'] + $cart_item['line_tax'] )/ $cart_item['quantity'];
				$qty 				= $cart_item['quantity'];
				$sku				= $_product->get_sku();
				$vat 				= round( $cart_item['line_tax'] / $cart_item['line_total'], 2 );
				
				$payData->AddOrderItem(new  PaysonEmbedded\OrderItem($product_name, $product_price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
			}
		}

		// Process shipping
		if ( WC()->shipping->get_packages() ) {
			foreach ( WC()->shipping->get_packages() as $shipping_package ) {
				foreach ( $shipping_package['rates'] as $shipping_rate_key => $shipping_rate_value ) {
					$shipping_tax = array_sum( $shipping_rate_value->taxes );

					if ( $shipping_rate_value->cost > 0 ) {
						$vat_percent = round( $shipping_tax / $shipping_rate_value->cost, 2 );
					} else {
						$vat_percent = 0;
					}
				
					$title = $shipping_rate_value->label;
					$price = $shipping_rate_value->cost + $shipping_tax;
					$qty = 1;
					$sku = 'Shipping';
					
					$payData->AddOrderItem(new  PaysonEmbedded\OrderItem($title, $price, $qty, $vat_percent, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
				
				}
			}

		}

		// Process fees
		if ( WC()->cart->fee_total > 0 ) {
			foreach ( WC()->cart->get_fees() as $cart_fee ) {
				$cart_fee_tax = array_sum( $cart_fee->tax_data );
				$title = $cart_fee->label;
				$price = round( ( $cart_fee->amount + $cart_fee_tax ), 2 );
				$qty = 1;
				$vat = round( $cart_fee_tax / $cart_fee->amount, 2 );
				$sku = 'Fee';
				$payData->AddOrderItem(new  PaysonEmbedded\OrderItem($title, $price, $qty, $vat, $sku, PaysonEmbedded\OrderItemType::PHYSICAL, 0, 'ean12345', 'http://uri', 'http://imageUri'));
			}
		}
		return $payData;
	}
	
	
	public function get_item_reference( $_product ) {
		$item_reference = '';
		if ( $_product->get_sku() ) {
			$item_reference = $_product->get_sku();
		} elseif ( $_product->variation_id ) {
			$item_reference = $_product->variation_id;
		} else {
			$item_reference = $_product->id;
		}
		return strval( $item_reference );
	}

}