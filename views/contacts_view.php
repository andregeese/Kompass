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
   

    <div class="space-y-4">
        <svg id="network" style="width: 100%; height: 500px; border:1px solid #ccc;"></svg>    
    </div>

    <div class="space-y-4">
        <!--<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <template x-for="contact in filteredContacts" :key="contact.ceid || 'temp-' + contact.id">
                <div x-data="{ isEditing: false }" 
                    class="bg-white p-5 rounded-2xl border transition-all duration-300 relative shadow-sm hover:shadow-md"
                    :class="contact && Number(contact.is_connected) === 1 ? 'border-emerald-400 bg-emerald-50/20' : 'border-slate-200'">

                    <div class="absolute top-4 right-4 flex gap-1 z-10">
                        <button type="button" 
                                x-show="contact && contact.is_connected!==1"
                                @click.stop="openMatchModal(contact)" 
                                class="p-1.5 text-slate-400 hover:text-emerald-600 rounded-lg hover:bg-emerald-50 transition-colors">
                            🔗
                        </button>
                        
                        <button type="button" 
                                x-show="!isEditing"
                                @click.stop="isEditing = true"
                                class="p-1.5 text-slate-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>

                        <button type="button" 
                                x-show="isEditing"
                                @click.stop="saveInlineContact(contact); isEditing = false;"
                                class="p-1.5 text-emerald-600 bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-3" @keydown.enter="saveInlineContact(contact); isEditing = false;">
                        
                        <div x-show="!isEditing" class="space-y-1 pr-12">
                            <div class="flex items-center gap-2">
                                <h3 class="font-bold text-slate-800 text-sm truncate" x-text="contact.displayname"></h3>
                                <span class="text-[9px] px-1.5 py-0.5 rounded-md font-bold uppercase tracking-wide shrink-0"
                                    :class="contact.type === 'company' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'"
                                    x-text="contact.type === 'company' ? 'Firma' : 'Privat'">
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 font-mono" x-text="contact.email"></p>
                        </div>

                        <div x-show="isEditing" x-cloak class="space-y-3 pr-12">
                            <input type="text" x-model="contact.displayname" 
                                class="w-full text-sm font-bold bg-transparent border-b-2 border-blue-500 focus:outline-none focus:border-blue-600 px-0 py-1"
                                placeholder="Name...">
                            <input type="text" x-model="contact.email" 
                                class="w-full text-xs text-slate-600 bg-transparent border-b border-blue-300 focus:outline-none focus:border-blue-500 px-0 py-1"
                                placeholder="E-Mail...">
                            
                            <label class="flex items-center gap-3 cursor-pointer py-1">
                                <div class="relative">
                                    <input type="checkbox" 
                                        :checked="contact.type === 'company'" 
                                        @change="contact.type = $event.target.checked ? 'company' : 'person'"
                                        class="sr-only peer">
                                    <div class="w-9 h-5 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-full"></div>
                                </div>
                                <span class="text-[10px] font-bold text-slate-600 uppercase" x-text="contact.type === 'company' ? 'Firma' : 'Person'"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </template>

             <template x-if="Number(contact.is_connected) === 1">
                <div class="mt-3 pt-2 border-t border-slate-100">
                    <p class="text-[9px] font-bold text-slate-400 uppercase">Verknüpft mit:</p>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <template x-for="mail in contact.additional_emails.split(', ')">
                            <span class="text-[9px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded" 
                                x-text="mail">
                            </span>
                        </template>
                    </div>
                </div>
            </template>
        </div>-->

       

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
            <template x-if="activeContact">
                <div class="p-4 flex-1 overflow-y-auto" x-data="{ openSection: 'match' }">
                    <div class="mb-6 pb-6 border-b border-slate-200 dark:border-gray-700">
                        <p class="text-[9px] font-bold text-slate-400 uppercase mb-3">Aktive Bearbeitung</p>
                        
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                    <span x-text="activeContact.displayname.charAt(0)"></span>
                                </div>
                                <div class="flex-1 overflow-hidden">
                                    <h3 class="font-bold text-slate-800 dark:text-white text-sm truncate" x-text="activeContact.displayname"></h3>
                                    <p class="text-[11px] text-slate-500 font-mono truncate" x-text="activeContact.emails[0]?.email || 'Keine E-Mail'"></p>
                                </div>
                            </div>
                            
                            <template x-if="activeContact.is_connected">
                                <div class="mt-3 pt-2 border-t border-slate-100 dark:border-gray-700">
                                    <p class="text-[9px] font-bold text-emerald-600 uppercase">Verknüpft</p>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="mb-4">
                            <button @click="openSection = (openSection === 'match' ? null : 'match')" 
                                    class="w-full flex justify-between items-center text-xs font-bold text-slate-500 uppercase tracking-wider p-2 bg-slate-100 dark:bg-gray-700 rounded-lg">
                                <span>🔗 Korrespondenten verknüpfen</span>
                                <span x-text="openSection === 'match' ? '−' : '+'"></span>
                            </button>
                            
                            <div x-show="openSection === 'match'" x-collapse class="mt-2 space-y-2">
                                <input type="text" x-model="paperlessQuery" @input.debounce="performPaperlessSearch()"
                                    placeholder="Suche Korrespondent...." 
                                    class="w-full p-2 border border-slate-200 dark:border-gray-700 rounded-lg text-xs bg-white dark:bg-gray-800">
                                <div x-show="loadingPaperless" class="text-xs text-blue-500 italic">Suche...</div>
                                <template x-for="result in paperlessResults" :key="result.id">
                                    <div class="p-3 bg-slate-50 dark:bg-gray-750 rounded-lg flex justify-between items-center text-xs">
                                        <span x-text="result.name"></span>
                                        <button @click="linkToMatch(activeContact.id, result.id)" class="text-emerald-600 font-bold">🔗 Link</button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div>
                            <button @click="openSection = (openSection === 'emails' ? null : 'emails')" 
                                    class="w-full flex justify-between items-center text-xs font-bold text-slate-500 uppercase tracking-wider p-2 bg-slate-100 bg-whtie dark:bg-gray-700 rounded-lg dark:text-white">
                                <span>✉️ E-Mail Adressen</span>
                                <span x-text="openSection === 'emails' ? '−' : '+'"></span>
                            </button>
                            
                            <div x-show="openSection === 'emails'" x-collapse class="mt-2 space-y-3">
                                <input type="email" 
                                    x-model="emailQuery" 
                                    @input.debounce.500ms="performMatchSearch()"
                                    placeholder="E-Mails suchen..."
                                    class="w-full text-sm p-3 rounded-xl border border-slate-200 bg-white focus:border-blue-500 outline-none">

                                <div x-show="loadingMatch" class="text-xs text-blue-500 italic p-2">Suche läuft...</div>

                                <div class="flex flex-wrap gap-2">
                                    <template x-for="contact in sortedMatchResults" :key="contact.ceid">
                                        <button type="button" 
                                                @click="toggleEmail(contact)"
                                                :class="isEmailLinked(contact.ceid) ? 'bg-emerald-100 border-emerald-300 text-emerald-800' : 'bg-slate-100 border-slate-200 text-slate-700'"
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border transition-all hover:opacity-80">
                                            <span x-text="contact.email"></span>
                                            <span class="ml-2 font-bold" x-text="isEmailLinked(contact.ceid) ? '✕' : '+'"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <button @click="saveContact(activeContact); closeMatchModal();" 
                                class="w-full py-3 bg-emerald-600 text-white rounded-xl font-bold text-xs hover:bg-emerald-700 transition-all shadow-md">
                            Speichern & Schließen
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>