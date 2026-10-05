/* Run with: node tests/liquid-background-js-test.js */
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

function createClassList(element) {
	const values = new Set();
	return {
		add(name) {
			values.add(name);
			element.className = Array.from(values).join(' ');
		},
		remove(name) {
			values.delete(name);
			element.className = Array.from(values).join(' ');
		},
		contains(name) {
			return values.has(name);
		}
	};
}

function createElement(tagName) {
	const element = {
		tagName: tagName.toUpperCase(),
		attributes: Object.create(null),
		children: [],
		parentNode: null,
		isConnected: true,
		style: createStyle(),
		className: '',
		classList: null,
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
			const index = before ? this.children.indexOf(before) : -1;
			child.parentNode = this;
			child.isConnected = true;
			if (index >= 0) {
				this.children.splice(index, 0, child);
			} else {
				this.children.unshift(child);
			}
		},
		remove() {
			if (this.parentNode) {
				this.parentNode.children = this.parentNode.children.filter((child) => child !== this);
			}
			this.parentNode = null;
			this.isConnected = false;
		},
		addEventListener() {},
		removeEventListener() {},
		getBoundingClientRect() {
			return { left: 0, top: 0, width: 800, height: 360 };
		}
	};
	element.classList = createClassList(element);
	Object.defineProperty(element, 'firstChild', {
		get() {
			return this.children[0] || null;
		}
	});
	if (tagName === 'canvas') {
		element.getContext = () => null;
	}
	return element;
}

function collectLiquidRoots(root, output) {
	if (root.hasAttribute && root.hasAttribute('data-marrison-liquid-background')) {
		output.push(root);
	}
	root.children.forEach((child) => collectLiquidRoots(child, output));
}

function createHarness(width) {
	const body = createElement('body');
	const document = {
		body,
		readyState: 'complete',
		visibilityState: 'visible',
		documentElement: { clientWidth: width },
		createElement,
		addEventListener() {},
		querySelectorAll(selector) {
			const roots = [];
			if (selector === '[data-marrison-liquid-background]') {
				collectLiquidRoots(body, roots);
			}
			return roots;
		}
	};
	const window = {
		document,
		innerWidth: width,
		devicePixelRatio: 1,
		Map,
		Float32Array,
		Uint8Array,
		JSON,
		Math,
		parseFloat,
		parseInt,
		isFinite,
		decodeURIComponent,
		setTimeout,
		clearTimeout,
		getComputedStyle() {
			return { position: 'static' };
		},
		matchMedia() {
			return { matches: false, addEventListener() {}, addListener() {} };
		},
		requestAnimationFrame(callback) {
			callback(16);
			return 1;
		},
		cancelAnimationFrame() {},
		addEventListener() {},
		removeEventListener() {}
	};
	function MutationObserver() {}
	MutationObserver.prototype.observe = function observe() {};
	function IntersectionObserver(callback) {
		this.callback = callback;
	}
	IntersectionObserver.prototype.observe = function observe(target) {
		this.callback([{ target, isIntersecting: true }]);
	};
	IntersectionObserver.prototype.disconnect = function disconnect() {};
	function ResizeObserver(callback) {
		this.callback = callback;
	}
	ResizeObserver.prototype.observe = function observe() {
		this.callback();
	};
	ResizeObserver.prototype.disconnect = function disconnect() {};

	return {
		body,
		window,
		context: {
			window,
			document,
			Map,
			Float32Array,
			Uint8Array,
			JSON,
			Math,
			parseFloat,
			parseInt,
			isFinite,
			decodeURIComponent,
			setTimeout,
			clearTimeout,
			MutationObserver,
			IntersectionObserver,
			ResizeObserver
		}
	};
}

function readLiquidSource() {
	return fs.readFileSync(path.join(__dirname, '..', 'marrison-addon', 'assets', 'js', 'marrison-liquid-background.js'), 'utf8');
}

function addRoot(body, id, disableMobile, blendGradient, blendColor) {
	const root = createElement('section');
	const color = blendColor || '#050505';
	root.setAttribute('id', id);
	root.setAttribute('data-marrison-liquid-background', JSON.stringify({
		preset: 'deep-purple',
		background: '#000000',
		primary: '#6C3BFF',
		secondary: '#321875',
		speed: { desktop: 0.35, tablet: 0.3, mobile: 0.25 },
		scale: { desktop: 1, tablet: 1, mobile: 1 },
		complexity: 3,
		distortion: 0.5,
		softness: 0.45,
		contrast: 1,
		opacity: { desktop: 1, tablet: 1, mobile: 1 },
		blendGradient: {
			enabled: blendGradient,
			color,
			height: { desktop: 45, tablet: 55, mobile: 65 }
		},
		mouse: false,
		mouseInfluence: 0.2,
		direction: 'natural',
		disableMobile,
		reducedMotion: 'static',
		seed: 1234,
		breakpoints: { mobile: 767, tablet: 1024 }
	}));
	body.children.push(root);
	root.parentNode = body;
	return root;
}

function run(width) {
	const harness = createHarness(width);
	const roots = [
		addRoot(harness.body, 'a', false, false),
		addRoot(harness.body, 'b', false, true, { desktop: '#050505', tablet: 'globals/colors?id=accent', mobile: '#070707' }),
		addRoot(harness.body, 'mobile-off', true, true)
	];
	const source = fs.readFileSync(path.join(__dirname, '..', 'marrison-addon', 'assets', 'js', 'marrison-liquid-background.js'), 'utf8');
	vm.runInNewContext(source, harness.context);
	return roots.map((root) => ({
		host: root.classList.contains('marrison-liquid-background-host'),
		layers: root.children.filter((child) => child.className === 'marrison-liquid-background-fallback' || child.className === 'marrison-liquid-background-canvas').length,
		nativeBackground: root.children.filter((child) => child.className === 'marrison-liquid-background-native').length,
		blend: root.children.filter((child) => child.className === 'marrison-liquid-background-blend').length,
		blendBackground: (root.children.find((child) => child.className === 'marrison-liquid-background-blend') || { style: createStyle() }).style.getPropertyValue('background'),
		secondaryCss: root.style.getPropertyValue('--marrison-liquid-secondary'),
		firstLayer: root.children[0] ? root.children[0].className : ''
	}));
}

function check(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

const desktop = run(1365);
check(desktop[0].host && desktop[0].layers === 1, 'Desktop first instance did not create one fallback layer.');
check(desktop.every((result) => result.nativeBackground === 0), 'Liquid must not duplicate the native background above the animation.');
check(desktop[0].secondaryCss === '#321875', 'Secondary liquid color was not applied as a runtime CSS value.');
check(desktop[0].blend === 0, 'Desktop first instance created a blend layer while disabled.');
check(desktop[1].host && desktop[1].layers === 1 && desktop[1].blend === 1, 'Desktop second instance did not create the requested blend layer.');
check(desktop[1].blendBackground.includes('25%') && desktop[1].blendBackground.includes('50%') && desktop[1].blendBackground.includes('75%') && desktop[1].blendBackground.includes('100%'), 'Blend gradient should progress across the full selected height.');
check(!desktop[1].blendBackground.includes('68%'), 'Blend gradient should not jump to a solid band near the bottom.');
check(desktop[1].blendBackground.includes('#050505'), 'Desktop blend color was not applied.');
check(desktop[2].host && desktop[2].layers === 1 && desktop[2].blend === 1, 'Desktop mobile-enabled instance should still render with blend.');

const tablet = run(800);
check(tablet[1].blendBackground.includes('var(--e-global-color-accent)'), 'Tablet blend color should accept Elementor global CSS colors.');
check(!tablet[1].blendBackground.includes('68%') && tablet[1].blendBackground.includes('100%'), 'Tablet blend gradient should fade across the full selected height.');

const mobile = run(390);
check(mobile[0].host && mobile[0].layers === 1, 'Mobile first instance did not create one fallback layer.');
check(mobile[1].host && mobile[1].layers === 1 && mobile[1].blend === 1, 'Mobile second instance did not create the requested blend layer.');
check(mobile[1].blendBackground.includes('#070707'), 'Mobile blend color was not applied.');
check(!mobile[2].host && mobile[2].layers === 0 && mobile[2].blend === 0, 'Disable on Mobile created a layer before it should.');
check(mobile[2].nativeBackground === 0, 'Disable on Mobile must not create a native background mirror.');

const css = fs.readFileSync(path.join(__dirname, '..', 'marrison-addon', 'assets', 'css', 'marrison-liquid-background.css'), 'utf8');
const hostChildRule = css.match(/\.marrison-liquid-background-host > :where\(:not\([^{]+\{[^}]+\}/);
check(hostChildRule && hostChildRule[0].includes(':not(.elementor-shape)'), 'Liquid content stacking rule must exclude Elementor shape dividers.');
check(hostChildRule && !hostChildRule[0].includes('position:'), 'Liquid content stacking rule must not change Elementor child positioning.');

const source = readLiquidSource();
check(source.includes('secondary_wave') && source.includes('secondary_floor'), 'WebGL shader must reserve visible structure for the secondary liquid color.');
check(source.includes('settings.__globals__') && source.includes('globals/colors'), 'Editor preview should resolve Elementor global color references.');

console.log('Liquid background JS fallback/mobile contract: PASS');
