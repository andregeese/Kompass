document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardView', () => ({
        // Zählerstände deiner IMAP-Ordner/DMS-Pipeline
        pipeline: {
            inbox: 20,
            dms: 2,
            done: 412
        },

        // Memos, die heute fällig sind oder deren due_date in der Vergangenheit liegt
        criticalMemos: [], 
        
        // Die nächsten Kalendereinträge
        upcomingEvents: [],

        acceleratorClusters: [],

        init() {
            // Hier würdest du deine API-Endpunkte triggern, um die Daten zu füttern
            this.loadDashboardData();
        },

        async loadDashboardData() {
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
            let aC = await apiCall('get_accelerate_cluster', {}, 'GET');
            let tD = await apiCall('get_total_document_count', {'tag':'unclassified'}, 'GET');
            let tD2 = await apiCall('get_total_document_count', {}, 'GET');
            this.acceleratorClusters = aC.details.accelorate_clusters;

            let memos = await apiCall('get_critical_memos', {}, 'GET');
            let events = await apiCall('get_upcoming_events', {}, 'GET');
            
            let lastDocs = pD.details.last_docs;
            this.criticalMemos = memos.details.critical_memos;
            this.upcomingEvents = events.details.upcoming_events;
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
            this.pipeline.inbox -= cluster.count;
            this.pipeline.dms += cluster.count;
        }
    }));
});