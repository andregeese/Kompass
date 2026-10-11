/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

window.crmNotifications = [];
const GLOBAL_API_URL = 'api.php';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';
/**
 * Modal Dialog statt confirm()
 * @param {} message 
 * @param {*} callback 
 */
function confirmAction(message, buttonsOrCallback) {
    window.dispatchEvent(new CustomEvent('confirm-modal', {
        detail: { 
            message: message,
            buttons: buttonsOrCallback 
        }
    }));
}

//Notify => erzeugt ein notify und eine Mitteilung
function showNotify(title, message, type = 'danger') {
    //debugger;
    //console.groupCollapsed(`🔔 //showNotify aufgerufen: "${title}" (${type})`);
    //console.log("Message:", message);
    //console.trace("Aufrufpfad (Stacktrace):"); // <-- DAS IST DER SCHLÜSSEL!
    //console.groupEnd();

    // 1. Notification zum globalen Array hinzufügen (für die Sidebar/Counter)
    const newNotification = {
        id: Date.now() + Math.random(),
        title,
        message,
        type,
        time: new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' })
    };
    
    if (!window.crmNotifications) window.crmNotifications = [];
    window.crmNotifications.push(newNotification);
    
    // Event feuern, damit AlpineJS reagiert
    document.dispatchEvent(new CustomEvent('notifications-updated', { detail: window.crmNotifications }));

    // 2. Toast-Container abgreifen oder dynamisch erstellen
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        
        // GEFIXT: Auf Mobilgeräten zentriert über dem Nav-Footer (bottom-20), auf dem Desktop unten rechts (md:...)
        container.className = 'fixed bottom-20 left-1/2 -translate-x-1/2 md:bottom-4 md:right-4 md:left-auto md:translate-x-0 z-[999999] space-y-2 max-w-sm w-full px-4 md:px-0';
        document.body.appendChild(container);
    }

    const colorMapping = {
        'danger': 'bg-red-500 dark:bg-red-600 text-white',
        'success': 'bg-emerald-500 dark:bg-emerald-600 text-white',
        'info': 'bg-blue-500 dark:bg-blue-600 text-white',
        'warning': 'bg-amber-500 text-slate-900'
    };
    const bgClass = colorMapping[type] || colorMapping['danger'];

    // Toast-Element erzeugen
    const toast = document.createElement('div');
    toast.className = `transform translate-y-4 opacity-0 transition-all duration-300 ease-out p-4 rounded-2xl shadow-xl flex items-start justify-between backdrop-blur-md border border-white/10 ${bgClass}`;
    toast.innerHTML = `
        <div class="flex-1 pr-3 text-xs">
            <div class="font-bold mb-0.5">${title}</div>
            <div class="opacity-90 leading-relaxed">${message}</div>
        </div>
        <button type="button" class="text-current opacity-60 hover:opacity-100 font-bold text-sm transition-opacity shrink-0 p-0.5" onclick="this.closest('#notification-container > div').remove()">✕</button>
    `;

    container.appendChild(toast);

    // Animation einleiten (Slide-In)
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
    });

    // Auto-Löschen nach 5 Sekunden
    setTimeout(() => {
        toast.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            toast.remove();
            // Falls der Container komplett leer ist, auch den Container löschen, damit er im DOM nicht stört
            if (container.children.length === 0) {
                container.remove();
            }
        }, 300);
    }, 5000);
};


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
    //showNotify("Sicherheit", "Sicherheitsfall: Token nicht gefunden!", "error");
    // 3. Nach 2 Sekunden Bedenkzeit hart zum Login weiterleiten
    setTimeout(() => {
        window.location.replace('index.php?view=login');
    }, 2000);
}
async function initApiConfig() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    if (!metaTag || !metaTag.content) {
        // Nicht eingeloggt / Keine Session -> Initialisierung überspringen
        return; 
    }
    const res = await apiCall('get_allowed_actions', {}, 'GET');
    if (res.success) ALLOWED_ACTIONS = res.data;
}

// Standardisierte Aufrufe
async function apiCall(action, params = {}, method = 'POST') 
{
    const publicActions = ['login', 'register', 'get_settings'];
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
    
    // KORREKTUR: Wenn params ein FormData-Objekt ist, DARF kein Content-Type gesetzt werden!
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
    console.log(`🚀 Action [${action}] aufgerufen. Kontext:`, context, triggerElement);  
    console.log(triggerElement)  // 1. Debug-Info für Entwickler
    if (typeof debug !== 'undefined' && debug) {
        console.log(`%c[Dispatcher] Triggered: ${action}`, "color: #3b82f6; font-weight: bold;", context);
    }
    debugger;
    // 2. Action-Registry
    const actions = {
        'mail-send_view':        (ctx) => window.loadMailByHash?.(ctx.id),
        'mail_view':        (ctx) => window.loadMailByHash?.(ctx.id),
        'note_add':         (ctx) => openNoteModal(ctx.uid, ctx.folder),
        'mail_archive':     (ctx) => archiveMail(ctx.uid, triggerElement),
        'contact_view':        (ctx) => window.loadMailByHash?.(ctx.id),
        
        'open_modal_notes': (ctx) => {
            const params = new URLSearchParams();
            params.set('view', 'notes');
            
            // Daten aus dem 'item'-Kontext extrahieren
            if (ctx.id) params.set('id', ctx.id);

            console.log(ctx);
            
            // Seite mit den neuen Parametern neu laden / aufrufen
            window.location.search = params.toString();
        },
        'open_modal':       (ctx) => {
            // Falls ein spezifisches Event genannt wird, nutzen wir das, sonst den alten Default
            const eventName = ctx.targetEvent || 'open-item-modal';
            // Wenn Daten gekapselt in .payload liegen, nimm die, sonst den ganzen Kontext
            const data = ctx.payload !== undefined ? ctx.payload : ctx;
            
            window.dispatchEvent(new CustomEvent(eventName, { detail: data }));
        },
        'mail_view': (ctx) => {
            const urlParams = new URLSearchParams(window.location.search);
            const isAlreadyInMailsView = urlParams.get('view') === 'mails';

            if (isAlreadyInMailsView) {
                // Alpine-Komponente direkt im DOM ansprechen, ohne die Seite neu zu laden
                const mailViewEl = document.querySelector('[x-data^="mailView"]');
                if (mailViewEl && mailViewEl.__x_data) {
                    // Über den Alpine-Schnittstellen-Proxy die Funktion aufrufen
                    mailViewEl.__x_data.openMailDirectlyByHash(ctx.hash || ctx.id);
                    return;
                }
            }

            // Fallback / Aufruf aus einer anderen View (z.B. Dashboard): Seite mit Query-Parametern ansteuern
            const params = new URLSearchParams();
            params.set('view', 'mails');
            params.set('id', ctx.hash || ctx.id);
            window.location.search = params.toString();
        },
        'mail-send_view': (ctx) => {
            const urlParams = new URLSearchParams(window.location.search);
            const isAlreadyInMailsView = urlParams.get('view') === 'mails';

            if (isAlreadyInMailsView) {
                // Alpine-Komponente direkt im DOM ansprechen, ohne die Seite neu zu laden
                const mailViewEl = document.querySelector('[x-data^="mailView"]');
                if (mailViewEl && mailViewEl.__x_data) {
                    // Über den Alpine-Schnittstellen-Proxy die Funktion aufrufen
                    mailViewEl.__x_data.openMailDirectlyByHash(ctx.hash || ctx.id);
                    return;
                }
            }

            // Fallback / Aufruf aus einer anderen View (z.B. Dashboard): Seite mit Query-Parametern ansteuern
            const params = new URLSearchParams();
            params.set('view', 'mails');
            params.set('id', ctx.hash || ctx.id);
            window.location.search = params.toString();
        },
        'contact_view': (ctx) => {
            const targetHash = ctx.hash || ctx.id;

            const urlParams = new URLSearchParams(window.location.search);
            const isAlreadyInMailsView = urlParams.get('view') === 'contacts';

            if (isAlreadyInMailsView) {
                // Alpine-Komponente direkt im DOM ansprechen, ohne die Seite neu zu laden
                const contactEl = document.querySelector('[x-data^="contactsView"]');
                if (contactEl && contactEl.__x_data) {
                    // Über den Alpine-Schnittstellen-Proxy die Funktion aufrufen
                    contactEl.__x_data.openMailDirectlyByHash(targetHash);
                    return;
                }
            }

            // Fallback / Aufruf aus einer anderen View (z.B. Dashboard): Seite mit Query-Parametern ansteuern
            const params = new URLSearchParams();
            params.set('view', 'contacts');
            params.set('hash', targetHash);
            window.location.search = params.toString();
        },
        'document_view': (ctx) => {
            const targetHash = ctx.hash || ctx.id;

            const urlParams = new URLSearchParams(window.location.search);
            const isAlreadyInMailsView = urlParams.get('view') === 'document';

            if (isAlreadyInMailsView) {
                // Alpine-Komponente direkt im DOM ansprechen, ohne die Seite neu zu laden
                const contactEl = document.querySelector('[x-data^="documentView"]');
                if (contactEl && contactEl.__x_data) {
                    // Über den Alpine-Schnittstellen-Proxy die Funktion aufrufen
                    contactEl.__x_data.openMailDirectlyByHash(targetHash);
                    return;
                }
            }

            // Fallback / Aufruf aus einer anderen View (z.B. Dashboard): Seite mit Query-Parametern ansteuern
            const params = new URLSearchParams();
            params.set('view', 'document');
            params.set('hash', targetHash);
            window.open(`${window.CRM_PAPERLESS}/documents/${params.get('hash')}/details`, '_blank');
        },
        'search_mails': (ctx) => {
            const targetHash = ctx.query;

            const urlParams = new URLSearchParams(window.location.search);
            const isAlreadyInMailsView = urlParams.get('view') === 'mails';

            if (isAlreadyInMailsView) {
                // Alpine-Komponente direkt im DOM ansprechen, ohne die Seite neu zu laden
                const contactEl = document.querySelector('[x-data^="mailView"]');
                if (contactEl && contactEl.__x_data) {
                    // Über den Alpine-Schnittstellen-Proxy die Funktion aufrufen
                    contactEl.__x_data.openMailDirectlyByHash(targetHash);
                    return;
                }
            }

            // Fallback / Aufruf aus einer anderen View (z.B. Dashboard): Seite mit Query-Parametern ansteuern
            const params = new URLSearchParams();
            params.set('view', 'mails');
            params.set('query', targetHash);
            params.set('folder', ctx.folder);
            window.location.search = params.toString();
        }
    };

    // 3. Ausführung mit Fehlerbehandlung
    if (actions[action]) {
        try {
            // Wir prüfen, ob context existiert, da dies oft vergessen wird
            if (!context && action !== 'open_modal') {
                console.warn(`[Dispatcher] Warnung: Aktion "${action}" wurde ohne Kontext/Daten aufgerufen.`);
            }
            actions[action](context);
        } catch (err) {
            console.error(`[Dispatcher] Fehler bei der Ausführung von "${action}":`, err);
        }
    } else {
        // Hier wird es kritisch: Die Aktion ist nicht definiert
        console.error(`[Dispatcher] Fehler: Definition für Action "${action}" fehlt komplett!`, {
            action,
            context,
            availableActions: Object.keys(actions)
        });
    }
};

async function openItemDetails(item) {
    // Falls es eine Mail ist, laden wir vielleicht erst den vollen Inhalt via API
    if (item.type === 'mail') {
        const res = await apiCall('get_mail_content', { id: item.id }, 'GET');
        if (res.success) {
            item.content = res.data.content;
        }
    }
    
    // Event feuern, das das Modal oben empfängt
    window.dispatchEvent(new CustomEvent('open-item-modal', { detail: item }));
};

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

        if (response.status === 403) {
            const data = await response.json().catch(() => ({})); // Versuch JSON zu lesen
            if (data.error === 'CSRF Token ungültig' || data.error?.includes('CSRF')) {
                showNotify("Session abgelaufen", "Die Seite wird neu geladen...", "warning");
                //triggerSecurityLockout();
                return { success: false, error: 'CSRF_EXPIRED' };
            }
        }

        if (!response.ok) {
            let json = null;
            try {
                json = await response.json(); // Versuche das JSON trotz Status 400 zu lesen
            } catch(e) {}

            // 💡 Prüfe, ob das Backend ein ui.notify im 400-Payload mitgeschickt hat
            if (json?.details?.ui?.notify) {
                showNotify(json.details.ui.title, json.details.ui.message, json.details.ui.type || 'danger');
                return json;
            }

            // Fallback: Standard-Fehlermeldung
            const errorMsg = json?.error || `Server-Fehler (${response.status})`;
            showNotify("FEHLER", errorMsg, "danger");
            return json || { success: false, error: errorMsg };
        }

        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            const raw = await response.text();
            throw new Error("Server-Fehler: Antwort ist kein JSON.");
        }
        // 💡 KORREKTUR: jResponse/data Variablen-Chaos beseitigt
        const data = await response.json();
        
        // 🚨 3. STRIKTE SCHEMA-PRÜFUNG (Hart abbrechen, wenn Format falsch ist!)
        // Jede gültige Antwort MUSS ein 'success'-boolean besitzen!
        if (typeof data.success !== 'boolean') {
            console.error("API Contract Violation! Erhaltenes Objekt:", data);
            throw new Error("API Fehler: Antwort entspricht nicht dem vorgegebenen Schema (Fehlendes 'success'-Feld).");
        }

        if (data && data.success === false) {
            console.error("API Error:", data.error);
            //showNotify("Fehler", data.error || "Ein unbekannter Fehler ist aufgetreten", "danger");
            return data;
        }
        if (data.details?.ui?.notify) {
            const ui = data.details.ui;
            showNotify(ui.title, ui.message, ui.type || 'info');
        }
        return data;
    } catch (error) {
        //showNotify("Systemfehler", error.message, "danger");
        return { success: false, error: error.message };
    }
}
window.crmEngine = function() {
    // Fallback falls window.CRM_API_URL in index.php fehlt
    const apiUrl = window.CRM_API_URL || 'api.php';

    return {
        SYNCFOLDER: [],
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
        currentFolder: '',
        page: 1,
        limit: 20,
        hasMore: true,
        selectedId: null,
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
            const view = urlParams.get('view');

            if (view === 'login' || !view) {
                this.currentView = 'login';
                return; 
            }
            
            try {
                let res = await apiCall('get_settings', {}, 'POST');
                // z.B. this.settings = res;
            } catch (err) {
                console.error('Fehler beim Laden der Settings:', err);
            }
            
            const res = await apiCall('get_imap_folder', {}, 'GET');
            this.SYNCFOLDER = res?.details?.imaps || [];
            this.registerForPush();

            // 🚀 HIER: Den ersten Eintrag als Standard setzen (mit Fallback auf 'INBOX')
            this.currentFolder = this.SYNCFOLDER.length > 0 ? this.SYNCFOLDER[0] : 'INBOX';


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
                    const jResponse = await apiCall('global_search', {query: trimmed}, 'GET');
                    
                    if (jResponse?.success) {
                        // Hier nimmst du die zurückgegebenen Treffer (die Liste mit dem herkunft-Flag) auf
                        this.results = jResponse.details.gSearch || jResponse.details || [];
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
                    const jResponse = await apiCall('save_note', {memoData:memo}, 'POST');
                    
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
        // Hilfsfunktion oben im Script oder der Klasse einfügen
        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },
        async registerForPush() {
            console.log("register for push");
            if (!('serviceWorker' in navigator && 'PushManager' in window)) return;
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') return;
            const registration = await navigator.serviceWorker.ready;
            
            // Hier den Public Key eintragen!
            const vapidPublicKey = 'BHPDfcBzsQ5-csRk7GePqcvO0QtZ7yejB-D2bRyNQbW7eilsVrPaVAUQuKtFreNog6SmD0FfmOTBoSw-XhKHW_I';
            const convertedVapidKey = this.urlBase64ToUint8Array(vapidPublicKey);
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: convertedVapidKey
            });
            console.log("send to Api");
            // Sende das Objekt an dein Backend
            await apiCall('save_push_subscription', subscription, 'POST');
            console.log("Push-Subscription registriert.");
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
                        //showNotify("Update bereit", "Eine neue Version ist installiert. <button onclick='performAppUpdate()'>Jetzt aktualisieren</button>", "info");
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