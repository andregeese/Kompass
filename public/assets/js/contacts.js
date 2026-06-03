const contactsViewComponent = () => ({
    // State
    contacts: [],
    searchQueryContacts: '',
    matchSearchQuery: '',
    matchResults: [],
    loading: false,
    loadingMatch: false,
    isMatchPanelOpen: false,
    isEditing: false,
    activeContact: { displayname: '', type: 'person', emails: [] },
    simulation: null,

    async initContacts() {

        const urlParams = new URLSearchParams(window.location.search);
        const hashValue = urlParams.get('id');

        if (hashValue) {
            const res = await safeFetch('api.php?action=get_emails_by_contact', {
                method: 'POST',
                body: JSON.stringify({query: hashValue})
            });

            // 1. Wert setzen
            this.searchQueryContacts = res?.details?.contacts?.[0]?.displayname || '';
            
            // 2. WICHTIG: Die Suche sofort explizit triggern!
            // Da du ein Debounce (setTimeout) im Watcher hast, 
            // rufst du hier am besten direkt die Ladefunktion auf.
            this.loadContacts(); 
            
            // Optional: Falls performMatchSearch auch automatisch laufen soll
            this.$nextTick(() => {
                this.performMatchSearch();
            });
        }
        
        // Bestehender Watcher bleibt wie er ist
        this.$watch('searchQueryContacts', () => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => this.loadContacts(), 300);
        });
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

    // --- Kontakt-Logik ---
    async loadContacts() {
        this.loading = true;
        try {
            const data = await safeFetch(`api.php?action=list_contacts&query=${encodeURIComponent(this.searchQueryContacts)}`);
            this.contacts = data.details?.contacts || [];
            this.drawNetwork(this.contacts);
        } finally {
            this.loading = false;
        }
    },

    async saveContact(contact = this.activeContact) {
        try {
            const res = await safeFetch('api.php?action=save_contact', {
                method: 'POST',
                body: JSON.stringify(contact)
            });
            if (res.success) {
                Object.assign(contact, res.data || contact);
                this.closeMatchModal();
            }
        } catch (e) {
            console.error("Speichern fehlgeschlagen", e);
        }
    },

    // --- Modal & Verknüpfungs-Logik ---
    openMatchModal(contact) {
        const targetContact = contact;

        this.activeContact = targetContact;
        this.isMatchPanelOpen = true;
        // Zuerst die Mails vom Server laden
        this.fetchEmailsForContact(targetContact.id).then(() => {
            console.log(this.activeContact.emails);
            // Erst wenn geladen, matchResults mit den aktiven befüllen
            if (this.activeContact && this.activeContact.id === targetContact.id) {
                this.matchResults = [...(this.activeContact.emails ?? [])];
            }
        });
    },

    closeMatchModal() {
        this.isMatchPanelOpen = false;
        this.activeContact = null;
    },

    async fetchEmailsForContact(id) {
        this.loadingMatch = true;
        const data = await safeFetch(`${this.apiUrl}?action=get_emails_by_contact&query=${id}`);
        if (data?.success) {
            this.activeContact.emails = data.details.contacts || [];
            this.matchResults = [...this.activeContact.emails];
        }
        this.loadingMatch = false;
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

            if (response.success) {
                console.log('Kontakt gespeichert:', resuresponset.message);
                // Optional: Hier das UI aktualisieren, falls der Server bereinigte Daten schickt
            } else {
                alert('Fehler: ' + (response.error || 'Unbekannt'));
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
            // 1. ABSICHERUNG: Wenn kein Kontakt offen ist, darf nichts passieren!
            if (!this.activeContact) return;

            if (this.matchSearchQuery.length < 2) {
                this.matchResults = [...(this.activeContact.emails ?? [])];
                return;
            }

            this.loadingMatch = true;
        
            const data = await safeFetch(`${this.apiUrl}?action=get_emails_by_search&query=${encodeURIComponent(this.matchSearchQuery)}`);
            
            const rawResults = data.details?.contact_emails || [];
            const normalizedResults = rawResults.map(item => ({
                ceid: item.ceid,
                email: item.email
            }));

            // 2. Hier nutzen wir die sichere Abfrage, die du schon in Zeile 173 hattest
            const existingEmails = Array.isArray(this.activeContact.emails) ? this.activeContact.emails : [];
            const combined = [...existingEmails, ...normalizedResults];
            
            this.matchResults = Array.from(new Map(combined.map(item => [item.ceid, item])).values());
        } catch (err) {
            console.error("Suche fehlgeschlagen:", err);
        } finally {
            this.loadingMatch = false;
        }
    },

    // --- E-Mail UI Logik ---
    isEmailLinked(ceid) {
        return this.activeContact.emails.some(e => Number(e.ceid) === Number(ceid));
    },

    toggleEmail(emailObj) {
        if (this.isEmailLinked(emailObj.ceid)) {
            this.activeContact.emails = this.activeContact.emails.filter(e => Number(e.ceid) !== Number(emailObj.ceid));
        } else {
            this.activeContact.emails.push(emailObj);
        }
    },

    // --- D3 Netzwerk-Logik ---
    drawNetwork(contacts) {
        if (this.simulation) this.simulation.stop();
        const svg = d3.select("#network");
        svg.selectAll("*").remove();

        // Transformation
        const nodes = [], links = [];
        contacts.forEach(c => {
            nodes.push({ 
                id: 'c' + c.id, 
                label: c.displayname, 
                type: 'contact',
                is_active: c.is_active
            });
            c.emails?.forEach(e => {
                nodes.push({ 
                    id: 'e' + e.ceid, 
                    label: c.id + ": " + e.email, 
                    type: 'email',
                    is_active: c.is_active
                });
                links.push({ 
                    source: 'c' + c.id, target: 'e' + e.ceid 
                });
            });
        });

        // Simulation
        const width = svg.node().parentElement.clientWidth;
        this.simulation = d3.forceSimulation(nodes)
            .force("link", d3.forceLink(links).id(d => d.id).distance(80))
            .force("charge", d3.forceManyBody().strength(-250))
            .force("center", d3.forceCenter(width / 2, 200));

        // Zeichnen (G-Container)
        const g = svg.append("g");
        svg.call(d3.zoom().on("zoom", (e) => g.attr("transform", e.transform)));

        const link = g.selectAll("line").data(links).enter().append("line").attr("stroke", "#999");
        const node = g.selectAll("circle").data(nodes).enter().append("circle").attr("r", 8)
            .style("fill", d => d.is_active == 0 ? '#ef4444' : (d.type === 'contact' ? '#2563eb' : '#10b981'))
            .on("click", (event, d) => {
                let foundContact = null;

                if (d.type === 'contact') {
                    // Fall 1: Du hast auf den blauen Kontakt-Kreis geklickt
                    foundContact = contacts.find(c => ('c' + c.id) === d.id);
                } else if (d.type === 'email') {
                    // Fall 2: Du hast auf eine E-Mail geklickt
                    // Wir müssen den Kontakt finden, zu dem diese E-Mail gehört
                    foundContact = contacts.find(c => c.emails?.some(e => ('e' + e.ceid) === d.id));
                }

                if (foundContact) {
                    this.openMatchModal(foundContact);
                } else {
                    console.warn("Kein Kontakt zu diesem Knoten gefunden:", d.id);
                }
            });
        
        // HIER DIE LABELS:
        const label = g.selectAll("text").data(nodes).enter().append("text")
            .text(d => d.label)
            .attr("font-size", "10px")
            .attr("dx", 12)
            .attr("dy", 4)
            .attr("fill", "#334155");

        // 4. Tick-Update mit Labels
        this.simulation.on("tick", () => {
            link.attr("x1", d => d.source.x).attr("y1", d => d.source.y)
                .attr("x2", d => d.target.x).attr("y2", d => d.target.y);
            
            node.attr("cx", d => d.x).attr("cy", d => d.y);
            
            // Labels an die Position der Knoten binden
            label.attr("x", d => d.x).attr("y", d => d.y);
        });
    }
});

// Sicherheits-Registrierung am Ende von mail.js
if (window.Alpine) {
    // Falls Alpine bereits voll geladen ist
    window.Alpine.data('contactsView', contactsViewComponent);
} else {
    // Falls Alpine noch auf den Start wartet
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('contactsView', contactsViewComponent);
    });
}