(function() {
    'use strict';

    var letterAnimations = ['marrisonLettersRise', 'marrisonLettersFocus', 'marrisonLettersElastic'];

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
        if (!container || container.dataset.marrisonLettersReady === '1') {
            return;
        }

        var text = container.textContent || '';
        if (!text.trim()) {
            return;
        }

        var tokens = text.split(/(\s+)/);
        var fragment = document.createDocumentFragment();
        var letterIndex = 0;

        for (var i = 0; i < tokens.length; i++) {
            var token = tokens[i];

            if (!token) {
                continue;
            }

            if (/^\s+$/.test(token)) {
                fragment.appendChild(document.createTextNode(token));
                continue;
            }

            var word = document.createElement('span');
            word.className = 'marrison-heading-word';

            for (var j = 0; j < token.length; j++) {
                var letter = document.createElement('span');
                letter.className = 'marrison-heading-letter';
                letter.style.setProperty('--marrison-letter-index', letterIndex);
                letter.textContent = token.charAt(j);
                word.appendChild(letter);
                letterIndex++;
            }

            fragment.appendChild(word);
        }

        container.textContent = '';
        container.appendChild(fragment);
        container.dataset.marrisonLettersReady = '1';
    }

    function processHeading(element) {
        var animation = findAnimationClass(element);

        if (!animation || letterAnimations.indexOf(animation) === -1) {
            return;
        }

        var container = getTextContainer(element);
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
        var items = scope.querySelectorAll(selector + ', [data-marrison-letter-animation]');

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

                if (mutation.type === 'attributes') {
                    var target = mutation.target;

                    if (target && target.nodeType === 1) {
                        processHeading(target);
                        scan(target);
                    }

                    continue;
                }

                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];

                    if (node.nodeType !== 1) {
                        continue;
                    }

                    if (node.matches && (node.matches('[data-marrison-letter-animation]') || letterAnimations.some(function(animation) { return node.classList.contains(animation); }))) {
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
            attributeFilter: ['class', 'data-marrison-letter-animation']
        });
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
