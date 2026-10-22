'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(require('path').join(__dirname, '../marrison-addon/assets/js/marrison-preloader.js'), 'utf8');
function clickCase(href, options = {}) {
    const classes = new Set(['marrison-loaded']);
    const timers = [];
    const listeners = {};
    const windowListeners = {};
    const location = { href: 'https://site.test/page?x=1', origin: 'https://site.test', pathname: '/page', search: '?x=1' };
    const preloader = { hidden: true, offsetWidth: 100, querySelector: () => null, classList: { add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c) } };
    const window = { location, addEventListener(name, fn) { windowListeners[name] = fn; }, requestAnimationFrame: cb => cb(), setTimeout: (cb, ms) => { timers.push({ cb, ms }); return timers.length; }, clearTimeout() {}, clearInterval() {} };
    const document = { readyState: 'complete', getElementById: () => preloader, addEventListener: (type, cb) => { listeners[type] = cb; } };
    vm.runInNewContext(source, { window, document, URL, Math, parseInt });
    timers.length = 0;
    let prevented = false;
    const link = { href: new URL(href, location.href).href, target: options.target || '', dataset: {}, hasAttribute: name => name === 'download' && !!options.download, classList: { contains: () => false }, closest: () => null };
    const event = { defaultPrevented: !!options.defaultPrevented, ctrlKey: !!options.ctrlKey, button: 0, target: { closest: () => link }, preventDefault() { prevented = true; } };
    listeners.click(event);
    return { classes, timers, prevented, location, preloader, windowListeners };
}
for (const [href, options] of [['#', {}], ['/page?x=1#', {}], ['#target', {}], ['/next', { defaultPrevented: true }], ['/next', { ctrlKey: true }], ['/next', { target: '_blank' }], ['/next', { download: true }], ['https://other.test/', {}]]) {
    const result = clickCase(href, options);
    assert.equal(result.prevented, false, `Unexpected interception: ${href}`);
    assert.equal(result.timers.length, 0, `Unexpected navigation timer: ${href}`);
    assert(result.classes.has('marrison-loaded'), `Overlay shown for ${href}`);
}
const normal = clickCase('/next');
assert.equal(normal.prevented, true);
assert.equal(normal.timers.length, 1);
assert(!normal.classes.has('marrison-loaded'));
normal.timers[0].cb();
assert.equal(normal.location.href, 'https://site.test/next');
normal.windowListeners.beforeunload();
assert(!normal.classes.has('marrison-loaded'));
normal.windowListeners.pageshow({ persisted: true });
assert(normal.classes.has('marrison-loaded'), 'Returning from bfcache must immediately reveal content.');
assert(!normal.classes.has('marrison-enter'));
assert(normal.preloader.hidden, 'A restored page must not wait for the exit CSS transition.');
const phpSource = fs.readFileSync(require('path').join(__dirname, '../marrison-addon/includes/modules/class-marrison-addon-preloader.php'), 'utf8');
const cssSource = fs.readFileSync(require('path').join(__dirname, '../marrison-addon/assets/css/marrison-preloader.css'), 'utf8');
assert(/^[ \t]*<div id="marrison-preloader".* aria-hidden="true" hidden>/m.test(phpSource), 'Server markup must start hidden if JS never executes.');
assert(/#marrison-preloader\[hidden\]\s*\{\s*display:\s*none/.test(cssSource), 'Hidden markup must stay out of the layout without JS.');
console.log('PASS preloader links, progressive activation without JS and immediate bfcache recovery');
