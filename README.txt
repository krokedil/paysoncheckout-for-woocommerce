=== PaysonCheckout 2.0 for WooCommerce ===
Contributors: krokedil, niklashogefjord
Tags: ecommerce, e-commerce, woocommerce, payson, paysoncheckout2.0
Requires at least: 4.5
Tested up to: 5.3.0
WC requires at least: 3.0
WC tested up to: 3.8.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Stable tag: trunk

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
= 2019.12.10	- version 2.3.0 =
* Feature		- Free trial subscriptions is now supported in the plugin.
* Enhancement 	- Added more logging in the notification callback function.
* Fix			- Comparing with correct payment id in notification callback if order is a subscription.

= 2019.11.11	- version 2.2.1 =
* Enhancement	- Added default for Last name when customer type is business. Prevents orders from being created on checkout error fallback.

= 2019.09.25	- version 2.2.0 =
* Feature		- Added support for creating orders via API callback.

= 2019.08.09	- version 2.1.1 =
* Fix			- Updating nonce correctly on update_checkout. This could cause issues finalizing order if logging in on checkout page.
* Fix			- Added checks to prevent JS error with some themes in checkout page.
* Fix			- Fix for order going through even if login in required and user isn't logged in.

= 2019.05.28	- version 2.1.0 =
* Feature	    - Added support for recurring orders through WooCommerce Subscriptions ( https://woocommerce.com/products/woocommerce-subscriptions/ ).
* Enhancement   - Better handling of payment id transfer between frontend and server to server calls.
* Fix           - Fixed fee totals calculations. Caused missmatches between Payson and WooCommerce.
* Fix			- Set customer country properly on server to server callbacks. WooCommerce 3.6 compatible.

= 2019.04.18	- version 2.0.3 =
* Enhancement	- Added swedish translation of plugin.
* Fix			- Fixed an issue getting a fee name.

= 2019.04.10	- version 2.0.2 =
* Enhancement   - Added check on Payson order status for checkout page to create a new checkout session if the order is expired, canceled and so on.
* Enhancement   - Added integration information to all requests to Payson for easier debugging.
* Tweak         - Removed setting for mandatory phone number. We now go off if the phone number field is required or not.
* Tweak         - Moved a update checkout trigger to cover more scenarios for postcode based shipping.
* Fix           - Fixed inline CSS to prevent an overflow scroll for mobile users on checkout page.

= 2019.04.08	- version 2.0.1 =
* Tweak			- Changed location of extra checkout fields to be above the iFrame.
* Tweak			- Re-added the language pot file.
* Tweak			- Retain the extra checkout field validation message.

= 2019.04.03	- version 2.0.0 =
* Feature		- Added support for Extra Checkout field validation.
* Feature		- Added support for full order refunds with Payson.
* Feature		- Better error handling and logging.
* Feature		- Added stock, coupon and order total validation before completing checkout.
* Feature       - Added filters to all request arguments. Create and update Payson order. 
* Tweak			- Complete rewrite of plugin.
* Tweak			- Only create a WooCommerce order when Payson payment is completed.
* Tweak			- Update the order reference in Payson on WooCommerce payment complete.
* Tweak			- Optimized checkout process.
* Tweak			- Optimized JavaScript for checkout and confirmation page.

= 2018.10.16    - version 1.4.0 =
* Feature		- Added support for wp_add_privacy_policy_content (for GDPR compliance). More info: https://core.trac.wordpress.org/attachment/ticket/43473/PRIVACY-POLICY-CONTENT-HOOK.md.
* Tweak			- Improved error messaging in Payson account check request.
* Fix			- Updated compatability functions to prevent error.
* Tweak			- Added filters payson_pay_data, payson_gui & payson_customer to allow merchants to modify order data sent to Payson.
* Tweak			- Added company name to WooCommerce order if purchase is a B2B order.
* Fix			- Changed how JS eventlistener PaysonEmbeddedAddressChanged was implemented. Some websites was not able to retrieve customer address data.
* Fix			- Submission failure process bug fix. Order is now created in Woo and customer redirected to thankyou page even if original checkout form submission fails.

= 2018.05.15    - version 1.3.1 =
* Tweak			- Only make merchant account validation request to Payson on PaysonCheckout 2.0 settings page.
* Fix			- Don’t display Payson as an available payment method if cart total is too low (below 4 SEK or 0 EUR).
* Fix			- Save customer order note correctly when processing WC checkout/order.

= 2018.05.04    - version 1.3.0 =
* Feature       - Added support for handling payments of manually created orders (via Pay for order page).
* Tweak         - Save _payson_checkout_id to WC order in thank you page if it hasn't been saved earlier. 

= 2018.04.09    - version 1.2.3 =
* Tweak			- Change priority to 999 for filter woocommerce_locate_template so that PaysonCheckout template file doesn't get overwritten by other plugins/themes.
* Fix			- Changed text domain for "Select another payment method" label so it uses translation files in plugin.

= 2018.04.05    - version 1.2.2 =
* Tweak         - Improved error logging/handling.
* Tweak         - Display error message in checkout if Payson iframe doesnt load.
* Fix           - Unset payson_checkout_id session if communication error happens.

= 2018.04.05    - version 1.2.1 =
* Fix           - Refer to self in static function to avoid issues when processing order in WooCommerce checkout. 
* Fix           - Logging improvements.
* Tweak         - Don't rely on session data in thank you page when trigger payment_complete().

= 2018.03.23    - version 1.2.0 =
* Feature       - Use template file for displaying Payson Checkout. Making it possible to overwrite via theme.
* Tweak         - Make initial call to Payson before the checkout page is rendered. Reusluts in a faster loading Payson Checkout iframe.
* Tweak         - Submit WC checkout form when purchase is finalized in Payson iframe. Making it possible to save more order/cart data.
* Tweak         - Pause and resume current iframe on update_checkout event instead of re-rendering the html/iframe.
* Tweak         - Logging improvements.
* Tweak         - Schedule readyToShip notification callback to be executed in WC 2 minutes after purchase. For better order status control.
* Fix           - Avoid order status updates to be executed in a way so that new order email is missing customer address data.

= 2018.02.18    - version 1.1.14 =
* Fix			- WC 3.3 switch payment method bug fix (not being able to switch from Payson to other gateway).
* Fix			- Flatesome theme css compatibility fix.

= 2017.12.06    - version 1.1.13 =
* Tweak			- Save order after adding customer data in ready to ship callback.

= 2017.12.06    - version 1.1.12 =
* Fix			- Re-add possibility to send items to Payson with price set to 0. 
* Fix           - Only include Krokedil compatibility functions if they don't already exist (compat w other plugins).


= 2017.11.22	- version 1.1.11 =
* Fix			- Don’t send items to Payson with price set to 0. Product Bundles compatibility fix.
* Tweak			- Improved logging.

= 2017.10.09    - version 1.1.10 =
* Fix			- Only delete WC order on expired callback from Payson if WC order has status payson-incomplete.

= 2017.09.29   	- version 1.1.9 =
* Fix           - Changed how we get the name of fees from $fee->label to $fee->name.
* Fix           - No longer possible for division by zero on Product vat.
* Fix           - Allowed countries sent to Payson changed from WC allowed shipping countries to WC allowed countries.

= 2017.08.25   	- version 1.1.8 =
* Tweak			- Adds loading spinner when waiting for the Payson iframe to render.
* Tweak			- Restrict product names sent to Payson to be max 199 characters.
* Tweak			- Added check to make sure that WC Gateway is loaded.
* Tweak			- Removed apply_filters woocommerce_cart_item_name when sending cart items to Payson. This filter can be used by themes to add html.
* Fix			- Adds fix that moves "Select another payment method" button to above order review section when checkout is updated via ajax.

= 2017.07.07    - version 1.1.7 =
* Fix			- Adds customer email to local order in payson_address_changed_callback() callback. Fixes bug where customer confirmation email not being sent.

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
