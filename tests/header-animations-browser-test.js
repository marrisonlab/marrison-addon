'use strict';
const { chromium } = require('playwright');
const assert = require('assert/strict');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 1000, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent(`<!doctype html><html><body>
            <style>
                .widget { width: 640px; }
                .elementor-heading-title { margin:0; font: 40px/1.2 Arial; }
                .elementor-invisible { visibility:hidden; }
            </style>
            <div id="lines" class="widget elementor-invisible" data-marrison-heading-animations='{"_animation":"marrisonLinesRise","_animation_mobile":"none"}'>
                <h2 class="elementor-heading-title"><a href="#destination">Una prima riga<br>La <em>seconda</em> riga sale dal basso</a></h2>
            </div>
            <div id="reference" class="widget"><h2 class="elementor-heading-title"><a href="#destination">Una prima riga<br>La <em>seconda</em> riga sale dal basso</a></h2></div>
            <script>window.elementorFrontend = { isEditMode: () => true };</script>
        </body></html>`);
        await page.addStyleTag({ path: path.join(__dirname, '../marrison-addon/assets/css/marrison-header-animations.css') });
        const original = await page.locator('#lines h2').innerHTML();
        const originalText = await page.locator('#lines h2').textContent();
        await page.locator('#lines a').evaluate(link => {
            window.savedLink = link;
            window.linkClicks = 0;
            link.addEventListener('click', event => { event.preventDefault(); window.linkClicks++; });
        });
        await page.addScriptTag({ path: path.join(__dirname, '../marrison-addon/assets/js/marrison-header-animations.js') });
        const masks = '#lines .marrison-heading-line-mask';
        const text = '#lines .marrison-heading-line-text';
        await page.waitForFunction(() => document.querySelectorAll('#lines .marrison-heading-line-mask').length === 9);
        assert.equal(await page.locator('#lines').evaluate(node => node.classList.contains('marrisonLinesRise')), false, 'Wait for Elementor entrance trigger.');
        assert.equal(await page.locator(text).first().evaluate(node => getComputedStyle(node).animationName), 'none');
        assert.equal(await page.locator('#lines h2').textContent(), originalText);
        assert.equal(await page.locator('#lines br').count(), 1);
        assert.equal(await page.locator('#lines em').textContent(), 'seconda');
        assert.equal(await page.locator('#lines a').evaluate(link => link === window.savedLink), true);
        assert.equal(await page.locator('#lines h2').evaluate(node => node.offsetHeight), await page.locator('#reference h2').evaluate(node => node.offsetHeight), 'Splitting must retain heading height.');

        const indexes = () => page.locator(masks).evaluateAll(nodes => nodes.map(node => Number(node.style.getPropertyValue('--marrison-line-index'))));
        assert.deepEqual(await indexes(), [0,0,0,1,1,1,1,1,1]);
        await page.locator('#lines').evaluate(node => { node.classList.remove('elementor-invisible'); node.classList.add('animated', 'marrisonLinesRise'); });
        await page.waitForFunction(() => getComputedStyle(document.querySelector('#lines .marrison-heading-line-text')).animationName === 'marrisonLinesRise');
        assert.equal(await page.locator(masks).first().evaluate(node => getComputedStyle(node).clipPath.startsWith('inset(')), true, 'Each line segment must mask vertical overflow.');
        const animations = await page.locator(text).evaluateAll(nodes => nodes.map(node => {
            const animation = node.getAnimations()[0];
            animation.pause();
            animation.currentTime = 0;
            return { delay: animation.effect.getTiming().delay, transform: getComputedStyle(node).transform, height: node.offsetHeight };
        }));
        assert.deepEqual(animations.map(item => item.delay), [0,0,0,120,120,120,120,120,120]);
        assert.ok(animations.every(item => Number(item.transform.split(',')[5].replace(')', '')) >= item.height), 'Text must start below the mask.');
        const screenshot = process.argv.indexOf('--screenshot');
        if (screenshot !== -1 && process.argv[screenshot + 1]) {
            await page.locator(text).evaluateAll(nodes => nodes.forEach(node => { node.getAnimations()[0].currentTime = 100; }));
            await page.screenshot({ path: process.argv[screenshot + 1] });
        }
        await page.locator(text).evaluateAll(nodes => nodes.forEach(node => { node.getAnimations()[0].currentTime = 5000; }));
        assert.ok(await page.locator(text).evaluateAll(nodes => nodes.every(node => getComputedStyle(node).transform === 'matrix(1, 0, 0, 1, 0, 0)')));
        await page.locator('#lines a').click();
        assert.equal(await page.evaluate(() => window.linkClicks), 1, 'Original link listeners must survive.');

        // Container-only resizing must regroup lines without new nodes or a window resize.
        await page.locator('#lines').evaluate(node => { window.firstMask = node.querySelector('.marrison-heading-line-mask'); node.style.width = '285px'; });
        await page.waitForFunction(() => new Set(Array.from(document.querySelectorAll('#lines .marrison-heading-line-mask'), node => node.style.getPropertyValue('--marrison-line-index'))).size > 2);
        assert.equal(await page.locator(masks).first().evaluate(node => node === window.firstMask), true);

        // Reduced motion shows the entire title immediately.
        await page.emulateMedia({ reducedMotion: 'reduce' });
        assert.equal(await page.locator(text).first().evaluate(node => getComputedStyle(node).transform), 'none');
        assert.equal(await page.locator(text).first().evaluate(node => getComputedStyle(node).animationName), 'none');

        // Responsive none removes all wrappers and restores exact original markup.
        await page.setViewportSize({ width: 600, height: 900 });
        await page.waitForFunction(() => !document.querySelector('#lines .marrison-heading-line-mask'));
        assert.equal(await page.locator('#lines h2').innerHTML(), original);
        await page.setViewportSize({ width: 1000, height: 900 });
        await page.waitForFunction(() => document.querySelector('#lines .marrison-heading-line-mask'));

        // Editor switches between line, letter and whole-heading effects must clean up.
        async function select(animation) {
            await page.locator('#lines').evaluate((node, animation) => node.dataset.marrisonHeadingAnimations = JSON.stringify({ _animation: animation }), animation);
        }
        await select('marrisonLettersRise');
        await page.waitForFunction(() => document.querySelector('#lines .marrison-heading-letter'));
        assert.equal(await page.locator(masks).count(), 0);
        await select('marrisonLinesRise');
        await page.waitForFunction(() => document.querySelector('#lines .marrison-heading-line-mask'));
        assert.equal(await page.locator('#lines .marrison-heading-letter').count(), 0);
        await page.locator('#lines h2 a').evaluate(node => { node.innerHTML = 'Titolo nuovo<br>Altra riga'; });
        await page.waitForFunction(() => document.querySelectorAll('#lines .marrison-heading-line-mask').length === 4);
        assert.equal(await page.locator('#lines h2').textContent(), 'Titolo nuovoAltra riga');
        await select('marrisonLiftSoft');
        await page.waitForFunction(() => !document.querySelector('#lines .marrison-heading-line-mask'));
        assert.equal(await page.locator('#lines h2 a').innerHTML(), 'Titolo nuovo<br>Altra riga');
        assert.deepEqual(errors, []);
        console.log('PASS Header line masking, line stagger, entrance trigger, responsive reflow/none, markup/link preservation, reduced motion and editor changes');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
