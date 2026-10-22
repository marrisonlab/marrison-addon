'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { fixture } = require('./cookie-runtime-js-test');
const source = fs.readFileSync(path.join(__dirname, '../marrison-addon/includes/modules/cookie-manager/assets/js/scan-agent.js'), 'utf8');

async function measure(phase, blocked = false, delayed = false) {
    const page = fixture(phase), reports = [], timers = [], intervals = [];
    let clock = 0, onLoad;
    page.cookies.clear();
    if (blocked) Object.defineProperty(page.Document.prototype, 'cookie', { configurable: true, get() { return ''; }, set() {} });
    page.window.credentialless = true;
    page.window.parent = { postMessage(report) { reports.push(report); } };
    page.window.marrisonScanFrame = { job: 'fixture', token: 'signed', phase, url: 'https://example.test/', origin: 'https://example.test' };
    page.window.cookieStore = { async getAll() { return []; } }; // Still fall back to readable document cookies.
    page.window.addEventListener = (name, callback) => { if (name === 'load') onLoad = callback; };
    page.window.scrollBy = () => {};
    page.document.readyState = 'loading';
    page.document.querySelectorAll = () => [{ tagName: 'IFRAME', getAttribute(name) { return name === 'src' ? 'https://www.google.com/maps/embed?PRIVATE_VALUE' : null; } }];
    page.document.documentElement = {scrollHeight: 800};
    Object.assign(page.context, { performance: { now: () => clock, getEntriesByType: () => [{name: 'https://www.google-analytics.com/g/collect?PRIVATE_VALUE', initiatorType: 'fetch'}] }, HTMLDocument: page.Document, innerHeight: 800, scrollY: 0,
        setTimeout(fn, delay) { timers.push({fn, delay}); return timers.length; }, clearTimeout() {}, setInterval(fn) { intervals.push(fn); return intervals.length; }, clearInterval() {} });
    vm.runInContext(source, page.context);
    if (!blocked) {
        page.document.cookie = 'PHPSESSID=PRIVATE_VALUE; Path=/';
        page.document.cookie = '_ga_fixture=PRIVATE_VALUE; Max-Age=120; Path=/';
        if (delayed) {
            onLoad(); intervals[0](); clock = 1500;
            timers.find(timer => timer.delay === 1000).fn();
            assert.equal(reports.length, 0, 'A quiet page finished before delayed services could run');
            clock = 3000; page.document.cookie = 'marrison_scan_test_delayed=PRIVATE_VALUE; Max-Age=120; Path=/';
            clock = 4000; timers.find(timer => timer.delay === 250).fn();
            await new Promise(resolve => setImmediate(resolve));
        } else { await timers.find(timer => timer.delay === 12000).fn(); }
    }
    assert.equal(reports.length, 1);
    assert(!page.cookies.has('marrison_cookie_scan_probe'), 'Probe was left in the cookie jar');
    assert(!JSON.stringify(reports).includes('PRIVATE_VALUE'), 'Cookie values or resource queries leaked');
    return reports[0];
}
(async () => {
    for (const phase of ['before', 'after']) {
        const report = await measure(phase);
        assert.equal(report.storage_access, 'available');
        assert.equal(report.error, '');
        const names = report.cookies.map(cookie => cookie.cookie_name);
        assert(names.includes('PHPSESSID'), 'An empty Cookie Store snapshot hid readable document cookies');
        assert.equal(names.includes('_ga_fixture'), phase === 'after', 'The agent bypassed consent enforcement');
        assert(!names.includes('marrison_cookie_scan_probe'), 'Synthetic probe appeared in inventory');
        assert.equal(report.resources[0].url, 'https://www.google.com/maps/embed');
        assert(report.resources.some(resource => resource.url === 'https://www.google-analytics.com/g/collect'), 'Fetch-only external service was lost');
        assert.equal(report.resources.find(resource => resource.url === 'https://www.google-analytics.com/g/collect').kind, 'request', 'Network requests were mislabelled as script elements');
    }
    const blocked = await measure('after', true);
    assert.equal(blocked.storage_access, 'blocked');
    assert(blocked.error.includes('blocca'), 'Blocked storage was reported as an empty successful scan');
    const delayed = await measure('after', false, true);
    assert(delayed.cookies.some(cookie => cookie.cookie_name === 'marrison_scan_test_delayed'), 'A cookie written after three seconds was missed');
    console.log('PASS scan agent: read/write probe, blocked access, empty Cookie Store fallback, consent before/after, probe cleanup and no values/query disclosure');
})().catch(error => { console.error(error); process.exitCode = 1; });
