/**
 * Service worker — app shell para la PWA (RF-26, RF-29).
 *
 * - install: precachea el shell estático + los assets compilados por Vite
 *   (nombres con hash: se leen de /build/manifest.json en tiempo de instalación).
 * - activate: descarta cachés de versiones anteriores.
 * - fetch:
 *     · /api/*            → solo red, nunca cache (los datos siempre frescos).
 *     · navegación (HTML) → red primero; si falla, la copia en cache y si no
 *       existe, la página /offline (primer arranque sin red, RF-29).
 *     · estáticos         → cache primero, con relleno de red en segundo plano.
 *
 * Sube CACHE_VERSION al cambiar el shell para forzar un precache limpio.
 */
const CACHE_VERSION = 'v1';
const CACHE_NAME = `shopping-list-${CACHE_VERSION}`;

const STATIC_SHELL = [
    '/',
    '/offline',
    '/manifest.json',
    '/favicon.ico',
    '/icons/icon-32.png',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

async function buildAssetUrls() {
    try {
        const res = await fetch('/build/manifest.json', { cache: 'no-cache' });
        if (!res.ok) return [];
        const manifest = await res.json();

        return Object.values(manifest).flatMap((entry) => {
            const urls = entry.file ? [`/build/${entry.file}`] : [];
            for (const css of entry.css || []) urls.push(`/build/${css}`);
            return urls;
        });
    } catch {
        return [];
    }
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            const assets = await buildAssetUrls();
            await cache.addAll([...STATIC_SHELL, ...assets]);
            await self.skipWaiting();
        })(),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
            );
            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Los datos de la API siempre van a la red: nada se sirve de cache.
    if (url.pathname.startsWith('/api/')) return;

    if (request.mode === 'navigate') {
        event.respondWith(
            (async () => {
                try {
                    return await fetch(request);
                } catch {
                    const cache = await caches.open(CACHE_NAME);
                    return (await cache.match(request)) || (await cache.match('/offline'));
                }
            })(),
        );
        return;
    }

    event.respondWith(
        (async () => {
            const cache = await caches.open(CACHE_NAME);
            const cached = await cache.match(request);
            if (cached) return cached;

            const response = await fetch(request);
            if (response.ok) cache.put(request, response.clone());
            return response;
        })(),
    );
});
