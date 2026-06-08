// public/assets/js/notes.js
const notesViewComponent = () => ({
    notes: [],
    loading: false,
    recentEmails: [],
    contactMails: [],
    loadingEmails: null,
    emailSearch: '',

    newNote: {
        content: '',
        uid: new URLSearchParams(window.location.search).get('uid') || '',
        folder: new URLSearchParams(window.location.search).get('folder') || '',
        category: 'general',
        ceid: '',
        contact_email: '',
        contact_name: ''
    },

    init() {
        this.loadNotes();
        //this.getRecentMails();

        // Fokus setzen bei "?action=create"
        const params = new URLSearchParams(window.location.search);
        if (params.get('action') === 'create') {
            this.$nextTick(() => this.$refs.quickNoteArea?.focus());
        }

        // LAUSCHER: Wenn das Gerät wieder online geht, sofort Sync triggern!
        window.addEventListener('online', () => {
            console.log('📶 Verbindung wieder da! Triggere Background-Sync...');
            this.syncOfflineNotes();
        });
    },

    async loadNotes() {
        const localQueue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
        const cachedNotes = JSON.parse(localStorage.getItem('cached_notes') || '[]');
        
        // FALLBACK: Wenn offline, zeige sofort den letzten gecachten UI-Stand
        if (!navigator.onLine) {
            this.notes = cachedNotes;
            return;
        }

        // 1. SYNC-TRIGGER: Erst offene Memos wegarbeiten
        if (localQueue.length > 0) {
            await this.syncOfflineNotes();
        }

        // 2. SERVER-FETCH: Frische Daten holen
        try {
            const jResponse = await apiCall('get_notes', {}, 'GET');
            if (jResponse) {
                const currentQueue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
                this.notes = [...currentQueue, ...jResponse];
                localStorage.setItem('cached_notes', JSON.stringify(this.notes));
            }
        } catch (err) {
            console.error("Server-Fehler beim Laden, nutze Cache-Fallback:", err);
            this.notes = cachedNotes;
        }
    },

    async searchEmail() {
        if (!this.emailSearch.trim()) {
            this.recentEmails = [];
            return;
        }

        try {
            const data = await apiCall('search_mail_address', { query: this.emailSearch }, 'GET');
            this.recentEmails = data?.success && data.details?.mails ? data.details.mails : [];
        } catch (err) {
            console.error("DMS-Suche fehlgeschlagen:", err);
            this.recentEmails = [];
        }
    },

    async searchLastMails(ceid) {
        if (!ceid || !ceid.trim()) {
            this.contactMails = [];
            return;
        }

        try {
            const data = await apiCall('get_mails_by_mailadress', { query: ceid }, 'GET');
            this.contactMails = data?.success && data.details?.mails ? data.details.mails : [];
        } catch (err) {
            console.error("Mailsuche nach Kontakt fehlgeschlagen:", err);
            this.contactMails = []; // FIX: Korrekte Zuweisung (nicht mehr recentEmails)
        }
    },

    async toggleNoteStatus(noteId) {
        const note = this.notes.find(n => n.id === noteId);
        if (!note) return;

        note.status = note.status === 'completed' ? 'pending' : 'completed';
        localStorage.setItem('cached_notes', JSON.stringify(this.notes));

        if (navigator.onLine) { // FIX: Von isOnline auf navigator.onLine geändert
            await apiCall('toggle_note', { id: noteId, status: note.status }, 'GET');                
        }
    },

    async addNote() {
        if (!this.newNote.content.trim()) return;

        const memoData = {
            id: 'local_' + Date.now(),
            content: this.newNote.content,
            uid: this.newNote.uid || null,
            folder: this.newNote.folder || null,
            category: this.newNote.category,
            status: 'active',
            mail: this.newNote.contact_email || null,
            created_at: new Date().toISOString()
        };

        // OPTIMISTIC UI: Einmalig oben in die Liste schieben
        this.notes.unshift(memoData);
        localStorage.setItem('cached_notes', JSON.stringify(this.notes));

        const noteContentBackup = this.newNote.content; // Backup falls Online-Versuch komplett crasht
        this.newNote.content = ''; // UI sofort leeren für grandioses Speed-Gefühl

        if (!navigator.onLine) {
            console.warn('Funkloch! Schiebe in Offline-Warteschlange.');
            this.saveToLocalStorage(memoData);
        } else {
            const success = await this.sendNoteToServer(memoData); // FIX: Liefert echten Boolean
            if (success) {
                this.resetForm();
            } else {
                // Falls sendNoteToServer fehlschlug, hat es das Memo bereits via Catch im LocalStorage gesichert.
                // Wir stellen hier nur den Text wieder her, falls der User es korrigieren muss.
                this.newNote.content = noteContentBackup; 
            }
        }
    },

    async getRecentMails() {
        try {
            let response = await apiCall('get_recent_mails', {}, 'GET');
            this.recentEmails = response?.details?.mails || [];
        } catch (e) {
            console.error("Fehler beim Laden der letzten Memos:", e);
        }
    },

    saveToLocalStorage(memoData) {
        // FIX: Kein zweiter unshift mehr! Nur noch Queue-Handling.
        let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
        if (!queue.some(item => item.id === memoData.id)) {
            queue.push(memoData);
            localStorage.setItem('queue_notes', JSON.stringify(queue));
        }
    },

    async sendNoteToServer(memo) {
        try {
            const response = await apiCall('save_note', memo, 'POST');
            if (response?.success) {
                // Tausche temporäre ID gegen echte DB-ID aus
                const index = this.notes.findIndex(n => n.id === memo.id);
                if (index !== -1) this.notes[index].id = response.db_id;
                
                // Aus der Queue löschen, falls es aus einem nachträglichen Sync kam
                let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
                queue = queue.filter(item => item.id !== memo.id);
                localStorage.setItem('queue_notes', JSON.stringify(queue));
                
                return true;
            }
        } catch (e) {
            console.error('Verbindungsfehler beim API-Sync, sichere offline:', e);
            this.saveToLocalStorage(memo);
        }
        return false;
    },

    async syncOfflineNotes() {
        if (!navigator.onLine) return;
        let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
        if (queue.length === 0) return;

        console.log(`🔄 Verarbeite ${queue.length} Offline-Notizen...`);
        for (let memo of queue) {
            await this.sendNoteToServer(memo);
        }
    },

    resetForm() {
        this.newNote.content = '';
        this.newNote.uid = '';
        this.newNote.folder = '';
        this.newNote.ceid = '';
        this.newNote.contact_email = '';
        this.newNote.contact_name = '';
        this.contactMails = [];
        console.log('Formular zurückgesetzt.');
    }
});

document.addEventListener('alpine:init', () => {
    window.Alpine.data('notesView', notesViewComponent);
});