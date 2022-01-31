import puppeteer from "puppeteer";
import setup from "../api/setup";
import urls from "../helpers/urls";
import utils from "../helpers/utils";
import tests from "../config/tests.json"
import data from "../config/data.json";
import API from "../api/API";


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

            json = await setup.setupStore(json);

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