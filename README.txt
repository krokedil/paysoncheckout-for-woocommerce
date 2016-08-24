=== PaysonCheckout 2.0 for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, payson, paysoncheckout2.0
Requires at least: 4.3
Tested up to: 4.6
Requires WooCommerce at least: 2.5
Tested WooCommerce up to: 2.6
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: 0.5

PaysonCheckout 2.0 for WooCommerce is a plugin that extends WooCommerce, allowing you to take payments via Payson.


== DESCRIPTION ==
<ul>
	<li>All payment methods with one contract</li>
	<li>No fixed fees, get started within 24 hours</li>
	<li>The customer never leaves your e-commerce store</li>
</ul>

In PaysonCheckout 2.0 Payson has removed all unnecessary steps in the checkout to increase conversions and sales. The payment window is nicely integrated into your e-store where customers can pay with a single click! All purchases are based on the service Invoice via email. The customer can then choose to get the goods delivered first and pay retrospectively or pay the invoice immediately upon purchase, for example by card, online bank payment or SMS.

To get started with Payson you need to [sign up](https://www.payson.se/sv/) for a Business account.

More information on how to get started can be found in the [plugin documentation](http://docs.krokedil.com/se/documentation/paysoncheckout-2-0-woocommerce/).


== INSTALLATION	 ==
1. Download and unzip the latest release zip file.
2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
3. Upload the entire plugin directory to your /wp-content/plugins/ directory.
4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
5. Go WooCommerce Settings --> Payment Gateways and configure your Payson settings.
6. Read more about the configuration process in the [plugin documentation](http://docs.krokedil.com/se/documentation/paysoncheckout-2-0-woocommerce/).


== CHANGELOG ==

= 2016.08.22	- version 0.4 =
* Fix			- Allow free products in order sent to PaysonCheckout (caused fatal error).

= 2016.08.17	- version 0.3 =
* Feature		- Added Cancel reservation in Payson directly from WooCommerce.
* Feature		- Added Capture transaction in Payson directly from WooCommerce. 
* Tweak			- Improved try/catch when connecting to Payson.
* Tweak			- Store Payson checkout id as a separate post meta instead of the order transaction number.
* Tweak			- Updated Payson SDK to latest version.
* Fix			- Unset all created sessions on successful payment.

= 2016.08.02	- version 0.2 =
* Tweak			- Improved display of the Payson iframe in checkout pages with a two column layout.
* Tweak			- Added Payson order status readyToShip as post meta to orders on payment_complete().