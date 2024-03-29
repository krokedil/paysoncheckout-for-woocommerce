import { AdminLogin, GetWcApiClient, WcPages } from '@krokedil/wc-test-helper';
import { test, expect, APIRequestContext } from '@playwright/test';
import { HandlePaysonIFrame } from '../utils/Utils';
import { VerifyOrderRecieved } from '../utils/VerifyOrder';

const {
	CI,
	BASE_URL,
	CONSUMER_KEY,
	CONSUMER_SECRET,
} = process.env;

test.describe('Order management @shortcode', () => {
	//test.skip(CI !== undefined, 'Skipping tests in CI environment since its currently failing randomly without any reason during CI. Skipping to prevent false negative tests.') // - Fix this test for CI.

	test.use({ storageState: process.env.GUESTSTATE });

	let wcApiClient: APIRequestContext;

	let orderId;

	test.beforeAll(async () => {
		wcApiClient = await GetWcApiClient(BASE_URL ?? 'http://localhost:8080', CONSUMER_KEY ?? 'admin', CONSUMER_SECRET ?? 'password');
	});

	test.afterEach(async ({ page }) => {
		// Delete the order from WooCommerce.
		await wcApiClient.delete(`orders/${orderId}`);

		// Clear all cookies.
		await page.context().clearCookies();
	});

	test('Can capture an order', async ({ page }) => {
		await test.step('Place an order with Payson Checkout.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
			await checkoutPage.goto();
			await response;

			await HandlePaysonIFrame(page);

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Capture the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();

			expect(page.getByText('PaysonCheckout reservation was successfully activated.')).not.toBeUndefined();
			expect(await adminSingleOrder.hasOrderNoteWithText('PaysonCheckout reservation was successfully activated.')).toBe(true);
		});
	});

	test('Can cancel an order', async ({ page }) => {
		await test.step('Place an order with Payson Checkout.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
			await checkoutPage.goto();
			await response;

			await HandlePaysonIFrame(page);

			await expect(page).toHaveURL(/order-received/);

			orderId = await orderRecievedPage.getOrderId();
		});

		await test.step('Cancel the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.cancelOrder();

			expect(page.getByText('PaysonCheckout reservation was successfully cancelled.')).not.toBeUndefined();
			expect(await adminSingleOrder.hasOrderNoteWithText('PaysonCheckout reservation was successfully cancelled.')).toBe(true);
		});
	});

	test('Can refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Payson Checkout.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
			await checkoutPage.goto();
			await response;

			await HandlePaysonIFrame(page);

			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
		});

		await test.step('Fully refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundFullOrder(order, false);

			expect(page.getByText('Order status changed from Completed to Refunded.')).not.toBeUndefined();
			expect(await adminSingleOrder.hasOrderNoteWithText('Order status changed from Completed to Refunded.')).toBe(true);
		});
	});

	test('Can partially refund an order', async ({ page }) => {
		let order;
		await test.step('Place an order with Payson Checkout.', async () => {
			const cartPage = new WcPages.Cart(page, wcApiClient);
			const orderRecievedPage = new WcPages.OrderReceived(page, wcApiClient);
			const checkoutPage = new WcPages.Checkout(page);
			await cartPage.addtoCart(['simple-25']);

			var response = page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); 
			await checkoutPage.goto();
			await response;

			await HandlePaysonIFrame(page);

			await expect(page).toHaveURL(/order-received/);

			order = await orderRecievedPage.getOrder();
			orderId = order.id;
		});

		await test.step('Partially refund the order.', async () => {
			// Login as admin.
			await AdminLogin(page);

			const adminSingleOrder = new WcPages.AdminSingleOrder(page, orderId);
			await adminSingleOrder.goto();
			await adminSingleOrder.completeOrder();
			await adminSingleOrder.refundPartialOrder(order, false);

			expect(page.getByText('PaysonCheckout reservation was successfully refunded for')).not.toBeUndefined();
			expect(await adminSingleOrder.hasOrderNoteWithText('PaysonCheckout reservation was successfully refunded for')).toBe(true);;
		});
	});
});
