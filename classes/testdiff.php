<?php
/**
 * Logging class file.
 *
 * @package Qliro_One_For_WooCommerce/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Qliro_One_Logger class.
 */
class Qliro_One_Logger {
	/**
	 * Log message string
	 *
	 * @var $log
	 */
	private static $log;

	/**
	 * Logs an event.
	 *
	 * @param string $data The data string.
	 */
	public static function log( $data ) {
		$settings = get_option( 'woocommerce_qliro_one_settings' );

		if ( 'yes' === $settings['logging'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'qliro-one-checkout-for-woocommerce', wp_json_encode( $message ) );
		}

		if ( isset( $data['response']['code'] ) && ( $data['response']['code'] < 200 || $data['response']['code'] > 299 ) ) {
			self::log_to_db( $data );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param string $data Json string of data.
	 * @return array
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) ) {
			$data['request']['body'] = json_decode( $data['request']['body'], true );
		}
		return $data;
	}

	/**
	 * Formats the log data to be logged.
	 *
	 * @param string          $qliro_order_id The Qliro One order id.
	 * @param string          $method The method.
	 * @param string          $title The title for the log.
	 * @param array           $request_args The request args.
	 * @param object|WP_Error $response The response.
	 * @param string          $code The status code.
	 * @param string          $request_url The request url.
	 * @return array
	 */
	public static function format_log( $qliro_order_id, $method, $title, $request_args, $response, $code, $request_url = null ) {
		// Unset the snippet to prevent issues in the response.
		if ( ! is_wp_error( $response ) ) {
			$response = self::remove_html_snippet( $response );
		}
		// Unset the snippet to prevent issues in the request body.
		if ( isset( $request_args['body'] ) ) {
			$request_body = json_decode( $request_args['body'], true );
			if ( isset( $request_body['OrderHtmlSnippet'] ) ) {
				unset( $request_body['OrderHtmlSnippet'] );
				$request_args['body'] = wp_json_encode( $request_body );
			}
		}
		return array(
			'id'             => $qliro_order_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'request_url'    => $request_url,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => date( 'Y-m-d H:i:s' ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions -- Date is not used for display.
			'stack'          => self::get_stack(),
			'plugin_version' => QLIRO_WC_VERSION,
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ), true ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
					if ( isset( $data['object'] ) && $data['object'] instanceof WP_Hook ) {
						$priority   = $data['object']->current_priority();
						$name       = is_array( $data['object']->current() ) ? key( $data['object']->current() ) : '';
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}
		return $stack;
	}

	/**
	 * Logs an event in the WP DB.
	 *
	 * @param array $data The data to be logged.
	 */
	public static function log_to_db( $data ) {
		$logs = get_option( 'krokedil_debuglog_qliro_one', array() );

		if ( ! empty( $logs ) ) {
			$logs = json_decode( $logs );
		}

		$logs   = array_slice( $logs, -14 );
		$logs[] = $data;
		$logs   = wp_json_encode( $logs );
		update_option( 'krokedil_debuglog_qliro_one', $logs );
	}

	/**
	 * Removes the HTML snippet from the response body if its set.
	 *
	 * @param object $response
	 * @return object
	 */
	public static function remove_html_snippet( $response ) {
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['OrderHtmlSnippet'] ) ) {
			unset( $body['OrderHtmlSnippet'] );
		}

		$response['body'] = $body;

		return $response;
	}
}
