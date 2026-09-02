/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

// Kern-Logik der Komponente
const mailViewComponent = () => ({
    currentMailUrl: 'about:blank',
    mailLoading: false, 
    paperlessTree: {},
    mails: [],
    hasMore: true,
    loading: false,
    query: false,
    isSidebarOpen: true,
    currentMailHtml:'',
    folder: 'INBOX',
    currentFolder: 'INBOX',
    
    async init_mails() {
        // 1. FIX: await hinzugefügt!
        try {
            const res = await apiCall('get_imap_folder', {}, 'GET');
            this.SYNCFOLDER = res?.details?.imaps || [];
        } catch (e) {
            console.error("Fehler beim Laden der IMAP-Ordner:", e);
        }

        // URL-Parameter prüfen, ob ein spezifischer Ordner übergeben wurde
        const urlParams = new URLSearchParams(window.location.search);
        const requestedFolder = urlParams.get('folder');
        
        if (requestedFolder && this.SYNCFOLDER.includes(requestedFolder)) {
            this.currentFolder = requestedFolder;
        }else if (this.SYNCFOLDER.length > 0) {
            this.currentFolder = this.SYNCFOLDER[0];
        }

        this.initInfiniteScroll();

        // Standardmäßig Mails laden
        this.loadMails(false);

        // 🎯 URL-Parameter direkt im JS abfangen
        const params = new URLSearchParams(window.location.search);
        const targetId = params.get('id'); // FIX: targetId sauber für den gesamten Verlauf definieren

        if (targetId) {
            // Mail in Liste suchen
            let foundMail = this.mails.find(m => m.hash === targetId || m.id == targetId);
            
            // Wenn nicht gefunden, per API laden
            if (!foundMail) {
                const data = await apiCall('get_mail_by_id', { id: targetId }, 'GET');
                if (data?.success) foundMail = data.details.mail;
            }

            // Wenn wir eine Mail haben, öffnen wir sie exakt wie bei einem Klick
            if (foundMail) {
                await this.openMail(foundMail);
            }
        }

        //Suche ansteuern
        const query = params.get('query'); // FIX: targetId sauber für den gesamten Verlauf definieren
        if (query)
        {
            this.mailSearchQuery = query;
            this.searchQuery = query;
            this.folder = params.get('folder') || 'INBOX';
        }

        // 2. FIX: Watcher JETZT global für alle Zustände aktiv!
        this.$watch('mailSearchQuery', (val) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.query = (val && val.trim() !== '');
                this.loadMails(false);
            }, 300);
        });
    },

    async openMail(mail) {
        console.log("openMail", mail);
        this.currentMail = mail;
        this.currentMailHash = mail.id;
        this.isSidebarOpen = false; // Entspricht deinem Click-Handler
        
        // UI-Events auslösen
        this.$dispatch('toggle-sidebar-request', { tab: 'context' });
        
        // Mail-Inhalt laden
        await this.viewMail(mail);
    },

    get filteredMails() {
        if (!this.mailSearchQuery || !this.mailSearchQuery.trim()) {
            return this.mails;
        }
        const query = this.mailSearchQuery.toLowerCase().trim();
        
        return this.mails.filter(mail => {
            const subject = mail.subject ? mail.subject.toLowerCase() : '';
            const sender = mail.sender ? mail.sender.toLowerCase() : '';
            const date = mail.date ? mail.date.toLowerCase() : '';
            return subject.includes(query) || sender.includes(query) || date.includes(query);
        });
    },
    // --- Unter deinen CORE AKTIONEN hinzufügen ---
    switchFolder(newFolder) {
        if (this.currentFolder === newFolder) return;
        
        this.loading = true;
        this.currentFolder = newFolder;
        this.mails = [];
        this.page = 1;
        this.hasMore = true;
        this.currentMailHtml = '';
        //this.mailSearchQuery = ''; // 4. FIX: Sauber über Zustand leeren, kein DOM-Gepfusche mehr!
        
        this.$nextTick(() => {
            setTimeout(() => {
                this.loading = false;
                this.loadMails(false);
            }, 50);
        });
    },
    
    async loadMails(append = false) {
        if (this.loading || (append && !this.hasMore)) return;
        this.loading = true;

        if (!append) {
            this.page = 1;
            this.hasMore = true;
        }

        try {
            const params = {
                folder: this.currentFolder,
                page: this.page,
                limit: this.limit,
                search: this.mailSearchQuery || ''
            };

            // FIX: action steuern wir über den Zustand von "this.query"
            const action = this.query ? 'search_mail' : 'list_mails';
            const jResponse = await apiCall(action, params, 'GET');

            if (jResponse?.success && jResponse.details?.mails) {
                const fetchedMails = Array.isArray(jResponse.details.mails) 
                    ? jResponse.details.mails 
                    : Object.values(jResponse.details.mails);

                this.mails = append ? [...this.mails, ...fetchedMails] : fetchedMails;
                this.hasMore = (fetchedMails.length >= this.limit);
                if (this.hasMore) this.page++;
                
                localStorage.setItem('cached_mails', JSON.stringify(this.mails));
            } else {
                if (!append) this.mails = [];
                this.hasMore = false;
            }
        } catch (err) {
            console.error("Fehler beim Laden:", err);
        } finally {
            this.loading = false;
        }
    },

    // Mail in DB einspeichern
    async fillSidebarWithMailData(mail) {
        this.manualSearchQuery = mail.subject || '';
        let hash = mail.hash;
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
        //hast ist die ID gehasht
        //this.autoLinkContact(parsedName, parsedEmail, hash);
    },
    
    async viewMail(mail) 
    {
        if (!mail?.id) return;
        this.$data.currentMail = mail;
        this.mailLoading = true; 
        
        try {
            // CSRF-geschützter API-Call anstatt Iframe-URL
            console.log("lade Email", mail.id);
            const jResponse = await apiCall('stream_mail_html', { id: mail.id }, 'GET');
            
            if (jResponse && jResponse?.success !== false) {
                // Wenn die API das HTML direkt als String liefert:
                this.currentMailHtml = jResponse.details?.mail; 
            } else {
                this.currentMailHtml = '<p>Fehler beim Laden der E-Mail.</p>';
            }
        } catch (err) {
            console.error("Fehler beim Laden der Mail:", err);
            this.currentMailHtml = '<p>Verbindungsfehler.</p>';
        } finally {
            this.mailLoading = false;
        }
    },        

    async moveToDMS(mail) {
        if (!this.isOnline || !mail?.id) return;
    
        try {
            const jResponse = await apiCall('dms', {'id': mail.id}, 'POST');
            mail.paperless_id = true; // Markiert die Mail im Frontend als übertragen
        } catch (err) {
            console.error("Fehler beim Verschieben ins DMS:", err);
        }
    },

    async deleteMail(mail) 
    {
        confirmAction('Wie möchtest du diese Mail löschen?', [
        {
            label: 'Abbrechen',
            class: 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-white',
            onClick: () => { console.log('Aktion abgebrochen'); }
        },
        {
            label: 'alle löschen',
            class: 'bg-amber-500 hover:bg-amber-600 text-white',
            onClick: async () => {
                // Deine originale Lösch-Logik
                const jResponse = await apiCall('delete_all_mails', { id: mail.id }, 'POST');
                if (jResponse?.success) {
                    this.$data.mails = this.$data.mails.filter(m => m.id !== mail.id);
                }
            }
        },
        {
            label: 'Endgültig Löschen',
            class: 'bg-red-600 hover:bg-red-700 text-white',
            onClick: async () => {
                // Deine originale Lösch-Logik
                const jResponse = await apiCall('delete', { id: mail.id }, 'POST');
                if (jResponse?.success) {
                    this.$data.mails = this.$data.mails.filter(m => m.id !== mail.id);
                }
            }
        }
    ]);
        
    },
    async trackFoldersSilent(showLoadingIndicator, folderName = 'INBOX') {
        if (showLoadingIndicator) this.loading = true;
        let syncSuccess = false;
        
        try {
            const jResponse = await apiCall('sync_mails', {folder:folderName, maxNewMailsLimit:100}, 'POST');
            if (jResponse && jResponse.success) {
                syncSuccess = true;
            }
        } catch (e) {
            console.error("Sync-Fehler im Frontend:", e);
        } finally {
            if (showLoadingIndicator) this.loading = false;
        }
        // Erst JETZT feuern, wenn der Loader (this.loading) garantiert wieder false ist!
        if (syncSuccess && this.currentFolder === folderName) {
            await this.loadMailsSilent();
        }
    },

    // Wartet, bis das Fenster komplett geladen ist, bevor die Loops starten
    startBackgroundSyncDelayed() {
        setTimeout(() => {
            console.log("🚀 Hintergrund-Sync wird jetzt gestartet...");
            this.startBackgroundSync();
        }, 500);
    },
    // Startet die Schleife für den Hintergrund-Sync sauber und sicher
    startBackgroundSync() {
        // Schleife 1: Reiner Mail-Refresh
        const refreshMails = async () => {
            try {
                if (this.$data.isOnline && !this.$data.loading) {
                    await this.loadMailsSilent();
                }
            } catch (err) {
                console.error("Fehler im Hintergrund-Mail-Sync:", err);
            }
            setTimeout(refreshMails, 30000); 
        };
        refreshMails();
        // Schleife 2: IMAP-Ordner-Sync (vom Mail-Refresh entkoppelt)
        const runFolderSyncLoop = async () => {
            if (this.$data.isOnline) {
                const foldersToSync = this.SYNCFOLDER;
                const LIMIT = 100; // Muss exakt mit deinem PHP $maxNewMailsLimit übereinstimmen

                // Der Loop läuft entkoppelt im Hintergrund
                (async () => {
                    for (const folder of foldersToSync) {
                        let keepSyncing = true;
                        let safetyBuffer = 0; // Verhindert Endlosschleifen bei PHP-Fehlern
                        console.log(`⏱️ Starte Tiefen-Sync für Ordner: ${folder}`);
                        while (keepSyncing && safetyBuffer < 10) {
                            try {
                                // Führe den Sync aus und warte auf das echte Ergebnis aus PHP
                                const jResponse = await apiCall('sync_mails', {folder:folder, maxNewMailsLimit: LIMIT}, 'POST');
                                
                                // Wenn PHP erfolgreich war und neue Mails hinzugefügt hat
                                if (jResponse && jResponse.success) {
                                    //console.log(jResponse);
                                
                                    const addedCount = parseInt(jResponse.added || 0);
                                    console.log(`📥 [${folder}] ${addedCount} Mails importiert.`);
                                    // Wenn das Limit erreicht wurde, gibt es vermutlich noch mehr Mails!
                                    if (addedCount === LIMIT) {
                                        console.log(`🔄 Limit von ${LIMIT} erreicht. Triggere sofort den nächsten Durchlauf für ${folder}...`);
                                        keepSyncing = true; 
                                        safetyBuffer++; // Zähler hochsetzen
                                    } else {
                                        // Weniger als 20 Mails hinzugefügt? Dann sind wir komplett aktuell!
                                        keepSyncing = false;
                                    }
                                } else {
                                    // Fehler von der API erhalten -> Schleife abbrechen
                                    keepSyncing = false;
                                }
                            } catch (err) {
                                console.error(`Fehler im Tiefen-Sync für ${folder}:`, err);
                                keepSyncing = false;
                            }
                        }
                        console.log(`✅ Ordner vollständig abgeglichen: ${folder}`);
                    }
                })();
            }
            // Wenn alle Ordner glattgezogen sind, in 60 Sekunden von vorne prüfen
            setTimeout(runFolderSyncLoop, 60000);
        };
        // Erster Durchlauf nach 5 Sekunden, um dem initialen Laden Vorrang zu geben
        setTimeout(runFolderSyncLoop, 5000);
    },

    /** lädt E-Mails in der Übersicht, beim Scrollen nach */
    async loadMailsSilent() {
        if (this.loading || this.silentLoading) return;
        if (this.mailSearchQuery && this.mailSearchQuery.length > 0) return;
        
        // 5. FIX: Wenn der User tief gescrolled hat (page > 2), pausieren wir den Silent Sync,
        // um Daten-Inkonsistenz zu vermeiden.
        if (this.page > 2) return; 

        this.silentLoading = true;
        try {
            const params = { limit: this.limit, folder: this.currentFolder };
            const jResponse = await apiCall('list_mails', params, 'GET');
            
            if (jResponse?.success && jResponse.details?.mails) {
                if (JSON.stringify(this.mails) !== JSON.stringify(jResponse.details.mails)) {
                    this.mails = jResponse.details.mails;
                }
            }
        } catch (err) {
            console.error("Fehler beim Laden der Mails (silent):", err);
        } finally {
            this.silentLoading = false;
        }
    },

    initInfiniteScroll() {
        this.$nextTick(() => {
            const container = document.getElementById('mail-list');
            if (!container) return;
            
            container.onscroll = () => {
                if (this.loading || !this.hasMore) return;
                
                const threshold = 150;
                const position = container.scrollHeight - (container.scrollTop + container.clientHeight);
                
                if (position <= threshold) {
                    this.loadMails(true); 
                }
            };
        });
    },
    
    mailLoaded() {
        if (this.currentMailUrl === 'about:blank') return;
        console.log("Mail-Iframe fertig geladen!");
        this.mailLoading = false; 
    }
});
// Sicherheits-Registrierung am Ende von mail.js
if (window.Alpine) {
    // Falls Alpine bereits voll geladen ist
    window.Alpine.data('mailView', mailViewComponent);
} else {
    // Falls Alpine noch auf den Start wartet
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('mailView', mailViewComponent);
    });
}