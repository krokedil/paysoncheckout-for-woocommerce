<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * WC_PaysonCheckout_Templates class.
 */
class WC_PaysonCheckout_Templates {

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
	 * Plugin actions.
	 */
	public function __construct() {
		// Override template if Payson Checkout page.
		add_filter( 'woocommerce_locate_template', array( $this, 'override_template' ), 999, 3 );

		// Template hooks.
		add_action( 'wc_payson_before_checkout_form', 'wc_payson_calculate_totals', 1 );
		add_action( 'wc_payson_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		add_action( 'wc_payson_before_checkout_form', 'woocommerce_checkout_coupon_form', 20 );
		add_action( 'wc_payson_after_order_review', 'wc_payson_show_extra_fields', 10 );
		add_action( 'wc_payson_after_order_review', 'wc_payson_show_another_gateway_button', 20 );
		add_action( 'wc_payson_after_snippet', 'wc_payson_show_payment_method_field', 10 );
	}

	/**
	 * Override checkout form template if Klarna Checkout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @param string $template_path Template path.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name, $template_path ) {
		if ( is_checkout() ) {
			// Payson Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/payson-checkout.php' ) ) {
					$payson_checkout_template = locate_template( 'woocommerce/payson-checkout.php' );
				} else {
					$payson_checkout_template = PAYSONCHECKOUT_PATH . '/templates/payson-checkout.php';
				}

				// Payson checkout page.
				if ( array_key_exists( 'paysoncheckout', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'paysoncheckout' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! isset( $_GET['payson_payment_successful'] ) ) {
							$template = $payson_checkout_template;
						}
					}

					// If chosen payment method does not exist and KCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'paysoncheckout' === key( $available_gateways ) ) {
							if ( ! isset( $_GET['payson_payment_successful'] ) ) {
								$template = $payson_checkout_template;
							}
						}
					}

					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );

							if ( 'paysoncheckout' === key( $available_gateways ) ) {
								if ( ! isset( $_GET['payson_payment_successful'] ) ) {
									$template =  $payson_checkout_template;
								}
							}
						}
					}
				}
			}
		}

		return $template;
	}

}

WC_PaysonCheckout_Templates::get_instance();