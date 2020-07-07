<?php
/**
 * Confirmation class file.
 *
 * @package PaysonCheckout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Confirmation class.
 */
class PaysonCheckout_For_WooCommerce_Confirmation {

	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	protected static $instance;
	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'pco_wc_before_checkout_form', array( $this, 'maybe_populate_wc_checkout' ) );
	}

	/**
	 * Maybe populates the checkout fields.
	 *
	 * @return void
	 */
	public function maybe_populate_wc_checkout() {
		// Get the payson order.
		$payment_id   = WC()->session->get( 'payson_payment_id' );
		$payson_order = pco_wc_get_order( $payment_id );
		if ( is_wp_error( $payson_order ) ) {
			// If error print error message.
			$code    = $payson_order->get_error_code();
			$message = $payson_order->get_error_message();
			$text    = __( 'Payson API Error: ', 'payson-checkout-for-woocommerce' ) . '%s %s'
			?>
			<ul class="woocommerce-error" role="alert">
				<li><?php echo sprintf( $text, $code, $message ); ?></li>
			</ul>
			<?php
		} else {
			$address       = $payson_order['customer'];
			$customer_data = array();

			// First name.
			WC()->customer->set_billing_first_name( sanitize_text_field( $address['firstName'] ) );
			WC()->customer->set_shipping_first_name( sanitize_text_field( $address['firstName'] ) );
			// Last name.
			$payson_lastname = sanitize_text_field( $address['lastName'] );
			if ( 'business' === $payson_order['customer']['type'] ) {
				$payson_lastname = '-';
			}
			WC()->customer->set_billing_last_name( sanitize_text_field( $payson_lastname ) );
			WC()->customer->set_shipping_last_name( sanitize_text_field( $payson_lastname ) );
			// Country.
			WC()->customer->set_billing_country( strtoupper( sanitize_text_field( $address['countryCode'] ) ) );
			WC()->customer->set_shipping_country( strtoupper( sanitize_text_field( $address['countryCode'] ) ) );
			// Street address.
			WC()->customer->set_billing_address_1( sanitize_text_field( $address['street'] ) );
			WC()->customer->set_shipping_address_1( sanitize_text_field( $address['street'] ) );
			// City.
			WC()->customer->set_billing_city( sanitize_text_field( $address['city'] ) );
			WC()->customer->set_shipping_city( sanitize_text_field( $address['city'] ) );
			// Postcode.
			WC()->customer->set_billing_postcode( sanitize_text_field( $address['postalCode'] ) );
			WC()->customer->set_shipping_postcode( sanitize_text_field( $address['postalCode'] ) );
			// Phone.
			WC()->customer->set_billing_phone( sanitize_text_field( $address['phone'] ) );
			// Email.
			WC()->customer->set_billing_email( sanitize_text_field( $address['email'] ) );

			// Save customer.
			WC()->customer->save();
		}
	}
}
PaysonCheckout_For_WooCommerce_Confirmation::get_instance();
