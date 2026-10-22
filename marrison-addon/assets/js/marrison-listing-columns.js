(function () {
	'use strict';
	const selector = '[data-marrison-listing-columns]';
	const instances = new Map();
	const managed = new Map();
	let scheduled = false;
	let hooksRegistered = false;
	const editing = () => !!(window.elementorFrontend && window.elementorFrontend.isEditMode());
	const storageKey = config => 'marrison-listing-columns:' + window.location.pathname + ':' + config.selector;

	function preference(instance) {
		if (!instance.config.remember || editing()) return null;
		try { return window.localStorage.getItem(instance.key); } catch (error) { return null; }
	}

	function paint(instance) {
		instance.buttons.forEach(button => {
			const enabled = instance.allowed.includes(button.dataset.cols);
			button.hidden = !enabled;
			// This is functional visibility: theme/Elementor button display rules must not reveal other breakpoint options.
			if (enabled) button.style.removeProperty('display');
			else button.style.setProperty('display', 'none', 'important');
			button.setAttribute('aria-pressed', String(button.dataset.cols === instance.value));
		});
	}

	function updateProfile(instance) {
		const profiles = instance.config.profiles || [{ name: 'desktop', minColumns: 1, maxColumns: 12, defaultColumns: instance.config.defaultColumns }];
		let profile = profiles[0];
		profiles.forEach(candidate => {
			if ((candidate.direction === 'max' && window.innerWidth <= candidate.width) || (candidate.direction === 'min' && window.innerWidth >= candidate.width)) profile = candidate;
		});
		const key = storageKey(instance.config) + (profile.name === 'desktop' ? '' : ':' + profile.name);
		if (instance.key === key) return;
		instance.key = key;
		instance.allowed = instance.buttons.map(button => button.dataset.cols).filter(value => /^(?:[1-9]|1[0-2])$/.test(value) && Number(value) >= profile.minColumns && Number(value) <= profile.maxColumns);
		instance.value = String(profile.defaultColumns);
		const stored = instance.choices.get(key) || preference(instance);
		if (instance.allowed.includes(stored)) instance.value = stored;
		if (!instance.allowed.includes(instance.value)) instance.value = instance.allowed[0];
		// Toolbars targeting the same listing and breakpoint share the actual choice.
		instances.forEach(other => { if (other !== instance && other.key === key) instance.value = other.value; });
		paint(instance);
	}

	function setValue(instance, value) {
		if (!instance.allowed.includes(value)) return;
		instances.forEach(other => {
			if (other.key === instance.key) {
				other.value = value;
				other.choices.set(other.key, value);
				paint(other);
			}
		});
		if (instance.config.remember && !editing()) {
			try { window.localStorage.setItem(instance.key, value); } catch (error) { /* Private or blocked storage: keep the in-page choice. */ }
		}
		refresh();
	}

	function create(root) {
		let config;
		try { config = JSON.parse(root.dataset.marrisonListingColumns); } catch (error) { return; }
		if (!config || typeof config.selector !== 'string') return;
		const buttons = Array.from(root.querySelectorAll('.marrison-listing-columns__button'));
		if (!buttons.length) return;
		const instance = { root, config, buttons, allowed: [], choices: new Map(), signature: root.dataset.marrisonListingColumns };
		updateProfile(instance);
		instance.click = event => {
			const button = event.target.closest('.marrison-listing-columns__button');
			if (button && root.contains(button) && !button.disabled) setValue(instance, button.dataset.cols);
		};
		root.addEventListener('click', instance.click);
		instances.set(root, instance);
		paint(instance);
	}

	function gridsFor(instance) {
		let targets;
		try { targets = document.querySelectorAll(instance.config.selector); } catch (error) { return []; }
		const grids = new Set();
		targets.forEach(target => {
			const candidates = target.matches('.jet-listing-grid__items') ? [target] : target.querySelectorAll('.jet-listing-grid__items');
			candidates.forEach(grid => {
				// Keep nested listing cards independent from their outer listing.
				const parentListing = grid.parentElement && grid.parentElement.closest('.jet-listing-grid__items');
				if (parentListing && target.contains(parentListing)) return;
				if (grid.closest('.jet-listing-grid__slider, .jet-listing-grid__scroll-slider, .slick-slider, .jet-listing-grid__masonry') || grid.hasAttribute('data-masonry-grid-options')) return;
				grids.add(grid);
			});
		});
		return Array.from(grids);
	}

	function restore(grid, original) {
		grid.removeAttribute('data-marrison-listing-columns-active');
		if (original.value) grid.style.setProperty('--marrison-listing-columns', original.value, original.priority);
		else grid.style.removeProperty('--marrison-listing-columns');
	}

	function refresh() {
		const desired = new Map();
		instances.forEach((instance, root) => {
			if (!root.isConnected) {
				root.removeEventListener('click', instance.click);
				instances.delete(root);
				return;
			}
			updateProfile(instance);
			const active = window.innerWidth >= (Number(instance.config.minViewport) || 0);
			root.hidden = !active;
			const grids = gridsFor(instance);
			instance.buttons.forEach(button => { button.disabled = !grids.length; });
			if (active) grids.forEach(grid => desired.set(grid, instance.value));
		});
		managed.forEach((original, grid) => {
			if (!desired.has(grid)) { restore(grid, original); managed.delete(grid); }
		});
		desired.forEach((value, grid) => {
			if (!managed.has(grid)) managed.set(grid, { value: grid.style.getPropertyValue('--marrison-listing-columns'), priority: grid.style.getPropertyPriority('--marrison-listing-columns') });
			grid.dataset.marrisonListingColumnsActive = 'true';
			grid.style.setProperty('--marrison-listing-columns', value);
		});
	}

	function scan() {
		document.querySelectorAll(selector).forEach(root => {
			const current = instances.get(root);
			if (current && (current.signature !== root.dataset.marrisonListingColumns || current.buttons[0] !== root.querySelector('.marrison-listing-columns__button'))) {
				root.removeEventListener('click', current.click);
				instances.delete(root);
			}
			if (!instances.has(root)) create(root);
		});
		refresh();
	}

	function schedule() {
		if (scheduled) return;
		scheduled = true;
		window.requestAnimationFrame(() => { scheduled = false; scan(); });
	}

	function registerHooks() {
		if (hooksRegistered || !window.elementorFrontend || !window.elementorFrontend.hooks) return;
		hooksRegistered = true;
		window.elementorFrontend.hooks.addAction('frontend/element_ready/marrison_listing_columns.default', schedule);
	}

	function boot() {
		scan();
		registerHooks();
		// Child-list changes cover JetSmartFilters replacements, pagination and editor rerenders.
		new MutationObserver(records => {
			if (records.some(record => record.type === 'attributes' || record.addedNodes.length || record.removedNodes.length)) schedule();
		}).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-marrison-listing-columns'] });
		window.addEventListener('resize', schedule, { passive: true });
		window.addEventListener('storage', event => {
			const matching = Array.from(instances.values()).filter(instance => event.key === instance.key && instance.config.remember && !editing());
			if (matching.some(instance => instance.allowed.includes(event.newValue))) {
				matching.forEach(instance => {
					instance.value = event.newValue;
					instance.choices.set(instance.key, event.newValue);
					paint(instance);
				});
			}
			refresh();
		});
	}

	if (window.jQuery) window.jQuery(window).on('elementor/frontend/init', registerHooks);
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
	else boot();
}());
