import { Frame } from "@playwright/test";
import { APIRequestContext, Page, request, FrameLocator, Locator, expect } from "@playwright/test";
import { PaysonIFrame } from "../pages/PaysonIFrame"

const {
	PAYSON_AGENT_ID,
	PAYSON_API_KEY,
} = process.env;

export const GetPaysonClient = async (): Promise<APIRequestContext> => {
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

export const HandlePaysonIFrame = async(page: Page) => {
	const paysonIFrame = new PaysonIFrame(page);
	await paysonIFrame.handleIFrame();
}