(function() {
    'use strict';

    if (!window.marrisonBgWebp || !window.marrisonBgWebp.ajaxUrl || !window.marrisonBgWebp.uploadsBaseUrl) {
        return;
    }

    var config = window.marrisonBgWebp;
    var uploadsBasePath = '';
    var cache = {};
    var pendingTimer = null;
    var preferredFormat = config.preferAvif ? 'avif' : 'webp';
    var candidateSelector = [
        'body',
        '[style*="background-image"]',
        '.elementor-element',
        '.elementor-background-slideshow__slide__image',
        '.elementor-widget-wrap',
        '.e-con',
        '.e-con-inner'
    ].join(',');
    var sessionStoragePrefix = 'marrison-bg-webp:' + (config.cacheVersion || '1') + ':';
    var sessionStore = null;

    try {
        uploadsBasePath = new URL(config.uploadsBaseUrl, window.location.href).pathname;
    } catch (e) {
        uploadsBasePath = '';
    }

    try {
        sessionStore = window.sessionStorage || null;
    } catch (e) {
        sessionStore = null;
    }

    function isUploadsUrl(url) {
        if (!url) {
            return false;
        }

        if (url.indexOf(config.uploadsBaseUrl) === 0) {
            return true;
        }

        if (url.indexOf(config.uploadsBaseUrl.replace(/^https?:/i, '')) === 0) {
            return true;
        }

        try {
            return uploadsBasePath && new URL(url, window.location.href).pathname.indexOf(uploadsBasePath) === 0;
        } catch (e) {
            return false;
        }
    }

    function extractBackgroundUrl(backgroundImage) {
        var match;
        var pattern = /url\((["']?)(.*?)\1\)/g;

        while ((match = pattern.exec(backgroundImage)) !== null) {
            if (isUploadsUrl(match[2])) {
                return match[2];
            }
        }

        return '';
    }

    function readCachedUrl(cacheKey) {
        if (Object.prototype.hasOwnProperty.call(cache, cacheKey)) {
            return cache[cacheKey];
        }

        if (!sessionStore) {
            return null;
        }

        try {
            var stored = sessionStore.getItem(sessionStoragePrefix + cacheKey);
            if (stored !== null) {
                cache[cacheKey] = stored;
                return stored;
            }
        } catch (e) {}

        return null;
    }

    function rememberCachedUrl(cacheKey, url) {
        var value = url || '';
        cache[cacheKey] = value;

        if (!sessionStore) {
            return;
        }

        try {
            sessionStore.setItem(sessionStoragePrefix + cacheKey, value);
        } catch (e) {}
    }

    function isNearViewport(rect) {
        var marginY = Math.max(window.innerHeight || 0, 800);
        return rect.bottom >= -marginY && rect.top <= (window.innerHeight || 0) + marginY;
    }

    function requestBestSizes(items, elementsByKey) {
        var formData = new FormData();
        formData.append('action', 'marrison_resolve_background_webp');

        items.forEach(function(item, index) {
            formData.append('items[' + index + '][key]', item.key);
            formData.append('items[' + index + '][url]', item.url);
            formData.append('items[' + index + '][width]', item.width);
            formData.append('items[' + index + '][height]', item.height);
            formData.append('items[' + index + '][fit]', item.fit);
            formData.append('items[' + index + '][format]', item.format);
        });

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(response) {
                if (!response || !response.success || !response.data || !response.data.items) {
                    return;
                }

                var resolvedItems = response.data.items || {};

                Object.keys(elementsByKey).forEach(function(key) {
                    var entry = elementsByKey[key];
                    var item = resolvedItems[key];

                    if (!entry || !entry.element) {
                        return;
                    }

                    if (!item || !item.url) {
                        rememberCachedUrl(entry.cacheKey, entry.originalUrl);
                        return;
                    }

                    var element = entry.element;
                    var currentBackground = window.getComputedStyle(element).backgroundImage;
                    var currentUrl = extractBackgroundUrl(currentBackground);
                    rememberCachedUrl(entry.cacheKey, item.url);

                    if (!currentUrl || currentUrl === item.url) {
                        return;
                    }

                    element.style.backgroundImage = currentBackground.split(currentUrl).join(item.url);
                    element.setAttribute('data-marrison-bg-webp-width', item.width || '');
                });
            })
            .catch(function() {});
    }

    function scanBackgrounds() {
        var elements = document.querySelectorAll(candidateSelector);
        var items = [];
        var elementsByKey = {};
        var deviceRatio = Math.max(1, Math.min(3, window.devicePixelRatio || 1));
        var itemIndex = 0;

        elements.forEach(function(element) {
            if (items.length >= 50) {
                return;
            }

            var rect = element.getBoundingClientRect();
            if (rect.width < 1 || rect.height < 1) {
                return;
            }
            if (!isNearViewport(rect)) {
                return;
            }

            var computedStyle = window.getComputedStyle(element);
            var backgroundImage = computedStyle.backgroundImage;
            if (!backgroundImage || backgroundImage === 'none' || backgroundImage.indexOf('url(') === -1) {
                return;
            }

            // Intrinsic sizing depends on the source image dimensions. Replacing it
            // with a smaller variant would visibly shrink an auto-sized background.
            if (/\bauto\b/.test(computedStyle.backgroundSize)) {
                return;
            }

            var url = extractBackgroundUrl(backgroundImage);
            if (!url) {
                return;
            }

            var targetWidth = Math.ceil(rect.width * deviceRatio);
            var targetHeight = Math.ceil(rect.height * deviceRatio);
            var widthBucket = Math.ceil(targetWidth / 100) * 100;
            var heightBucket = Math.ceil(targetHeight / 100) * 100;
            var fit = computedStyle.backgroundSize.indexOf('cover') !== -1 ? 'cover' : 'width';
            var cacheKey = url + '|' + widthBucket + '|' + heightBucket + '|' + fit + '|' + preferredFormat;
            var cachedUrl = readCachedUrl(cacheKey);

            if (cachedUrl !== null) {
                if (cachedUrl && cachedUrl !== url) {
                    element.style.backgroundImage = backgroundImage.split(url).join(cachedUrl);
                }
                return;
            }

            var key = 'bg' + itemIndex++;
            items.push({
                key: key,
                url: url,
                width: widthBucket,
                height: heightBucket,
                fit: fit,
                format: preferredFormat
            });
            elementsByKey[key] = {
                element: element,
                cacheKey: cacheKey,
                originalUrl: url
            };
        });

        if (!items.length) {
            return;
        }

        requestBestSizes(items, elementsByKey);
    }

    function scheduleScan() {
        if (pendingTimer) {
            window.clearTimeout(pendingTimer);
        }

        pendingTimer = window.setTimeout(function() {
            pendingTimer = null;
            scanBackgrounds();
        }, 80);
    }

    function nodeMayContainBackgroundImage(node) {
        if (!node || node.nodeType !== 1) {
            return false;
        }

        if (
            (node.matches && node.matches('.elementor-background-slideshow__slide__image, [style*="background-image"]')) ||
            (node.querySelector && node.querySelector('.elementor-background-slideshow__slide__image, [style*="background-image"]'))
        ) {
            return true;
        }

        return false;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleScan);
    } else {
        scheduleScan();
    }

    window.addEventListener('load', scheduleScan);
    window.addEventListener('resize', scheduleScan);

    if (window.MutationObserver && document.body) {
        new MutationObserver(function(mutations) {
            var shouldScan = mutations.some(function(mutation) {
                return Array.prototype.some.call(mutation.addedNodes, nodeMayContainBackgroundImage);
            });

            if (shouldScan) {
                scheduleScan();
            }
        }).observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', scheduleScan);
    }
})();
