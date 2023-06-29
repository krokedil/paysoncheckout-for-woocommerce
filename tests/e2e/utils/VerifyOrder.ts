import { WcPages } from "@krokedil/wc-test-helper";
import { expect } from "@playwright/test";
import { GetPaysonClient } from "./Utils";

export const VerifyOrderRecieved = async (orderRecievedPage: WcPages.OrderReceived, expectedWCStatus: string = 'processing') => {
	const pcClient = await GetPaysonClient();

	// Get the WC Order.
	const wcOrder = await orderRecievedPage.getOrder();

	// Verify that the order has the correct status.
	expect(wcOrder.status).toBe(expectedWCStatus);

	// Verify that the order has the correct payment method.
	expect(wcOrder.payment_method).toBe('paysoncheckout');

	// Get the Payson order id from the transaction id.
	const paysonOrder = await GetPaysonOrder(wcOrder, pcClient);

	// Verify that the Payson order has the correct status.
	expect(paysonOrder.status).toBe('readyToShip');

	// Compare order line totals.
	await VerifyOrderLines(wcOrder, paysonOrder);
}

const VerifyOrderLines = async (wcOrder: any, paysonOrder: any) => {
	const paysonOrderTotal = paysonOrder.order.totalPriceIncludingTax
	expect(Number(wcOrder.total)).toBeCloseTo(paysonOrderTotal, 1); //Only care about the first decimal

	for (const wcLineItem of wcOrder.line_items) {
		const paysonLineItem = paysonOrder.order.items.find((pLineItem: any) => pLineItem.reference === wcLineItem.sku); //Find equivalent payson item
		expect(paysonLineItem).not.toBeUndefined();
		//Compare names too?
		
		const paysonLineItemTotal = paysonLineItem.totalPriceIncludingTax;
		expect(Number(wcLineItem.total) + Number(wcLineItem.total_tax)).toBeCloseTo(paysonLineItemTotal, 1);
		expect(Number(wcLineItem.quantity)).toBe(paysonLineItem.quantity);
	}

	// Compare shipping totals, if the order has any shipping.
	if (wcOrder.shipping_lines.length > 0) {
		const paysonShippingLine = paysonOrder.order.items.find((pLineItem: any) => pLineItem.type === 'service' && pLineItem.reference.includes(`shipping|${wcOrder.shipping_lines[0].method_id}`));
		expect(paysonShippingLine).not.toBeUndefined();

		const paysonShippingPrice = paysonShippingLine.totalPriceIncludingTax;
		expect(Number(wcOrder.shipping_total) + Number(wcOrder.shipping_tax)).toBeCloseTo(paysonShippingPrice, 1);
	}
}

const GetPaysonOrder = async (wcOrder: any, pcClient) => {
	const paysonOrderId = wcOrder.meta_data.find((meta: any) => meta.key === '_payson_checkout_id').value;

	// Get the Payson order.
	const response = await pcClient.get(`Checkouts/${paysonOrderId}`);
	const paysonOrder = await response.json();
	return paysonOrder;
}