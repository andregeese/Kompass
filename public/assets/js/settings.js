const settingsComponent = () => ({
    loadingFolders: false,
    loading: false,
    folders: [], // WICHTIG: Muss im State initialisiert werden
    message: { text: '', type: 'success' },
    form: {
        imap_host: '', imap_port: '993', imap_user: '', imap_password: '',
        paperless_url: '', paperless_name: '', paperless_token: '',
        sync_folders: [] // WICHTIG: Muss als Array vorliegen
    },

    async initSettings() {
        try {
            let res = await fetch('api.php?action=get_settings').then(r => r.json());
            if (res.success) {
                // Zusammenführen und existierende Daten behalten
                this.form = { ...this.form, ...res.details.settings };
                
                // Stelle sicher, dass sync_folders ein Array ist
                if (typeof this.form.sync_folders === 'string') {
                    this.form.sync_folders = JSON.parse(this.form.sync_folders);
                } else if (!this.form.sync_folders) {
                    this.form.sync_folders = [];
                }

                // Passwörter leeren
                this.form.imap_password = '';
                this.form.paperless_token = '';
                
                // Falls bereits Ordner gespeichert sind, lade sie direkt
                if (this.form.sync_folders.length > 0) this.folders = this.form.sync_folders;
            } else {
                this.showMsg(res.error, 'error');
            }
        } catch (err) {
            this.showMsg('Fehler beim Laden der Einstellungen.', 'error');
        }
    },

    async fetchImapFolders() {
        this.loadingFolders = true;
        try {
            let fd = new FormData();
            fd.append('action', 'test_imap');
            fd.append('imap_host', this.form.imap_host);
            fd.append('imap_port', this.form.imap_port);
            fd.append('imap_user', this.form.imap_user);
            fd.append('imap_password', this.form.imap_password);

            let res = await safeFetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
            
            if (res.success) {
                this.folders = res.folders; // Erwartet ['INBOX', 'Archiv', ...]
                this.showMsg('Verbindung erfolgreich!', 'success');
            } else {
                this.showMsg('Verbindung fehlgeschlagen: ' + res.error, 'error');
            }
        } catch (err) {
            this.showMsg('Verbindungsfehler.', 'error');
        } finally {
            this.loadingFolders = false;
        }
    },

    async saveSettings() {
        this.loading = true;
        try {
            let fd = new FormData();
            fd.append('action', 'save_settings');
            for (let key in this.form) {
                // sync_folders als JSON String für die DB
                let value = key === 'sync_folders' ? JSON.stringify(this.form[key]) : this.form[key];
                fd.append(key, value);
            }
            
            let res = await fetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
            this.loading = false;
            
            if (res.success) {
                this.showMsg('Einstellungen erfolgreich gespeichert!', 'success');
                this.initSettings();
            } else {
                this.showMsg(res.error, 'error');
            }
        } catch (err) {
            this.loading = false;
            this.showMsg('Verbindungsfehler.', 'error');
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