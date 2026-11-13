'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const pwa = require('../../scripts/game/pwa-install.js');

function fakeStorage(initial) {
	const data = Object.assign({}, initial || {});
	return {
		getItem(key) {
			return Object.prototype.hasOwnProperty.call(data, key) ? data[key] : null;
		},
		setItem(key, value) {
			data[key] = String(value);
		},
		removeItem(key) {
			delete data[key];
		},
		dump() {
			return data;
		}
	};
}

describe('PWA install dismiss persistence', () => {
	it('exports the shared dismiss key and 30-day window', () => {
		assert.equal(pwa.DISMISS_KEY, 'hivenova_pwa_install_dismissed');
		assert.equal(pwa.DISMISS_MS, 30 * 24 * 60 * 60 * 1000);
	});

	it('writeDismiss stores a timestamp that isDismissed honors across “reloads”', () => {
		const storage = fakeStorage();
		const now = 1_700_000_000_000;
		assert.equal(pwa.isDismissed(now, storage), false);
		assert.equal(pwa.writeDismiss(now, storage), true);
		assert.equal(storage.getItem(pwa.DISMISS_KEY), String(now));
		assert.equal(pwa.isDismissed(now + 1000, storage), true);
		assert.equal(pwa.isDismissed(now + pwa.DISMISS_MS - 1, storage), true);
		assert.equal(pwa.isDismissed(now + pwa.DISMISS_MS, storage), false);
	});

	it('treats missing, corrupt, or failing storage as not dismissed', () => {
		assert.equal(pwa.isDismissed(Date.now(), null), false);
		assert.equal(pwa.isDismissed(Date.now(), fakeStorage({ [pwa.DISMISS_KEY]: 'nope' })), false);
		assert.equal(pwa.writeDismiss(1, null), false);
		const boom = {
			getItem() { throw new Error('blocked'); },
			setItem() { throw new Error('blocked'); }
		};
		assert.equal(pwa.isDismissed(Date.now(), boom), false);
		assert.equal(pwa.writeDismiss(Date.now(), boom), false);
	});

	it('shouldShowInstallBanner stays false after dismiss until expiry', () => {
		assert.equal(pwa.shouldShowInstallBanner({
			standalone: false,
			dismissed: true,
			page: 'overview'
		}), false);
		assert.equal(pwa.shouldShowInstallBanner({
			standalone: false,
			dismissed: false,
			page: 'overview'
		}), true);
		assert.equal(pwa.shouldShowInstallBanner({
			standalone: true,
			dismissed: false,
			page: 'overview'
		}), false);
	});
});

describe('PWA install banner fleet pages', () => {
	it('identifies fleet dispatch pages from body id / query', () => {
		['fleetTable', 'fleetStep1', 'fleetStep2', 'fleetStep3'].forEach((page) => {
			assert.equal(pwa.isFleetDispatchPage(page), true);
			assert.equal(pwa.shouldShowInstallBanner({
				standalone: false,
				dismissed: false,
				page
			}), false);
		});
		assert.equal(pwa.isFleetDispatchPage('overview'), false);
		assert.equal(pwa.isFleetDispatchPage('fleetDealer'), false);
		assert.equal(pwa.pageFromSearch('?page=fleetTable&lang=en'), 'fleetTable');
		assert.equal(pwa.getGamePage({ body: { id: 'fleetStep2' } }), 'fleetStep2');
		assert.equal(pwa.getGamePage({ body: {} }, '?page=fleetStep3'), 'fleetStep3');
	});
});
