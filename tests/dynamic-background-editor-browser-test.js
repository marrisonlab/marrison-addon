/* Real installed Elementor Media view + Backbone/Marionette in a browser fixture.
 * Run: NODE_PATH=<bundled packages> node tests/dynamic-background-editor-browser-test.js <wordpress-root>
 * Native document commands are recorded by a fixture adapter; no live editor/DB is used.
 */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const wpRoot = process.argv[2];
assert(wpRoot, 'Pass the installed WordPress root containing Elementor.');
const elementorRoot = path.join(wpRoot, 'wp-content/plugins/elementor');
const editor = fs.readFileSync(path.join(elementorRoot, 'assets/js/editor.js'), 'utf8');
const pluginRoot = path.join(__dirname, '../marrison-addon');
function region(name) {
    const start = editor.indexOf('//#region assets/dev/js/editor/controls/' + name + '.js');
    assert(start >= 0, 'Missing native control region: ' + name);
    return editor.slice(start, editor.indexOf('//#endregion', start));
}
function method(source, name) {
    const start = source.indexOf(name + ': function ');
    assert(start >= 0, 'Missing native method: ' + name);
    const body = source.indexOf('function ', start);
    const end = source.indexOf('\n\t\t\t},', body);
    assert(end > body, 'Missing native method end: ' + name);
    return source.slice(body, end) + '\n}';
}
const data = region('base-data');
const multiple = region('base-multiple');
const media = region('media');
const methods = Object.fromEntries(['getInputValue', 'onBaseInputChange', 'setSettingsModel', 'getControlPlaceholder'].map(name => [name, method(data, name)]));
const multipleBody = multiple.slice(multiple.indexOf('var ControlBaseMultipleItemView ='), multiple.indexOf('module.exports = ControlBaseMultipleItemView;'));
const mediaBody = media.slice(media.indexOf('var ControlMediaItemView ='), media.indexOf('module.exports = ControlMediaItemView;'));

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_BROWSER_PATH || undefined });
    try {
        const page = await browser.newPage({ viewport: { width: 800, height: 900 } });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setContent('<!doctype html><html><head></head><body><main id="controls"></main></body></html>');
        for (const file of ['wp-includes/js/jquery/jquery.min.js', 'wp-includes/js/underscore.min.js', 'wp-includes/js/backbone.min.js', 'wp-content/plugins/elementor/assets/lib/backbone/backbone.marionette.js']) {
            await page.addScriptTag({ path: path.join(wpRoot, file) });
        }
        await page.addStyleTag({ path: path.join(elementorRoot, 'assets/css/editor.css') });
        await page.addStyleTag({ path: path.join(pluginRoot, 'assets/css/marrison-dynamic-background-size-editor.css') });
        await page.evaluate(({ methods, multipleBody, mediaBody }) => {
            window._typeof = value => typeof value;
            window._objectSpread = (...args) => Object.assign({}, ...args);
            window._defineProperty = (object, key, value) => { object[key] = value; return object; };
            window.elementorCommon = { helpers: { cloneObject: value => JSON.parse(JSON.stringify(value)) } };
            const filters = [];
            window.elementor = {
                hooks: { addFilter: (name, callback) => { if (name === 'controls/base/behaviors') filters.push(callback); } },
                channels: { editor: Object.assign({}, Backbone.Events) },
                imagesManager: { getImageUrl: value => value.url }
            };
            window.marrisonDynamicBackgroundSizeEditor = { notice: 'Marrison Addon applies the selected resolution when a WordPress attachment is available.' };
            window.commands = [];
            window.$e = { run(command, args) {
                window.commands.push({ command, container: args.container, before: JSON.parse(JSON.stringify(args.container.settings.attributes)), settings: JSON.parse(JSON.stringify(args.settings)) });
                args.container.settings.set(args.settings);
            } };
            window.wp = { media: { attachment: () => ({ fetch: () => Promise.resolve({}) }) } };
            const base = {
                className: 'elementor-control elementor-control-type-media',
                ui: () => ({ select: 'select[data-setting]' }),
                behaviors() { return filters.reduce((result, callback) => callback(result, this), {}); },
                events() { return { 'change @ui.select': 'onBaseInputChange' }; },
                initialize(options) { this.container = options.container; this.validators = []; },
                onRender() { this.applySavedValue(); this.triggerMethod('ready'); },
                renderWithChildren() { this.render(); },
                template: () => '<div class="elementor-control-media"><div class="elementor-control-media__preview"></div><div class="e-control-image-size"><div class="elementor-control-field"><label>Image Resolution</label><div class="elementor-control-input-wrapper"><select class="e-image-size-select" data-setting="size"><option value="">Full</option><option value="medium">Medium</option><option value="large">Large</option></select></div></div><div class="elementor-control-field-description">Image size settings don’t apply to Dynamic Images.</div></div></div>'
            };
            for (const [name, source] of Object.entries(methods)) base[name] = (0, eval)('(' + source + ')');
            window.NativeBase = Marionette.ItemView.extend(base);
            window.require_base_data = () => window.NativeBase;
            (0, eval)(multipleBody + '; window.NativeMultiple = ControlBaseMultipleItemView;');
            window.ControlMultipleBaseItemView = window.NativeMultiple;
            (0, eval)(mediaBody + '; window.NativeMedia = ControlMediaItemView;');
            window.createControl = function(options = {}) {
                const name = options.name || 'background_image';
                const value = Object.assign({ id: '', url: '', size: '' }, options.value);
                const settings = new Backbone.Model({ [name]: value, __dynamic__: options.dynamic === false ? {} : { [name]: '[elementor-tag name="fixture"]' } });
                settings.validators = {};
                const container = { model: new Backbone.Model({ elType: options.elType || 'container' }), settings, placeholders: { [name]: options.placeholder } };
                const model = new Backbone.Model({ name, type: options.type || 'media', has_sizes: options.hasSizes !== false, media_types: ['image'], responsive: options.responsive || false, inherit_placeholders: true });
                const view = new window.NativeMedia({ model, container });
                document.getElementById('controls').appendChild(view.el);
                view.render();
                return view;
            };
        }, { methods, multipleBody, mediaBody });
        await page.addScriptTag({ path: path.join(pluginRoot, 'assets/js/marrison-dynamic-background-size-editor.js') });
        const result = await page.evaluate(async () => {
            let count = 0;
            function check(condition, message) { count++; if (!condition) throw new Error(message); }
            const tick = () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            const visible = view => getComputedStyle(view.$('.e-control-image-size')[0]).display !== 'none';
            const notice = view => view.$('.e-control-image-size .elementor-control-field-description').text();
            const original = 'Image size settings don’t apply to Dynamic Images.';
            const empty = window.createControl();
            check(visible(empty), 'A dynamic background with no static image must expose the native resolution field.');
            check(empty.$('select').length === 1, 'The original select must be the only select.');
            check(notice(empty).startsWith('Marrison Addon'), 'Only the supported control must show the corrected notice.');
            check(empty.$('select').val() === '', 'No saved choice must display native Full.');
            const tag = JSON.stringify(empty.container.settings.get('__dynamic__'));
            empty.$('select').val('medium').trigger('change');
            await tick();
            check(empty.container.settings.get('background_image').size === 'medium', 'Native input handling must save Medium without an ID.');
            check(commands.some(call => call.command === 'document/elements/settings' && call.container === empty.container && call.settings.background_image.size === 'medium'), 'Selection must use the real native document settings command.');
            check(JSON.stringify(empty.container.settings.get('__dynamic__')) === tag, 'The dynamic source must remain unchanged.');
            check(empty.container.settings.get('background_image').id === '', 'Selecting a size must not require a static attachment.');
            empty.render();
            check(empty.$('select').val() === 'medium', 'Reopening the control must display its saved choice.');
            const step = commands.find(call => call.container === empty.container);
            empty.container.settings.set(step.before);
            empty.applySavedValue();
            await tick();
            check(empty.$('select').val() === '', 'Restoring prior settings must display Full.');
            empty.container.settings.set(step.settings);
            empty.applySavedValue();
            await tick();
            check(empty.$('select').val() === 'medium', 'Restoring the saved settings must display Medium.');
            for (const name of ['background_image_mobile', 'background_hover_image', 'background_overlay_image', 'background_overlay_hover_image_tablet']) {
                const view = window.createControl({ name, responsive: { max: 'mobile' }, value: { size: 'medium' }, placeholder: { id: 42, url: '/inherited.jpg', size: 'large' } });
                check(visible(view), name + ' must show the existing field.');
                check(view.$('select').val() === 'medium', name + ' must show the saved dynamic size instead of the static placeholder size.');
                const beforeTag = JSON.stringify(view.container.settings.get('__dynamic__'));
                view.$('select').val('large').trigger('change');
                await tick();
                check(view.container.settings.get(name).size === 'large', name + ' must persist the selection through the native handler.');
                check(JSON.stringify(view.container.settings.get('__dynamic__')) === beforeTag, name + ' must preserve the dynamic source when the native placeholder is copied.');
            }
            for (const options of [{ dynamic: false }, { elType: 'widget' }, { name: 'image' }, { hasSizes: false }]) {
                const view = window.createControl(options);
                check(!view.$el.hasClass('marrison-dynamic-background-size-control'), 'Unrelated controls must retain native behavior.');
                check(!visible(view), 'An unrelated empty native control must remain hidden.');
                check(notice(view) === original, 'An unrelated control must retain its original notice.');
            }
            empty.container.settings.set('__dynamic__', {});
            empty.applySavedValue();
            await tick();
            check(!visible(empty), 'Disabling the dynamic tag must restore native visibility.');
            check(notice(empty) === original, 'Disabling the dynamic tag must restore its native notice.');
            empty.container.settings.set('__dynamic__', { background_image: '[elementor-tag name="fixture"]' });
            await tick();
            check(visible(empty), 'Enabling a dynamic tag again must reveal the native field.');
            check(notice(empty).startsWith('Marrison Addon'), 'Enabling the tag must restore the module notice.');
            empty.container.settings.set('background_image', { id: '', url: '', size: 'large' });
            empty.destroy();
            await tick();
            check(empty.isDestroyed, 'Destroying a control with a pending update must be safe.');
            return { assertions: count, commands: commands.length };
        });
        assert.deepEqual(errors, []);
        if (process.env.DYNAMIC_BACKGROUND_SCREENSHOT) {
            await page.evaluate(() => {
                const demo = window.createControl({ value: { size: 'medium' } });
                document.getElementById('controls').replaceChildren(demo.el);
            });
            await page.addStyleTag({ content: 'body{font:13px Arial;margin:0;padding:24px;background:#fff}main{width:340px} .elementor-control-field-description{margin-top:12px;line-height:1.5}' });
            await page.screenshot({ path: process.env.DYNAMIC_BACKGROUND_SCREENSHOT, fullPage: true });
        }
        console.log('Dynamic background editor / native Elementor Media view: ' + result.assertions + ' assertions PASS (' + result.commands + ' native settings commands).');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
