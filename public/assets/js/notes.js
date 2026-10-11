/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

// public/assets/js/notes.js
const notesViewComponent = () => ({
    notes: [],
    loading: false,
    recentEmails: [],
    contactMails: [],
    loadingEmails: null,
    emailSearch: '',
    memoData: {},
    newNote: {
        content: '',
        uid: new URLSearchParams(window.location.search).get('uid') || '',
        folder: new URLSearchParams(window.location.search).get('folder') || '',
        category: 'general',
        ceid: '',
        contact_email: '',
        contact_name: ''
    },
    async init() {
        this.loadNotes();
        // 🎯 URL-Parameter direkt im JS abfangen
        const params = new URLSearchParams(window.location.search);
        console.log(params);
        // Prüfen, ob relevante Parameter für eine neue Notiz übergeben wurden
        if (params.has('id')) {
            console.log('📥 Verknüpfte Mail-Daten in URL erkannt. Befülle Formular...');
            let currentNote = await apiCall('get_mail_data_for_note', {id:params.get('id')}, 'GET');
            if (currentNote.success && currentNote.details?.mail){
                // Alpine-Zustand mit den URL-Werten füttern
                this.newNote.uid = currentNote.details.mail.uid || '';
                this.newNote.folder = currentNote.details.mail.imap_folder || '';
                
                this.newNote.contact_email = currentNote.details.mail.email || '';
                this.newNote.contact_name = currentNote.details.mail.display_name || '';

                this.emailSearch = currentNote.details.mail.email;

            }else{
                console.warn("Kein DS zur Mail gefunden!");
            }

            
            // Falls ein E-Mail-Hash mitgeliefert wurde, direkt den Verlauf laden
            const emailHash = params.get('id');
            if (emailHash) {
                await this.fetchMailHistory(emailHash);
            }

            // Textarea automatisch fokussieren
            this.$nextTick(() => this.$refs.quickNoteArea?.focus());

            // ✨ URL im Browser säubern (Aus ?view=notes&uid=... wird reines ?view=notes)
            // Verhindert Duplikate oder Fehler beim manuellen Neuladen der Seite
            const cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname + '?view=notes';
            window.history.pushState({}, '', cleanUrl);

        } else if (params.get('action') === 'create') {
            // Klassischer Fall ohne Parameter, nur Fokus
            this.$nextTick(() => this.$refs.quickNoteArea?.focus());
        }

        // LAUSCHER: Wenn das Gerät wieder online geht, sofort Sync triggern!
        window.addEventListener('online', () => {
            console.log('📶 Verbindung wieder da! Triggere Background-Sync...');
            this.syncOfflineNotes();
        });
    },
    editNote(note) {
        // 1. ID des Datensatzes setzen
        this.editingNoteId = note.id;
        // 2. Formular-Zustand befüllen
        // Wir nutzen hier die Felder, die aus deiner DB kommen
        this.newNote.content = note.content;
        this.newNote.category = note.category || 'general';
        this.newNote.uid = note.mail_uid || '';
        this.newNote.folder = note.mail_folder || '';
        this.newNote.ceid = note.ceid || '';
        this.newNote.contact_email = note.email || '';
        
        // 3. UI-Anpassung
        this.$refs.quickNoteArea?.focus();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        console.log("Notiz zur Bearbeitung geladen:", this.editingNoteId);
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
            console.log("Rohdaten vom Server:", jResponse?.details?.notes);
            // 1. Erstmal komplett leeren
            this.notes = [];
            if (jResponse?.details?.notes) {
                const rawNotesArray = Object.values(jResponse.details.notes);

                // Daten-Bereinigung: Filtert unvollständige Objekte heraus 
                // und stellt sicher, dass jedes Objekt ein echtes Inhaltsfeld hat
                const cleanServerNotes = rawNotesArray.filter(note => {
                    return note && typeof note === 'object' && (note.content !== undefined || note.created_at !== undefined);
                });
                const currentQueue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
                console.log(currentQueue, cleanServerNotes);
                
                // Zuweisung an Alpine
                this.notes = [...currentQueue, ...cleanServerNotes];
                
                console.log("Bereinigte Daten für Alpine:", this.notes);
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
    async toggleEmailSelection(email) 
    {
        // 1. Wenn bereits ausgewählt -> Abwählen (Toggle-Off)
        if (this.newNote.ceid === email.id) {
            this.newNote.ceid = '';
            this.newNote.contact_email = '';
            this.newNote.contact_name = '';
            this.newNote.uid = '';
            this.contactMails = [];
            return;
        }

        // 2. Zustand setzen (Das triggert das Einfärben und die UI-Anzeige!)
        this.newNote.ceid = email.id;
        this.newNote.contact_email = email.email;
        this.newNote.contact_name = email.display_name;
        this.newNote.uid = ''; // Vorherige Mail-ID-Auswahl leeren

        // 3. Verlaufs-Mails im Hintergrund nachladen
        try {
            const data = await apiCall('get_mails_by_mailadress', { hash: email.email_hash }, 'GET');
            this.contactMails = data?.success && data.details?.mails ? data.details.mails : [];
        } catch (err) {
            console.error("Mailsuche nach Kontakt fehlgeschlagen:", err);
            this.contactMails = [];
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
        console.log("Notiz-Speichervorgang gestartet..." + this.newNote.ceid );
        if (!this.newNote.content.trim()) return;
        // Basis-Datenobjekt aufbauen (mit temporärer ID für den Fall der Fälle)
        const memoData = {
            id: 'local_' + Date.now(),
            ceid: this.newNote.ceid || null,
            content: this.newNote.content,
            uid: this.newNote.uid || null,
            folder: this.newNote.folder || null,
            category: this.newNote.category,
            status: 'active',
            mail: this.newNote.contact_email || null,
            created_at: new Date().toISOString(),
            due_date: this.newNote.due_date ? new Date(this.newNote.due_date).toISOString() : null
        };
        // FALL 1: Gerät ist komplett offline -> Direkt in den Offline-Speicher & UI sichern
        if (!navigator.onLine) {
            console.warn('Keine Netzwerkverbindung! Sende direkt in den Offline-Speicher.');
            this.processOfflineFallback(memoData);
            return;
        }
        // FALL 2: Gerät ist online -> Erst Server-Versuch (Kein voreiliges Caching!)
        try {
            const response = await apiCall('save_note', {'memoData': memoData}, 'POST');
            
            if (response?.success) {
                // Server-Erfolg: Temporäre ID mit echter DB-ID überschreiben
                memoData.id = response.db_id;
                
                // Jetzt erst ins UI pushen und den regulären UI-Cache aktualisieren
                this.notes.unshift(memoData);
                localStorage.setItem('cached_notes', JSON.stringify(this.notes));
                
                this.resetForm();
                console.log('Notiz erfolgreich online gespeichert. ID:', response.db_id);
            } else {
                // Server antwortet mit Fehler (z.B. Validierung fehlgeschlagen) -> Lokal retten
                console.warn('Server meldete Fehler beim Speichern. Weiche auf lokalen Speicher aus.');
                this.processOfflineFallback(memoData);
            }
        } catch (err) {
            // Netzwerk-Timeout oder Server tot -> Fallback aktivieren
            console.error('API-Aufruf komplett fehlgeschlagen. Sichere offline:', err);
            this.processOfflineFallback(memoData);
        }
    },

    processOfflineFallback(memoData) {
        // In die UI-Liste schieben, damit die Notiz für den User sichtbar bleibt
        this.notes.unshift(memoData);
        localStorage.setItem('cached_notes', JSON.stringify(this.notes));
        
        // In die PWA-Warteschlange für den nächsten Online-Sync einreihen
        this.saveToLocalStorage(memoData);
        
        this.resetForm();
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
                // Tausche temporäre ID gegen echte DB-ID im UI aus
                const index = this.notes.findIndex(n => n.id === memo.id);
                if (index !== -1) {
                    this.notes[index].id = response.db_id;
                }
                
                // Aus der Offline-Sync-Warteschlange löschen
                let queue = JSON.parse(localStorage.getItem('queue_notes') || '[]');
                queue = queue.filter(item => item.id !== memo.id);
                localStorage.setItem('queue_notes', JSON.stringify(queue));
                
                // WICHTIG: Den UI-Cache nach dem ID-Tausch auf den aktuellen Stand bringen
                localStorage.setItem('cached_notes', JSON.stringify(this.notes));
                
                return true;
            }
        } catch (e) {
            console.error('Verbindungsfehler beim API-Sync im Hintergrund:', e);
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