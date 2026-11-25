'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadScrollHandler(documentMock, windowMock) {
	const tpl = fs.readFileSync(
		path.join(__dirname, '../../styles/templates/game/main.header.tpl'),
		'utf8'
	);
	const match = tpl.match(/\$\(window\)\.scroll\(function\(\)\{[\s\S]*?\}\);/);
	assert.ok(match, 'main.header.tpl should define the $(window).scroll affix handler');

	let registered = null;
	const sandbox = {
		window: windowMock,
		document: documentMock,
		Math,
		scrollTo: windowMock.scrollTo,
		$(target) {
			return {
				scroll(fn) {
					if (target === windowMock) {
						registered = fn;
					}
				},
			};
		},
	};
	vm.runInNewContext(match[0], sandbox);
	assert.equal(typeof registered, 'function');
	return { handler: registered, sandbox };
}

test('menu affix scroll handler ignores missing <menu> before body parse (#767)', () => {
	const origScrollTo = () => {};
	const { handler, sandbox } = loadScrollHandler(
		{
			getElementsByTagName: () => [],
			documentElement: { clientHeight: 844, scrollTop: 120 },
			body: { clientHeight: 844, scrollTop: 0 },
		},
		{ innerHeight: 844, pageYOffset: 120, scrollTo: origScrollTo }
	);

	assert.doesNotThrow(() => handler());
	assert.equal(sandbox.scrollTo, origScrollTo);
});

test('menu affix scroll handler ignores <menu> without .fixed child (#767)', () => {
	const { handler } = loadScrollHandler(
		{
			getElementsByTagName: () => [{ getElementsByClassName: () => [] }],
			documentElement: { clientHeight: 844, scrollTop: 50 },
			body: { clientHeight: 844, scrollTop: 0 },
		},
		{ innerHeight: 844, pageYOffset: 50, scrollTo: () => {} }
	);

	assert.doesNotThrow(() => handler());
});

test('menu affix scroll handler positions tall .fixed menu without clobbering scrollTo', () => {
	const origScrollTo = () => {};
	const fixedEl = { clientHeight: 1000, style: { top: '' } };
	const { handler, sandbox } = loadScrollHandler(
		{
			getElementsByTagName: () => [{ getElementsByClassName: () => [fixedEl] }],
			documentElement: { clientHeight: 800, scrollTop: 150 },
			body: { clientHeight: 800, scrollTop: 0 },
		},
		{ innerHeight: 800, pageYOffset: 150, scrollTo: origScrollTo }
	);

	handler();
	assert.equal(fixedEl.style.top, '-50px');
	assert.equal(sandbox.scrollTo, origScrollTo);
});
