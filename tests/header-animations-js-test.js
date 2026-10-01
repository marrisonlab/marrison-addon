'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const path = require('path');
let source = fs.readFileSync(path.join(__dirname, '../marrison-addon/assets/js/marrison-header-animations.js'), 'utf8');
source = source.replace('    if (document.readyState', '    window.testSelect = selectResponsiveAnimation;\n    if (document.readyState');
function selected(width, settings, breakpoints, frontend) {
    const classes = new Set();
    const element = { getAttribute: () => JSON.stringify(settings), classList: { contains: c => classes.has(c), add: c => classes.add(c), remove: c => classes.delete(c), toggle(c, yes) { yes ? classes.add(c) : classes.delete(c); } } };
    const window = { innerWidth: width, addEventListener() {}, elementorFrontendConfig: { responsive: { breakpoints } } };
    if (frontend) { window.elementorFrontend = frontend; }
    vm.runInNewContext(source, { window, document: { readyState: 'loading', addEventListener() {} }, WeakMap, Array, Object, JSON });
    return window.testSelect(element);
}
const breakpoints = { mobile: {value: 500, direction: 'max', is_enabled: true}, tablet: {value: 800, direction: 'max', is_enabled: true}, laptop: {value: 1200, direction: 'max', is_enabled: true}, mobile_extra: {value: 700, direction: 'max', is_enabled: false}, widescreen: {value: 1800, direction: 'min', is_enabled: true} };
const settings = {_animation: '', _animation_tablet: 'marrisonDropSoft', _animation_mobile: 'marrisonLettersFocus', _animation_mobile_extra: 'marrisonLiftSoft'};
assert.equal(selected(900, settings, breakpoints), '', 'Tablet setting must not leak above the configured tablet breakpoint');
assert.equal(selected(700, settings, breakpoints), 'marrisonDropSoft');
assert.equal(selected(390, settings, breakpoints), 'marrisonLettersFocus');
assert.equal(selected(390, {...settings, _animation_mobile:'none'}, breakpoints), '', 'Explicit none must override tablet inheritance');
assert.equal(selected(390, {...settings, _animation_mobile:''}, breakpoints), 'marrisonDropSoft', 'Empty mobile must inherit tablet');
assert.equal(selected(1900, {...settings, _animation_widescreen:'marrisonFocusIn'}, breakpoints), 'marrisonFocusIn');
assert.equal(selected(1000, {...settings, _animation_laptop:'marrisonZoomSettle'}, breakpoints), 'marrisonZoomSettle');
assert.equal(selected(900, settings, breakpoints, {elements: {$deviceMode: [{}]}, getCurrentDeviceSetting() {throw new Error('Native helper is not initialized');}}), '', 'Do not call the native helper before breakpoints initialize');
console.log('PASS Header pre-init custom breakpoints, responsive inheritance, explicit none and widescreen');
