import { test, expect, APIRequestContext } from '@playwright/test';
import { GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with separate shipping address', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});

	test('Can place order with Company name in both billing and shipping address', async ({ page }) => {
		const cartPage = new WcPages.Cart(page, wcApiClient);
		const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
		const checkoutPage = new WcPages.Checkout(page);

		// Add products to the cart.
		await cartPage.addtoCart(['simple-25']);

		// Go to the checkout page.
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

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
		await checkoutPage.goto();

		// TODO - Handle Payson Checkout

		// Verify that the order was placed.
		await expect(page).toHaveURL(/order-received/);

		orderId = await orderRecievedPage.getOrderId();

		// Verify the order details.
		await VerifyOrderRecieved(orderRecievedPage);
	});
});
