import { test, expect, APIRequestContext } from '@playwright/test';
import { GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';
import { HandlePaysonIFrame } from '../utils/Utils';

const {
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

test.describe('Guest Checkout @shortcode', () => {
	test.use({ storageState: process.env.GUESTSTATE });

	let wcApiClient: APIRequestContext;

	let orderId: string;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async () => {
		// Delete the order from WooCommerce.
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
		await wcApiClient.delete(`orders/${orderId}`);
	});

	test('Can buy 6x 99.99 products with 25% tax.', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25', 'simple-25']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
		await checkoutPage.goto();
		await response;

		await HandlePaysonIFrame(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can buy products with different tax rates', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25', 'simple-12', 'simple-06', 'simple-00']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
		await checkoutPage.goto();
		await response;

		await HandlePaysonIFrame(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can buy products that don\'t require shipping', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-virtual-downloadable-25', 'simple-virtual-downloadable-12', 'simple-virtual-downloadable-06', 'simple-virtual-downloadable-00']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
		await checkoutPage.goto();
		await response;

		await HandlePaysonIFrame(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can buy variable products', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['variable-25-blue', 'variable-12-red', 'variable-12-red', 'variable-25-black', 'variable-12-black']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
		await checkoutPage.goto();
		await response;

		await HandlePaysonIFrame(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with Company name in billing address', async ({ page }) => { // Separate shipping not supported, only testing B2B checkout
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
		await checkoutPage.goto();
		await response;

		await HandlePaysonIFrame(page, false, true);
		// Fix company payment

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can change shipping method', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
		await checkoutPage.goto();
		await response;

		// Change shipping
		await checkoutPage.selectShippingMethod('free shipping');
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);

		await HandlePaysonIFrame(page);
		// Fix shipping option change

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with coupon 10%', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
		await checkoutPage.goto();
		await response;

		// Apply coupon and wait for two update_checkout events
		await checkoutPage.applyCoupon('percent-10');
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);

		await HandlePaysonIFrame(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with coupon fixed 10', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
		await checkoutPage.goto();
		await response;
		
		// Apply coupon and wait for two update_checkout events
		await checkoutPage.applyCoupon('fixed-10');
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);

		await HandlePaysonIFrame(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with coupon 100%', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
		await checkoutPage.goto();
		await response;

		// Apply coupon and wait for two update_checkout events
		await checkoutPage.applyCoupon('percent-100');
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
		await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 

		await HandlePaysonIFrame(page);

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});
});
