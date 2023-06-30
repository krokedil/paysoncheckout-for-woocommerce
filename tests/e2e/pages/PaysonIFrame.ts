import { FrameLocator, Locator, Page, expect } from '@playwright/test';

export class PaysonIFrame {
    readonly page: Page;
    readonly iframe: FrameLocator;

    constructor(page: Page) {
        this.page = page;
        this.iframe = page.frameLocator('#paysonIframe')
    }

    async fillPaysonPaymentDetails() {
        // Fill in the payson iframe with the required information.
        await this.iframe.getByRole('textbox', { name: 'E-mail' }).fill('test.testsson@test.se');
        await this.iframe.getByLabel('Personal ID number').fill('4606082222');
        await this.iframe.getByRole('textbox', { name: 'Zip code' }).fill('99999');
        await this.iframe.locator('#PersonLookupMandatoryPhoneNumber').fill('0720000000');
    }

    async finishOrder() {
        await this.iframe.locator('#SubmitAddress').click();

        // Paysoncheckout will trigger a update twice after the addess is submitted. Once for the address being changed event, and one when the address fields are populated in WooCommerce
        await this.page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
        await this.page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);

        // Select the bank account payment method and complete the purchase.
        await this.iframe.getByRole('radio', { name: 'Bank account' }).click();
        await this.iframe.getByRole('button', { name: 'Complete purchase' }).click();

        // Simulate the accept button being clicked landing on the mock page.
        await this.page.getByRole('button', { name: 'Simulate Accept' }).click();
    }

    async handleIFrame() {
        await this.fillPaysonPaymentDetails();

        await this.finishOrder();
    }
}
