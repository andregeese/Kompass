// script/dashboard.js

document.addEventListener('alpine:init', () => {
    Alpine.data('mailApp', () => ({
        // --- STATE (Datenmodell) ---
        mails: [],
        page: 1,
        limit: 20,
        loading: false,
        hasMore: true,
        selectedHash: null,
        activeTab: 'paperless',
        // Suchen & Filtern
        mailSearchQuery: '',     // <-- DIESE ZEILE FEHLT BEI DIR!
        manualSearchQuery: '',   // (Bestehende Paperless-Suche)
        
        // Aktive E-Mail Variablen
        currentMail: null, // Hält das gesamte Objekt der aktuell geöffneten Mail
        currentHash: null,
        currentMailSubject: null,
        currentMailDate: null,
        currentMailSender: null,
        
        // Paperless Integration
        paperlessMatches: [],
        paperlessLoading: false,
        paperlessTree: {}, // Hält die hierarchische Struktur (Jahr -> Monat -> Docs)
        
        // System Status
        haInfoHtml: '<span>Lade Status...</span>',
        serverInfoHtml: '<span>Lade Status...</span>',
        
        // Mobile UI Steuerung
        showMobilePreview: false,
        silentLoading: false,
        currentFolder: 'INBOX', // Neu: Aktueller Ordner (z.B. 'INBOX', 'DMS')

        // 2. GETTER (Berechnete Eigenschaften - HIER MUSS ES STEHEN)
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

        // Berechnet den maximalen Score der aktuellen Suche
        get maxScore() {
            if (this.documents.length === 0) return 1;
            return Math.max(...this.documents.map(d => d.originscore || 0), 1);
        },

        // Gibt die relative Qualität (0 bis 1) zurück
        getQuality(score) {
            return (score || 0) / this.maxScore;
        },

        // --- INIT ---
        async init() {
            this.currentFolder = 'DMSDone';

            try {
                await this.loadMails(false);
            } catch(e) {
                console.error("Initiales Laden der Mails fehlgeschlagen:", e);
            }
            try{
                await this.loadSystemStatus();
             
            } catch(e) {
                console.error("Initiales Laden des Systemstatus fehlgeschlagen:", e);
            }

            try{
                await this.initInfiniteScroll();
            }catch(e) {
                console.error("Initialisierung des Infinite Scroll fehlgeschlagen:", e);
            }

            this.initPullToRefresh();

            
            // Watcher für die manuelle Suche mit eingebautem Debounce (300ms)
            // Watcher für die manuelle Suche mit eingebautem Debounce (300ms)
            this.$watch('manualSearchQuery', this.debounce((query) => {
                const trimmed = query.trim();
                if (trimmed.length > 2) {
                    // 1. Dokumenten-Treffer suchen
                    this.findMatchSuggestions(trimmed);
                    
                    // 2. Nur den Baum via Textsuche laden, wenn die Mail NICHT bereits fest verknüpft ist
                    if (!this.currentMail?.paperless_id) {
                        this.loadPaperlessTree(trimmed);
                    }
                    
                } else if (trimmed.length === 0 && this.currentMailSubject) {
                    this.findMatchSuggestions(this.currentMailSubject);
                    this.paperlessTree = {}; // Baum leeren, wenn Suche gelöscht wird
                }
            }, 300));

            // Überwache das Suchfeld auf Änderungen
            this.$watch('mailSearchQuery', (value) => {
                // Bestehenden Timer löschen, während der User tippt
                clearTimeout(this.searchTimeout);
                
                // Erst 300ms nach dem letzten Tastendruck die Suche ausführen
                this.searchTimeout = setTimeout(() => {
                    console.log("Suche wird getriggert für:", value); // <-- Zum Testen im Browser (F12)
                    this.manualSearchQuery = this.currentMailSubject;
            
                    
                    this.page = 1;
                    this.mails = [];
                    this.hasMore = true; // Wichtig, damit loadMails() nicht blockiert!
                    this.loadMails(false);
                }, 300);
            });
        },

        // --- HILFSFUNKTIONEN ---
        debounce(func, delay = 300) {
            let timeoutId;
            return function (...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => {
                    func.apply(this, args);
                }, delay);
            };
        },

        initPullToRefresh() {
            let touchStart_Y = 0;

            // Event-Listener an das Fenster binden
            window.addEventListener('touchstart', (e) => {
                // Nur triggern, wenn wir ganz oben am Rand der Seite/Spalte sind
                if (window.scrollY === 0) {
                    touchStart_Y = e.touches[0].clientY;
                }
            }, { passive: true });

            window.addEventListener('touchmove', (e) => {
                const touchMove_Y = e.touches[0].clientY;
                const touchMove_Difference = touchMove_Y - touchStart_Y;

                // 150 Pixel nach unten ziehen = Reload auslösen
                if (window.scrollY === 0 && touchMove_Difference > 150) {
                    // Kurzes haptisches Feedback am Smartphone (falls unterstützt)
                    if (navigator.vibrate) {
                        navigator.vibrate(15);
                    }
                    
                    // Seite neu laden
                    window.location.reload();
                }
            }, { passive: true });
        },

        /**
         * Öffnet ein Dokument direkt in Paperless-ngx in einem neuen Browser-Tab.
         * @param {number|string} paperlessId - Die ID des Dokuments in Paperless
         */
        openPaperless(paperlessId) {
            if (!paperlessId) {
                console.warn("Keine Paperless-ID übergeben.");
                return;
            }

            // Falls deine API-Url im Dashboard-State hinterlegt ist (z.B. http://192.168.0.72:8030)
            // Falls nicht, kannst du stattdessen die IP direkt als String einsetzen.
            const baseUrl = this.paperlessURL || 'http://192.168.0.72:8030';
            
            // Paperless-ngx nutzt das Format /documents/{id} für die Detailansicht
            const paperlessUrl = `${baseUrl}/documents/${paperlessId}`;

            // Öffnet den Link in einem neuen Tab, ohne das aktuelle Dashboard zu verlassen
            window.open(paperlessUrl, '_blank');
        },

        async syncPaperless() {
            this.paperlessLoading = true;
            this.syncLogs = []; // Hier speichern wir die Log-Zeilen für das HTML

            try {
                // Aufruf deiner API
                const response = await safeFetch(`api.php?action=syncPaperless`);
                const data = await response.json();

                if (data && data.success) {
                    // 💡 HIER LIEGT DER TRICK: 
                    // Wir holen die Logs aus data.details.logs (wie im neuen PHP definiert)
                    this.syncLogs = data.details.logs || [];
                    
                    // Falls du die Matches auch direkt verarbeiten willst:
                    this.paperlessMatches = data.details.matches || [];
                    
                    console.log("Paperless Sync Logs geladen:", this.syncLogs);
                } else {
                    this.syncLogs = [`<span class="text-red-500">API Fehler: ${data.error || 'Unbekannt'}</span>`];
                }
            } catch (err) {
                console.error("Fehler beim PaperlessSync:", err);
                this.syncLogs = [`<span class="text-red-500">Verbindungsfehler: ${err.message}</span>`];
            } finally {
                this.paperlessLoading = false;
            }
        },

        /*// --- CORE AKTIONEN ---
        async loadMailsSilent() {
            // Wenn der normale Loader ODER der Silent-Loader läuft, abbrechen
            if (this.loading || this.silentLoading) return;
            this.silentLoading = true;

            try {
                // Hinweis: action=sync holt die Mails ab und arbeitet sie ab.
                // Falls du nur den aktuellen Stand refreshen willst, ohne cURL-Verzögerung,
                // wäre hier eine action=getMails o.ä. performanter.
                const data = await safeFetch('api.php?action=sync&max=50');
                
                if (data && data.success && data.details && data.details.mails) {
                    // Nur zuweisen, wenn sich die Anzahl oder Daten tatsächlich geändert haben
                    if (JSON.stringify(this.mails) !== JSON.stringify(data.details.mails)) {
                        this.mails = data.details.mails;
                        this.showNotify("NEUE Mail", "es kamen neue Mails an", "success");
                    }
                }
            } catch (err) {
                console.error("Fehler beim Laden der Mails (silent):", err);
            } finally {
                this.silentLoading = false;
            }
        },*/

        // Mails asynchron nachladen (Infinite Scroll & IMAP-Sync)
        async loadMails(append=false) 
        {
            if (this.loading || !this.hasMore) return;
            this.loading = true;

            if (!append) {
                this.page = 1;
                this.hasMore = true;
                this.mails = []; // Liste leeren
            }
            const folder = this.currentFolder || 'INBOX';
            const mailContainer = document.getElementById('mail-list-container');
            if (!append && mailContainer) {
                mailContainer.innerHTML = '<div class="spinner">Lädt...</div>';
            }

            try {
                const searchParam = this.mailSearchQuery ? `&search=${encodeURIComponent(this.mailSearchQuery)}` : '';
                const url = `api.php?action=list&page=${this.page}&limit=${this.limit}&folder=${folder}${searchParam}`;
                
                // 1. 🚀 FIX: safeFetch liefert bereits das FERTIGE JSON-Objekt!
                // Kein "response" und kein "response.json()" mehr nötig!
                const data = await safeFetch(url);

                if (!append && mailContainer) {
                    mailContainer.innerHTML = ''; // Spinner entfernen
                }
                
                // 2. Jetzt arbeiten wir direkt mit dem sauberen Objekt
                if (data && data.success && data.details && data.details.mails && data.details.mails.length > 0) {
                    this.page++;
                    
                    // Reaktiv in die Liste pushen
                    this.mails.push(...data.details.mails);
                    
                    // Falls weniger Mails zurückkommen als das Limit, sind wir am Ende der DB
                    if (data.details.mails.length < this.limit) {
                        this.hasMore = false;
                    }
                } else {
                    // Lokale DB leer -> IMAP Sync erzwingen
                    this.hasMore = false;
                    
                    if (this.mailSearchQuery) {
                        if (!append && mailContainer) {
                            mailContainer.innerHTML = '<div class="no-results">Keine Mails für diese Suche gefunden.</div>';
                        }
                        return; 
                    }
                    // Nur wenn KEINE Suche aktiv ist, holen wir ältere Mails via IMAP nach
                    showNotify('IMAP Sync', `Suche in ${this.currentFolder} nach älteren Nachrichten...`, 'info');
                    
                    try {
                        const syncData = await safeFetch(`api.php?action=sync&folder=${encodeURIComponent(this.currentFolder)}`);
                        
                        if (syncData && syncData.success && syncData.details && syncData.details.added > 0) {
                            showNotify('Sync erfolgreich', `${syncData.details.added} neue Mail(s) vom Server geladen.`, 'success');
                            this.mails = [];
                            this.page = 1;
                            this.hasMore = true;
                            this.loading = false; // Sperre vor Rekursion lösen
                            
                            return await this.loadMails(false);
                        } else {
                            showNotify('Info', 'Keine weiteren Mails in diesem Ordner.', 'secondary');
                        }
                    } catch (err) {
                        console.error("Fehler beim Ordner-Sync via loadMails:", err);
                    }
                }
            } catch (err) {
                console.error("Fehler beim Laden der Mails:", err);
                this.hasMore = false;
            } finally {
                this.loading = false; // Gibt den Loader erst ganz am Ende frei!
            }
        },

        // Einzelne Mail laden & anzeigen
        async viewMail(mail) 
        {
            
            if (!mail || !mail.hash) return;
            const hash = typeof mail === 'string' ? mail : mail.hash;
            if (!hash) return;

            this.currentHash = mail.hash;
            this.selectedHash = mail.hash;
            
            // Metadaten für das UI und die Suche direkt setzen:
            this.currentMail = mail; 
            this.currentMailSubject = mail.subject || '';
            this.currentMailSender = mail.sender || '';
            this.currentMailDate = mail.date_received || '';

            this.manualSearchQuery = mail.subject;

            const iframe = document.getElementById('mail-preview-iframe');
            const spinnerContainer = document.getElementById('mail-preview-spinner');

            if (iframe) {
                iframe.classList.add('hidden'); // Iframe verstecken, während es streamt
                

                // 🚀 ONLOAD-RETTUNG: Sobald das Iframe fertig vom Server gestreamt hat...
                iframe.onload = () => {
                    if (spinnerContainer) spinnerContainer.classList.add('hidden'); // Spinner weg
                    iframe.classList.remove('hidden'); // Iframe zeigen
                    
                    // Metadaten für die Suche füttern, falls das Iframe fertig ist
                    if (this.currentMailSubject) {
                        this.findMatchSuggestions(this.currentMailSubject);
                    }
                };

                // Jetzt stoßen wir das Streaming über das src-Attribut an!
                // (Wichtig: Verwende hier exakt den API-Action-Namen deiner api.php, z.B. stream_mail_html)
                iframe.src = `api.php?action=stream_mail_html&hash=${hash}`;
            }
            
        
            if (spinnerContainer) {
                spinnerContainer.classList.remove('hidden');
            }
        },

        // Paperless-Suche über das PHP-Backend absetzen
        async findMatchSuggestions(searchQuery) {
            let cleanSubject = searchQuery
                .replace(/^(Re|Aw|Fwd|Forward|Antwort|Betreff):\s*/i, '')
                .replace(/[[\]().,;:!?\-_]/g, ' ')
                .trim();

            cleanSubject = cleanSubject.split(/\s+/).slice(0, 4).join(' ');

            try {
                const url = `api.php?action=paperless_search&query=${encodeURIComponent(cleanSubject)}&date=${encodeURIComponent(this.currentMailDate)}`;
                const data = await safeFetch(url);
                if (data && data.success) {
                    this.paperlessMatches = data.details.documents || [];
                }
            } catch (err) {
                console.error("Fehler bei Paperless Suche:", err);
            }
        },

        // Mail mit existierendem Paperless Dokument verknüpfen
        async linkToPaperless(docId) {
            if (!confirm(`Möchtest du diese Mail wirklich mit dem Paperless-Dokument #${docId} verknüpfen?`)) return;

            const fd = new FormData();
            fd.append('action', 'paperless_match');
            fd.append('id', this.currentHash);
            fd.append('paperless_id', docId);

            try {
                const data = await safeFetch('api.php', { method: 'POST', body: fd });
                if (data && data.success) {
                    showNotify('Paperless', 'Erfolgreich verknüpft!', 'success');
                    
                    // Lokalen State updaten (UI reagiert sofort!)
                    this.currentMail.is_dms = true;
                    this.currentMail.paperless_id = docId;
                    this.paperlessMatches = [];

                    // Direkt den Verlaufs-Baum für den Absender nachladen
                    if (this.currentMailSender) {
                        this.loadPaperlessTreeByMail(this.currentMailSender);
                    }
                }
            } catch (err) {
                console.error("Match-Fehler:", err);
            }
        },

        async loadPaperlessTreeByMail(mailHash) {
            this.paperlessTree = {}; 
            this.paperlessStatusText = ''; 
            
            try {
                const data = await safeFetch(`api.php?action=paperless_mail_tree&hash=${encodeURIComponent(mailHash)}`);
                
                if (data.success) {
                    if (data.status === 'info_error') {
                        // 🚀 Hier landet die Nachricht deiner "throw new Exception" aus dem Backend!
                        this.paperlessStatusText = data.error;
                        this.paperlessTree = {};
                    } else {
                        this.paperlessTree = JSON.parse(JSON.stringify(data.details.tree));
                        //console.log("Paperless-Baum für Mail geladen:", this.paperlessTree);
                    }
                }
            } catch (err) {
                console.error('Kritischer Netzwerk- oder Parserfehler:', err);
            }
        },
        
        // Historien-Baum des Absenders aus Paperless holen
        async loadPaperlessTree(senderName) {
            try {
                const data = await safeFetch(`api.php?action=paperless_tree&sender=${encodeURIComponent(senderName)}`);
                if (data && data.success) {
                    this.paperlessTree = data.details.tree || {};
                }
            } catch (err) {
                console.error("Fehler beim Laden des Paperless-Baums:", err);
            }
        },

        // DMS Export (Verschieben mit Tag 'Recht')
        async moveToDMS(mail, tag = 'Recht') {
            const fd = new FormData();
            fd.append('action', 'dms');
            fd.append('id', mail.hash);
            fd.append('tag', tag);

            try {
                await safeFetch('api.php', { method: 'POST', body: fd });
                showNotify('DMS Export', `Erfolgreich nach ${tag} übertragen.`, 'success');
                
                // Reaktiv als verarbeitet markieren
                mail.paperless_id = true;
            } catch (err) {
                console.error("DMS Export Fehler:", err);
            }
        },

        // PDF Erstellung triggern
        async exportPdf(hash) {
            const fd = new FormData();
            fd.append('action', 'pdf');
            fd.append('id', hash);
            
            try {
                const data = await safeFetch('api.php', { method: 'POST', body: fd });
                showNotify('PDF Export', 'Datei erstellt: ' + data.details.file, 'success');
            } catch (err) {}
        },

        /** 
         * Gibt es in Paperless eine Mail mit dem Betreff?
         */
        async showInPaperless(hash) {
            try {
                const data = await safeFetch(`api.php?action=syncPaperless&hash=${encodeURIComponent(hash)}`);
                //showNotify('PDF Export', 'Datei erstellt: ' + data.details.file, 'success');
            }catch(err) {
                console.error("Fehler beim Abrufen der Mail für Paperless-Öffnung:", err);
            }
        },

        // Mail löschen
        async deleteMail(mail) {
            if (!confirm('Diese Mail wirklich löschen?')) return;

            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', mail.hash);

            try {
                await safeFetch('api.php', { method: 'POST', body: fd });
                
                // Mail aus dem lokalen Array filtern -> Verschwindet sofort aus dem DOM
                this.mails = this.mails.filter(m => m.hash !== mail.hash);
                
                if (this.currentHash === mail.hash) {
                    this.currentMail = null;
                    this.currentHash = null;
                    this.showMobilePreview = false;
                }
                showNotify('Erfolg', 'Mail gelöscht.', 'success');
            } catch (err) {}
        },

        // Manueller IMAP-Sync über Navbar-Button
        async triggerNavbarSync(event) {
            // Absicherung: Falls kein Event übergeben wurde (z.B. durch Auto-Sync), 
            // suchen wir nicht nach einem Button-Target.
            const btn = event && event.target ? event.target : null;
            
            this.loading = true;
            if (btn) btn.disabled = true;

            try {
                const response = await safeFetch(`api.php?action=sync&folder=${encodeURIComponent(this.currentFolder)}`);
                
                // WICHTIG: Sicherstellen, dass safeFetch die Daten geliefert hat
                // Wenn dein safeFetch bereits das JSON liefert, reicht: const data = response;
                const data = await response.json(); 

                // 🚨 ABSICHERUNG: Prüfen, ob data überhaupt existiert, bevor wir auf .added zugreifen!
                if (data && data.success) {
                    console.log(`Sync erfolgreich. ${data.added || 0} neue Mails hinzugefügt.`);
                    // Ordner neu laden, damit die neuen Mails erscheinen
                    await this.loadMails(); 
                } else {
                    console.error("Sync-Fehler laut API:", data ? data.error : "Keine Antwortdaten erhalten");
                }

            } catch (err) {
                console.error("Fehler beim Navbar-Sync:", err);
            } finally {
                this.loading = false;
                if (btn) btn.disabled = false;
            }
        },

        // System- & Home Assistant Status abrufen
        async loadSystemStatus() {
            try {
                const data = await safeFetch('api.php?action=system_status');
                if (data && data.success && data.details) {
                    this.haInfoHtml = data.details.ha_html || '<span class="text-success">Verbunden</span>';
                    this.serverInfoHtml = data.details.server_html || '<span class="text-success">Online</span>';
                }
            } catch (err) {
                this.haInfoHtml = '<span class="text-danger">Fehler</span>';
                this.serverInfoHtml = '<span class="text-danger">Fehler</span>';
            }
        },

        async trackFoldersSilent(showLoadingIndicator, folderName = 'INBOX') {
            
            if (showLoadingIndicator) this.loading = true;
            
            try {
                // Hier wird der Ordner dynamisch als Query-Parameter an die API geschickt
                const data = await safeFetch(`api.php?action=sync&folder=${folderName}`);
                
                if (data && data.success) {
                    // Optionale Erfolgs-Logik (z.B. Mails neu rendern, falls man im aktuellen Ordner steht)
                    if (this.currentFolder === folderName) {
                        this.loadMailsSilent();
                    }
                }
            } catch (e) {
                console.error("Sync-Fehler im Frontend:", e);
            } finally {
                if (showLoadingIndicator) this.loading = false;
            }
        },

        // Startet die Schleife für den Hintergrund-Sync sauber und sicher
        startBackgroundSync() {
            // Sicherer Mail-Refresh (wartet, bis der alte fertig ist)
            const refreshMails = async () => {
                await this.loadMailsSilent();
                setTimeout(refreshMails, 30000); // Erst nach 30 Sek neu starten
            };
            refreshMails();

            // Ordner-Sync-Schleife
            const runFolderSyncLoop = async () => {
                const foldersToSync = ['INBOX', 'DMS', 'DMSDone'];
                for (const folder of foldersToSync) {
                    try {
                        // Wartet brav, bis der Ordner fertig gesynct ist, bevor der nächste dran ist
                        await this.trackFoldersSilent(false, folder);
                    } catch (err) {
                        console.error(err);
                    }
                }
                setTimeout(runFolderSyncLoop, 60000); // 60 Sek Pause nach ALLEN Ordnern
            };
            setTimeout(runFolderSyncLoop, 5000);
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


        // Infinite Scroll Listener
        initInfiniteScroll() {
            const scrollContainer = document.getElementById('mail-list');
            if (!scrollContainer) return;

            if (!scrollContainer) {
                console.error("❌ [Scroll-Setup] Element mit ID #mail-list wurde im DOM nicht gefunden!");
                return;
            }

            scrollContainer.addEventListener('scroll', () => {
                // 🚨 SCHUTZWALL: Wenn bereits geladen wird oder Schluss ist -> SOFORT ABBRECHEN!
                if (this.loading || !this.hasMore) {
                    return;
                }

                // Mathematisch sichere Berechnung des unteren Rands
                const totalScrollable = scrollContainer.scrollHeight;
                const currentScrollPosition = scrollContainer.scrollTop + scrollContainer.clientHeight;
                
                // Wenn wir weniger als 150 Pixel vom Boden entfernt sind, laden wir nach
                if (totalScrollable - currentScrollPosition <= 150) {
                    console.log(`[Infinite Scroll] Lade nächste Seite: ${this.page}`);
                    this.loadMails(true);
                }
            });
        },

        // Gestensteuerung (Swipe)
        initSwipe(el, mail) {
            let touchStartX = 0;
            let touchStartY = 0; // Auch Y-Achse messen gegen vertikales Scrollen
            let touchEndX = 0;
            let touchEndY = 0;

            el.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
                touchStartY = e.changedTouches[0].screenY;
            }, { passive: true });

            el.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                touchEndY = e.changedTouches[0].screenY;
                
                const diffX = touchStartX - touchEndX;
                const diffY = touchStartY - touchEndY;
                const threshold = 100; // Mindest-Pixel für einen Swipe

                // WICHTIG: Wenn sich der Finger kaum bewegt hat, war es ein KLICK!
                // In diesem Fall brechen wir ab und lassen Alpine das @click ausführen.
                if (Math.abs(diffX) < 10 && Math.abs(diffY) < 10) {
                    return; 
                }

                // Nur wenn es ein echter horizontaler Swipe war:
                if (Math.abs(diffX) > Math.abs(diffY)) {
                    // Swipe nach links -> DMS (Recht)
                    if (diffX > threshold) {
                        el.style.transition = 'transform 0.2s ease, opacity 0.2s ease';
                        el.style.transform = 'translateX(-100%)';
                        el.style.opacity = '0';
                        setTimeout(() => {
                            this.moveToDMS(mail, 'Recht');
                            this.mails = this.mails.filter(m => m.hash !== mail.hash);
                        }, 200);
                    }
                    
                    // Swipe nach rechts -> Als verarbeitet markieren
                    if (diffX < -threshold) {
                        mail.is_dms = true;
                        showNotify('Info', 'Mail markiert', 'info');
                    }
                }
            }, { passive: true });
        }
    }));
});


// =========================================================================
// GLOBALE CORE-FUNKTIONEN (Sicheres HTTP-Handling & Push-Benachrichtigungen)
// =========================================================================

async function safeFetch(url, options = {}) {
    try {
        const response = await fetch(url, options);

        // 1. HTTP-Statusfehler abfangen (500, 504, etc.) mit unserem StatusText-Fallback
        if (!response.ok) {
            const httpStatusMessages = {
                400: 'Ungültige Anfrage (Bad Request)',
                401: 'Nicht autorisiert (Token fehlt oder ungültig)',
                403: 'Zugriff verweigert (Forbidden)',
                404: 'API-Endpunkt nicht gefunden',
                500: 'PHP-Absturz im Backend (Internal Server Error)',
                502: 'Bad Gateway', 503: 'Server überlastet',
                504: 'Gateway Timeout (Server antwortet nicht rechtzeitig)'
            };
            const errorText = response.statusText.trim() || httpStatusMessages[response.status] || 'Unbekannter Serverfehler';
            this.showNotify("Fehler", errorText, 'danger');
            throw new Error(`Server-Fehler ${response.status}: ${errorText}`);

            
        }

        const data = await response.json();

        // 2. Globale Prüfung der standardisierten PHP-Response
        if (data && typeof data === 'object') {
            
            // Fall A: Ein harter Backend-Fehler (success ist false)
            if (data.success === false) {
                this.showNotify("FEHLER", data.error, "danger");
                throw new Error(data.error || 'Ein unbekannter Backend-Fehler ist aufgetreten.');
            }
            
            // Fall B: Ein weicher Fehler / Zustand (z.B. "Noch nicht im DMS")
            // Wir hängen die Info an das Datenobjekt, damit das Frontend weiß, dass es kein Crash ist
            if (data.status === 'info_error' || data.status === 'not_archived') {
                data.isUiNotice = true;
                data.uiMessage = data.error || data.message || data.details;
            }
        }

        return data; // Gibt die sauberen Daten an die aufrufende Funktion zurück

    } catch (error) {
        console.error(`🚨 safeFetch-Zentrale abgefangen für [${url}]:`, error);
        // Wir reichen den Fehler weiter, damit der lokale catch-Block reagieren kann
        throw error;
    }
}

function showNotify(title, message, type = 'danger') {
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '1055';
        document.body.appendChild(container);
    }

    // 🚀 FIX: Farb-Mapping für Tailwind ODER Bootstrap Absicherung
    const colorMapping = {
        'danger': 'bg-red-600 bg-danger',       // Tailwind & Bootstrap Fallback
        'success': 'bg-green-600 bg-success',
        'info': 'bg-blue-600 bg-info',
        'secondary': 'bg-gray-600 bg-secondary',
        'warning': 'bg-yellow-500 text-dark bg-warning'
    };

    // Standard auf danger, falls ein ungültiger Typ übergeben wird
    const bgClass = colorMapping[type] || colorMapping['danger'];

    const toast = document.createElement('div');
    // 🚀 HIER nutzen wir jetzt die fertige bgClass Variable:
    toast.className = `toast show align-items-center text-white ${bgClass} border-0 mb-2 p-2 rounded shadow-lg`;
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <div class="d-flex justify-content-between align-items-center" style="display: flex; justify-content: space-between; align-items: center;">
            <div class="toast-body" style="padding: 8px;">
                <strong>${title}</strong><br>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2" onclick="this.closest('.toast').remove()" style="background: none; border: none; color: white; cursor: pointer; font-size: 20px; padding-right: 10px;">✕</button>
        </div>
    `;
    
    container.appendChild(toast);
    
    // Auto-Löschen nach 5 Sekunden
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease';
        setTimeout(() => toast.remove(), 500);
    }, 5000);
}