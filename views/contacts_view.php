<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
?>

<div x-data="contactsView" x-init="initContacts()" class="flex-1 overflow-y-auto p-4 md:p-6 mx-auto w-full space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center space-x-2">
            <span>👥</span> <span>Kontakte & Partner-Archiv</span>
        </h2>
        <button @click="openNewContactModal()" 
            class="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition-all text-sm font-medium whitespace-nowrap">
        <span>+</span> <span>Neuer Kontakt</span>
    </button>
    </div>
    <input type="text" x-model="searchQueryContacts" placeholder="Kontakte suchen..." 
           class="w-full p-3 rounded-xl border border-slate-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm">
    </input>
   
    <div class="bg-slate-50 dark:bg-gray-900/40 border border-slate-200 dark:border-gray-700/60 rounded-2xl p-2 shadow-sm relative overflow-hidden backdrop-blur-sm">
        <svg id="network" class="w-full h-[450px] md:h-[500px] bg-transparent cursor-grab active:cursor-grabbing"></svg>    
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
        
        <div class="relative w-full max-w-md bg-white dark:bg-gray-800 h-full shadow-2xl flex flex-col transform transition-transform duration-300"
            x-show="isMatchPanelOpen"
            x-transition:enter="transition-transform duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0">
            
            <template x-if="activeContact">
                <div class="p-6 flex-1 overflow-y-auto space-y-6">
                    
                    <div class="border-b border-slate-200 dark:border-gray-700 pb-4">
                        <h2 class="text-lg font-bold text-slate-800 dark:text-white" 
                            x-text="activeContact.id ? 'Kontakt bearbeiten' : 'Neuer Kontakt'"></h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Anzeigename</label>
                            <input type="text" x-model="activeContact.displayname" 
                                class="mt-1 w-full p-2 border border-slate-200 dark:border-gray-700 rounded-lg text-sm bg-white dark:bg-gray-900 dark:text-white focus:border-blue-500 outline-none"
                                placeholder="Max Mustermann">
                        </div>
                        
                        <div>
                            <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-2">Kontakttyp</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" x-model="activeContact.type" value="person" class="text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm dark:text-slate-300">Person</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" x-model="activeContact.type" value="company" class="text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm dark:text-slate-300">Firma</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-gray-700">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex justify-between items-center">
                            <span>E-Mail-Adressen verknüpfen</span>
                            <span x-show="loadingMatch" class="text-blue-500 italic lowercase">suche...</span>
                        </label>
                        
                        <input type="email" x-model="emailQuery" @input.debounce.500ms="performMatchSearch()"
                            placeholder="E-Mail suchen..."
                            class="w-full text-sm p-2 mb-3 rounded-lg border border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-900 focus:border-blue-500 outline-none">

                        <div class="flex flex-wrap gap-2">
                            <template x-for="contact in sortedMatchResults" :key="contact.ceid">
                                <button type="button" 
                                        @click="toggleEmail(contact)"
                                        :class="isEmailLinked(contact.ceid) ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400' : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-gray-900 dark:border-gray-700 dark:text-slate-400'"
                                        class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-medium border transition-all hover:scale-[1.02]">
                                    
                                    <span x-text="contact.email" class="whitespace-nowrap truncate max-w-[120px]"></span>
                                    
                                    <span class="ml-1.5 opacity-50 font-bold" x-text="isEmailLinked(contact.ceid) ? '✕' : '+"'"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="sortedMatchResults.length === 0 && emailQuery.length < 2" class="text-xs text-slate-400 italic mt-1">
                            Tippe oben, um nach E-Mail-Adressen zu suchen.
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-gray-700">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 block">Paperless Korrespondent</label>
                        
                        <template x-if="activeContact.paperless_id">
                            <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 p-3 rounded-lg mb-3 border border-blue-100 dark:border-blue-800">
                                <div class="flex items-center gap-2 text-sm text-blue-800 dark:text-blue-300">
                                    <span>📄</span>
                                    <span class="font-bold" x-text="activeContact.paperless_name || ('ID: ' + activeContact.paperless_id)"></span>
                                </div>
                                <button @click="activeContact.paperless_id = null; activeContact.paperless_name = null" 
                                        class="text-xs text-red-500 hover:text-red-700 font-bold">Lösen</button>
                            </div>
                        </template>

                        <div x-show="!activeContact.paperless_id" class="space-y-2">
                            <input type="text" x-model="paperlessQuery" @input.debounce.500ms="performPaperlessSearch()"
                                placeholder="Korrespondent suchen..." 
                                class="w-full p-2 border border-slate-200 dark:border-gray-700 rounded-lg text-sm bg-slate-50 dark:bg-gray-900 focus:border-blue-500 outline-none">
                            
                            <div x-show="loadingPaperless" class="text-xs text-blue-500 italic">Suche in Paperless...</div>
                            
                            <div class="space-y-1 max-h-40 overflow-y-auto">
                                <template x-for="result in paperlessResults" :key="result.id">
                                    <div class="p-2 bg-white dark:bg-gray-800 border border-slate-100 dark:border-gray-700 rounded-lg flex justify-between items-center text-sm cursor-pointer hover:border-blue-300"
                                        @click="setPaperlessMatch(result.id, result.name)">
                                        <span x-text="result.name"></span>
                                        <span class="text-blue-600 font-bold text-xs">Auswählen</span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="pt-8 mt-auto">
                        <button @click="saveContact()" 
                                class="w-full py-3 bg-blue-600 text-white rounded-xl font-bold text-sm hover:bg-blue-700 transition-all shadow-md">
                            Kontakt speichern
                        </button>
                    </div>
                    
                </div>
            </template>
        </div>
    </div>
</div>