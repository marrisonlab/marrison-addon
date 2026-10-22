'use strict';
// Standalone browser fixtures; requires Playwright and a local Chrome installation.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const assert = require('assert/strict');
const html = execFileSync('php', [path.join(__dirname, 'listing-columns-php-test.php'), '--fixture'], { encoding: 'utf8' });
const toolbar = '#toolbar .marrison-listing-columns';
const grid = '#catalogo > .jet-listing-grid > .jet-listing-grid__items';
const key = 'marrison-listing-columns:/catalogo:#catalogo';

(async () => {
	const browser = await chromium.launch({ channel: 'chrome', headless: true });
	try {
		const context = await browser.newContext({ viewport: { width: 1280, height: 1000 } });
		await context.route('http://listing-columns.test/**', route => route.fulfill({ contentType: 'text/html', body: html }));
		const page = await context.newPage();
		const errors = [];
		page.on('pageerror', error => errors.push(error.message));
		async function columns(expected) {
			await page.waitForFunction(({ grid, expected }) => getComputedStyle(document.querySelector(grid)).gridTemplateColumns.split(' ').length === expected, { grid, expected });
		}
		await page.goto('http://listing-columns.test/catalogo');
		await columns(3);
		await page.locator(toolbar + ' [data-cols="4"]').click();
		await columns(4);
		assert.equal(await page.locator('#second [data-cols="4"]').getAttribute('aria-pressed'), 'true', 'Shared toolbars must sync.');
		assert.equal(await page.evaluate(key => localStorage.getItem(key), key), '4');
		await page.reload();
		await columns(4);
		await page.setViewportSize({ width: 600, height: 1000 });
		await page.waitForFunction(toolbar => document.querySelector(toolbar).hidden, toolbar);
		assert.equal(await page.locator(grid).evaluate(node => getComputedStyle(node).display), 'flex', 'Mobile must restore native layout.');
		assert.equal(await page.locator(grid).getAttribute('data-marrison-listing-columns-active'), null);
		await page.setViewportSize({ width: 1280, height: 1000 });
		await columns(4);
		// AJAX replaces the grid with fresh markup.
		await page.locator(grid).evaluate(node => { const clone = node.cloneNode(true); clone.removeAttribute('style'); clone.removeAttribute('data-marrison-listing-columns-active'); node.replaceWith(clone); });
		await columns(4);
		// A nested listing must keep its own column count.
		await page.locator(grid + ' > article').first().evaluate(node => { node.insertAdjacentHTML('beforeend', '<div class="jet-listing-grid"><div id="nested" class="jet-listing-grid__items"><div class="jet-listing-grid__item">Nested</div></div></div>'); });
		await page.waitForTimeout(50);
		assert.equal(await page.locator('#nested').getAttribute('data-marrison-listing-columns-active'), null);
		await page.locator('#nested').evaluate(node => node.parentElement.remove());
		// JetEngine's native masonry, carousel and scroll slider wrappers stay untouched.
		for (const className of ['jet-listing-grid__masonry', 'jet-listing-grid__slider', 'jet-listing-grid__scroll-slider']) {
			await page.locator('#catalogo > .jet-listing-grid').evaluate((node, className) => node.classList.add(className), className);
			// Trigger the same child-list notification as a JetEngine rerender.
			await page.locator(grid).evaluate(node => node.append(document.createComment('rerender')));
			await page.waitForFunction(grid => !document.querySelector(grid).hasAttribute('data-marrison-listing-columns-active'), grid);
			assert.equal(await page.locator(toolbar + ' button').first().isDisabled(), true);
			await page.locator('#catalogo > .jet-listing-grid').evaluate((node, className) => node.classList.remove(className), className);
			await page.locator(grid).evaluate(node => node.append(document.createComment('restore')));
			await columns(4);
		}
		// Invalid targets disable controls without exceptions; a later valid target recovers.
		await page.locator(toolbar).evaluate(node => { const config = JSON.parse(node.dataset.marrisonListingColumns); config.selector = '['; node.dataset.marrisonListingColumns = JSON.stringify(config); });
		await page.waitForFunction(toolbar => document.querySelector(toolbar + ' button').disabled, toolbar);
		await page.locator(toolbar).evaluate(node => { const config = JSON.parse(node.dataset.marrisonListingColumns); config.selector = '#catalogo'; node.dataset.marrisonListingColumns = JSON.stringify(config); });
		await page.waitForFunction(toolbar => !document.querySelector(toolbar + ' button').disabled, toolbar);
		await page.locator(toolbar + ' [data-cols="2"]').focus();
		await page.keyboard.press('Enter');
		await columns(2);
		assert.equal(await page.locator(toolbar + ' [data-cols="2"]').evaluate(node => getComputedStyle(node).outlineStyle), 'solid', 'Keyboard focus must be visible.');
		// External storage updates synchronize active controls and the actual layout.
		await page.evaluate(({ key }) => dispatchEvent(new StorageEvent('storage', { key, newValue: '3' })), { key });
		await columns(3);
		// Each page has a separate preference.
		await page.goto('http://listing-columns.test/altro-catalogo');
		await columns(3);
		await page.goto('http://listing-columns.test/catalogo');
		await columns(2);
		// Remove all toolbars: original layout and inline custom-property state must return.
		await page.locator('[data-marrison-listing-columns]').evaluateAll(nodes => nodes.forEach(node => node.remove()));
		await page.waitForFunction(grid => !document.querySelector(grid).hasAttribute('data-marrison-listing-columns-active'), grid);
		assert.equal(await page.locator(grid).evaluate(node => node.style.getPropertyValue('--marrison-listing-columns')), '');
		await page.reload();
		// Editor rerenders replace buttons in-place, then update the initial column count.
		await page.evaluate(() => { window.elementorFrontend = { isEditMode: () => true }; document.querySelector('#second').remove(); });
		await page.locator(toolbar).evaluate(node => { const config = JSON.parse(node.dataset.marrisonListingColumns); config.defaultColumns = 4; config.profiles[0].defaultColumns = 4; node.innerHTML = node.innerHTML; node.dataset.marrisonListingColumns = JSON.stringify(config); });
		await columns(4);
		await page.locator(toolbar + ' [data-cols="3"]').click();
		await columns(3);
		assert.equal(await page.evaluate(key => localStorage.getItem(key), key), '2', 'Editor must not save preferences.');
		await page.reload();
		await page.locator(toolbar + ' [data-cols="3"]').click();
		await columns(3);
		// Different enabled ranges on two toolbars must still reflect the actual grid.
		await page.locator('#second [data-cols="4"]').evaluate(node => node.remove());
		await page.waitForTimeout(50);
		await page.locator(toolbar + ' [data-cols="4"]').click();
		await columns(4);
		assert.equal(await page.locator('#second [aria-pressed="true"]').count(), 0);
		await page.locator(toolbar + ' [data-cols="3"]').click();
		await columns(3);
		assert.equal(await page.locator('#second [data-cols="3"]').getAttribute('aria-pressed'), 'true');
		if (process.argv.includes('--screenshot')) {
			const directory = path.join(__dirname, '../audit/2026-10-21/listing-columns');
			fs.mkdirSync(directory, { recursive: true });
			await page.waitForTimeout(250); // Let the button color transition finish for visual QA.
			await page.screenshot({ path: path.join(directory, 'desktop.png'), fullPage: true });
			await page.setViewportSize({ width: 390, height: 844 });
			await page.waitForFunction(toolbar => document.querySelector(toolbar).hidden, toolbar);
			await page.screenshot({ path: path.join(directory, 'mobile.png'), fullPage: true });
		}
		assert.deepEqual(errors, []);
		await context.close();
		// Responsive settings follow the active Elementor breakpoint widths, including optional devices.
		const responsiveHtml = execFileSync('php', [path.join(__dirname, 'listing-columns-php-test.php'), '--responsive-fixture'], { encoding: 'utf8' });
		const responsive = await browser.newContext({ viewport: { width: 1600, height: 1000 } });
		await responsive.route('http://listing-columns.test/**', route => route.fulfill({ contentType: 'text/html', body: responsiveHtml }));
		const responsivePage = await responsive.newPage();
		responsivePage.on('pageerror', error => errors.push(error.message));
		async function responsiveState(width, expected, options) {
			await responsivePage.setViewportSize({ width, height: 1000 });
			await responsivePage.waitForFunction(({ grid, expected, toolbar, options }) => {
				const actual = getComputedStyle(document.querySelector(grid)).gridTemplateColumns.split(' ').length;
				const visible = Array.from(document.querySelectorAll(toolbar + ' button')).filter(button => getComputedStyle(button).display !== 'none').map(button => Number(button.dataset.cols));
				return actual === expected && JSON.stringify(visible) === JSON.stringify(options);
			}, { grid, expected, toolbar, options });
			assert.equal(await responsivePage.locator(toolbar + ' [aria-pressed="true"]').getAttribute('data-cols'), String(expected));
			assert.equal(await responsivePage.locator('#second [aria-pressed="true"]').getAttribute('data-cols'), String(expected));
			assert.equal(await responsivePage.locator(toolbar).isVisible(), true);
		}
		await responsivePage.goto('http://listing-columns.test/catalogo');
		await responsiveState(1600, 4, [2, 3, 4, 5, 6]);
		// A site's button display rules must not reveal options rendered for another breakpoint.
		await responsivePage.addStyleTag({ content: '#toolbar .marrison-listing-columns__button, #second .marrison-listing-columns__button { display: inline-flex !important; }' });
		assert.equal(await responsivePage.locator(toolbar + ' [data-cols="1"]').isVisible(), false, 'A button below the active minimum must stay invisible even when theme CSS overrides display.');
		assert.equal(await responsivePage.locator('#second [data-cols="1"]').isVisible(), false, 'Each synchronized toolbar must filter its own visible buttons.');
		// The same script must also work if a cached stylesheet predates the button[hidden] rule.
		await responsivePage.locator('style').first().evaluate(node => { node.textContent = node.textContent.replace(/\.marrison-listing-columns \.marrison-listing-columns__button\[hidden\]\s*\{[^}]*\}/g, ''); });
		assert.equal(await responsivePage.locator(toolbar + ' [data-cols="1"]').isVisible(), false);
		await responsivePage.locator(toolbar + ' [data-cols="6"]').click();
		await responsiveState(1600, 6, [2, 3, 4, 5, 6]);
		await responsiveState(1100, 2, [1, 2, 3]);
		await responsivePage.locator(toolbar + ' [data-cols="3"]').click();
		await responsiveState(1100, 3, [1, 2, 3]);
		await responsiveState(1101, 4, [2, 3, 4, 5]); // Laptop inherits desktop initial columns, not its stored choice.
		await responsiveState(900, 2, [1, 2, 3]); // Mobile extra inherits tablet's configured range/default.
		await responsiveState(680, 1, [1, 2]);
		assert.equal(await responsivePage.locator(toolbar + ' [data-cols="3"]').isVisible(), false, 'Out-of-range buttons must be hidden by actual CSS.');
		await responsivePage.locator(toolbar + ' [data-cols="2"]').click();
		await responsiveState(680, 2, [1, 2]);
		await responsiveState(681, 2, [1, 2, 3]);
		await responsiveState(1000, 3, [1, 2, 3]);
		await responsivePage.reload();
		await responsiveState(1000, 3, [1, 2, 3]);
		await responsiveState(1600, 6, [2, 3, 4, 5, 6]);
		await responsiveState(1800, 6, [4, 5, 6, 7, 8]);
		await responsivePage.locator(toolbar + ' [data-cols="8"]').click();
		await responsiveState(1800, 8, [4, 5, 6, 7, 8]);
		await responsiveState(1799, 6, [2, 3, 4, 5, 6]);
		await responsiveState(600, 2, [1, 2]);
		await responsivePage.reload();
		await responsiveState(600, 2, [1, 2]);
		// Tablet/mobile/widescreen choices never overwrite the existing desktop storage key.
		assert.equal(await responsivePage.evaluate(key => localStorage.getItem(key), key), '6');
		assert.equal(await responsivePage.evaluate(key => localStorage.getItem(key + ':tablet'), key), '3');
		assert.equal(await responsivePage.evaluate(key => localStorage.getItem(key + ':mobile'), key), '2');
		assert.equal(await responsivePage.evaluate(key => localStorage.getItem(key + ':widescreen'), key), '8');
		// An invalid old preference for a narrowed responsive range falls back to its configured default.
		await responsivePage.evaluate(key => localStorage.setItem(key + ':mobile', '12'), key);
		await responsivePage.reload();
		await responsiveState(600, 1, [1, 2]);
		// No persistence in the editor, while device previews still resolve their own range/default.
		await responsivePage.addInitScript(() => { window.elementorFrontend = { isEditMode: () => true }; });
		await responsivePage.reload();
		await responsiveState(600, 1, [1, 2]);
		await responsiveState(1600, 4, [2, 3, 4, 5, 6]);
		// Exact reported range: mobile has option 1, but desktop 2–4 must show only 2/3/4.
		await responsivePage.evaluate(toolbar => {
			document.querySelector('#second').remove();
			const root = document.querySelector(toolbar);
			const config = JSON.parse(root.dataset.marrisonListingColumns);
			config.profiles[0].maxColumns = 4;
			config.profiles[0].defaultColumns = 3;
			root.dataset.marrisonListingColumns = JSON.stringify(config);
		}, toolbar);
		await responsivePage.waitForFunction(toolbar => {
			const buttons = Array.from(document.querySelectorAll(toolbar + ' button')).filter(button => getComputedStyle(button).display !== 'none');
			return buttons.map(button => button.dataset.cols).join(',') === '2,3,4';
		}, toolbar);
		await responsivePage.setViewportSize({ width: 600, height: 1000 });
		await responsivePage.waitForFunction(toolbar => getComputedStyle(document.querySelector(toolbar + ' [data-cols="1"]')).display !== 'none', toolbar);
		await responsivePage.setViewportSize({ width: 1600, height: 1000 });
		await responsivePage.waitForFunction(toolbar => getComputedStyle(document.querySelector(toolbar + ' [data-cols="1"]')).display === 'none', toolbar);
		assert.deepEqual(errors, []);
		await responsive.close();
		// Storage denied by the browser: selection still works.
		const blocked = await browser.newContext({ viewport: { width: 1280, height: 1000 } });
		await blocked.addInitScript(() => Object.defineProperty(window, 'localStorage', { get() { throw new DOMException('Denied', 'SecurityError'); } }));
		await blocked.route('http://listing-columns.test/**', route => route.fulfill({ contentType: 'text/html', body: html }));
		const blockedPage = await blocked.newPage();
		blockedPage.on('pageerror', error => errors.push(error.message));
		await blockedPage.goto('http://listing-columns.test/catalogo');
		await blockedPage.locator(toolbar + ' [data-cols="4"]').click();
		await blockedPage.waitForFunction(grid => getComputedStyle(document.querySelector(grid)).gridTemplateColumns.split(' ').length === 4, grid);
		assert.deepEqual(errors, []);
		await blocked.close();
		console.log('Listing Columns browser: existing layout/storage/AJAX/editor checks plus responsive min/max/default, custom widths, laptop/mobile-extra/widescreen, exact boundaries, inheritance, hidden buttons and per-breakpoint preferences PASS.');
	} finally {
		await browser.close();
	}
})().catch(error => { console.error(error); process.exitCode = 1; });
