/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

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
            let res = await safeFetch('api.php?action=get_settings');
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
            console.error("Fehler beim Laden der Einstellungen");
            console.error(err);
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
                console.error('Verbindung fehlgeschlagen');
                console.error(res.error);
                this.showMsg('Verbindung fehlgeschlagen: ' + res.error, 'error');
            }
        } catch (err) {
            this.showMsg('Verbindungsfehler.' + err, 'error');
        } finally {
            this.loadingFolders = false;
        }
    },
    async saveSettings() {
        this.loading = true;
        try {
            const res = await apiCall('save_settings', this.form, 'POST');
            
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