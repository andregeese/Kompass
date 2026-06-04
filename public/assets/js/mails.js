// Kern-Logik der Komponente
const mailViewComponent = () => ({
    currentMailUrl: 'about:blank',
    mailLoading: false, 
    paperlessTree: {},
    mails: [],
    hasMore: true,
    loading: false,
    query: false,
    
    async init_mails() {
        this.initInfiniteScroll();
        // --- NEU: Such-Watcher ---
        this.$watch('mailSearchQuery', (val) => {
            clearTimeout(this.searchTimeout);
            
            this.searchTimeout = setTimeout(() => {
                this.loadMails(false);
                this.query= true;
            }, 300);
        });

        if (this.$data.isOnline) {
            await this.loadMails(false);
        };

        this.$nextTick(() => {
            console.log(this.$refs.mailList); // Jetzt sollte es definiert sein
        });
    },

    handleScroll(el) {
        if (!el) return;
    
        // Die Berechnung:
        // Ist der Abstand zum Boden weniger als 150px?
        const isNearBottom = el.scrollHeight - (el.scrollTop + el.clientHeight) <= 150;
        
        // Nur laden, wenn:
        // - Wir nicht schon laden (loading)
        // - Noch mehr Daten da sind (hasMore)
        // - Wir am unteren Ende sind
        if (!this.loading && this.hasMore && isNearBottom) {
            console.log("Trigger Infinite Scroll...");
            this.loadMails(true); // append=true
        }
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
        // Wenn wir schon in dem Ordner sind, nichts tun
        if (this.currentFolder === newFolder) return;
        
        // 1. Loader aktivieren, damit Alpine weiß, dass gleich was kommt
        this.loading = true;
        this.currentFolder = newFolder;
        
        // 2. Ansicht komplett leeren
        this.mails = [];
        this.page = 1;
        this.hasMore = true;
        this.currentHash = null; 
        this.currentMail = null;

        const searchInput = document.getElementById('search-input');
        if (searchInput) { searchInput.value = ''; currentSearch = ''; }

        // Aktiviere visuellen Klick-Zustand in der Navigation (Optional)
        document.querySelectorAll('.folder-item').forEach(el => el.classList.remove('active'));

        const activeEl = document.getElementById(`folder-${this.currentFolder}`);
        if (activeEl) activeEl.classList.add('active');
        
        // 3. WICHTIG: Erst im nächsten DOM-Zyklus laden, wenn die alte Liste weg ist!
        this.$nextTick(() => {
            // Ein kleiner Timeout (z.B. 50ms) bricht die Blockade des Infinite-Scroll-Containers
            setTimeout(() => {
                this.loading = false; // Sperre kurz lösen...
                this.loadMails(false);     // ...und frisch abfeuern!
            }, 50);
        });
    },
    
    async loadMails(append = false) {
        // Abbruch, wenn bereits geladen wird oder wir am Ende sind (bei nicht-Suche)
        if (this.loading || (append && !this.hasMore)) return;

        this.loading = true;

        // Reset der Seite bei frischem Laden
        if (!append) {
            this.$data.page = 1;
            this.hasMore = true;
        }

        try {
            const isSearch = (typeof this.mailSearchQuery === 'string' && this.mailSearchQuery.trim().length > 0)
            const folder = this.currentFolder;
            const params = isSearch 
                ? { folder: folder, query: this.mailSearchQuery } 
                : { folder: folder, page: this.$data.page, limit: this.$data.limit };

            // 2. Aktion bestimmen
            const action = isSearch ? 'search_mail' : 'list_mails';

            // 3. Aufruf über die neue API-Brücke
            const data = await apiCall(action, params, 'GET');

            // 4. Zugriff auf die Daten
            const fetchedMails = data.details?.mails || [];

            const receivedCount = fetchedMails.length;

            if (data?.success && data.details?.mails) {
                // Sicherstellen, dass mails immer ein Array ist
                const fetchedMails = Array.isArray(data.details.mails) 
                    ? data.details.mails 
                    : Object.values(data.details.mails);

                if (append) {
                    this.mails.push(...fetchedMails);
                } else {
                    this.mails = [];
                    this.mails = fetchedMails;
                    console.log("Liste wurde für neue Suche geleert und neu befüllt.");
                }

                // Paging Logik: Nur bei normaler Liste erhöhen, Suche hat kein Paging
                if (!isSearch) {
                    this.page++;
                    this.hasMore = this.hasMore = (receivedCount >= 50);;
                } else {
                    this.hasMore = false; // Infinite Scroll bei Suche deaktivieren
                }

                localStorage.setItem('cached_mails', JSON.stringify(this.mails));
            } else {
                // Wenn keine Ergebnisse: Liste leeren bei neuer Suche
                if (!append) this.mails = [];
                this.hasMore = false;
            }
        } catch (err) {
            console.error(data);
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

    // wird von viewMails aufgerufen, um mail in db zu speichen
    async autoLinkContact(name, email, hash) 
    {
        
        try {
            const response = await apiCall('auto_link_contact', {name: name, email: email, hash: hash }, 'POST');
            // Nach dem Linken aktualisierst du die Sidebar
            if (!response.success) {
                showNotify("Verknüpfung des Kontakts fehlgeschlagen!");
            }
        } catch (err) {
            showNotify("Verknüpfung des Kontakts (AutoLink) fehlgeschlagen!");
            console.error("Fehler beim AutoLink:", err);
        }
    },

    async viewMail(mail) {
        if (!mail?.hash) return;

        this.$data.currentMail = mail;
        this.mailLoading = true; 
        
        try {
            // CSRF-geschützter API-Call anstatt Iframe-URL
            const data = await apiCall('stream_mail_html', { hash: mail.hash }, 'GET');
            
            if (data && data.success !== false) {
                // Wenn die API das HTML direkt als String liefert:
                this.currentMailHtml = data.details?.mail; 
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
        if (!this.isOnline || !mail?.hash) return;
    
        const fd = new FormData();
        fd.append('id', mail.hash); // Schickt NUR den Hash an die ${this.$data.apiUrl}
        
        try {
            const data = await apiCall('dms', fd, 'POST');
            mail.paperless_id = true; // Markiert die Mail im Frontend als übertragen
        } catch (err) {
            console.error("Fehler beim Verschieben ins DMS:", err);
        }
    },

    async deleteMail(mail) 
    {
        confirmAction('Diese Mail wirklich löschen?', async() => {
            try {
                const fd = new FormData();
                fd.append('id', mail.hash);
                const data = await apiCall('delete', { id: mail.hash }, 'POST');
                
                if (data?.success) {
                    // UI aktualisieren
                    this.$data.mails = this.$data.mails.filter(m => m.hash !== mail.hash);
                    localStorage.setItem('cached_mails', JSON.stringify(this.$data.mails));
                    
                    if (this.$data.selectedHash === mail.hash) {
                        this.$data.currentMail = null;
                    }
                } else {
                    console.error("Löschen fehlgeschlagen:", data?.error);
                }
            } catch (err) { 
                console.error("Netzwerkfehler beim Löschen:", err);
            }
        });
        
    },

    async trackFoldersSilent(showLoadingIndicator, folderName = 'INBOX') {
        if (showLoadingIndicator) this.loading = true;
        let syncSuccess = false;
        
        try {
            const data = await apiCall('sync', {folder:folderName}, 'POST');
            if (data && data.success) {
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
                const foldersToSync = ['INBOX', 'DMS', 'DMSDone', 'Sent Messages'];
                const LIMIT = 20; // Muss exakt mit deinem PHP $maxNewMailsLimit übereinstimmen

                // Der Loop läuft entkoppelt im Hintergrund
                (async () => {
                    for (const folder of foldersToSync) {
                        let keepSyncing = true;
                        let safetyBuffer = 0; // Verhindert Endlosschleifen bei PHP-Fehlern

                        console.log(`⏱️ Starte Tiefen-Sync für Ordner: ${folder}`);

                        while (keepSyncing && safetyBuffer < 10) {
                            try {
                                // Führe den Sync aus und warte auf das echte Ergebnis aus PHP
                                const data = await apiCall('sync', {folder:folder}, 'POST');
                                
                                // Wenn PHP erfolgreich war und neue Mails hinzugefügt hat
                                if (data && data.success) {
                                    console.log(data);
                                
                                    const addedCount = parseInt(data.added || 0);
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
        if (this.$data.loading || this.silentLoading) return; // 🚀 FIX: $data genutzt
        this.silentLoading = true;

        if (this.mailSearchQuery && this.mailSearchQuery.length > 0) {
            console.log("Hintergrund-Sync pausiert: Suche ist aktiv.");
            return;
        }

        try {
            const params = { limit: 50 };
            if (this.currentFolder) {
                params.folder = this.currentFolder;
            }

            // 2. Aufruf über die neue API-Brücke
            const data = await apiCall('list_mails', params, 'GET');
            
            if (data && data.success && data.details?.mails) {
                // 🚀 FIX: Einheitlich $data.mails prüfen und setzen
                if (JSON.stringify(this.$data.mails) !== JSON.stringify(data.details.mails)) {
                    this.$data.mails = data.details.mails;
                }
            }
        } catch (err) {
            console.error("Fehler beim Laden der Mails (silent):", err);
        } {
            this.silentLoading = false;
        }
    },

    initInfiniteScroll() {
        this.$nextTick(() => {
            const container = document.getElementById('mail-list');
            if (!container) return;
            
            // Definiere den Handler als Funktion, damit wir ihn sauber binden
            const scrollHandler = () => {
                // Check: Lädt er schon? Sind noch Daten da?
                if (this.loading || !this.hasMore) return;
                
                // Check: Ist genug gescrollt?
                if (container.scrollHeight - (container.scrollTop + container.clientHeight) <= 150) {
                    console.log("Nachladen Seite:", this.page);
                    this.loadMails(true); // append=true
                }
            };

            container.addEventListener('scroll', scrollHandler);
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