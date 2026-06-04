const contactsViewComponent = () => ({
    // State
    contacts: [],
    searchQueryContacts: '',
    
    emailQuery: '',
    emailResults: [],     // Gefundene E-Mails zum Kontakt
    loadingEmails: false,
    
    paperlessQuery: '',
    paperlessResults: [], // Ergebnisse von Paperless
    loadingPaperless: false,

    matchResults: [],
    loading: false,
    loadingMatch: false,
    isMatchPanelOpen: false,
    isEditing: false,
    activeContact: { displayname: '', type: 'person', emails: [] },
    simulation: null,

    async initContacts() {

        const urlParams = new URLSearchParams(window.location.search);
        const hashValue = urlParams.get('hash'); //mail_hash
        
        //Wenn ein Hash mitkommt suche den Kontakt dirket!
        if (hashValue) {
            const data = await apiCall('get_emails_by_hash', {hash:hashValue},'GET');

            // 1. Wert setzen
            this.searchQueryContacts = data?.details?.contacts?.[0]?.displayname || '';
            
            this.loadContacts(); 
            
            // Optional: Falls performMatchSearch auch automatisch laufen soll
            this.$nextTick(() => {
                //this.performMatchSearch();
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
    async loadContacts() 
    {
        this.loading = true;
        try {
            const data = await apiCall(
                'list_contacts',
                {query: this.searchQueryContacts},
                'GET'
            );
            //const data = await apiCall('list_contacts', {query:this.searchQueryContacts}, 'POST');
            this.contacts = data.details?.contacts || [];
            this.drawNetwork(this.contacts);
        } finally {
            this.loading = false;
        }
    },

    async saveContact(contact = this.activeContact) {
        try {
            const data = await apiCall('save_contact', contact, 'POST');
            if (data.success) {
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
        const data = await apiCall('get_emails_by_contact', {query:id},'GET');
        if (data?.success) {
            this.activeContact.emails = data.details.contacts || [];
            this.matchResults = [...this.activeContact.emails];
        }
        this.loadingMatch = false;
    },

    async saveContact() 
    {
       try {
            
            const data = await apiCall('save_contact', this.activeContact, 'POST');

            if (data.success) {
                console.log('Kontakt gespeichert:', data.message);
                // Optional: Hier das UI aktualisieren, falls der Server bereinigte Daten schickt
            } else {
                alert('Fehler: ' + (data.error || 'Unbekannt'));
            }
        } catch (err) {
            console.error('Speicherfehler:', err);
        }

    },

    /**
     * Paperless-Verknüpfung
     */
    async linkToMatch(contactId, itemId) 
    {
        const data = await apiCall('link_contact_item', { contactId: contactId, correspondentId: itemId }, 'POST');
        
        if (data?.success) {
            console.log("YEAH, Kontakt verknüpft!");
        }
        
        // UI aktualisieren: Panel schließen
        //this.closeMatchModal();
    },

    /**
     * E-Mail Suche für das Matching
     * @returns 
     */
    // --- E-MAIL: Interne E-Mail-Adressen Suche/Liste ---
    async fetchEmailsForContact(contactId = this.activeContact?.id) 
    {
        if (!contactId) return;
        this.loadingEmails = true;

        try {
            // 1. Entscheide nur noch über Action und Parameter
            const isSearch = this.emailQuery.length >= 2;
            const action = isSearch ? 'get_emails_by_search' : 'get_emails_by_contact';
            const params = isSearch ? { query: this.emailQuery } : { query: contactId };
            const method = isSearch ? 'POST' : 'GET';

            // 2. Ein sauberer Aufruf
            const data = await apiCall(action, params, method);

            // 3. Ergebnis verarbeiten
            if (data?.success) {
                this.activeContact.emails = data.details?.contact_emails || [];
            }
        } finally {
            this.loadingEmails = false;
        }
    },

    //Paperless-Suche
    async performPaperlessSearch() 
    {
        if (this.paperlessQuery.length < 2) {
            this.paperlessResults = [];
            return;
        }

        this.loadingPaperless = true;
        try {
            // Der Aufruf reduziert sich auf die fachliche Logik:
            // Action, Daten-Objekt und Methode
            const data = await apiCall('get_correspondets', { query: this.paperlessQuery }, 'POST');
            
            this.paperlessResults = data.details?.correspondents || [];
        } catch (e) {
            console.error("Paperless Suche fehlgeschlagen", e);
        } finally {
            this.loadingPaperless = false;
        }
    },

     async performMatchSearch() {
        if (this.emailQuery.length < 2) {
            // Zeige nur die bereits verknüpften E-Mails an
            this.matchResults = [...this.activeContact.emails];
            return;
        }
        this.loadingMatch = true;
        try {
            // 1. ABSICHERUNG: Wenn kein Kontakt offen ist, darf nichts passieren!
            if (!this.activeContact) return;

            if (this.emailQuery.length < 2) {
                this.matchResults = [...(this.activeContact.emails ?? [])];
                return;
            }

            this.loadingMatch = true;
        
            const data = await apiCall('get_emails_by_search', {query: this.emailQuery}, 'GET');
            
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