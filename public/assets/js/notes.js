// public/assets/js/notes.js
const notesViewComponent = () => ({
    notes: [],
    loading: false,
    newNote: {
        content: '',
        uid: new URLSearchParams(window.location.search).get('uid') || '',
        folder: new URLSearchParams(window.location.search).get('folder') || '',
        category: 'general'
    },

    init() {
        // 1. Bestehende Notizen laden
        this.loadNotes();

        // 2. Fokus setzen bei "?action=create"
        const params = new URLSearchParams(window.location.search);
        if (params.get('action') === 'create') {
            this.$nextTick(() => this.$refs.quickNoteArea?.focus());
        }

        // 3. LAUSCHER: Wenn das Gerät wieder online geht, sofort Sync triggern!
        window.addEventListener('online', () => {
            console.log('Verbindung wieder da! Triggere Background-Sync...');
            this.syncOfflineNotes();
        });
    },

    async loadNotes() {
        // Lokale Cache-Memos holen
        const localMemos = JSON.parse(localStorage.getItem('crm_offline_memos') || '[]');
        
        if (!navigator.onLine) {
            this.notes = localMemos;
            return;
        }

        try {
            const jResponse = await apiCall('get_notes',{},'GET');
            
            if (jResponse.success) {
                // Lokale (noch nicht gesyncte) Memos oben anheften, gefolgt von den Server-Notizen
                this.notes = [...localMemos, ...jResponse];
            }
        } catch (err) {
            this.notes = localMemos; // Fallback bei Server-Timeout
        }
    },

    async addNote() {
        if (!this.newNote.content.trim()) return;

        const memoData = {
            id: 'local_' + Date.now(), // Temporäre ID für das Alpine-Template
            content: this.newNote.content,
            uid: this.newNote.uid,
            folder: this.newNote.folder,
            category: this.newNote.category,
            status: 'active'
        };

        // Sofort ins UI rendern (optimistic UI update)
        this.notes.unshift(memoData);
        this.newNote.content = '';

        // URL säubern
        if (this.newNote.uid) {
            this.newNote.uid = '';
            this.newNote.folder = '';
            const cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname + '?view=notes';
            window.history.pushState({}, '', cleanUrl);
        }

        if (!navigator.onLine) {
            // --- OFFLINE MODUS ---
            console.warn('Funkloch erkannt! Speichere lokal im Browser-Cache.');
            this.saveToLocalStorage(memoData);
        } else {
            // --- ONLINE MODUS ---
            await this.sendNoteToServer(memoData);
        }
    },

    // Schreibt ein Memo in die Offline-Warteschlange
    saveToLocalStorage(memoData) {
        // 1. UI-UPDATE: Notiz sofort links in die sichtbare Liste schieben
        // Falls memoData noch keine ID hat, verpassen wir ihr eine temporäre lokale ID
        if (!memoData.id) {
            memoData.id = 'local_' + Date.now();
            memoData.status = 'pending';
        }
        
        // In AlpineJS-State unshiften, damit es sofort oben erscheint
        this.notes.unshift(memoData); 
        
        // Den UI-Gesamtcache für den nächsten Seitenaufruf ohne Netz sichern
        localStorage.setItem('cached_notes', JSON.stringify(this.notes));

        // 2. SYNC-QUEUE: Jetzt die Notiz in die geheime Warteschlange für das Backend legen
        let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
        
        // Sicherstellen, dass wir keine Duplikate in die Queue schieben
        const bereitsInQueue = queue.some(item => item.id === memoData.id);
        if (!bereitsInQueue) {
            queue.push(memoData);
            localStorage.setItem('queue_notes', JSON.stringify(queue));
        }

        console.log('Notiz erfolgreich für späteren Sync eingereiht:', memoData);
    },

    // Schickt eine einzelne Notiz an die PHP-API
    async sendNoteToServer(memo) {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await apiCall('save_note', {memo}, 'POST');
            
            const res = await response.json();
            if (res.success) {
                // Tausche die temporäre lokale ID ('local_...') gegen die echte DB-ID aus
                const index = this.notes.findIndex(n => n.id === memo.id);
                if (index !== -1) this.notes[index].id = res.db_id;
                return true;
            }
        } catch (e) {
            console.error('Verbindungsfehler beim API-Sync, schiebe in Cache', e);
            this.saveToLocalStorage(memo);
        }
        return false;
    }
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('notesView', notesViewComponent);
});