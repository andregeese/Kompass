/**
 * Enterprise Hub - Kombinierte Core Engine & All-In-One PWA Controller
 * 100% Offline-Sicher, robustes Caching & nahtloses Infinite Loading
 */

window.crmNotifications = [];
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

// Hilfsfunktion für ausfallsichere API-Abfragen mit globalem API-Pfad und Session-Timeout Schutz
async function safeFetch(url, customOptions = {}) 
{
    const defaultOptions = {
        method: 'POST',
        credentials: 'include',
        // 'Content-Type' entfernen wir aus den Default-Headern
        headers: {} 
    };

    const finalOptions = { ...defaultOptions, ...customOptions };

    // Automatische Header-Logik:
    // Wenn der Body KEIN FormData ist, setzen wir JSON als Standard
    if (!(finalOptions.body instanceof FormData)) {
        finalOptions.headers['Content-Type'] = 'application/json';
        // Falls du JSON sendest, musst du es hier stringifyen
        if (typeof finalOptions.body === 'object') {
            finalOptions.body = JSON.stringify(finalOptions.body);
        }
    }

    try {
        const response = await fetch(url, finalOptions);

        // 1. HTTP-Status prüfen (Gehört HIERHER, solange response noch lebt)
        if (!response.ok) {
            throw new Error(`Server-Fehler: Status ${response.status}`);
        }

        // 2. JSON lesen
        const data = await response.json();

        // 3. Business-Logik prüfen
        if (data && data.success === false) {
            showNotify('Fehler', data.error || 'Unbekannter Fehler', 'danger');
            // Je nachdem, ob du das als Fehler werfen willst oder nur anzeigen:
            return data; 
        }

        return data;

    } catch (error) {
        console.error("Fehler beim Laden:", error);
        showNotify('Fehler: ' + error.message, 'danger');
        // Gib ein leeres Objekt oder null zurück, damit dein restlicher Code nicht abstürzt
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

            // Live-Überwachung des Netzwerkstatus
            window.addEventListener('online', () => { 
                this.isOnline = true; 
                this.syncOfflineNotes();
                this.loadNotesBackend();
                this.loadAppointmentsBackend();
            });
            window.addEventListener('offline', () => { 
                this.isOnline = false; 
            });

            // Theme- & Pull-To-Refresh Logik initialisieren
            this.evaluateTimeBasedTheme();
            this.initPullToRefresh();

            // Erste Datensätze aus Backend abholen, falls Online
            if (this.isOnline) {
                this.loadNotesBackend();
                this.loadAppointmentsBackend();
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
                    const data = await safeFetch(`${apiUrl}?action=search_contact&hash=${encodeURIComponent(trimmed)}`);
                    
                    if (data?.success) {
                        // Hier nimmst du die zurückgegebenen Treffer (die Liste mit dem herkunft-Flag) auf
                        this.results = data.details.contacts || data.details || [];
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
            const data = await safeFetch(`${apiUrl}?action=crm_get_notes`);
            if (data?.success) {
                this.notes = data.details.notes || [];
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
                const res = await safeFetch(`${apiUrl}?action=crm_save_note`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (res?.success && res.details?.db_id) payload.id = res.details.db_id;
            } else {
                let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
                queue.push(payload);
                localStorage.setItem('queue_notes', JSON.stringify(queue));
            }
            this.newNote.content = '';
        },

        async toggleNoteStatus(noteId) {
            const note = this.notes.find(n => n.id === noteId);
            if (!note) return;
            note.status = note.status === 'completed' ? 'pending' : 'completed';
            localStorage.setItem('cached_notes', JSON.stringify(this.notes));

            if (this.isOnline) {
                await safeFetch(`${apiUrl}?action=crm_toggle_note`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: noteId, status: note.status })
                });
            }
        },

        async syncOfflineNotes() {
            let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
            if (!queue.length || !this.isOnline) return;
            for (const item of queue) {
                await safeFetch(`${apiUrl}?action=crm_save_note`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(item)
                });
            }
            localStorage.removeItem('queue_notes');
            this.loadNotesBackend();
        },

        // =========================================================================
        // BEREICH: KALENDER & PAPERLESS MANAGEMENT
        // =========================================================================
        async loadAppointmentsBackend() {
            if (!this.isOnline) return;
            /*const data = await safeFetch(`${apiUrl}?action=get_appointments`);
            if (data?.success) {
                this.appointments = data.details.appointments || [];
                localStorage.setItem('cached_appointments', JSON.stringify(this.appointments));
            }*/
        },

        async findMatchSuggestions(searchQuery) {
            if (!this.isOnline) return;
            let clean = searchQuery.replace(/^(Re|Aw|Fwd):\s*/i, '').trim();
            try {
                // 🚀 NEU: Paperless-Suche über modulare API
                const data = await safeFetch(`${apiUrl}?action=paperless_search&query=${encodeURIComponent(clean)}`);
                if (data?.success) this.paperlessMatches = data.details.documents || [];
            } catch (err) { console.error(err); }
        },

        async linkToPaperless(docId) {
            if (!this.isOnline || !confirm(`Mit Dokument #${docId} verknüpfen?`)) return;
            const fd = new FormData();
            fd.append('id', this.selectedHash);
            fd.append('paperless_id', docId);

            // 🚀 NEU: Matching über neue API-Struktur
            const data = await safeFetch(`${apiUrl}?action=paperless_match`, { method: 'POST', body: fd });
            if (data?.success) {
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
            this.isDarkMode = (hour < 7 || hour >= 19);
            document.documentElement.classList.toggle('dark', this.isDarkMode);
        },

        toggleTheme() {
            this.isDarkMode = !this.isDarkMode;
            document.documentElement.classList.toggle('dark', this.isDarkMode);
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