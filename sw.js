const CACHE_NAME = 'hub-v2.55'; 

const ASSETS = [
    './manifest.json',
    './public/assets/js/crm.js',
    './public/assets/js/right_sidebar.js',
    './public/assets/js/login.js',
    './public/assets/js/mails.js',
    './public/assets/js/notes.js',
    './public/assets/js/contacts.js?v=1',
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
    console.log("sw FETCH");
    const url = new URL(event.request.url);

    // 1. ABSOLUTER DURCHREICH-MODUS:
    // Wenn es sich um api.php handelt, greift der Service Worker NICHT ein.
    // Dadurch bleiben alle Header (auch X-CSRF-TOKEN) unangetastet.
    if (url.pathname.includes('api.php') || url.searchParams.has('action')) {
        return; 
    }

    // 2. ABBRUCHKRITERIEN: Nur GET-Requests, die keine API sind
    if (event.request.method !== 'GET' || !event.request.url.startsWith('http')) return;

    // Prüfen, ob die Datei exakt zu unseren Core-Assets gehört
    const isCoreAsset = ASSETS.some(asset => {
        const cleanAsset = asset.replace('./', '');
        return url.pathname.endsWith(cleanAsset) || event.request.url.includes(cleanAsset);
    });

    if (isCoreAsset) {
        event.respondWith(
            caches.match(event.request).then(cachedResponse => {
                return cachedResponse || fetch(event.request).then(networkResponse => {
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

    // 3. FÜR DYNAMISCHE SEITEN (Navigation)
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(() => {
                return caches.match('./index.php');
            })
        );
    }
});