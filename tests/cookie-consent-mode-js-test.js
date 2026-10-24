'use strict';
const assert = require('node:assert/strict');
const { fixture } = require('./cookie-runtime-js-test');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const rules = JSON.parse(execFileSync('php', [path.join(__dirname, 'cookie-service-catalog-php-test.php'), '--runtime-json'], {encoding: 'utf8'}));
(async () => {
    const page = fixture(undefined, rules);
    const initial = page.window.dataLayer[0];
    assert.equal(initial[0], 'consent'); assert.equal(initial[1], 'default');
    for (const key of ['ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization']) { assert.equal(initial[2][key], 'denied'); }
    assert.equal(initial[2].wait_for_update, 500); assert.equal(page.requests.length, 0);
    for (const url of ['https://www.googletagmanager.com/gtag/js?id=G-123', 'https://www.googletagmanager.com/gtm.js?id=GTM-123', 'https://connect.facebook.net/en_US/fbevents.js']) {
        await assert.rejects(page.window.fetch(url), {name: 'NotAllowedError'});
    }
    const pixelCalls = []; page.window.fbq = (...args) => pixelCalls.push(args);
    page.window.MarrisonConsentRuntime.setState({valid: true, categories: ['necessary', 'analytics'], expires: Date.now() / 1000 + 3600});
    assert.equal(page.window.dataLayer.at(-1)[2].analytics_storage, 'granted');
    assert.equal(page.window.dataLayer.at(-1)[2].ad_storage, 'denied');
    await page.window.fetch('https://www.googletagmanager.com/gtag/js?id=G-123');
    page.window.MarrisonConsentRuntime.setState({valid: true, categories: ['necessary', 'marketing'], expires: Date.now() / 1000 + 3600});
    assert.equal(page.window.dataLayer.at(-1)[2].ad_user_data, 'granted');
    assert.deepEqual(pixelCalls.at(-1), ['consent', 'grant']);
    page.window.MarrisonConsentRuntime.setState({valid: true, categories: ['necessary'], expires: Date.now() / 1000 + 3600});
    assert.deepEqual(pixelCalls.at(-1), ['consent', 'revoke']);
    const video = new page.Element('IFRAME');
    video.setAttribute('data-marrison-blocked-src', 'https://www.youtube.com/embed/test');
    assert(page.window.MarrisonConsentRuntime.allowContent(video));
    assert.match(video.getAttribute('data-marrison-blocked-src'), /^https:\/\/www.youtube-nocookie.com\/embed\//);
    video.setAttribute('src', video.getAttribute('data-marrison-blocked-src'));
    assert(video.getAttribute('src'), 'Single content consent not honored');
    assert.deepEqual(Array.from(page.window.MarrisonConsentRuntime.allowed()), ['necessary']);
    page.window.MarrisonConsentRuntime.setState({valid: true, categories: ['necessary'], expires: Date.now() / 1000 + 3600});
    assert.equal(video.getAttribute('src'), null, 'Single-content permission survived revocation');
    const bad = new page.Element('IFRAME'); bad.setAttribute('data-marrison-blocked-src', 'https://evil.test/embed/test');
    assert.equal(page.window.MarrisonConsentRuntime.allowContent(bad), false);
    console.log('PASS Consent Mode v2: denied defaults, no preconsent Google/Meta requests, category updates, Meta revocation, single YouTube nocookie consent and revocation');
})().catch(error => {console.error(error); process.exitCode = 1;});
