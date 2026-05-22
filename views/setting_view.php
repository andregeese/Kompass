<div x-data="settingsComponent()" x-init="initSettings()" class="p-6 max-w-4xl mx-auto space-y-6 overflow-y-auto h-full">
    
    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight">System- & E-Mail-Einstellungen</h1>
            <p class="text-xs text-slate-400 mt-1">Verwalte deine IMAP-Verbindung und Paperless-Zugangsdaten für diesen Mandanten.</p>
        </div>
        <span class="text-3xl">⚙️</span>
    </div>

    <div x-show="message.text" 
         :class="message.type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border-red-500/20 text-red-400'"
         class="p-4 rounded-xl border text-xs" x-cloak x-text="message.text">
    </div>

    <form @submit.prevent="saveSettings()" class="space-y-6">
        
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800 pb-2 flex items-center gap-2">
                <span>📧</span> IMAP Posteingang (z.B. iCloud)
            </h2>
            
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">IMAP Server (Host)</label>
                    <input type="text" x-model="form.imap_host" placeholder="imap.mail.me.com" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Port</label>
                    <input type="number" x-model="form.imap_port" placeholder="993" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">E-Mail / Benutzername</label>
                    <input type="email" x-model="form.imap_user" placeholder="dein-name@icloud.com" required
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">App-spezifisches Passwort</label>
                    <input type="password" x-model="form.imap_password" placeholder="•••• •••• •••• ••••"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
                    <p class="text-[10px] text-slate-500 mt-1">Wird verschlüsselt gespeichert. Leer lassen, um es nicht zu ändern.</p>
                </div>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 shadow-xl">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 border-b border-slate-800 pb-2 flex items-center gap-2">
                <span>📄</span> Paperless-ngx Server
            </h2>
            
            <div>
                <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Paperless URL</label>
                <input type="url" x-model="form.paperless_url" placeholder="https://paperless.gfw5home.com" required
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Archiv Name</label>
                    <input type="text" x-model="form.paperless_name" placeholder="Hauptarchiv"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">API Token</label>
                    <input type="password" x-model="form.paperless_token" placeholder="Dein API Token"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all">
                    <p class="text-[10px] text-slate-500 mt-1">Wird verschlüsselt gespeichert. Leer lassen, um es nicht zu ändern.</p>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit" :disabled="loading" 
                    class="bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 text-white font-bold py-2.5 px-6 rounded-xl transition shadow-lg shadow-blue-950/30 text-sm flex items-center gap-2">
                <span x-show="loading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span x-text="loading ? 'Speichere...' : 'Einstellungen übernehmen'"></span>
            </button>
        </div>
    </form>
</div>

<script>
function settingsComponent() {
    return {
        loading: false,
        message: { text: '', type: 'success' },
        form: {
            imap_host: '', imap_port: '993', imap_user: '', imap_password: '',
            paperless_url: '', paperless_name: '', paperless_token: ''
        },
        async initSettings() {
            try {
                let res = await fetch('api.php?action=get_settings').then(r => r.json());
                if (res.success) {
                    this.form = { ...this.form, ...res.details.settings };
                    // Passwörter leeren, da wir sie zur Sicherheit nicht im Klartext zurückgeben
                    this.form.imap_password = '';
                    this.form.paperless_token = '';
                } else {
                    this.showMsg(res.error, 'error');
                }
            } catch (err) {
                this.showMsg('Fehler beim Laden der Einstellungen.', 'error');
            }
        },
        async saveSettings() {
            this.loading = true;
            this.message.text = '';
            try {
                let fd = new FormData();
                fd.append('action', 'save_settings');
                for (let key in this.form) {
                    fd.append(key, this.form[key]);
                }
                
                let res = await fetch('api.php?action=save_settings', { method: 'POST', body: fd }).then(r => r.json());
                this.loading = false;
                
                if (res.success) {
                    this.showMsg('Einstellungen erfolgreich gespeichert!', 'success');
                    // Neu initialisieren, um Passwörter im Formular wieder zu maskieren
                    this.initSettings();
                } else {
                    this.showMsg(res.error, 'error');
                }
            } catch (err) {
                this.loading = false;
                this.showMsg('Verbindungsfehler beim Speichern.', 'error');
            }
        },
        showMsg(text, type) {
            this.message = { text, type };
            if (type === 'success') {
                setTimeout(() => { this.message.text = ''; }, 4000);
            }
        }
    }
}
</script>