import { APIRequestContext, Page, request } from "@playwright/test";

const {
	PAYSON_AGENT_ID,
	PAYSON_API_KEY,
} = process.env;

export const GetPaysonClient = async (): Promise<APIRequestContext> => {
	// TODO - Get payson client.
	return await request.newContext({
		baseURL: ``,
		extraHTTPHeaders: {
			Authorization: `Basic ${Buffer.from(
				``
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
