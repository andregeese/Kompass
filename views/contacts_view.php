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

<div x-data="contactsView" x-init="initContacts()" class="flex-1 overflow-y-auto p-4 md:p-6 mx-auto w-full space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
            <span class="flex items-center justify-center transition-transform duration-300 group-hover:rotate-45">
                <svg class="w-8 h-8 md:w-6 md:h-6 shrink-0">
                    <use href="public/assets/sprite.svg#icon-contact"></use>
                </svg>
            </span>
            
            <span>Kontakte & Partner-Archiv</span>
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
        <button @click="closeMatchModal()" 
                class="absolute top-4 right-4 z-10 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors bg-white/50 dark:bg-gray-800/50 rounded-full hover:bg-slate-100 dark:hover:bg-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

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
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex justify-between items-center">
                            <span>Paperless Korrespondenten</span>
                            <span x-show="loadingPaperless" class="text-blue-500 italic lowercase">suche...</span>
                        </label>

                        <input type="text" x-model="paperlessQuery" @input.debounce.500ms="performPaperlessSearch()"
                            placeholder="Korrespondent suchen..."
                            class="w-full text-sm p-2 mb-3 rounded-lg border border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-900 focus:border-blue-500 outline-none">

                        <div class="flex flex-wrap gap-2">
                            <template x-for="corr in paperlessResults" :key="corr.id">
                                <button type="button"
                                    @click="togglePaperless(corr)"
                                    :class="isPaperlessLinked(corr.id) 
                                        ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400' 
                                        : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-gray-900 dark:border-gray-700 dark:text-slate-400'"
                                    class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-medium border transition-all hover:scale-[1.02]">

                                    <span x-text="corr.name" class="whitespace-nowrap truncate max-w-[120px]"></span>

                                    <span class="ml-1.5 opacity-50 font-bold" 
                                        x-text="isPaperlessLinked(corr.id) ? '✕' : '+'"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="paperlessResults.length === 0 && paperlessQuery.length < 2" class="text-xs text-slate-400 italic mt-1">
                            Tippe oben, um Korrespondenten zu suchen.
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100 dark:border-gray-700 mt-4">
                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex justify-between items-center">
                            <span>Paperless Tags</span>
                            <span x-show="loadingTags" class="text-blue-500 italic lowercase">suche...</span>
                        </label>

                        <input type="text" x-model="tagQuery" @input.debounce.500ms="performTagSearch()"
                            placeholder="Tag suchen..."
                            class="w-full text-sm p-2 mb-3 rounded-lg border border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-900 focus:border-blue-500 outline-none">

                        <div class="flex flex-wrap gap-2">
                            <template x-for="tag in tagResults" :key="tag.id">
                                <button type="button"
                                    @click="toggleTag(tag)"
                                    :class="isTagLinked(tag.id) 
                                        ? 'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-950/30 dark:border-blue-800 dark:text-blue-400' 
                                        : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-gray-900 dark:border-gray-700 dark:text-slate-400'"
                                    class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-medium border transition-all hover:scale-[1.02]">

                                    <span :style="'background-color: ' + tag.color" class="w-2 h-2 rounded-full mr-1.5 flex-shrink-0 border border-black/10"></span>

                                    <span x-text="tag.name" class="whitespace-nowrap truncate max-w-[120px]"></span>

                                    <span class="ml-1.5 opacity-50 font-bold" 
                                        x-text="isTagLinked(tag.id) ? '✕' : '+'"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="tagResults.length === 0 && tagQuery.length < 2" class="text-xs text-slate-400 italic mt-1">
                            Tippe oben, um Tags zu suchen.
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