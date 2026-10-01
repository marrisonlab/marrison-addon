/* Run with: node tests/read-more-js-test.js */
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function createClassList(element) {
	const values = new Set();
	return {
		add(...names) {
			names.forEach((name) => values.add(name));
		},
		remove(...names) {
			names.forEach((name) => values.delete(name));
		},
		contains(name) {
			return values.has(name);
		},
		toggle(name, force) {
			const active = force === undefined ? ! values.has(name) : !! force;
			if (active) {
				values.add(name);
			} else {
				values.delete(name);
			}
			return active;
		}
	};
}

function createStyle() {
	const values = Object.create(null);
	return {
		display: '',
		setProperty(name, value) {
			values[name] = String(value);
		},
		getPropertyValue(name) {
			return values[name] || '';
		}
	};
}

function createTextNode(value) {
	let text = String(value);
	return {
		nodeType: 3,
		parentElement: null,
		get nodeValue() {
			return text;
		},
		set nodeValue(value) {
			text = String(value);
		},
		get textContent() {
			return text;
		},
		set textContent(value) {
			text = String(value);
		}
	};
}

function createElement(tagName) {
	const element = {
		tagName: tagName.toUpperCase(),
		nodeType: 1,
		attributes: Object.create(null),
		childNodes: [],
		parentElement: null,
		classList: null,
		style: createStyle(),
		_top: 0,
		_rectHeight: null,
		_scrollHeight: null,
		listeners: Object.create(null),
		setAttribute(name, value) {
			this.attributes[name] = String(value);
		},
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
		},
		appendChild(child) {
			child.parentElement = this;
			this.childNodes.push(child);
		},
		removeChild(child) {
			this.childNodes = this.childNodes.filter((current) => current !== child);
			child.parentElement = null;
			return child;
		},
		addEventListener(type, callback) {
			this.listeners[type] = callback;
		},
		querySelector(selector) {
			return findFirst(this, selector);
		},
		querySelectorAll(selector) {
			const output = [];
			collect(this, selector, output);
			return output;
		},
		matches(selector) {
			if (selector === '[data-marrison-read-more]') {
				return Object.prototype.hasOwnProperty.call(this.attributes, 'data-marrison-read-more');
			}
			if (selector.charAt(0) === '.') {
				return this.classList.contains(selector.slice(1));
			}
			return false;
		},
		closest(selector) {
			for (let current = this; current; current = current.parentElement) {
				if (current.matches && current.matches(selector)) {
					return current;
				}
			}
			return null;
		},
		getBoundingClientRect() {
			let height = this._rectHeight;
			if (height === null) {
				height = this.style.height && this.style.height !== 'auto' ? parseFloat(this.style.height) : this.scrollHeight;
			}

			if (! Number.isFinite(height) || height < 0) {
				height = 0;
			}

			return {
				top: this._top,
				bottom: this._top + height,
				height
			};
		}
	};
	element.classList = createClassList(element);
	Object.defineProperty(element, 'children', {
		get() {
			return this.childNodes.filter((child) => child.nodeType === 1);
		}
	});
	Object.defineProperty(element, 'textContent', {
		get() {
			return this.childNodes.map((child) => child.textContent || '').join('');
		},
		set(value) {
			this.childNodes = [createTextNode(value)];
			this.childNodes[0].parentElement = this;
		}
	});
	Object.defineProperty(element, 'innerHTML', {
		get() {
			return this.textContent;
		},
		set(value) {
			this.textContent = value;
		}
	});
	Object.defineProperty(element, 'scrollHeight', {
		get() {
			if (this._scrollHeight !== null) {
				return this._scrollHeight;
			}
			if (this.classList.contains('marrison-read-more-content')) {
				return Math.max(20, Math.ceil(this.textContent.length / 20) * 20);
			}
			if (this.children.length) {
				return this.children.reduce((height, child) => height + child.scrollHeight, 0);
			}
			return 20;
		},
		set(value) {
			this._scrollHeight = value;
		}
	});
	return element;
}

function matches(element, selector) {
	return element.matches(selector);
}

function findFirst(root, selector) {
	for (const child of root.children) {
		if (matches(child, selector)) {
			return child;
		}
		const nested = findFirst(child, selector);
		if (nested) {
			return nested;
		}
	}
	return null;
}

function collect(root, selector, output) {
	root.children.forEach((child) => {
		if (matches(child, selector)) {
			output.push(child);
		}
		collect(child, selector, output);
	});
}

function check(condition, message) {
	if (! condition) {
		throw new Error(message);
	}
}

const listeners = Object.create(null);

function createReadMoreRoot(text) {
	const root = createElement('div');
	root.setAttribute('data-marrison-read-more', JSON.stringify({
		lines: 2,
		labelMore: 'Apri',
		labelLess: 'Chiudi'
	}));
	const content = createElement('div');
	content.classList.add('marrison-read-more-content');
	content.innerHTML = text;
	const buttonWrap = createElement('div');
	buttonWrap.classList.add('marrison-read-more-toggle-wrap');
	buttonWrap.style.display = 'none';
	const button = createElement('button');
	button.classList.add('marrison-read-more-toggle');
	buttonWrap.appendChild(button);
	root.appendChild(content);
	root.appendChild(buttonWrap);

	return { root, content, buttonWrap, button };
}

const longText = 'L’istituto di vigilanza dell’urbe fornisce una gamma di servizi per la messa in sicurezza di tutti i supporti informatici presenti in un ufficio, nonché dei suoi locali.';
const longReadMore = createReadMoreRoot(longText);
const root = longReadMore.root;
const content = longReadMore.content;
const buttonWrap = longReadMore.buttonWrap;
const button = longReadMore.button;
const listingGrid = createElement('div');
listingGrid.classList.add('jet-listing-grid');
const listingItems = createElement('div');
listingItems.classList.add('jet-listing-grid__items');
const listingItem = createElement('div');
listingItem.classList.add('jet-listing-grid__item');
listingItem.style.height = '40px';
listingItem._top = 100;
listingItem._rectHeight = null;
root._top = 100;
const widgetContainer = createElement('div');
widgetContainer.classList.add('elementor-widget-container');
widgetContainer._top = 100;
widgetContainer.appendChild(root);
listingItem.appendChild(widgetContainer);
listingItems.appendChild(listingItem);
listingGrid.appendChild(listingItems);
const shortReadMore = createReadMoreRoot('Testo breve.');
shortReadMore.root.setAttribute('data-marrison-read-more', JSON.stringify({
	lines: 2,
	labelMore: 'Apri',
	labelLess: 'Chiudi'
}));

const documentElement = createElement('html');
const body = createElement('body');
body.appendChild(listingGrid);
body.appendChild(shortReadMore.root);
documentElement.appendChild(body);

const document = {
	documentElement,
	readyState: 'complete',
	querySelectorAll(selector) {
		const output = [];
		if (matches(root, selector)) {
			output.push(root);
		}
		collect(root, selector, output);
		return output;
	},
	addEventListener() {}
};

let resizeDispatches = 0;
let jqueryResizeTriggers = 0;
let isotopeCalls = 0;
let masonryCalls = 0;
let nativeIsotopeCalls = 0;
let nativeMasonryCalls = 0;

const windowRef = {
	document,
	JSON,
	WeakMap,
	parseInt,
	parseFloat,
	isFinite,
	getComputedStyle(element) {
		if (element === content) {
			return {
				lineHeight: '20px',
				fontSize: '16px',
				backgroundColor: 'rgba(0, 0, 0, 0)'
			};
		}
		return {
			lineHeight: '20px',
			fontSize: '16px',
			backgroundColor: '#ffffff'
		};
	},
	requestAnimationFrame(callback) {
		callback();
	},
	addEventListener(type, callback) {
		listeners[type] = callback;
	},
	dispatchEvent(event) {
		if (event && event.type === 'resize') {
			resizeDispatches++;
		}
		if (event && listeners[event.type]) {
			listeners[event.type](event);
		}
	},
	clearTimeout() {},
	setTimeout(callback) {
		callback();
		return 1;
	},
	jQuery(target) {
		return {
			isotope(command) {
				if (target === listingItems && command === 'layout') {
					isotopeCalls++;
				}
			},
			masonry(command) {
				if (target === listingItems && command === 'layout') {
					masonryCalls++;
				}
			},
			trigger(eventName) {
				if (target === windowRef && eventName === 'resize') {
					jqueryResizeTriggers++;
				}
			}
		};
	},
	Masonry: {
		data(target) {
			if (target !== listingItems) {
				return null;
			}

			return {
				layout() {
					nativeMasonryCalls++;
				}
			};
		}
	},
	Isotope: {
		data(target) {
			if (target !== listingItems) {
				return null;
			}

			return {
				layout() {
					nativeIsotopeCalls++;
				}
			};
		}
	}
};

function MutationObserver() {}
MutationObserver.prototype.observe = function observe() {};
function ResizeObserver(callback) {
	this.callback = callback;
}
ResizeObserver.prototype.observe = function observe() {};

const source = fs.readFileSync(path.join(__dirname, '..', 'marrison-addon', 'assets', 'js', 'marrison-read-more.js'), 'utf8');
vm.runInNewContext(source, {
	window: windowRef,
	document,
	JSON,
	WeakMap,
	parseInt,
	parseFloat,
	isFinite,
	MutationObserver,
	ResizeObserver,
	Event: function Event(type) {
		this.type = type;
	}
});

check(root.classList.contains('marrison-read-more--active'), 'Overflowing text did not activate Read More.');
check(root.classList.contains('is-collapsed'), 'Read More should start collapsed.');
check(button.textContent === 'Apri', 'Closed label was not applied.');
check(button.getAttribute('aria-expanded') === 'false', 'Initial aria-expanded should be false.');
check(root.style.getPropertyValue('--marrison-read-more-collapsed-height') === '40px', 'Collapsed height did not use selected lines.');
check(content.style.maxHeight === '40px' && content.style.overflow === 'hidden', 'Collapsed inline styles were not applied.');
check(buttonWrap.style.display === 'block', 'Toggle was not shown for overflowing text.');
check(content.textContent.endsWith('....'), 'Collapsed text does not end with an inline suffix.');
check(content.textContent.length < longText.length, 'Collapsed text was not actually shortened.');
check(! shortReadMore.root.classList.contains('marrison-read-more--active'), 'Short text should not activate Read More.');
check(shortReadMore.buttonWrap.style.display === 'none', 'Short text should keep the toggle hidden.');

button.listeners.click();
check(root.classList.contains('is-expanded'), 'Click did not expand content.');
check(button.textContent === 'Chiudi', 'Open label was not applied.');
check(button.getAttribute('aria-expanded') === 'true', 'Expanded aria state missing.');
check(content.style.maxHeight === 'none' && content.style.overflow === 'visible', 'Expanded inline styles were not applied.');
check(content.textContent === longText, 'Expanded content did not restore the original text.');
check(parseInt(listingItem.style.minHeight, 10) >= root.scrollHeight, 'Listing item min-height was not updated after expanding.');
check(parseInt(listingItem.style.height, 10) >= root.scrollHeight, 'Listing item height was not locked after expanding.');
check(parseInt(widgetContainer.style.minHeight, 10) >= root.scrollHeight, 'Intermediate listing wrapper min-height was not updated after expanding.');
check(resizeDispatches === 0, 'Native resize should not be dispatched during Read More toggle.');
check(jqueryResizeTriggers === 0, 'jQuery resize should not be triggered during Read More toggle.');
check(isotopeCalls > 0 && masonryCalls > 0, 'Listing layout plugins were not asked to recalculate.');
check(nativeIsotopeCalls > 0 && nativeMasonryCalls > 0, 'Native masonry layout instances were not asked to recalculate.');

console.log('Read More JS contract: PASS');
