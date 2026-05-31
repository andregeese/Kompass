<div x-data="mailView" x-init="init_mails();startBackgroundSyncDelayed();" class="flex-1 flex overflow-hidden w-full h-full">
    
    <!-- Linke Spalte: Mail-Liste -->
    <div class="w-full md:w-80 border-r border-slate-200 dark:border-gray-700 flex flex-col overflow-hidden bg-white dark:bg-gray-850 shrink-0">
        <div class="px-2 py-2">
            <input type="text" 
                x-model="mailSearchQuery"
                placeholder="Suche in E-Mails..." 
                class="w-full bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
        </div>
        <!-- Ordner-Navigation -->
        <div class="p-3 border-b border-slate-100 dark:border-gray-700 flex space-x-1 overflow-x-auto shrink-0">
            <button @click="switchFolder('INBOX')" :class="currentFolder === 'INBOX' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-gray-300'" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors">📥 Posteingang</button>
            <button @click="switchFolder('DMS')" :class="currentFolder === 'DMS' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-gray-300'" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors">📁 DMS Archiv</button>
            <button @click="switchFolder('DMSDone')" :class="currentFolder === 'DMSDone' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-gray-300'" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors">✅ Erledigt</button>
            <button @click="switchFolder('Sent Messages')" :class="currentFolder === 'Sent Messages' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-gray-300'" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors"> Gesendet</button>
        </div>

        <div class="flex justify-between items-center px-1 mt-1.5 text-[11px]" x-show="mailSearchQuery.trim().length >= 0" x-cloak>
            <span class="text-slate-400" x-text="`Gefunden: ${filteredMails.length} von ${mails.length}`"></span>
            <a href="#" @click.prevent="mailSearchQuery = ''" class="text-blue-500 hover:underline">Leeren</a>
        </div>

        <div id="mail-list" 
            @scroll="if (!$data.loading && $data.hasMore && !$data.mailSearchQuery && ($el.scrollHeight - ($el.scrollTop + $el.clientHeight) <= 150)) { loadMails(true); }" 
            class="flex-1 overflow-y-auto p-2 space-y-1.5">
            
            <div class="px-2 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                Gesamt: <span x-text="mails.length"></span> Mails
            </div>

            <template x-for="mail in mails" :key="mail.hash">
                <div @click="viewMail(mail); currentMail=mail; $dispatch('toggle-sidebar-request', { tab: 'context' });" 
                    class="w-full text-left block bg-white dark:bg-gray-800 p-3 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-750 transition-all relative pl-7"
                    :class="{
                            'border-blue-500 ring-2 ring-blue-500/10 dark:bg-gray-750': selectedHash === mail.hash,
                            'border-emerald-500 dark:border-emerald-500': mail.paperless_id !== null && mail.paperless_id !== undefined,
                            'bg-slate-50/60 dark:bg-gray-850/40 opacity-90': mail.is_read // Gelesene Mails leicht ausgrauen
                        }">
                    
                    <!-- 1. UNGELESEN-INDIKATOR (Blauer Punkt ganz links) -->
                    <div x-show="mail.is_read===0" 
                        class="absolute left-2.5 top-1/2 -translate-y-1/2 w-2 h-2 bg-blue-500 rounded-full shadow-sm shadow-blue-500/50">
                    </div>

                    <!-- Kopfzeile mit Absender, Beantwortet-Status, Datum und Flagge -->
                    <div class="flex justify-between items-center text-[11px] text-slate-400 dark:text-gray-400 mb-1 min-w-0">
                        <div class="flex items-center space-x-1 truncate mr-2">
                            <!-- 2. BEANTWORTET-INDIKATOR (Geschwungener Pfeil) -->
                            <span x-show="mail.is_answered" class="text-blue-500 dark:text-blue-400 shrink-0 font-bold" title="Beantwortet">↩️</span>
                            
                            <!-- Absender (Wird fett, wenn die Mail ungelesen ist) -->
                            <span class="text-blue-500 dark:text-blue-400 truncate" 
                                :class="mail.is_read===0 ? 'font-black' : 'font-medium'" 
                                x-text="mail.sender"></span>
                        </div>
                        
                        <div class="flex items-center space-x-1.5 shrink-0">
                            <!-- 3. MARKIERT-INDIKATOR (Flagge / Pin) -->
                            <span x-show="mail.is_flagged" class="text-amber-500 animate-pulse text-[12px]" title="Markiert">📌</span>
                            <span x-text="mail.date"></span>
                        </div>
                    </div>
                    
                    <!-- Betreffzeile (Ebenfalls fetter bei ungelesenen Mails) -->
                    <h4 class="text-xs line-clamp-2 text-slate-800 dark:text-gray-200 mb-2" 
                        :class="mail.is_read===0 ? 'font-bold' : 'font-normal text-slate-600 dark:text-gray-300'"
                        x-text="mail.subject"></h4>

                    <div class="flex justify-between items-center pt-1 border-t border-slate-100 dark:border-gray-700/50 mt-2">
                        <!-- Kleines Label für den Lesestatus (optional, gut für die Barrierefreiheit) -->
                        <span class="text-[10px] text-slate-400 dark:text-gray-500 font-medium" 
                            x-text="mail.is_read ? 'Gelesen' : 'Neu'"></span>

                        <button @click.stop="deleteMail(mail)" 
                                class="px-2.5 py-1.5 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-100 transition-colors">
                            🗑️ Löschen
                        </button>
                    </div>
                </div>
            </template>
            
            <div x-show="loading" class="text-center py-4 text-xs text-slate-400 flex items-center justify-center space-x-2">
                <div class="animate-spin rounded-full h-3.5 w-3.5 border-2 border-blue-500 border-t-transparent"></div>
                <span x-text="mailSearchQuery ? 'Suche...' : 'Lade weitere Nachrichten...'"></span>
            </div>
            
            <div x-show="!hasMore && mails.length > 0 && !mailSearchQuery" class="text-center py-4 text-[11px] text-slate-400 font-medium">
                ✨ Alle Nachrichten aus <span x-text="currentFolder"></span> geladen.
            </div>
        </div>
    </div>

    <!-- Rechte Spalte: Mail-Inhalt (Iframe) -->
    <div :class="showMobilePreview ? 'absolute inset-0 z-40 bg-slate-50 dark:bg-gray-900 flex flex-col' : 'hidden md:flex flex-1 flex-col'" class="overflow-hidden bg-slate-50 dark:bg-gray-900">
        <template x-if="currentMail">
            <div class="h-full flex flex-col overflow-hidden">
                <div class="p-3 bg-white dark:bg-gray-800 border-b border-slate-200 dark:border-gray-700 flex items-center justify-between shrink-0">
                    <div class="flex items-center space-x-2">
                        <button @click="showMobilePreview = false" class="px-2 py-1 text-sm bg-slate-100 dark:bg-gray-700 rounded-lg">⬅️</button>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white line-clamp-1" x-text="currentMail.subject"></h3>
                            <div class="flex items-center space-x-2 text-[11px] text-slate-400">
                                <span x-text="'Von: ' + currentMail.sender"></span>
                                <span>•</span>
                                <span x-text="currentMail.date"></span>
                            </div>
                            <div class="flex items-center space-x-2 text-[11px] text-slate-400">
                                <template x-if="currentMail.emailhash">
                                    <a target="_blank" :href="'index.php?view=contacts&hash=' + currentMail.emailhash" 
                                    class="text-indigo-500 hover:underline hover:text-indigo-600 font-medium">
                                    👤 Kontaktprofil
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-1.5">                        
                        <template x-if="!currentMail?.paperless_id">
                            <!-- HINWEIS: Übergibt 'Recht' ans JS. Stelle sicher, dass dein JS diesen 2. Parameter verarbeitet! -->
                            <button @click="moveToDMS(currentMail, 'Recht')" 
                                    class="px-2.5 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg text-xs font-semibold hover:bg-indigo-100 transition-colors">
                                📦 Ins DMS
                            </button>
                        </template>
                        <template x-if="currentMail?.paperless_id">
                            <button @click="currentView = 'paperless'; $data.selectedDocumentId = currentMail.paperless_id;" 
                                    class="px-2.5 py-1.5 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition-colors">
                                📄 In Paperless zeigen
                            </button>
                        </template>
                        <button @click="deleteMail(currentMail)" 
                                class="px-2.5 py-1.5 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-100 transition-colors">
                            🗑️ Löschen
                        </button>
                    </div>
                </div>

                <!-- Iframe Container -->
                <div class="flex-1 flex flex-col lg:flex-row overflow-hidden">
                    <div class="flex-1 bg-white rounded-xl shadow-inner m-3 border border-slate-200 overflow-hidden relative flex items-center justify-center">
                        <div x-show="mailLoading" x-cloak class="absolute inset-0 flex flex-col items-center justify-center bg-white dark:bg-gray-800 z-10">
                            <div class="animate-spin rounded-full h-8 w-8 border-3 border-blue-500 border-t-transparent"></div>
                            <span class="text-xs font-medium text-slate-500 dark:text-gray-400 mt-2">Hole E-Mail vom Server...</span>
                        </div>

                        <iframe :src="currentMailUrl"
                                @load="mailLoaded()"
                                :class="{'opacity-0 pointer-events-none': mailLoading}"
                                class="w-full h-full border-0 bg-white transition-opacity duration-200" 
                                sandbox="allow-same-origin allow-popups allow-scripts">
                        </iframe>
                    </div>
                </div>
            </div>
        </template>
        
        <!-- Empty State -->
        <template x-if="!currentMail">
            <div class="flex-1 flex flex-col items-center justify-center text-slate-400 p-8">
                <span class="text-3xl mb-2">📬</span>
                <p class="text-xs text-center font-medium">Wähle eine Nachricht aus der linken Liste aus, um die Live-Vorschau und den Dokumentenbaum einzusehen.</p>
            </div>
        </template>
    </div>
</div>