/* Actual PHP-rendered module screen + native WordPress CodeMirror assets.
 * NODE_PATH=<bundled node_modules> node tests/php-snippets-browser-test.js <wordpress-root> <fixture.json> <output-dir>
 * Generate fixture with MARRISON_SNIPPETS_HTML=<fixture.json> php tests/php-snippets-php-test.php.
 * Isolated UI fixture, not a deployed WordPress/JetEngine integration test.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const [wpRoot, fixturePath, outputDir] = process.argv.slice(2);
assert(wpRoot && fixturePath && outputDir, 'Provide WordPress root, PHP-rendered fixture and output directory.');
const fixture = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
const plugin = path.join(__dirname, '../marrison-addon');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_BROWSER_PATH || undefined });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent('<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>' + fixture.html + '</body></html>');
        for (const relative of ['wp-includes/css/buttons.min.css', 'wp-admin/css/common.min.css', 'wp-admin/css/forms.min.css', 'wp-admin/css/list-tables.min.css', 'wp-includes/js/codemirror/codemirror.min.css']) {
            await page.addStyleTag({ path: path.join(wpRoot, relative) });
        }
        for (const relative of ['assets/css/admin.css', 'assets/css/admin-php-snippets.css']) {
            await page.addStyleTag({ path: path.join(plugin, relative) });
        }
        await page.addStyleTag({ content: 'body{padding:16px;background:#f0f0f1;} .wrap{margin:0;}' });
        for (const relative of ['wp-includes/js/jquery/jquery.min.js', 'wp-includes/js/underscore.min.js', 'wp-includes/js/codemirror/codemirror.min.js', 'wp-admin/js/code-editor.js']) {
            await page.addScriptTag({ path: path.join(wpRoot, relative) });
        }
        await page.addScriptTag({ content: fixture.script });
        await page.waitForSelector('.CodeMirror');
        assert.equal(await page.locator('.CodeMirror').count(), 1, 'Only one editor should be initialized.');
        const editorResult = await page.evaluate(() => {
            const editor = document.querySelector('.CodeMirror').CodeMirror;
            editor.setValue('add_action("init", function () { return 42; });');
            return { startOpen: editor.getOption('mode').startOpen, type: editor.getTokenTypeAt({ line: 0, ch: 3 }) };
        });
        assert.equal(editorResult.startOpen, true);
        assert(editorResult.type && editorResult.type !== 'tag', 'Code must be highlighted as PHP without an opening tag.');
        await page.fill('#marrison-snippet-name', 'Shortcode di esempio');
        await page.selectOption('#marrison-snippet-scope', 'frontend');
        const form = page.locator('form').filter({ has: page.locator('#marrison-php-snippet-code') });
        await form.evaluate(formElement => {
            formElement.addEventListener('submit', event => {
                event.preventDefault();
                window.snippetSubmission = Object.fromEntries(new FormData(formElement));
            });
        });
        await page.evaluate(() => {
            document.querySelector('#marrison-php-snippet-code').value = '';
            document.querySelector('.CodeMirror').CodeMirror.setValue('add_shortcode("example", function () { return "ok"; });');
        });
        await form.getByRole('button', { name: 'Salva disattivato' }).click();
        const submitted = await page.evaluate(() => window.snippetSubmission);
        assert(submitted && submitted.code.includes('add_shortcode'), 'The editor must synchronize to the textarea before form submission.');
        assert.equal(submitted.scope, 'frontend');
        assert.equal(submitted.operation, 'save');
        assert(submitted._wpnonce.startsWith('valid-'));
        for (const viewport of [{ width: 1280, height: 1100 }, { width: 390, height: 844 }]) {
            await page.setViewportSize(viewport);
            await page.evaluate(() => document.querySelector('.CodeMirror').CodeMirror.refresh());
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            assert.equal(overflow, false, 'The page must not overflow horizontally at ' + viewport.width + 'px.');
            await page.screenshot({ path: path.join(outputDir, 'php-snippets-' + viewport.width + '.png'), fullPage: true });
        }
        assert.deepEqual(errors, []);
        console.log('PHP Snippets UI: PASS (native WordPress CodeMirror, PHP highlighting without tags, synchronized form submission, escaped markup, desktop/mobile layout).');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
