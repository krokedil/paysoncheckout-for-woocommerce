<?php
/**
 * Plugin Name:     PaysonCheckout 2.0 for WooCommerce
 * Plugin URI:      http://krokedil.com/
 * Description:     Provides a PaysonCheckout 2.0 payment gateway for WooCommerce.
 * Version:         1.1.10
 * Author:          Krokedil
 * Author URI:      http://krokedil.com/
 * Developer:       Krokedil
 * Developer URI:   http://krokedil.com/
 * Text Domain:     woocommerce-gateway-paysoncheckout
 * Domain Path:     /languages
 * Copyright:       © 2016-2017 Krokedil.
 * License:         GNU General Public License v3.0
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Localisation.
 */
load_plugin_textdomain( 'woocommerce-gateway-paysoncheckout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

// Define plugin constants.
define( 'PAYSONCHECKOUT_VERSION', '1.1.10' );
define( 'PAYSONCHECKOUT_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
define( 'PAYSONCHECKOUT_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

// Include files.
include_once( 'includes/class-wc-paysoncheckout.php' );
include_once( 'includes/gateways/class-wc-paysoncheckout-gateway.php' );
include_once( 'includes/class-wc-paysoncheckout-wc-order.php' );
include_once( 'includes/class-wc-paysoncheckout-ajax.php' );
include_once( 'includes/class-wc-paysoncheckout-response-handler.php' );
include_once( 'includes/class-wc-paysoncheckout-capture.php' );
include_once( 'includes/class-wc-paysoncheckout-cancel-reservation.php' );
include_once( 'includes/class-wc-paysoncheckout-admin-notices.php' );
include_once( 'krokedil-wc-compatability.php' );
include_once( PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php' );


