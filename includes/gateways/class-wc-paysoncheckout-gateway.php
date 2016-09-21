<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'init_wc_gateway_paysoncheckout_class' );
add_filter( 'woocommerce_payment_gateways', 'add_paysoncheckout_method' );

/**
 * Initialize PaysonCheckout payment gateway
 *
 * @wp_hook plugins_loaded
 */
function init_wc_gateway_paysoncheckout_class() {
	/**
	 * PaysonCheckout 2.0 Payment Gateway.
	 *
	 * Provides PaysonCheckout 2.0 Payment Gateway for WooCommerce.
	 *
	 * @class       WC_Gateway_PaysonCheckout
	 * @extends     WC_Payment_Gateway
	 * @version     0.1
	 * @author      Krokedil
	 */
	class WC_Gateway_PaysonCheckout extends WC_Payment_Gateway {
		
		/** @var WC_Logger Logger instance */
		public static $log = false;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			$this->id                 = 'paysoncheckout';
			$this->method_title       = __( 'Payson', 'woocommerce-gateway-paysoncheckout' );
			$this->icon               = '';
			$this->has_fields         = true;
			$this->method_description = __( 'Allows payments through ' . $this->method_title . '.', 'woocommerce-gateway-paysoncheckout' );
			
			// Load the form fields.
			$this->init_form_fields();
	
			// Load the settings.
			$this->init_settings();
		
			// Define user set variables
			$this->enabled			= ( isset( $this->settings['enabled'] ) ) ? $this->settings['enabled'] : '';
			$this->title			= ( isset( $this->settings['title'] ) ) ? $this->settings['title'] : '';
			$this->description		= ( isset( $this->settings['description'] ) ) ? $this->settings['description'] : '';
			$this->merchant_id		= ( isset( $this->settings['merchant_id'] ) ) ? $this->settings['merchant_id'] : '';
			$this->api_key			= ( isset( $this->settings['api_key'] ) ) ? $this->settings['api_key'] : '';
			$this->color_scheme		= ( isset( $this->settings['color_scheme'] ) ) ? $this->settings['color_scheme'] : '';
			$this->request_phone	= ( isset( $this->settings['request_phone'] ) ) ? $this->settings['request_phone'] : '';
			$this->debug			= ( isset( $this->settings['debug'] ) ) ? $this->settings['debug'] : '';
			$this->order_management	= ( isset( $this->settings['order_management'] ) ) ? $this->settings['order_management'] : '';
			$this->mobile_threshold	= ( isset( $this->settings['mobile_threshold'] ) ) ? $this->settings['mobile_threshold'] : '';
			
			// Supports
			$this->supports = array(
				'products'
			);

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
			
			// Scripts
			add_action( 'wp_footer', array( $this, 'print_checkout_script' ) );
			add_action( 'wp_head', array( $this, 'add_inline_style' ) );
			
			// Thankyou page
			add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'payson_thankyou_order_received_text' ), 10, 2 );
			add_action( 'woocommerce_thankyou_paysoncheckout', array( $this, 'payson_thankyou' ) );

		}
		
		
		/**
		 * Logging method.
		 *
		 * @param string $message
		 */
		public static function log( $message ) {
			$afterpay_settings = get_option( 'woocommerce_paysoncheckout_settings' );
			if ( $afterpay_settings['debug'] == 'yes' ) {
				if ( empty( self::$log ) ) {
					self::$log = new WC_Logger();
				}
				self::$log->add( 'paysoncheckout', $message );
			}
		}
		
		
		/**
		* Check if this gateway is enabled and available in the user's country
		*/
		function is_available() {
			global $woocommerce;
			
			if ($this->enabled=="yes") :
				
				if ( ! is_admin() ) {
					
					// Currency check
					if (!in_array(get_woocommerce_currency(), array('EUR', 'SEK'))) return false;
					
					// Country check
					//if (!in_array(WC()->customer->get_country(), array('FI', 'SE'))) return false;
					
					// Required fields check
					if( !$this->merchant_id || !$this->api_key ) return false;
				
				}
				
				return true;
						
			endif;	
		
			return false;
		}
		
		
		/**
		 * Remove thankyou page order received text if PaysonCheckout is the selected payment method.
		 */
		public function payson_thankyou_order_received_text( $text, $order ) {
			
			if( 'paysoncheckout' == WC()->session->get( 'chosen_payment_method' ) ) {
				$text = '';
			}
			
			return $text;
		}
		
		
		/**
		 * Add PaysonCheckout iframe to thankyou page.
		 */
		public function payson_thankyou() {
			
			if( $_GET['paysonorder'] ) {
				
				remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );

				include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
				$payson_api 	= new WC_PaysonCheckout_Setup_Payson_API();
				$checkout 		= $payson_api->get_notification_checkout(  $_GET['paysonorder'] );
				WC_Gateway_PaysonCheckout::log( 'Posted checkout info in thankyou page: ' . var_export( $checkout, true ) );
				
				/*echo '<pre>';
				var_dump($checkout->snippet);
				echo '</pre>';
				*/
				echo '<div class="paysonceckout-container" style="width:100%;  margin-left:auto; margin-right:auto;">';
			    echo $checkout->snippet; 
				echo "</div>";
				WC()->session->__unset( 'payson_checkout_id' );
				WC()->session->__unset( 'ongoing_payson_order' );
				
			}
		}
		
		
		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce-gateway-paysoncheckout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable ' . $this->method_title, 'woocommerce-gateway-paysoncheckout' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => __( $this->method_title, 'woocommerce-gateway-paysoncheckout' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-paysoncheckout' ),
				),
				'merchant_id' => array(
					'title'       => __( 'Merchant ID', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'description' => __( '', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => '',
				),
				'api_key' => array(
					'title'       => __( 'API Key', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'description' => __( '', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => '',
				),
				'testmode' => array(
					'title'       => __( 'Testmode', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable PaysonCheckout testmode', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => 'no',
				),
				'order_management' => array(
					'title'   => __( 'Enable Order Management', 'woocommerce-gateway-paysoncheckout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Payson order capture on WooCommerce order completion and Payson order cancellation on WooCommerce order cancellation', 'woocommerce-gateway-paysoncheckout' ),
					'default' => 'yes'
				),
				'color_scheme'		=> array(
					'title'       => __( 'Color Scheme', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'select',
					'options'     => array(
						'Gray' => __( 'Gray', 'woocommerce-gateway-paysoncheckout' ),
						'Blue' => __( 'Blue', 'woocommerce-gateway-paysoncheckout' ),
						'White' => __( 'White', 'woocommerce-gateway-paysoncheckout' ),
						'GrayTextLogos' => __( 'GrayTextLogos', 'woocommerce-gateway-paysoncheckout' ),
						'BlueTextLogos' => __( 'BlueTextLogos', 'woocommerce-gateway-paysoncheckout' ),
						'WhiteTextLogos' => __( 'WhiteTextLogos', 'woocommerce-gateway-paysoncheckout' )
					),
					'description' => __( 'Different color schemes for how the embedded PaysonCheckout iframe should be displayed.', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => 'gray',
					'desc_tip'    => true
				),
				'request_phone' => array(
					'title'   => __( 'Request phone', 'woocommerce-gateway-paysoncheckout' ),
					'type'    => 'checkbox',
					'label'   => __( 'Check this box to require the customer to fill in his phone number.', 'woocommerce-gateway-paysoncheckout' ),
					'default' => 'no'
				),
				'mobile_threshold' => array(
					'title'       => __( 'Mobile threshold', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'text',
					'description' => __( 'If your theme has a two column checkout layout; specify the width in px (but without the actual px, e.g. 767) where the checkout layout alters to a one column layout. Leave blank to disable this feature. Storefront and one column checkout layouts will not require this setting.', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => '',
				),
				'debug' => array(
					'title'       => __( 'Debug Log', 'woocommerce-gateway-paysoncheckout' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce-gateway-paysoncheckout' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log ' . $this->method_title . ' events in <code>%s</code>', 'woocommerce-gateway-paysoncheckout' ), wc_get_log_file_path( 'paysoncheckout' ) )
				),
			);
		}
		
	
	
		/**
		 * Javascript for testing visibility of checkout forms
		 *
		 **/
		function print_checkout_script() {
			global $woocommerce;
			
			// Get the theme
			$theme = wp_get_theme();
			if ('Flatsome' == $theme->name || 'Flatsome' == $theme->parent_theme) {
			    $current_theme = 'Flatsome';
			} else {
				$current_theme = 'somethingelse';
			}
			
			// Get mobile threshold
			$mobile_threshold = $this->mobile_threshold;
			
			if ( ( is_checkout() || defined( 'WOOCOMMERCE_CHECKOUT' ) && 'yes' == $this->enabled ) ) {
				?>
				<script type="text/javascript">
	
					// Document ready
					jQuery(document).ready(function ($) {
						
						// Set variable to declare that the checkout isn't initialized yet. To avoid that the checkout iframe doesn't update directly on page load.
						var wc_payson_initialized = false;
						
						// Check if we need to move the payson iframe on page load (desktop layout for 2 column layouts)
						maybe_move_payson_iframe();
						
						// Check if we need to move the payson iframe after page resize
						
						/* Store the window width */
					    var windowWidth = $(window).width();
					
					    /* Resize Event */
					    $(window).resize(function() {
					        // Check window width has actually changed and it's not just iOS triggering a resize event on scroll
					        if ($(window).width() != windowWidth) {
					
					            // Update the window width for next time
					            windowWidth = $(window).width();
					
					            // Maybe move the iframe
					            var id;
					            clearTimeout(id);
								id = setTimeout(maybe_move_payson_iframe, 500);
					
					        }
					    });
					    
					    
						// Check if Payson payment method is selected
						var selected_payment_method = jQuery('input[name=payment_method]:checked').val();
						//console.log( selected_payment_method );
						
						// Hide/show shipping and billing form depending on the selecter payment gateway
						if ( selected_payment_method == 'paysoncheckout') {
							/*jQuery('.woocommerce-billing-fields').css('visibility', 'hidden');
							jQuery('.woocommerce-shipping-fields').css('visibility', 'hidden');
							jQuery('.place-order').hide();
							jQuery('#customer_details_payson').css('visibility', 'visible');
							*/
							jQuery('.woocommerce-billing-fields').hide();
							jQuery('.woocommerce-shipping-fields').hide();
							jQuery('.place-order').hide();
							jQuery('#customer_details_payson').show();
						} else {
							/*
							jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
							jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
							jQuery('.place-order').show();
							jQuery('#customer_details_payson').css('visibility', 'hidden');
							*/
							jQuery('.woocommerce-billing-fields').show();
							jQuery('.woocommerce-shipping-fields').show();
							jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
							jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
							jQuery('.place-order').show();
							jQuery('#customer_details_payson').hide();
						}
						
						setInterval(function() {
							// Hide/show shipping and billing form depending on the selecter payment gateway
							if ( selected_payment_method == 'paysoncheckout') {
								/*jQuery('.woocommerce-billing-fields').css('visibility', 'hidden');
								 jQuery('.woocommerce-shipping-fields').css('visibility', 'hidden');
								 jQuery('.place-order').hide();
								 jQuery('#customer_details_payson').css('visibility', 'visible');
								 */
								jQuery('.woocommerce-billing-fields').hide();
								jQuery('.woocommerce-shipping-fields').hide();
								jQuery('.place-order').hide();
								jQuery('#customer_details_payson').show();
							} else {
								/*
								 jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
								 jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
								 jQuery('.place-order').show();
								 jQuery('#customer_details_payson').css('visibility', 'hidden');
								 */
								jQuery('.woocommerce-billing-fields').show();
								jQuery('.woocommerce-shipping-fields').show();
								jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
								jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
								jQuery('.place-order').show();
								jQuery('#customer_details_payson').hide();
							}
						}, 1000);
	
	
						// On switch of payment method radiobuttons
						$(document.body).on('change', 'input[name="payment_method"]', function () {
	
							var selected_payment_method = jQuery('input[name=payment_method]:checked').val();
							console.log( selected_payment_method );
						
							if ( selected_payment_method == 'paysoncheckout') {
								/*
								jQuery('.woocommerce-billing-fields').css('visibility', 'hidden');
								jQuery('.woocommerce-shipping-fields').css('visibility', 'hidden');
								jQuery('.place-order').hide();
								jQuery('#customer_details_payson').css('visibility', 'visible');
								*/
								jQuery('.woocommerce-billing-fields').hide();
								jQuery('.woocommerce-shipping-fields').hide();
								jQuery('.place-order').hide();
								jQuery('#customer_details_payson').show();
							} else {
								/*
								jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
								jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
								jQuery('.place-order').show();
								jQuery('#customer_details_payson').css('visibility', 'hidden');
								*/
								jQuery('.woocommerce-billing-fields').show();
								jQuery('.woocommerce-shipping-fields').show();
								jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
								jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
								jQuery('.place-order').show();
								jQuery('#customer_details_payson').hide();
							}
	
	
						});
						
						jQuery(document).ajaxComplete(function () {
							var selected_payment_method = jQuery('input[name=payment_method]:checked').val();
							
							if ( selected_payment_method == 'paysoncheckout') {
								/*jQuery('.woocommerce-billing-fields').css('visibility', 'hidden');
								jQuery('.woocommerce-shipping-fields').css('visibility', 'hidden');
								jQuery('.place-order').hide();
								jQuery('#customer_details_payson').css('visibility', 'visible');
								*/
								jQuery('.woocommerce-billing-fields').hide();
								jQuery('.woocommerce-shipping-fields').hide();
								jQuery('.place-order').hide();
								jQuery('#customer_details_payson').show();
								
							} else {
								/*jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
								jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
								jQuery('.place-order').show();
								jQuery('#customer_details_payson').css('visibility', 'hidden');
								*/
								jQuery('.woocommerce-billing-fields').show();
								jQuery('.woocommerce-shipping-fields').show();
								jQuery('.woocommerce-billing-fields').css('visibility', 'visible');
								jQuery('.woocommerce-shipping-fields').css('visibility', 'visible');
								jQuery('.place-order').show();
								jQuery('#customer_details_payson').hide();
								
							}

						});
						
												
						// Update iframe
						jQuery('body').on('updated_checkout', function($) {
							//console.log( wc_payson_initialized );
						    // code
						    if(!wc_payson_initialized) {
							    wc_payson_initialized = true;
							} else {
								sendPaysonUpdate();
							}
						});
						
						// Address update callback
						$(document).on('PaysonEmbeddedAddressChanged', function(data) {
							$.ajax(
								'<?php echo get_site_url() . '/wp-admin/admin-ajax.php' ?>',
								{
									type: 'POST',
									dataType: 'json',
									data: {
										action		: 'payson_address_changed_callback',
										address		: data.detail,
										_wpnonce	: '<?php echo wp_create_nonce('payson_nonce'); ?>',
									},
									success: function(response) {
										console.log('Address update AJAX sucess');
										//sendPaysonUpdate();
										//console.log(response);
									},
									error: function(response) {
										console.log('Address update AJAX error');
										//console.log(response);
									},
									complete: function() {
										console.log('Address update AJAX complete');
									}
								}
							);
						});
						
					});
					
					// Function for updating the Payson iframe
					function sendPaysonUpdate() {
				        var iframe = document.getElementById('paysonIframe');
				        iframe.contentWindow.postMessage('updatePage', '*');
				        console.log( 'sendUpdate' );
				    }
				    
				    
				    // Function for moving the Payson Checkout iframe. 
				    function maybe_move_payson_iframe() {
					    
					    // Declare the theme
						var current_theme = '<?php echo $current_theme; ?>';
						
						// Declare mobile threshold
						var mobile_threshold = '<?php echo $mobile_threshold; ?>';
						
						// Only run this if a mobile threshold is set in settings
						if( mobile_threshold ) {
						    var $iW = jQuery(window).width();
							if ($iW > mobile_threshold){
								// Move the payson iframe to billing/shipping area if window is larger than the mobile threshold
								if ('Flatsome' == current_theme ) {
									jQuery('#customer_details_payson').appendTo(jQuery('.large-7.columns')); // Flatsome
								} else {
									jQuery('#customer_details_payson').insertBefore('#customer_details'); // Themes with WooCommerce standard html markup
								}
							} else {
								// Move payson iframe to below the order review box if the window is smaller than the mobile threshold
								jQuery('#customer_details_payson').appendTo('#order_review');
							}
						}
					}
	
				</script>
				<?php
			} // End if is_checkout()
		}
		
		/**
		 * Add inline css to hide WooCommerce billing & shipping fields on page load if needed
		 * Todo: Implement this in a better way
		 *
		 **/
		public function add_inline_style() {
			if ( is_checkout() && 'yes' == $this->enabled ) {
				$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
				
				if( ( 1 == count($available_gateways) && 'paysoncheckout' == key($available_gateways) ) || 'paysoncheckout' == WC()->session->chosen_payment_method ) {
					echo '<style>.woocommerce-billing-fields, .woocommerce-shipping-fields {visibility:hidden}; .place-order {display:none};</style>';
				}
			}
		}
		
	}
}

/**
 * Add PaysonCheckout 2.0 payment gateway
 *
 * @wp_hook woocommerce_payment_gateways
 *
 * @param  $methods Array All registered payment methods
 *
 * @return $methods Array All registered payment methods
 */
function add_paysoncheckout_method( $methods ) {
	$methods[] = 'WC_Gateway_PaysonCheckout';

	return $methods;
}
