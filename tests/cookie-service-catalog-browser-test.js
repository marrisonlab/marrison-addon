/** NODE_PATH must include Playwright. Optional argv[2]: screenshot output path. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const { chromium } = require('playwright');
(async () => {
    const html = execFileSync('php', [path.join(__dirname, 'cookie-service-catalog-php-test.php'), '--admin-html'], { encoding: 'utf8' });
    const browser = await chromium.launch({ headless: true, ...(process.env.BROWSER_EXECUTABLE ? { executablePath: process.env.BROWSER_EXECUTABLE } : {}) });
    try {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        await page.setContent(html);
        assert.equal(await page.locator('select').count(), 12);
        assert.equal(await page.locator('#service-stripe').inputValue(), 'necessary');
        await page.locator('#service-paypal').selectOption('marketing');
        assert.equal(await page.locator('#service-paypal').inputValue(), 'marketing');
        assert(await page.getByRole('heading', { name: 'Catalogo servizi e classificazione' }).isVisible());
        assert(await page.locator('.marrison-category-uncategorized').first().isVisible());
        assert.equal(await page.locator('a[href^="javascript:"]').count(), 0);
        if (process.argv[2]) { await page.screenshot({ path: process.argv[2], fullPage: true }); }
        console.log('PASS admin catalog browser fixture: 12 labeled selects, selected necessary payment, editable categories, visible review badge and safe links');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
