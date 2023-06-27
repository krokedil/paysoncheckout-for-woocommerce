import { WcPages } from "@krokedil/wc-test-helper";
import { expect } from "@playwright/test";
import { GetPaysonClient } from "./Utils";

export const VerifyOrderRecieved = async (orderRecievedPage: WcPages.OrderReceived, expectedStatus: string = 'processing') => {
	const komClient = await GetPaysonClient();

	// Get the WC Order.
	const wcOrder = await orderRecievedPage.getOrder();


}

const VerifyOrderLines = async (wcOrder: any, paysonOrder: any) => {

}
