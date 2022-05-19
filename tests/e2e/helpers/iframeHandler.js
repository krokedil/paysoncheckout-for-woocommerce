// Clear all Checkout input field values
const clearCheckoutFields = async (page) => {

	const elementHandle = await page.waitForSelector('iframe[id="paysonIframe"]');
	const frame = await elementHandle.contentFrame();

	// Fill out Customer form.
	await frame.$eval('#PersonLookupEmail', el => el.value = '');
	await frame.$eval('#PersonIdentityNumber', el => el.value = '');
	await frame.$eval('#PersonLookupPostalCode', el => el.value = '');
	await frame.$eval('#PersonLookupMandatoryPhoneNumber', el => el.value = '');

}

export default {
	clearCheckoutFields
}