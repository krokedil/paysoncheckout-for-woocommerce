<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Returns error messages depending on
 *
 * @class    WC_PaysonCheckout_Admin_Notices
 * @version  1.0
 * @package  WC_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Admin_Notices {

	/**
	 * WC_PaysonCheckout_Admin_Notices constructor.
	 */
	public function __construct() {
		$paysoncheckout_settings = get_option( 'woocommerce_paysoncheckout_settings' );
		$this->enabled           = $paysoncheckout_settings['enabled'];
		// add_action( 'admin_notices', array( $this, 'check_settings' ) );
		// add_action( 'woocommerce_settings_saved', array( $this, 'validate_account' ) );
		add_action( 'admin_init', array( $this, 'check_settings' ) );
	}

	public function check_settings() {
		if ( ! empty( $_POST ) ) {
			add_action( 'woocommerce_settings_saved', array( $this, 'check_terms' ) );
			add_action( 'woocommerce_settings_saved', array( $this, 'validate_account_on_save' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'check_terms' ) );
			add_action( 'admin_notices', array( $this, 'validate_account' ) );
		}
	}

	/**
	 * Check if terms page is set
	 */
	public function check_terms() {
		if ( 'yes' != $this->enabled ) {
			return;
		}
		// Terms page
		if ( ! wc_get_page_id( 'terms' ) || wc_get_page_id( 'terms' ) < 0 ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . __( 'You need to specify a terms page in WooCommerce Settings to be able to use Payson.', 'woocommerce-gateway-paysoncheckout' ) . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Validate entered Payson credentials
	 */
	public function validate_account() {
		if ( 'yes' != $this->enabled ) {
			return;
		}
		// Account check
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$validation = $payson_api->get_validate_account();
		if ( is_wp_error( $validation ) ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . $validation->get_error_message() . '</p>';
			echo '</div>';
		}
	}

	/**
	 * Validate entered Payson credentials on saved settings
	 */
	public function validate_account_on_save() {
		$paysoncheckout_settings = get_option( 'woocommerce_paysoncheckout_settings' );
		$this->enabled           = $paysoncheckout_settings['enabled'];
		if ( 'yes' != $this->enabled || 'paysoncheckout' != $_GET['section'] ) {
			return;
		}
		// Account check
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$validation = $payson_api->get_validate_account();
		if ( is_wp_error( $validation ) ) {
			echo '<div class="notice notice-error">';
			echo '<p>' . $validation->get_error_message() . '</p>';
			echo '</div>';
		} elseif ( 'Approved' == $validation->status ) {
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>' . __( 'The Payson credentials where entered correctly.', 'woocommerce-gateway-paysoncheckout' ) . '</p>';
			echo '</div>';
		} else {
			echo '<div class="notice notice-error">';
			echo '<p>' . sprintf( __( 'The Payson credentials where entered correctly but account status is set to: %s', 'woocommerce-gateway-paysoncheckout' ), $validation->status ) . '</p>';
			echo '</div>';
		}
	}
}

$wc_paysoncheckout_admin_notices = new WC_PaysonCheckout_Admin_Notices;
