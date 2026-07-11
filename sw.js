/**
 * ============================================
 * SERVICE WORKER — FOCUS COACH
 * ============================================
 * Stratégies :
 *   - network-first  : pages HTML/PHP (dynamiques, données fraîches)
 *   - cache-first    : assets statiques (css, js, fonts, images)
 *
 * Cache versionné : bumper CACHE_VERSION à chaque release CSS/JS
 * pour invalider proprement les caches navigateur.
 */

const CACHE_VERSION = 'v2.9.1';
const STATIC_CACHE  = `focuscoach-static-${CACHE_VERSION}`;
const RUNTIME_CACHE = `focuscoach-runtime-${CACHE_VERSION}`;

// Shell à précharger à l'installation
const PRECACHE_URLS = [
    '/',
    '/assets/css/main.css',
    '/assets/css/home.css',
    '/assets/img/icon-192.png',
    '/assets/img/icon-512.png',
    '/manifest.json'
];

// ------------------------- INSTALL -------------------------
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS).catch(() => null))
            .then(() => self.skipWaiting())
    );
});

// ------------------------- ACTIVATE -------------------------
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key.startsWith('focuscoach-') &&
                                    key !== STATIC_CACHE &&
                                    key !== RUNTIME_CACHE)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

// ------------------------- FETCH -------------------------
self.addEventListener('fetch', (event) => {
    const request = event.request;

    // On ne gère que GET et même origine
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Ne pas intercepter les endpoints d'API (toujours dynamiques)
    if (url.pathname.startsWith('/api/')) return;

    // HTML / PHP = network-first (avec fallback cache)
    const accept = request.headers.get('accept') || '';
    const isHtml = accept.includes('text/html') ||
                   url.pathname.endsWith('.php') ||
                   url.pathname === '/' ||
                   url.pathname.endsWith('/');

    if (isHtml) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(RUNTIME_CACHE).then((c) => c.put(request, copy));
                    return response;
                })
                .catch(() => caches.match(request).then((r) => r || caches.match('/')))
        );
        return;
    }

    // Assets statiques = cache-first
    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) return cached;
            return fetch(request).then((response) => {
                if (response && response.status === 200 && response.type === 'basic') {
                    const copy = response.clone();
                    caches.open(STATIC_CACHE).then((c) => c.put(request, copy));
                }
                return response;
            });
        })
    );
});
