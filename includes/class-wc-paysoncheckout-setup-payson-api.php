<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Setup PaysonCheckout API
 *
 * @class    WC_PaysonCheckout_Setup_Payson_API
 * @version  1.0.0
 * @package  WC_Gateway_PaysonCheckout/Classes
 * @category Class
 * @author   Krokedil
 */
class WC_PaysonCheckout_Setup_Payson_API {

	/**
	 * WC_PaysonCheckout_Setup_Payson_API constructor.
	 */
	public function __construct() {
		$this->payment_method_id = 'paysoncheckout';
		$this->settings          = get_option( 'woocommerce_' . $this->payment_method_id . '_settings' );
	}

	/**
	 * Gets PaysonCheckout resource.
	 *
	 * @param bool $order_id integer.
	 *
	 * @return mixed|null|\PaysonEmbedded\Checkout
	 */
	public function get_checkout( $order_id = false ) {
		//require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';

		// Setup.
		$callPaysonApi  = $this->set_payson_api();
		$paysonMerchant = $this->set_merchant( $order_id );
		$payData        = $this->set_pay_data( $order_id );
		$gui            = $this->set_gui();
		$customer       = $this->set_customer();
		$checkout       = new PaysonEmbedded\Checkout( $paysonMerchant, $payData, $gui, $customer );

		/*
		 * Step 2 Create checkout
		 */
		$payson_embedded_status = '';
		$checkout_temp_obj      = null;
		if ( WC()->session->get( 'payson_checkout_id' ) ) {
			try {
				$checkout_temp_obj = $callPaysonApi->GetCheckout( WC()->session->get( 'payson_checkout_id' ) );
			} catch ( Exception $e ) {
				return new WP_Error( 'connection-error', $e->getMessage() );
				WC()->session->__unset( 'payson_checkout_id' );
			}
			$payson_embedded_status = $checkout_temp_obj->status;
			
			// Unset the payson_checkout_id session and create a new one if the currency hs been changed
			if( strtoupper($checkout_temp_obj->payData->currency) !== get_woocommerce_currency() ) {
				update_post_meta( $order_id, '_order_currency', get_woocommerce_currency() );
				WC()->session->__unset( 'payson_checkout_id' );
			}
		}
		if ( WC()->session->get( 'payson_checkout_id' ) && ( 'readyToPay' === $payson_embedded_status || 'created' === $payson_embedded_status ) ) {
			// Update checkout (if reloading checkout page when a payson_checkout_id session exist).
			$checkout_temp_obj->payData = $this->set_pay_data( $order_id );

			// Check if this is an pay for order or a regular purchase 
			if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
				$order = wc_get_order( $order_id );
				$confirmationUri = add_query_arg( array( 'pay_for_order' =>'true', 'paysonorder' => $checkout_temp_obj->id ), $order->get_checkout_order_received_url() );
			} else {
				$confirmationUri = add_query_arg( array( 'payson_payment_successful' =>'1', 'wc_order' => $order_id, 'paysonorder' => $checkout_temp_obj->id ), wc_get_checkout_url() );
			}
			$checkout_temp_obj->merchant->confirmationUri = $confirmationUri;

			WC_Gateway_PaysonCheckout::log( 'Update checkout call sent to Payson (in get_checkout() function): ' . stripslashes_deep( json_encode( $checkout_temp_obj ) ) );
			
			try {
				$checkout_temp_obj          = $callPaysonApi->UpdateCheckout( $checkout_temp_obj );
			} catch ( Exception $e ) {
				WC_Gateway_PaysonCheckout::log( 'Update checkout error response from Payson: ' . stripslashes_deep( json_encode( $e->getMessage() ) ) );
				WC()->session->__unset( 'payson_checkout_id' );
				return new WP_Error( 'connection-error', $e->getMessage() );
			}
		} else {
			// Create checkout
			WC_Gateway_PaysonCheckout::log( 'Create checkout call sent to Payson: ' . stripslashes_deep( json_encode( $checkout ) ) );
			
			try {
				$checkoutId = $callPaysonApi->CreateCheckout( $checkout );
			} catch ( Exception $e ) {
				WC_Gateway_PaysonCheckout::log( 'Create checkout error response from Payson: ' . stripslashes_deep( json_encode( $e->getMessage() ) ) );
				WC()->session->__unset( 'payson_checkout_id' );
				return new WP_Error( 'connection-error', $e->getMessage() );
			}
			
			$checkout_temp_obj = $callPaysonApi->GetCheckout( $checkoutId );
			WC_Gateway_PaysonCheckout::log( 'Create checkout response from Payson: ' . stripslashes_deep( json_encode( $checkout_temp_obj ) ) );
			
			// Check if this is an pay for order or a regular purchase 
			if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
				$order = wc_get_order( $order_id );
				$confirmationUri = add_query_arg( array( 'pay_for_order' =>'true', 'paysonorder' => $checkout_temp_obj->id ), $order->get_checkout_order_received_url() );
			} else {
				$confirmationUri = add_query_arg( array( 'payson_payment_successful' =>'1', 'wc_order' => $order_id, 'paysonorder' => $checkout_temp_obj->id ), wc_get_checkout_url() );
			}
			
			$checkout_temp_obj->merchant->confirmationUri = $confirmationUri;
			$checkout_temp_obj                            = $callPaysonApi->UpdateCheckout( $checkout_temp_obj );

			WC()->session->set( 'payson_checkout_id', $checkout_temp_obj->id );
		}
		return $checkout_temp_obj;
	}

	/**
	 * Updates PaysonCheckout resource.
	 *
	 * @param bool $order_id integer.
	 *
	 * @return mixed|null|\PaysonEmbedded\Checkout
	 */
	public function update_checkout( $order_id = false ) {

		// Setup.
		$callPaysonApi  = $this->set_payson_api();
		$paysonMerchant = $this->set_merchant( $order_id );
		$payData        = $this->set_pay_data( $order_id );
		$gui            = $this->set_gui();
		$customer       = $this->set_customer();
		$checkout       = new PaysonEmbedded\Checkout( $paysonMerchant, $payData, $gui, $customer );
		
		$payson_embedded_status = '';
		$checkout_temp_obj      = null;
		if ( WC()->session->get( 'payson_checkout_id' ) ) {
			try {
				$checkout_temp_obj = $callPaysonApi->GetCheckout( WC()->session->get( 'payson_checkout_id' ) );
			} catch ( Exception $e ) {
				WC_Gateway_PaysonCheckout::log( 'Failed to get checkout in update_checkout request: ' . stripslashes_deep( json_encode( $e->getMessage() ) ) );
				WC()->session->__unset( 'payson_checkout_id' );
				return new WP_Error( 'connection-error', $e->getMessage() );
			}
			$payson_embedded_status = $checkout_temp_obj->status;
			
			// Unset the payson_checkout_id session and return error if the currency hs been changed
			if( strtoupper($checkout_temp_obj->payData->currency) !== get_woocommerce_currency() ) {
				update_post_meta( $order_id, '_order_currency', get_woocommerce_currency() );
				WC()->session->__unset( 'payson_checkout_id' );
				return new WP_Error( 'order-error', 'Currency mismatch' );
			}
		}
		if ( WC()->session->get( 'payson_checkout_id' ) && ( 'readyToPay' === $payson_embedded_status || 'created' === $payson_embedded_status ) ) {
			// Update checkout.
			$checkout_temp_obj->payData = $this->set_pay_data( $order_id );
			
			// Update notification url with the Payson Checkout ID
			$confirmationUri = add_query_arg( array( 'payson_payment_successful' =>'1', 'wc_order' => $order_id ), wc_get_checkout_url() );
			$confirmationUri                              = add_query_arg( array( 'paysonorder' => $checkout_temp_obj->id ), $confirmationUri );
			$checkout_temp_obj->merchant->confirmationUri = $confirmationUri;
			
			WC_Gateway_PaysonCheckout::log( 'Update checkout call sent to Payson: ' . stripslashes_deep( json_encode( $checkout_temp_obj ) ) );
			$checkout_temp_obj          = $callPaysonApi->UpdateCheckout( $checkout_temp_obj );
			// @todo - check that the update request was ok
			return $checkout_temp_obj;
			
		} else {

			WC_Gateway_PaysonCheckout::log( 'Something was wrong with the status in update_checkout request: ' . stripslashes_deep( json_encode( $checkout_temp_obj ) ) );
			WC()->session->__unset( 'payson_checkout_id' );
			return new WP_Error( 'order-error', 'Status or payson_checkout_id problem' );
		}
	}

	public function set_payson_api() {
		//require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		// Your merchant ID and apikey. Information about the merchant and the integration.
		$environment = ( 'yes' == $this->settings['testmode'] ) ? true : false;
		$merchant_id = $this->settings['merchant_id'];
		$api_key     = $this->settings['api_key'];
		$callPaysonApi = new PaysonEmbedded\PaysonApi( $merchant_id, $api_key, $environment );

		return $callPaysonApi;
	}

	public function set_merchant( $order_id ) {
		//require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		// URLs used by payson for redirection after a completed/canceled/notification purchase.
		$order = wc_get_order( $order_id );

		// Check if this is an pay for order or a regular purchase 
		if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
			$confirmationUri = add_query_arg( array( 'pay_for_order' =>'true' ), $order->get_checkout_order_received_url() );
		} else {
			$confirmationUri = add_query_arg( array( 'payson_payment_successful' =>'1', 'wc_order' => $order_id ), wc_get_checkout_url() );
		}
		
		$checkoutUri     = wc_get_checkout_url();
		$notificationUri = add_query_arg( 'wc_order', $order_id, get_home_url() . '/wc-api/WC_Gateway_PaysonCheckout/' );
		$termsUri        = wc_get_page_permalink( 'terms' );
		$partnerId       = 'Krokedil';
		$reference       = $order->get_order_number();
		$paysonMerchant = new PaysonEmbedded\Merchant( $checkoutUri, $confirmationUri, $notificationUri, $termsUri, $partnerId, $reference );

		return $paysonMerchant;
	}

	public function set_pay_data( $order_id = false ) {
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-process-order-lines.php' );
		$order_lines = new WC_PaysonCheckout_Process_Order_Lines();
		$payData     = $order_lines->get_order_lines( $order_id );
		return $payData;
	}

	public function set_gui() {
		//require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		$gui = new  PaysonEmbedded\Gui( $this->get_payson_language(), $this->settings['color_scheme'], 'none', $this->get_request_phone(), $this->get_shipping_countries() );
		return $gui;
	}

	public function get_payson_language() {
		$iso_code      = explode( '_', get_locale() );
		$shop_language = $iso_code[0];
		switch ( $shop_language ) {
			case 'sv' :
				$payson_language = 'sv';
				break;
			case 'fi' :
				$payson_language = 'fi';
				break;
			default:
				$payson_language = 'en';
		}

		return $payson_language;
	}

	public function get_request_phone() {
		if ( 'yes' == $this->settings['request_phone'] ) {
			return true;
		} else {
			return null;
		}
	}

	public function set_customer() {
		//require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		$email        = '';
		$postcode     = '';
		$current_user = wp_get_current_user();
		// Get customer info if logged in
		if ( $current_user->user_email ) {
			$email = $current_user->user_email;
		}
		if ( WC()->customer->get_shipping_postcode() ) {
			$postcode = WC()->customer->get_shipping_postcode();
		}
		$customer = new  PaysonEmbedded\Customer( '', '', $email, '', '', '', '', $postcode, '' );

		return $customer;
	}

	public function get_notification_checkout( $order_id = false ) {
		//require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		$merchant_id 	= $this->settings['merchant_id'];
		$api_key     	= $this->settings['api_key'];
		$environment 	= ( 'yes' == $this->settings['testmode'] ) ? true : false;
		$callPaysonApi 	= new  PaysonEmbedded\PaysonApi( $merchant_id, $api_key, $environment );
		$checkout      	= $callPaysonApi->GetCheckout( $order_id );
		//$checkout 		= $this->verify_customer_data( $checkout );
		WC_Gateway_PaysonCheckout::log( 'Payson GetCheckout request: ' . stripslashes_deep( json_encode( $callPaysonApi ) ) );
		WC_Gateway_PaysonCheckout::log( 'Payson GetCheckout response (for id ' . $order_id . '): ' . stripslashes_deep( json_encode( $checkout ) ) );
		return $checkout;
	}

	public function get_validate_account() {
		//require_once PAYSONCHECKOUT_PATH . '/includes/lib/paysonapi.php';
		$merchant_id = $this->settings['merchant_id'];
		$api_key     = $this->settings['api_key'];
		$environment = ( 'yes' == $this->settings['testmode'] ) ? true : false;
		$callPaysonApi = new PaysonEmbedded\PaysonApi( $merchant_id, $api_key, $environment );
		try {
			$account = $callPaysonApi->Validate();

			return $account;
		} catch ( Exception $ex ) {
			return new WP_Error( 'error', __( 'The entered Payson Merchant ID, API Key or test/live mode is not correct.', 'woocommerce-gateway-paysoncheckout' ) );
		}
	}

	public function get_shipping_countries() {
		// Add shipping countries
		$wc_countries = new WC_Countries();
		$countries = array_keys( $wc_countries->get_allowed_countries() );
		return $countries;
	}

	public function verify_customer_data( $checkout ) {
		//error_log('$checkout ' . var_export($checkout, true));
		$billing_first_name     = isset( $checkout->customer->firstName ) ? $checkout->customer->firstName : '.';
		$billing_last_name      = isset( $checkout->customer->lastName ) ? $checkout->customer->lastName : '.';
		$billing_address     	= isset( $checkout->customer->street ) ? $checkout->customer->street : '.';
		$billing_postal_code    = isset( $checkout->customer->postalCode ) ? $checkout->customer->postalCode : '11111';
		$billing_city     		= isset( $checkout->customer->city ) ? $checkout->customer->city : '.';
		$billing_country      	= isset( $checkout->customer->countryCode ) ? $checkout->customer->countryCode : '.';
		$billing_phone      	= isset( $checkout->customer->phone ) ? $checkout->customer->phone : '0700000000';
		$billing_email      	= isset( $checkout->customer->email ) ? $checkout->customer->email : 'test@test.se';

		$customer_information = array(
			'billingFirstName'      =>  $billing_first_name,
			'billingLastName'       =>  $billing_last_name,
			'billingAddress'        =>  $billing_address,
			'billingPostalCode'     =>  $billing_postal_code,
			'billingCity'           =>  $billing_city,
			'billingCounry'           =>  $billing_country,
			'shippingFirstName'     =>  $billing_first_name,
			'shippingLastName'      =>  $billing_last_name,
			'shippingAddress'       =>  $billing_address,
			'shippingPostalCode'    =>  $billing_postal_code,
			'shippingCity'          =>  $billing_city,
			'shippingCounry'           =>  $billing_country,
			'phone'                 =>  $billing_phone,
			'email'                 =>  $billing_email,
		);
		//error_log('customer_information ' . var_export($customer_information, true));
		return $customer_information;
	}
}
