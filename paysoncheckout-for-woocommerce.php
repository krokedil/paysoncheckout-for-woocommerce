<?php
/**
 * Plugin Name:     PaysonCheckout 2.0 gateway for WooCommerce
 * Plugin URI:      http://krokedil.com/
 * Description:     Provides PaysonCheckout 2.0 payment gateway for WooCommerce.
 * Version:         0.1
 * Author:          Krokedil
 * Author URI:      http://krokedil.com/
 * Developer:       Krokedil
 * Developer URI:   http://krokedil.com/
 * Text Domain:     woocommerce-gateway-paysoncheckout
 * Domain Path:     /languages
 * Copyright:       © 2016 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Define plugin paths
define( 'PAYSONCHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
define( 'PAYSONCHECKOUT_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

// Include files
include_once( 'includes/gateways/class-wc-payson-checkout.php' );
include_once( 'includes/class-wc-paysoncheckout-wc-order.php' );
include_once( 'includes/class-wc-paysoncheckout-create-checkout.php' );
include_once( 'includes/class-wc-paysoncheckout-ajax-handler.php' );
include_once( 'includes/class-wc-paysoncheckout-response-handler.php' );

//include_once( 'includes/class-process-order-lines.php' );


/**
 * Load a custom template for checkout
 */
function load_custom_checkout_template( $located, $template_name ) {
	
	if( 'checkout/form-checkout.php' == $template_name ) {
		return PAYSONCHECKOUT_PATH . '/templates/form-checkout.php';
	}
	return $located;
}
//add_filter( 'wc_get_template', 'load_custom_checkout_template', 10, 2 );