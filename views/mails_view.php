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

<div x-data="mailView" x-init="init_mails();startBackgroundSyncDelayed();" class="flex w-full h-screen overflow-hidden bg-slate-50 dark:bg-gray-900">
    
    <!-- 2. #sidebarMail: Mobil 'fixed' bis über die Bottom-Nav (bottom-16), ab md: 'relative h-full' -->
    <div id="sidebarMail" 
         class="fixed inset-x-0 top-0 bottom-16 z-40 flex shrink-0 md:relative md:inset-auto md:z-auto md:h-full" 
         :class="isSidebarOpen ? 'w-full md:w-auto' : 'pointer-events-none md:pointer-events-auto w-0 md:w-auto'">
        
        <div x-show="isSidebarOpen" 
            x-transition:enter="transition-all duration-300 ease-out"
            x-transition:enter-start="-ml-full md:-ml-80 opacity-0" 
            x-transition:enter-end="ml-0 opacity-100"
            x-transition:leave="transition-all duration-300 ease-in"
            x-transition:leave-start="ml-0 opacity-100" 
            x-transition:leave-end="-ml-full md:-ml-80 opacity-0"
            class="w-full md:w-80 h-full min-h-0 border-r border-slate-200 dark:border-gray-700 flex flex-col bg-white dark:bg-gray-850 relative">
            
            <button @click="isSidebarOpen = false" 
                    class="hidden md:block absolute right-2 top-4 z-50 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-full p-1 shadow-md hover:bg-slate-100 transition-colors text-[10px] text-slate-500">
                ◀
            </button>
            
            <div class="px-2 py-2 shrink-0">
                <input type="text" 
                    x-model="mailSearchQuery"
                    placeholder="Suche in E-Mails..." 
                    class="w-full bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-500 outline-none">
            </div>
            
            <div class="p-3 border-b border-slate-100 dark:border-gray-700 flex space-x-1 overflow-x-auto shrink-0">
                <template x-for="folder in SYNCFOLDER" :key="folder">
                    <button @click="switchFolder(folder)" 
                            :class="currentFolder === folder ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 dark:bg-gray-700 text-slate-600 dark:text-gray-300'" 
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-colors"
                            x-text="folder">
                    </button>
                </template>
            </div>
            
            <div class="flex justify-between items-center px-3 mt-1.5 text-[11px] shrink-0" x-show="mailSearchQuery.trim().length > 0" x-cloak>
                <span class="text-slate-400" x-text="`Gefunden: ${filteredMails.length} von ${mails.length}`"></span>
                <a href="#" @click.prevent="mailSearchQuery = ''" class="text-blue-500 hover:underline">Leeren</a>
            </div>
            
            <!-- E-Mail Liste: flex-1 und min-h-0 erzwingen internes Scrollen -->
            <div id="mail-list" class="flex-1 min-h-0 overflow-y-auto p-2 space-y-1.5">
                <div class="px-2 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    Gesamt: <span x-text="mails.length"></span> Mails
                </div>
                
                <template x-for="mail in mails" :key="mail.id">
                    <div @click="openMail(mail)" 
                        class="w-full text-left block bg-white dark:bg-gray-800 p-3 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-750 transition-all relative pl-7"
                        :class="{
                                'border-blue-500 ring-2 ring-blue-500/10 dark:bg-gray-750': selectedId === mail.id,
                                'border-emerald-500 dark:border-emerald-500': mail.paperless_id !== null && mail.paperless_id !== undefined,
                                'bg-slate-50/60 dark:bg-gray-850/40 opacity-90': mail.is_read,
                                'bg-amber-50/60 dark:bg-amber-900/20 opacity-90': mail.is_newsletter
                            }">
                        
                        <div x-show="mail.is_read===0" class="absolute left-2.5 top-1/2 -translate-y-1/2 w-2 h-2 bg-blue-500 rounded-full shadow-sm"></div>
                        <div class="flex justify-between items-center text-[11px] text-slate-400 dark:text-gray-400 mb-1 min-w-0">
                            <div class="flex items-center space-x-1 truncate mr-2">
                                <span x-show="mail.is_answered" class="text-blue-500 font-bold">↩️</span>
                                <span class="text-blue-500 dark:text-blue-400 truncate" :class="mail.is_read===0 ? 'font-black' : 'font-medium'" x-text="mail.sender"></span>
                            </div>
                            <div class="flex items-center space-x-1.5 shrink-0">
                                <span x-show="mail.is_flagged" class="text-amber-500 text-[12px]">📌</span>
                                <span x-text="mail.date"></span>
                            </div>
                        </div>
                        
                        <h4 class="text-xs line-clamp-2 text-slate-800 dark:text-gray-200 mb-2" :class="mail.is_read===0 ? 'font-bold' : 'font-normal text-slate-600 dark:text-gray-300'" x-text="mail.subject"></h4>
                        <div class="flex justify-between items-center pt-1 border-t border-slate-100 dark:border-gray-700/50 mt-2">
                            <span class="text-[10px] text-slate-400" x-text="mail.is_read ? 'Gelesen' : 'Neu'"></span>
                            <div class="flex items-center space-x-1.5">
                                <button @click.stop="moveToDMS(mail)" class="px-2.5 py-1.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg text-xs font-semibold hover:bg-indigo-100">📦 </button>
                                <button @click.stop="deleteMail(mail)" class="px-2.5 py-1.5 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg text-xs font-semibold hover:bg-red-100">🗑️ </button>
                            </div>
                        </div>
                    </div>
                </template>
                
                <div x-show="loading" class="text-center py-4 text-xs text-slate-400 flex items-center justify-center space-x-2">
                    <div class="animate-spin rounded-full h-3.5 w-3.5 border-2 border-blue-500 border-t-transparent"></div>
                    <span x-text="mailSearchQuery ? 'Suche...' : 'Lade weitere Nachrichten...'"></span>
                </div>
            </div>
        </div>
        
        <div x-show="!isSidebarOpen" 
            class="relative hidden md:block w-0 h-full z-50 shrink-0">
            <button @click="isSidebarOpen = true" 
                    class="absolute top-4 left-0 z-50 w-6 h-6 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-full shadow-md flex items-center justify-center text-slate-500 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-750 transition-all focus:outline-none text-[10px] pl-0.5">
                ▶
            </button>
        </div>
    </div>
    <div id="currentmaiL" class="flex-1 h-full overflow-hidden bg-slate-50 dark:bg-gray-900 relative"
          :class="isSidebarOpen ? 'hidden md:flex flex-col' : 'flex flex-col'">
          
        <template x-if="currentMail">
            <div class="h-full flex flex-col overflow-hidden">
                <div class="p-3 bg-white dark:bg-gray-800 border-b border-slate-200 dark:border-gray-700 flex items-center justify-between shrink-0">
                    <div class="flex items-center space-x-2 min-w-0">
                        <button @click="isSidebarOpen = true" class="md:hidden px-3 py-1.5 text-xs bg-slate-100 dark:bg-gray-700 hover:bg-slate-200 dark:hover:bg-gray-600 rounded-lg font-bold shrink-0">
                            ⬅️ 
                        </button>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white line-clamp-1" x-text="currentMail.subject"></h3>
                            <div class="flex items-center space-x-2 text-[11px] text-slate-400">
                                <span class="truncate" x-text="'Von: ' + currentMail.sender"></span>
                                <span>•</span>
                                <span class="shrink-0" x-text="currentMail.date"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex-1 flex flex-col overflow-hidden">
                    <div class="flex-1 bg-white rounded-xl shadow-inner m-3 border border-slate-200 overflow-hidden relative flex items-center justify-center">
                        <div x-show="mailLoading" x-cloak class="absolute inset-0 flex flex-col items-center justify-center bg-white dark:bg-gray-800 z-10">
                            <div class="animate-spin rounded-full h-8 w-8 border-3 border-blue-500 border-t-transparent"></div>
                            <span class="text-xs font-medium text-slate-500 mt-2">Hole E-Mail...</span>
                        </div>
                        <div x-show="!mailLoading" x-html="currentMailHtml" class="w-full h-full p-4 overflow-auto bg-white"></div>
                    </div>
                </div>
            </div>
        </template>
        
        <template x-if="!currentMail">
            <div class="flex-1 flex flex-col items-center justify-center text-slate-400 p-8">
                <span class="text-3xl mb-2">📬</span>
                <p class="text-xs text-center font-medium">Wähle eine Nachricht aus der linken Liste aus.</p>
            </div>
        </template>
    </div>
</div>