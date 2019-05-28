<?php
/**
 * Main request class
 *
 * @package PaysonCheckout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main request class
 */
class PaysonCheckout_For_WooCommerce_Request {
	/**
	 * The request enviroment.
	 *
	 * @var $enviroment
	 */
	public $enviroment;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->set_enviroment();
	}

	/**
	 * Returns headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		return PaysonCheckout_For_WooCommerce_Helper_Headers::get_headers();
	}

	/**
	 * Sets the enviroment.
	 *
	 * @return void
	 */
	public function set_enviroment() {
		$live_enviroment = 'https://api.payson.se/2.0/';
		$test_enviroment = 'https://test-api.payson.se/2.0/';
		$payson_settings = get_option( 'woocommerce_paysoncheckout_settings' );

		if ( 'no' === $payson_settings['testmode'] ) {
			$this->enviroment = 'https://api.payson.se/2.0/';
		} else {
			$this->enviroment = 'https://test-api.payson.se/2.0/';
		}
	}

	/**
	 * Checks response for any error.
	 *
	 * @param object $response The response.
	 * @param array  $request_args The request args.
	 * @param string $request_url The request URL.
	 * @return object|array
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		// Check the status code.
		if ( wp_remote_retrieve_response_code( $response ) < 200 || wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = ' ';
			// Get the error messages.
			if ( null !== json_decode( $response['body'], true )['errors'] ) {
				foreach ( json_decode( $response['body'], true )['errors'] as $error ) {
					$error_message = $error_message . '<br>' . $error['message'];
				}
			}
			return new WP_Error( wp_remote_retrieve_response_code( $response ), $response['response']['message'] . $error_message, $data );
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
