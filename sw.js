const CACHE_NAME = 'crm_1.86'; 

// Nur lokale Assets cachen! CDNs gehören nicht in den Cache.
const ASSETS = [
    './manifest.json',
    './public/assets/js/crm.js',
    './public/assets/js/right_sidebar.js',
    './public/assets/js/login.js',
    './public/assets/js/mails.js',
    './public/assets/js/notes.js',
    './public/assets/js/contacts.js',
    './public/assets/js/paperless.js',
    './public/assets/js/appointments.js',
    './public/assets/js/detail.js',
    './public/assets/js/settings.js'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            console.log('⚡ PWA: Installiere Core-Assets...');
            return cache.addAll(ASSETS); // cache.addAll ist robuster als manuelle Schleife
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.map(key => key !== CACHE_NAME ? caches.delete(key) : null)
        ))
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // 1. API IMMER ignorieren (Sicherheit & CORS-Probleme vermeiden)
    if (url.pathname.includes('api.php') || url.searchParams.has('action')) {
        return; 
    }

    // 2. Nur GET Requests
    if (event.request.method !== 'GET') return;

    // 3. Wenn es ein Core-Asset ist -> Cache-First
    if (ASSETS.some(asset => url.pathname.endsWith(asset.replace('./', '')))) {
        event.respondWith(
            caches.match(event.request).then(response => response || fetch(event.request))
        );
        return;
    }

    // 4. Navigation (Dynamic Content) -> Network-First, Fallback auf Index
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('./index.php'))
        );
    }
});