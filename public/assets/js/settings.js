/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

const settingsComponent = () => ({
    loadingFolders: false,
    loading: false,
    folders: [], // WICHTIG: Muss im State initialisiert werden
    message: { text: '', type: 'success' },
    form: {
        imap_host: '', 
        imap_port: '993', 
        imap_user: '', 
        imap_password: '',
        paperless_url: '', 
        paperless_name: '', 
        paperless_token: '',
        sync_folders: [],
        // HIER DIE ERWEITERUNG: Die neuen Felder im State registrieren
        sent_folder: '',
        dms_folder: ''
    },
    
    async initSettings() {
        try {
            let res = await apiCall('get_settings', {}, 'POST'); 
            
            if (res.success && res.details && res.details?.settings) {
                const dbSettings = res.details.settings;

                // Nur die Felder mappen, die wir im form-Objekt definiert haben
                for (let key in this.form) {
                    if (dbSettings[key.toUpperCase()] !== undefined) {
                        this.form[key] = dbSettings[key.toUpperCase()];
                    }
                }
                
                // Stelle sicher, dass sync_folders ein sauberes Array ist
                if (typeof this.form.sync_folders === 'string') {
                    try {
                        this.form.sync_folders = JSON.parse(this.form.sync_folders);
                    } catch (e) {
                        this.form.sync_folders = [];
                    }
                } else if (!this.form.sync_folders) {
                    this.form.sync_folders = [];
                }
                
                // KORREKTUR/ERWEITERUNG: Damit auch die Dropdowns für die Spezialordner 
                // sofort beim Laden Optionen anzeigen, befüllen wir das Array mit allen bekannten Ordnern
                const trackedFolders = [...this.form.sync_folders];
                if (this.form.sent_folder) trackedFolders.push(this.form.sent_folder);
                if (this.form.dms_folder) trackedFolders.push(this.form.dms_folder);
                
                // Duplikate via Set entfernen
                if (trackedFolders.length > 0) {
                    this.folders = [...new Set(trackedFolders)];
                }

                // Aus Sicherheitsgründen die Passwörter aus dem State leeren
                //this.form.imap_password = '';
                //this.form.paperless_token = '';
            } else {
                if (res.error) this.showMsg(res.error, 'error');
            }
        } catch (err) {
            console.error("Fehler beim Laden der Einstellungen");
            console.error(err);
            this.showMsg('Fehler beim Laden der Einstellungen.', 'error');
        }
    },
    async fetchImapFolders() {
        this.loadingFolders = true;
        try {
            // Nutzt du apiCall oder safeFetch? apiCall ist oft sauberer für JSON Payloads.
            const payload = {
                action: 'test_imap',
                imap_host: this.form.imap_host,
                imap_port: this.form.imap_port,
                imap_user: this.form.imap_user,
                imap_password: this.form.imap_password
            };

            let res = await apiCall('test_imap', payload, 'POST');
            
            if (res.success) {
                // Erwartet ['INBOX', 'Archiv', ...]
                // Wichtig: Wir fügen die neuen Ordner ein, behalten aber die Selektion
                this.folders = res?.folders  || [];
                this.showMsg('Verbindung erfolgreich! Ordner geladen.', 'success');
            } else {
                this.showMsg('Verbindung fehlgeschlagen: ' + (res.error || 'Unbekannter Fehler'), 'error');
            }
        } catch (err) {
            this.showMsg('Verbindungsfehler beim IMAP-Test.', 'error');
        } finally {
            this.loadingFolders = false;
        }
    },
    async saveSettings() {
        this.loading = true;
        this.message.text = ''; // altes Feedback löschen

        try {
            const response = await apiCall('save_settings', {'data': this.form}, 'POST');
            
            if (response.success) {
                this.showMsg('Einstellungen erfolgreich gespeichert!', 'success');
                // Optional: initSettings() nochmal aufrufen, um State aufzuräumen (Passwörter leeren)
                this.initSettings();
            } else {
                this.showMsg(res.error || 'Fehler beim Speichern', 'error');
            }
        } catch (err) {
            this.showMsg('Verbindungsfehler beim Speichern.' + err.message, 'error');
        } finally {
            this.loading = false;
        }
    },
    showMsg(text, type) {
        this.message = { text, type };
        if (type === 'success') setTimeout(() => { this.message.text = ''; }, 4000);
    }
});
// Registrierung
document.addEventListener('alpine:init', () => {
    window.Alpine.data('settingsComponent', settingsComponent);
});