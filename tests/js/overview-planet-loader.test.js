'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const {
	applyFallbackVisual,
	applyLoadingVisual,
	hasOverviewPlanetDom,
	isLoaderConfigValid,
	isHiveThemePath,
	resolveFallbackSrc,
	FALLBACK,
	LOADING,
	READY
} = require('../../scripts/game/overview-planet-loader-utils.js');

function createClassList() {
	const classes = new Set();
	return {
		add(name) { classes.add(name); },
		remove(name) { classes.delete(name); },
		has(name) { return classes.has(name); }
	};
}

function createFallbackImg(dataSrc) {
	const attrs = { 'data-src': dataSrc };
	return {
		getAttribute(key) { return attrs[key] ?? null; },
		setAttribute(key, value) { attrs[key] = value; },
		removeAttribute(key) { delete attrs[key]; }
	};
}

function createOverviewDom({ withData = true, withCanvas = true, dataSrc = './styles/theme/hive/planeten/normaltempplanet03_hq.jpg' } = {}) {
	const wrap = { classList: createClassList() };
	const canvas = { id: 'overview-planet-canvas', parentElement: wrap };
	const fallbackImg = createFallbackImg(dataSrc);
	const nodes = {
		'overview-planet-data': withData ? { id: 'overview-planet-data' } : null,
		'overview-planet-canvas': withCanvas ? canvas : null
	};

	wrap.querySelector = function (selector) {
		return selector === '.overview-planet-fallback' ? fallbackImg : null;
	};

	return {
		wrap,
		canvas,
		fallbackImg,
		doc: {
			getElementById(id) { return nodes[id] || null; }
		}
	};
}

describe('overview-planet-loader fallback helpers', () => {
	it('applyFallbackVisual sets fallback class and promotes data-src to src once', () => {
		const { wrap, fallbackImg } = createOverviewDom();
		wrap.classList.add(LOADING, READY);

		applyFallbackVisual(wrap, fallbackImg);

		assert.equal(wrap.classList.has(FALLBACK), true);
		assert.equal(wrap.classList.has(LOADING), false);
		assert.equal(wrap.classList.has(READY), false);
		assert.equal(fallbackImg.getAttribute('src'), './styles/theme/hive/planeten/normaltempplanet03_hq.jpg');
		assert.equal(fallbackImg.getAttribute('aria-hidden'), null);

		fallbackImg.setAttribute('data-src', './styles/theme/nova/planeten/wasserplanet04.jpg');
		applyFallbackVisual(wrap, fallbackImg);
		assert.equal(
			fallbackImg.getAttribute('src'),
			'./styles/theme/hive/planeten/normaltempplanet03_hq.jpg',
			'should not overwrite an existing src'
		);
	});

	it('hasOverviewPlanetDom requires both JSON script tag and canvas', () => {
		assert.equal(hasOverviewPlanetDom(createOverviewDom().doc), true);
		assert.equal(hasOverviewPlanetDom(createOverviewDom({ withData: false }).doc), false);
		assert.equal(hasOverviewPlanetDom(createOverviewDom({ withCanvas: false }).doc), false);
	});

	it('isLoaderConfigValid requires both script URLs', () => {
		assert.equal(isLoaderConfigValid({
			threeSrc: './scripts/threejs/three.min.js',
			planetSrc: './scripts/game/overview-planet.js'
		}), true);
		assert.equal(isLoaderConfigValid(null), false);
		assert.equal(isLoaderConfigValid({ threeSrc: './three.min.js' }), false);
		assert.equal(isLoaderConfigValid({ planetSrc: './overview-planet.js' }), false);
	});

	it('resolveFallbackSrc prefers hq with jpg fallback on error', () => {
		const fallbackImg = createFallbackImg('./styles/theme/hive/planeten/normaltempplanet03.jpg');
		fallbackImg.setAttribute('data-src-hq', './styles/theme/hive/planeten/normaltempplanet03_hq.jpg');
		resolveFallbackSrc(fallbackImg);
		assert.equal(
			fallbackImg.getAttribute('src'),
			'./styles/theme/hive/planeten/normaltempplanet03_hq.jpg'
		);
		fallbackImg.onerror();
		assert.equal(
			fallbackImg.getAttribute('src'),
			'./styles/theme/hive/planeten/normaltempplanet03.jpg'
		);
	});

	it('applyLoadingVisual keeps a dimmed placeholder visible during boot', () => {
		const { wrap, fallbackImg } = createOverviewDom();
		applyLoadingVisual(wrap, fallbackImg);
		assert.equal(wrap.classList.has(LOADING), true);
		assert.equal(fallbackImg.getAttribute('src'), './styles/theme/hive/planeten/normaltempplanet03_hq.jpg');
	});

	it('isHiveThemePath detects hive skin paths only', () => {
		assert.equal(isHiveThemePath('./styles/theme/hive/'), true);
		assert.equal(isHiveThemePath('./styles/theme/nova/'), false);
	});
});
