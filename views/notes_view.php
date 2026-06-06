<div x-data="notesView" class="flex-1 overflow-y-auto p-4 md:p-6 max-w-3xl mx-auto w-full space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center space-x-2">
            <span>📝</span> 
            <span>Notizbuch & Memos</span>
        </h2>
        <span class="text-[10px] uppercase font-bold tracking-wider bg-emerald-100 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 px-2.5 py-1 rounded-full border border-emerald-200 dark:border-emerald-900/40 flex items-center gap-1.5">
            <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
            Cloud Sync
        </span>
    </div>

    <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm space-y-3 transition-all duration-200 focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500">
        
        <template x-if="newNote.uid">
            <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 px-3 py-1.5 rounded-xl text-xs border border-blue-100 dark:border-blue-900/40 animate-in fade-in slide-in-from-top-1 duration-200">
                <div class="flex items-center space-x-1.5 font-medium">
                    <span>🔗</span>
                    <span>Verknüpft mit E-Mail UID <span class="font-mono bg-blue-100 dark:bg-blue-900 px-1 py-0.5 rounded" x-text="newNote.uid"></span> im Ordner <span class="underline" x-text="newNote.folder"></span></span>
                </div>
                <button @click="newNote.uid = ''; newNote.folder = ''; const cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname + '?view=notes'; window.history.pushState({}, '', cleanUrl);" 
                        class="text-blue-400 hover:text-blue-600 dark:hover:text-blue-200 p-0.5 rounded-lg transition-colors">
                    ✕
                </button>
            </div>
        </template>

        <textarea x-ref="quickNoteArea" x-model="newNote.content" 
                  placeholder="Schreibe eine schnelle Notiz oder Gesprächsnotiz... (Wird bei Funkloch im Cache gesichert)" rows="3" 
                  class="w-full bg-slate-50 dark:bg-gray-750 border-0 rounded-xl p-3 text-sm focus:ring-0 text-slate-800 dark:text-white placeholder-slate-400 resize-none focus:outline-none"></textarea>
        
        <div class="flex justify-between items-center pt-1">
            <select x-model="newNote.category" class="text-xs bg-slate-50 dark:bg-gray-750 text-slate-600 dark:text-gray-300 border border-slate-200 dark:border-gray-600 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="general">📌 Allgemein</option>
                <option value="todo">📞 Wiedervorlage</option>
                <option value="important">🔥 Wichtig</option>
            </select>

            <button @click="addNote()" 
                    :disabled="!newNote.content.trim()"
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold text-xs px-4 py-2 rounded-xl transition-colors shadow-sm shadow-blue-500/10 flex items-center space-x-1.5">
                <span>💾</span>
                <span>Memo sichern</span>
            </button>
        </div>
    </div>

    <div class="space-y-4">
        <div class="bg-slate-50 dark:bg-gray-850 p-4 rounded-2xl border border-slate-200/60 dark:border-gray-750">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-gray-400 flex items-center gap-1.5">
                    <span>✉️</span> Letzte Posteingänge
                </h3>
                <button @click="loadRecentEmails()" class="text-xs text-blue-600 hover:underline" :disabled="loadingEmails">
                    <span x-show="!loadingEmails">🔄</span>
                    <span x-show="loadingEmails" class="inline-block animate-spin">⏳</span>
                </button>
            </div>

            <div class="space-y-2">
                <template x-for="email in recentEmails" :key="email.uid">
                    <div @click="linkEmail(email)" 
                            class="p-2.5 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-750 shadow-xs cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 hover:bg-blue-50/30 dark:hover:bg-blue-950/10 transition-all group flex flex-col space-y-1"
                            :class="{'border-blue-500 ring-2 ring-blue-500/10 bg-blue-50/40 dark:bg-blue-950/20': newNote.uid === email.uid}">
                        
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-semibold text-slate-700 dark:text-gray-300 truncate max-w-[130px]" x-text="email.from_name"></span>
                            <span class="text-[9px] text-slate-400 font-mono" x-text="email.folder"></span>
                        </div>
                        
                        <p class="text-xs text-slate-600 dark:text-gray-400 line-clamp-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors" x-text="email.subject"></p>
                        
                        <div class="flex items-center justify-between pt-1 text-[10px] text-slate-400">
                            <span x-text="new Date(email.received_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})"></span>
                            <span class="text-blue-600 dark:text-blue-400 font-medium opacity-0 group-hover:opacity-100 transition-opacity flex items-center gap-0.5">
                                📎 Verknüpfen
                            </span>
                        </div>
                    </div>
                </template>

                <template x-if="recentEmails.length === 0 && !loadingEmails">
                    <p class="text-xs text-slate-400 text-center py-4 italic">Keine aktuellen Mails gefunden.</p>
                </template>
            </div>
        </div>
    </div>

    <div class="space-y-2.5">
        <template x-for="note in notes" :key="note.id">
            <div class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm flex items-start justify-between transition-all duration-200 hover:border-slate-300 dark:hover:border-gray-600" 
                 :class="{'opacity-50 bg-slate-50/50 dark:bg-gray-850/40 line-through': note.status === 'completed'}">
                
                <div class="flex items-start space-x-3 flex-1 min-w-0">
                    <input type="checkbox" :checked="note.status === 'completed'" @change="toggleNoteStatus(note.id)" 
                           class="mt-1 h-4 w-4 rounded border-slate-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500 bg-transparent dark:checked:bg-blue-600">
                    <div class="flex-1 min-w-0 space-y-1">
                        <p class="text-xs text-slate-700 dark:text-gray-200 leading-relaxed whitespace-pre-wrap breakdown-words" x-text="note.content"></p>
                        
                        <template x-if="note.uid">
                            <div class="text-[10px] text-slate-400 dark:text-gray-500 flex items-center gap-1 font-mono">
                                <span>✉️ Mail-Ref: ID</span>
                                <span class="bg-slate-100 dark:bg-gray-750 px-1 py-0.5 rounded text-slate-500 dark:text-gray-400" x-text="note.uid"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <span x-show="note.id.toString().startsWith('local_')" class="text-[9px] bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider ml-2 shrink-0 border border-amber-200 dark:border-amber-900/40">Lokal</span>
            </div>
        </template>
    </div>
</div>