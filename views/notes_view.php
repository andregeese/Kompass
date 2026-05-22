<div x-data="notesView" class="flex-1 overflow-y-auto p-4 md:p-6 max-w-3xl mx-auto w-full space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center space-x-2"><span>📝</span> <span>Notizbuch & Offline Memos</span></h2>
        <span class="text-[10px] uppercase font-semibold tracking-wider bg-blue-100 dark:bg-blue-900/40 text-blue-600 px-2.5 py-1 rounded-full">Automatischer Sync</span>
    </div>

    <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm space-y-3">
        <textarea x-model="newNote.content" placeholder="Schreibe eine schnelle Notiz oder Gesprächsnotiz... (Wird bei Funkloch im Cache gesichert)" rows="3" 
                  class="w-full bg-slate-50 dark:bg-gray-750 border-0 rounded-xl p-3 text-sm focus:ring-2 focus:ring-blue-500 text-slate-800 dark:text-white placeholder-slate-400"></textarea>
        <div class="flex justify-end">
            <button @click="addNote()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold text-xs px-4 py-2 rounded-xl transition-colors shadow-sm shadow-blue-500/10">💾 Memo sichern</button>
        </div>
    </div>

    <div class="space-y-2.5">
        <template x-for="note in notes" :key="note.id">
            <div class="p-3.5 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm flex items-start justify-between transition-all" :class="{'opacity-50 line-through bg-slate-50': note.status === 'completed'}">
                <div class="flex items-start space-x-3 flex-1">
                    <input type="checkbox" :checked="note.status === 'completed'" @change="toggleNoteStatus(note.id)" class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <p class="text-xs text-slate-700 dark:text-gray-200 leading-relaxed whitespace-pre-wrap flex-1" x-text="note.content"></p>
                </div>
                <span x-show="note.id.toString().startsWith('local_')" class="text-[9px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-bold uppercase tracking-wider ml-2 shrink-0">Lokal</span>
            </div>
        </template>
    </div>
</div>