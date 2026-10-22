'use strict';
// Renderer fixture, no WordPress database or running site required.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const assert = require('assert/strict');
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        for (const mode of ['none', 'all']) {
            const html = execFileSync('php', ['tests/module-dependencies-php-test.php', process.argv[2], mode, '--fixture'], { encoding: 'utf8' });
            await page.setContent('<style>body{font-family:Arial,sans-serif;background:#f0f0f1;padding:20px}.screen-reader-text{position:absolute;clip-path:inset(50%);height:1px;width:1px;overflow:hidden}' + fs.readFileSync('marrison-addon/assets/css/admin.css', 'utf8') + '</style><main class="marrison-admin-page">' + html + '</main>');
            assert.equal(await page.locator('.marrison-module-card').count(), 26);
            const card = page.locator('[data-module="dynamic_svg"]');
            const statusClass = mode === 'none' ? 'is-missing' : 'is-active';
            await card.locator('.marrison-module-dependency.' + statusClass).filter({ hasText: 'JetEngine' }).waitFor();
            assert.equal(await card.locator('input').isDisabled(), mode === 'none');
            assert.equal(await card.locator('input').isChecked(), mode === 'all');
            const fallback = page.locator('[data-module="query_fallback"]');
            assert.equal(await fallback.locator('.marrison-module-dependency.' + statusClass).count(), 2);
            assert.equal(await fallback.getByText('Necessita di', { exact: false }).count(), 0);
            assert.equal(await fallback.locator('input').isDisabled(), mode === 'none');
            assert.equal(await fallback.locator('input').isChecked(), mode === 'all');
            assert.equal(await page.locator('[data-module="anchor_offset"] input').isChecked(), true);
            await page.setViewportSize({ width: 400, height: 850 });
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Dashboard overflows on narrow view');
            if (process.argv[3]) await fallback.screenshot({ path: process.argv[3] + '/query-fallback-' + mode + '.png' });
            await page.setViewportSize({ width: 1280, height: 900 });
        }
        assert.deepEqual(errors, []);
        console.log('PASS Chrome dashboard fixture: 26 cards, requirement labels, effective toggles, independent modules and narrow layout.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
