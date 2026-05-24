const CACHE_NAME = 'hub-v2.9'; 

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
    './public/assets/js/settings.js',
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.14.5/dist/cdn.min.js'
];

// 1. Installieren (Unverändert, stabil)
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(async cache => {
            console.log('⚡ PWA: Starte resilienten Asset-Cache...');
            for (const url of ASSETS) {
                try {
                    const request = new Request(url, { mode: url.includes('cdn') ? 'cors' : 'no-cors' });
                    await cache.add(request);
                } catch (err) {
                    console.warn(`⚠️ PWA: Asset nicht erreichbar: ${url}`);
                }
            }
        })
    );
    self.skipWaiting();
});

// 2. Aktivieren
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cache => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// 3. Fetch (KOMPLETT ENTKERNT GEGEN SCHLEIFEN)
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // ABBRUCHKRITERIEN: Nur GET, keine APIs, keine Actions, keine WebSockets
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) return;
    if (url.pathname.endsWith('api.php') || url.searchParams.has('action')) return;

    // Prüfen, ob die Datei exakt zu unseren Core-Assets gehört
    const isCoreAsset = ASSETS.some(asset => {
        const cleanAsset = asset.replace('./', '');
        return url.pathname.endsWith(cleanAsset) || event.request.url.includes(cleanAsset);
    });

    if (isCoreAsset) {
        // REINES CACHE-FIRST: Keine Hintergrund-Anfragen (Stale-While-Revalidate) mehr, 
        // die die Schleife triggern könnten!
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                if (cachedResponse) {
                    return cachedResponse; // 0ms aus dem Cache, fertig.
                }
                // Fallback ans Netzwerk, falls beim Installieren was schiefging
                return fetch(event.request).then(networkResponse => {
                    if (networkResponse.status === 200) {
                        const responseClone = networkResponse.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
                    }
                    return networkResponse;
                }).catch(() => new Response('Asset Offline nicht verfügbar', { status: 503 }));
            })
        );
        return;
    }

    // FÜR ALLES ANDERE (Dynamische PHP-Seiten, HTML-Wechsel): 
    // Reines Netzwerk. Wenn offline, dann lade die index.php aus dem Cache.
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match('./index.php');
            })
        );
    }
});