import { FrameLocator, Locator, Page, expect } from '@playwright/test';

export class PaysonIFrame {
    readonly page: Page;
    readonly iframe: FrameLocator;

    constructor(page: Page) {
        this.page = page;
        this.iframe = page.frameLocator('#paysonIframe')
    }

    async fillPaysonPaymentDetails() {
        await this.iframe.getByRole('textbox', { name: 'E-mail' }).fill('test.testsson@test.se');
        await this.iframe.getByLabel('Personal ID number').fill('4606082222');
        await this.iframe.getByRole('textbox', { name: 'Zip code' }).fill('99999');
        await this.iframe.locator('#PersonLookupMandatoryPhoneNumber').fill('0720000000');
    }

    async finishOrder() {
        await this.iframe.locator('#SubmitAddress').click();

        await this.iframe.getByRole('radio', { name: 'Bank account' }).click();
        await this.iframe.getByRole('button', { name: 'Complete purchase' }).click();
        await this.page.getByRole('button', { name: 'Simulate Accept' }).click();
    }

    async handleIFrame() {
        await this.page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200); //Is this needed?

        await this.fillPaysonPaymentDetails();

        await this.finishOrder();
    }
}
