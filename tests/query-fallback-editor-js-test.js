'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const values = { custom_query_id: '1', marrison_query_fallback_id: '2' };
const controls = {};
['marrison_query_fallback_id', 'marrison_query_fallback_warning'].forEach(key => {
    controls[key] = { set(name, value) { this[name] = value; } };
});
let update;
let selectRenders = 0;
let selectDestroys = 0;
const selectView = { render() { selectRenders++; }, select2Instance: { destroy() { selectDestroys++; } } };
const page = {
    getControlModel: key => controls[key],
    getControlViewByName: key => key === 'marrison_query_fallback_id' ? selectView : { render() {} },
    listenTo(settings, names, callback) { update = callback; }
};
const config = { queries: { '': 'Select...', 1: 'Main', 2: 'Backup', 3: 'Other' }, compatible: true, apiMissing: 'API_MISSING', invalid: 'INVALID', unsupported: 'UNSUPPORTED' };
const elementor = { hooks: { addAction(name, callback) {
    assert.strictEqual(name, 'panel/open_editor/widget/jet-listing-grid');
    callback({ getCurrentPageView: () => page }, { get: () => ({ get: key => values[key] }) });
} } };
const context = { window: { elementor, marrisonQueryFallback: config }, elementor };
vm.runInNewContext(fs.readFileSync('marrison-addon/assets/js/marrison-query-fallback-editor.js', 'utf8'), context);
assert(!controls.marrison_query_fallback_id.options[1]);
assert(controls.marrison_query_fallback_id.options[2]);
assert.strictEqual(controls.marrison_query_fallback_warning.raw, '');
assert.strictEqual(selectRenders, 1);
values.custom_query_id = '2'; update();
assert(!controls.marrison_query_fallback_id.options[2]);
assert(controls.marrison_query_fallback_id.options[1]);
assert.strictEqual(controls.marrison_query_fallback_warning.raw, 'INVALID');
values.custom_query_id = '999'; update();
assert.strictEqual(controls.marrison_query_fallback_warning.raw, 'INVALID');
values.custom_query_id = '1'; values.marrison_query_fallback_id = '999'; update();
assert.strictEqual(controls.marrison_query_fallback_warning.raw, 'INVALID');
values.marrison_query_fallback_id = '2'; values.use_load_more = 'yes'; update();
assert.strictEqual(controls.marrison_query_fallback_warning.raw, 'UNSUPPORTED');
const rendersBeforeSelection = selectRenders;
values.marrison_query_fallback_id = '3'; update();
assert.strictEqual(selectRenders, rendersBeforeSelection, 'Selecting a fallback must not rebuild its Select2');
assert.strictEqual(selectDestroys, selectRenders, 'Select2 must be disposed before rebuilding');
config.compatible = false; update();
assert.strictEqual(controls.marrison_query_fallback_warning.raw, 'API_MISSING');
config.compatible = true;
values.use_load_more = ''; config.filtersActive = true; update();
assert.strictEqual(controls.marrison_query_fallback_warning.raw, '', 'An installed filtering plugin must not suspend an unfiltered grid');
values.custom_query_id = ''; update();
assert(controls.marrison_query_fallback_id.options[''], 'Empty primary must preserve the fallback placeholder');
console.log('PASS editor options: primary exclusion/change, placeholder, Select2 lifecycle, invalid queries and precise safety warnings (model fixture).');
