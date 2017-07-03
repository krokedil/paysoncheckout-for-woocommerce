=== PaysonCheckout 2.0 for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, payson, paysoncheckout2.0
Requires at least: 4.3
Tested up to: 4.8
Requires WooCommerce at least: 2.5
Tested WooCommerce up to: 3.1.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: 1.1.6

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

= 2017.07.03    - version 1.1.6 =
* Fix			- Shipping tax sent correctly to Payson.

= 2017.07.03    - version 1.1.5 = 
* Fix			- Updated get_product_name() function (product name sent to Payson). Caused bug with WooCommerce 3.1.

= 2017.05.31    - version 1.1.4 =
* Fix           - Added customer note to an order made with PaysonCheckout 2.0.
* Fix           - Added support to limit countries that can be chosen under "OTHER".
* Fix           - Added compatibility functions to make PaysonCheckout 2.0 compatible with WooCommerce 3.0.

= 2017.04.06	- version 1.1.3 =
* Fix			- WC 3.0 fix. Order lines sent to Payson now fetched from cart instead of local order.
* Fix			- Improved calculate_totals.

= 2017.03.05	- version 1.1.2 =
* Fix			- Display PaysonCheckout iframe when switch to Payson in checkout (if not having Payson as default payment method).

= 2017.03.01	- version 1.1.1 =
* Tweak			- Updating Paysons SDK (version from 2016-11-02).
* Tweak			- Adds product variations to product name sent to Payson.
* Tweak			- Improved multi currency support.
* Fix			- Create a new Payson checkout ID if the store currency has been changed when updating order/cart.
* Fix			- JS fix to prevent double calls to Payson on initial loading of checkout page.


= 2017.01.17    - version 1.1 =
* Tweak			- Only show available shipping countries (defined in WC settings) in PaysonCheckout country selector.
* Tweak			- Change order status to Processing already from info received in thank you page. Helpful for stores where Payson server-to-server callbacks are being blocked.
* Tweak			- Swedish translation update.

= 2016.12.01	- version 1.0.1 =
* Tweak			- Adds compatibility with Sequential Order Numbers and Sequential Order Numbers Pro.
* Fix			- Fixes order statuses issue in callback handler from Payson.
* Fix			- Fixes no PaysonCheckout resource update on shipping option change in checkout page.

= 2016.11.29	- version 1.0 =
* Tweak			- Not WooCommerce order is created only when PaysonCheckout iframe is initialized.
* Tweak			- Improved checkout page UI when PaysonCheckout is the selected option.

= 2016.10.24	- version 0.8.5 =
* Tweak			- Added support for Flatsome 3.x. No need for specific markup for Flatsome anymore.
* Tweak			- Removed duplicate code.
* Fix			- Fixed issue with new order emails not being sent (if PaysonEmbeddedAddressChanged js-event wasn't triggered).
* Fix			- Fixed missing quote in inline CSS.

= 2016.09.23	- version 0.8.4 =
* Tweak			- Added Payson logo to payment method display in checkout.
* Tweak			- Changed naming of Merchant ID to Agent ID in settings.
* Tweak			- Added Swedish translation file.
* Tweak			- Added default payment gateway description in settings.

= 2016.09.14	- version 0.8.3 =
* Tweak			- Changes orderstatus from Payson Incomplete to Pending on Address Changed JS callback from Payson.
* Tweak			- Adds customer address to local order on Address Changed JS callback from Payson.
* Fix			- Fixed issue with new order emails not being sent.

= 2016.09.08	- version 0.8.2 =
* Fix			- Fixed PHP error in check_terms() function caused when using PHP 5.5 and older.

= 2016.09.06	- version 0.8.1 =
* Tweak			- Hide WooCommerce billing and shipping fields on loading of checkout page if PaysonCheckout is the selected payment method.
* Fix			- Prevent checkout iframe to reload/update directly on page load.
* Fix			- Prevent checkout iframe to update on scroll on mobile devices (triggered by resize js event).

= 2016.08.31	- version 0.8 =
* Fix			- Fixing wrong url in include_once, caused PHP warnings.

= 2016.08.31	- version 0.7 =
* Tweak			- Small code refactoring.
* Fix			- Fixes issue to be able to see orders with parson-incomplete order status in shop order list.

= 2016.08.30	- version 0.6 =
* Feature		- Added setting for enable/disable request phone number in checkout.
* Misc			- First release on wordpress.org.

= 2016.08.24	- version 0.5 =
* Tweak			- Added admin notices to inform merchant about possible misconfigurations in Payson settings.
* Tweak			- Change css class names for the divs that is hidden/displayed when Payson is the selected payment method in checkout.

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
