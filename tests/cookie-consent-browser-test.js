/* Real HTTP/PHP/browser integration; run with loopback networking available. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const { chromium } = require('playwright');
const wordpress = process.argv[2];
assert(wordpress, 'Pass an installed WordPress root');
const directory = path.join(__dirname, '.cookie-consent-' + process.pid);
fs.mkdirSync(directory);
const optionsFile = path.join(directory, 'options.json');
fs.writeFileSync(optionsFile, JSON.stringify({ marrison_cookie_consent_duration: 30, marrison_cookie_analytics_sources: '/tracking/', marrison_cookie_marketing_sources: '/ads/', marrison_cookie_show_banner: true, marrison_cookie_button_background_color: '#345678', marrison_cookie_button_text_color: '#fefefe', marrison_cookie_privacy_policy_content: '<h1>Privacy Policy</h1><p>Contenuto approvato del titolare.</p>', marrison_cookie_cookie_policy_content: '<h1>Cookie Policy</h1><p>Servizi e finalità approvati.</p>' }));
let server, browser, origin, output = '', debugPage;
const readOptions = () => JSON.parse(fs.readFileSync(optionsFile, 'utf8'));
const setOptions = data => fs.writeFileSync(optionsFile, JSON.stringify({ ...readOptions(), ...data }));
const trackingCount = () => fs.existsSync(path.join(directory, 'tracking.log')) ? fs.readFileSync(path.join(directory, 'tracking.log'), 'utf8').trim().split('\n').filter(Boolean).length : 0;
function receipts() {
    const dir = path.join(directory, 'marrison-cookie/consent');
    return fs.readdirSync(dir).filter(file => /^receipts-/.test(file)).flatMap(file => fs.readFileSync(path.join(dir, file), 'utf8').split('\n').slice(1).filter(Boolean).map(line => JSON.parse(line)));
}
(async () => {
    const listener = net.createServer();
    await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise(resolve => listener.close(resolve));
    origin = 'http://127.0.0.1:' + port;
    server = spawn(process.env.PHP_BINARY || 'php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'cookie-consent-fixture.php')], { env: { ...process.env, COOKIE_TEST_DIRECTORY: directory, COOKIE_TEST_WORDPRESS: wordpress }, windowsHide: true });
    server.stderr.on('data', data => { output += data; }); server.stdout.on('data', data => { output += data; });
    for (let attempt = 0; attempt < 40; attempt++) { try { await fetch(origin + '/privacy'); break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
    browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_BROWSER_PATH || undefined });
    const context = await browser.newContext();
    const page = await context.newPage();
    debugPage = page;
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    // Background verification failures must not add diagnostic text or controls.
    await page.route('**/wp-admin/admin-ajax.php', async route => {
        if (new URLSearchParams(route.request().postData()).get('action') === 'marrison_cookie_consent_state') {
            await route.fulfill({ json: { success: false, data: { message: 'Protezione dell’archivio ricevute non disponibile.' } } });
        } else { await route.continue(); }
    });
    await page.goto(origin + '/page?old_nonce=1');
    await page.locator('#marrison-cookie-banner').waitFor({ state: 'visible' });
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('#marrison-consent-status').innerText(), '');
    assert.equal(await page.locator('.marrison-consent-retry').count(), 0);
    if (process.env.COOKIE_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.COOKIE_SCREENSHOTS, 'cookie-banner-no-diagnostics.png') });
    await page.unroute('**/wp-admin/admin-ajax.php');
    // Reproduce a host that blocks web.config but permits private PHP receipts.
    fs.mkdirSync(path.join(directory, 'marrison-cookie/consent/web.config'), { recursive: true });
    assert.equal(trackingCount(), 0, 'Tracking loaded before any consent');
    assert.equal(await page.locator('#marrison-accept-all').evaluate(el => getComputedStyle(el).backgroundColor), await page.locator('#marrison-reject-all').evaluate(el => getComputedStyle(el).backgroundColor));
    await page.locator('#marrison-accept-all').click();
    await page.waitForFunction(() => window.analyticsRan && window.adsRan);
    assert.equal(receipts().length, 1);
    const first = receipts()[0]; assert.equal(first.event, 'choice');
    assert(first.categories.includes('analytics') && first.categories.includes('marketing'));
    const cookies = await context.cookies();
    const stateCookie = cookies.find(cookie => cookie.name === 'marrison_cookie_state');
    assert(stateCookie.httpOnly && stateCookie.sameSite === 'Lax');
    assert(stateCookie.expires - Date.now() / 1000 >= 180 * 86400 - 15, 'Existing short duration was not raised to six months');
    assert(!JSON.stringify(first).includes('127.0.0.1') && !JSON.stringify(first).includes('PRIVATE_VALUE'));
    await page.reload();
    await page.waitForFunction(() => window.analyticsRan === true);
    assert.equal(await page.locator('#marrison-cookie-banner').isVisible(), false);
    assert.equal(await page.locator('#marrison-widget-button').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(52, 86, 120)', 'Floating widget ignores button color');
    assert.equal(await page.locator('.marrison-widget-icon').evaluate(el => getComputedStyle(el).fill), 'rgb(254, 254, 254)', 'Floating icon ignores button text color');
    if (process.env.COOKIE_SCREENSHOTS) await page.screenshot({ path: path.join(process.env.COOKIE_SCREENSHOTS, 'cookie-floating-colors.png') });
    const beforeReject = trackingCount();
    await page.locator('#marrison-widget-button').click();
    await Promise.all([page.waitForEvent('load'), page.locator('#marrison-reject-all').click()]);
    await page.waitForFunction(() => document.querySelector('#marrison-floating-widget').style.display !== 'none');
    assert.equal(trackingCount(), beforeReject, 'Tracking restarted after refusal/revocation');
    assert.equal(await page.evaluate(() => document.cookie.includes('_ga_fixture=')), false);
    const second = receipts()[1]; assert.equal(second.event, 'withdrawal'); assert.equal(second.previous_receipt, first.receipt_id); assert.equal(second.visitor_id, first.visitor_id);
    assert.deepEqual(second.categories, ['necessary']);
    await page.reload();
    await page.waitForFunction(() => document.querySelector('#marrison-floating-widget').style.display !== 'none');
    assert.equal(await page.locator('#marrison-cookie-banner').isVisible(), false, 'Refusal was solicited again');

    const other = await browser.newContext(); const otherPage = await other.newPage();
    await otherPage.goto(origin + '/page'); await otherPage.locator('#marrison-cookie-banner').waitFor({ state: 'visible' });
    assert.equal(await otherPage.locator('#marrison-close-banner').count(), 0, 'Removed banner close control is still present');
    await otherPage.locator('#marrison-reject-all').click(); await otherPage.locator('#marrison-cookie-banner').waitFor({ state: 'hidden' });
    assert.notEqual(receipts()[2].visitor_id, first.visitor_id, 'Two browsers behind the same IP shared a consent record');
    await other.close();

    const custom = await browser.newContext(); const customPage = await custom.newPage();
    debugPage = customPage;
    await customPage.goto(origin + '/page'); await customPage.locator('#marrison-cookie-banner').waitFor({ state: 'visible' });
    await customPage.locator('#marrison-customize').click();
    await customPage.locator('.marrison-cookie-category[data-category="analytics"] .marrison-switch').click();
    assert.equal(await customPage.locator('.marrison-category-checkbox[data-category="analytics"]').isChecked(), true);
    await customPage.locator('#marrison-save-preferences').click();
    await customPage.waitForFunction(() => window.analyticsRan === true);
    assert.equal(await customPage.evaluate(() => !!window.adsRan), false, 'Marketing ran with analytics-only consent');
    const customCookies = await custom.cookies();
    const copied = customCookies.filter(cookie => cookie.name !== 'marrison_cookie_state').map(cookie => ({ name: cookie.name, value: cookie.value, url: origin }));
    const forged = await browser.newContext(); await forged.addCookies(copied); const forgedPage = await forged.newPage();
    const beforeForged = trackingCount();
    await forgedPage.goto(origin + '/page'); await forgedPage.locator('#marrison-cookie-banner').waitFor({ state: 'visible' });
    assert.equal(trackingCount(), beforeForged, 'Unsigned compatibility cookies enabled tracking');
    await forged.close();
    setOptions({ marrison_cookie_policy_revision: '2' });
    const staleClick = await custom.request.post(origin + '/wp-admin/admin-ajax.php', { headers: { Origin: origin }, form: { action: 'marrison_save_consent', nonce: 'valid', consent_type: 'accept_all', revision: first.policy_revision } });
    assert.equal((await staleClick.json()).success, false, 'A stale notice version was accepted');
    await customPage.reload(); await customPage.locator('#marrison-cookie-banner').waitFor({ state: 'visible' });
    assert.equal(await customPage.evaluate(() => !!window.analyticsRan), false, 'Old revision enabled tracking');
    setOptions({ fixture_upload_error: true });
    const countBeforeFailure = receipts().length;
    await customPage.locator('#marrison-accept-all').click();
    await customPage.locator('#marrison-consent-status').filter({ hasText: 'Non è stato possibile salvare le preferenze' }).waitFor();
    assert.equal(await customPage.locator('.marrison-consent-retry').count(), 0);
    assert.equal(receipts().length, countBeforeFailure); assert.equal(await customPage.evaluate(() => !!window.analyticsRan), false);
    assert.equal(await customPage.locator('#marrison-accept-all').isEnabled(), true, 'Write failure left the consent button disabled');
    setOptions({ fixture_upload_error: false });
    await customPage.locator('#marrison-accept-all').click(); await customPage.waitForFunction(() => window.analyticsRan === true);
    const csrf = await custom.request.post(origin + '/wp-admin/admin-ajax.php', { headers: { Origin: 'https://outside.example' }, form: { action: 'marrison_save_consent', nonce: 'valid', consent_type: 'accept_all' } });
    assert.equal(csrf.status(), 403, 'Cross-site consent request accepted');
    await custom.close();

    if (process.env.COOKIE_BANNER_ONLY === '1') {
        setOptions({marrison_cookie_cookie_policy_url: origin + '/cookie-policy'});
        for (const width of [1280, 390]) {
            const fresh = await browser.newContext({viewport: {width, height: 900}}), screen = await fresh.newPage();
            await screen.goto(origin + '/page'); await screen.locator('#marrison-cookie-banner').waitFor({state: 'visible'});
            const styles = await screen.locator('#marrison-accept-all, #marrison-reject-all').evaluateAll(items => items.map(el => {
                const css = getComputedStyle(el); return {background: css.backgroundColor, color: css.color, font: css.font, height: el.getBoundingClientRect().height};
            }));
            assert.deepEqual(styles[0], styles[1], 'Accept/reject do not have equal prominence');
            assert.equal(await screen.locator('.marrison-banner-link').filter({hasText: 'Cookie Policy'}).count(), 1);
            const beforeScroll = receipts().length; await screen.evaluate(() => window.scrollBy(0, 500)); await screen.waitForTimeout(100);
            assert.equal(receipts().length, beforeScroll, 'Scroll recorded consent');
            await screen.locator('#marrison-customize').click();
            assert.equal(await screen.locator('.marrison-category-checkbox:not(:disabled):checked').count(), 0, 'Optional categories preselected');
            if (process.env.COOKIE_SCREENSHOTS) { await screen.screenshot({path: path.join(process.env.COOKIE_SCREENSHOTS, 'cookie-consent-' + width + '.png')}); }
            await fresh.close();
        }
        await context.close(); assert.deepEqual(errors, []);
        assert(!/PHP (?:Fatal|Warning|Deprecated)/.test(output), output);
        console.log('PASS PHP/Chrome banner: signed choices/refusal/custom/revocation/expiry, equal accept/reject at 1280/390, policy link, no scroll consent and no preselected optional categories');
        return;
    }
    const admin = await browser.newContext({ extraHTTPHeaders: { 'X-Fixture-Admin': '1' } });
    const exported = await admin.request.post(origin + '/wp-admin/admin-ajax.php', { form: { action: 'marrison_cookie_consent_receipts', nonce: 'valid', date: new Date().toISOString().slice(0, 10), offset: '0' } });
    const exportedData = await exported.json();
    assert(exportedData.success, JSON.stringify(exportedData)); assert.equal(exportedData.data.receipts.length, receipts().length);
    assert(exportedData.data.receipts.every(receipt => receipt.integrity_valid));
    assert(Object.keys(exportedData.data.snapshots).length >= 2);
    setOptions({ fixture_salt: 'rotated-wordpress-auth-key' });
    const afterRotation = await admin.request.post(origin + '/wp-admin/admin-ajax.php', { form: { action: 'marrison_cookie_consent_receipts', nonce: 'valid' } });
    assert((await afterRotation.json()).data.receipts.every(receipt => receipt.integrity_valid), 'WordPress auth-key rotation invalidated historical evidence');
    const adminPage = await admin.newPage();
    debugPage = adminPage;
    setOptions({ marrison_cookie_privacy_profile: { controller_name: 'Example Controller', controller_address: 'Via Esempio 1, Italia', controller_email: 'privacy@example.test' } });
    await adminPage.goto(origin + '/wp-admin/admin.php?page=marrison-cookie&tab=policies');
    assert((await adminPage.locator('.marrison-privacy-profile').innerText()).includes('Tutti i campi sono facoltativi'));
    assert.equal(await adminPage.locator('.marrison-privacy-profile [required]').count(), 0);
    assert.equal(await adminPage.locator('.marrison-profile-status').count(), 0);
    assert.equal(await adminPage.locator('.marrison-privacy-profile label').filter({hasText: '*'}).count(), 0);
    if (process.env.COOKIE_SCREENSHOTS) await adminPage.screenshot({ path: path.join(process.env.COOKIE_SCREENSHOTS, 'cookie-privacy-optional-fields.png'), fullPage: true });
    // Save the partial profile without filling recipients, transfers or log retention.
    await Promise.all([adminPage.waitForEvent('load'), adminPage.locator('[name="marrison_save_settings"]').click()]);
    assert.equal(await adminPage.locator('[data-privacy-field="controller_name"]').inputValue(), 'Example Controller');
    const privacyHtml = await (await admin.request.get(origin + '/privacy')).text();
    assert(privacyHtml.includes('Example Controller') && !privacyHtml.includes('non è ancora stata completata'));
    assert(!privacyHtml.includes('4. Trasferimenti internazionali') && !privacyHtml.includes('artt. 46'));
    for (const width of [1246, 390]) {
        await adminPage.setViewportSize({ width, height: 950 });
        await adminPage.goto(origin + '/wp-admin/admin.php?page=marrison-cookie&tab=policies');
        assert.equal(await adminPage.locator('[data-privacy-field="controller_name"]').inputValue(), readOptions().marrison_cookie_privacy_profile.controller_name);
        if (process.env.COOKIE_SCREENSHOTS) await adminPage.screenshot({ path: path.join(process.env.COOKIE_SCREENSHOTS, 'cookie-policies-' + width + '.png'), fullPage: true });
        assert.equal(await adminPage.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'Policy settings overflow');
    }
    for (const width of [1246, 390]) {
        await adminPage.setViewportSize({ width, height: 950 });
        await adminPage.goto(origin + '/wp-admin/admin.php?page=marrison-cookie&tab=scanner');
        const padding = await adminPage.locator('.marrison-scanner-report').evaluate(el => parseFloat(getComputedStyle(el).paddingLeft));
        assert(padding >= 14, 'Scanner report is flush against the card');
        assert.equal(await adminPage.locator('#marrison-scan-resume').isVisible(), false);
        assert.equal(await adminPage.locator('#marrison-scan-cancel').isVisible(), false);
        if (process.env.COOKIE_SCREENSHOTS) await adminPage.screenshot({ path: path.join(process.env.COOKIE_SCREENSHOTS, 'cookie-scanner-spacing-' + width + '.png'), fullPage: true });
    }
    await adminPage.goto(origin + '/wp-admin/admin.php?page=marrison-cookie&tab=receipts');
    await adminPage.locator('#marrison-receipts-load').click(); await adminPage.locator('#marrison-receipts-result').filter({ hasText: 'Ricevuta e informativa integre' }).waitFor();
    const download = adminPage.waitForEvent('download'); await adminPage.locator('#marrison-receipts-export').click();
    const report = await download; assert(report.suggestedFilename().startsWith('cookie-consent-'));
    assert.equal(await adminPage.locator('[name="marrison_save_settings"]').count(), 0);
    await admin.close(); await context.close();
    assert.deepEqual(errors, []);
    assert(!/PHP (?:Fatal|Warning|Deprecated)/.test(output), output);
    console.log('PASS real PHP/Chrome consent: signed HttpOnly state, >=6 months, accept/reject/X/custom/revoke/reload, same-IP isolation, stale/tampered states, cache-safe HTML, nonce refresh, write failure, CSRF guard, private proof export and policy snapshots, key rotation, policy UI 1246/390px');
})().catch(async error => { console.error(error); if (debugPage && !debugPage.isClosed()) console.error((await debugPage.content()).slice(0, 2000)); console.error(output.slice(-3500)); process.exitCode = 1; }).finally(async () => {
    if (browser) await browser.close(); if (server) server.kill();
    // Only this explicitly-created test directory is disposable.
    const absolute = path.resolve(directory); assert(absolute.startsWith(path.resolve(__dirname) + path.sep + '.cookie-consent-'));
    fs.rmSync(absolute, { recursive: true, force: true });
});
