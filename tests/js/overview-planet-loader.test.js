'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');

const {
	applyFallbackVisual,
	applyLoadingVisual,
	hasOverviewPlanetDom,
	isLoaderConfigValid,
	isHiveThemePath,
	getVisualSlotSide,
	reserveOverviewCanvasSlot,
	resolveFallbackSrc,
	shouldFallbackAfterBoot,
	isRgbaSampleLit,
	countLitRgbaSamples,
	canvasSamplesLookBlank,
	FALLBACK,
	LOADING,
	READY,
	VISUAL_SIZE_CAP,
	BOOT_WATCHDOG_MS
} = require('../../scripts/game/overview-planet-loader-utils.js');

function createClassList() {
	const classes = new Set();
	return {
		add(name) { classes.add(name); },
		remove(name) { classes.delete(name); },
		has(name) { return classes.has(name); },
		contains(name) { return classes.has(name); }
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

function createOverviewDom({ withData = true, withCanvas = true, dataSrc = './styles/theme/hive/planeten/normaltempplanet03_hq.jpg', slotWidth = 0 } = {}) {
	const wrap = { classList: createClassList(), clientWidth: slotWidth, style: {} };
	wrap.classList.add('overview-planet-visual');
	const canvas = { id: 'overview-planet-canvas', parentElement: wrap, width: 0, height: 0 };
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

	it('resolveFallbackSrc prefers lite jpg with hq fallback on error', () => {
		const fallbackImg = createFallbackImg('./styles/theme/hive/planeten/normaltempplanet03.jpg');
		fallbackImg.setAttribute('data-src-hq', './styles/theme/hive/planeten/normaltempplanet03_hq.jpg');
		resolveFallbackSrc(fallbackImg);
		assert.equal(
			fallbackImg.getAttribute('src'),
			'./styles/theme/hive/planeten/normaltempplanet03.jpg'
		);
		fallbackImg.onerror();
		assert.equal(
			fallbackImg.getAttribute('src'),
			'./styles/theme/hive/planeten/normaltempplanet03_hq.jpg'
		);
	});

	it('applyLoadingVisual marks boot state and promotes fallback image for first paint', () => {
		const { wrap, fallbackImg } = createOverviewDom();
		applyLoadingVisual(wrap, fallbackImg);
		assert.equal(wrap.classList.has(LOADING), true);
		assert.equal(wrap.classList.has(READY), false);
		assert.equal(wrap.classList.has(FALLBACK), false);
		assert.equal(
			fallbackImg.getAttribute('src'),
			'./styles/theme/hive/planeten/normaltempplanet03_hq.jpg'
		);
	});

	it('shouldFallbackAfterBoot is true only when boot reports failure', () => {
		assert.equal(shouldFallbackAfterBoot(true), false);
		assert.equal(shouldFallbackAfterBoot(false), true);
		assert.equal(shouldFallbackAfterBoot(0), true);
	});

	it('canvasSamplesLookBlank detects empty near-black WebGL readbacks', () => {
		assert.equal(typeof BOOT_WATCHDOG_MS, 'number');
		assert.equal(BOOT_WATCHDOG_MS > 0, true);
		assert.equal(isRgbaSampleLit([5, 5, 5, 255]), false);
		assert.equal(isRgbaSampleLit([80, 120, 40, 255]), true);
		assert.equal(countLitRgbaSamples([
			[0, 0, 0, 0],
			[4, 6, 8, 255],
			[2, 2, 2, 255]
		]), 0);
		assert.equal(canvasSamplesLookBlank([
			[0, 0, 0, 0],
			[5, 6, 7, 255],
			[1, 1, 1, 255]
		]), true);
		assert.equal(canvasSamplesLookBlank([
			[0, 0, 0, 0],
			[5, 6, 7, 255],
			[90, 110, 70, 255]
		]), false);
	});

	it('getVisualSlotSide caps width at the visual maximum', () => {
		const wrap = { clientWidth: 360, getBoundingClientRect: () => ({ width: 360 }) };
		assert.equal(getVisualSlotSide(wrap), VISUAL_SIZE_CAP);
	});

	it('reserveOverviewCanvasSlot sets square canvas attributes from wrapper width', () => {
		const { canvas, wrap, doc } = createOverviewDom({ slotWidth: 220 });
		const side = reserveOverviewCanvasSlot(doc);
		assert.equal(side, 220);
		assert.equal(canvas.width, 220);
		assert.equal(canvas.height, 220);
		assert.equal(wrap.style.width, '220px');
		assert.equal(wrap.style.height, '220px');
	});

	it('isHiveThemePath detects hive skin paths only', () => {
		assert.equal(isHiveThemePath('./styles/theme/hive/'), true);
		assert.equal(isHiveThemePath('./styles/theme/nova/'), false);
	});
});
