/* Early consent enforcement for dynamic resources and JavaScript cookie writes. */
(function () {
    'use strict';
    var rules = window.marrisonConsentRules;
    if (!rules || window.MarrisonConsentRuntime) { return; }
    var cookieDescriptor = Object.getOwnPropertyDescriptor(Document.prototype, 'cookie');
    var nativeSetAttribute = Element.prototype.setAttribute;
    var valid = ['necessary', 'functional', 'analytics', 'marketing'];
    var verifiedState = null;
    var stateResolved = false;
    var singleContents = new Set();
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', {ad_storage: 'denied', analytics_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500});
    function updateProviders() {
        var categories = allowed();
        var marketing = categories.indexOf('marketing') !== -1;
        window.gtag('consent', 'update', {ad_storage: marketing ? 'granted' : 'denied', ad_user_data: marketing ? 'granted' : 'denied',
            ad_personalization: marketing ? 'granted' : 'denied', analytics_storage: categories.indexOf('analytics') !== -1 ? 'granted' : 'denied'});
        if (typeof window.fbq === 'function') { window.fbq('consent', marketing ? 'grant' : 'revoke'); }
    }
    var blockedHosts = [], reportTimer = null;
    function reportBlocked(value) {
        if (!rules.passive || !originalFetch || blockedHosts.length >= 20) { return; }
        var host;
        try { host = new URL(String(value), location.protocol + '//' + location.hostname).hostname; } catch (error) { return; }
        if (!host || host === location.hostname || blockedHosts.indexOf(host) !== -1) { return; }
        blockedHosts.push(host);
        if (reportTimer) { return; }
        reportTimer = setTimeout(function () {
            var body = new URLSearchParams({ action: 'marrison_cookie_blocked_hosts', nonce: rules.passive.nonce });
            blockedHosts.forEach(function (item) { body.append('hosts[]', item); });
            originalFetch.call(window, rules.passive.url, {method: 'POST', credentials: 'same-origin', body: body}).catch(function () {});
        }, 1500);
    }
    function rawCookies() { try { return cookieDescriptor.get.call(document); } catch (error) { return ''; } }
    function allowed() {
        if (rules.phase === 'after') { return valid; }
        if (rules.phase === 'before' || !verifiedState || !verifiedState.valid || verifiedState.expires * 1000 <= Date.now()) { return ['necessary']; }
        return valid.filter(function (category) { return category === 'necessary' || verifiedState.categories.indexOf(category) !== -1; });
    }
    function categoryForCookie(name) {
        if (rules.phase && name === 'marrison_cookie_scan_probe') { return 'necessary'; }
        if (/^(?:marrison_cookie_(?:consent|categories|state)|PHPSESSID|woocommerce_cart_hash|woocommerce_items_in_cart|wp_woocommerce_session_.+|wordpress_(?:logged_in|sec)_.+|wordpress_[a-f0-9]{32}|wordpress_test_cookie|wp-settings(?:-time)?-\d+)$/.test(name)) { return 'necessary'; }
        var rule = (rules.cookies || []).find(function (item) {
            var pattern = String(item.name).split('*').map(function (part) { return part.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }).join('.*');
            return new RegExp('^' + pattern + '$').test(name);
        });
        return rule && valid.indexOf(rule.category) !== -1 ? rule.category : 'functional';
    }
    function categoryForResource(value, element, hasResource) {
        if (element && singleContents.has(element)) { return 'necessary'; }
        var explicit = element && element.getAttribute('data-marrison-cookie-category');
        if (explicit && valid.indexOf(explicit) !== -1) { return explicit; }
        value = String(value || '').toLowerCase();
        // Parse real URLs before legacy inline-script rules; hostname suffixes need a dot boundary.
        // Inline data (for example JSON-LD @context) are not resource requests.
        var urls = element && !hasResource ? [] : value.match(/(?:https?:)?\/\/[^\s,"'<>`\\()]+|(?:^|\s)\/(?!\/)[^\s,"'<>`\\()]+/g) || [];
        var resourceCategory = null;
        for (var i = 0; i < urls.length; i++) {
            var url;
            try { url = new URL(urls[i].trim(), location.protocol + '//' + location.hostname); } catch (error) { continue; }
            var custom = Object.keys(rules.custom_sources || {}).find(function (category) {
                return rules.custom_sources[category].some(function (rule) {
                    rule = rule.toLowerCase();
                    if (rule.charAt(0) === '/') { return url.pathname.indexOf(rule) === 0; }
                    try {
                        var parsed = new URL(rule.indexOf('://') < 0 ? 'https://' + rule : rule);
                        return (url.hostname === parsed.hostname || url.hostname.endsWith('.' + parsed.hostname)) && url.pathname.indexOf(parsed.pathname) === 0;
                    } catch (error) { return false; }
                });
            });
            var service = (rules.catalog || []).find(function (entry) {
                return entry.patterns.some(function (pattern) {
                    var host = pattern.host.toLowerCase();
                    if (host ? url.hostname !== host && !(pattern.subdomains && url.hostname.endsWith('.' + host)) : url.hostname !== location.hostname) { return false; }
                    if (pattern.path && url.pathname.indexOf(pattern.path) !== 0) { return false; }
                    try {
                        return (!pattern.path_regex || new RegExp(pattern.path_regex, 'i').test(url.pathname))
                            && (!pattern.query_regex || new RegExp(pattern.query_regex, 'i').test(url.search.slice(1)));
                    } catch (error) { return false; }
                });
            });
            var category = custom || (service && service.category) || (rules.hosts && rules.hosts[url.hostname]);
            if (!category && rules.unknown_external && /^https?:$/.test(url.protocol) && url.hostname !== location.hostname) { category = 'unclassified'; }
            if (category === 'unclassified') { return category; }
            if (category && (!resourceCategory || resourceCategory === 'necessary')) { resourceCategory = category; }
        }
        if (resourceCategory) { return resourceCategory; }
        return Object.keys(rules.sources || {}).find(function (category) {
            return rules.sources[category].some(function (pattern) { return pattern && value.indexOf(String(pattern).toLowerCase()) !== -1; });
        }) || null;
    }
    function permitted(category) { return !category || allowed().indexOf(category) !== -1; }
    function neutralize(element, value, attribute) {
        if (!element || element.nodeType !== 1 || !/^(SCRIPT|IFRAME|IMG)$/.test(element.tagName)) { return false; }
        var hasResource = !!(value || element.getAttribute('src') || element.getAttribute('srcset'));
        var category = categoryForResource((value || element.getAttribute('src') || '') + ' ' + (element.getAttribute('srcset') || '') + ' ' + (element.tagName === 'SCRIPT' ? element.textContent : ''), element, hasResource);
        if (permitted(category)) { return false; }
        reportBlocked(value || element.getAttribute('src'));
        nativeSetAttribute.call(element, 'data-marrison-cookie-category', category);
        nativeSetAttribute.call(element, 'data-marrison-cookie-blocked', element.tagName.toLowerCase());
        var srcset = attribute === 'srcset' ? value : element.getAttribute('srcset');
        if (srcset) { nativeSetAttribute.call(element, 'data-marrison-blocked-srcset', srcset); element.removeAttribute('srcset'); }
        var src = attribute === 'srcset' ? element.getAttribute('src') : (value || element.getAttribute('src'));
        if (src) { nativeSetAttribute.call(element, 'data-marrison-blocked-src', src); element.removeAttribute('src'); }
        if (element.tagName === 'SCRIPT') {
            if (!element.hasAttribute('data-marrison-script-type')) { nativeSetAttribute.call(element, 'data-marrison-script-type', element.getAttribute('type') || ''); }
            nativeSetAttribute.call(element, 'type', 'text/plain');
        }
        return true;
    }
    function tree(node) {
        if (!node) { return; }
        neutralize(node);
        if (node.querySelectorAll) { node.querySelectorAll('script,iframe,img').forEach(function (element) { neutralize(element); }); }
    }
    ['appendChild', 'insertBefore', 'replaceChild'].forEach(function (method) {
        var original = Node.prototype[method];
        Node.prototype[method] = function (node) { tree(node); return original.apply(this, arguments); };
    });
    [Element.prototype, Document.prototype, DocumentFragment.prototype].forEach(function (prototype) {
        ['append', 'prepend', 'before', 'after', 'replaceWith', 'replaceChildren'].forEach(function (method) {
            var original = prototype[method];
            if (!Object.prototype.hasOwnProperty.call(prototype, method) || !original) { return; }
            prototype[method] = function () { Array.from(arguments).forEach(tree); return original.apply(this, arguments); };
        });
    });
    var originalAdjacentElement = Element.prototype.insertAdjacentElement;
    Element.prototype.insertAdjacentElement = function (position, node) { tree(node); return originalAdjacentElement.apply(this, arguments); };
    var innerHtmlDescriptor = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
    function filterHtml(html) {
        if (typeof html !== 'string' || !/<(?:script|iframe|img)\b/i.test(html)) { return html; }
        var template = document.createElement('template');
        innerHtmlDescriptor.set.call(template, html);
        tree(template.content);
        return innerHtmlDescriptor.get.call(template);
    }
    if (innerHtmlDescriptor && innerHtmlDescriptor.configurable) {
        Object.defineProperty(Element.prototype, 'innerHTML', { configurable: true, enumerable: innerHtmlDescriptor.enumerable,
            get: innerHtmlDescriptor.get, set: function (html) { innerHtmlDescriptor.set.call(this, /^(SCRIPT|STYLE|TEXTAREA|TITLE)$/.test(this.tagName) ? html : filterHtml(html)); }
        });
        var originalAdjacentHtml = Element.prototype.insertAdjacentHTML;
        Element.prototype.insertAdjacentHTML = function (position, html) { return originalAdjacentHtml.call(this, position, filterHtml(html)); };
    }
    Element.prototype.setAttribute = function (name, value) {
        var attribute = String(name).toLowerCase();
        if ((attribute === 'src' || attribute === 'srcset') && neutralize(this, String(value), attribute)) { return; }
        return nativeSetAttribute.apply(this, arguments);
    };
    [HTMLScriptElement, HTMLIFrameElement, HTMLImageElement].forEach(function (type) {
        var descriptor = Object.getOwnPropertyDescriptor(type.prototype, 'src');
        if (!descriptor || !descriptor.set || !descriptor.configurable) { return; }
        Object.defineProperty(type.prototype, 'src', { configurable: true, enumerable: descriptor.enumerable,
            get: descriptor.get, set: function (value) { if (!neutralize(this, String(value))) { descriptor.set.call(this, value); } }
        });
    });
    var srcsetDescriptor = Object.getOwnPropertyDescriptor(HTMLImageElement.prototype, 'srcset');
    if (srcsetDescriptor && srcsetDescriptor.configurable) {
        Object.defineProperty(HTMLImageElement.prototype, 'srcset', { configurable: true, enumerable: srcsetDescriptor.enumerable,
            get: srcsetDescriptor.get, set: function (value) { if (!neutralize(this, String(value), 'srcset')) { srcsetDescriptor.set.call(this, value); } }
        });
    }
    if (cookieDescriptor && cookieDescriptor.get && cookieDescriptor.set && cookieDescriptor.configurable) {
        Object.defineProperty(Document.prototype, 'cookie', { configurable: true, enumerable: cookieDescriptor.enumerable,
            get: cookieDescriptor.get,
            set: function (text) {
                var name = String(text).split('=')[0].trim();
                if (permitted(categoryForCookie(name)) || /(?:expires=Thu, 01 Jan 1970|max-age=0)/i.test(String(text))) { cookieDescriptor.set.call(this, text); }
            }
        });
    }
    var originalFetch = window.fetch;
    if (originalFetch) {
        window.fetch = function (input) {
            var url = typeof input === 'string' || input instanceof URL ? String(input) : input && input.url;
            if (!permitted(categoryForResource(url))) { reportBlocked(url); return Promise.reject(new DOMException('Blocked by cookie consent', 'NotAllowedError')); }
            return originalFetch.apply(this, arguments);
        };
    }
    var originalBeacon = navigator.sendBeacon;
    if (originalBeacon) { navigator.sendBeacon = function (url) { if (!permitted(categoryForResource(url))) { reportBlocked(url); return false; } return originalBeacon.apply(this, arguments); }; }
    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) { this.marrisonConsentCategory = categoryForResource(url); this.marrisonConsentUrl = url; return originalOpen.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function () { if (!permitted(this.marrisonConsentCategory)) { reportBlocked(this.marrisonConsentUrl); this.abort(); return; } return originalSend.apply(this, arguments); };
    function enforce() {
        // Pending verification blocks new optional activity, but cannot invalidate a
        // previous signed choice. A failed request remains pending until a retry.
        if (!stateResolved && rules.phase !== 'before' && rules.phase !== 'after') { return; }
        var names = rawCookies().split(';').map(function (part) { return part.split('=')[0].trim(); });
        names.forEach(function (name) {
            if (!name || permitted(categoryForCookie(name))) { return; }
            var scopes = (rules.cookies || []).filter(function (rule) { return rule.name === name && rule.path; });
            scopes.push({ path: '/', domain: '' });
            var path = location.pathname;
            while (path.lastIndexOf('/') > 0) { path = path.slice(0, path.lastIndexOf('/')); scopes.push({ path: path, domain: '' }); }
            scopes.forEach(function (scope) {
                var domains = ['', location.hostname, '.' + location.hostname];
                if (scope.domain) { domains.push(scope.domain, '.' + scope.domain.replace(/^\./, '')); }
                domains.forEach(function (domain) {
                    cookieDescriptor.set.call(document, name + '=; Max-Age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + scope.path + (domain ? '; domain=' + domain : '') + '; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : ''));
                });
            });
        });
    }
    window.MarrisonConsentRuntime = { allowed: allowed, enforce: enforce, cookieCategory: categoryForCookie,
        allowContent: function (element) {
            if (!element || element.tagName !== 'IFRAME') { return false; }
            try {
                var url = new URL(element.getAttribute('data-marrison-blocked-src'));
                if (url.protocol !== 'https:' || !/^(www\.)?youtube(?:-nocookie)?\.com$/.test(url.hostname) || url.pathname.indexOf('/embed/') !== 0) { return false; }
                url.hostname = 'www.youtube-nocookie.com';
                nativeSetAttribute.call(element, 'data-marrison-blocked-src', url.href);
                singleContents.add(element); return true;
            } catch (error) { return false; }
        },
        setState: function (state) {
            singleContents.forEach(function (frame) {
                var src = frame.getAttribute('src');
                if (src) { nativeSetAttribute.call(frame, 'data-marrison-blocked-src', src); frame.removeAttribute('src'); }
                nativeSetAttribute.call(frame, 'data-marrison-cookie-blocked', 'iframe');
            });
            singleContents.clear();
            stateResolved = true; verifiedState = state && state.valid && Array.isArray(state.categories) ? state : null; updateProviders(); enforce();
        }
    };
    enforce();
}());
