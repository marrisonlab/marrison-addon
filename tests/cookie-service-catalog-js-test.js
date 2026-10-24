'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { fixture } = require('./cookie-runtime-js-test');
const config = JSON.parse(execFileSync('php', [path.join(__dirname, 'cookie-service-catalog-php-test.php'), '--runtime-json'], { encoding: 'utf8' }));
(async () => {
    const page = fixture(undefined, config);
    for (const url of ['https://unseen.example/a.js', 'https://unknown.example/a.js', 'https://js.stripe.com/v3/', 'https://www.youtube-nocookie.com/embed/a', 'https://www.paypal.com/sdk/js?client-id=1', 'https://www.googletagmanager.com/gtag/js?id=G-1']) {
        await assert.rejects(page.window.fetch(url), { name: 'NotAllowedError' });
    }
    page.window.MarrisonConsentRuntime.setState({ valid: true, categories: ['necessary', 'functional', 'analytics', 'marketing'], expires: Date.now() / 1000 + 3600 });
    await assert.rejects(page.window.fetch('https://unknown.example/a.js'), { name: 'NotAllowedError' });
    await page.window.fetch('https://js.stripe.com/v3/');
    const after = fixture('after', config);
    await assert.rejects(after.window.fetch('https://unknown.example/a.js'), { name: 'NotAllowedError' });
    const necessaryConfig = JSON.parse(JSON.stringify(config));
    necessaryConfig.catalog.find(service => service.patterns.some(pattern => pattern.host === 'stripe.com')).category = 'necessary';
    necessaryConfig.hosts['cdn.example'] = 'necessary';
    const payment = fixture(undefined, necessaryConfig);
    await payment.window.fetch('https://js.stripe.com/v3/');
    await payment.window.fetch('https://cdn.example/a.js');
    await payment.window.fetch('/wp-content/plugins/woocommerce/a.js');
    const jsonLd = new payment.Element();
    jsonLd.textContent = '{"@context":"https://schema.org","url":"https://stripe.com"}';
    jsonLd.setAttribute('type', 'application/ld+json');
    new payment.Element().appendChild(jsonLd);
    assert.equal(jsonLd.getAttribute('type'), 'application/ld+json', 'Inline JSON-LD was blocked as a request');
    const mixed = new payment.Element('IMG');
    mixed.setAttribute('srcset', '/wp-content/plugins/woocommerce/a.png 1x, https://unknown.example/a.png 2x');
    assert.equal(mixed.getAttribute('srcset'), null, 'Mixed srcset bypassed unknown-host blocking');
    // Host spoofing must not inherit the necessary category of the real payment provider.
    const spoofConfig = JSON.parse(JSON.stringify(necessaryConfig));
    spoofConfig.hosts['js.stripe.com.evil.test'] = 'unclassified';
    const spoof = fixture(undefined, spoofConfig);
    await assert.rejects(spoof.window.fetch('https://js.stripe.com.evil.test/a.js'), { name: 'NotAllowedError' });
    necessaryConfig.custom_sources.analytics = ['js.stripe.com'];
    const overridden = fixture(undefined, necessaryConfig);
    await assert.rejects(overridden.window.fetch('https://js.stripe.com/v3/'), { name: 'NotAllowedError' });
    const element = new overridden.Element();
    element.setAttribute('src', 'https://js.stripe.com/v3/');
    assert.equal(element.getAttribute('src'), null);
    assert.equal(element.getAttribute('data-marrison-cookie-category'), 'analytics');
    console.log('PASS catalog runtime: PHP-generated rules, denied/granted/unknown hosts, necessary payments, relative WooCommerce, custom priority and host boundaries');
})().catch(error => { console.error(error); process.exitCode = 1; });
