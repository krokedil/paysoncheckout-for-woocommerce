import { FrameLocator, Locator, Page, expect } from '@playwright/test';

export class PaysonIFrame {
    readonly page: Page;
    readonly iframe: FrameLocator;

    constructor(page: Page) {
        this.page = page;
        this.iframe = page.frameLocator('#paysonIframe')
    }

    async fillPaymentDetailsB2C() {
        // Fill in the payson iframe with the required information.
        await this.iframe.getByRole('textbox', { name: 'E-mail' }).fill('test.testsson@test.se');
        await this.iframe.getByLabel('Personal ID number').fill('4606082222');
        await this.iframe.getByRole('textbox', { name: 'Zip code' }).fill('99999');
        await this.iframe.locator('#PersonLookupMandatoryPhoneNumber').fill('0720000000');
    }
    async fillPaymentDetailsB2B() {
        await this.iframe.locator('#slider').click(); // Click button that changes to B2B

        // Fill in details
        await this.iframe.locator('#CompanyLookupEmail').fill('test.testsson@test.se');
        await this.iframe.locator('#form2').getByText('Company registration number').fill('4608142222');
        await this.iframe.locator('#CompanyLookupMandatoryPhoneNumber').fill('0720000000');
        await this.iframe.locator('#CompanyLookupReferenceFirstName').fill('test');
        await this.iframe.locator('#CompanyLookupReferenceLastName').fill('testsson');
    }

    async finishOrder(b2b: boolean) {
        await this.iframe.locator('#SubmitAddress').click();

        if ( b2b ) { // Extra options can pop up
            await this.iframe.getByText('Persson, Tess T, c/o Eriksson, Erik Testgatan 1, 99999 Stan').first().click();
            await this.iframe.getByRole('button', { name: 'Continue' }).click();
        }

        // Paysoncheckout will trigger a update twice after the addess is submitted. Once for the address being changed event, and one when the address fields are populated in WooCommerce
        await this.page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);
        await this.page.waitForResponse(response => response.url().includes('pco_wc_update_checkout') && response.status() === 200);

        // Select the bank account payment method and complete the purchase.
        await this.iframe.getByRole('radio', { name: 'Bank account' }).click();
        await this.iframe.getByRole('button', { name: 'Complete purchase' }).click();

        // Simulate the accept button being clicked landing on the mock page.
        await this.page.getByRole('button', { name: 'Simulate Accept' }).click();
    }

    async handleIFrame(skipDetails: boolean = false, b2b: boolean = false) { 
        if ( !skipDetails ) { // B2B checkout and simultaneously skipping details is not supported
            if (!b2b) await this.fillPaymentDetailsB2C();
            else await this.fillPaymentDetailsB2B();
        }
        else {
            await this.iframe.getByRole('link', { name: 'Continue without social security number' }).click(); // Do not use personal number, use the prefilled details instead
        }

        await this.finishOrder(b2b);
    }
}
