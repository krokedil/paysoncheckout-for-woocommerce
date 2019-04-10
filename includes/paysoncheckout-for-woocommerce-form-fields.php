<?php
/**
 * Settings form fields for the gateway.
 *
 * @package PaysonCheckout/Includes
 */

$settings = array(
	'enabled'                    => array(
		'title'   => __( 'Enable/Disable', 'woocommerce-gateway-paysoncheckout' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable ' . $this->method_title, 'woocommerce-gateway-paysoncheckout' ), // phpcs:ignore
		'default' => 'yes',
	),
	'title'                      => array(
		'title'       => __( 'Title', 'woocommerce-gateway-paysoncheckout' ),
		'type'        => 'text',
		'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paysoncheckout' ),
		'default'     => __( $this->method_title, 'woocommerce-gateway-paysoncheckout' ), // phpcs:ignore
		'desc_tip'    => true,
	),
	'description'                => array(
		'title'       => __( 'Description', 'woocommerce-gateway-paysoncheckout' ),
		'type'        => 'textarea',
		'default'     => __( 'Pay with Payson via invoice, card, direct bank payments, part payment and sms.', 'woocommerce-gateway-paysoncheckout' ),
		'desc_tip'    => true,
		'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paysoncheckout' ),
	),
	'select_another_method_text' => array(
		'title'       => __( 'Other payment method button text', 'woocommerce-gateway-paysoncheckout' ),
		'type'        => 'text',
		'description' => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than PaysonCheckout. Leave blank to use the default (and translatable) text.', 'woocommerce-gateway-paysoncheckout' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'merchant_id'                => array(
		'title'       => __( 'Agent ID', 'woocommerce-gateway-paysoncheckout' ),
		'type'        => 'text',
		'description' => __( '', 'woocommerce-gateway-paysoncheckout' ), // phpcs:ignore
		'default'     => '',
	),
	'api_key'                    => array(
		'title'       => __( 'API Key', 'woocommerce-gateway-paysoncheckout' ),
		'type'        => 'text',
		'description' => __( '', 'woocommerce-gateway-paysoncheckout' ), // phpcs:ignore
		'default'     => '',
	),
	'testmode'                   => array(
		'title'   => __( 'Testmode', 'woocommerce-gateway-paysoncheckout' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable PaysonCheckout testmode', 'woocommerce-gateway-paysoncheckout' ),
		'default' => 'no',
	),
	'order_management'           => array(
		'title'   => __( 'Enable Order Management', 'woocommerce-gateway-paysoncheckout' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable Payson order capture on WooCommerce order completion and Payson order cancellation on WooCommerce order cancellation', 'woocommerce-gateway-paysoncheckout' ),
		'default' => 'yes',
	),
	'color_scheme'               => array(
		'title'       => __( 'Color Scheme', 'woocommerce-gateway-paysoncheckout' ),
		'type'        => 'select',
		'options'     => array(
			'Gray'  => __( 'Gray', 'woocommerce-gateway-paysoncheckout' ),
			'White' => __( 'White', 'woocommerce-gateway-paysoncheckout' ),
		),
		'description' => __( 'Different color schemes for how the embedded PaysonCheckout iframe should be displayed.', 'woocommerce-gateway-paysoncheckout' ),
		'default'     => 'White',
		'desc_tip'    => true,
	),
	'debug'                      => array(
		'title'       => __( 'Debug Log', 'woocommerce-gateway-paysoncheckout' ),
		'type'        => 'checkbox',
		'label'       => __( 'Enable logging', 'woocommerce-gateway-paysoncheckout' ),
		'default'     => 'no',
		'description' => sprintf( __( 'Log ' . $this->method_title . ' events in <code>%s</code>', 'woocommerce-gateway-paysoncheckout' ), wc_get_log_file_path( 'paysoncheckout' ) ), // phpcs:ignore
	),
);

return apply_filters( 'paysoncheckout_settings', $settings );
