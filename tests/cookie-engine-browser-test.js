/* Real Chrome isolation, scan coordinator/agent, and early consent enforcement. */
const assert = require('node:assert/strict');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const serverOnly = process.env.COOKIE_ENGINE_SERVER_ONLY === '1';
const { chromium } = serverOnly ? {} : require('playwright');
const root = path.join(__dirname, '../marrison-addon/includes/modules/cookie-manager');
const wordpress = process.argv[2];
assert(wordpress, 'Pass an installed WordPress root');
const scripts = {
    '/jquery.js': fs.readFileSync(path.join(wordpress, 'wp-includes/js/jquery/jquery.min.js')),
    '/scanner.js': fs.readFileSync(path.join(root, 'assets/js/scanner.js')),
    '/runtime.js': fs.readFileSync(path.join(root, 'assets/js/consent-runtime.js')),
    '/agent.js': fs.readFileSync(path.join(root, 'assets/js/scan-agent.js'))
};
const revision = fs.readFileSync(path.join(root, 'includes/class-scan-engine.php'), 'utf8').match(/const REVISION = '([^']+)'/)[1];
const runtime = { protocol: 2, revision, compatible: true, plugin_version: 'fixture', scanner_hash: '1234567890123456' };
const rules = phase => ({ phase, cookies: [{ name: 'essential', category: 'necessary' }, { name: '_ga*', category: 'analytics' }], sources: { analytics: ['/tracking/'], marketing: ['/ads/'], functional: [] } });
let origin = 'https://cookie-engine.local';
const network = serverOnly || process.env.COOKIE_ENGINE_NETWORK === '1';
let server;
let job = null, run = 0, fixtureMode = 'cookies';
const reports = [], frameRequests = [], trackingRequests = [];
const next = () => job && job.status === 'browser' ? { url: origin + '/site/' + (job.browser_done % 2 ? 'inside/' : ''), phase: job.browser_done < 2 ? 'before' : 'after', token: 'signed-' + job.browser_done, frame_url: origin + '/site/' + (job.browser_done % 2 ? 'inside/' : '') + '?phase=' + (job.browser_done < 2 ? 'before' : 'after') + '&token=signed-' + job.browser_done } : null;
function summary() { return job && { ...job, next: next() }; }
function html(response, body) { response.setHeader('Content-Type', 'text/html; charset=utf-8'); response.end('<!doctype html><html><head><meta charset="utf-8"></head>' + body + '</html>'); }
async function serve(request, response) {
    const url = new URL(request.url, origin);
    if (url.pathname === '/results') {
        html(response, '<body><pre id="results">' + JSON.stringify({ job, reports, frameRequests: frameRequests.map(item => ({run:item.run,phase:item.phase,adminCookieSent:item.cookie.includes('ADMIN_SECRET')})) }).replace(/</g, '&lt;') + '</pre></body>'); return;
    }
    if (scripts[url.pathname]) { response.setHeader('Content-Type', 'text/javascript'); response.end(scripts[url.pathname]); return; }
    if (url.pathname === '/ajax') {
        let body = ''; for await (const chunk of request) body += chunk;
        const data = new URLSearchParams(body);
        const action = data.get('action').replace('marrison_addon_cookie_engine_', '');
        let output = {};
        if (action === 'start') {
            job = { id: 'job-' + ++run, status: 'http', http_done: 0, http_failed: 0, browser_done: 0, browser_total: 4, browser_failed: 0, cookie_count: 0, queued: 2, limit: 50, coverage: 'Fixture coverage', issues: [], sampling: { strategy: 'templates', candidates: 200, equivalents: 198, selected: 2, unselected: 0, groups: [{label:'Articoli',template:'single.php',url:origin+'/site/one/',represented_urls:100},{label:'Annunci',template:'single-annunci.php',url:origin+'/site/two/',represented_urls:100}] } };
        } else if (action === 'step') { job.status = 'browser'; job.http_done = 2; job.queued = 0; }
        else if (action === 'browser') {
            const result = JSON.parse(data.get('result'));
            assert.equal(result.url, next().url); assert.equal(result.phase, next().phase); assert.equal(result.token, next().token);
            reports.push({ run, ...result });
            if (result.error) { job.browser_failed++; job.issues.push(result.error); }
            job.browser_done++;
            job.cookie_count = new Set(reports.filter(report => report.run === run).flatMap(report => report.cookies || []).map(cookie => cookie.cookie_name)).size;
            if (job.browser_done === 4) job.status = job.browser_failed ? 'partial' : 'completed';
        } else if (action === 'cancel') { job.status = 'cancelled'; }
        else if (action === 'inventory') { output = { cookies: [] }; }
        else assert.equal(action, 'state');
        response.setHeader('Content-Type', 'application/json');
        response.end(JSON.stringify({ success: true, data: { runtime, ...output, job: summary() } })); return;
    }
    if (url.pathname === '/admin') {
        fixtureMode = url.searchParams.get('mode') || 'cookies';
        response.setHeader('Set-Cookie', ['wordpress_logged_in_fixture=ADMIN_SECRET; Path=/', 'marrison_cookie_consent=accept_all; Path=/']);
        html(response, `<body><button id="start" onclick="MarrisonCookieScanner.start()">Start</button><div id="marrison-scan-engine"><div id="marrison-engine-runtime"></div><div id="marrison-engine-summary"></div><div id="marrison-engine-current"></div><div id="marrison-engine-coverage"></div><p id="marrison-engine-sampling"></p><p id="marrison-engine-services"></p><ul id="marrison-engine-samples"></ul><pre id="marrison-engine-details"></pre><progress id="marrison-engine-progress"></progress></div><span id="marrison-scan-status"></span><button id="marrison-scan-cancel" hidden>Stop</button><button id="marrison-scan-resume" hidden>Resume</button><script>window.marrisonCookieScanner=${JSON.stringify({ ajaxUrl: origin + '/ajax', nonce: 'fixture', protocol: '2', revision, origin })};</script><script src="/jquery.js"></script><script src="/scanner.js"></script></body>`); return;
    }
    if (url.pathname.startsWith('/site/')) {
        frameRequests.push({ cookie: request.headers.cookie || '', phase: url.searchParams.get('phase'), run });
        const phase = url.searchParams.get('phase');
        const block = fixtureMode === 'blocked' ? '<script>Object.defineProperty(Document.prototype,"cookie",{configurable:true,get(){return ""},set(value){}});</script>' : '';
        const writes = fixtureMode === 'empty' ? '' : `<script>document.cookie='essential=PRIVATE_VALUE; Path=/; SameSite=Lax';document.cookie='_ga_fixture=PRIVATE_VALUE; Max-Age=3600; Path=/site/; SameSite=Lax';document.cookie='unrecognized=PRIVATE_VALUE; Path=/';var s=document.createElement('script');s.src='/tracking/dynamic.js';document.head.appendChild(s);</script>`;
        const lazy = fixtureMode === 'empty' ? '' : `<div id="lazy" style="margin-top:1200px;height:20px">Lazy embed</div><script>new IntersectionObserver(function(items,observer){if(items.some(item=>item.isIntersecting)){observer.disconnect();var s=document.createElement('script');s.src='/tracking/lazy.js';document.head.appendChild(s);}}).observe(document.getElementById('lazy'));</script>`;
        html(response, `${block}<script>window.marrisonConsentRules=${JSON.stringify(rules(phase))};window.marrisonScanFrame=${JSON.stringify({ job: job.id, token: url.searchParams.get('token'), phase, url: origin + url.pathname, origin })};</script><script src="/runtime.js"></script><script src="/agent.js"></script><body style="min-height:1200px">${writes}Cookie fixture${lazy}</body>`); return;
    }
    if (url.pathname === '/consent') {
        html(response, `<script>window.marrisonConsentRules=${JSON.stringify(rules(null))};</script><script src="/runtime.js"></script><body><div id="target"></div></body>`); return;
    }
    if (url.pathname.startsWith('/tracking/')) {
        trackingRequests.push(url.pathname);
        response.setHeader('Content-Type', 'text/javascript');
        response.end("window.trackerRan=true; document.cookie='" + (url.pathname.endsWith('lazy.js') ? '_ga_lazy' : '_ga_dynamic') + "=PRIVATE_VALUE; Path=/; Max-Age=120';"); return;
    }
    response.writeHead(404); response.end();
}
async function intercept(context) {
    await context.route(origin + '/**', async route => {
        const source = route.request();
        const request = { url: source.url(), headers: await source.allHeaders(), async *[Symbol.asyncIterator]() { yield source.postData() || ''; } };
        const response = { headers: {}, status: 200, body: '', setHeader(name, value) { this.headers[name] = Array.isArray(value) ? value.join('\n') : value; }, writeHead(status) { this.status = status; }, end(body = '') { this.body = body; } };
        await serve(request, response);
        await route.fulfill({ status: response.status, headers: response.headers, body: response.body });
    });
}
(async () => {
    if (network) {
        server = http.createServer(serve);
        await new Promise(resolve => server.listen(serverOnly ? 8874 : 0, '127.0.0.1', resolve));
        origin = 'http://127.0.0.1:' + server.address().port;
    }
    if (serverOnly) { console.log('Fixture ready: ' + origin + '/admin'); return; }
    const browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_BROWSER_PATH || undefined });
    try {
        const context = await browser.newContext();
        if (!network) await intercept(context);
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.goto(origin + '/admin');
        await page.waitForFunction(() => !!window.MarrisonCookieScanner);
        for (let scan = 1; scan <= 2; scan++) {
            await page.locator('#start').click();
            await page.waitForFunction(() => document.getElementById('marrison-scan-status').textContent.startsWith('Scansione completata:'), { timeout: 30000 });
            assert.equal(job.status, 'completed');
            const current = reports.filter(report => report.run === scan);
            assert.equal(current.length, 4);
            for (const report of current) {
                assert.equal(report.error, '');
                assert.equal(report.storage_access, 'available');
                const names = report.cookies.map(cookie => cookie.cookie_name);
                assert(names.includes('essential'));
                assert(!names.includes('wordpress_logged_in_fixture'), 'Administrator cookie leaked into isolated scan');
                assert.equal(names.includes('_ga_fixture'), report.phase === 'after');
                assert.equal(names.includes('_ga_dynamic'), report.phase === 'after');
                assert.equal(names.includes('_ga_lazy'), report.phase === 'after', 'Hidden scan failed to activate an intersecting lazy resource');
                if (report.phase === 'after') {
                    const analytics = report.cookies.find(cookie => cookie.cookie_name === '_ga_fixture');
                    assert.equal(analytics.cookie_path, '/site/'); assert(analytics.expires > Date.now());
                }
            }
        }
        // Playwright's mocked transport derives cookie headers outside the frame partition.
        if (network) assert(frameRequests.every(request => !request.cookie.includes('ADMIN_SECRET')), 'Frame requests forwarded the admin login');
        assert(!JSON.stringify(reports).includes('PRIVATE_VALUE'), 'Agent transmitted cookie values');
        assert.deepEqual(errors, []);
        assert((await context.cookies()).some(cookie => cookie.name === 'wordpress_logged_in_fixture'), 'Scan changed parent administrator cookies');
        assert((await page.locator('#marrison-engine-sampling').innerText()).includes('198 URL equivalenti'));
        assert.equal(await page.locator('#marrison-engine-samples li').count(), 2);
        assert((await page.locator('#marrison-engine-summary').innerText()).includes('pagine campione'));
        await context.close();

        const isolation = await browser.newContext();
        if (!network) await intercept(isolation);
        const consent = await isolation.newPage();
        await consent.goto(origin + '/consent');
        const beforeRequests = trackingRequests.length;
        const before = await consent.evaluate(async () => {
            document.cookie='wordpress_logged_in_fixture=ADMIN_SECRET; Path=/';
            document.cookie='essential=PRIVATE_VALUE; Path=/';
            document.cookie='_ga_blocked=PRIVATE_VALUE; Path=/';
            document.cookie='unknown_preference=PRIVATE_VALUE; Path=/';
            ['appendChild', 'append', 'prepend'].forEach((method, index) => { const script=document.createElement('script'); script.src='/tracking/' + index + '.js'; document.head[method](script); });
            document.getElementById('target').innerHTML='<img src="/tracking/pixel.gif" srcset="/tracking/pixel2.gif 2x"><iframe src="/tracking/frame"></iframe>';
            document.body.insertAdjacentHTML('beforeend','<img src="/tracking/adjacent.gif">');
            const blockedFetch = await fetch('/tracking/fetch').then(() => false, () => true);
            const blockedBeacon = navigator.sendBeacon('/tracking/beacon', 'fixture') === false;
            return { cookies: document.cookie, blockedFetch, blockedBeacon, blockedElements: document.querySelectorAll('[data-marrison-cookie-blocked]').length };
        });
        assert(before.cookies.includes('essential=')); assert(before.cookies.includes('wordpress_logged_in_fixture='));
        assert(!before.cookies.includes('_ga_blocked=')); assert(!before.cookies.includes('unknown_preference='));
        assert(before.blockedFetch && before.blockedBeacon && before.blockedElements === 6);
        assert.equal(trackingRequests.length, beforeRequests, 'A tracker request escaped before consent');
        assert.equal(await consent.locator('#target img').getAttribute('srcset'), null);
        await consent.evaluate(() => { window.MarrisonConsentRuntime.setState({valid:true,categories:['necessary','analytics'],expires:Date.now()/1000+3600}); document.cookie='marrison_cookie_consent=custom; Path=/'; document.cookie='marrison_cookie_categories=necessary|analytics; Path=/'; document.cookie='_ga_allowed=PRIVATE_VALUE; Path=/'; document.cookie='unknown_preference=PRIVATE_VALUE; Path=/'; const script=document.createElement('script'); script.src='/tracking/allowed.js'; document.head.append(script); });
        await consent.waitForFunction(() => window.trackerRan === true);
        const custom = await consent.evaluate(() => document.cookie);
        assert(custom.includes('_ga_allowed=')); assert(!custom.includes('unknown_preference='));
        const revoked = await consent.evaluate(() => { document.cookie='marrison_cookie_consent=reject_all; Path=/'; window.MarrisonConsentRuntime.setState(null); window.MarrisonConsentRuntime.enforce(); return document.cookie; });
        assert(!revoked.includes('_ga_')); assert(revoked.includes('essential=') && revoked.includes('wordpress_logged_in_fixture='));
        await isolation.close();
        console.log('PASS browser engine (' + (network ? 'real loopback HTTP' : 'mocked HTTP transport') + '): two fresh credentialless runs, real before/after JavaScript cookies, domain/path/expiry, no administrator cookies or values; dynamic scripts/pixels/iframes/fetch/beacon blocked until consent, selective consent and revocation');
    } finally { await browser.close(); if (server) await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });
