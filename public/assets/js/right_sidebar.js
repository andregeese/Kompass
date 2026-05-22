const rightSidebarComponent = () => ({
    isOpen: false,
    ollamaActive: true,
    haStatus: 'Verbunden',
    notifications: [],
    activeTab: 'context',

    showManualForm: false,

    manualSearchQuery: '',
    paperlessLoading: false,
    paperlessMatches: [],
    paperlessTree: {},

    //CRM
    contactState: null,
    crmLoading: false,
    documents: [],
    notes: [],
    appointments: [],
    contactState: null,
    formData: { 
        name: '', 
        email: '', 
        type: 'company' 
    },
    allCorrespondents: [],
    selectedCorrespondents: [],
    currentSearchQuery: '',
    timelineData: [],

    initSidebar() {

        console.log("Smart Context Hub erfolgreich initialisiert.");

        // ⚡ DIREKT-FILL BEIM START: Falls beim Öffnen der Sidebar schon eine Mail aktiv ist
        // Prüfe alle typischen Pfade, wo die mail.js die aktuelle Mail hält
        const initialMail = this.$data?.currentMail 
                         || window.mailComponent?.currentMail 
                         || (typeof currentMail !== 'undefined' ? currentMail : null);

        if (initialMail) {
            this.fillSidebarWithMailData(initialMail);
        }

        // Event-Listener für manuelle Sidebar-Requests (z.B. Button-Klicks)
        document.addEventListener('toggle-sidebar-request', (e) => {
            if (!this.isOpen) {
                this.isOpen = true;
            }
            this.activeTab = e.detail.tab || 'context';
            
            // Falls beim Event-Aufruf direkt Mail-Daten mitgegeben wurden
            if (e.detail.email || e.detail.hash) {
                this.formData.email = e.detail.email || '';
                if (e.detail.hash) {
                    this.checkCRMStatus(e.detail.hash);
                    this.getTimelineofContact(); //NICHT LÖSCHEN
                }
            }
        });

        // REAKTIVER WATCHER (Wenn die Mail gewechselt wird, während die Sidebar offen ist)
        this.$watch(() => this.$data.currentMail, (newMail) => {
            if (newMail) {
                this.fillSidebarWithMailData(newMail);
            } else {
                this.clearSidebarData();
            }
        });

        document.addEventListener('notifications-updated', (e) => {
            this.notifications = [...e.detail];
        });

        document.addEventListener('system:low_memory', () => {
            this.handleLowMemoryShutdown();
        });
    },

    // 🛠️ Hilfsfunktion: Füllt alle Sidebar-Felder radikal aus dem Mail-Objekt vor
    fillSidebarWithMailData(mail) {
        this.manualSearchQuery = mail.subject || '';
        

        // Das kommt direkt vom Mail-Sender (z.B. "VR-Bank Freudenberg-Niederfischbach eG <info@vr-bank-fn.de>")
        const rawSender = mail.sender || mail.from || ''; 
        
        let parsedName = '';
        let parsedEmail = '';

        // Regex sucht nach dem Muster: Text <inhalt>
        const match = rawSender.match(/^([^<]*)\s*<([^>]+)>/);

        if (match) {
            // match[1] ist alles VOR der Klammer, match[2] ist der Inhalt der Klammer
            parsedName = match[1].trim();
            parsedEmail = match[2].trim();
        } else {
            // Fallback: Wenn kein "<" gefunden wurde, ist es wohl nur eine reine Mailadresse
            parsedEmail = rawSender.trim();
            parsedName = parsedEmail.split('@')[0]; // Nimmt den Teil vor dem @ als Namens-Vorschlag
        }

        // Jetzt die bereinigten Werte in deine Sidebar-Felder jagen
        this.formData.email = parsedEmail;
        this.formData.name = parsedName;
        this.formData.type = 'company';

        // Starte DMS & CRM Abfragen im Hintergrund
        this.searchDMS();
        this.loadPaperlessTree(mail);
        this.getTimelineofContact();
        if (mail.hash) {
            this.checkCRMStatus(mail.hash);
        }
    },

    // 🛠️ Hilfsfunktion: Leert die Daten sauber
    clearSidebarData() {
        this.paperlessMatches = [];
        this.paperlessTree = {};
        this.formData = { name: '', email: '', type: 'company' };
        this.contactState = null;
    },

    // 🚀 NEW CHRONOLOGICAL TIMELINE TREE
    async getTimelineofContact() {
        // Zieht sich die Mail primär aus Alpine, nutzt initialMail als Fallback
        const mail = this.$data?.currentMail 
                  || window.mailComponent?.currentMail 
                  || (typeof currentMail !== 'undefined' ? currentMail : null);
                  
        if (!mail || !mail.hash) return;
        
        try {
            const url = `api.php?action=get_contact_timeline`;
            const data = await safeFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ hash: mail.hash })
            });

            //wichtig, reagiert auf timelineData!!
            if (data && data.success && data.details) {
                this.timelineData = data.details; 
                
                if (this.$data?.debug) {
                    console.log("Timeline erfolgreich geladen:", data.details);
                }
            } else {
                this.timelineData = [];
            }
        } catch (err) {
            console.error("Fehler beim Laden der Timeline:", err);
        }
    },

    // 🚀 DMS VERLAUF (ACTION: paperless_mail_tree -> details.tree)
    async loadPaperlessTree(mail) {
        const searchQuery = mail ? (mail.subject || '') : '';
        const mailHash = mail ? (mail.hash || '') : '';

        try {
            const url = `api.php?action=paperless_mail_tree&query=${encodeURIComponent(searchQuery)}&hash=${mailHash}`;
            const data = await safeFetch(url);

            if (data?.success && data.details?.tree) {
                this.paperlessTree = data.details.tree;
            } else {
                this.paperlessTree = {};
            }
        } catch (err) {
            console.error("DMS-Verlauf Tree konnte nicht geladen werden:", err);
            this.paperlessTree = {};
        }
    },

    // 🚀 DMS SUCHE (ACTION: paperless_search -> details.documents)
    async searchDMS() {
        if (!this.manualSearchQuery.trim()) {
            this.paperlessMatches = [];
            return;
        }
        this.paperlessLoading = true;
        try {
            const url = `api.php?action=paperless_search&query=${encodeURIComponent(this.manualSearchQuery)}`;
            const data = await safeFetch(url);
            
            if (data?.success && data.details?.documents) {
                this.paperlessMatches = data.details.documents;
            } else {
                this.paperlessMatches = [];
            }
        } catch (err) {
            console.error("DMS-Suche fehlgeschlagen:", err);
            this.paperlessMatches = [];
        } finally {
            this.paperlessLoading = false;
        }
    },

    async linkToPaperless(documentId) {
        const mail = this.$data.currentMail;
        if (!mail || !mail.hash) return;
        try {
            const url = `api.php?action=link_mail_to_paperless`;
            const data = await safeFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ hash: mail.hash, paperless_id: documentId })
            });

            if (data?.success) {
                this.$data.currentMail.paperless_id = documentId;
                showNotify('Zuordnung erfolgreich', 'E-Mail verknüpft.', 'success');
                this.loadPaperlessTree(mail);
            }
        } catch (err) {
            console.error("Fehler beim Matchen:", err);
        }
    },

    async unlinkFromPaperless() {
        const mail = this.$data.currentMail;
        if (!mail || !mail.hash) return;
        if (!confirm('Möchtest du die Verknüpfung wirklich aufheben?')) return;

        try {
            const url = `api.php?action=unlink_mail_from_paperless`;
            const data = await safeFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ hash: mail.hash })
            });

            if (data?.success) {
                this.$data.currentMail.paperless_id = null;
                this.manualSearchQuery = mail.subject || '';
                this.searchDMS();
                this.loadPaperlessTree(mail);
                showNotify('Verbindung gelöst', 'Die Zuordnung wurde aufgehoben.', 'info');
            }
        } catch (err) {
            console.error("Fehler beim Lösen:", err);
        }
    },

    toggleSidebar(tab = 'context') {
        if (!this.isOpen) {
            this.isOpen = true;
            this.activeTab = tab;
        } else if (this.isOpen && this.activeTab === tab) {
            this.closeAndReset();
        } else {
            this.activeTab = tab;
        }
    },

    closeAndReset() {
        // 1. Sidebar schließen & Tabs zurücksetzen
        this.isOpen = false;
        this.activeTab = 'context';
        this.showManualForm = false;

        // 2. Bestehende Such- und DMS-Inhalte leeren
        this.manualSearchQuery = '';
        this.paperlessMatches = [];
        this.paperlessTree = {};
        this.paperlessLoading = false;
        this.selectedCorrespondents = [];

        // 🚀 3. NEU: CRM-Kontext & Timeline komplett zurücksetzen
        this.contactState = { success: false, contact: null };
        this.timelineData = [];

        // 4. Formular-Felder für manuelle Erfassung leeren
        this.formData = {
            email: '',
            name: '',
            type: 'company'
        };
    },

    clearNotification(id) {
        this.notifications = this.notifications.filter(n => n.id !== id);
    },

    clearAllNotifications() {
        this.notifications = [];
    },

    handleLowMemoryShutdown() {
        if (this.ollamaActive) {
            this.ollamaActive = false;
            return;
        }
        if (this.haStatus === 'Verbunden') {
            this.haStatus = 'Offline (Gestoppt)';
        }
    },

    /* Prüft, ob der Absender bereits im CRM existiert oder ein DMS-Vorschlag vorliegt
    @param hash = mail.id gehasht
    */
    async checkCRMStatus(hash) 
    {
        this.crmLoading = true;
        this.dmsLoading = true;

        try {
            // 1. Erst CRM Status prüfen, um an die Mailadresse zu kommen
            const data = await safeFetch(`api.php?action=search_contact&hash=${hash}`);
            
            if (data && data.success) {
                this.contactState = {
                    success: true,
                    contact: {
                        id: data.details.contact.contact_id,
                        display_name: data.details.contact.display_name,
                        type: data.details.contact.type,
                        // Macht aus dem kommagetrennten String ein echtes JS-Array für spätere Zwecke
                        emails: data.details.contact.additional_emails 
                            ? data.details.contact.additional_emails.split(', ') 
                            : []
                    }
                };  

                // 2. Formular-Daten befüllen, aber Formular ZU lassen (da Kontext existiert)
                this.formData.name = data.details.contact.display_name;
                this.formData.email = data.details.contact.additional_emails ? c.additional_emails.split(', ')[0] : '';
                this.formData.type = data.details.contact.type;
                
                // Correspondents setzen (für dein Paperless-Matching)
                this.selectedCorrespondents = [c.display_name];
                
                // 🚪 Gefunden? Dann Formular standardmäßig ZU
                this.showManualForm = false;

            }else{
                // ❌ Nichts im CRM gefunden -> Fallback auf Mail-Daten & Formular AUFKLAPPEN
                this.contactState = { success: false, contact: null };
                
                const mail = this.$data?.currentMail;
                this.formData.name = mail ? mail.sender_name : '';
                this.formData.email = mail ? mail.sender_email : '';
                this.formData.type = 'company';
                this.selectedCorrespondents = [];
                
                // 📖 Nichts gefunden? Dann Formular automatisch AUFKLAPPEN
                this.showManualForm = true;
            }


            // 2. Default-Suche füttern: Wenn das manuelle Feld leer ist, nimm die Mailadresse aus dem CRM-State
            if (!this.manualSearchQuery) {
                this.manualSearchQuery = this.formData.email || this.contactState.email;
            }

            // 3. Erstmalige DMS-Suche ausführen
            await this.fetchDMSCorrespondents(hash, this.manualSearchQuery);
            
        } catch (err) {
            console.error("CRM-Abfrage fehlgeschlagen:", err);
        } finally {
            this.crmLoading = false;
            this.dmsLoading = false;
        }
    },
    // Hilfsfunktion für den eigentlichen API-Fetch an Paperless
    async fetchDMSCorrespondents(hash, queryTerm) {
        try {
            // Wir encoden den QueryTerm, falls der User Leerzeichen oder Sonderzeichen eingibt
            const response = await safeFetch(`api.php?action=get_paperless_correspondents&hash=${hash}&force_query=${encodeURIComponent(queryTerm)}`);
            const data = await response.json();
            
            // HIER DIE NEUE PRÜFUNG: Auf "status === 'success'" checken!
            if (data.status === 'success' && data.details && data.details.contacts) {
                // Die Kontakte direkt in das reaktive Alpine-Array pushen
                this.allCorrespondents = [...data.details.contacts];
            } else {
                this.allCorrespondents = [];
            }
        } catch (e) {
            console.error("Fehler beim Holen der DMS-Korrespondenten:", e);
        }
    },

    // Wird abgefeuert, wenn man im Suchschlitz Enter drückt oder auf die Lupe klickt
    async triggerManualDMSSearch() {
        // 1. Suche nach Dokumenten (braucht KEINEN Mail-Hash, läuft immer!)
        if (this.manualSearchQuery.trim()) {
            this.searchDMS(); 
        }

        // 2. Suche nach DMS-Korrespondenten (versucht einen Hash zu finden)
        const urlParams = new URLSearchParams(window.location.search);
        const hash = urlParams.get('hash') || this.$data.currentMail?.hash || '';
        
        // Wenn kein Hash da ist, leeren wir die Korrespondenten-Liste oder überspringen sie sauber
        if (!hash) {
            console.log("Autarker Modus: Korrespondenten-Suche übersprungen (kein Mail-Hash). Dokumenten-Suche lief.");
            return; 
        }
        
        if (!this.manualSearchQuery.trim()) return;
        
        this.dmsLoading = true;
        await this.fetchDMSCorrespondents(hash, this.manualSearchQuery);
        this.dmsLoading = false;
    },

    // Erstellt den neuen CRM-Kontakt über die Sidebar
    async saveContact() {
        if (!this.formData.name) return alert('Bitte einen Namen eingeben.');
        this.crmLoading = true;

        const bodyData = new FormData();
        bodyData.append('name', this.formData.name);
        bodyData.append('email', this.formData.email);
        bodyData.append('type', this.formData.type);

        try {
            const res = await safeFetch('api.php?action=insert_contact', { 
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: this.formData.name,
                    email: this.formData.email,
                    type: this.formData.type
                })
            });
            
            const json = await res.json();
            if (json.success) {
                const urlParams = new URLSearchParams(window.location.search);
                const hash = urlParams.get('hash') || this.$data.currentMail.hash || '';
                if (hash) await this.checkCRMStatus(hash);
            } else {
                alert(json.error);
            }
        } catch (err) {
            console.error("Fehler beim Speichern:", err);
        } finally {
            this.crmLoading = false;
        }
    },

    // Hebt relevante Suchbegriffe (z. B. aus dem Betreff) im Notiz-Inhalt gelb hervor
    highlightText(text) {
        const subject = this.$data.currentMail?.subject;
        if (!subject || !text) return text;

        // Nutzt die ersten Wörter des Betreffs als Keyword-Matching
        const keywords = subject.split(' ').filter(word => word.length > 4);
        if (keywords.length === 0) return text;

        let highlighted = text;
        keywords.forEach(word => {
            const cleanWord = word.replace(/[^a-zA-Z0-9]/g, '');
            if (cleanWord.length < 3) return;
            const regex = new RegExp(`(${cleanWord})`, 'gi');
            highlighted = highlighted.replace(regex, '<mark class="bg-amber-200 dark:bg-amber-500/40 text-slate-900 dark:text-white px-0.5 rounded">$1</mark>');
        });

        return highlighted;
    },

    // Formatiert Datumswerte für das UI
    formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
});