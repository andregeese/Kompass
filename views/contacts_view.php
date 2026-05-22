<div x-data="contactsView" x-init="initContacts()" class="flex-1 overflow-y-auto p-4 md:p-6 max-w-4xl mx-auto w-full space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center space-x-2">
            <span>👥</span> <span>Kontakte & Partner-Archiv</span>
        </h2>
    </div>

    <input type="text" x-model="searchQueryContacts" placeholder="Kontakte suchen..." 
           class="w-full p-3 rounded-xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <template x-for="contact in filteredContacts" :key="contact.id">
            <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm group/item">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white" x-text="contact.displayname"></h3>
                        <p class="text-[11px] text-slate-500" x-text="contact.email"></p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs mb-4">
                    <div class="col-span-2">
                        <p class="text-[9px] uppercase font-bold text-slate-400">Adresse</p>
                        <p class="text-slate-600 dark:text-gray-300" x-text="contact.street || 'Keine Straße'"></p>
                        <p class="text-slate-600 dark:text-gray-300" x-text="(contact.zip || '') + ' ' + (contact.city || '')"></p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-slate-400">Telefon</p>
                        <p class="text-slate-600 dark:text-gray-300" x-text="contact.phone || '-'"></p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-slate-400">Webseite</p>
                        <p class="text-blue-600 dark:text-blue-400 hover:underline cursor-pointer" x-text="contact.website || '-'"></p>
                    </div>
                </div>
                <div class="space-y-2 mt-4 pt-4 border-t border-slate-100 dark:border-gray-700">
                    <p class="text-[10px] font-bold uppercase text-slate-400">Verknüpfte Paperless-Dokumente</p>
                    
                    <template x-for="item in contact.linked_items" :key="item.id">
                        <div class="flex items-start justify-between gap-2">
                            <a :href="item.link" 
                               :target="item.type === 'mail' ? '_self' : '_blank'"
                               class="font-semibold hover:underline flex-1 text-[11px] leading-tight text-slate-800 dark:text-gray-200"
                               @click="item.type === 'mail' ? $dispatch('select-mail-hash', { hash: item.hash }) : null"
                               x-text="item.title">
                            </a>
                        </div>
                    </template>

                    <button x-show="contact.paperless_id === null || contact.paperless_id === undefined" @click="openMatchModal(contact)"
                            @click="openMatchModal(contact)"
                             class="w-full mt-2 bg-slate-50 hover:bg-emerald-600 dark:bg-gray-700 dark:hover:bg-emerald-600 text-slate-600 dark:text-gray-300 hover:text-white text-[10px] font-bold px-3 py-2 rounded-lg transition shadow-sm">
                        🔗 Paperless Korrespondent matchen
                    </button>

                    <a x-show="contact.paperless_id !== null && contact.paperless_id !== undefined"
                        :href="'/view=paperless&documents?correspondent_id=' + contact.paperless_id" 
                        target="_blank"
                        class="w-full mt-2 block text-center bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold px-3 py-2 rounded-lg transition shadow-sm">
                            📂 Alle Dokumente anzeigen
                        </a>
                </div>
            </div>
        </template>
    </div>

    <!--Modal -->
    <div x-show="isMatchPanelOpen" 
        x-cloak
        class="fixed inset-0 z-[100] flex justify-end"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0">
        
        <div @click="closeMatchModal()" class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm"></div>

        <div class="relative w-full max-w-sm bg-white dark:bg-gray-800 h-full shadow-2xl flex flex-col transform transition-transform duration-300"
            x-show="isMatchPanelOpen"
            x-transition:enter="transition-transform duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition-transform duration-300"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full">
            
            <div class="p-4 border-b border-slate-200 dark:border-gray-700 flex justify-between items-center">
                <h3 class="font-bold text-slate-800 dark:text-white" x-text="'Match für: ' + (activeContact?.display_name || '')"></h3>
                <button @click="closeMatchModal()" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>

            <div class="p-4 flex-1 overflow-y-auto">
                <input type="text" x-model="matchSearchQuery" @input.debounce="performMatchSearch()"
                    placeholder="Suche Dokument oder Mail..." 
                    class="w-full p-2 border border-slate-200 rounded-lg mb-4 text-xs">
                
                <div class="space-y-2">
                    <template x-for="result in matchResults" :key="result.id">
                        <div class="p-3 bg-slate-50 dark:bg-gray-750 rounded-lg flex justify-between items-center text-xs">
                            <span x-text="result.name"></span>
                            <span x-text="result.score"></span>
                            <button @click="linkToMatch(activeContact.id, result.id)" class="text-emerald-600 font-bold">🔗</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>