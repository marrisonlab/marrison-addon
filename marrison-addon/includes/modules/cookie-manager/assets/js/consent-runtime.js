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
    function categoryForResource(value, element) {
        var explicit = element && element.getAttribute('data-marrison-cookie-category');
        if (explicit && valid.indexOf(explicit) !== -1) { return explicit; }
        value = String(value || '').toLowerCase();
        return Object.keys(rules.sources || {}).find(function (category) {
            return rules.sources[category].some(function (pattern) { return pattern && value.indexOf(String(pattern).toLowerCase()) !== -1; });
        }) || null;
    }
    function permitted(category) { return !category || allowed().indexOf(category) !== -1; }
    function neutralize(element, value, attribute) {
        if (!element || element.nodeType !== 1 || !/^(SCRIPT|IFRAME|IMG)$/.test(element.tagName)) { return false; }
        var category = categoryForResource((value || element.getAttribute('src') || '') + ' ' + (element.getAttribute('srcset') || '') + ' ' + (element.tagName === 'SCRIPT' ? element.textContent : ''), element);
        if (permitted(category)) { return false; }
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
            if (!permitted(categoryForResource(url))) { return Promise.reject(new DOMException('Blocked by cookie consent', 'NotAllowedError')); }
            return originalFetch.apply(this, arguments);
        };
    }
    var originalBeacon = navigator.sendBeacon;
    if (originalBeacon) { navigator.sendBeacon = function (url) { return permitted(categoryForResource(url)) ? originalBeacon.apply(this, arguments) : false; }; }
    var originalOpen = XMLHttpRequest.prototype.open;
    var originalSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) { this.marrisonConsentCategory = categoryForResource(url); return originalOpen.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function () { if (!permitted(this.marrisonConsentCategory)) { this.abort(); return; } return originalSend.apply(this, arguments); };
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
        setState: function (state) { stateResolved = true; verifiedState = state && state.valid && Array.isArray(state.categories) ? state : null; enforce(); }
    };
    enforce();
}());
