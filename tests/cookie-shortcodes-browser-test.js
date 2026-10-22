/* Run with NODE_PATH for Playwright: node tests/cookie-shortcodes-browser-test.js <wordpress-root>. */
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const wordpress = process.argv[2];
assert(wordpress, 'Pass an installed WordPress root');
const moduleRoot = path.join(__dirname, '../marrison-addon/includes/modules/cookie-manager');
const jquery = path.join(wordpress, 'wp-includes/js/jquery/jquery.min.js');
const fixture = mode => execFileSync('php', [path.join(__dirname, 'cookie-shortcodes-php-test.php'), wordpress, mode], { encoding: 'utf8' });
(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_BROWSER_PATH || undefined });
    try {
        for (const width of [1246, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            const requests = [];
            let failSave = true;
            await page.route('http://cookie-fixture.local/**', async route => {
                if (route.request().url().endsWith('/admin-ajax.php')) {
                    const data = new URLSearchParams(route.request().postData());
                    requests.push(Object.fromEntries(data));
                    await route.fulfill({ json: failSave ? { success: false, data: { message: 'Salvataggio non riuscito' } } : { success: true, data: { redirect_url: 'http://cookie-fixture.local/settings' } } });
                } else {
                    await route.fulfill({ contentType: 'text/html', body: fixture('--fixture-wizard') });
                }
            });
            await page.goto('http://cookie-fixture.local/wizard');
            await page.addStyleTag({ content: 'body{font:14px Arial;margin:0}' });
            await page.addStyleTag({ path: path.join(moduleRoot, 'assets/css/admin.css') });
            await page.addStyleTag({ path: path.join(moduleRoot, 'assets/css/wizard.css') });
            await page.addScriptTag({ path: jquery });
            await page.evaluate(() => {
                window.marrisonWizard = { ajaxUrl: 'http://cookie-fixture.local/admin-ajax.php', nonce: 'fixture', currentStep: '6', totalSteps: '6', shouldOpen: true, stepText: 'Step {step} di {total}', completeText: 'Completa', nextText: 'Avanti', finishErrorText: 'Errore', connectionErrorText: 'Connessione non riuscita' };
            });
            await page.addScriptTag({ path: path.join(moduleRoot, 'assets/js/wizard.js') });
            const step = page.locator('.marrison-wizard-step[data-step="6"]');
            await step.waitFor({ state: 'visible' });
            assert.equal(await step.locator('input[type="checkbox"]').count(), 0);
            for (const shortcode of ['[marrison_privacy_policy]', '[marrison_cookie_policy_content]', '[marrison_cookie_policy]']) {
                assert(await step.locator('.marrison-shortcode-value').evaluateAll((inputs, value) => inputs.some(input => input.value === value), shortcode));
            }
            await page.locator('#wizard_privacy_policy_url').fill('https://example.test/privacy-elementor');
            await page.locator('#wizard_cookie_policy_url').fill('https://example.test/cookie-elementor');
            if (process.env.COOKIE_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.COOKIE_SCREENSHOTS, `cookie-shortcodes-wizard-${width}.png`) });
            assert.equal(await page.locator('.marrison-wizard-wrapper').evaluate(el => el.scrollWidth > el.clientWidth), false, 'Wizard overflows');
            await page.locator('#wizard_next').click();
            await page.locator('#wizard_policy_status').filter({ hasText: 'Salvataggio non riuscito' }).waitFor({ state: 'visible' });
            assert(await step.isVisible(), 'Failed save completed the wizard');
            failSave = false;
            await page.locator('#wizard_next').click();
            await page.locator('.marrison-wizard-step[data-step="completed"]').waitFor({ state: 'visible' });
            assert.equal(requests.length, 2);
            for (const request of requests) {
                assert.equal(request.action, 'marrison_wizard_finish');
                assert.equal(request['data[privacy_policy_url]'], 'https://example.test/privacy-elementor');
                assert.equal(request['data[cookie_policy_url]'], 'https://example.test/cookie-elementor');
            }
            assert.deepEqual(errors, []);
            await page.close();

            const policy = await browser.newPage({ viewport: { width, height: 900 } });
            const policyErrors = [];
            policy.on('pageerror', error => policyErrors.push(error.message));
            await policy.route('http://cookie-fixture.local/**', async route => {
                if (route.request().url().endsWith('/admin-ajax.php')) {
                    const data = new URLSearchParams(route.request().postData());
                    if (data.get('action') === 'marrison_cookie_consent_state') {
                        await route.fulfill({ json: { success: true, data: { state: { valid: false, type: null, categories: ['necessary'], expires: null, revision: 'fixture' } } } });
                        return;
                    }
                    assert.equal(data.get('action'), 'marrison_get_cookie_list');
                    await route.fulfill({ json: { success: true, data: { categories: { necessary: '<ul><li>current_scan</li></ul>' } } } });
                } else {
                    await route.fulfill({ contentType: 'text/html', body: fixture('--fixture-policy') });
                }
            });
            await policy.goto('http://cookie-fixture.local/policy');
            await policy.addStyleTag({ content: 'body{font:14px Arial;margin:0}main{padding:16px;box-sizing:border-box}' });
            await policy.addStyleTag({ path: path.join(moduleRoot, 'assets/css/frontend.css') });
            await policy.addScriptTag({ path: jquery });
            await policy.evaluate(() => {
                window.marrisonCookie = { ajaxUrl: 'http://cookie-fixture.local/admin-ajax.php', nonce: 'fixture', loadingText: 'Caricamento...', hasConsent: false, showBanner: '', closedTriggerModes: { desktop: 'link', tablet: 'link', mobile: 'link' } };
            });
            await policy.addScriptTag({ path: path.join(moduleRoot, 'assets/js/frontend.js') });
            assert.equal(await policy.locator('.marrison-cookie-policy-content').count(), 1);
            assert.equal(await policy.locator('.marrison-cookie-policy-details').count(), 1);
            assert.equal(await policy.locator('#marrison-cookie-banner').isVisible(), false);
            assert.equal(await policy.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'Policy overflows');
            await policy.locator('.marrison-open-cookie-preferences').click();
            await policy.waitForFunction(() => document.querySelector('[data-cookie-list="necessary"]').textContent.includes('current_scan'));
            assert(await policy.locator('#marrison-cookie-modal').isVisible(), 'Full policy cannot open preferences with banner disabled');
            assert.deepEqual(policyErrors, []);
            await policy.close();
        }
        for (const width of [1246, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.route('https://cookie-fixture.local/**', route => route.fulfill({ contentType: 'text/html', body: fixture('--fixture-shortcodes') }));
            await page.goto('https://cookie-fixture.local/shortcodes');
            await page.addStyleTag({ path: path.join(moduleRoot, 'assets/css/admin.css') });
            await page.addScriptTag({ path: jquery });
            await page.addScriptTag({ path: path.join(moduleRoot, 'assets/js/admin.js') });
            assert.equal((await page.locator('.nav-tab-active').innerText()).trim(), 'Shortcode');
            assert.equal(await page.locator('[name="marrison_save_settings"]').count(), 0);
            const codes = ['[marrison_privacy_policy]', '[marrison_cookie_policy_content]', '[marrison_cookie_policy]'];
            for (let index = 0; index < codes.length; index++) {
                const row = page.locator('.marrison-shortcode-item').nth(index);
                assert.equal(await row.locator('input').inputValue(), codes[index]);
                await page.evaluate(() => {
                    window.copiedText = null;
                    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: text => { window.copiedText = text; return Promise.resolve(); } } });
                });
                await row.locator('button').click();
                await row.locator('[role="status"]').filter({ hasText: 'Copiato!' }).waitFor();
                assert.equal(await page.evaluate(() => window.copiedText), codes[index]);
            }
            await page.evaluate(() => {
                navigator.clipboard.writeText = () => Promise.reject(new Error('Permission denied'));
                document.execCommand = () => false;
            });
            const row = page.locator('.marrison-shortcode-item').first();
            await row.locator('button').click();
            await row.locator('[role="status"]').filter({ hasText: 'Copia non riuscita' }).waitFor();
            assert.equal(await row.locator('input').evaluate(input => input.selectionEnd - input.selectionStart), codes[0].length);
            assert.equal(await row.locator('button').isEnabled(), true);
            if (process.env.COOKIE_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.COOKIE_SCREENSHOTS, `cookie-shortcodes-tab-${width}.png`) });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Shortcode tab overflows');
            await page.close();
        }
        console.log('PASS browser: policy shortcodes and wizard at 1246/390px, saved URLs, failed-save recovery, no page-creation requests, full policy preferences with banner disabled, no overflow or JS errors');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
