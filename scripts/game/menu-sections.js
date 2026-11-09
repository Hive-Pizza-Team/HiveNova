'use strict';

var MENU_SECTIONS_STORAGE_KEY = 'hivenova.menuSections.v1';
var MENU_SECTIONS_DEFAULTS = {
	basics: true,
	empire: false,
	account: true
};

function readMenuSectionPrefs(storage) {
	var store = storage || (typeof localStorage !== 'undefined' ? localStorage : null);
	if (!store || typeof store.getItem !== 'function') {
		return {};
	}
	try {
		var raw = store.getItem(MENU_SECTIONS_STORAGE_KEY);
		if (!raw) {
			return {};
		}
		var parsed = JSON.parse(raw);
		return parsed && typeof parsed === 'object' ? parsed : {};
	} catch (err) {
		return {};
	}
}

function writeMenuSectionPrefs(prefs, storage) {
	var store = storage || (typeof localStorage !== 'undefined' ? localStorage : null);
	if (!store || typeof store.setItem !== 'function') {
		return false;
	}
	try {
		store.setItem(MENU_SECTIONS_STORAGE_KEY, JSON.stringify(prefs || {}));
		return true;
	} catch (err) {
		return false;
	}
}

function resolveMenuSectionExpanded(groupId, prefs, hasActive) {
	if (hasActive) {
		return true;
	}
	var stored = prefs && Object.prototype.hasOwnProperty.call(prefs, groupId)
		? prefs[groupId]
		: undefined;
	if (typeof stored === 'boolean') {
		return stored;
	}
	if (Object.prototype.hasOwnProperty.call(MENU_SECTIONS_DEFAULTS, groupId)) {
		return MENU_SECTIONS_DEFAULTS[groupId];
	}
	return true;
}

function setMenuGroupExpanded(group, expanded) {
	if (!group || !group.classList) {
		return false;
	}
	var next = !!expanded;
	group.classList.toggle('is-collapsed', !next);
	var toggle = group.querySelector
		? group.querySelector('[data-menu-toggle]')
		: null;
	if (toggle && toggle.setAttribute) {
		toggle.setAttribute('aria-expanded', next ? 'true' : 'false');
	}
	return next;
}

function bindMenuSectionToggle(group, onChange) {
	if (!group || typeof group.querySelector !== 'function') {
		return false;
	}
	var toggle = group.querySelector('[data-menu-toggle]');
	if (!toggle || typeof toggle.addEventListener !== 'function') {
		return false;
	}
	toggle.addEventListener('click', function () {
		var expanded = toggle.getAttribute('aria-expanded') !== 'false';
		var next = setMenuGroupExpanded(group, !expanded);
		if (typeof onChange === 'function') {
			onChange(group.getAttribute('data-menu-group'), next);
		}
	});
	return true;
}

function initMenuSections(root, storage) {
	if (!root || typeof root.querySelectorAll !== 'function') {
		return false;
	}
	var prefs = readMenuSectionPrefs(storage);
	var groups = root.querySelectorAll('[data-menu-group]');
	if (!groups.length) {
		return false;
	}

	Array.prototype.forEach.call(groups, function (group) {
		var groupId = group.getAttribute('data-menu-group') || '';
		var hasActive = !!(group.querySelector && group.querySelector('a.active'));
		var expanded = resolveMenuSectionExpanded(groupId, prefs, hasActive);
		setMenuGroupExpanded(group, expanded);
		bindMenuSectionToggle(group, function (id, next) {
			prefs[id] = next;
			writeMenuSectionPrefs(prefs, storage);
		});
	});

	return true;
}

var HiveNovaMenuSections = {
	STORAGE_KEY: MENU_SECTIONS_STORAGE_KEY,
	DEFAULTS: MENU_SECTIONS_DEFAULTS,
	readPrefs: readMenuSectionPrefs,
	writePrefs: writeMenuSectionPrefs,
	resolveExpanded: resolveMenuSectionExpanded,
	setExpanded: setMenuGroupExpanded,
	bindToggle: bindMenuSectionToggle,
	init: initMenuSections
};

if (typeof document !== 'undefined' && document.addEventListener) {
	document.addEventListener('DOMContentLoaded', function () {
		var root = document.querySelector('[data-menu-sections]');
		if (root) {
			initMenuSections(root);
		}
	});
}

if (typeof module !== 'undefined' && module.exports) {
	module.exports = HiveNovaMenuSections;
}
