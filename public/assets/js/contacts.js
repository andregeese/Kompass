/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

const contactsViewComponent = () => ({
    // State
    contacts: [],
    searchQueryContacts: '',
    
    emailQuery: '',
    emailResults: [],     // Gefundene E-Mails zum Kontakt
    loadingEmails: false,

    //Tags
    loadingTags: false,
    tagQuery: '',
    tagResults: [],
    
    paperlessQuery: '',
    paperlessResults: [], // Ergebnisse von Paperless
    loadingPaperless: false,
    matchResults: [],

    loading: false,
    loadingMatch: false,
    isMatchPanelOpen: false,
    isEditing: false,
    activeContact: { displayname: '', type: 'person', emails: [], linked_correspondents: [], tags: []},
    simulation: null,

    async initContacts() 
    {
        const urlParams = new URLSearchParams(window.location.search);
        const hashValue = urlParams.get('hash'); //id_hash
        
        //Wenn ein Hash mitkommt suche den Kontakt dirket!
        if (hashValue) {
            const data = await apiCall('get_contact_by_hash', {hash:hashValue},'GET');
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
            this.searchTimeout = setTimeout(() => this.loadContacts(), 400);
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
            const aId = a.id;
            const bId = b.id;
            const isASelected = this.activeContact?.emails?.some(e => e.id === aId) || false;
            const isBSelected = this.activeContact?.emails?.some(e => e.id === bId) || false;
            
            if (isASelected && !isBSelected) return -1;
            if (!isASelected && isBSelected) return 1;
            return 0;
        });
    },

    // --- Kontakt-Logik ---
    async loadContacts() 
    {
        this.loading = true;
        try {
            const data = await apiCall('list_contacts', {query: this.searchQueryContacts}, 'GET');
            //const data = await apiCall('list_contacts', {query:this.searchQueryContacts}, 'POST');
            this.contacts = data.details?.contacts || [];
            this.drawNetwork(this.contacts);
        } finally {
            this.loading = false;
        }
    },

    // NEU: Methode für den Button "Neuer Kontakt"
    openNewContactModal() {
        this.activeContact = { 
            id: null, 
            displayname: '', 
            type: 'person', 
            emails: [],
            tags: [],
            linked_correspondents: [],
            paperless_id: null,
            paperless_name: null 
        };
        this.paperlessQuery = '';
        this.paperlessResults = [];
        this.emailQuery = '';
        this.matchResults = [];
        this.tagQuery = '';
        this.tagResults = [];
        this.isMatchPanelOpen = true;
    },

    // --- Modal & Verknüpfungs-Logik ---
    openMatchModal(contact) {
        // Tiefe Kopie, damit Änderungen nicht sofort im Grid landen, bevor man speichert
        this.activeContact = JSON.parse(JSON.stringify(contact));
        
        // Initialisiere leere Arrays, falls vom Server nichts kam
        if (!this.activeContact.emails) this.activeContact.emails = [];
        if (!this.activeContact.linked_correspondents) this.activeContact.linked_correspondents = [];
        
        this.isMatchPanelOpen = true;

        // Wenn es ein bestehender Kontakt ist, lade seine E-Mails vom Server
        if (this.activeContact.id) {
            this.fetchEmailsForContact(this.activeContact.id);
            this.fetchCorresForContact(this.activeContact.id);
            this.fetchTagsForContact(this.activeContact.id);
        }
    },

    closeMatchModal() {
        this.isMatchPanelOpen = false;

        this.emailQuery = '';
        this.paperlessQuery = '';
        this.tagQuery = ''; // KORREKTUR: Reset

        this.matchResults = [];
        this.paperlessResults = [];
        this.tagResults = []; // KORREKTUR: Reset

        this.loadingMatch = false;
        this.loadingPaperless = false;
        this.loadingTags = false; // KORREKTUR: Reset

        this.activeContact = null;
    },

    // --- Paperless UI Logik ---
    isPaperlessLinked(corrId) {
        // Prüft, ob der Correspondent bereits in der Liste ist
        return this.activeContact.linked_correspondents.some(c => c.id === corrId);
    },
    
    async togglePaperless(corrObj) {
        const isLinked = this.isPaperlessLinked(corrObj.id);

        // 1. Lokale UI sofort aktualisieren mit Array-Kopie (erzwingt Alpine Re-Render)
        if (isLinked) {
            this.activeContact.linked_correspondents = this.activeContact.linked_correspondents.filter(
                c => c.id !== corrObj.id
            );
        } else {
            // Sicherstellen, dass das Objekt korrekt hinzugefügt wird
            this.activeContact.linked_correspondents = [
                ...this.activeContact.linked_correspondents, 
                { id: corrObj.id, name: corrObj.name }
            ];
        }

        // 2. API-Sync im Hintergrund
        try {
            await apiCall('update_paperless_to_contact', {
                corrId: corrObj.id, 
                contactId: this.activeContact.id, 
                action: isLinked ? 'unlink' : 'link'
            }, 'POST');
        } catch (e) {
            // Rollback bei Fehler: Wenn API fehlschlägt, UI wieder zurücksetzen
            console.error("API Sync fehlgeschlagen, mache UI-Änderung rückgängig");
            // (Optional: Hier Logik zum Wiederherstellen einbauen)
        }
    },
    
    //Paperless-Suche
    async performPaperlessSearch() {
        if (this.paperlessQuery.length < 2) {
            // Bei leerer Suche zeigen wir nur die schon verknüpften an
            this.paperlessResults = [...this.activeContact.linked_correspondents];
            return;
        }
        
        this.loadingPaperless = true;
        try {
            const data = await apiCall('search_correspondents', { query: this.paperlessQuery }, 'GET');
            
            if (data?.success && data.details?.correspondents) {
                // FUSION: Wir nehmen die neuen Ergebnisse...
                const newResults = data.details.correspondents;
                
                // ...und stellen sicher, dass die bereits verknüpften in der Liste bleiben 
                // (falls sie durch die Suche nicht gefunden wurden, aber wir sie behalten wollen)
                const map = new Map();
                
                // Erst die verknüpften in die Map
                this.activeContact.linked_correspondents.forEach(item => { map.set(item.id), item});
                
                // Dann die neuen Ergebnisse drüber (oder dazu)
                newResults.forEach(item => map.set(item.id, item));
                
                // Zurück in das Array
                this.paperlessResults = Array.from(map.values());
            }
        } catch (e) {
            console.error("Fehler", e);
        } finally {
            this.loadingPaperless = false;
        }
    },

    async fetchPaperlessNameById() {
        const data = await apiCall('get_correspondet', {c:this.activeContact.id}, 'GET');
        console.log(data);
    },

    async fetchCorresForContact(id) {
        //this.loadingMatch = true;
        const data = await apiCall('get_correspondent_by_contact', {cid:id},'GET');
        if (data?.success) {
            this.activeContact.linked_correspondents = data.details.activeCorres || [];
            this.paperlessResults = [...this.activeContact.linked_correspondents];
        }
        //this.loadingMatch = false;
    },

    async saveContact() 
    {
        const data = await apiCall('save_contact', {data:this.activeContact}, 'POST');
        if (data.success) {
            console.log('Kontakt gespeichert:', data.message);
            this.closeMatchModal();
            // Optional: Hier das UI aktualisieren, falls der Server bereinigte Daten schickt
        } else {
            //showNotify(data);
        }
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
            const params = isSearch ? { query: this.emailQuery } : { hash: contactId };
            const method = isSearch ? 'POST' : 'GET';
            // 2. Ein sauberer Aufruf
            const data = await apiCall(action, params, method);

            // 3. Ergebnis verarbeiten
            if (data?.success) {
                if (!isSearch) {
                    // KORREKTUR: Greift auf das verschachtelte Array aus deinem Screenshot zu
                    this.activeContact.emails = data.details?.contacts?.[0]?.emails || [];
                    // Synchronisiere auch die Match-Suchergebnisse für die Anzeige
                    this.matchResults = [...this.activeContact.emails];
                } else {
                    // Für die Suche (falls dein Such-Endpoint ein flaches Array liefert)
                    this.matchResults = data.details?.contact_emails || data.details?.emails || [];
                }
            }
        } finally {
            this.loadingEmails = false;
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
        return this.activeContact?.emails?.some(e => e.ceid === ceid);
    },
    
    toggleEmail(emailObj) {
        const isLinked = this.isEmailLinked(emailObj.ceid);

        if (isLinked) {
            this.activeContact.emails = this.activeContact.emails.filter(e => e.ceid !== emailObj.ceid);
        } else {
            this.activeContact.emails.push(emailObj);
        }
        
        apiCall('update_mail_to_contact', {
            ceid: emailObj.ceid, 
            cid: this.activeContact.id, 
            action: isLinked ? 'unlink' : 'link'
        }, 'POST' );
    },

    //TAGS
    async fetchTagsForContact(id) {
        const data = await apiCall('get_tags_by_contact', {cid:id}, 'GET');
        if (data?.success) {
            this.activeContact.tags = data.details.tags || [];
            this.tagResults = [...this.activeContact.tags];
        }
    },

    async performTagSearch() {
        if (!this.activeContact) return;
        const currentTags = Array.isArray(this.activeContact.tags) ? this.activeContact.tags : [];

        if (this.tagQuery.trim().length < 2) {
            this.tagResults = [...currentTags];
            return;
        }
        
        this.loadingTags = true;
        try {
            const data = await apiCall('get_tags_by_search', {query: this.tagQuery}, 'GET');
            const rawResults = data.details?.tags || [];
            
            // Mappt id, name und color für das UI
            const normalizedResults = rawResults.map(item => ({
                id: item.id,
                name: item.name,
                color: item.color || '#a8a29e'
            }));
            
            //const existingTags = Array.isArray(this.activeContact.tags) ? this.activeContact.tags : [];
            const combined = [...currentTags, ...normalizedResults];
            
            // Einzigartig machen nach Tag-ID (nicht mehr ceid!)
            this.tagResults = Array.from(new Map(combined.map(item => [item.id, item])).values());
        } catch (err) {
            console.error("Tag-Suche fehlgeschlagen:", err);
        } finally {
            this.loadingTags = false;
        }
    },

    isTagLinked(tagId) {
        if (!this.activeContact?.tags) return false;
        return this.activeContact.tags.some(t => t.id === tagId);
    },
    
    async toggleTag(tagObj) {
       if (!this.activeContact) return;

        // 1. Array-Fallstrick absichern
        if (!Array.isArray(this.activeContact.tags)) {
            this.activeContact.tags = [];
        }

        const isLinked = this.isTagLinked(tagObj.id);
        const previousTags = [...this.activeContact.tags]; // Backup für Rollback

        // 2. UI sofort aktualisieren
        if (isLinked) {
            this.activeContact.tags = this.activeContact.tags.filter(t => t.id !== tagObj.id);
        } else {
            this.activeContact.tags = [...this.activeContact.tags, tagObj];
        }
        
        try {
            await apiCall('update_tag_to_contact', {
                tagId: tagObj.id, 
                cid: this.activeContact.id, 
                action: isLinked ? 'unlink' : 'link'
            }, 'POST');
        } catch (e) {
            console.error("Tag-Zuweisung auf Server fehlgeschlagen", e);
            this.activeContact.tags = previousTags; // Rollback auf vorherigen Zustand
        }
    },
    
    // --- D3 Netzwerk-Logik ---
    drawNetwork(contacts) {
        const svg = d3.select("#network");
        svg.on(".zoom", null);

        if (this.simulation) {
            this.simulation.stop();
            this.simulation = null; // Gibt die Referenz frei
        }
        
        svg.selectAll("*").interrupt().remove();

        // Hilfsfunktion zum Kürzen überlanger Texte
        const truncate = (text, maxLen = 22) => {
            if (!text) return "";
            return text.length > maxLen ? text.substring(0, maxLen - 3) + "..." : text;
        };

        // 1. Transformation
        const nodes = [], links = [];
        contacts.forEach(c => {
            nodes.push({ 
                id: 'c' + c.id, 
                label: truncate(c.displayname),
                fullLabel: c.displayname, 
                type: 'contact',
                is_active: c.is_active
            });
            c.emails?.forEach(e => {
                nodes.push({ 
                    id: 'e' + e.ceid, 
                    label: truncate(e.email),
                    fullLabel: e.email, 
                    type: 'email',
                    is_active: c.is_active
                });
                links.push({ 
                    source: 'c' + c.id, target: 'e' + e.ceid 
                });
            });
        });

        const parentNode = svg.node().parentElement;
        const width = parentNode.clientWidth || 500;
        const height = 450; 

        // 2. OPTIMIERT FÜR NÄHE: Kräfte deutlich sanfter einstellen
        const isMassive = nodes.length > 25;
        const chargeStrength = isMassive ? -100 : -160; 
        const linkDistance = isMassive ? 40 : 55;

        // Simulation aufbauen
        this.simulation = d3.forceSimulation(nodes)
            .force("link", d3.forceLink(links).id(d => d.id).distance(linkDistance))
            .force("charge", d3.forceManyBody().strength(chargeStrength))
            .force("center", d3.forceCenter(width / 2, height / 2))
            
            // HIER IST DIE DYNAMIK: Jedes Element berechnet seinen eigenen Radius!
            .force("collide", d3.forceCollide().radius(d => {
                // Basis-Radius für den Kreis selbst
                const baseRadius = isMassive ? 14 : 18;
                
                // Ungefähre Breite des Textes berechnen (Zeichenlänge * Pixel pro Zeichen)
                // Ein Buchstabe hat bei 10-11px Schriftgröße ca. 6-7px Breite.
                const textWidth = d.label ? d.label.length * 6.5 : 0;
                
                // Da der Text rechts neben dem Punkt steht, rechnen wir einen 
                // gewichteten Faktor hinzu, um Platz nach rechts zu schaffen.
                return baseRadius + (textWidth * 0.45);
            }))
            
            .force("x", d3.forceX(width / 2).strength(0.08))
            .force("y", d3.forceY(height / 2).strength(0.08));

        const g = svg.append("g");
        const zoom = d3.zoom()
            .scaleExtent([0.15, 4])
            .on("zoom", (e) => g.attr("transform", e.transform));
        svg.call(zoom);

        // 3. Render-Elemente generieren
        const link = g.selectAll("line").data(links).enter().append("line")
            .attr("stroke", "#cbd5e1")
            .attr("stroke-width", "1.5px")
            .attr("stroke-opacity", "0.7");

        // 4. SOLUTION A: Node & Label Container Group
        // Wir erstellen EINE Gruppe pro Knoten, die Punkt und Text enthält.
        const nodeGroup = g.selectAll(".node-container")
            .data(nodes)
            .enter()
            .append("g")
            .attr("class", "node-container cursor-pointer") // cursor-pointer am Container!
            .on("click", (event, d) => { // Ein einziger Click-Handler am Parent-Group!
                let foundContact = null;
                if (d.type === 'contact') {
                    foundContact = contacts.find(c => ('c' + c.id) === d.id);
                } else if (d.type === 'email') {
                    foundContact = contacts.find(c => c.emails?.some(e => ('e' + e.ceid) === d.id));
                }
                if (foundContact) this.openMatchModal(foundContact);
            });

        // Native Browser-Tooltips injizieren am Parent-Container
        nodeGroup.append("title").text(d => d.fullLabel);

        // 4b. SOLUTION B: Anti-Flicker Hover-Effekt (Wir ersetzen scale-125 durch einen fette Border-Effekt)
        // Dieser CSS-Effekt verändert den Layout-Raum nicht und eliminiert das Springen komplett!
        const nodeCircle = nodeGroup.append("circle")
            .attr("r", isMassive ? 7 : 9)
            .attr("class", "visible-node transition-all duration-200") // Klasse für CSS-Targeting
            .style("fill", d => d.is_active == 0 ? '#ef4444' : (d.type === 'contact' ? '#3b82f6' : '#10b981'))
            .style("stroke", "#ffffff") // Weiß default
            .style("stroke-width", "2.5px")
            .style("filter", "drop-shadow(0px 2px 3px rgba(0,0,0,0.06))");

        const nodeLabel = nodeGroup.append("text")
            .text(d => d.label)
            // pointer-events-none, damit Klicks durch den Text auf den Parent-Group durchschlagen
            .attr("class", "fill-slate-600 dark:fill-slate-300 font-medium select-none pointer-events-none") 
            .attr("font-size", isMassive ? "10px" : "11px")
            .attr("dx", isMassive ? 12 : 14)
            .attr("dy", 4);
            
        // CSS-Hover-Stil für Layout-neutrale visuelle Rückmeldung am Parent-Group
        svg.append("style").text(`
            #network .node-container:hover .visible-node {
                stroke: #2563eb; /* Blauer Rahmen bei Hover */
                stroke-width: 4px;
                filter: drop-shadow(0px 3px 6px rgba(0,0,0,0.18));
            }
            #network .node-container:hover text {
                fill: #2563eb; /* Text wird auch blau bei Hover */
            }
        `);

        // 4c. OFFLINE PRE-COMPUTATION & FREEZE
        for (let i = 0; i < 150; i++) {
            this.simulation.tick();
        }
        
        // Beruhigt den Graph komplett
        this.simulation.stop(); 

        // Einmalige, finale Zuweisung der berechneten Koordinaten
        link.attr("x1", d => d.source.x).attr("y1", d => d.source.y)
            .attr("x2", d => d.target.x).attr("y2", d => d.target.y);
        
        // Die Koordinaten werden jetzt dem Parent-Group zugewiesen!
        nodeGroup.attr("transform", d => `translate(${d.x}, ${d.y})`);

        // 5. Auto-Fit Kamera-Fokus
        if (nodes.length > 0) {
            const minX = d3.min(nodes, d => d.x);
            const maxX = d3.max(nodes, d => d.x);
            const minY = d3.min(nodes, d => d.y);
            const maxY = d3.max(nodes, d => d.y);

            const graphWidth = maxX - minX;
            const graphHeight = maxY - minY;
            const padding = 80; 
            
            const scaleX = width / (graphWidth + padding);
            const scaleY = height / (graphHeight + padding);
            let idealScale = Math.min(scaleX, scaleY);

            if (idealScale > 1.1) idealScale = 1; 

            const midX = (minX + maxX) / 2;
            const midY = (minY + maxY) / 2;

            const transform = d3.zoomIdentity
                .translate(width / 2, height / 2)
                .scale(idealScale)
                .translate(-midX, -midY);

            svg.call(zoom.transform, transform);
        }
    }
});


// Sicherheits-Registrierung am Ende von .js
if (window.Alpine) {
    // Falls Alpine bereits voll geladen ist
    window.Alpine.data('contactsView', contactsViewComponent);
} else {
    // Falls Alpine noch auf den Start wartet
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('contactsView', contactsViewComponent);
    });
}