'use strict';
// Native Elementor PHP templates/CSS and Select2 library; editor models are fixtures.
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const elementorRoot = process.argv[2];
const wpRoot = process.argv[3];
const fixture = JSON.parse(execFileSync('php', ['tests/query-fallback-editor-fixture.php', elementorRoot], { encoding: 'utf8' }));
(async () => {
    const browser = await chromium.launch({ channel: 'chrome', headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 500, height: 1000 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent('<!doctype html><html><head></head><body><div id="elementor-panel" class="elementor-panel" style="width:280px;position:relative;height:auto"><div id="elementor-panel-content-wrapper"><div id="controls"></div></div></div></body></html>');
        await page.addScriptTag({ path: path.join(wpRoot, 'wp-includes/js/jquery/jquery.min.js') });
        await page.addScriptTag({ path: path.join(wpRoot, 'wp-includes/js/underscore.min.js') });
        await page.addStyleTag({ path: path.join(elementorRoot, 'assets/lib/e-select2/css/e-select2.css') });
        await page.addStyleTag({ path: path.join(elementorRoot, 'assets/css/common.css') });
        await page.addStyleTag({ path: path.join(elementorRoot, 'assets/css/theme-light.css') });
        await page.addStyleTag({ path: path.join(elementorRoot, 'assets/css/editor.css') });
        await page.addScriptTag({ path: path.join(elementorRoot, 'assets/lib/e-select2/js/e-select2.full.js') });
        await page.addStyleTag({ content: '.elementor-screen-only{clip:rect(1px,1px,1px,1px);height:1px;overflow:hidden;position:absolute!important;width:1px}.elementor-control-content{width:100%}' });
        await page.addStyleTag({ path: 'marrison-addon/assets/css/marrison-query-fallback-editor.css' });
        await page.evaluate(fixture => {
            const state = { custom_query_id: '1', marrison_query_fallback_enable: 'yes', marrison_query_fallback_id: '', marrison_query_fallback_mode: 'empty' };
            const models = {}, views = {};
            const callbacks = [];
            const settings = { get: key => state[key], set(key, value) { state[key] = value; callbacks.forEach(callback => callback()); } };
            const template = source => _.template(source, { evaluate: /<#([\s\S]+?)#>/g, interpolate: /\{\{\{([\s\S]+?)\}\}\}/g, escape: /\{\{([^}]+?)\}\}(?!\})/g });
            window.elementor = {
                compileTemplate: value => value,
                hooks: { addAction(name, callback) { window.openPanel = () => callback({ getCurrentPageView: () => panel }, { get: () => settings }); } }
            };
            window.marrisonQueryFallback = fixture.config;
            for (const [name, definition] of Object.entries(fixture.controls)) {
                if (definition.type === 'switcher') continue;
                const data = Object.assign({ description: '', label: '', multiple: false, content_classes: '', _cid: name }, definition, { name });
                const wrapper = document.createElement('div');
                wrapper.className = 'elementor-control elementor-control-' + name + ' elementor-control-type-' + data.type + (data.label_block ? ' elementor-label-block' : ' elementor-label-inline');
                document.getElementById('controls').appendChild(wrapper);
                models[name] = { set(key, value) { data[key] = value; } };
                views[name] = {
                    render() {
                        data.controlValue = state[name] || '';
                        const nativeTemplate = fixture.templates[data.type === 'raw_html' ? 'raw-html' : data.type];
                        wrapper.innerHTML = '<div class="elementor-control-content">' + template(nativeTemplate)({ data, elementor, view: this }) + '</div>';
                        if (data.type === 'select2') {
                            const select = jQuery(wrapper).find('select');
                            select.val(state[name]).select2({ allowClear: true, placeholder: data.options[''], width: '100%' });
                            this.select2Instance = { destroy() { select.select2('destroy'); } };
                            select.on('change', () => settings.set(name, select.val() || ''));
                        } else if (data.type === 'choose') {
                            wrapper.querySelector('input[value="' + state[name] + '"]').checked = true;
                            wrapper.addEventListener('change', event => settings.set(name, event.target.value));
                        }
                    }
                };
                views[name].render();
            }
            const panel = {
                getControlModel: name => models[name], getControlViewByName: name => views[name],
                listenTo(model, events, callback) { callbacks.push(callback); }
            };
            window.editorState = state;
            window.editorSettings = settings;
        }, fixture);
        await page.addScriptTag({ path: 'marrison-addon/assets/js/marrison-query-fallback-editor.js' });
        await page.evaluate(() => window.openPanel());
        const selector = '.elementor-control-marrison_query_fallback_id';
        await page.locator(selector + ' .select2-selection__placeholder').getByText('Seleziona una query', { exact: true }).waitFor();
        await page.locator(selector + ' .select2-selection').click();
        await page.locator('.select2-search__field').fill('Ultimi');
        await page.locator('.select2-results__option').filter({ hasText: 'Ultimi prodotti' }).click();
        assert.equal(await page.evaluate(() => editorState.marrison_query_fallback_id), '2');
        assert.equal(await page.locator(selector + ' .select2-container').count(), 1, 'Select2 duplicated after selection');
        const mode = '.elementor-control-marrison_query_fallback_mode';
        for (const width of [280, 240]) {
            await page.locator('#elementor-panel').evaluate((node, width) => { node.style.width = width + 'px'; }, width);
            const geometry = await page.locator(mode).evaluate(node => {
                const labels = [...node.querySelectorAll('.elementor-choices-label')].map(label => {
                    const rect = label.getBoundingClientRect();
                    const range = document.createRange(); range.selectNodeContents(label.querySelector('span'));
                    const text = range.getBoundingClientRect();
                    return { top: rect.top, bottom: rect.bottom, height: rect.height, textVisible: text.top >= rect.top && text.bottom <= rect.bottom };
                });
                const field = node.querySelector('.elementor-choices').getBoundingClientRect();
                const notice = document.querySelector('.elementor-control-marrison_query_fallback_notice').getBoundingClientRect();
                return { labels, bottom: field.bottom, noticeTop: notice.top, height: field.height };
            });
            assert(geometry.height >= 64, 'Native 27px radio height still clips stacked options');
            assert(geometry.labels.every(label => label.height >= 32 && label.textVisible), 'Radio text clipped');
            assert(geometry.labels[1].top >= geometry.labels[0].bottom, 'Radio options overlap');
            assert(geometry.noticeTop >= geometry.bottom, 'Notice overlaps radio options');
        }
        await page.locator(mode + ' label[data-tooltip="Fill missing items"]').click();
        assert.equal(await page.evaluate(() => editorState.marrison_query_fallback_mode), 'fill');
        await page.locator(mode + ' label[data-tooltip="Only when empty"]').click();
        assert.equal(await page.evaluate(() => editorState.marrison_query_fallback_mode), 'empty');
        await page.evaluate(() => editorSettings.set('use_load_more', 'yes'));
        await page.getByText('Fallback sospeso: Load More è attivo.', { exact: true }).waitFor();
        await page.evaluate(() => editorSettings.set('use_load_more', ''));
        assert.equal(await page.locator('.elementor-control-marrison_query_fallback_warning .elementor-control-raw-html').isVisible(), false);
        if (process.argv[4]) await page.locator('#elementor-panel').screenshot({ path: process.argv[4] });
        assert.deepEqual(errors, []);
        console.log('PASS native Elementor templates/CSS + Select2: 240/280px, visible radio labels, selection/search persistence, placeholder and accurate warning. Editor models are fixtures.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
