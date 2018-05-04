<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Echoes Klarna Checkout iframe snippet.
 */
function wc_payson_show_snippet() {
    if( isset( $_GET['payson_payment_successful'] ) && '1' == $_GET['payson_payment_successful'] ) {
		return;
	}

	// Check if this is an pay for order or a regular purchase
	if ( isset( $_GET['pay_for_order'], $_GET['key'] ) ) {
		$order_id = wc_get_order_id_by_order_key( sanitize_text_field( $_GET['key'] ) );
		if( $order_id ) {
			WC()->session->set( 'ongoing_payson_order', $order_id );
			$order = wc_get_order( $order_id );
			$order->set_payment_method( 'paysoncheckout' );
			$order->save();
		}
	} else {
		$wc_order = new WC_PaysonCheckout_WC_Order();
		$order_id = $wc_order->update_or_create_local_order();
	}
    
    include_once( PAYSONCHECKOUT_PATH . '/includes/class-wc-paysoncheckout-setup-payson-api.php' );
    $payson_api = new WC_PaysonCheckout_Setup_Payson_API();
	$checkout   = $payson_api->get_checkout( $order_id );
	
    $iframe = '<div class="paysoncheckout-container" style="width:100%;  margin-left:auto; margin-right:auto;">';
    if ( is_wp_error( $checkout ) ) {
		$iframe =  '<ul class="woocommerce-error"><li>' . sprintf( '%s <a href="%s" class="button wc-forward">%s</a>', __( 'There was a problem in the communication with Payson.', 'woocommerce-gateway-paysoncheckout' ), wc_get_checkout_url(), __( 'Try again', 'woocommerce-gateway-paysoncheckout' ) ) . '</li></ul>';
    } else {
		echo("<script>console.log('Payson Checkout ID: ".json_encode($checkout->id)."');</script>");
        $iframe .= $checkout->snippet;
    }
    $iframe .= '</div>';

    echo $iframe;
}


/**
 * Shows extra fields in Payson Checkout page.
 */
function wc_payson_show_extra_fields() {

	echo '<div id="paysoncheckout-extra-fields">';
	
	// Order note
	//do_action( 'woocommerce_before_order_notes', WC()->checkout() );
	if ( apply_filters( 'woocommerce_enable_order_notes_field', true ) ) {
		$form_field = WC()->checkout()->get_checkout_fields( 'order' );
		woocommerce_form_field( 'order_comments', $form_field['order_comments'] );
	}
	//do_action( 'woocommerce_after_order_notes', WC()->checkout() );

	echo '</div>';
}

/**
 * Shows select another payment method button in Klarna Checkout page.
 */
function wc_payson_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

	if ( count( $available_gateways ) > 1 ) {
		$settings                   = get_option( 'woocommerce_payson_settings' );
		$select_another_method_text = isset( $settings['select_another_method_text'] ) && '' !== $settings['select_another_method_text'] ? $settings['select_another_method_text'] : __( 'Select another payment method', 'woocommerce-gateway-paysoncheckout' );

		?>
		<p style="margin-top:30px">
			<a class="checkout-button button" href="#" id="payson-checkout-select-other">
				<?php echo $select_another_method_text; ?>
			</a>
		</p>
		<?php
	}
}


/**
 * Calculates cart totals.
 */
function wc_payson_calculate_totals() {
	WC()->cart->calculate_fees();
	WC()->cart->calculate_shipping();
	WC()->cart->calculate_totals();
}

function wc_payson_show_payment_method_field() {
	?>
	<input style="display:none" type="radio" name="payment_method" value="paysoncheckout"/>
	<?php
}

/**
 * Unset Payson session
 */
function wc_payson_unset_sessions() {
	WC()->session->__unset( 'payson_checkout_id' );
	WC()->session->__unset( 'ongoing_payson_order' );
}



/**
 * Converts 3-letter ISO returned from Klarna to 2-letter code used in WooCommerce.
 *
 * @param $country
 */
function wc_payson_country_code_converter( $country ) {
	$countries = array(
		'AF' => 'AFG', // Afghanistan.
		'AX' => 'ALA', // Aland Islands.
		'AL' => 'ALB', // Albania.
		'DZ' => 'DZA', // Algeria.
		'AS' => 'ASM', // American Samoa.
		'AD' => 'AND', // Andorra.
		'AO' => 'AGO', // Angola.
		'AI' => 'AIA', // Anguilla.
		'AQ' => 'ATA', // Antarctica.
		'AG' => 'ATG', // Antigua and Barbuda.
		'AR' => 'ARG', // Argentina.
		'AM' => 'ARM', // Armenia.
		'AW' => 'ABW', // Aruba.
		'AU' => 'AUS', // Australia.
		'AT' => 'AUT', // Austria.
		'AZ' => 'AZE', // Azerbaijan.
		'BS' => 'BHS', // Bahamas.
		'BH' => 'BHR', // Bahrain.
		'BD' => 'BGD', // Bangladesh.
		'BB' => 'BRB', // Barbados.
		'BY' => 'BLR', // Belarus.
		'BE' => 'BEL', // Belgium.
		'BZ' => 'BLZ', // Belize.
		'BJ' => 'BEN', // Benin.
		'BM' => 'BMU', // Bermuda.
		'BT' => 'BTN', // Bhutan.
		'BO' => 'BOL', // Bolivia.
		'BQ' => 'BES', // Bonaire, Saint Estatius and Saba.
		'BA' => 'BIH', // Bosnia and Herzegovina.
		'BW' => 'BWA', // Botswana.
		'BV' => 'BVT', // Bouvet Islands.
		'BR' => 'BRA', // Brazil.
		'IO' => 'IOT', // British Indian Ocean Territory.
		'BN' => 'BRN', // Brunei.
		'BG' => 'BGR', // Bulgaria.
		'BF' => 'BFA', // Burkina Faso.
		'BI' => 'BDI', // Burundi.
		'KH' => 'KHM', // Cambodia.
		'CM' => 'CMR', // Cameroon.
		'CA' => 'CAN', // Canada.
		'CV' => 'CPV', // Cape Verde.
		'KY' => 'CYM', // Cayman Islands.
		'CF' => 'CAF', // Central African Republic.
		'TD' => 'TCD', // Chad.
		'CL' => 'CHL', // Chile.
		'CN' => 'CHN', // China.
		'CX' => 'CXR', // Christmas Island.
		'CC' => 'CCK', // Cocos (Keeling) Islands.
		'CO' => 'COL', // Colombia.
		'KM' => 'COM', // Comoros.
		'CG' => 'COG', // Congo.
		'CD' => 'COD', // Congo, Democratic Republic of the.
		'CK' => 'COK', // Cook Islands.
		'CR' => 'CRI', // Costa Rica.
		'CI' => 'CIV', // Côte d\'Ivoire.
		'HR' => 'HRV', // Croatia.
		'CU' => 'CUB', // Cuba.
		'CW' => 'CUW', // Curaçao.
		'CY' => 'CYP', // Cyprus.
		'CZ' => 'CZE', // Czech Republic.
		'DK' => 'DNK', // Denmark.
		'DJ' => 'DJI', // Djibouti.
		'DM' => 'DMA', // Dominica.
		'DO' => 'DOM', // Dominican Republic.
		'EC' => 'ECU', // Ecuador.
		'EG' => 'EGY', // Egypt.
		'SV' => 'SLV', // El Salvador.
		'GQ' => 'GNQ', // Equatorial Guinea.
		'ER' => 'ERI', // Eritrea.
		'EE' => 'EST', // Estonia.
		'ET' => 'ETH', // Ethiopia.
		'FK' => 'FLK', // Falkland Islands.
		'FO' => 'FRO', // Faroe Islands.
		'FJ' => 'FIJ', // Fiji.
		'FI' => 'FIN', // Finland.
		'FR' => 'FRA', // France.
		'GF' => 'GUF', // French Guiana.
		'PF' => 'PYF', // French Polynesia.
		'TF' => 'ATF', // French Southern Territories.
		'GA' => 'GAB', // Gabon.
		'GM' => 'GMB', // Gambia.
		'GE' => 'GEO', // Georgia.
		'DE' => 'DEU', // Germany.
		'GH' => 'GHA', // Ghana.
		'GI' => 'GIB', // Gibraltar.
		'GR' => 'GRC', // Greece.
		'GL' => 'GRL', // Greenland.
		'GD' => 'GRD', // Grenada.
		'GP' => 'GLP', // Guadeloupe.
		'GU' => 'GUM', // Guam.
		'GT' => 'GTM', // Guatemala.
		'GG' => 'GGY', // Guernsey.
		'GN' => 'GIN', // Guinea.
		'GW' => 'GNB', // Guinea-Bissau.
		'GY' => 'GUY', // Guyana.
		'HT' => 'HTI', // Haiti.
		'HM' => 'HMD', // Heard Island and McDonald Islands.
		'VA' => 'VAT', // Holy See (Vatican City State).
		'HN' => 'HND', // Honduras.
		'HK' => 'HKG', // Hong Kong.
		'HU' => 'HUN', // Hungary.
		'IS' => 'ISL', // Iceland.
		'IN' => 'IND', // India.
		'ID' => 'IDN', // Indonesia.
		'IR' => 'IRN', // Iran.
		'IQ' => 'IRQ', // Iraq.
		'IE' => 'IRL', // Republic of Ireland.
		'IM' => 'IMN', // Isle of Man.
		'IL' => 'ISR', // Israel.
		'IT' => 'ITA', // Italy.
		'JM' => 'JAM', // Jamaica.
		'JP' => 'JPN', // Japan.
		'JE' => 'JEY', // Jersey.
		'JO' => 'JOR', // Jordan.
		'KZ' => 'KAZ', // Kazakhstan.
		'KE' => 'KEN', // Kenya.
		'KI' => 'KIR', // Kiribati.
		'KP' => 'PRK', // Korea, Democratic People's Republic of.
		'KR' => 'KOR', // Korea, Republic of (South).
		'KW' => 'KWT', // Kuwait.
		'KG' => 'KGZ', // Kyrgyzstan.
		'LA' => 'LAO', // Laos.
		'LV' => 'LVA', // Latvia.
		'LB' => 'LBN', // Lebanon.
		'LS' => 'LSO', // Lesotho.
		'LR' => 'LBR', // Liberia.
		'LY' => 'LBY', // Libya.
		'LI' => 'LIE', // Liechtenstein.
		'LT' => 'LTU', // Lithuania.
		'LU' => 'LUX', // Luxembourg.
		'MO' => 'MAC', // Macao S.A.R., China.
		'MK' => 'MKD', // Macedonia.
		'MG' => 'MDG', // Madagascar.
		'MW' => 'MWI', // Malawi.
		'MY' => 'MYS', // Malaysia.
		'MV' => 'MDV', // Maldives.
		'ML' => 'MLI', // Mali.
		'MT' => 'MLT', // Malta.
		'MH' => 'MHL', // Marshall Islands.
		'MQ' => 'MTQ', // Martinique.
		'MR' => 'MRT', // Mauritania.
		'MU' => 'MUS', // Mauritius.
		'YT' => 'MYT', // Mayotte.
		'MX' => 'MEX', // Mexico.
		'FM' => 'FSM', // Micronesia.
		'MD' => 'MDA', // Moldova.
		'MC' => 'MCO', // Monaco.
		'MN' => 'MNG', // Mongolia.
		'ME' => 'MNE', // Montenegro.
		'MS' => 'MSR', // Montserrat.
		'MA' => 'MAR', // Morocco.
		'MZ' => 'MOZ', // Mozambique.
		'MM' => 'MMR', // Myanmar.
		'NA' => 'NAM', // Namibia.
		'NR' => 'NRU', // Nauru.
		'NP' => 'NPL', // Nepal.
		'NL' => 'NLD', // Netherlands.
		'AN' => 'ANT', // Netherlands Antilles.
		'NC' => 'NCL', // New Caledonia.
		'NZ' => 'NZL', // New Zealand.
		'NI' => 'NIC', // Nicaragua.
		'NE' => 'NER', // Niger.
		'NG' => 'NGA', // Nigeria.
		'NU' => 'NIU', // Niue.
		'NF' => 'NFK', // Norfolk Island.
		'MP' => 'MNP', // Northern Mariana Islands.
		'NO' => 'NOR', // Norway.
		'OM' => 'OMN', // Oman.
		'PK' => 'PAK', // Pakistan.
		'PW' => 'PLW', // Palau.
		'PS' => 'PSE', // Palestinian Territory.
		'PA' => 'PAN', // Panama.
		'PG' => 'PNG', // Papua New Guinea.
		'PY' => 'PRY', // Paraguay.
		'PE' => 'PER', // Peru.
		'PH' => 'PHL', // Philippines.
		'PN' => 'PCN', // Pitcairn.
		'PL' => 'POL', // Poland.
		'PT' => 'PRT', // Portugal.
		'PR' => 'PRI', // Puerto Rico.
		'QA' => 'QAT', // Qatar.
		'RE' => 'REU', // Reunion.
		'RO' => 'ROU', // Romania.
		'RU' => 'RUS', // Russia.
		'RW' => 'RWA', // Rwanda.
		'BL' => 'BLM', // Saint Bartholemy.
		'SH' => 'SHN', // Saint Helena.
		'KN' => 'KNA', // Saint Kitts and Nevis.
		'LC' => 'LCA', // Saint Lucia.
		'MF' => 'MAF', // Saint Martin (French part).
		'SX' => 'SXM', // Sint Maarten / Saint Martin (Dutch part).
		'PM' => 'SPM', // Saint Pierre and Miquelon.
		'VC' => 'VCT', // Saint Vincent and the Grenadines.
		'WS' => 'WSM', // Samoa.
		'SM' => 'SMR', // San Marino.
		'ST' => 'STP', // Sso Tome and Principe.
		'SA' => 'SAU', // Saudi Arabia.
		'SN' => 'SEN', // Senegal.
		'RS' => 'SRB', // Serbia.
		'SC' => 'SYC', // Seychelles.
		'SL' => 'SLE', // Sierra Leone.
		'SG' => 'SGP', // Singapore.
		'SK' => 'SVK', // Slovakia.
		'SI' => 'SVN', // Slovenia.
		'SB' => 'SLB', // Solomon Islands.
		'SO' => 'SOM', // Somalia.
		'ZA' => 'ZAF', // South Africa.
		'GS' => 'SGS', // South Georgia/Sandwich Islands.
		'SS' => 'SSD', // South Sudan.
		'ES' => 'ESP', // Spain.
		'LK' => 'LKA', // Sri Lanka.
		'SD' => 'SDN', // Sudan.
		'SR' => 'SUR', // Suriname.
		'SJ' => 'SJM', // Svalbard and Jan Mayen.
		'SZ' => 'SWZ', // Swaziland.
		'SE' => 'SWE', // Sweden.
		'CH' => 'CHE', // Switzerland.
		'SY' => 'SYR', // Syria.
		'TW' => 'TWN', // Taiwan.
		'TJ' => 'TJK', // Tajikistan.
		'TZ' => 'TZA', // Tanzania.
		'TH' => 'THA', // Thailand.
		'TL' => 'TLS', // Timor-Leste.
		'TG' => 'TGO', // Togo.
		'TK' => 'TKL', // Tokelau.
		'TO' => 'TON', // Tonga.
		'TT' => 'TTO', // Trinidad and Tobago.
		'TN' => 'TUN', // Tunisia.
		'TR' => 'TUR', // Turkey.
		'TM' => 'TKM', // Turkmenistan.
		'TC' => 'TCA', // Turks and Caicos Islands.
		'TV' => 'TUV', // Tuvalu.
		'UG' => 'UGA', // Uganda.
		'UA' => 'UKR', // Ukraine.
		'AE' => 'ARE', // United Arab Emirates.
		'GB' => 'GBR', // United Kingdom.
		'US' => 'USA', // United States.
		'UM' => 'UMI', // United States Minor Outlying Islands.
		'UY' => 'URY', // Uruguay.
		'UZ' => 'UZB', // Uzbekistan.
		'VU' => 'VUT', // Vanuatu.
		'VE' => 'VEN', // Venezuela.
		'VN' => 'VNM', // Vietnam.
		'VG' => 'VGB', // Virgin Islands, British.
		'VI' => 'VIR', // Virgin Island, U.S..
		'WF' => 'WLF', // Wallis and Futuna.
		'EH' => 'ESH', // Western Sahara.
		'YE' => 'YEM', // Yemen.
		'ZM' => 'ZMB', // Zambia.
		'ZW' => 'ZWE', // Zimbabwe.
	);

	return array_search( strtoupper( $country ), $countries, true );
}