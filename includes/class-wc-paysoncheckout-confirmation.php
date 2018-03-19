<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_PaysonCheckout_Confirmation class.
 *
 * Handles Payson Checkout confirmation page.
 */
class WC_PaysonCheckout_Confirmation {

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
	 * WC_PaysonCheckout_Confirmation constructor.
	 */
	public function __construct() {
		//add_action( 'wp_head', array( $this, 'maybe_hide_checkout_form' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'maybe_populate_wc_checkout' ) );
		add_action( 'wp_footer', array( $this, 'maybe_submit_wc_checkout' ), 999 );
		add_filter( 'the_title', array( $this, 'confirm_page_title' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'unrequire_fields' ), 99 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'unrequire_posted_data' ), 99 );
		//add_action( 'woocommerce_checkout_after_order_review', array( $this, 'add_kco_order_id_field' ) );
		//add_action( 'woocommerce_checkout_create_order', array( $this, 'save_kco_order_id_field' ), 10, 2 );
	}

	/**
	 * Filter Checkout page title in confirmation page.
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function confirm_page_title( $title ) {
		if ( ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_checkout() && isset( $_GET['payson_payment_successful'] ) && '1' === $_GET['payson_payment_successful'] ) {
			$title = __( 'Please wait while we process your order.', 'klarna-checkout-for-woocommerce' );
			remove_filter( 'the_title', array( $this, 'confirm_page_title' ) );
		}

		return $title;
	}

	/**
	 * Hides WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_hide_checkout_form() {
		if ( ! $this->is_paysoncheckout_confirmation() ) {
			return;
		}

		echo '<style>form.woocommerce-checkout,div.woocommerce-info{display:none!important}</style>';
	}

	/**
	 * Populates WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_populate_wc_checkout( $checkout ) {
		if ( ! $this->is_paysoncheckout_confirmation() ) {
			return;
		}

        echo '<div id="kco-confirm-loading"></div>';
        
        $payson_checkout_id = WC()->session->get( 'payson_checkout_id' );
        
		WC_Gateway_PaysonCheckout::log( '$payson_checkout_id: ' . stripslashes_deep( json_encode( $payson_checkout_id ) ) );
		include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
		$payson_api = new WC_PaysonCheckout_Setup_Payson_API();
		$payson_order   = $payson_api->get_notification_checkout( $payson_checkout_id );

        var_dump($payson_order);
		$this->save_customer_data( $payson_order );
	}

	/**
	 * Submits WooCommerce checkout form in KCO confirmation page.
	 */
	public function maybe_submit_wc_checkout() {
		if ( ! $this->is_paysoncheckout_confirmation() ) {
			return;
		}
		?>

		<script>
			jQuery(function ($) {
                console.log('Starting woocommerce checkout processing...');
				$('input#terms').prop('checked', true);

				// If order value = 0, payment method fields will not be in the page, so we need to
				if (!$('input#payment_method_paysoncheckout').length) {
					$('#order_review').append('<input id="payment_method_paysoncheckout" type="radio" class="input-radio" name="payment_method" value="paysoncheckout" checked="checked" />');
				}

                $('input#payment_method_paysoncheckout').prop('checked', true);
                
                

				<?php
				$extra_field_values = WC()->session->get( 'kco_wc_extra_fields_values', array() );

				foreach ( $extra_field_values as $field_name => $field_value ) { ?>

				var elementName = "<?php echo $field_name; ?>";
				var elementValue = <?php echo wp_json_encode( $field_value ); ?>;
				var element = $('*[name="' + elementName + '"]');

				console.log(elementName);
				console.log(elementValue);
				console.log(element);
				console.log(element.type);

				if (element.length) {
					if (element.is('select')) { // Select.
						var selectedOption = element.find('option[value="' + elementValue + '"]');
						selectedOption.prop('selected', true);
					} else if ('radio' === element.get(0).type) { // Radio.
						var checkedRadio = $('*[name="' + elementName + '"][value="' + elementValue + '"]');
						checkedRadio.prop('checked', true);
					} else if ('checkbox' === element.get(0).type) { // Checkbox.
						if (elementValue) {
							element.prop('checked', true);
						}
					} else { // Text and textarea.
						element.val(elementValue);
					}
				}

				<?php
				}
				//do_action( 'kco_wc_before_submit' );
				?>
                console.log('Sbubmitting form...');
				$('.validate-required').removeClass('validate-required');
				$('form.woocommerce-checkout').submit();
			});
		</script>
		<?php
	}

	/**
	 * Checks if in KCO confirmation page.
	 *
	 * @return bool
	 */
	private function is_paysoncheckout_confirmation() {
		if ( isset( $_GET['payson_payment_successful'] ) && '1' === $_GET['payson_payment_successful'] && isset( $_GET['paysonorder'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Saves customer data from Klarna order into WC()->customer.
	 *
	 * @param $klarna_order
	 */
	private function save_customer_data( $payson_order ) {
		// First name.
		WC()->customer->set_billing_first_name( sanitize_text_field( $payson_order->billingFirstName ) );
		WC()->customer->set_shipping_first_name( sanitize_text_field( $payson_order->shippingFirstName ) );

		// Last name.
		WC()->customer->set_billing_last_name( sanitize_text_field( $payson_order->billingLastName ) );
		WC()->customer->set_shipping_last_name( sanitize_text_field( $payson_order->shippingLastName ) );

		// Country.
		WC()->customer->set_billing_country( strtoupper( sanitize_text_field( $payson_order->billingCounry ) ) );
		WC()->customer->set_shipping_country( strtoupper( sanitize_text_field( $payson_order->shippingCounry ) ) );

		// Street address 1.
		WC()->customer->set_billing_address_1( sanitize_text_field( $payson_order->billingAddress ) );
		WC()->customer->set_shipping_address_1( sanitize_text_field( $payson_order->shippingAddress ) );

        // Street address 2.
        /*
		if ( isset( $payson_order->billing_address->street_address2 ) ) {
			WC()->customer->set_billing_address_2( sanitize_text_field( $payson_order->billing_address->street_address2 ) );
			WC()->customer->set_shipping_address_2( sanitize_text_field( $payson_order->shipping_address->street_address2 ) );
		}
        */
		// City.
		WC()->customer->set_billing_city( sanitize_text_field( $payson_order->billingCity ) );
		WC()->customer->set_shipping_city( sanitize_text_field( $payson_order->shippingCity ) );

        // County/State.
        /*
		WC()->customer->set_billing_state( sanitize_text_field( $payson_order->billing_address->region ) );
		WC()->customer->set_shipping_state( sanitize_text_field( $payson_order->shipping_address->region ) );
        */
		// Postcode.
		WC()->customer->set_billing_postcode( sanitize_text_field( $payson_order->billingPostalCode ) );
		WC()->customer->set_shipping_postcode( sanitize_text_field( $payson_order->shippingPostalCode ) );

		// Phone.
		WC()->customer->set_billing_phone( sanitize_text_field( $payson_order->phone ) );

		// Email.
		WC()->customer->set_billing_email( sanitize_text_field( $payson_order->email ) );

        WC()->customer->save();
        ?>
        <script>
			jQuery(function ($) {
                var customer_data = <?php echo json_encode($payson_order); ?>;
                $("form.checkout #billing_first_name").val(customer_data.billingFirstName);
                $("form.checkout #billing_last_name").val(customer_data.billingLastName);
                $("form.checkout #billing_email").val(customer_data.email);
                $("form.checkout #billing_country").val(customer_data.billingCounry);
                $("form.checkout #billing_address_1").val(customer_data.billingAddress);
                $("form.checkout #billing_city").val(customer_data.billingCity);
                $("form.checkout #billing_postcode").val(customer_data.billingPostalCode);
                $("form.checkout #billing_phone").val(customer_data.phone);
                $("form.checkout #shipping_first_name").val(customer_data.billingFirstName);
                $("form.checkout #shipping_last_name").val(customer_data.billingLastName);
                $("form.checkout #shipping_country").val(customer_data.billingCounry);
                $("form.checkout #shipping_address_1").val(customer_data.billingAddress);
                $("form.checkout #shipping_city").val(customer_data.billingCity);
                $("form.checkout #shipping_postcode").val(customer_data.billingPostalCode);
            });
        </script>
        <?php
	}

	/**
	 * When checking out using KCO, we need to make sure none of the WooCommerce are required, in case Klarna
	 * does not return info for some of them.
	 *
	 * @param array $fields WooCommerce checkout fields.
	 *
	 * @return mixed
	 */
	public function unrequire_fields( $fields ) {
		if ( 'paysoncheckout' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $fields as $fieldset_key => $fieldset ) {
				foreach ( $fieldset as $key => $field ) {
					$fields[ $fieldset_key ][ $key ]['required']        = '';
					$fields[ $fieldset_key ][ $key ]['wooccm_required'] = '';
				}
			}
		}

		return $fields;
	}

	/**
	 * Makes sure there's no empty data sent for validation.
	 *
	 * @param array $data Posted data.
	 *
	 * @return mixed
	 */
	public function unrequire_posted_data( $data ) {
		if ( 'paysoncheckout' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $data as $key => $value ) {
				if ( '' === $value ) {
					unset( $data[ $key ] );
				}
			}
		}

		return $data;
	}


	/**
	 * Adds hidden field to WooCommerce checkout form, holding Klarna Checkout order ID.
	 */
	public function add_kco_order_id_field() {
		if ( 'kco' === WC()->session->get( 'chosen_payment_method' ) && isset( $_GET['confirm'] ) && 'yes' === $_GET['confirm'] ) {
			if ( isset( $_GET['kco_wc_order_id'] ) ) { // Input var okay.
				$klarna_order_id = esc_attr( sanitize_text_field( $_GET['kco_wc_order_id'] ) );
				echo '<input type="hidden" id="kco_order_id" name="kco_order_id" value="' . $klarna_order_id . '" />';
			}
		}
	}

	/**
	 * Saves KCO order ID to WooCommerce order as meta field.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @param array    $data  Posted data.
	 */
	public function save_kco_order_id_field( $order, $data ) {
		if ( isset( $_POST['kco_order_id'] ) ) {
			$kco_order_id = sanitize_text_field( $_POST['kco_order_id'] );

			update_post_meta( $order->get_id(), '_wc_klarna_order_id', sanitize_key( $kco_order_id ) );
			update_post_meta( $order->get_id(), '_transaction_id', sanitize_key( $kco_order_id ) );
		}
	}

}

WC_PaysonCheckout_Confirmation::get_instance();