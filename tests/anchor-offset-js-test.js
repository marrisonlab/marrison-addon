'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(require('path').join(__dirname, '../marrison-addon/assets/js/marrison-anchor-offset.js'), 'utf8');
function click(options = {}) {
    const listeners = {};
    const calls = [];
    const target = { getBoundingClientRect: () => ({ top: 300 }) };
    const document = {
        readyState: 'complete',
        documentElement: { contains: () => true, style: { setProperty() {} } },
        querySelector: selector => selector === 'base[target]' && options.baseTarget ? { getAttribute: () => options.baseTarget } : null,
        getElementById: id => id === 'target' ? target : id === 'marrison-anchor-offset-style' ? {} : null,
        addEventListener: (event, handler) => { listeners[event] = handler; }
    };
    const window = {
        location: new URL('https://site.test/page'), pageYOffset: 0,
        matchMedia: () => ({ matches: false }), addEventListener() {}, setTimeout() {},
        scrollTo: value => calls.push(value), history: { pushState: () => calls.push('history') }
    };
    vm.runInNewContext(source, { document, window, URL });
    const link = {
        href: 'https://site.test/page#target',
        getAttribute: name => name === 'target' ? options.target || '' : '',
        hasAttribute: name => name === 'target' ? options.target !== undefined : name === 'download' && !!options.download
    };
    let prevented = false;
    listeners.click({ button: 0, ctrlKey: !!options.ctrlKey, target: { closest: () => link }, preventDefault() { prevented = true; } });
    return { prevented, calls };
}
assert.equal(click().prevented, true);
assert.equal(click({ target: '_self' }).calls.length, 2);
for (const options of [{ target: '_blank' }, { target: 'calendar' }, { target: '_top' }, { download: true }, { ctrlKey: true }, { baseTarget: '_blank' }]) {
    const result = click(options);
    assert.equal(result.prevented, false, JSON.stringify(options));
    assert.equal(result.calls.length, 0);
}
assert.equal(click({ target: '_self', baseTarget: '_blank' }).prevented, true);
assert.equal(click({ target: '', baseTarget: '_blank' }).prevented, true);
console.log('Anchor Offset: normal scroll, target, inherited target, download and modifiers PASS.');
