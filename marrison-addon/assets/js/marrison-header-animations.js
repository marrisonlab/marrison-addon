(function() {
    'use strict';

    var letterAnimations = ['marrisonLettersRise', 'marrisonLettersFocus', 'marrisonLettersElastic'];
    var customAnimations = ['marrisonLiftSoft', 'marrisonDropSoft', 'marrisonSlideReveal', 'marrisonCurtainRight', 'marrisonFocusIn', 'marrisonZoomSettle', 'marrisonTiltRise', 'marrisonSkewSweep', 'marrisonDriftLeft', 'marrisonElasticPop'].concat(letterAnimations);
    var headingStates = new WeakMap();

    function selectResponsiveAnimation(element) {
        var raw = element.getAttribute('data-marrison-heading-animations');
        if (!raw) {
            return findAnimationClass(element);
        }
        var settings;
        try {
            settings = JSON.parse(raw);
        } catch (error) {
            return '';
        }
        var frontend = window.elementorFrontend;
        var nativeAnimation = settings._animation || '';
        if (frontend && frontend.getCurrentDeviceSetting && frontend.breakpoints && frontend.elements && frontend.elements.$deviceMode) {
            nativeAnimation = frontend.getCurrentDeviceSetting(settings, '_animation') || '';
        } else {
            // The inline configuration is available before the native helpers initialize.
            var config = (frontend && frontend.config) || window.elementorFrontendConfig;
            var breakpoints = config && config.responsive && config.responsive.breakpoints;
            if (breakpoints) {
                Object.keys(breakpoints).filter(function(mode) {
                    return breakpoints[mode].is_enabled !== false && breakpoints[mode].direction === 'max';
                }).sort(function(a, b) {
                    return breakpoints[b].value - breakpoints[a].value;
                }).forEach(function(mode) {
                    if (window.innerWidth <= breakpoints[mode].value) {
                        nativeAnimation = settings['_animation_' + mode] || nativeAnimation;
                    }
                });
                var wide = breakpoints.widescreen;
                if (wide && wide.is_enabled !== false && window.innerWidth >= wide.value) {
                    nativeAnimation = settings._animation_widescreen || nativeAnimation;
                }
            } else {
                if (window.innerWidth <= 1024) {
                    nativeAnimation = settings._animation_tablet || nativeAnimation;
                }
                if (window.innerWidth <= 767) {
                    nativeAnimation = settings._animation_mobile || nativeAnimation;
                }
            }
        }
        var animation = nativeAnimation || settings.marrison_header_animation || '';
        var custom = customAnimations.indexOf(animation) !== -1;
        var state = headingStates.get(element) || { seen: false };
        if (!element.classList.contains('elementor-invisible') && (!nativeAnimation || nativeAnimation === 'none' || element.classList.contains('animated'))) {
            state.seen = true;
        }
        headingStates.set(element, state);
        var started = state.seen || element.classList.contains('animated') || (!nativeAnimation && custom);
        customAnimations.forEach(function(name) {
            if (name !== animation && element.classList.contains(name)) {
                element.classList.remove(name);
            }
        });
        element.classList.toggle('marrison-heading-animated', custom);
        element.classList.toggle('marrison-heading-letter-animation', letterAnimations.indexOf(animation) !== -1);
        // Native entrance animations retain Elementor's observer and delay.
        if (custom && started && !element.classList.contains(animation)) {
            element.classList.add(animation);
        }
        return custom ? animation : '';
    }

    function findAnimationClass(element) {
        for (var i = 0; i < letterAnimations.length; i++) {
            if (element.classList.contains(letterAnimations[i])) {
                return letterAnimations[i];
            }
        }

        return element.getAttribute('data-marrison-letter-animation') || '';
    }

    function getTextContainer(element) {
        var heading = element.classList.contains('elementor-heading-title') ? element : element.querySelector('.elementor-heading-title');

        if (!heading) {
            return null;
        }

        if (
            heading.childElementCount === 1 &&
            heading.firstElementChild &&
            heading.firstElementChild.tagName === 'A'
        ) {
            return heading.firstElementChild;
        }

        return heading;
    }

    function splitIntoLetters(container) {
        if (!container || (container.dataset.marrisonLettersReady === '1' && container.querySelector('.marrison-heading-letter'))) {
            return;
        }

        var text = container.textContent || '';
        if (!text.trim()) {
            return;
        }

        var letterIndex = 0;
        function visit(parent) {
            Array.prototype.slice.call(parent.childNodes).forEach(function(node) {
                if (node.nodeType === 1) {
                    if (!/^(SCRIPT|STYLE)$/.test(node.tagName)) {
                        visit(node);
                    }
                    return;
                }
                if (node.nodeType !== 3) {
                    return;
                }
                var fragment = document.createDocumentFragment();
                (node.nodeValue || '').split(/(\s+)/).forEach(function(token) {
                    if (!token) {
                        return;
                    }
                    if (/^\s+$/.test(token)) {
                        fragment.appendChild(document.createTextNode(token));
                        return;
                    }
                    var word = document.createElement('span');
                    word.className = 'marrison-heading-word';
                    Array.from(token).forEach(function(character) {
                        var letter = document.createElement('span');
                        letter.className = 'marrison-heading-letter';
                        letter.style.setProperty('--marrison-letter-index', letterIndex++);
                        letter.textContent = character;
                        word.appendChild(letter);
                    });
                    fragment.appendChild(word);
                });
                parent.replaceChild(fragment, node);
            });
        }
        visit(container);
        container.dataset.marrisonLettersReady = '1';
    }

    function processHeading(element) {
        var animation = selectResponsiveAnimation(element);
        var container = getTextContainer(element);

        if (!animation || letterAnimations.indexOf(animation) === -1) {
            if (container && container.dataset.marrisonLettersReady === '1') {
                var wrappers = container.querySelectorAll('.marrison-heading-word, .marrison-heading-letter');
                for (var i = wrappers.length - 1; i >= 0; i--) {
                    var fragment = document.createDocumentFragment();
                    while (wrappers[i].firstChild) {
                        fragment.appendChild(wrappers[i].firstChild);
                    }
                    wrappers[i].parentNode.replaceChild(fragment, wrappers[i]);
                }
                container.normalize();
                delete container.dataset.marrisonLettersReady;
            }
            return;
        }

        if (!container) {
            return;
        }

        splitIntoLetters(container);
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var selector = letterAnimations.map(function(animation) {
            return '.' + animation;
        }).join(',');
        var items = scope.querySelectorAll(selector + ', [data-marrison-letter-animation], [data-marrison-heading-animations]');

        for (var i = 0; i < items.length; i++) {
            processHeading(items[i]);
        }
    }

    function observe() {
        if (!document.body || typeof MutationObserver === 'undefined') {
            return;
        }

        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'attributes' && mutation.target.matches('[data-marrison-heading-animations]')) {
                    processHeading(mutation.target);
                }

                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];

                    if (node.nodeType !== 1) {
                        continue;
                    }

                    if (node.matches && (node.matches('[data-marrison-letter-animation], [data-marrison-heading-animations]') || letterAnimations.some(function(animation) { return node.classList.contains(animation); }))) {
                        processHeading(node);
                    }

                    scan(node);
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'data-marrison-heading-animations']
        });

        if (!(window.elementorFrontend && window.elementorFrontend.isEditMode && window.elementorFrontend.isEditMode())) {
            window.addEventListener('load', function() {
                window.setTimeout(function() {
                    observer.disconnect();
                }, 2000);
            }, { once: true });
        }
    }

    window.addEventListener('resize', function() { scan(document); });
    window.addEventListener('load', function() { scan(document); });
    if (window.jQuery) {
        window.jQuery(window).on('elementor/frontend/init', function() { scan(document); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            scan(document);
            observe();
        });
    } else {
        scan(document);
        observe();
    }
})();
