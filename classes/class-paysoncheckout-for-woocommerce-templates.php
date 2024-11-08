<?php
/**
 * Template class file.
 *
 * @package PaysonCheckout/Classes
 */

/**
 * Templates class.
 */
class PaysonCheckout_For_WooCommerce_Templates {
	/**
	 * Checkout layout.
	 *
	 * @var string
	 */
	private $checkout_layout;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$payson_settings       = get_option( 'woocommerce_paysoncheckout_settings' );
		$this->checkout_layout = $payson_settings['checkout_layout'] ?? 'one_column_checkout';

		add_filter( 'wc_get_template', array( $this, 'override_template' ), 10, 2 );
		add_action( 'pco_wc_after_wrapper', array( $this, 'add_wc_form' ), 10 );
		add_action( 'pco_wc_after_order_review', array( $this, 'add_extra_checkout_fields' ), 10 );
		add_action( 'pco_wc_after_order_review', 'pco_wc_show_another_gateway_button', 20 );
		add_action( 'pco_wc_before_checkout_form', 'pco_maybe_show_validation_error_message', 5 );
		add_action( 'pco_wc_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		add_action( 'pco_wc_before_checkout_form', 'woocommerce_checkout_coupon_form', 20 );
		add_action( 'pco_wc_after_checkout_form', array( $this, 'pco_wc_after_checkout_form' ) );
		add_filter( 'body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Overrides checkout form template if PaysonCheckout is the selected payment method.
	 *
	 * @param string $template      Template.
	 * @param string $template_name Template name.
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( is_checkout() ) {
			// PaysonCheckout Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				// Don't display PCO template if we have a cart that doesn't needs payment.
				if ( ! WC()->cart->needs_payment() ) {
					return $template;
				}
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				if ( locate_template( 'woocommerce/paysoncheckout-checkout.php' ) ) {
					$paysoncheckout_template = locate_template( 'woocommerce/paysoncheckout-checkout.php' );
				} else {
					$paysoncheckout_template = PAYSONCHECKOUT_PATH . '/templates/paysoncheckout-checkout.php';
				}
				// Paysoncheckout checkout page.
				if ( array_key_exists( 'paysoncheckout', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'paysoncheckout' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! isset( $_GET['confirm'] ) ) {
							$template = $paysoncheckout_template;
						}
					}
					// If chosen payment method does not exist and PCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );
						if ( 'paysoncheckout' === key( $available_gateways ) ) {
							if ( ! isset( $_GET['confirm'] ) ) {
								$template = $paysoncheckout_template;
							}
						}
					}
					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );
							if ( 'paysoncheckout' === key( $available_gateways ) ) {
								if ( ! isset( $_GET['confirm'] ) ) {
									$template = $paysoncheckout_template;
								}
							}
						}
					}
				}
			}

			// When the customer changes subscription method to Payson Checkout, we'll redirect back to the same page, but with the added query parameter 'gateway'. If it is present, we'll override the the template, and display the Payson iframe instead.
			$is_change_payment_method = ( 'checkout/form-change-payment-method.php' === $template_name ) && isset( $_GET['gateway'] ) && 'paysoncheckout' === $_GET['gateway'];

			// PaysonCheckout Pay for order and change payment method.
			if ( 'checkout/form-pay.php' === $template_name || $is_change_payment_method ) {
				$order_id           = absint( get_query_var( 'order-pay', 0 ) );
				$order              = wc_get_order( $order_id );
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
				if ( array_key_exists( 'paysoncheckout', $available_gateways ) ) {
					$paysoncheckout_pay_template = locate_template( 'woocommerce/paysoncheckout-pay.php' );
					if ( empty($paysoncheckout_pay_template ) ) {
						$paysoncheckout_pay_template = PAYSONCHECKOUT_PATH . '/templates/paysoncheckout-pay.php';
					}

					// On the change-payment-method page, we do not change the payment method until the subscription has been confirmed as paid.
					// Therefore, we must check if the `gateway` query parameter is set, as get_payment_method will refer to the previous payment method.
					$gateway = filter_input( INPUT_GET, 'gateway', FILTER_SANITIZE_SPECIAL_CHARS );
					if ( 'paysoncheckout' === $order->get_payment_method() || 'paysoncheckout' === $gateway ) {
						if ( ! isset( $_GET['confirm'] ) ) {
							$template = $paysoncheckout_pay_template;
						}
					}

					// If chosen payment method does not exist and PCO is the first gateway.
					if ( empty( $order->get_payment_method() ) ) {
						reset( $available_gateways );
						if ( 'paysoncheckout' === key( $available_gateways ) ) {
							if ( ! isset( $_GET['confirm'] ) ) {
								$template = $paysoncheckout_pay_template;
							}
						}
					}
				}
			}
		}
		return $template;
	}

	/**
	 * Adds the WC form and other fields to the checkout page.
	 *
	 * @return void
	 */
	public function add_wc_form() {
		?>
		<div aria-hidden="true" id="pco-wc-form" style="position:absolute; top:0; left:-99999px;">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
			<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			<?php
			if ( isset( $_GET['pco_confirm'] ) ) {
				// On confirmation page - render woocommerce_checkout_payment() to get the woocommerce-process-checkout-nonce correct.
				woocommerce_checkout_payment();
			} else {
				// On regular PCO checkout page - use our own woocommerce-process-checkout-nonce (so we don't render the checkout form submit button).
				?>
				<div id="pco-nonce-wrapper">
					<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
				</div>
				<input id="payment_method_paysoncheckout" type="radio" class="input-radio" name="payment_method" value="paysoncheckout" checked="checked" />
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Adds the extra checkout field div to the checkout page.
	 *
	 * @return void
	 */
	public function add_extra_checkout_fields() {
		?>
		<div id="pco-extra-checkout-fields">
		</div>
		<?php
	}

	/**
	 * Trigger actions after the checkout form.
	 *
	 * @return void
	 */
	public function pco_wc_after_checkout_form() {
		do_action( 'woocommerce_after_checkout_form' );
	}

	/**
	 * Add checkout page body class, embedded only.
	 *
	 * @param array $class CSS classes used in body tag.
	 *
	 * @return array
	 */
	public function add_body_class( $class ) {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {

			// Don't display Payson body classes if we have a cart that doesn't need payment.
			if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
				return $class;
			}

			if ( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}

			if ( 'paysoncheckout' === $first_gateway && 'two_column_left' === $this->checkout_layout ) {
				$class[] = 'payson-checkout-selected';
				$class[] = 'payson-checkout-two-column-left';
			}

			if ( 'paysoncheckout' === $first_gateway && 'two_column_right' === $this->checkout_layout ) {
				$class[] = 'payson-checkout-selected';
				$class[] = 'payson-checkout-two-column-right';
			}

			if ( 'paysoncheckout' === $first_gateway && 'one_column_checkout' === $this->checkout_layout ) {
				$class[] = 'payson-checkout-selected';
			}
		}
		return $class;
	}
}
new PaysonCheckout_For_WooCommerce_Templates();
