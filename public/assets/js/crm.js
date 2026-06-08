/**
 * Enterprise Hub - Kombinierte Core Engine & All-In-One PWA Controller
 * 100% Offline-Sicher, robustes Caching & nahtloses Infinite Loading
 */

window.crmNotifications = [];
const GLOBAL_API_URL = 'api.php';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

/**
 * Modal Dialog statt confirm()
 * @param {} message 
 * @param {*} callback 
 */
function confirmAction(message, callback) {
    window.dispatchEvent(new CustomEvent('confirm-modal', {
        detail: { message, callback }
    }));
}

function showNotify(title, message, type = 'danger') {
    // 1. Notification zum globalen Array hinzufügen (für die Sidebar)
    const newNotification = {
        id: Date.now() + Math.random(),
        title,
        message,
        type,
        time: new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
    };
    window.crmNotifications.push(newNotification);

    // Event feuern, damit AlpineJS in der Sidebar den Counter sofort hochzählt
    document.dispatchEvent(new CustomEvent('notifications-updated', { detail: window.crmNotifications }));

    // 2. Toast-Container für das flüchtige Popup unten rechts erstellen
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'fixed bottom-4 right-4 z-50 space-y-2 max-w-sm w-full';
        document.body.appendChild(container);
    }

    const colorMapping = {
        'danger': 'bg-red-500 dark:bg-red-600 text-white',
        'success': 'bg-emerald-500 dark:bg-emerald-600 text-white',
        'info': 'bg-blue-500 dark:bg-blue-600 text-white',
        'warning': 'bg-amber-500 text-slate-900'
    };

    const bgClass = colorMapping[type] || colorMapping['danger'];

    // Toast-Element im schicken, abgerundeten Apple-Look erzeugen
    const toast = document.createElement('div');
    toast.className = `transform translate-y-4 opacity-0 transition-all duration-300 ease-out p-4 rounded-2xl shadow-xl flex items-start justify-between backdrop-blur-md ${bgClass}`;

    toast.innerHTML = `
        <div class="flex-1 pr-3 text-xs">
            <div class="font-bold mb-0.5">${title}</div>
            <div class="opacity-90 leading-relaxed">${message}</div>
        </div>
        <button type="button" class="text-current opacity-60 hover:opacity-100 font-bold text-sm transition-opacity shrink-0" onclick="this.closest('#notification-container > div').remove()">✕</button>
    `;

    container.appendChild(toast);

    // Animation einleiten (Slide-In)
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    });

    // Auto-Löschen nach 5 Sekunden aus dem flüchtigen Toast-Sichtfeld
    setTimeout(() => {
        toast.classList.add('opacity-0', 'scale-95');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

let ALLOWED_ACTIONS = [];

function triggerSecurityLockout() {
    // 1. Erstelle das Fullscreen-Schutzschild
    const overlay = document.createElement('div');
    overlay.id = 'security-lockout-overlay';
    
    // Styling für den Unschärfe-Effekt (Frisst auch alle Klicks ab)
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.15); /* Eleganter, dunkler Schleier */
        backdrop-filter: blur(12px);         /* Macht den Hintergrund unscharf */
        -webkit-backdrop-filter: blur(12px); /* Safari Support */
        z-index: 999990;                     /* Extrem hoch, fängt alles ab */
        pointer-events: all;                 /* Blockiert Klicks auf die App */
        cursor: not-allowed;
    `;
    
    // In die Seite einfügen
    document.body.appendChild(overlay);

    // 2. Benachrichtigung anzeigen
    showNotify("Sicherheit", "Sicherheitsfall: Token nicht gefunden!", "error");

    // 3. Nach 2 Sekunden Bedenkzeit hart zum Login weiterleiten
    setTimeout(() => {
        window.location.replace('index.php?view=Login');
    }, 2000);
}

async function initApiConfig() {
    const res = await apiCall('get_allowed_actions', {}, 'GET');
    if (res.success) ALLOWED_ACTIONS = res.data;
}

//Standardisierte aufrufe
// Standardisierte Aufrufe
async function apiCall(action, params = {}, method = 'POST') 
{
    const publicActions = ['login', 'register'];
    const isPublic = publicActions.includes(action);

    const metaTag = document.querySelector('meta[name="csrf-token"]');
    const currentToken = metaTag ? metaTag.content : '';

    if (!isPublic && (!currentToken || currentToken === '')) {
        console.error("[SECURITY] Abbruch: Kein CSRF-Token im DOM gefunden!");
        triggerSecurityLockout();
        return { success: false, error: "Kein CSRF-Token gefunden. Bitte Seite neu laden." };
    }

    if (typeof ALLOWED_ACTIONS !== 'undefined' && ALLOWED_ACTIONS.length > 0 && !ALLOWED_ACTIONS.includes(action)) {
        console.error("Ungültige Aktion!");
        showNotify("AKTION " + action, "Aktion unbekannt!", "error");
        return { success: false, error: "Ungültige Aktion" };
    }

    let url = `${GLOBAL_API_URL}?action=${action}`;
    
    // 💡 KORREKTUR: Wenn params ein FormData-Objekt ist, DARF kein Content-Type gesetzt werden!
    // Der Browser setzt die Multi-Part-Boundaries sonst falsch.
    let headers = {};
    if (!(params instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }
    
    if (currentToken) {
        headers['X-CSRF-TOKEN'] = currentToken;
    }

    let options = { method: method, headers: headers };

    if (method === 'POST') {
        options.body = params; // Kann nun JSON-kompatibles Objekt ODER FormData sein
    } else {
        const query = new URLSearchParams(params).toString();
        url += query ? `&${query}` : '';
    }

    return await safeFetch(url, options);
}

function checkUrlOnLoad() {
    const urlParams = new URLSearchParams(window.location.search);
    const hash = window.location.hash.substring(1); // Entfernt das '#'
    
    // Wenn view=mails aktiv ist und im Hash eine Action steckt
    if (urlParams.get('view') === 'mails' && hash.includes('action=view')) {
        // Extrahiere den reinen Hash vor dem '&'
        const cleanHash = hash.split('&')[0];
        
        // Triggere die Ansicht verzögert, bis Alpine bereit ist
        setTimeout(() => {
            dispatchAction('mail_view', { hash: cleanHash });
        }, 100);
    }
}

// Ausführen beim Seitenstart
document.addEventListener('DOMContentLoaded', checkUrlOnLoad);

window.addEventListener('select-mail-hash', (event) => {
    console.log("mail-hash");
    const mailHash = event.detail.hash;
    
    // Prüfen, welche View aktuell in der URL aktiv ist
    const urlParams = new URLSearchParams(window.location.search);
    const currentView = urlParams.get('view');

    if (currentView === 'mails') {
        // Fall A: Wir sind schon in der Mail-Ansicht -> Mail sofort live öffnen (ohne Reload!)
        dispatchAction('mail_view', { hash: mailHash });
    } else {
        // Fall B: Wir sind woanders (z.B. Dashboard) -> Seite wechseln mit Hash im Gepäck
        window.location.href = `index.php?view=mails#${mailHash}&action=view`;
    }
});

/**
 * Der zentrale Verteiler für alle Website-weiten Aktionen
 */
function dispatchAction(action, context, triggerElement) {
    onsole.log(`Zentrale Action getriggert: [${action}]`, context);

    switch (action) {
        case 'mail_view':
            // 💡 HIER REAGIERST DU AUF DEN HASH
            console.log("Öffne Mail mit Hash:", context.hash);
            
            // Falls du eine Funktion hast, die die Mail im Hauptfenster rendert:
            if (typeof window.loadMailByHash === 'function') {
                window.loadMailByHash(context.hash);
            }
            
            // Optional: Scrollt den Browser zum Mail-Inhalt, falls nötig
            window.location.hash = context.hash;
            break;

        case 'note_add':
            openNoteModal(context.uid, context.folder);
            break;

        case 'mail_archive':
            archiveMail(context.uid, triggerElement);
            break;

        // ... deine anderen Cases ...

        default:
            console.warn(`Definition für Action "${action}" fehlt im Switch-Block!`);
    }
}

async function safeFetch(url, customOptions = {}) {
    const defaultOptions = {
        method: 'POST',
        credentials: 'include',
        headers: {}
    };

    const finalOptions = {
        ...defaultOptions,
        ...customOptions,
        headers: { ...defaultOptions.headers, ...customOptions.headers }
    };

    // 💡 KORREKTUR: Nur stringifizieren, wenn es ein flaches Objekt und KEIN FormData ist
    if (finalOptions.method === 'POST' && finalOptions.body && !(finalOptions.body instanceof FormData) && typeof finalOptions.body === 'object') {
        finalOptions.body = JSON.stringify(finalOptions.body);
    }

    try {
        const response = await fetch(url, finalOptions);

        if (response.redirected && response.url.includes('cloudflareaccess.com')) {
            console.warn('Cloudflare Access Session abgelaufen!');
            window.location.href = response.url;
            return false;
        }

        if (!response.ok) {
            const rawText = await response.text();
            let errorMsg = `Server antwortete mit Status ${response.status}`;
            try {
                const json = JSON.parse(rawText);
                if (json && json.error) errorMsg = json.error;
            } catch(e) {}
            showNotify("FEHLER", errorMsg, "danger");
            throw new Error(errorMsg);
        }

        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            const raw = await response.text();
            throw new Error("Server-Fehler: Antwort ist kein JSON.");
        }

        // 💡 KORREKTUR: jResponse/data Variablen-Chaos beseitigt
        const data = await response.json();
        
        if (data && data.success === false) {
            console.error("API Error:", data.error);
            showNotify("Fehler", data.error || "Ein unbekannter Fehler ist aufgetreten", "danger");
            return data;
        }

        if (data.details?.ui?.notify) {
            const ui = data.details.ui;
            showNotify(ui.title, ui.message, ui.type || 'info');
        }

        return data;

    } catch (error) {
        showNotify("Systemfehler", error.message, "danger");
        return { success: false, error: error.message };
    }
}

window.crmEngine = function() {
    // Fallback falls window.CRM_API_URL in index.php fehlt
    const apiUrl = window.CRM_API_URL || 'api.php';

    return {
        // --- CORE & UI STATE ---
        currentView: 'login', 
        isOnline: navigator.onLine,
        apiUrl: apiUrl,
        loading: false,
        searchQuery: '',
        filteredContacts: [],
        newNote: { content: '' },
        isDarkMode: false,
        rightPanel: { open: false, tab: 'call' },
        incomingCall: { number: '', clientName: '' },
        rightSidebarOpen: false,
        selectedContactId: null,
        currentHash: null,
        dmsLoading: false,

        // --- CACHED DATA STATES (Local Storage Fallbacks) ---
        mails: JSON.parse(localStorage.getItem('cached_mails') || '[]'),
        notes: JSON.parse(localStorage.getItem('cached_notes') || '[]'),
        appointments: JSON.parse(localStorage.getItem('cached_appointments') || '[]'),
        paperlessTree: {},
        results: [],
        
        // --- MAIL SYSTEM STACK ---
        currentFolder: 'INBOX',
        page: 1,
        limit: 20,
        hasMore: true,
        selectedHash: null,
        mailSearchQuery: '',
        manualSearchQuery: '',
        currentMail: null,
        currentMailUrl: 'about:blank',
        paperlessMatches: [],
        showMobilePreview: false,
        mailLoading: false,
        
        // --- LIVE SYSTEM BANNER STATES ---
        haInfoHtml: '<span class="text-gray-400 text-xs">Lade...</span>',
        serverInfoHtml: '<span class="text-gray-400 text-xs">Lade...</span>',

        // --- GLOBAL INITIALIZATION ---
        async init() {
            // Wenn wir auf der Login-Seite sind, holen wir KEINE Daten!
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('view') === 'login' || !urlParams.get('view')) {
                return; 
            }

            if (typeof initApiConfig === 'function') {
                await initApiConfig();
            }
            // 1. EVENT-LISTENER: Reagiert nur auf Status-WECHSEL während der Laufzeit
            window.addEventListener('online', () => { 
                this.isOnline = true; 
                this.syncOfflineNotes(); // Greift, wenn das Netz zwischendurch weg war
                this.loadNotesBackend();
            });
            window.addEventListener('offline', () => { 
                this.isOnline = false; 
            });

            this.evaluateTimeBasedTheme();
            this.initPullToRefresh();
            
            // 2. INITIALER START-CHECK: Wenn beim Laden der App SCHON Internet da ist
            if (this.isOnline) {
                // 💡 HIER GEHÖRT ES HIN!
                // Erst syncen, falls noch Altlasten im LocalStorage liegen...
                await this.syncOfflineNotes(); 
                
                // ...dann die frische Liste aus dem Backend holen
                this.loadNotesBackend();
            }

        },

        async triggerSearch() {
            const trimmed = this.searchQuery.trim();
            
            // Mindestlänge prüfen
            if (trimmed.length < 2) {
                this.results = [];
                return;
            }
            
            if (this.isOnline) {
                this.loading = true;
                try {
                    // Da du oben nach "Quast" suchst, übergeben wir den String hier direkt an deinen API-Pfad
                    const jResponse = await apiCall('search_contact', {hash: trimmed}, 'GET');
                    
                    if (jResponse?.success) {
                        // Hier nimmst du die zurückgegebenen Treffer (die Liste mit dem herkunft-Flag) auf
                        this.results = jResponse.details.contacts || jResponse.details || [];
                    }
                } catch (err) {
                    console.error("Suchfehler im Backend:", err);
                } finally {
                    this.loading = false;
                }
            }
        },

        selectContact(contactId) {
            if (!contactId) return;

            this.currentView = 'detail'; 
            this.selectedContactId = contactId; 

            const newUrl = `${window.location.protocol}//${window.location.host}${window.location.pathname}?view=detail&id=${contactId}`;
            window.history.pushState({ path: newUrl }, '', newUrl);

            this.searchQuery = '';
            this.results = [];
        },

        // =========================================================================
        // BEREICH: NOTIZEN (NOTES)
        // =========================================================================
        async loadNotesBackend() {
            if (!this.isOnline) return;
            const jResponse = await apiCall('get_notes', {}, 'GET');
            
            if (jResponse?.success) {
                this.notes = jResponse.details.notes || [];
                localStorage.setItem('cached_notes', JSON.stringify(this.notes));
            }
        },

        async addNote() {
            const content = this.newNote?.content?.trim();
            if (!content) return;

            const payload = { id: 'local_' + Date.now(), content, status: 'pending' };
            this.notes.unshift(payload);
            localStorage.setItem('cached_notes', JSON.stringify(this.notes));

            if (this.isOnline) {
                const jResponse = await apiCall('crm_save_note', payload, 'POST');
                
                if (jResponse?.success && jResponse.details?.db_id) payload.id = jResponse.details.db_id;
            } else {
                let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
                queue.push(payload);
                localStorage.setItem('queue_notes', JSON.stringify(queue));
            }
            this.newNote.content = '';
        },

       
        async syncOfflineNotes() {
            if (!navigator.onLine) return;

            // 1. Hol die offenen Memos aus der Queue
            let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
            if (queue.length === 0) return;

            console.log(`🔄 ${queue.length} Offline-Notiz(en) gefunden. Starte Sync...`);
            
            let remainingQueue = [];

            for (let memo of queue) {
                try {
                    // Wir senden das Memo an deinen PHP-Controller
                    // (Passe 'save_note' an deinen tatsächlichen API-Endpoint an)
                    const jResponse = await apiCall('save_note', memo, 'POST');
                    
                    if (jResponse && jResponse.note) {
                        console.log(`✅ Notiz ${memo.id} erfolgreich mit DB synchronisiert.`);
                        
                        // Optionale Kosmetik: Ersetze die 'local_...' ID in deiner Live-Liste 
                        // durch das echte Notiz-Objekt vom Server (inkl. echter DB-ID)
                        this.notes = this.notes.map(n => n.id === memo.id ? jResponse.note : n);
                    } else {
                        // Server hat geantwortet, aber kein Erfolg (z.B. Validierungsfehler)
                        // Wir behalten es in der Queue, um Datenverlust zu vermeiden
                        remainingQueue.push(memo);
                    }
                } catch (err) {
                    console.error(`❌ Sync-Fehler für Notiz ${memo.id}:`, err);
                    // Netzwerk-Timeout oder Server down? Zurück in die Warteschlange für den nächsten Versuch!
                    remainingQueue.push(memo);
                }
            }

            // 2. Queue im LocalStorage aktualisieren (Erfolgreiche Memos sind jetzt gelöscht!)
            localStorage.setItem('queue_notes', JSON.stringify(remainingQueue));
            
            // UI-Gesamtcache für den nächsten Reload sichern
            localStorage.setItem('cached_notes', JSON.stringify(this.notes));
        },

        // =========================================================================
        // BEREICH: KALENDER & PAPERLESS MANAGEMENT
        // =========================================================================
        async loadAppointmentsBackend() {
            if (!this.isOnline) return;
            const jResponse = await apiCall('get_appointments', null, 'GET');
            if (jResponse?.success) {
                this.appointments = jResponse.details.appointments || [];
                localStorage.setItem('cached_appointments', JSON.stringify(this.appointments));
            }
        },

        async findMatchSuggestions(searchQuery) {
            if (!this.isOnline) return;
            let clean = searchQuery.replace(/^(Re|Aw|Fwd):\s*/i, '').trim();
            try {
                // 🚀 NEU: Paperless-Suche über modulare API
                const jResponse = await apiCall('paperless_search', {query:clean}, 'GET');
                if (jResponse?.success) this.paperlessMatches = jResponse.details.documents || [];
            } catch (err) { console.error(err); }
        },

        async linkToPaperless(docId) {
            if (!this.isOnline || !confirmAction(`Mit Dokument #${docId} verknüpfen?`)) return;
            const fd = new FormData();
            fd.append('id', this.selectedHash);
            fd.append('paperless_id', docId);

            // 🚀 NEU: Matching über neue API-Struktur
            const jResponse = await apiCall('link_contact_item', fd, 'POST');
            if (jResponse?.success) {
                this.currentMail.is_dms = true;
                this.currentMail.paperless_id = docId;
            }
        },

        // =========================================================================
        // CORE GLOBAL UTILITIES
        // =========================================================================
        async switchView(viewName, targetHash = null) {
            this.currentView = viewName;
            this.showMobilePreview = false;

            if (targetHash) {
                this.currentHash = targetHash;
            }

            let newUrl = `${window.location.protocol}//${window.location.host}${window.location.pathname}?view=${viewName}`;
            if (this.currentHash) {
                newUrl += `&hash=${this.currentHash}`;
            }
            
            window.history.pushState({ path: newUrl }, '', newUrl);

            if (viewName.startsWith('detail_')) {
                const activeHash = targetHash || this.currentHash;
                
                if (activeHash) {
                    this.dmsLoading = true;
                    // Stelle sicher, dass checkCRMStatus() auch window.CRM_API_URL nutzt!
                    if (typeof this.checkCRMStatus === 'function') {
                        await this.checkCRMStatus(activeHash);
                    }
                    this.dmsLoading = false;
                }
            }
        },

        debounce(func, delay = 300) {
            let timeoutId;
            return function (...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        },

        evaluateTimeBasedTheme() {
            const hour = new Date().getHours();
            this.isDarkMode = false;//(hour < 7 || hour >= 19);
            //document.documentElement.classList.toggle('dark', this.isDarkMode);
        },

        toggleTheme() {
            this.isDarkMode = !this.isDarkMode;
            //document.documentElement.classList.toggle('dark', this.isDarkMode);
        },

        initPullToRefresh() {
            let touchStart_Y = 0;
            window.addEventListener('touchstart', (e) => {
                if (window.scrollY === 0) touchStart_Y = e.touches[0].clientY;
            }, { passive: true });

            window.addEventListener('touchmove', (e) => {
                const diff = e.touches[0].clientY - touchStart_Y;
                if (window.scrollY === 0 && diff > 160) {
                    if (navigator.vibrate) navigator.vibrate(15);
                    window.location.reload();
                }
            }, { passive: true });
        }
    };
};

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('./sw.js', { updateViaCache: 'none' })
        .then(registration => {
            console.log('ServiceWorker registriert:', registration.scope);
            registration.update();

            registration.onupdatefound = () => {
                const installingWorker = registration.installing;
                if (!installingWorker) return;
                
                installingWorker.onstatechange = () => {
                    if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        window.updateAvailable = true;
                        showNotify("Update bereit", "Eine neue Version ist installiert. <button onclick='performAppUpdate()'>Jetzt aktualisieren</button>", "info");
                    }
                };
            };
        })
        .catch(err => console.error('ServiceWorker Registrierungsfehler:', err));
    });
}

// Zentrale Funktion für den Update-Prozess (Global verfügbar)
window.performAppUpdate = function() {
    if ('caches' in window) {
        caches.keys().then(names => names.forEach(name => caches.delete(name)));
    }
    window.location.reload(true);
};