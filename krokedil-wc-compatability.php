<?php
/**
 *
 * Functions to make plugin compatible with multiple versions of WooCommerce
 *
 */
// Get the current WooCommerce version.
function krokedil_get_wc_version() {
	return defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;
}
// Check if WooCommerce version is greater or equal to 3.0
function krokedil_wc_gte_3_0() {
	return krokedil_get_wc_version() && version_compare( krokedil_get_wc_version(), '3.0', '>=' );
}
// Get the order total
function krokedil_get_order_total( $order ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $order->get_total();
	} else {
		return $order->order_total;
	}
}
// Get the billing email
function krokedil_get_billing_email( $order ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $order->get_billing_email();
	} else {
		return $order->billing_email;
	}
}
// Get the billing first name
function krokedil_get_billing_first_name( $order ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $order->get_billing_first_name();
	} else {
		return $order->billing_first_name;
	}
}
// Get the billing last name
function krokedil_get_billing_last_name( $order ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $order->get_billing_last_name();
	} else {
		return $order->billing_last_name;
	}
}
// Get the shipping tax
function krokedil_get_order_shipping_tax( $order ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $order->get_shipping_tax();
	} else {
		return $order->order_shipping_tax;
	}
}
// Get the product id
function krokedil_get_product_id( $product ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $product->get_id();
	} else {
		return $product->id;
	}
}
// Get the order id
function krokedil_get_order_id( $order ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $order->get_id();
	} else {
		return $order->id;
	}
}

// Get item meta
function krokedil_get_item_meta_cart( $item, $product ) {
	if ( krokedil_wc_gte_3_0() ) {
		$item_meta = '';
	} else {
		$item_meta = new WC_Order_Item_Meta( $item['item_meta'], $product );
		$item_meta = nl2br( $item_meta->display( true, true ) );
	}
	return $item_meta;
}
function krokedil_get_item_meta_order( $item, $product ) {
	if ( krokedil_wc_gte_3_0() ) {
		$item_meta = strip_tags( wc_display_item_meta( $item, array(
			'before'    => '',
			'separator' => ', ',
			'after'     => '',
			'echo'      => false,
			'autop'     => false,
		) ) );

		return $item_meta;
	} else {
		$item_meta = new WC_Order_Item_Meta( $item['item_meta'], $product );
		$item_meta = nl2br( $item_meta->display( true, true ) );

		return $item_meta;
	}
}
function krokedil_get_payment_method( $order ) {
	if ( krokedil_wc_gte_3_0() ) {
		return $order->get_payment_method();
	} else {
		return $order->payment_method;
	}
}
