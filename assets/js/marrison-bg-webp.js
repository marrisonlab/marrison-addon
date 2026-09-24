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

    try {
        uploadsBasePath = new URL(config.uploadsBaseUrl, window.location.href).pathname;
    } catch (e) {
        uploadsBasePath = '';
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

                Object.keys(response.data.items).forEach(function(key) {
                    var entry = elementsByKey[key];
                    var item = response.data.items[key];

                    if (!entry || !entry.element || !item.url) {
                        return;
                    }

                    var element = entry.element;
                    var currentBackground = window.getComputedStyle(element).backgroundImage;
                    var currentUrl = extractBackgroundUrl(currentBackground);
                    cache[entry.cacheKey] = item.url;

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
        var elements = document.querySelectorAll('body *');
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

            var computedStyle = window.getComputedStyle(element);
            var backgroundImage = computedStyle.backgroundImage;
            if (!backgroundImage || backgroundImage === 'none' || backgroundImage.indexOf('url(') === -1) {
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

            if (cache[cacheKey]) {
                if (cache[cacheKey] !== url) {
                    element.style.backgroundImage = backgroundImage.split(url).join(cache[cacheKey]);
                }
                return;
            }

            var key = 'bg' + itemIndex++;
            items.push({
                key: key,
                url: url,
                width: targetWidth,
                height: targetHeight,
                fit: fit,
                format: preferredFormat
            });
            elementsByKey[key] = {
                element: element,
                cacheKey: cacheKey
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
