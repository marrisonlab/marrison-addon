/* Run with NODE_PATH pointing to Playwright/pngjs and an Elementor frontend.css path argument. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const { PNG } = require('pngjs');

const elementorCss = process.argv[2];
assert(elementorCss && fs.existsSync(elementorCss), 'Pass the installed Elementor frontend.css path.');
const root = path.join(__dirname, '..', 'marrison-addon');
const liquidCss = fs.readFileSync(process.env.LIQUID_CSS || path.join(root, 'assets/css/marrison-liquid-background.css'), 'utf8');
const liquidJs = fs.readFileSync(path.join(root, 'assets/js/marrison-liquid-background.js'), 'utf8');

async function pixel(page, bottom = false) {
	const box = await page.locator('#host').boundingBox();
	const shot = await page.screenshot({ clip: {
		x: Math.round(box.x + 12), y: Math.round(box.y + (bottom ? box.height - 12 : 12)), width: 1, height: 1
	} });
	return Array.from(PNG.sync.read(shot).data).slice(0, 3);
}

async function setBackground(page, color) {
	await page.evaluate((value) => {
		const target = document.querySelector('#native') || document.querySelector('#host');
		if (target.tagName === 'VIDEO') {
			const ctx = window.videoCanvas.getContext('2d');
			ctx.fillStyle = value;
			ctx.fillRect(0, 0, 64, 64);
			window.videoStream.getVideoTracks()[0].requestFrame();
		} else {
			target.style.backgroundImage = `linear-gradient(${value}, ${value})`;
		}
	}, color);
	// Let the browser composite the new video frame as well as the CSS image.
	await page.waitForTimeout(120);
}

async function run(page, mode, boxed, fallback) {
	const native = mode === 'image' ? '' : mode === 'video'
		? '<div class="elementor-background-video-container"><video id="native" class="elementor-background-video-hosted" muted playsinline></video></div>'
		: mode === 'slideshow'
			? '<div class="elementor-background-slideshow"><div id="native" class="elementor-background-slideshow__slide__image"></div></div>'
			: '<div class="elementor-motion-effects-container"><div id="native" class="elementor-motion-effects-layer"></div></div>';
	const content = '<button class="elementor-element elementor-widget" id="content">Clickable content</button><div class="elementor-shape elementor-shape-bottom" id="divider"></div>';
	const config = { preset: 'deep-purple', opacity: 0.25, reducedMotion: 'static', blendGradient: { enabled: true, color: '#000000', height: 100 } };
	await page.setContent(`<html><head></head><body class="elementor"><div id="host" class="elementor-element e-con e-flex ${boxed ? 'e-con-boxed' : 'e-con-full'}" data-marrison-liquid-background='${JSON.stringify(config)}'>${boxed ? `<div class="e-con-inner">${native}${content}</div>` : native + content}</div></body></html>`);
	await page.addStyleTag({ path: elementorCss });
	await page.addStyleTag({ content: `
		body { margin: 0; }
		#host { --display: flex; width: 320px; height: 240px; padding: 0; --overlay-transition: 0s; }
		#host > .e-con-inner { padding: 0; width: 100%; }
		#host #content { position: absolute; top: 80px; left: 70px; width: 180px; height: 40px; margin: 0; background: white; color: black; }
		#divider { position: absolute; bottom: 0; left: 0; width: 100%; height: 4px; background: blue; }
		.elementor-background-video-hosted { width: 100%; height: 100%; }
		.elementor-motion-effects-container, .elementor-motion-effects-layer { position: absolute; inset: 0; }
		.elementor-motion-effects-container { z-index: 0; }
		.elementor-motion-effects-layer { transform: translateY(0); }
		.overlay::before { --background-overlay: ''; background-image: linear-gradient(rgb(0, 255, 0), rgb(0, 255, 0)); }
	` });
	await page.addStyleTag({ content: liquidCss });
	if (fallback) {
		await page.evaluate(() => {
			const original = HTMLCanvasElement.prototype.getContext;
			HTMLCanvasElement.prototype.getContext = function (type, ...args) {
				return type === 'webgl' ? null : original.call(this, type, ...args);
			};
		});
	}
	if (mode === 'video') {
		await page.evaluate(async () => {
			window.videoCanvas = document.createElement('canvas');
			window.videoCanvas.width = window.videoCanvas.height = 64;
			window.videoStream = window.videoCanvas.captureStream(0);
			document.querySelector('#native').srcObject = window.videoStream;
			const ctx = window.videoCanvas.getContext('2d');
			ctx.fillStyle = 'red'; ctx.fillRect(0, 0, 64, 64);
			window.videoStream.getVideoTracks()[0].requestFrame();
			await document.querySelector('#native').play();
		});
	}
	await setBackground(page, 'rgb(255, 0, 0)');
	await page.addScriptTag({ content: liquidJs });
	await page.waitForSelector('.marrison-liquid-background-host');
	const layer = fallback ? '.marrison-liquid-background-fallback' : '.marrison-liquid-background-canvas';
	assert.equal(await page.locator(layer).count(), 1, `${mode}: expected ${layer}`);
	const red = await pixel(page);
	await setBackground(page, 'rgb(0, 255, 0)');
	const green = await pixel(page);
	assert(green[1] - red[1] > 100 && red[0] - green[0] > 100, `${mode}/${boxed}/${fallback}: native background hidden: ${red} -> ${green}`);
	await page.evaluate(() => {
		const host = document.querySelector('#host');
		const config = JSON.parse(host.getAttribute('data-marrison-liquid-background'));
		config.opacity = 1;
		host.setAttribute('data-marrison-liquid-background', JSON.stringify(config));
	});
	await page.waitForFunction(() => document.querySelector('#host').style.getPropertyValue('--marrison-liquid-opacity') === '1');
	await page.evaluate(() => document.querySelector('#host').classList.add('overlay'));
	const overlay = await pixel(page, true);
	assert(overlay[1] > 245 && overlay[0] < 10 && overlay[2] < 10, `Overlay must cover Liquid and its bottom blend: ${overlay}`);
	await page.evaluate(() => document.querySelector('#host').style.setProperty('--overlay-opacity', '0.5'));
	const translucentOverlay = await pixel(page, true);
	assert(translucentOverlay[1] > 110 && translucentOverlay[1] < 150, `Elementor overlay opacity ignored: ${translucentOverlay}`);
	await page.evaluate(() => document.querySelector('#host').style.setProperty('--overlay-opacity', '1'));
	await page.evaluate(() => { document.querySelector('#content').onclick = () => { window.clicked = true; }; });
	await page.locator('#content').click();
	assert(await page.evaluate(() => window.clicked), 'Overlay intercepted the content click.');
	const divider = await page.locator('#divider').evaluate((el) => ({ position: getComputedStyle(el).position, width: el.getBoundingClientRect().width }));
	assert.equal(divider.position, 'absolute');
	assert.equal(divider.width, 320);
	const dividerPixel = PNG.sync.read(await page.locator('#divider').screenshot()).data;
	assert(dividerPixel[2] > 245 && dividerPixel[0] < 10, 'Shape divider was covered.');
	await page.evaluate(() => {
		const host = document.querySelector('#host');
		host.classList.remove('overlay');
		const overlay = document.createElement('div');
		overlay.className = 'elementor-background-overlay';
		overlay.style.backgroundColor = 'rgb(0, 255, 0)';
		host.appendChild(overlay);
	});
	const legacyOverlay = await pixel(page, true);
	assert(legacyOverlay[1] > 245 && legacyOverlay[0] < 10, `Element overlay must also cover Liquid: ${legacyOverlay}`);
	await page.evaluate(() => document.querySelector('#host').removeAttribute('data-marrison-liquid-background'));
	await page.waitForFunction(() => !document.querySelector('.marrison-liquid-background-host'));
	assert.equal(await page.locator('.marrison-liquid-background-canvas,.marrison-liquid-background-fallback,.marrison-liquid-background-blend').count(), 0);
	console.log(`PASS: ${mode}, ${boxed ? 'boxed' : 'full'}, ${fallback ? 'fallback' : 'WebGL'} (${page.viewportSize().width}px)`);
}

(async () => {
	const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
	let checks = 0;
	try {
		for (const width of [1365, 390]) {
			for (const fallback of [false, true]) {
				for (const boxed of [false, true]) {
					for (const mode of ['image', 'video', 'slideshow', 'motion']) {
						// Fresh page prevents the forced fallback stub from leaking into other cases.
						const page = await browser.newPage({ viewport: { width, height: 700 }, reducedMotion: 'reduce' });
						try { await run(page, mode, boxed, fallback); checks++; }
						finally { await page.close(); }
					}
				}
			}
		}
		console.log(`Liquid/Elementor background stacking: ${checks} browser scenarios PASS`);
	} finally { await browser.close(); }
})().catch((error) => { console.error(error); process.exitCode = 1; });
