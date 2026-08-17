'use strict';

// Serve the static files of the compiled Adminer from the Cache Storage.
// The HTTP cache is enough almost everywhere but some hosts forbid caching anything their
// interface generates, and Adminer generates its own files. Cache Storage is filled by
// explicit put() calls so those headers don't apply to it.

// this script is served from <Adminer URL>?file=worker.js&version=<version>, the rest follows from that
const prefix = self.location.href.replace(/worker\.js&version=.*/, '');
const version = self.location.href.replace(/.*&version=/, '');
const cacheName = 'adminer-' + version;

// don't wait for the tabs of the previous version to close
self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', event => {
	event.waitUntil(self.clients.claim().then(() => caches.keys()).then(keys => Promise.all(
		keys.filter(key => key != cacheName && key.startsWith('adminer-')).map(key => caches.delete(key))
	)));
});

self.addEventListener('fetch', event => {
	const url = event.request.url;
	if (
		event.request.method != 'GET'
		|| !url.startsWith(prefix)
		|| !url.endsWith('&version=' + version) // a file of another version, its cache is deleted on activate
		|| url == self.location.href // the browser fetches this script bypassing this handler but a cached service worker could never be replaced
	) {
		return; // not ours - do nothing, which lets the browser handle it as if there was no service worker
	}
	event.respondWith(caches.open(cacheName)
		.then(cache => cache.match(event.request).then(cached => cached || fetch(event.request).then(response => {
			if (response.ok) {
				// the version in the URL makes the file immutable so it is never revalidated; waitUntil keeps the worker alive until it is stored
				event.waitUntil(cache.put(event.request, response.clone()));
			}
			return response;
		})))
		.catch(() => fetch(event.request)) // a broken cache must not make Adminer unreachable
	);
});
