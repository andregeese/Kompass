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

<div x-data="notesView" class="flex-1 h-full overflow-y-auto w-full max-w-5xl mx-auto p-4 md:p-6 space-y-6">
    
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
    <div class="bg-white dark:bg-gray-800 p-5 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm space-y-4 transition-all focus-within:ring-2 focus-within:ring-blue-500/20 focus-within:border-blue-500">
        
        <template x-if="newNote.uid">
            <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 px-3 py-1.5 rounded-xl text-xs border border-blue-100 dark:border-blue-900/40">
                <div class="flex items-center space-x-1.5 font-medium truncate">
                    <span>🔗</span>
                    <span class="truncate">Verknüpft mit Mail-UID <span class="font-mono bg-blue-100 dark:bg-blue-900 px-1 py-0.5 rounded" x-text="newNote.uid"></span> (<span x-text="newNote.folder"></span>)</span>
                </div>
                <button @click="newNote.uid = ''; newNote.folder = ''; const cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname + '?view=notes'; window.history.pushState({}, '', cleanUrl);" 
                        class="text-blue-400 hover:text-blue-600 dark:hover:text-blue-200 p-1 rounded-lg transition-colors shrink-0 ml-2">
                    ✕
                </button>
            </div>
        </template>
        <textarea x-ref="quickNoteArea" x-model="newNote.content" 
                  placeholder="Schreibe eine schnelle Notiz oder Gesprächsnotiz..." rows="3" 
                  class="w-full bg-slate-50 dark:bg-gray-750 border-0 rounded-xl p-3 text-sm focus:ring-0 text-slate-800 dark:text-white placeholder-slate-400 resize-none focus:outline-none"></textarea>
        
        <div class="border-t border-slate-100 dark:border-gray-750/60 pt-3 space-y-2">
            <div class="flex items-center justify-between px-0.5">
                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 flex items-center gap-1.5">
                    <span>✉️</span> E-Mail verknüpfen
                </label>
                <button @click="loadRecentEmails()" type="button" class="text-[11px] text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1" :disabled="loadingEmails">
                    <span x-show="!loadingEmails">🔄 Laden</span>
                    <span x-show="loadingEmails" class="inline-block animate-spin">⏳</span>
                </button>
            </div>
            <div class="relative" x-init="emailSearch = ''" @input.debounce.300ms="searchEmail();">
                <input type="text" 
                    placeholder="Kontakt filtern..." 
                    x-model="emailSearch"
                    class="w-full text-[11px] bg-slate-50 dark:bg-gray-750 border border-slate-200 dark:border-gray-700 rounded-lg px-2.5 py-1.5 text-slate-800 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
            </div>
            <div class="flex flex-nowrap gap-1 w-full overflow-x-auto pb-1.5 mask-inline-edge scrollbar-none">
                <template x-for="email in recentEmails" :key="email.id">
                    <button type="button" 
                            x-show="!emailSearch || email.display_name.toLowerCase().includes(emailSearch.toLowerCase()) || email.mail.toLowerCase().includes(emailSearch.toLowerCase())"
                            @click="toggleEmailSelection(email)"
                            :class="newNote.ceid === email.id 
                                ? 'bg-emerald-50 border-emerald-300 text-emerald-950 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-300 ring-2 ring-emerald-500/10' 
                                : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100 dark:bg-gray-750 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700'"
                            class="inline-flex items-center gap-1.5 p-1.5 px-2.5 rounded-lg text-[10px] font-medium border transition-all text-left max-w-[150px] shrink-0">
                        
                        <span class="truncate" x-text="email.display_name.replace(/'/g, '')"></span>
                        
                        <span class="text-[9px] font-bold opacity-60 shrink-0" 
                            :class="newNote.ceid === email.id ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                            x-text="newNote.ceid === email.id ? '✕' : '+'"></span>
                    </button>
                </template>
            </div>
            <template x-if="newNote.mail">
                <div class="p-2.5 bg-slate-50/60 dark:bg-gray-850/40 rounded-xl border border-slate-200/60 dark:border-gray-750 space-y-1.5 animate-fadeIn">
                    
                    <div class="flex items-center justify-between text-[9px] font-semibold text-slate-400 uppercase tracking-wider px-0.5">
                        <span class="truncate max-w-[60%]">Mails von: <span class="text-blue-600 dark:text-blue-400 normal-case" x-text="newNote.contact_name.replace(/'/g, '')"></span></span>
                        <span class="font-mono text-right truncate max-w-[40%]" x-text="newNote.contact_email"></span>
                    </div>
                    <div class="space-y-1 max-h-[125px] overflow-y-auto pr-0.5">
                        <template x-for="mail in contactMails.slice(0, 3)" :key="mail.id || mail.uid || index">
                            <button type="button"
                                    @click="newNote.uid === mail.uid ? newNote.uid = '' : newNote.uid = mail.uid"
                                    :class="newNote.uid === mail.uid 
                                        ? 'bg-blue-50/80 border-blue-300 text-blue-950 dark:bg-blue-950/40 dark:border-blue-800 dark:text-blue-300 ring-1 ring-blue-500/20' 
                                        : 'bg-white border-slate-200/80 text-slate-700 hover:bg-slate-50 dark:bg-gray-800 dark:border-gray-700/80 dark:text-gray-300 dark:hover:bg-gray-750'"
                                    class="w-full p-2 rounded-lg border text-left text-[11px] flex items-center justify-between gap-3 transition-all group">
                                
                                <div class="flex items-center space-x-2 min-w-0">
                                    <span class="w-2 h-2 rounded-full border shrink-0 transition-all flex items-center justify-center"
                                        :class="newNote.uid === mail.uid ? 'border-blue-500 bg-blue-500' : 'border-slate-300 dark:border-gray-600 bg-transparent'">
                                        <span class="w-1 h-1 bg-white rounded-full" x-show="newNote.uid === mail.uid"></span>
                                    </span>
                                    <span class="font-medium truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors" x-text="mail.subject || '(Kein Betreff)'"></span>
                                </div>
                                
                                <span class="text-[9px] text-slate-400 dark:text-gray-500 whitespace-nowrap font-mono shrink-0" 
                                    x-text="mail.date || 'Unbekannt'"></span>
                            </button>
                        </template>
                        <template x-if="typeof contactMails === 'undefined' || contactMails.length === 0">
                            <p class="text-[10px] text-slate-400 italic py-1 text-center">Keine Mails im Verlauf gefunden.</p>
                        </template>
                    </div>
                </div>
            </template>
            <template x-if="recentEmails.length === 0 && !loadingEmails">
                <p class="text-[11px] text-slate-400 italic w-full text-center py-2">Keine Kontakte im Verlauf. Bitte "Laden" triggern.</p>
            </template>
        </div>
        <div class="flex justify-between items-center pt-3 border-t border-slate-100 dark:border-gray-750/60">
            <select x-model="newNote.category" class="text-xs bg-slate-50 dark:bg-gray-750 text-slate-600 dark:text-gray-300 border border-slate-200 dark:border-gray-600 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500">
                <option value="general">📌 Allgemein</option>
                <option value="todo">📞 Wiedervorlage</option>
                <option value="important">🔥 Wichtig</option>
            </select>
            <div class="relative flex items-center">
                <input type="datetime-local" x-model="newNote.due_date" 
                        class="text-xs bg-slate-50 dark:bg-gray-750 text-slate-600 dark:text-gray-300 border border-slate-200 dark:border-gray-600 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-blue-500 min-w-[130px]">
            </div>
            <button @click="addNote()" 
                    :disabled="!newNote.content.trim()"
                    class="bg-blue-600 hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold text-xs px-4 py-2 rounded-xl transition-colors shadow-sm flex items-center space-x-1.5">
                <span>💾</span>
                <span>Memo sichern</span>
            </button>
        </div>
    </div>
    <div class="space-y-3">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 px-1 flex items-center gap-1.5">
            <span>📋</span> Gespeicherte Memos & Notizen
        </h3>
        <div class="space-y-3">
            <template x-for="(note, index) in notes" :key="note.id ? note.id.toString() : 'fallback_' + index">
                <div class="group relative p-4 bg-white dark:bg-gray-800 rounded-2xl border border-slate-200/80 dark:border-gray-700 shadow-sm flex items-start justify-between transition-all duration-200 hover:shadow-md hover:border-slate-300 dark:hover:border-gray-600 overflow-hidden" 
                    :class="{
                        'opacity-50 bg-slate-50/50 dark:bg-gray-850/40 line-through': note.status === 'completed',
                        'border-l-4 border-l-rose-500': note.category === 'important' && note.status !== 'completed',
                        'border-l-4 border-l-amber-500': note.category === 'todo' && note.status !== 'completed',
                        'border-l-4 border-l-slate-400 dark:border-l-gray-600': (note.category === 'general' || !note.category) && note.status !== 'completed'
                    }">
                    
                    <div class="flex items-start space-x-3.5 flex-1 min-w-0">
                        <div class="relative flex items-center h-5 mt-0.5">
                            <input type="checkbox" :checked="note.status === 'completed'" @change="toggleNoteStatus(note.id)" 
                                class="h-4.5 w-4.5 rounded-lg border-slate-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500/20 bg-transparent dark:checked:bg-blue-600 cursor-pointer transition-colors">
                        </div>
                        
                        <div class="flex-1 min-w-0 space-y-2">
                            <p class="text-sm text-slate-700 dark:text-gray-200 leading-relaxed whitespace-pre-wrap break-words font-medium" x-text="note.content"></p>
                            
                            <div class="flex flex-wrap items-center gap-2 pt-1 text-[10px] text-slate-400 dark:text-gray-500">
                                
                                <template x-if="note.created_at">
                                    <span class="flex items-center gap-1 bg-slate-50 dark:bg-gray-750 text-slate-500 dark:text-gray-400 px-1.5 py-0.5 rounded-md"
                                        x-text="new Date(note.created_at).toLocaleDateString('de-DE', {day: '2-digit', month: '2-digit', hour: '2-digit', minute:'2-digit'})"></span>
                                </template>
                                <span class="px-1.5 py-0.5 rounded-md font-semibold uppercase tracking-wider bg-slate-100 dark:bg-gray-750 text-slate-500 dark:text-gray-400 text-[9px]"
                                    x-text="note.category === 'important' ? '🔥 Wichtig' : (note.category === 'todo' ? '📞 Wiedervorlage' : '📌 Allgemein')"></span>
                                <template x-if="note.email">
                                    <div class="flex items-center gap-1 bg-blue-50/60 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded-md border border-blue-100/40 dark:border-blue-900/20">
                                        <span>📧</span>
                                        <span class="truncate max-w-[180px]" x-text="note.contact_email"></span>
                                    </div>
                                </template>
                                <template x-if="note.uid">
                                    <div class="flex items-center gap-1 font-mono bg-slate-100 dark:bg-gray-750 text-slate-600 dark:text-gray-400 px-1.5 py-0.5 rounded-md">
                                        <span>ID:</span>
                                        <span class="font-bold" x-text="note.uid || note.mail_uid"></span>
                                    </div>
                                </template>
                                <span x-show="note.id?.toString()?.startsWith('local_')" class="text-[9px] bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 px-1.5 py-0.5 rounded-md font-bold uppercase tracking-wider border border-amber-200/40 animate-pulse">Lokal gesichert</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-1 pl-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150 NicolaiActions">
                        <button @click="editNote(note)" title="Notiz bearbeiten" class="p-1.5 text-slate-400 hover:text-blue-600 hover:bg-slate-50 dark:hover:bg-gray-700/50 rounded-lg transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                        <button @click="deleteNote(note.id)" title="Notiz löschen" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-lg transition-all">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
            </template>
            <template x-if="notes.length === 0">
                <div class="text-center py-12 border-2 border-dashed border-slate-200 dark:border-gray-700 rounded-2xl bg-slate-50/50 dark:bg-gray-850/20">
                    <span class="text-2xl opacity-40">📭</span>
                    <p class="text-xs text-slate-400 mt-2 italic">Noch keine Notizen vorhanden.</p>
                </div>
            </template>
        </div>
    </div>
</div>