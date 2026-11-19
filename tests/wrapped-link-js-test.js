'use strict';
const assert = require('assert/strict');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const source = fs.readFileSync(path.join(__dirname, '../marrison-addon/assets/js/marrison-addon.js'), 'utf8');

function click(settings, options = {}) {
    let listener;
    const opened = [];
    const window = { location: { href: 'https://example.test/' }, open: (...args) => opened.push(args) };
    const document = { addEventListener: (type, handler) => { if (type === 'click') listener = handler; } };
    vm.runInNewContext(source, { document, window });
    // The wrapper may have been present in cached HTML or inserted later by AJAX.
    const wrapper = { getAttribute: () => typeof settings === 'string' ? settings : JSON.stringify(settings) };
    const target = { closest: selector => selector === '[data-marrison-addon]' ? wrapper : options.interactive ? {} : null };
    listener({ target, defaultPrevented: !!options.prevented });
    return { href: window.location.href, opened };
}

for (const project of ['marrison-custom-updater', 'marrison-assistant', 'woo-legal', 'domino']) {
    const url = 'https://www.marrisonlab.com/projects/' + project + '/';
    // Match WordPress's JSON serialization, including escaped slashes.
    const settings = JSON.stringify({ url, is_external: '', nofollow: '', custom_attributes: '' }).replace(/\//g, '\\/');
    assert.equal(click(settings).href, url, 'Navigate when clicking headings or paragraph text inside the supplied cards.');
}
for (const is_external of ['on', '1', 1, true]) {
    const result = click({ url: 'https://example.test/external', is_external });
    assert.deepEqual(result.opened[0], ['https://example.test/external', '_blank', 'noopener']);
    assert.equal(result.href, 'https://example.test/');
}
for (const [settings, options] of [
    ['{invalid', {}], [{ url: '' }, {}], [{ url: '#' }, {}],
    [{ url: 'https://example.test/destination' }, { interactive: true }],
    [{ url: 'https://example.test/destination' }, { prevented: true }]
]) {
    const result = click(settings, options);
    assert.equal(result.href, 'https://example.test/');
    assert.equal(result.opened.length, 0);
}
console.log('Wrapped Link: four supplied URLs, external tabs, nested controls and invalid data PASS.');
