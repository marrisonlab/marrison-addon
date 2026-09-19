(function() {
    'use strict';

    var headerSelector = '#hdr';
    var offsetProperty = '--marrison-anchor-offset';
    var header = null;
    var lastOffset = -1;

    function getHeader() {
        if (header && document.documentElement.contains(header)) {
            return header;
        }

        header = document.querySelector(headerSelector);
        return header;
    }

    function getHeaderHeight() {
        var activeHeader = getHeader();

        if (!activeHeader) {
            return 0;
        }

        var rect = activeHeader.getBoundingClientRect();
        return Math.max(0, Math.ceil(rect.height || activeHeader.offsetHeight || 0));
    }

    function updateOffset() {
        var offset = getHeaderHeight();

        if (offset === lastOffset) {
            return offset;
        }

        lastOffset = offset;
        document.documentElement.style.setProperty(offsetProperty, offset + 'px');
        return offset;
    }

    function addScrollStyles() {
        if (document.getElementById('marrison-anchor-offset-style')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'marrison-anchor-offset-style';
        style.textContent = 'html{scroll-padding-top:var(' + offsetProperty + ',0px)}[id]{scroll-margin-top:var(' + offsetProperty + ',0px)}';
        document.head.appendChild(style);
    }

    function getHashId(hash) {
        if (!hash || hash === '#') {
            return '';
        }

        var id = hash.slice(1);

        try {
            id = decodeURIComponent(id);
        } catch (error) {
            return '';
        }

        return id;
    }

    function getTargetByHash(hash) {
        var id = getHashId(hash);

        if (!id) {
            return null;
        }

        return document.getElementById(id) || document.getElementsByName(id)[0] || null;
    }

    function isSamePageUrl(url) {
        return url.origin === window.location.origin &&
            url.pathname.replace(/\/$/, '') === window.location.pathname.replace(/\/$/, '') &&
            url.search === window.location.search;
    }

    function scrollToTarget(target, smooth) {
        var offset = updateOffset();
        var targetTop = target.getBoundingClientRect().top + window.pageYOffset - offset;
        var behavior = smooth && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'smooth' : 'auto';

        window.scrollTo({
            top: Math.max(0, targetTop),
            behavior: behavior
        });
    }

    function handleAnchorClick(event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = event.target.closest('a[href*="#"]');

        if (!link) {
            return;
        }

        var url;

        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return;
        }

        if (!url.hash || !isSamePageUrl(url)) {
            return;
        }

        var target = getTargetByHash(url.hash);

        if (!target) {
            return;
        }

        event.preventDefault();
        scrollToTarget(target, true);

        if (window.history && window.history.pushState) {
            window.history.pushState(null, '', url.hash);
        }
    }

    function adjustCurrentHash() {
        var target = getTargetByHash(window.location.hash);

        if (!target) {
            updateOffset();
            return;
        }

        scrollToTarget(target, false);
    }

    function watchHeaderSize() {
        var activeHeader = getHeader();

        if (!activeHeader || typeof ResizeObserver === 'undefined') {
            return;
        }

        var observer = new ResizeObserver(function() {
            updateOffset();
        });

        observer.observe(activeHeader);
    }

    function init() {
        addScrollStyles();
        updateOffset();
        watchHeaderSize();
        document.addEventListener('click', handleAnchorClick);
        window.addEventListener('hashchange', function() {
            window.setTimeout(adjustCurrentHash, 0);
        });
        window.setTimeout(adjustCurrentHash, 0);
        window.setTimeout(adjustCurrentHash, 250);
        window.setTimeout(updateOffset, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
