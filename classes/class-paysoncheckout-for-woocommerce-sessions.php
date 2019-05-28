<?php
/**
 * Sessions class file.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sessions class.
 */
class PaysonCheckout_For_WooCommerce_Sessions {
	/**
	 * The session id.
	 *
	 * @var string $session_id The WooCommerce session id.
	 */
	public $session_id = '';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->set_session_id();

		add_action( 'wp_loaded', array( $this, 'set_session_from_id' ), 1 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'maybe_set_wc_cart' ), 1 );
	}

	/**
	 * Sets the session id.
	 *
	 * @param  string|null $session_id The WooCommerce session id.
	 * @return string
	 */
	public function set_session_id( $session_id = null ) {
		if ( null === $session_id ) {
			foreach ( $_COOKIE as $key => $value ) { // phpcs: ignore.
				if ( strpos( $key, 'wp_woocommerce_session_' ) !== false ) {
					$session_id       = explode( '||', $value );
					$this->session_id = $session_id[0];
					return $this->session_id;
				}
			}
		} else {
			$this->session_id = $session_id;
		}
	}

	/**
	 * Gets the session_id.
	 *
	 * @return string
	 */
	public function get_session_id() {
		return $this->session_id;
	}

	/**
	 * Sets the session from the session id.
	 *
	 * @return void
	 */
	public function set_session_from_id() {
		if ( isset( $_GET['pco_session_id'] ) ) { // phpcs: ignore.
			$sessions_handler = new WC_Session_Handler();
			$session_data     = $sessions_handler->get_session( $_GET['pco_session_id'] );
			if ( ! empty( $session_data ) ) {
				WC()->session = $sessions_handler;
				foreach ( $session_data as $key => $value ) {
					WC()->session->set( $key, maybe_unserialize( $value ) );
				}
			}

			// Set customer country based on session data.
			$customer_data = maybe_unserialize( $session_data['customer'] );
			WC()->customer->set_billing_country( $customer_data['country'] );
			WC()->customer->set_shipping_country( $customer_data['shipping_country'] );
			WC()->customer->save();
		}
	}

	/**
	 * Maybe sets the cart from session.
	 *
	 * @param object $cart The WooCommerce cart object.
	 * @return void
	 */
	public function maybe_set_wc_cart( $cart ) {
		if ( isset( $_GET['pco_session_id'] ) ) { // phpcs: ignore.
			WC()->cart = $cart;
		}
	}
}
