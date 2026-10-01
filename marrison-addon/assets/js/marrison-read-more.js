(function() {
	'use strict';

	var roots = new WeakMap();
	var resizeHandlerAttached = false;
	var resizeTimer = null;

	function requestFrame(callback) {
		if (window.requestAnimationFrame) {
			window.requestAnimationFrame(callback);
			return;
		}

		window.setTimeout(callback, 16);
	}

	function parseConfig(root) {
		var raw = root.getAttribute('data-marrison-read-more');
		var config = {};

		if (raw) {
			try {
				config = JSON.parse(raw);
			} catch (error) {
				config = {};
			}
		}

		var lines = parseInt(config.lines, 10);
		if (! isFinite(lines) || lines < 1) {
			lines = 3;
		}

		return {
			lines: Math.min(30, lines),
			labelMore: typeof config.labelMore === 'string' && config.labelMore ? config.labelMore : 'Leggi di più',
			labelLess: typeof config.labelLess === 'string' && config.labelLess ? config.labelLess : 'Leggi di meno'
		};
	}

	function getLineHeight(element) {
		var style = window.getComputedStyle(element);
		var lineHeight = parseFloat(style.lineHeight);

		if (! isFinite(lineHeight) || lineHeight <= 0) {
			var fontSize = parseFloat(style.fontSize);
			lineHeight = isFinite(fontSize) && fontSize > 0 ? fontSize * 1.2 : 20;
		}

		return lineHeight;
	}

	function getReadableBackground(element) {
		var current = element;

		while (current && current !== document.documentElement) {
			var color = window.getComputedStyle(current).backgroundColor;
			if (color && color !== 'transparent' && color !== 'rgba(0, 0, 0, 0)') {
				return color;
			}

			current = current.parentElement;
		}

		return '#fff';
	}

	function setState(state, expanded, refreshLayout) {
		state.rendering = true;
		state.expanded = expanded;
		state.root.classList.toggle('is-expanded', expanded);
		state.root.classList.toggle('is-collapsed', ! expanded);
		state.button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
		state.button.textContent = expanded ? state.config.labelLess : state.config.labelMore;

		if (expanded) {
			state.content.innerHTML = state.originalHtml;
			state.content.style.maxHeight = 'none';
			state.content.style.overflow = 'visible';
		} else {
			state.content.innerHTML = state.collapsedHtml;
			state.content.style.maxHeight = state.collapsedHeight + 'px';
			state.content.style.overflow = 'hidden';
		}

		if (refreshLayout !== false) {
			scheduleLayoutRefresh(state);
		}

		requestFrame(function() {
			requestFrame(function() {
				state.rendering = false;
			});
		});
	}

	function closestElement(element, selector) {
		if (! element) {
			return null;
		}

		if (element.closest) {
			return element.closest(selector);
		}

		for (var current = element; current; current = current.parentElement) {
			if (current.matches && current.matches(selector)) {
				return current;
			}
		}

		return null;
	}

	function addUnique(items, item) {
		if (item && items.indexOf(item) === -1) {
			items.push(item);
		}
	}

	function refreshJQueryLayout(elements) {
		if (! window.jQuery) {
			return;
		}

		elements.forEach(function(element) {
			var $element = window.jQuery(element);

			if (typeof $element.isotope === 'function') {
				try {
					$element.isotope('layout');
				} catch (error) {}
			}

			if (typeof $element.masonry === 'function') {
				try {
					$element.masonry('layout');
				} catch (error) {}
			}

			refreshJQueryLayoutData($element);
		});

	}

	function refreshJQueryLayoutData($element) {
		if (! $element || typeof $element.data !== 'function') {
			return;
		}

		[ 'masonry', 'Masonry', 'isotope', 'Isotope', 'packery', 'Packery' ].forEach(function(key) {
			try {
				refreshLayoutInstance($element.data(key));
			} catch (error) {}
		});

		try {
			var data = $element.data();
			if (! data) {
				return;
			}

			Object.keys(data).forEach(function(key) {
				refreshLayoutInstance(data[key]);
			});
		} catch (error) {}
	}

	function refreshLayoutInstance(instance) {
		if (! instance) {
			return false;
		}

		if (typeof instance.layout === 'function') {
			try {
				instance.layout();
				return true;
			} catch (error) {}
		}

		if (typeof instance.arrange === 'function') {
			try {
				instance.arrange();
				return true;
			} catch (error) {}
		}

		return false;
	}

	function refreshNativeLayout(elements) {
		[ 'Masonry', 'Isotope', 'Packery' ].forEach(function(constructorName) {
			var Constructor = window[constructorName];

			if (! Constructor || typeof Constructor.data !== 'function') {
				return;
			}

			elements.forEach(function(element) {
				try {
					refreshLayoutInstance(Constructor.data(element));
				} catch (error) {}
			});
		});
	}

	function getElementHeight(element) {
		if (! element) {
			return 0;
		}

		if (element.getBoundingClientRect) {
			var rect = element.getBoundingClientRect();
			if (rect && rect.height > 0) {
				return Math.ceil(rect.height);
			}
		}

		return Math.ceil(element.scrollHeight || 0);
	}

	function getRequiredHeight(container, child) {
		if (! container || ! child) {
			return 0;
		}

		if (container.getBoundingClientRect && child.getBoundingClientRect) {
			var containerRect = container.getBoundingClientRect();
			var childRect = child.getBoundingClientRect();

			if (containerRect && childRect) {
				return Math.max(0, Math.ceil(childRect.bottom - containerRect.top));
			}
		}

		return Math.ceil(child.scrollHeight || 0);
	}

	function rememberLayoutStyle(element) {
		if (! element || ! element.style || element.__marrisonReadMoreLayoutStyle) {
			return;
		}

		element.__marrisonReadMoreLayoutStyle = {
			height: element.style.height || '',
			minHeight: element.style.minHeight || ''
		};
	}

	function prepareManagedElement(element) {
		if (! element || ! element.style) {
			return;
		}

		rememberLayoutStyle(element);
		element.style.height = 'auto';
		element.style.minHeight = element.__marrisonReadMoreLayoutStyle.minHeight || '';
	}

	function applyManagedHeight(element, height, lockHeight) {
		if (! element || ! element.style || height <= 0) {
			return;
		}

		rememberLayoutStyle(element);
		element.style.minHeight = height + 'px';

		if (lockHeight) {
			element.style.height = height + 'px';
		}
	}

	function getLayoutTargets(readMoreRoot) {
		var targets = [];

		addUnique(targets, readMoreRoot);

		if (readMoreRoot && readMoreRoot.querySelector) {
			addUnique(targets, readMoreRoot.querySelector('.marrison-read-more-content'));
			addUnique(targets, readMoreRoot.querySelector('.marrison-read-more-toggle-wrap'));
		}

		return targets;
	}

	function getReadMoreRequiredHeight(container, scope) {
		if (! container || ! scope) {
			return 0;
		}

		var requiredHeight = 0;
		var readMoreRoots = [];

		if (scope.matches && scope.matches('[data-marrison-read-more]')) {
			readMoreRoots.push(scope);
		}

		if (scope.querySelectorAll) {
			var nestedRoots = scope.querySelectorAll('[data-marrison-read-more]');
			for (var index = 0; index < nestedRoots.length; index++) {
				addUnique(readMoreRoots, nestedRoots[index]);
			}
		}

		readMoreRoots.forEach(function(readMoreRoot) {
			getLayoutTargets(readMoreRoot).forEach(function(target) {
				requiredHeight = Math.max(requiredHeight, getRequiredHeight(container, target));
			});
		});

		return requiredHeight;
	}

	function getLayoutChain(root, listingItem) {
		var chain = [];

		for (var current = root.parentElement; current && current !== listingItem; current = current.parentElement) {
			chain.push(current);
		}

		if (listingItem) {
			chain.push(listingItem);
		}

		return chain;
	}

	function expandListingChain(root, listingItem) {
		if (! listingItem || ! listingItem.style) {
			return;
		}

		var chain = getLayoutChain(root, listingItem);
		chain.forEach(prepareManagedElement);

		var requiredItemHeight = Math.max(
			getElementHeight(listingItem),
			getRequiredHeight(listingItem, root),
			getReadMoreRequiredHeight(listingItem, listingItem)
		);
		if (requiredItemHeight > 0) {
			applyManagedHeight(listingItem, requiredItemHeight, true);
		}

		for (var current = root.parentElement; current && current !== listingItem; current = current.parentElement) {
			if (! current.style) {
				continue;
			}

			var requiredHeight = Math.max(
				getElementHeight(current),
				getRequiredHeight(current, root),
				getReadMoreRequiredHeight(current, current)
			);
			if (requiredHeight > 0) {
				applyManagedHeight(current, requiredHeight, false);
			}
		}
	}

	function refreshLayoutForRoot(root) {
		var elements = [];
		var listingItem = closestElement(root, '.jet-listing-grid__item');

		expandListingChain(root, listingItem);

		addUnique(elements, listingItem);
		addUnique(elements, closestElement(root, '.jet-listing-grid__items'));
		addUnique(elements, closestElement(root, '.jet-listing-grid'));
		addUnique(elements, closestElement(root, '.elementor-widget-jet-listing-grid'));

		refreshJQueryLayout(elements);
		refreshNativeLayout(elements);
	}

	function scheduleLayoutRefresh(state) {
		if (! state || state.layoutRefreshScheduled) {
			return;
		}

		state.layoutRefreshScheduled = true;
		refreshLayoutForRoot(state.root);

		requestFrame(function() {
			refreshLayoutForRoot(state.root);

			requestFrame(function() {
				refreshLayoutForRoot(state.root);
				state.layoutRefreshScheduled = false;
			});
		});
	}

	function getTextLength(node) {
		if (node.nodeType === 3) {
			return (node.nodeValue || '').length;
		}

		var length = 0;
		for (var index = 0; index < node.childNodes.length; index++) {
			length += getTextLength(node.childNodes[index]);
		}

		return length;
	}

	function appendSuffix(state) {
		if (! state.lastText || state.suffixAdded) {
			return;
		}

		state.lastText.nodeValue = (state.lastText.nodeValue || '').replace(/\s+$/, '') + state.suffix;
		state.suffixAdded = true;
	}

	function truncateChildren(parent, state) {
		for (var index = 0; index < parent.childNodes.length; index++) {
			var child = parent.childNodes[index];

			if (state.done) {
				parent.removeChild(child);
				index--;
				continue;
			}

			if (child.nodeType === 3) {
				var text = child.nodeValue || '';

				if (text.length <= state.remaining) {
					state.remaining -= text.length;
					if (text.trim()) {
						state.lastText = child;
					}
					continue;
				}

				var kept = text.slice(0, Math.max(0, state.remaining)).replace(/\s+$/, '');
				if (kept) {
					child.nodeValue = kept + state.suffix;
					state.lastText = child;
					state.suffixAdded = true;
				} else if (state.lastText) {
					appendSuffix(state);
					parent.removeChild(child);
					index--;
				} else {
					child.nodeValue = state.suffix;
					state.lastText = child;
					state.suffixAdded = true;
				}

				state.done = true;
				continue;
			}

			if (child.nodeType === 1) {
				truncateChildren(child, state);
			}
		}
	}

	function truncateContent(content, maxChars, suffix) {
		var state = {
			remaining: Math.max(0, maxChars),
			done: false,
			lastText: null,
			suffix: suffix,
			suffixAdded: false
		};

		truncateChildren(content, state);

		if (! state.suffixAdded) {
			appendSuffix(state);
		}
	}

	function buildCollapsedHtml(state, collapsedHeight) {
		var content = state.content;
		var totalLength = getTextLength(content);
		var low = 0;
		var high = totalLength;
		var best = 0;

		while (low <= high) {
			var middle = Math.floor((low + high) / 2);
			content.innerHTML = state.originalHtml;
			truncateContent(content, middle, '....');

			if (content.scrollHeight <= collapsedHeight + 1) {
				best = middle;
				low = middle + 1;
			} else {
				high = middle - 1;
			}
		}

		content.innerHTML = state.originalHtml;
		truncateContent(content, best, '....');

		return content.innerHTML;
	}

	function measure(state) {
		var root = state.root;
		var content = state.content;
		content.innerHTML = state.originalHtml;

		var lineHeight = getLineHeight(content);
		var collapsedHeight = Math.ceil(lineHeight * state.config.lines);
		state.collapsedHeight = collapsedHeight;

		root.style.setProperty('--marrison-read-more-collapsed-height', collapsedHeight + 'px');
		root.style.setProperty('--marrison-read-more-background', getReadableBackground(content));

		var wasExpanded = state.expanded;
		root.classList.remove('marrison-read-more--active', 'is-collapsed', 'is-expanded');
		content.style.maxHeight = 'none';
		content.style.overflow = 'visible';
		state.toggleWrap.style.display = 'none';

		var isOverflowing = content.scrollHeight > collapsedHeight + 1;
		if (! isOverflowing) {
			content.innerHTML = state.originalHtml;
			state.button.setAttribute('aria-expanded', 'false');
			state.button.textContent = state.config.labelMore;
			state.expanded = false;
			return;
		}

		root.classList.add('marrison-read-more--active');
		state.toggleWrap.style.display = 'block';
		state.collapsedHtml = buildCollapsedHtml(state, collapsedHeight);
		setState(state, wasExpanded, false);
	}

	function initRoot(root) {
		if (! root || roots.has(root)) {
			return;
		}

		var content = root.querySelector('.marrison-read-more-content');
		var toggleWrap = root.querySelector('.marrison-read-more-toggle-wrap');
		var button = root.querySelector('.marrison-read-more-toggle');

		if (! content || ! toggleWrap || ! button) {
			return;
		}

		var state = {
			root: root,
			content: content,
			toggleWrap: toggleWrap,
			button: button,
			config: parseConfig(root),
			originalHtml: content.innerHTML,
			collapsedHtml: '',
			collapsedHeight: 0,
			expanded: false
		};

		roots.set(root, state);

		button.textContent = state.config.labelMore;
		button.addEventListener('click', function() {
			setState(state, ! state.expanded);
		});

		if (typeof ResizeObserver !== 'undefined') {
			state.resizeObserver = new ResizeObserver(function() {
				if (state.rendering) {
					scheduleLayoutRefresh(state);
					return;
				}

				measure(state);
			});
			state.resizeObserver.observe(content);
		}

		measure(state);
		window.requestAnimationFrame(function() {
			measure(state);
		});
	}

	function initAll(scope) {
		var root = scope || document;
		var items = root.querySelectorAll('[data-marrison-read-more]');

		for (var index = 0; index < items.length; index++) {
			initRoot(items[index]);
		}

		if (! resizeHandlerAttached) {
			resizeHandlerAttached = true;
			window.addEventListener('resize', function() {
				window.clearTimeout(resizeTimer);
				resizeTimer = window.setTimeout(function() {
					document.querySelectorAll('[data-marrison-read-more]').forEach(function(rootElement) {
						var state = roots.get(rootElement);
						if (state) {
							measure(state);
						}
					});
				}, 120);
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			initAll(document);
		});
	} else {
		initAll(document);
	}

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/text-editor.default', function($scope) {
			initAll($scope && $scope[0] ? $scope[0] : document);
		});
		window.elementorFrontend.hooks.addAction('frontend/element_ready/jet-listing-dynamic-field.default', function($scope) {
			initAll($scope && $scope[0] ? $scope[0] : document);
		});
	}

	if (typeof MutationObserver !== 'undefined') {
		new MutationObserver(function(mutations) {
			mutations.forEach(function(mutation) {
				mutation.addedNodes.forEach(function(node) {
					if (node.nodeType === 1) {
						if (node.matches && node.matches('[data-marrison-read-more]')) {
							initRoot(node);
						}
						if (node.querySelectorAll) {
							initAll(node);
						}
					}
				});
			});
		}).observe(document.documentElement, {
			childList: true,
			subtree: true
		});
	}
}());
