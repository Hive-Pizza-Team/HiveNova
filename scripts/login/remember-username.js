/**
 * Remember the last username typed on login or register.
 * Password-login names and Hive account names are stored separately.
 */
(function (root) {
	'use strict';

	var STORAGE_KEY = 'hivenova.lastUsername';
	var MAX_LENGTH = 25;

	function emptyRecord() {
		return { email: '', hive: '' };
	}

	function normalizeKind(kind) {
		return kind === 'hive' || kind === 'email' ? kind : '';
	}

	function normalizeUsername(kind, username) {
		var value = String(username == null ? '' : username).replace(/^\s+|\s+$/g, '');
		if (kind === 'hive') {
			value = value.toLowerCase();
		}
		if (value.length > MAX_LENGTH) {
			value = value.slice(0, MAX_LENGTH);
		}
		return value;
	}

	function read(storage) {
		if (!storage || typeof storage.getItem !== 'function') {
			return emptyRecord();
		}
		try {
			var raw = storage.getItem(STORAGE_KEY);
			if (!raw) {
				return emptyRecord();
			}
			var parsed = JSON.parse(raw);
			if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
				return emptyRecord();
			}
			return {
				email: normalizeUsername('email', parsed.email),
				hive: normalizeUsername('hive', parsed.hive)
			};
		} catch (e) {
			return emptyRecord();
		}
	}

	function write(storage, kind, username) {
		kind = normalizeKind(kind);
		if (!kind || !storage || typeof storage.getItem !== 'function' || typeof storage.setItem !== 'function') {
			return false;
		}
		var value = normalizeUsername(kind, username);
		if (!value) {
			return false;
		}
		try {
			var current = read(storage);
			current[kind] = value;
			storage.setItem(STORAGE_KEY, JSON.stringify(current));
			return true;
		} catch (e) {
			return false;
		}
	}

	function clipToMaxlength(input, value) {
		var max = parseInt(input.getAttribute('maxlength'), 10);
		if (max > 0 && value.length > max) {
			return value.slice(0, max);
		}
		return value;
	}

	function apply(doc, storage) {
		if (!doc || typeof doc.querySelectorAll !== 'function') {
			return 0;
		}
		var stored = read(storage);
		var filled = 0;
		doc.querySelectorAll('[data-remember-username]').forEach(function (input) {
			var kind = normalizeKind(input.getAttribute('data-remember-username'));
			if (!kind || String(input.value || '').replace(/^\s+|\s+$/g, '') !== '') {
				return;
			}
			var value = clipToMaxlength(input, stored[kind] || '');
			if (!value) {
				return;
			}
			input.value = value;
			filled += 1;
			if (typeof input.dispatchEvent === 'function' && typeof Event === 'function') {
				input.dispatchEvent(new Event('input', { bubbles: true }));
			}
		});
		return filled;
	}

	function bind(doc, storage) {
		if (!doc || typeof doc.querySelectorAll !== 'function') {
			return [];
		}
		var bound = [];
		doc.querySelectorAll('[data-remember-username]').forEach(function (input) {
			var kind = normalizeKind(input.getAttribute('data-remember-username'));
			if (!kind) {
				return;
			}
			function save() {
				write(storage, kind, input.value);
			}
			input.addEventListener('input', save);
			input.addEventListener('change', save);
			if (input.form && typeof input.form.addEventListener === 'function') {
				input.form.addEventListener('submit', save);
			}
			bound.push(input);
		});
		apply(doc, storage);
		return bound;
	}

	function boot(doc, storage) {
		var documentObj = doc || (typeof document !== 'undefined' ? document : null);
		var store = storage;
		if (!store && typeof localStorage !== 'undefined') {
			store = localStorage;
		}
		return bind(documentObj, store);
	}

	function scheduleBoot() {
		if (typeof document === 'undefined') {
			return;
		}
		if (document.readyState === 'complete') {
			boot();
			return;
		}
		document.addEventListener('DOMContentLoaded', function () {
			boot();
		});
	}

	var api = {
		STORAGE_KEY: STORAGE_KEY,
		read: read,
		write: write,
		apply: apply,
		bind: bind,
		boot: boot
	};

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	} else {
		root.HiveNovaRememberUsername = api;
		scheduleBoot();
	}
})(typeof globalThis !== 'undefined' ? globalThis : this);
