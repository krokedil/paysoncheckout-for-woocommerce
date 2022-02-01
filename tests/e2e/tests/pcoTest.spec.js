import puppeteer from "puppeteer";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import tests from "../config/tests.json"
import data from "../config/data.json";
import API from "../api/API";
import iframeHandler from "../helpers/iframeHandler";


const options = {
    "headless": false,
    "defaultViewport": null,
    "args": [
        '--proxy-bypass-list=*',
        '--ignore-certificate-errors-spki-list',
        '--ignore-certificate-errors',
        "--disable-infobars",
        "--disable-web-security",
        "--disable-features=IsolateOrigins,site-per-process",
    ]
};

// Main selectors
let page;
let browser;
let context;
let timeOutTime = 2500;
let json = data;

describe("Payson Checkout E2E tests", () => {
    beforeAll(async () => {
        try {

            // json = await setup.setupStore(json);
            json = JSON.parse('{"taxes":[{"name":"25","rate":"25"},{"name":"12","rate":"12"},{"name":"06","rate":"6"},{"name":"00","rate":"0"}],"products":{"simple":[{"id":11,"name":"Test Product - (Simple Product, 25% Tax, Stock Quantity)","sku":"test-product-simple-product-25-tax-stock-quantity","regular_price":"9.99","virtual":false,"downloadable":false,"tax_class":"25"},{"id":12,"name":"Test Product - (Virtual, 0% Tax, Stock Quantity)","sku":"test-product-virtual-0-tax-stock-quantity","regular_price":"1490","virtual":true,"downloadable":false,"tax_class":"00"},{"id":13,"name":"Test Product - (Virtual, 25% Tax, Stock Quantity)","sku":"test-product-virtual-25-tax-stock-quantity","regular_price":"1490","virtual":true,"downloadable":false,"tax_class":"25"},{"id":14,"name":"Test Product - (Virtual, Downloadable, 0% Tax, Stock Quantity)","sku":"test-product-virtual-downloadable-0-tax-stock-quantity","regular_price":"1490","virtual":true,"downloadable":true,"tax_class":"00"},{"id":15,"name":"Test Product - (Virtual, Downloadable, 25% Tax, Stock Quantity)","sku":"test-product-virtual-downloadable-25-tax-stock-quantity","regular_price":"1490","virtual":true,"downloadable":true,"tax_class":"25"},{"id":16,"name":"Test Product - (Downloadable, Shipping, 0% Tax, Stock Quantity)","sku":"test-product-downloadable-0-tax-stock-quantity","regular_price":"1490","virtual":false,"downloadable":true,"tax_class":"00"},{"id":17,"name":"Test Product - (Downloadable, Shipping, 25% Tax, Stock Quantity)","sku":"test-product-downloadable-25-tax-stock-quantity","regular_price":"1490","virtual":false,"downloadable":true,"tax_class":"25"},{"id":18,"name":"Test Product - (Simple Product, 12% Tax, Stock Quantity)","sku":"test-product-simple-product-12-tax","regular_price":"0.99","virtual":false,"downloadable":false,"tax_class":"12"},{"id":19,"name":"Test Product - (Simple Product, 6% Tax, Stock Quantity)","sku":"test-product-simple-product-6-tax","regular_price":"0.99","virtual":false,"downloadable":false,"tax_class":"06"}],"variable":[{"id":20,"name":"Test Product - (1 Variable Product, 25% Tax)","sku":"test-product-1-variable-product-25-tax","regular_price":"99","virtual":false,"downloadable":false,"tax_class":"25","attribute":{"name":"color","options":[{"id":21,"name":"Variable 25%:black","option":"black"},{"id":22,"name":"Variable 25%:blue","option":"blue"},{"id":23,"name":"Variable 25%:green","option":"green"},{"id":24,"name":"Variable 25%:red","option":"red"}]}}],"attribute":[{"id":1,"name":"color"}]},"coupons":[{"code":"fixedcart","amount":"10","discountType":"fixed_cart"},{"code":"fixedproduct","amount":"13","discountType":"fixed_product"},{"code":"percent","amount":"11","discountType":"percent"},{"code":"free","amount":"100","discountType":"percent"}],"shipping":[{"name":"Sweden","location":{"code":"SE","type":"country"},"methods":[{"method":"flat_rate","amount":49},{"method":"free_shipping","amount":0}]}]}');

            await utils.setOptions();
        } catch (e) {
            console.log(e);
        }
    }, 250000);

    beforeEach(async () => {
        browser = await puppeteer.launch(options);
        context = await browser.createIncognitoBrowserContext();
        page = await context.newPage();
    });

    afterEach(async () => {
        if (!page.isClosed()) {
            browser.close();
        }
        await API.clearWCSession();
    });

    test.each(tests)(
        "$name",
        async (args) => {
            try {
                // --------------- GUEST/LOGGED IN --------------- //
                if(args.loggedIn) {
                    await page.goto(urls.MY_ACCOUNT);
                    await utils.login(page, "admin", "password");
                }

                // --------------- SETTINGS --------------- //
                await utils.setPricesIncludesTax({value: args.inclusiveTax});
                // await utils.setIframeShipping(args.shippingInIframe);

                // --------------- ADD PRODUCTS TO CART --------------- //
                await utils.addMultipleProductsToCart(page, args.products, json);
                await page.waitForTimeout(1 * timeOutTime);

                // --------------- GO TO CHECKOUT --------------- //
                await page.goto(urls.CHECKOUT);
                await page.waitForTimeout(timeOutTime);
                await utils.selectPaysonCheckout(page);
                await page.waitForTimeout(4 * timeOutTime);

                const elementHandle = await page.waitForSelector('iframe[id="paysonIframe"]');
                const frame = await elementHandle.contentFrame();

                // Fill out Customer form.
                await iframeHandler.clearCheckoutFields(page);
                await page.waitForTimeout(timeOutTime / 25);

                await frame.type("input[id='PersonLookupEmail']", "test@krokedil.com");
                await frame.type("input[id='PersonIdentityNumber']" , "4605092222");
                await frame.type("input[id='PersonLookupPostalCode']" , "99999");
                await frame.type("input[id='PersonLookupMandatoryPhoneNumber']" , "0720000000");

                // Submit form.
                await page.waitForTimeout( timeOutTime);
                await frame.click("button[id='SubmitAddress']");
                await page.waitForTimeout(4 * timeOutTime);

                //Select Invoice and confirm
                await frame.click("div[id='invoicePaymentSelector']");
                await page.waitForTimeout( timeOutTime);
                await frame.click("button[id='SubmitComplete']");

            } catch(e) {
                console.log("Error placing order", e)
            }

			// --------------- POST PURCHASE CHECKS --------------- //

                await page.waitForTimeout(3 * timeOutTime);
                const value = await page.$eval(".entry-title", (e) => e.textContent);
                expect(value).toBe("Order received");
                await page.waitForTimeout(3 * timeOutTime);

                const elementHandleThankYou = await page.waitForSelector('iframe[id="paysonIframe"]');
                const frameThankYou = await elementHandleThankYou.contentFrame();

                const paysonTotal = await frameThankYou.$eval('#Amount', (e) => e.textContent);
                const paysonTotalAsNumber = parseFloat(paysonTotal.replace(/\s/g, '').replace('SEK', '').replace(',', '.'));

                expect(paysonTotalAsNumber).toBe(args.expectedTotal);


        }, 190000);
});
