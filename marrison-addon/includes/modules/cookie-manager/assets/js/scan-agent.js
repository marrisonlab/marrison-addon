/* Runs only on an expiring, signed scan URL inside a credentialless iframe. */
(function () {
    'use strict';
    var config = window.marrisonScanFrame;
    if (!config || window.parent === window) { return; }
    var sent = false;
    var observed = new Map();
    var started = performance.now();
    function report(error) {
        if (sent) { return; }
        sent = true;
        window.parent.postMessage({ type: 'marrison-cookie-scan', job: config.job, token: config.token,
            url: config.url, phase: config.phase, cookies: Array.from(observed.values()),
            elapsed: Math.round(performance.now() - started), error: error || '' }, config.origin);
    }
    if (window.credentialless !== true) {
        report('Il browser non ha isolato la sessione. Nessun cookie amministrativo è stato acquisito.');
        return;
    }
    function keep(cookie) {
        if (!cookie.cookie_name || !/^[!#$%&'*+.^_`|~0-9a-z-]{1,255}$/i.test(cookie.cookie_name)) { return; }
        var key = cookie.cookie_name + '\0' + cookie.cookie_domain + '\0' + cookie.cookie_path;
        if (!observed.has(key) || cookie.scope_known) { observed.set(key, cookie); }
    }
    var descriptor = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie') || Object.getOwnPropertyDescriptor(HTMLDocument.prototype, 'cookie');
    function names(text) { return String(text || '').split(';').map(function (part) { return part.split('=')[0].trim(); }); }
    function written(text) {
        var parts = String(text).split(';');
        var name = parts.shift().split('=')[0].trim();
        var attrs = {};
        parts.forEach(function (part) {
            var i = part.indexOf('=');
            attrs[(i < 0 ? part : part.slice(0, i)).trim().toLowerCase()] = i < 0 ? true : part.slice(i + 1).trim();
        });
        var expires = null;
        if (typeof attrs['max-age'] === 'string' && /^-?\d+$/.test(attrs['max-age'])) { expires = Date.now() + Number(attrs['max-age']) * 1000; }
        else if (typeof attrs.expires === 'string') { expires = Date.parse(attrs.expires); }
        if (expires !== null && (!Number.isFinite(expires) || expires <= Date.now())) { return; }
        if (descriptor && names(descriptor.get.call(document)).indexOf(name) < 0) { return; }
        var path = location.pathname;
        path = path.lastIndexOf('/') > 0 ? path.slice(0, path.lastIndexOf('/')) : '/';
        keep({ cookie_name: name, cookie_domain: typeof attrs.domain === 'string' ? attrs.domain.replace(/^\./, '').toLowerCase() : location.hostname,
            cookie_path: typeof attrs.path === 'string' && attrs.path.charAt(0) === '/' ? attrs.path : path,
            expires: expires, secure: !!attrs.secure, same_site: typeof attrs.samesite === 'string' ? attrs.samesite.toLowerCase() : 'unknown', scope_known: true });
    }
    if (descriptor && descriptor.get && descriptor.set) {
        try {
            Object.defineProperty(document, 'cookie', { configurable: true,
                get: function () { return descriptor.get.call(document); },
                set: function (text) { descriptor.set.call(document, text); written(text); }
            });
        } catch (error) { /* Cookie Store API and final snapshot still report actual cookies. */ }
    }
    async function snapshot() {
        if (window.cookieStore && typeof window.cookieStore.getAll === 'function') {
            try {
                var cookies = await window.cookieStore.getAll();
                cookies.forEach(function (cookie) {
                    keep({ cookie_name: cookie.name, cookie_domain: (cookie.domain || location.hostname).replace(/^\./, '').toLowerCase(),
                        cookie_path: cookie.path || '/', expires: cookie.expires, secure: !!cookie.secure,
                        same_site: cookie.sameSite || 'unknown', scope_known: true });
                });
                return;
            } catch (error) { /* Fall back to names with explicitly unknown scope/expiration. */ }
        }
        names(document.cookie).forEach(function (name) {
            if (!Array.from(observed.values()).some(function (cookie) { return cookie.cookie_name === name; })) {
                keep({ cookie_name: name, cookie_domain: location.hostname, cookie_path: '/', expires: null, secure: false, same_site: 'unknown', scope_known: false });
            }
        });
    }
    var lastResource = performance.now();
    try { new PerformanceObserver(function () { lastResource = performance.now(); }).observe({ type: 'resource', buffered: true }); } catch (error) {}
    var finishTimer = null;
    async function finish() {
        clearTimeout(finishTimer);
        if (sent) { return; }
        try { await Promise.race([snapshot(), new Promise(function (resolve) { setTimeout(resolve, 1200); })]); }
        catch (error) { report('Cookie non leggibili nel contesto isolato.'); return; }
        report('');
    }
    function settle() {
        if (sent) { return; }
        if (performance.now() - started > 6500 || performance.now() - lastResource > 900) { finish(); }
        else { finishTimer = setTimeout(settle, 250); }
    }
    function loaded() {
        var steps = 0;
        var scroll = setInterval(function () {
            if (sent) { clearInterval(scroll); return; }
            window.scrollBy(0, Math.max(400, innerHeight * 0.8));
            steps++;
            if (steps >= 10 || scrollY + innerHeight >= document.documentElement.scrollHeight) {
                clearInterval(scroll);
                finishTimer = setTimeout(settle, 1000);
            }
        }, 180);
    }
    if (document.readyState === 'complete') { loaded(); }
    else { window.addEventListener('load', loaded, { once: true }); }
    setTimeout(finish, 8000);
}());
