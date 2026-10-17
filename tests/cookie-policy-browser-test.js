/* Run with NODE_PATH for Playwright and an installed WordPress jquery.min.js path argument. */
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const jqueryPath = process.argv[2];
assert(jqueryPath, 'Pass an installed WordPress jquery.min.js path');
const moduleRoot = path.join(__dirname, '../marrison-addon/includes/modules/cookie-manager');
const screenshots = process.env.COOKIE_SCREENSHOTS;
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_BROWSER_PATH || undefined });
    try {
        for (const width of [1246, 768, 390, 320]) {
            for (const enabled of [true, false]) {
                const page = await browser.newPage({ viewport: { width, height: 800 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                let calls = 0;
                const html = execFileSync('php', [path.join(__dirname, 'cookie-policy-php-test.php'), '--fixture', enabled ? 'on' : 'off'], { encoding: 'utf8' });
                await page.route('http://cookie-fixture.local/**', async route => {
                    if (route.request().url().endsWith('/admin-ajax.php')) {
                        calls++;
                        const action = new URLSearchParams(route.request().postData()).get('action');
                        assert.equal(action, 'marrison_get_cookie_list');
                        await route.fulfill({ json: { success: true, data: { categories: { necessary: '<ul><li>wordpress_test_cookie</li></ul>' } } } });
                    } else {
                        await route.fulfill({ contentType: 'text/html', body: html });
                    }
                });
                await page.goto('http://cookie-fixture.local/cookie-policy');
                // Reproduce a theme overriding the button borders and text spacing.
                await page.addStyleTag({ content: 'body{font:14px Arial;margin:0}main{max-width:100%;padding:16px;box-sizing:border-box}button,[type=button]{border:1px solid red!important}h3,p{margin:0 0 20px}' });
                await page.addStyleTag({ path: path.join(moduleRoot, 'assets/css/frontend.css') });
                await page.addScriptTag({ path: jqueryPath });
                await page.evaluate(showBanner => {
                    window.marrisonCookie = { ajaxUrl: 'http://cookie-fixture.local/admin-ajax.php', nonce: 'fixture', loadingText: 'Caricamento cookie...', hasConsent: false, showBanner, closedTriggerModes: { desktop: 'link', tablet: 'link', mobile: 'link' } };
                }, enabled);
                await page.addScriptTag({ path: path.join(moduleRoot, 'assets/js/frontend.js') });
                await page.waitForFunction(() => document.querySelector('#marrison-cookie-modal').parentElement === document.body);
                assert.equal(await page.locator('#marrison-cookie-banner').isVisible(), enabled);
                assert.equal(await page.locator('.marrison-cookie-policy-details').count(), 1);
                assert.equal(await page.locator('.marrison-cookie-policy-table tbody tr').count(), 3);
                const policyText = await page.locator('.marrison-cookie-policy-details').innerText();
                assert(policyText.includes('Sessione') && policyText.includes('Non rilevabile') && policyText.includes('01/12/2026 13:00'), 'Expiration labels or site timezone incorrect');
                assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, `Page overflows at ${width}`);
                if (enabled) {
                    await page.waitForFunction(() => [...document.querySelectorAll('.marrison-banner-buttons .marrison-button')].every(el => getComputedStyle(el).borderTopColor === 'rgb(0, 115, 170)'));
                    for (const id of ['marrison-accept-all', 'marrison-reject-all', 'marrison-customize']) {
                        assert.equal(await page.locator('#' + id).evaluate(el => getComputedStyle(el).borderTopColor), 'rgb(0, 115, 170)');
                        await page.locator('#' + id).hover();
                        assert.equal(await page.locator('#' + id).evaluate(el => getComputedStyle(el).borderTopColor), 'rgb(0, 115, 170)');
                    }
                    if (width > 1024) {
                        const title = await page.locator('.marrison-banner-title').boundingBox();
                        const link = await page.locator('.marrison-banner-links').boundingBox();
                        assert.equal(title.x, link.x, 'Desktop policy link is indented');
                    }
                    if (screenshots) await page.screenshot({ path: path.join(screenshots, `banner-${width}.png`) });
                }
                await page.locator('.marrison-open-cookie-preferences').click();
                await page.waitForFunction(() => document.querySelector('[data-cookie-list="necessary"]').textContent.includes('wordpress_test_cookie'));
                assert(await page.locator('#marrison-cookie-modal').isVisible(), 'Policy link did not open the modal');
                assert.equal(await page.locator('.marrison-category-checkbox:checked').count(), 1, 'Optional cookies preselected');
                await page.locator('#marrison-close-modal').click();
                assert.equal(await page.locator('#marrison-cookie-modal').isVisible(), false);
                assert.equal(calls, 1);
                assert.deepEqual(errors, []);
                await page.context().clearCookies();
                await page.context().addCookies([{ name: 'marrison_cookie_consent', value: 'custom', domain: 'cookie-fixture.local', path: '/' }, { name: 'marrison_cookie_categories', value: 'necessary|analytics', domain: 'cookie-fixture.local', path: '/' }]);
                await page.evaluate(() => window.marrisonCookie.hasConsent = true);
                await page.addScriptTag({ path: path.join(moduleRoot, 'assets/js/frontend.js') });
                await page.waitForFunction(() => document.querySelector('#marrison-cookie-banner').style.display === 'none');
                await page.waitForFunction(() => document.querySelector('.marrison-category-checkbox[data-category="analytics"]').checked);
                await page.locator('.marrison-open-cookie-preferences').click();
                assert(await page.locator('#marrison-cookie-modal').isVisible(), 'Policy link failed after saved consent');
                assert(await page.locator('[data-category="analytics"] input').isChecked(), 'Saved preferences not restored');
                await page.close();
            }
        }
        console.log('PASS browser: policy/modal with banner on/off and saved consent at 1246/768/390/320px, theme border overrides, link alignment, no page overflow or JS errors');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
