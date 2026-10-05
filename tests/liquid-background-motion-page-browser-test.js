/* Run with NODE_PATH pointing to Playwright/pngjs and the exported Servizi CSS manifest path. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');
const manifest = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const plugin = path.join(__dirname, '..', 'marrison-addon');
const css = fs.readFileSync(process.env.LIQUID_CSS || path.join(plugin, 'assets/css/marrison-liquid-background.css'), 'utf8');
const js = fs.readFileSync(process.env.LIQUID_JS || path.join(plugin, 'assets/js/marrison-liquid-background.js'), 'utf8');

async function sample(page) {
	const box = await page.locator('#host').boundingBox();
	return Array.from(PNG.sync.read(await page.screenshot({ clip: { x: box.x + 12, y: box.y + 12, width: 1, height: 1 } })).data).slice(0, 3);
}

async function setOpacity(page, value) {
	await page.evaluate((value) => {
		const host = document.querySelector('#host');
		const config = JSON.parse(host.getAttribute('data-marrison-liquid-background'));
		config.opacity = value;
		host.setAttribute('data-marrison-liquid-background', JSON.stringify(config));
	}, value);
	await page.waitForFunction((value) => document.querySelector('#host').style.getPropertyValue('--marrison-liquid-opacity') === String(value), value);
}

async function run(page, fallback) {
	// Use the styles exported from the reported page, without contacting remote media or services.
	await page.route('**/*', (route) => route.abort());
	await page.setContent(`<body class="elementor-default elementor-kit-5"><main class="elementor elementor-1479"><div id="host" class="elementor-element elementor-element-79017aa e-flex e-con-boxed e-con e-parent elementor-motion-effects-element elementor-motion-effects-element-type-background" data-marrison-liquid-background='{"preset":"deep-purple","opacity":1,"reducedMotion":"static"}'><div class="e-con-inner"><button class="elementor-element elementor-widget" id="content">Servizi — contenuti cliccabili</button></div></div></main></body>`);
	for (const name of ['frontend.min.css', 'motion-fx.min.css', 'post-5.css', 'post-1479.css']) {
		const asset = manifest.assets.find((entry) => entry.name === name);
		assert(asset && fs.existsSync(asset.path), `Missing exported page stylesheet: ${name}`);
		await page.addStyleTag({ path: asset.path });
	}
	await page.addStyleTag({ content: css });
	if (fallback) {
		await page.evaluate(() => { HTMLCanvasElement.prototype.getContext = () => null; });
	}
	await page.addScriptTag({ content: js });
	await page.waitForSelector('.marrison-liquid-background-host');
	// Motion Effects initializes after Liquid on the reported page and inserts a transformed layer.
	await page.evaluate(() => document.querySelector('#host').insertAdjacentHTML('afterbegin', '<div class="elementor-motion-effects-container"><div id="motion" class="elementor-motion-effects-layer" style="width:140%;height:100%;transform:translateX(-50px)"></div></div>'));
	assert((await page.locator('#motion').evaluate((el) => getComputedStyle(el).backgroundImage)).includes('/woo.svg'), 'Exported page CSS did not apply its actual background image.');
	// Replace the image with solid test markers to measure which layer is actually visible.
	await page.addStyleTag({ content: '#host::before, #host > .elementor-motion-effects-container > .elementor-motion-effects-layer::before { background: transparent; transition: none; }' });
	await page.locator('#motion').evaluate((el) => { el.style.backgroundColor = 'transparent'; el.style.backgroundImage = 'linear-gradient(red,red)'; });
	const red = await sample(page);
	await page.locator('#motion').evaluate((el) => { el.style.backgroundImage = 'linear-gradient(lime,lime)'; });
	const green = await sample(page);
	assert.deepEqual(red, green, 'Opaque Liquid must cover the Servizi motion background.');
	await setOpacity(page, 0);
	const nativeGreen = await sample(page);
	await page.locator('#motion').evaluate((el) => { el.style.backgroundImage = 'linear-gradient(red,red)'; });
	const nativeRed = await sample(page);
	assert(nativeRed[0] > 245 && nativeRed[1] < 10 && nativeGreen[1] > 245 && nativeGreen[0] < 10, `Servizi motion background hidden by transparent Liquid: ${nativeRed} -> ${nativeGreen}`);
	await setOpacity(page, 0.5);
	const redMix = await sample(page);
	await page.locator('#motion').evaluate((el) => { el.style.backgroundImage = 'linear-gradient(lime,lime)'; });
	const greenMix = await sample(page);
	assert(redMix[0] - greenMix[0] > 100 && greenMix[1] - redMix[1] > 100, `Servizi Liquid opacity did not reveal the background: ${redMix} -> ${greenMix}`);
	await setOpacity(page, 1);
	// Elementor writes the same overlay settings to the host and the moving media layer.
	await page.addStyleTag({ content: '#host::before, #host > .elementor-motion-effects-container > .elementor-motion-effects-layer::before { background: linear-gradient(blue,blue); opacity: 1; }' });
	const overlay = await sample(page);
	assert(overlay[2] > 245 && overlay[0] < 10 && overlay[1] < 10, `Motion layer overlay hidden by Liquid: ${overlay}`);
	await setOpacity(page, 0);
	await page.addStyleTag({ content: '#host::before, #host > .elementor-motion-effects-container > .elementor-motion-effects-layer::before { opacity: 0.5; }' });
	const alpha = await sample(page);
	assert(alpha[1] > 110 && alpha[1] < 145 && alpha[2] > 110 && alpha[2] < 145, `Motion overlay opacity ignored: ${alpha}`);
	assert.equal(await page.locator('#motion').evaluate((el) => getComputedStyle(el, '::before').content), 'none', 'The moving media must not duplicate the overlay below Liquid.');
	await page.locator('#motion').evaluate((el) => { el.style.transform = 'translateX(-80px)'; });
	assert((await page.locator('#motion').evaluate((el) => getComputedStyle(el).transform)).includes('-80'), 'Background motion transform was overwritten.');
	await page.locator('#content').evaluate((el) => { el.onclick = () => { window.testClick = true; }; });
	await page.locator('#content').click();
	assert(await page.evaluate(() => window.testClick), 'Background layers intercepted the content click.');
	console.log(`PASS: exported Servizi CSS, late motion layer, ${fallback ? 'fallback' : 'WebGL'}, ${page.viewportSize().width}px`);
}

(async () => {
	const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
	try {
		for (const width of [1365, 390]) {
			for (const fallback of [false, true]) {
				const page = await browser.newPage({ viewport: { width, height: 700 }, reducedMotion: 'reduce' });
				try { await run(page, fallback); } finally { await page.close(); }
			}
		}
	} finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; });
