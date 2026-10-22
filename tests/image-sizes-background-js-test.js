/* Run: node tests/image-sizes-background-js-test.js; isolated frontend scheduling/cache contracts. */
'use strict';
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const source = fs.readFileSync(require('path').join(__dirname, '../marrison-addon/assets/js/marrison-bg-webp.js'), 'utf8');
const base = 'https://fixture.example/wp-content/uploads/hero';
function element(url, hero, size = 'cover') {
    return {
        url, hero, size, style: {},
        getBoundingClientRect: () => ({ width: 400, height: 200, top: 0, bottom: 200 }),
        closest: () => hero ? {} : null,
        setAttribute: () => {}
    };
}
function run(elements, version, storage = {}) {
    const requests = [];
    const timers = [];
    const context = {
        URL, console,
        FormData: class { constructor() { this.fields = {}; } append(k, v) { this.fields[k] = v; } },
        document: { readyState: 'complete', querySelectorAll: () => elements },
        fetch: (url, opts) => { requests.push(opts.body.fields); return Promise.resolve({ json: () => ({ success: false }) }); }
    };
    context.window = {
        marrisonBgWebp: { ajaxUrl: '/wp-admin/admin-ajax.php', uploadsBaseUrl: 'https://fixture.example/wp-content/uploads', cacheVersion: version, preserveDynamicBackgroundSize: true },
        location: { href: 'https://fixture.example/' },
        innerHeight: 800, devicePixelRatio: 1,
        sessionStorage: { getItem: key => Object.hasOwn(storage, key) ? storage[key] : null, setItem: (key, value) => { storage[key] = value; } },
        getComputedStyle: el => ({ backgroundImage: 'url("' + el.url + '")', backgroundSize: el.size }),
        setTimeout: fn => { timers.push(fn); return timers.length; }, clearTimeout: () => {}, addEventListener: () => {}
    };
    vm.runInNewContext(source, context);
    timers.forEach(fn => fn());
    return requests;
}
assert.strictEqual(run([element(base + '.webp', true)], '1|new').length, 0, 'A preloaded WebP hero must not cause another AJAX request.');
assert.strictEqual(run([element(base + '.avif?ver=1', true)], '1|new').length, 0, 'A preloaded AVIF hero must retain its URL.');
assert.strictEqual(run([element(base + '.png', true)], '1|new').length, 1, 'A PNG hero must keep the dynamic fallback when conversion becomes available.');
assert.strictEqual(run([element(base + '.webp', false)], '1|new').length, 1, 'Responsive resolution must remain active outside the hero.');
assert.strictEqual(run([element(base + '.png', false, 'auto')], '1|new').length, 0, 'Intrinsic background sizing must remain unchanged.');
const selectedDynamic = element(base + '.png', false);
selectedDynamic.closest = () => ({ hasAttribute: name => name === 'data-marrison-dynamic-bg-size' });
assert.strictEqual(run([selectedDynamic], '1|new').length, 0, 'An explicit dynamic resolution must never trigger automatic size selection.');
const cached = { ['marrison-bg-webp:1|old:' + base + '.png|400|200|cover|webp']: base + '-old.webp' };
assert.strictEqual(run([element(base + '.png', false)], '1|old', cached).length, 0, 'A current browser cache entry must be reused.');
assert.strictEqual(run([element(base + '.png', false)], '1|new', cached).length, 1, 'A new media revision must ignore stale browser resolution.');
const batches = run(Array.from({ length: 23 }, (_, index) => element(base + index + '.png', false)), '1|batch');
assert.strictEqual(batches.length, 3, 'Every visible background must be sent in bounded requests of ten items.');
assert.deepStrictEqual(batches.map(batch => Object.keys(batch).filter(key => /\[url\]$/.test(key)).length), [10, 10, 3], 'The final short batch must preserve all remaining backgrounds.');
console.log('Image Sizes background hero/session cache: PASS');
