const CACHE_NAME = 'hub-v2.6'; 

// 📦 Alle statischen Kern-Ressourcen inklusive aller View-Skripte und CDNs!
const ASSETS = [
    './index.php',
    './manifest.json',
    './public/assets/js/crm.js',
    './public/assets/js/right_sidebar.js',
    './public/assets/js/login.js',
    './public/assets/js/mails.js',
    './public/assets/js/notes.js',
    './public/assets/js/paperless.js',
    './public/assets/js/appointments.js',
    './public/assets/js/detail.js',
    './public/assets/js/setting.js',
    // Externe CDNs cachen, damit das UI offline nicht stirbt
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js'
];

// 1. Resilienter Install-Prozess
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async cache => {
            console.log('⚡ PWA: Starte resilienten Asset-Cache...');
            for (const url of ASSETS) {
                try {
                    await cache.add(url);
                } catch (err) {
                    console.warn(`⚠️ PWA: Asset-Pfad nicht erreichbar: ${url}`);
                }
            }
        })
    );
    self.skipWaiting();
});

// 2. Aktivierung: Alten Cache sauber wegräumen
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        console.log('🧹 PWA: Lösche alten Cache:', cache);
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Nur GET-Requests verarbeiten
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) return;

    // 🔐 API KOMPLETT IGNORIEREN & DIREKT INS NETZWERK LEITEN
    if (url.pathname.endsWith('api.php') || url.searchParams.has('action')) {
        event.respondWith(fetch(event.request));
        return; 
    }

    // 🚀 CACHE-FIRST mit Network-Fallback für statische Assets & CDNs
    event.respondWith(
        caches.match(event.request).then(cachedResponse => {
            if (cachedResponse) {
                // Stale-While-Revalidate für statische Daten
                fetch(event.request).then(networkResponse => {
                    if (networkResponse.status === 200) {
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, networkResponse));
                    }
                }).catch(() => {});
                
                return cachedResponse;
            }

            return fetch(event.request).then(networkResponse => {
                if (networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
                }
                return networkResponse;
            }).catch(() => {
                if (event.request.mode === 'navigate') {
                    return caches.match('./index.php');
                }
            });
        })
    );
});