const contactsViewComponent = () => ({
    contacts: [],
    searchQueryContacts: '',
    loading: false,
    hasMore: true,
    page: 1,
    limit: 20,
    activeContact: null,
    isMatchPanelOpen: false,
    matchSearchQuery: '',
    matchResults: [],

    async initContacts() {
        // Such-Watcher für Live-Suche
        this.$watch('searchQueryContacts', (val) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.page = 1;
                this.loadContacts(false);
            }, 300);
        });

        await this.loadContacts(false);
    },

    // FEHLENDER GETTER: Alpine braucht diesen, um das x-for im Template zu füllen
    get filteredContacts() {
        return this.contacts; 
    },
    
    openMatchModal(contact) {
        this.activeContact = contact;
        this.isMatchPanelOpen = true;

        //await this.fetchEmailsForContact(contact.id);
    },

    closeMatchModal() {
        this.isMatchPanelOpen = false;
        this.activeContact = null;
    },

    async fetchEmailsForContact(contactId) {
        // Neue API-Abfrage wie von dir gewünscht
        const data = await safeFetch(`${this.apiUrl}?action=search_emails_by_contact&id=${contactId}`);
        if (data?.success) {
            this.matchResults = data.details.emails || [];
        }
    },

    async linkToMatch(contactId, itemId) {
        // Finde das ausgewählte Ergebnis, um den Typ zu bestimmen
        const result = this.matchResults.find(r => r.id === itemId);
        if (!result) return;

        if (result.type === 'mail') {
            await this.linkToMail(contactId, itemId); // itemId ist hier der hash
        } else {
            await this.linkToPaperless(contactId, itemId);
        }
        
        // UI aktualisieren: Panel schließen
        this.closeMatchModal();
    },

    async performMatchSearch() {
        if (this.matchSearchQuery.length < 2) return;
        // Beispiel-API-Aufruf: Suche nach Dokumenten oder Mails für diesen Kontakt
        const data = await safeFetch(`${this.apiUrl}?action=get_correspondet&query=${encodeURIComponent(this.matchSearchQuery)}`);
        this.matchResults = data.details || [];
    },

    // Lädt die Kontaktliste (mit optionalem Suchbegriff)
    async loadContacts(append = false) {
        if (this.loading) return;
        this.loading = true;

        try {
            const query = encodeURIComponent(this.searchQueryContacts);
            const data = await safeFetch(`api.php?action=list_contacts&page=${this.page}&limit=${this.limit}&query=${query}`);

            if (data?.success && data.details?.contacts) {
                const newContacts = data.details.contacts;
                this.contacts = append ? [...this.contacts, ...newContacts] : newContacts;
                
                this.hasMore = newContacts.length === this.limit;
                if (this.hasMore) this.page++;
            }
        } catch (err) {
            console.error("Fehler beim Laden der Kontakte:", err);
        } finally {
            this.loading = false;
        }
    },

    // Verknüpft einen Kontakt mit einem Dokument (aus Paperless)
    async linkToPaperless(contactId, docId) {
        if (!confirm(`Kontakt mit Dokument #${docId} verknüpfen?`)) return;
        
        const fd = new FormData();
        fd.append('contact_id', contactId);
        fd.append('item_id', docId);
        fd.append('item_type', 'paperless');

        try {
            const data = await safeFetch('api.php?action=link_contact_item', { method: 'POST', body: fd });
            if (data?.success) {
                const contact = this.contacts.findIndex(c => c.id == contactId);
                if (contact) {
                    // SICHERHEITS-CHECK: Existiert linked_items schon?
                    if (!contact.linked_items) {
                        contact.linked_items = [];
                    }
                    
                    // Jetzt ist es garantiert ein Array und .push() funktioniert
                    contact.linked_items.push({ 
                        id: docId, 
                        type: 'paperless', 
                        title: 'Neues Dokument' 
                    });
                    
                    // Falls du auch die ID setzen willst:
                    contact.paperless_id = docId;
                    
                    showNotify("Erfolgreich verknüpft!", "Daten aktualisiern", "success");
                }
            }
        } catch (err) {
            console.error("Link-Fehler:", err);
        }
    },

    // Verknüpft einen Kontakt mit einer Mail
    async linkToMail(contactId, mailHash) {
        const fd = new FormData();
        fd.append('contact_id', contactId);
        fd.append('item_id', mailHash);
        fd.append('item_type', 'mail');

        await safeFetch('api.php?action=link_contact_item', { method: 'POST', body: fd });
        // Hier könnte man analog das UI aktualisieren
    }
});

// Registrierung
document.addEventListener('alpine:init', () => {
    Alpine.data('contactsView', contactsViewComponent);
});