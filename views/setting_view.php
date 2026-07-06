<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
?>
<div x-data="settingsComponent()" x-init="initSettings()" class="flex-1 overflow-y-auto p-4 md:p-6 mx-auto w-full space-y-4">
    
    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <span class="flex items-center justify-center transition-transform duration-300 group-hover:rotate-45">
                <svg class="w-8 h-8 md:w-6 md:h-6 shrink-0">
                    <use href="public/assets/sprite.svg#icon-setting"></use>
                </svg>
            </span>
            
            <span>System- & E-Mail-Einstellungen</span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">Verwalte deine IMAP-Verbindung und Paperless-Zugangsdaten für diesen Mandanten.</p>
        
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
            <div class="mt-4">
                <button type="button" 
                        @click="fetchImapFolders()"
                        :disabled="loadingFolders"
                        class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold uppercase tracking-wider transition-all disabled:opacity-50">
                    <span x-text="loadingFolders ? 'Verbinde...' : 'Verbindung testen & Ordner laden'"></span>
                </button>
            </div>

            <div class="mt-4" x-show="folders.length > 0">
                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Sync-Ordner auswählen</div>
                <div class="bg-slate-950 border border-slate-800 rounded-xl p-3 space-y-2 max-h-48 overflow-y-auto">
                    <template x-for="folder in folders" :key="folder">
                        <label class="flex items-center gap-3 cursor-pointer p-2 hover:bg-slate-900 rounded-lg transition-all">
                            <input type="checkbox" 
                                :value="folder" 
                                x-model="form.sync_folders"
                                class="accent-blue-500 w-4 h-4">
                            <span class="text-sm text-slate-300" x-text="folder"></span>
                        </label>
                    </template>
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-800" x-show="folders.length > 0">
                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">System-Ordner zuweisen</div>
                
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">Ordner für gesendete Mails</label>
                        <select x-model="form.sent_folder" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all appearance-none">
                            <option value="">-- Keine Erkennung (Inaktiv) --</option>
                            <template x-for="folder in folders" :key="folder">
                                <option :value="folder" x-text="folder" :selected="form.sent_folder === folder"></option>
                            </template>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">Wichtig für die Erkennung von Empfängern (z.B. "Sent Messages").</p>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-400 mb-1">DMS / Archiv Ordner (Optional)</label>
                        <select x-model="form.dms_folder" 
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 outline-none text-sm transition-all appearance-none">
                            <option value="">-- Kein DMS-Ordner vorhanden --</option>
                            <template x-for="folder in folders" :key="folder">
                                <option :value="folder" x-text="folder" :selected="form.dms_folder === folder"></option>
                            </template>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">Mails in diesem Ordner erhalten keinen "Ab ins DMS"-Button mehr.</p>
                    </div>
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