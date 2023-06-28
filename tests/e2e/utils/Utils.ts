import { Frame } from "@playwright/test";
import { APIRequestContext, Page, request, FrameLocator, Locator, expect } from "@playwright/test";

const {
	PAYSON_AGENT_ID,
	PAYSON_API_KEY,
} = process.env;

export const GetPaysonClient = async (): Promise<APIRequestContext> => {
	// TODO - Get payson client.
	
	return await request.newContext({
		baseURL: `https://test-api.payson.se/2.0/`,
		extraHTTPHeaders: {
			Authorization: `Basic ${Buffer.from(
				`${PAYSON_AGENT_ID ?? 'admin'}:${PAYSON_API_KEY ?? 'password'}`
			).toString('base64')}`,
		},
	});
}

export const SetPaysonSettings = async (wcApiClient: APIRequestContext) => {
	// Set api credentials and enable the gateway.
	if (PAYSON_AGENT_ID) {
		const settings = {
			enabled: true,
			settings: {
				testmode: "yes",
				debug: "yes",
				merchant_id: PAYSON_AGENT_ID,
				api_key: PAYSON_API_KEY,
			}
		};

		// Update settings.
		await wcApiClient.post('payment_gateways/paysoncheckout', { data: settings });
	}
}
var iframe: FrameLocator

const FillPaysonPaymentDetails = async(iframe: FrameLocator) => {
	await iframe.getByRole('textbox', { name: 'E-mail' }).fill('test.testsson@test.se')
	await iframe.getByLabel('Personal ID number').fill('4606082222')
	await iframe.getByRole('textbox', { name: 'Zip code' }).fill('99999')
	await iframe.locator('#PersonLookupMandatoryPhoneNumber').fill('0720000000')
}
const FinishOrder = async(page: Page, iframe: FrameLocator) => {
	await iframe.locator('#SubmitAddress').click();

	await iframe.getByRole('radio', { name: 'Bank account' }).click();
	await iframe.getByRole('button', { name: 'Complete purchase' }).click();
	await page.getByRole('button', { name: 'Simulate Accept' }).click();
}
export const HandlePaysonIFrame = async(page: Page) => {
	iframe = page.frameLocator('#paysonIframe')
	await page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); //Is this needed?

	await FillPaysonPaymentDetails(iframe);
	await FinishOrder(page, iframe);
}