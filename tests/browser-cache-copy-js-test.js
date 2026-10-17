'use strict';
const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(require('path').join(__dirname, '../marrison-addon/assets/js/admin-browser-cache.js'), 'utf8');
async function copy({ clipboard, fallback, throws = false }) {
    let click;
    const status = { textContent: '' };
    const target = { value: 'config', focus() {}, select() {} };
    const button = { getAttribute: () => 'target', addEventListener: (type, fn) => { click = fn; } };
    const document = {
        addEventListener: (type, fn) => fn(),
        getElementById: id => id === 'marrison-browser-cache-copy-nginx' ? button : id === 'target' ? target : status,
        execCommand: () => { if (throws) throw Error('clipboard unavailable'); return fallback; }
    };
    vm.runInNewContext(source, { document, navigator: clipboard ? { clipboard: { writeText: clipboard } } : {} });
    click();
    await new Promise(resolve => setImmediate(resolve));
    return status.textContent;
}
(async () => {
    assert.strictEqual(await copy({ fallback: true }), 'Configurazione copiata.');
    assert.match(await copy({ fallback: false }), /non riuscita/);
    assert.match(await copy({ fallback: false, throws: true }), /non riuscita/);
    assert.strictEqual(await copy({ clipboard: () => Promise.resolve() }), 'Configurazione copiata.');
    assert.match(await copy({ clipboard: () => Promise.reject(Error('denied')), fallback: false }), /non riuscita/);
    console.log('Browser Cache clipboard success/failure: PASS');
})().catch(error => { console.error(error); process.exitCode = 1; });
