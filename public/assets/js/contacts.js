const contactsViewComponent = () => ({
    contacts: [],
    paperlessContacts: [],
    searchQueryContacts: '',
    loading: false,
    hasMore: true,
    page: 1,
    limit: 20,
    activeContact: null,
    isMatchPanelOpen: false,
    matchSearchQuery: '',
    matchResults: [],
    loadingMatch: false,
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

        //await this.loadContacts(false);
    },

    // FEHLENDER GETTER: Alpine braucht diesen, um das x-for im Template zu füllen
    get filteredContacts() {
        return this.contacts; 
    },

    get sortedMatchResults() {
        if (!Array.isArray(this.matchResults)) return [];
        const results = (this.matchResults && Array.isArray(this.matchResults)) ? this.matchResults : [];

        return [...results].sort((a, b) => {
            // Sicherer Vergleich
            const aId = Number(a.id);
            const bId = Number(b.id);
            const isASelected = this.activeContact?.emails?.some(e => Number(e.id) === aId) || false;
            const isBSelected = this.activeContact?.emails?.some(e => Number(e.id) === bId) || false;
            
            if (isASelected && !isBSelected) return -1;
            if (!isASelected && isBSelected) return 1;
            return 0;
        });
    },

    toggleEmail(contact) {
        if (!this.activeContact.emails) this.activeContact.emails = [];
        
        const contactId = Number(contact.ceid);
        const existingIndex = this.activeContact.emails.findIndex(e => Number(e.ceid) === contactId);
        
        if (existingIndex === -1) {
            this.activeContact.emails.push({ ceid: contactId, email: contact.email });
        } else {
            this.activeContact.emails.splice(existingIndex, 1);
        }
    },
    
    openMatchModal(contact) {
        this.activeContact = contact;
        this.isMatchPanelOpen = true;

        // Zuerst die Mails vom Server laden
        this.fetchEmailsForContact(contact.id).then(() => {
            // Erst wenn geladen, matchResults mit den aktiven befüllen
            this.matchResults = [...this.activeContact.emails];
        });
    },

    closeMatchModal() {
        this.isMatchPanelOpen = false;
        this.activeContact = null;
    },

    async fetchEmailsForContact(contactId) {
        this.loadingMatch = true;
        try {
            const data = await safeFetch(`${this.apiUrl}?action=get_emails_by_contact&query=${contactId}`);
            if (data?.success) {
                // Erst die bestehenden Mails für den Kontakt setzen
                this.activeContact.emails = data.details.contacts || [];
                // Dann die Suchergebnisse befüllen
                this.matchResults = data.details.contacts || []; 
            }
        } catch (e) {
            console.error("Fehler beim Laden:", e);
        } finally {
            this.loadingMatch = false;
        }
    },
    
    async saveContact() 
    {
       try {
            
            const response = await safeFetch('api.php?action=save_contact', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                // Hier schicken wir das ganze Objekt mit dem aktualisierten E-Mail-Array
                body: JSON.stringify(this.activeContact)
            });

            const result = await response.json();

            if (result.success) {
                console.log('Kontakt gespeichert:', result.message);
                // Optional: Hier das UI aktualisieren, falls der Server bereinigte Daten schickt
            } else {
                alert('Fehler: ' + (result.error || 'Unbekannt'));
            }
        } catch (err) {
            console.error('Speicherfehler:', err);
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

    /**
     * E-Mail Suche für das Matching
     * @returns 
     */
    async performMatchSearch() {
        if (this.matchSearchQuery.length < 2) {
            // Zeige nur die bereits verknüpften E-Mails an
            this.matchResults = [...this.activeContact.emails];
            return;
        }
        this.loadingMatch = true;
        try {
            const data = await safeFetch(`${this.apiUrl}?action=list_contacts&query=${this.matchSearchQuery}`);
            
            // Suche ausführen und die bereits ausgewählten beibehalten
            // Wir mischen die Suchergebnisse mit den bereits gewählten
            const searchResults = data.details?.contacts || [];
            
            // Mergen: Bestehende + Neue, dabei Duplikate vermeiden
            const combined = [...this.activeContact.emails, ...searchResults];
            this.matchResults = Array.from(new Map(combined.map(item => [item.ceid, item])).values());
            
        } catch (err) {
            console.error("Suche fehlgeschlagen:", err);
        } finally {
            this.loadingMatch = false;
        }
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
    },

    async saveInlineContact(contact) 
    {
        try {
            // Optionale Validierung vor dem Absenden
            if (!contact.displayname || contact.displayname.trim() === '') {
                alert('Bitte einen Namen angeben.');
                return;
            }

            // POST-Request an deine api.php mit dem Query-Parameter
            const response = await safeFetch('api.php?action=save_contact_manual', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // Falls du CSRF-Token oder andere Sicherheits-Header nutzt, hier einfügen:
                    // 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    id: contact.id,
                    displayname: contact.displayname,
                    email: contact.email,
                    street: contact.street,
                    zip: contact.zip,
                    city: contact.city
                })
            });

            // Falls der Server mit einem Fehler-Statuscode (z.B. 400 oder 500) antwortet
            if (!response.ok) {
                throw new Error(`Server-Fehler: ${response.status} ${response.statusText}`);
            }

            const result = await response.json();

            if (result.success) {
                // Optional: Eine kleine Erfolgsmeldung in der Konsole oder Toast-Nachricht
                console.log('Kontakt erfolgreich aktualisiert:', result.message);
                
                // Falls deine API die bereinigten/neuen Daten zurückgibt, 
                // kannst du sie hier direkt wieder in das Objekt schreiben:
                if (result.data) {
                    Object.assign(contact, result.data);
                }
            } else {
                // Falls die API 'success: false' liefert (z.B. Validierungsfehler in PHP)
                alert(`Fehler beim Speichern: ${result.error || 'Unbekannter Fehler'}`);
                
                // Rollback: Holt die alten Daten vom Server zurück, um das UI zu bereinigen
                if (typeof this.initContacts === 'function') {
                    this.initContacts();
                }
            }

        } catch (error) {
            console.error('Netzwerk- oder Verarbeitungsfehler:', error);
            alert('Verbindung zur API fehlgeschlagen. Die Änderungen konnten nicht gespeichert werden.');
            
            // Im Fehlerfall ebenfalls die Daten zurücksetzen
            if (typeof this.this.initContacts === 'function') {
                this.initContacts();
            }
        }
    }
});

// Registrierung
document.addEventListener('alpine:init', () => {
    Alpine.data('contactsView', contactsViewComponent);
});