/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

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
    paperlessDMSMatches: [],
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
    paperlessContacts: [],

    async initSidebar() {
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
                    this.searchDMS(); // Dokumentensuche                  
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
        
            
        // Automatische Vorauswahl, wenn der CRM-Kontakt bereits eine Zuweisung hat
        if (this.contactState?.contact?.paperless_id) {
            this.selectedPaperlessId = this.contactState.contact.paperless_id;
        }
    },

    // 🛠️ Hilfsfunktion: Füllt alle Sidebar-Felder radikal aus dem Mail-Objekt vor
    fillSidebarWithMailData(mail) {
        this.manualSearchQuery = mail.subject || '';
        
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
        //this.searchDMS();
        //this.loadPaperlessTree(mail);
        this.getTimelineofContact();

        if (mail.id) {
            this.checkCRMStatus(mail.id);
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
        
        console.log(mail);
        if (!mail || !mail.id) return;
        
        try {
            const params = { id: mail.id };
            const data = await apiCall('get_contact_timeline', params, 'GET');
            
            if (data && data?.success && data?.details) {
                this.timelineData = data.details.timeline;
                
            } else {
                this.timelineData = [];
            }
        } catch (err) {
            console.error("Fehler beim Laden der Timeline:", err);
        }
    },


    // 🚀 DMS VERLAUF (ACTION: paperless_mail_tree -> details.tree)
    /**
     * @deprecated
     * @param {*} mail 
     */
    async loadPaperlessTree(mail) {
        const searchQuery = mail ? (mail.subject || '') : '';
        const mailHash = mail ? (mail.id || '') : '';
        try {
            const data = await apiCall('paperless_mail_tree', {query:searchQuery, hash:mailHash}, 'GET');
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
        console.log("SEARCH DMS:" + this.manualSearchQuery);
        if (!this.manualSearchQuery.trim() || this.manualSearchQuery=="" ) {
            console.log("Reset manualSearch");
            this.paperlessDMSMatches = [];
            return;
        }
        
        this.paperlessLoading = true;
        try {
            const data = await apiCall('paperless_search', {query:this.manualSearchQuery}, 'GET');
            
            if (data?.success && data.details?.documents) {
                console.log("SEARCH DMS RESPONSE");
                console.log(data);
                this.paperlessDMSMatches = data.details.documents;
            } else {
                this.paperlessDMSMatches = [];
            }
        } catch (err) {
            console.error("DMS-Suche fehlgeschlagen:", err);
            this.paperlessDMSMatches = [];
        } finally {
            this.paperlessLoading = false;
        }
    },
    async linkToPaperless(documentId) {
        const mail = this.$data.currentMail;
        if (!mail || !mail.id) return;
        try {
            const data = apiCall('link_mail_to_paperless', { id: mail.id, paperless_id: documentId }, 'POST')
            
            if (data?.success) {
                this.$data.currentMail.paperless_id = documentId;
                //showNotify('Zuordnung erfolgreich', 'E-Mail verknüpft.', 'success');
                this.loadPaperlessTree(mail);
            }
        } catch (err) {
            console.error("Fehler beim Matchen:", err);
        }
    },
    async unlinkFromPaperless() {
        const mail = this.$data.currentMail;
        if (!mail || !mail.id) return;
        if (!confirm('Möchtest du die Verknüpfung wirklich aufheben?')) return;
        try {
            const url = `api.php?action=unlink_mail_from_paperless`;
            const data = await apiCall('unlink_mail_from_paperless', { id: mail.id }, 'POST');
            if (data?.success) {
                this.$data.currentMail.paperless_id = null;
                this.manualSearchQuery = mail.subject || '';
                this.searchDMS();
                this.loadPaperlessTree(mail);
                //showNotify('Verbindung gelöst', 'Die Zuordnung wurde aufgehoben.', 'info');
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
    async searchPaperlessContacts(searchTerm) {
        // Wenn das Feld leer ist, müssen wir nicht suchen
        if (!searchTerm || searchTerm.trim().length < 2) {
            return; 
        }
        try {
            // Ersetze 'query' durch den Parameter, den deine api.php erwartet
            const data = await apiCall('get_correspondets', {force_query:searchTerm}, 'GET');
            
            if (data && data.success === true) {
                this.paperlessContacts = []; // Erst leeren
                this.$nextTick(() => {       // Warten bis DOM verarbeitet wurde
                    this.paperlessContacts = data.details.contacts;
                });
                // ID-Zuordnung: Automatisches Finden beim Tippen
                const match = this.paperlessContacts.find(c => 
                    c.name?.toLowerCase() === searchTerm.toLowerCase()
                );
                
                if (match) {
                    this.selectedPaperlessId = match.id;
                    if (match.email) this.formData.email = match.email;
                } else {
                    this.selectedPaperlessId = '';
                }
            }
        } catch (error) {
            console.error('Fehler bei der Live-Suche:', error);
        }
    },
    updateFormFromPaperless() {
        // Wenn im Dropdown ein Kontakt gewählt wird, befüllen wir das Formular automatisch
        const selected = this.paperlessContacts.find(c => c.id == this.selectedPaperlessId);
        if (selected) {
            this.formData.name = selected.name;
            // Falls deine API auch E-Mails liefert:
            if (selected.email) this.formData.email = selected.email;
        }
    },
    /* Prüft, ob der Absender bereits im CRM existiert oder ein DMS-Vorschlag vorliegt
    @param hash = mail.id gehasht
    */
    async checkCRMStatus(id) 
    {
        console.log("checkCRMStatus");
        this.crmLoading = true;
        this.dmsLoading = true;
        try {
            // 1. Erst CRM Status prüfen, um an die Mailadresse zu kommen
            const data = await apiCall('search_contact', {id:id}, 'GET');
            
            if (data?.success && data?.details?.contact) {
                const c = data.details.contact[0];
                this.$data.contactState = {
                    success: true,
                    contact: {
                        id: c.contact_id,
                        display_name: c.display_name,
                        type: c.type,
                        // Macht aus dem kommagetrennten String ein echtes JS-Array für spätere Zwecke
                        emails: c.additional_emails ? c.additional_emails.split(', ') : []
                    }
                };  
                // 2. Formular-Daten befüllen, aber Formular ZU lassen (da Kontext existiert)
                this.formData.name = c.display_name;
                this.formData.email = c.additional_emails ? c.additional_emails.split(', ')[0] : '';
                this.formData.type = c.type;
                
                // Correspondents setzen (für dein Paperless-Matching)
                this.selectedCorrespondents = [c.display_name];
                
                // 🚪 Gefunden? Dann Formular standardmäßig ZU
                this.showManualForm = false;
             
            }else{
                // ❌ Nichts im CRM gefunden -> Fallback auf Mail-Daten & Formular AUFKLAPPEN
                this.$data.contactState = { success: false, contact: null };
                
                const mail = this.$data?.currentMail;
                this.formData.name = mail ? mail.sender_name : '';
                this.formData.email = mail ? mail.sender_email : '';
                this.formData.type = 'company';
                this.selectedCorrespondents = [];
                
                // 📖 Nichts gefunden? Dann Formular automatisch AUFKLAPPEN
                this.showManualForm = true;
            }
        } catch (err) {
            console.error("CRM-Abfrage fehlgeschlagen:", err);
        } finally {
            this.crmLoading = false;
            this.dmsLoading = false;
            //console.log(this.data?.contactState);
        }
    },
    // Wird abgefeuert, wenn man im Suchschlitz Enter drückt oder auf die Lupe klickt
    async triggerManualDMSSearch() {
        // 1. Suche nach Dokumenten (braucht KEINEN Mail-Hash, läuft immer!)
        if (this.manualSearchQuery.trim()) {
            this.searchDMS(); 
        }
    },
    // Erstellt den neuen CRM-Kontakt über die Sidebar
    async saveContact() {
        if (!this.formData.name) return alert('Bitte einen Namen eingeben.');
        this.crmLoading = true;
        try {
            const data = await apiCall('insert_contact', this.formData, 'POST');
            
            if (data.success) {
                const urlParams = new URLSearchParams(window.location.search);
                const hash = urlParams.get('hash') || this.$data.currentmail.id || '';
                if (hash) await this.checkCRMStatus(this.$data.currentmail.id);
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