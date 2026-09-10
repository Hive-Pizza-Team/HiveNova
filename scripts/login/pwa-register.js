/**
 * Register the site service worker on lobby pages so the lobby meets PWA installability.
 */
(function () {
	if (!('serviceWorker' in navigator)) {
		return;
	}
	navigator.serviceWorker.register('sw.js').catch(function () {});
})();
