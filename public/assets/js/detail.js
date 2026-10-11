/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

const detailViewComponent = () => ({
    // Reactive State
    loading: false,
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
    manualSearchQuery: '', // HIER: Für den Bind des neuen Suchschlitzes
    dmsLoading: false,
    // Haupt-Initialisierung beim Laden der Ansicht
    async initDetail() {
        // 1. Hole die URL-Parameter des echten Seitenaufrufs
        const urlParams = new URLSearchParams(window.location.search);
        const hashFromUrl = urlParams.get('hash');
        // 2. Wenn ein Hash in der URL existiert, laden wir sofort die CRM-Daten
        if (hashFromUrl) {
            // Sicherstellen, dass der Loader läuft
            this.crmLoading = true; 
            this.dmsLoading = true;
            // Rufe deine bestehende PHP-Abfragefunktion auf
            // (Nutze hier den exakten Funktionsnamen, den du für die API-Abfrage gebaut hast)
            this.checkCRMStatus(hashFromUrl).then(() => {
                this.crmLoading = false;
                this.dmsLoading = false;
            }).catch(err => {
                console.error("Fehler beim initialen CRM-Load:", err);
                this.crmLoading = false;
                this.dmsLoading = false;
            });
        }
    },
    // Prüft, ob der Absender bereits im CRM existiert oder ein DMS-Vorschlag vorliegt
    async checkCRMStatus(hash) {
        this.crmLoading = true;
        this.dmsLoading = true;
        try {
            // 1. Erst CRM Status prüfen, um an die Mailadresse zu kommen
            const res = await apiCall('search_contact', {hash:hash}, 'GET');
            const json = await res.json();
            
            if (json.success) {
                this.contactState = json.details;
                
                if (this.contactState.status === 'matched') {
                    this.selectedCorrespondents = this.contactState.current_mappings || [];
                } else if (this.contactState.status === 'unknown' || this.contactState.status === 'suggestion') {
                    this.formData.name = this.contactState.suggested_name || (this.contactState.contact ? this.contactState.contact.display_name : '');
                    this.formData.email = this.contactState.email || '';
                    this.formData.type = this.contactState.contact ? this.contactState.contact.type : 'company';
                    this.selectedCorrespondents = this.contactState.status === 'suggestion' ? [this.formData.name] : [];
                }
                // 2. Default-Suche füttern: Wenn das manuelle Feld leer ist, nimm die Mailadresse aus dem CRM-State
                if (!this.manualSearchQuery) {
                    this.manualSearchQuery = this.formData.email || this.contactState.email;
                }
                // 3. Erstmalige DMS-Suche ausführen
                await this.fetchDMSCorrespondents(hash, this.manualSearchQuery);
            }
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
            const response = await safeFetch('get_paperless_correspondents', {hash :hash, force_query:queryTerm}, 'GET');
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
        const urlParams = new URLSearchParams(window.location.search);
        const hash = urlParams.get('hash') || '';
        
        if (!hash || !this.manualSearchQuery) return;
        
        this.dmsLoading = true;
        await this.fetchDMSCorrespondents(hash, this.manualSearchQuery);
        this.dmsLoading = false;
    },
    // Erstellt den neuen CRM-Kontakt über die Sidebar
    async saveContactDetail() {
        if (!this.formData.name) return alert('Bitte einen Namen eingeben.');
        this.crmLoading = true;
        const bodyData = new FormData();
        bodyData.append('name', this.formData.name);
        bodyData.append('email', this.formData.email);
        bodyData.append('type', this.formData.type);
        try {
            const data = await apiCall('insert_contact', { body: bodyData }, 'POST');
            
            if (data.success) {
                const urlParams = new URLSearchParams(window.location.search);
                await this.checkCRMStatus(urlParams.get('hash'));
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
// Saubere Registrierung für Alpine (ohne Dopplungen)
if (window.Alpine) {
    Alpine.data('detailView', detailViewComponent);
} else {
    document.addEventListener('alpine:init', () => {
        Alpine.data('detailView', detailViewComponent);
    });
}