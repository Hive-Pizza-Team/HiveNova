'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const remember = require('../../scripts/login/remember-username.js');

function fakeStorage(initial) {
	const data = Object.assign({}, initial || {});
	return {
		getItem(key) {
			return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
		},
		setItem(key, value) {
			data[key] = String(value);
		},
		dump() {
			return data;
		}
	};
}

function fakeInput(attrs) {
	const listeners = {};
	const input = {
		value: attrs.value || '',
		form: attrs.form || null,
		getAttribute(name) {
			return Object.prototype.hasOwnProperty.call(attrs, name) && attrs[name] != null
				? String(attrs[name])
				: null;
		},
		addEventListener(type, fn) {
			(listeners[type] || (listeners[type] = [])).push(fn);
		},
		dispatchEvent(evt) {
			(listeners[evt.type] || []).forEach((fn) => fn(evt));
			return true;
		}
	};
	return input;
}

function fakeDoc(inputs) {
	return {
		querySelectorAll(selector) {
			if (selector === '[data-remember-username]') {
				return inputs;
			}
			return [];
		}
	};
}

describe('remember username', () => {
	it('returns an empty record when storage is missing or corrupt', () => {
		assert.deepEqual(remember.read(null), { email: '', hive: '' });
		assert.deepEqual(remember.read(fakeStorage({
			[remember.STORAGE_KEY]: '{not json'
		})), { email: '', hive: '' });
		assert.deepEqual(remember.read(fakeStorage({
			[remember.STORAGE_KEY]: '"just-a-string"'
		})), { email: '', hive: '' });
	});

	it('stores email and hive names without clobbering each other', () => {
		const storage = fakeStorage();
		assert.equal(remember.write(storage, 'email', '  Commander  '), true);
		assert.equal(remember.write(storage, 'hive', ' Alice '), true);
		assert.deepEqual(remember.read(storage), { email: 'Commander', hive: 'alice' });
		assert.equal(remember.write(storage, 'email', '   '), false);
		assert.equal(remember.read(storage).email, 'Commander');
	});

	it('ignores storage failures', () => {
		const storage = {
			getItem() { throw new Error('blocked'); },
			setItem() { throw new Error('blocked'); }
		};
		assert.deepEqual(remember.read(storage), { email: '', hive: '' });
		assert.equal(remember.write(storage, 'email', 'Commander'), false);
	});

	it('fills only empty fields of the matching kind', () => {
		const storage = fakeStorage();
		remember.write(storage, 'email', 'Commander');
		remember.write(storage, 'hive', 'alice');
		const email = fakeInput({ 'data-remember-username': 'email' });
		const hive = fakeInput({ 'data-remember-username': 'hive', maxlength: '16', value: 'kept' });
		const other = fakeInput({ 'data-remember-username': 'nope' });
		const filled = remember.apply(fakeDoc([email, hive, other]), storage);
		assert.equal(filled, 1);
		assert.equal(email.value, 'Commander');
		assert.equal(hive.value, 'kept');
		assert.equal(other.value, '');
	});

	it('clips a restored name to the field maxlength', () => {
		const storage = fakeStorage();
		remember.write(storage, 'hive', 'averylonghivname');
		const hive = fakeInput({ 'data-remember-username': 'hive', maxlength: '5' });
		remember.apply(fakeDoc([hive]), storage);
		assert.equal(hive.value, 'avery');
	});

	it('saves later edits from login and register fields', () => {
		const storage = fakeStorage();
		remember.write(storage, 'email', 'old');
		const formListeners = {};
		const form = {
			addEventListener(type, fn) {
				(formListeners[type] || (formListeners[type] = [])).push(fn);
			}
		};
		const email = fakeInput({ 'data-remember-username': 'email', form: form });
		const hive = fakeInput({ 'data-remember-username': 'hive' });
		remember.bind(fakeDoc([email, hive]), storage);
		assert.equal(email.value, 'old');

		email.value = 'newname';
		email.dispatchEvent({ type: 'input' });
		assert.equal(remember.read(storage).email, 'newname');

		hive.value = 'Bob';
		hive.dispatchEvent({ type: 'change' });
		assert.equal(remember.read(storage).hive, 'bob');
		assert.equal(remember.read(storage).email, 'newname');

		email.value = 'submitted';
		formListeners.submit.forEach((fn) => fn());
		assert.equal(remember.read(storage).email, 'submitted');
	});
});
