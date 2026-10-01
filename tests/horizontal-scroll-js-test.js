/* Run with: node tests/horizontal-scroll-js-test.js */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function createStyle() {
	const values = Object.create(null);
	const priorities = Object.create(null);
	return {
		setProperty(name, value, priority) {
			values[name] = String(value);
			priorities[name] = priority || '';
		},
		getPropertyValue(name) {
			return values[name] || '';
		},
		getPropertyPriority(name) {
			return priorities[name] || '';
		},
		removeProperty(name) {
			delete values[name];
			delete priorities[name];
		}
	};
}

function syncClassName(element, values) {
	element.className = Array.from(values).join(' ');
}

function createClassList(element) {
	const values = new Set();
	return {
		add(name) {
			values.add(name);
			syncClassName(element, values);
		},
		remove(name) {
			values.delete(name);
			syncClassName(element, values);
		},
		contains(name) {
			return values.has(name);
		},
		toggle(name, force) {
			const enabled = force === undefined ? ! values.has(name) : !! force;
			if (enabled) {
				values.add(name);
			} else {
				values.delete(name);
			}
			syncClassName(element, values);
			return enabled;
		}
	};
}

function createElement(tagName, windowRef) {
	const element = {
		tagName: tagName.toUpperCase(),
		attributes: Object.create(null),
		children: [],
		parentNode: null,
		isConnected: true,
		style: createStyle(),
		className: '',
		classList: null,
		clientWidth: 1000,
		_scrollWidth: null,
		setAttribute(name, value) {
			this.attributes[name] = String(value);
		},
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
		},
		hasAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attributes, name);
		},
		removeAttribute(name) {
			delete this.attributes[name];
		},
		insertBefore(child, before) {
			if (child.parentNode) {
				child.parentNode.children = child.parentNode.children.filter((current) => current !== child);
			}
			const index = before ? this.children.indexOf(before) : -1;
			child.parentNode = this;
			child.isConnected = true;
			if (index >= 0) {
				this.children.splice(index, 0, child);
			} else {
				this.children.unshift(child);
			}
		},
		appendChild(child) {
			if (child.parentNode) {
				child.parentNode.children = child.parentNode.children.filter((current) => current !== child);
			}
			child.parentNode = this;
			child.isConnected = true;
			this.children.push(child);
		},
		remove() {
			if (this.parentNode) {
				this.parentNode.children = this.parentNode.children.filter((child) => child !== this);
			}
			this.parentNode = null;
			this.isConnected = false;
		},
		contains(child) {
			if (child === this) {
				return true;
			}
			return this.children.some((current) => current.contains(child));
		},
		querySelectorAll(selector) {
			const result = [];
			function visit(node) {
				for (const child of node.children) {
					if (selector === 'img' && child.tagName === 'IMG') result.push(child);
					visit(child);
				}
			}
			visit(this);
			return result;
		},
		matches(selector) {
			return selector === '[data-marrison-horizontal-scroll]' && this.hasAttribute('data-marrison-horizontal-scroll');
		},
		closest(selector) {
			for (let current = this; current; current = current.parentNode) {
				if (selector === '.marrison-horizontal-scroll-scene' && current.classList.contains('marrison-horizontal-scroll-scene')) {
					return current;
				}
			}
			return null;
		},
		addEventListener() {},
		removeEventListener() {},
		getBoundingClientRect() {
			const documentTop = this.documentTop === undefined && this.classList.contains('marrison-horizontal-scroll-scene')
				? (windowRef.sceneDocumentTop || 0) : (this.documentTop || 0);
			const top = documentTop - windowRef.scrollY;
			return {
				left: 0,
				top,
				width: this.clientWidth,
				height: this.offsetHeight,
				bottom: top + this.offsetHeight
			};
		}
	};
	element.classList = createClassList(element);
	Object.defineProperty(element, 'firstChild', {
		get() {
			return this.children[0] || null;
		}
	});
	Object.defineProperty(element, 'parentElement', {
		get() {
			return this.parentNode;
		}
	});
	Object.defineProperty(element, 'offsetHeight', {
		get() {
			if (this.tagName === 'IMG' && this.classList.contains('marrison-horizontal-scroll-fit-image')) {
				return Math.min(this.explicitHeight, parseFloat(this.style.getPropertyValue('--marrison-horizontal-scroll-image-max-height')));
			}
			if (this.explicitHeight) {
				return this.explicitHeight;
			}
			return this.children.reduce((height, child) => Math.max(height, child.offsetHeight), 400);
		}
	});
	Object.defineProperty(element, 'scrollWidth', {
		get() {
			if (this._scrollWidth !== null) {
				return this._scrollWidth;
			}
			if (this.children.length) {
				return this.children.reduce((width, child) => width + child.clientWidth, 0);
			}
			return this.clientWidth;
		},
		set(value) {
			this._scrollWidth = value;
		}
	});
	Object.defineProperty(element, 'offsetLeft', {
		get() {
			if (! this.parentNode) {
				return 0;
			}
			let offset = this.classList.contains('marrison-horizontal-scroll-mover') ? (windowRef.moverOffsetLeft || 0) : 0;
			for (const child of this.parentNode.children) {
				if (child === this) {
					break;
				}
				offset += child.clientWidth;
			}
			return offset;
		}
	});
	return element;
}

function collectHorizontalRoots(root, output) {
	if (root.hasAttribute && root.hasAttribute('data-marrison-horizontal-scroll')) {
		output.push(root);
	}
	root.children.forEach((child) => collectHorizontalRoots(child, output));
}

function createHarness() {
	const listeners = Object.create(null);
	const scrollCalls = [];
	let now = 0;
	const windowRef = {
		innerWidth: 1200,
		innerHeight: 800,
		scrollY: 0,
		pageYOffset: 0,
		Date: {
			now() {
				return now;
			}
		},
		JSON,
		Math,
		Number,
		Map,
		Set,
		parseFloat,
		isFinite,
		matchMedia() {
			return { matches: false, addEventListener() {}, addListener() {} };
		},
		requestAnimationFrame(callback) {
			callback();
			return 0;
		},
		cancelAnimationFrame() {},
		addEventListener(type, callback) {
			if (! listeners[type]) {
				listeners[type] = [];
			}
			listeners[type].push(callback);
		},
		removeEventListener(type, callback) {
			listeners[type] = (listeners[type] || []).filter((listener) => listener !== callback);
		},
		scrollTo(first, second) {
			const top = typeof first === 'object' ? first.top : second;
			scrollCalls.push(top);
			this.scrollY = top;
			this.pageYOffset = top;
			(listeners.scroll || []).forEach((listener) => listener());
		},
		getComputedStyle(element) {
			return Object.assign({
				overflowY: 'visible',
				paddingLeft: '0px',
				paddingRight: '0px',
				position: 'relative',
				backgroundImage: 'none',
				backgroundColor: 'rgba(0, 0, 0, 0)'
			}, element.computedStyle || {});
		}
	};
	const body = createElement('body', windowRef);
	const document = {
		body,
		readyState: 'complete',
		createElement(tagName) {
			return createElement(tagName, windowRef);
		},
		addEventListener() {},
		querySelectorAll(selector) {
			const roots = [];
			if (selector === '[data-marrison-horizontal-scroll]') {
				collectHorizontalRoots(body, roots);
			}
			return roots;
		}
	};
	windowRef.document = document;

	function MutationObserver() {}
	MutationObserver.prototype.observe = function observe() {};
	MutationObserver.prototype.disconnect = function disconnect() {};
	function ResizeObserver() {}
	ResizeObserver.prototype.observe = function observe() {};
	ResizeObserver.prototype.disconnect = function disconnect() {};

	return {
		body,
		context: {
			window: windowRef,
			document,
			JSON,
			Math,
			Number,
			Map,
			Set,
			parseFloat,
			isFinite,
			Date: windowRef.Date,
			MutationObserver,
			ResizeObserver
		},
		get now() {
			return now;
		},
		set now(value) {
			now = value;
		},
		listeners,
		scrollCalls,
		window: windowRef
	};
}

function addHorizontalRoot(harness, snapThreshold = 220, height = 400) {
	const root = createElement('section', harness.window);
	root.clientWidth = 1000;
	root.scrollWidth = 3000;
	root.explicitHeight = height;
	root.setAttribute('data-marrison-horizontal-scroll', JSON.stringify({
		direction: 'rtl',
		pin: true,
		speed: 1,
		snap: true,
		snapThreshold,
		backgroundScale: { enabled: false, from: 0, to: 1 },
		devices: { desktop: true, tablet: true, mobile: true },
		breakpoints: { mobile: 767, tablet: 1024 }
	}));
	for (let i = 0; i < 3; i++) {
		const child = createElement('div', harness.window);
		child.clientWidth = 1000;
		child.scrollWidth = 1000;
		child.explicitHeight = height;
		child.classList.add('elementor-element');
		root.appendChild(child);
	}
	harness.body.appendChild(root);
	return root;
}

function addFollowingSection(harness, height = 600) {
	const following = createElement('section', harness.window);
	following.clientWidth = 1000;
	following.scrollWidth = 1000;
	following.explicitHeight = height;
	following.classList.add('elementor-element');
	harness.body.appendChild(following);
	return following;
}

function fireWheel(harness, target, deltaY) {
	let prevented = false;
	const event = {
		cancelable: true,
		deltaX: 0,
		deltaY,
		target,
		preventDefault() {
			prevented = true;
		}
	};
	(harness.listeners.wheel || []).forEach((listener) => listener(event));
	return prevented;
}

function moverTransform(root) {
	return root.children[0] && root.children[0].style.transform ? root.children[0].style.transform : '';
}

function check(condition, message) {
	if (! condition) {
		throw new Error(message);
	}
}

const harness = createHarness();
const root = addHorizontalRoot(harness);
const following = addFollowingSection(harness);
const source = fs.readFileSync(path.join(__dirname, '..', 'marrison-addon', 'assets', 'js', 'horizontal-scroll.js'), 'utf8');
vm.runInNewContext(source, harness.context);

check((harness.listeners.wheel || []).length === 1, 'Snap wheel handler was not registered.');
check(root.parentNode.parentNode.classList.contains('marrison-horizontal-scroll-scene'), 'Horizontal scene was not created.');
check(root.parentNode.parentNode.style.height === '2400px', 'Pinned short scene should reserve the classic vertical pin distance.');
check(following.style.getPropertyValue('translate') === '0 -2001px', 'Pinned short scene should pull following content up with a tiny overlap to remove the visible gap.');
check(following.classList.contains('marrison-horizontal-scroll-follow-shift'), 'Shifted following content should receive the optimization class.');

let prevented = fireWheel(harness, root, 120);
check(prevented, 'First below-threshold snap wheel should still keep the section pinned.');
check(harness.scrollCalls.length === 0 && moverTransform(root) === 'translate3d(0px, 0, 0)', 'First below-threshold snap wheel should not move to the next slide.');

prevented = fireWheel(harness, root, 120);
check(prevented, 'Second snap wheel should prevent normal vertical scrolling.');
check(harness.scrollCalls.length === 1, 'Second snap wheel should call scrollTo once.');
check(harness.scrollCalls[0] === 1000, 'Second snap wheel should target the second slide pin position.');
check(moverTransform(root) === 'translate3d(-1000px, 0, 0)', 'Second snap wheel should paint the second slide.');
check(following.style.getPropertyValue('translate') === '0 -1001px', 'Following content shift should shrink as horizontal progress advances while keeping the seam covered.');

harness.now = 100;
prevented = fireWheel(harness, root, 120);
check(prevented && harness.scrollCalls.length === 1 && moverTransform(root) === 'translate3d(-1000px, 0, 0)', 'Wheel events during snap animation should be swallowed without skipping slides.');

harness.now = 500;
prevented = fireWheel(harness, root, 120);
check(prevented && harness.scrollCalls.length === 1 && moverTransform(root) === 'translate3d(-1000px, 0, 0)', 'Below-threshold snap wheel should not skip from the second to the third slide.');

harness.now = 520;
prevented = fireWheel(harness, root, 120);
check(prevented, 'Threshold-reaching snap wheel should prevent normal vertical scrolling.');
check(harness.scrollCalls.length === 2 && harness.scrollCalls[1] === 2000 && moverTransform(root) === 'translate3d(-2000px, 0, 0)', 'Threshold-reaching snap wheel should scroll to the third slide pin position.');
check(following.style.getPropertyValue('translate') === '', 'Following content shift should be restored at the end of the pin range.');
check(! following.classList.contains('marrison-horizontal-scroll-follow-shift'), 'Following content shift class should be removed at the end of the pin range.');

harness.now = 1000;
prevented = fireWheel(harness, root, 120);
check(! prevented && harness.scrollCalls.length === 2, 'Scrolling down from the last slide should let the page continue.');

prevented = fireWheel(harness, root, -120);
check(prevented && harness.scrollCalls.length === 2 && moverTransform(root) === 'translate3d(-2000px, 0, 0)', 'First below-threshold upward wheel should not leave the current slide.');

prevented = fireWheel(harness, root, -120);
check(prevented, 'Scrolling up from the last slide should snap back after reaching the threshold.');
check(harness.scrollCalls.length === 3 && harness.scrollCalls[2] === 1000 && moverTransform(root) === 'translate3d(-1000px, 0, 0)', 'Scrolling up should move to the previous slide pin position.');

const resetHarness = createHarness();
resetHarness.now = 1000;
const resetRoot = addHorizontalRoot(resetHarness);
vm.runInNewContext(source, resetHarness.context);
prevented = fireWheel(resetHarness, resetRoot, 120);
check(prevented && resetHarness.scrollCalls.length === 0, 'First partial wheel should not snap before the threshold.');
resetHarness.now = 1400;
prevented = fireWheel(resetHarness, resetRoot, 120);
check(prevented && resetHarness.scrollCalls.length === 0, 'Partial wheel after a pause should not reuse the previous gesture delta.');
prevented = fireWheel(resetHarness, resetRoot, 120);
check(prevented && resetHarness.scrollCalls.length === 1 && resetHarness.scrollCalls[0] === 1000 && moverTransform(resetRoot) === 'translate3d(-1000px, 0, 0)', 'Two close partial wheels after the pause should snap.');

const lowThresholdHarness = createHarness();
const lowThresholdRoot = addHorizontalRoot(lowThresholdHarness, 80);
vm.runInNewContext(source, lowThresholdHarness.context);
prevented = fireWheel(lowThresholdHarness, lowThresholdRoot, 120);
check(prevented, 'Configurable low threshold should still prevent normal vertical scrolling.');
check(lowThresholdHarness.scrollCalls.length === 1 && lowThresholdHarness.scrollCalls[0] === 1000 && moverTransform(lowThresholdRoot) === 'translate3d(-1000px, 0, 0)', 'Configurable low threshold should allow a single wheel tick to snap.');

const beforePinHarness = createHarness();
const beforePinRoot = addHorizontalRoot(beforePinHarness);
const beforePinFollowing = addFollowingSection(beforePinHarness);
vm.runInNewContext(source, beforePinHarness.context);
beforePinRoot.parentNode.parentNode.documentTop = 300;
(beforePinHarness.listeners.scroll || []).forEach((listener) => listener());
check(beforePinFollowing.style.getPropertyValue('translate') === '0 -2001px', 'Following content shift should already remove the gap before the pinned scene reaches the viewport top.');

const restoreHarness = createHarness();
const restoreRoot = addHorizontalRoot(restoreHarness);
const restoreFollowing = addFollowingSection(restoreHarness);
restoreFollowing.style.setProperty('translate', '5px 6px');
vm.runInNewContext(source, restoreHarness.context);
check(restoreFollowing.style.getPropertyValue('translate') === '0 -2001px', 'Following shift should temporarily override an existing inline translate.');
restoreHarness.window.scrollTo(0, 2000);
check(restoreFollowing.style.getPropertyValue('translate') === '5px 6px', 'Following shift should restore an existing inline translate at the end of the pin range.');
check(moverTransform(restoreRoot) === 'translate3d(-2000px, 0, 0)', 'Direct scroll to the end of the pin range should still paint the last slide.');

const fullViewportHarness = createHarness();
const fullViewportRoot = addHorizontalRoot(fullViewportHarness, 220, 800);
const fullViewportFollowing = addFollowingSection(fullViewportHarness);
vm.runInNewContext(source, fullViewportHarness.context);
check(fullViewportRoot.parentNode.parentNode.style.height === '2800px', 'Pinned full-height scene should reserve viewport height plus horizontal scroll distance.');
check((fullViewportRoot.parentNode.parentNode.style.marginBottom || '') === '', 'Pinned full-height scene should keep normal pin spacing.');
check(fullViewportFollowing.style.getPropertyValue('translate') === '', 'Full-height pinned scene should not shift following content.');

const paddedHarness = createHarness();
const paddedRoot = addHorizontalRoot(paddedHarness);
paddedHarness.window.moverOffsetLeft = 10;
vm.runInNewContext(source, paddedHarness.context);
prevented = fireWheel(paddedHarness, paddedRoot, 240);
check(prevented && paddedHarness.scrollCalls.length === 1 && paddedHarness.scrollCalls[0] === 1000,
	'Host padding must not add an extra snap stop by mixing host and mover offsets.');
check(moverTransform(paddedRoot) === 'translate3d(-1000px, 0, 0)',
	'Snap must align the next child in the mover coordinate system.');

const tallSnapHarness = createHarness();
const tallSnapRoot = addHorizontalRoot(tallSnapHarness, 220, 1000);
tallSnapHarness.window.sceneDocumentTop = 1000;
tallSnapHarness.window.scrollY = 1000;
vm.runInNewContext(source, tallSnapHarness.context);
check(moverTransform(tallSnapRoot) === 'translate3d(0px, 0, 0)',
	'Snap fallback must retain the first slide when a tall parent reaches the viewport top.');
tallSnapHarness.window.scrollTo(0, 999);
check(moverTransform(tallSnapRoot) === 'translate3d(0px, 0, 0)',
	'Snap fallback must not advance before the parent reaches the viewport top.');
tallSnapHarness.window.scrollTo(0, 2350);
check(moverTransform(tallSnapRoot) === 'translate3d(-2000px, 0, 0)',
	'Snap fallback must still reach the final slide after its delayed start.');

const unpinnedSnapHarness = createHarness();
const unpinnedSnapRoot = addHorizontalRoot(unpinnedSnapHarness);
const unpinnedConfig = JSON.parse(unpinnedSnapRoot.getAttribute('data-marrison-horizontal-scroll'));
unpinnedConfig.pin = false;
unpinnedSnapRoot.setAttribute('data-marrison-horizontal-scroll', JSON.stringify(unpinnedConfig));
unpinnedSnapHarness.window.sceneDocumentTop = 1000;
unpinnedSnapHarness.window.scrollY = 600;
vm.runInNewContext(source, unpinnedSnapHarness.context);
check(moverTransform(unpinnedSnapRoot) === 'translate3d(0px, 0, 0)',
	'Explicitly unpinned Snap must retain the first slide when the parent first becomes fully visible.');

const imageFitHarness = createHarness();
const imageFitRoot = addHorizontalRoot(imageFitHarness);
imageFitRoot.explicitHeight = null;
imageFitRoot.children[0].explicitHeight = null;
const oversizedImage = createElement('img', imageFitHarness.window);
oversizedImage.explicitHeight = 1000;
oversizedImage.style.setProperty('--marrison-horizontal-scroll-image-max-height', '1234px', 'important');
imageFitRoot.children[0].appendChild(oversizedImage);
vm.runInNewContext(source, imageFitHarness.context);
check(imageFitRoot.parentNode.parentNode.classList.contains('marrison-horizontal-scroll-pinned'),
	'Fitting a tall image must keep a Snap section eligible for pinning.');
check(imageFitRoot.offsetHeight === 800 && oversizedImage.classList.contains('marrison-horizontal-scroll-fit-image'),
	'Image fit must constrain the rendered section to the viewport height.');
const imageFitConfig = JSON.parse(imageFitRoot.getAttribute('data-marrison-horizontal-scroll'));
imageFitConfig.snap = false;
imageFitRoot.setAttribute('data-marrison-horizontal-scroll', JSON.stringify(imageFitConfig));
(imageFitHarness.listeners.resize || []).forEach(listener => listener());
check(! oversizedImage.classList.contains('marrison-horizontal-scroll-fit-image') &&
	oversizedImage.style.getPropertyValue('--marrison-horizontal-scroll-image-max-height') === '1234px' &&
	oversizedImage.style.getPropertyPriority('--marrison-horizontal-scroll-image-max-height') === 'important',
	'Disabling Snap must restore image classes and the prior inline property, including priority.');

const impossibleFitHarness = createHarness();
const impossibleFitRoot = addHorizontalRoot(impossibleFitHarness, 220, 1000);
const impossibleImage = createElement('img', impossibleFitHarness.window);
impossibleImage.explicitHeight = 600;
impossibleFitRoot.children[0].computedStyle = { display: 'block', paddingTop: '500px', paddingBottom: '500px' };
impossibleFitRoot.children[0].appendChild(impossibleImage);
vm.runInNewContext(source, impossibleFitHarness.context);
check(! impossibleImage.classList.contains('marrison-horizontal-scroll-fit-image') && impossibleImage.offsetHeight === 600,
	'An impossible height budget must retain the image instead of collapsing it to one pixel.');

const css = fs.readFileSync(path.join(__dirname, '..', 'marrison-addon', 'assets', 'css', 'horizontal-scroll.css'), 'utf8');
check(css.includes('z-index: 2'), 'Pinned horizontal scroll viewport should stay above following content while overlapped.');
check(! css.includes('marrison-horizontal-scroll-fixed'), 'Rollback should not keep the compact fixed pin mode.');
check(css.includes('will-change: translate'), 'Following content shift should use translate optimization.');
check(css.includes('transition-property: none !important'), 'Following content shift should not animate while tracking scroll.');

console.log('Horizontal scroll JS snap wheel contract: PASS');
