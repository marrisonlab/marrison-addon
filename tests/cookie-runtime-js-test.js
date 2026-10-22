/* Focused early-runtime regression: pending decisions, rejection and scanner phases. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../marrison-addon/includes/modules/cookie-manager/assets/js/consent-runtime.js'), 'utf8');

function fixture(phase) {
    const writes = [], requests = [];
    const cookies = new Map([['_ga', 'existing-id'], ['functional_pref', 'saved'], ['woocommerce_cart_hash', 'cart']]);
    class Node {
        appendChild(node) { return node; }
        insertBefore(node) { return node; }
        replaceChild(node) { return node; }
    }
    class Element extends Node {
        constructor(tag = 'SCRIPT') { super(); this.nodeType = 1; this.tagName = tag; this.attributesMap = {}; }
        setAttribute(name, value) { this.attributesMap[name] = String(value); }
        getAttribute(name) { return this.attributesMap[name] || null; }
        hasAttribute(name) { return Object.hasOwn(this.attributesMap, name); }
        removeAttribute(name) { delete this.attributesMap[name]; }
        querySelectorAll() { return []; }
        insertAdjacentElement(position, node) { return node; }
    }
    class Document extends Node {}
    Object.defineProperty(Document.prototype, 'cookie', { configurable: true,
        get() { return [...cookies].map(([name, value]) => name + '=' + value).join('; '); },
        set(text) {
            writes.push(text);
            const [name, value] = text.split(';')[0].split('=');
            if (/max-age=0/i.test(text)) cookies.delete(name); else cookies.set(name, value);
        }
    });
    class DocumentFragment extends Node {}
    class HTMLScriptElement extends Element {}
    class HTMLIFrameElement extends Element {}
    class HTMLImageElement extends Element {}
    class XMLHttpRequest { open() {} send() {} abort() {} }
    const document = new Document();
    const window = { marrisonConsentRules: { phase, cookies: [{ name: '_ga*', category: 'analytics' }, { name: 'functional_pref', category: 'functional' }], sources: { analytics: ['/tracking/'] } }, fetch(url) { requests.push(url); return Promise.resolve('loaded'); } };
    const context = vm.createContext({ window, document, Node, Element, Document, DocumentFragment, HTMLScriptElement, HTMLIFrameElement, HTMLImageElement, XMLHttpRequest, navigator: {}, location: { pathname: '/shop/item', hostname: 'example.test', protocol: 'https:' }, URL, DOMException });
    vm.runInContext(source, context);
    return { window, document, writes, requests, cookies, Element, context, Document };
}

module.exports = { fixture };
if (require.main === module) {
(async () => {
    const page = fixture();
    assert.equal(page.writes.length, 0, 'Pending verification deleted existing cookies');
    page.window.MarrisonConsentRuntime.enforce(); // A failed verification leaves the runtime pending.
    assert.equal(page.cookies.get('_ga'), 'existing-id', 'A verification failure destroyed a previous identifier');
    page.document.cookie = '_ga_new=blocked; Path=/';
    assert(!page.cookies.has('_ga_new'), 'Pending verification permitted a new optional cookie');
    const pendingScript = new page.Element();
    pendingScript.setAttribute('src', '/tracking/pending.js');
    assert.equal(pendingScript.getAttribute('src'), null, 'Pending verification allowed a tracker');
    await assert.rejects(page.window.fetch('/tracking/pending'), { name: 'NotAllowedError' });
    assert.equal(page.requests.length, 0);

    page.window.MarrisonConsentRuntime.setState({ valid: true, categories: ['necessary', 'analytics', 'functional'], expires: Date.now() / 1000 + 3600 });
    assert.equal(page.cookies.get('_ga'), 'existing-id', 'Valid consent lost its preexisting cookie');
    assert.equal(page.cookies.get('functional_pref'), 'saved');
    page.document.cookie = '_ga_new=allowed; Path=/';
    assert.equal(page.cookies.get('_ga_new'), 'allowed');
    await page.window.fetch('/tracking/allowed');
    assert.equal(page.requests.length, 1);

    page.window.MarrisonConsentRuntime.setState({ valid: true, categories: ['necessary'], expires: Date.now() / 1000 + 3600 });
    assert(!page.cookies.has('_ga') && !page.cookies.has('functional_pref'), 'Verified refusal/revocation retained optional cookies');
    assert.equal(page.cookies.get('woocommerce_cart_hash'), 'cart');
    for (const state of [null, { valid: false }, { valid: true, categories: ['analytics'], expires: Date.now() / 1000 - 60 }]) {
        const invalid = fixture();
        invalid.window.MarrisonConsentRuntime.setState(state);
        assert(!invalid.cookies.has('_ga'), 'Absent, invalid or expired consent failed to remove optional cookies');
    }
    const before = fixture('before');
    assert(!before.cookies.has('_ga') && before.cookies.has('woocommerce_cart_hash'), 'Before-consent scan lost its clean baseline');
    const after = fixture('after');
    assert.equal(after.cookies.get('_ga'), 'existing-id');
    after.document.cookie = '_ga_after=observed; Path=/';
    await after.window.fetch('/tracking/after');
    assert.equal(after.cookies.get('_ga_after'), 'observed');
    console.log('PASS cookie runtime: pending preserves existing values while blocking writes/resources, valid consent, refusal/revocation/expiry, necessary cookies and before/after scan phases');
})().catch(error => { console.error(error); process.exitCode = 1; });
}
