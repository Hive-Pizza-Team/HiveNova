/**
 * Register the site service worker on lobby pages so the lobby meets PWA installability.
 * Use the same origin-root /sw.js as in-game push so /uniN/ pages share one registration.
 */
(function () {
	if (!('serviceWorker' in navigator)) {
		return;
	}
	var path = (typeof location !== 'undefined' && location.pathname) ? location.pathname : '';
	var script = /^\/uni[0-9]+(\/|$)/.test(path) ? '/sw.js' : 'sw.js';
	navigator.serviceWorker.register(script, { updateViaCache: 'none' }).catch(function () {});
})();
