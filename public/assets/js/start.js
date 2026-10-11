document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardView', () => ({
        // Zählerstände deiner IMAP-Ordner/DMS-Pipeline
        isLoadingClusters: false,
        paperlessInfo: {
            details: {
                paperless_info: {}
            }
        },
        timeFilter: 4,
        criticalMemos: [], 
        upcomingEvents: [],
        acceleratorClusters: [],
        lastDocs: [],
        shouldBeAnswered: [],

        init() {
            // Hier würdest du deine API-Endpunkte triggern, um die Daten zu füttern
            this.loadDashboardData();
        },

        async loadDashboardData() {
            this.isLoadingClusters = true;
            this.paperlessInfo = await apiCall('get_paperless_info', {}, 'GET');

            // Beispiel-Inhalt für Memos (Simulation)
            this.criticalMemos = [
                { id: 101, content: 'Smarte Wohnwagen-Upgrades prüfen & DMS checken', due_date: '2026-06-11', email: 'no-reply@shelly.cloud' },
                { id: 102, content: 'Abo-Rechnung MyHeritage im DMS-Recht ablegen', due_date: '2026-06-09', email: 'smartmatches@myheritage.com' }
            ];

            this.upcomingEvents = [
                { id: 1, title: 'Automatischer Paperless-Sync', date: '2026-06-11', time: '15:00', category: '⚙️ System' },
                { id: 2, title: 'Backup Archiv-Datenbank', date: '2026-06-12', time: '22:00', category: '💾 Wartung' }
            ];

            let pD = await apiCall('get_last_unclassified', {}, 'GET');
            let selectedValue = this.selectedWeeks || 4; // Entspricht deiner x-model Variable
            let payload = selectedValue === 'all' ? { weeks: 0, unlimited: 1 } : { weeks: parseInt(selectedValue), unlimited: 0 };

            this.fetchClusters();

            let memos = await apiCall('get_critical_memos', {}, 'GET');
            let events = await apiCall('get_upcoming_events', {}, 'GET');

            let weeksParam = this.timeFilter === 'all' ? 0 : parseInt(this.timeFilter);
            let shouldBeAnswered = await apiCall('get_mails_that_should_be_answered', { weeks: weeksParam }, 'GET');

            // (Falls correspondent_name im Objekt fehlt, nutzen wir correspondent als Fallback)
            const rawDocs = pD?.details?.last_docs || [];
            this.lastDocs = rawDocs.slice(0, 5).map(doc => ({
                ...doc,
                // Fallback, falls die API nur die ID (correspondent) sendet und keinen String
                correspondent_name: doc.correspondent_name || (doc.correspondent ? `ID: ${doc.correspondent}` : 'Unbekannt')
            }));

            this.shouldBeAnswered = shouldBeAnswered?.details?.mails_to_answer || [];
            this.upcomingEvents = events?.details?.upcoming_events || [];
        },

        async fetchClusters() {
            this.isLoadingClusters = true;
            let weeksParam = this.timeFilter === 'all' ? 0 : parseInt(this.timeFilter);
            let aC = await apiCall('get_accelerate_cluster', { weeks: weeksParam }, 'GET');
            this.acceleratorClusters = aC?.details?.accelorate_clusters || [];
            this.isLoadingClusters = false;
        },

        async changeTimeFilter(e) {
            this.timeFilter = e.target.value;
            await this.fetchClusters();
        },

        // Hilfsfunktionen für Datumsberechnungen und Style-Zuweisung
        isOverdue(dateString) {
            if (!dateString) return false;
            const today = new Date();
            today.setHours(0,0,0,0);
            const due = new Date(dateString);
            due.setHours(0,0,0,0);
            return due < today;
        },

        formatDate(dateString) {
            if (!dateString) return '';
            return new Date(dateString).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' });
        },

        getTodayString() {
            return new Date().toLocaleDateString('de-DE', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
        },

        // Trigger für den Accelerator-Button
        async accelerateCluster(cluster) {
            console.log(`Befehl an Backend: Schiebe alle ${cluster.count} Mails von ${cluster.sender} direkt ins DMS!`);
            // Hier fetch() an deine api.php senden, die im Hintergrund das IMAP-Moving regelt.
            // Nach Erfolg aus dem Array werfen:
            this.acceleratorClusters = this.acceleratorClusters.filter(c => c.sender !== cluster.sender);
            this.paperlessInfo.inbox -= cluster.count;
            this.paperlessInfo.dms += cluster.count;
        }
    }));
});