import API from "../api/API";
import urls from "./urls";

const timeOutTime = 2500;
const paysonCheckoutSettings = {
	woocommerce_paysoncheckout_settings: {
		'enabled' : 'yes',
		'title' : 'Payson',
		'description' : 'Pay with Payson via invoice, card, direct bank payments, part payment and sms.',
		'select_another_method_text' : 'Switch Payment Method from Payson',
		'recurring_invoice_expiration' : '7',
		'merchant_id' : '1399',
		'api_key' : '3aad2e03-f905-4cda-8e43-401feeaab98e',
		'testmode' : 'yes',
		'order_management' : 'yes',
		'default_customer_type' : 'b2c',
		'color_scheme' : 'White',
		'debug' : 'yes'
	},
};

const login = async (page, username, password) => {
	await page.type("#username", username);
	await page.type("#password", password);
	await page.waitForSelector("button[name=login]");
	await page.click("button[name=login]");
};

const applyCoupons = async (page, appliedCoupons) => {
	if (appliedCoupons.length > 0) {
		await appliedCoupons.forEach(async (singleCoupon) => {
			await page.click('[class="showcoupon"]');
			await page.waitForTimeout(500);
			await page.type('[name="coupon_code"]', singleCoupon);
			await page.click('[name="apply_coupon"]');
		});
	}
	await page.waitForTimeout(3 * timeOutTime);
};

const addSingleProductToCart = async (page, productId) => {
	const productSelector = productId;

	try {
		await page.goto(`${urls.ADD_TO_CART}${productSelector}`);
		await page.goto(urls.SHOP);
	} catch {
		// Proceed
	}
};

const addMultipleProductsToCart = async (page, products, data) => {
	const timer = products.length;

	await page.waitForTimeout(timer * 800);
	let ids = [];

	products.forEach((name) => {
		data.products.simple.forEach((product) => {
			if (name === product.name) {
				ids.push(product.id);
			}
		});

		data.products.variable.forEach((product) => {
			product.attribute.options.forEach((variation) => {
				if (name === variation.name) {
					ids.push(variation.id);
				}
			});
		});
	});

	await (async function addEachProduct() {
		for (let i = 0; i < ids.length + 1; i += 1) {
			await addSingleProductToCart(page, ids[i]);
		}
	})();

	await page.waitForTimeout(timer * 800);
};

const setPricesIncludesTax = async (value) => {
	await API.pricesIncludeTax(value);
};

const setOptions = async () => {
	await API.updateOptions(paysonCheckoutSettings);
};

const selectPaysonCheckout = async (page) => {
	if (await page.$('input[id="payment_method_paysoncheckout"]')) {
		await page.evaluate(
			(paymentMethod) => paymentMethod.click(),
			await page.$('input[id="payment_method_paysoncheckout"]')
		);
	}
};

export default {
	login,
	applyCoupons,
	addSingleProductToCart,
	addMultipleProductsToCart,
	setPricesIncludesTax,
	setOptions,
	selectPaysonCheckout,
};
