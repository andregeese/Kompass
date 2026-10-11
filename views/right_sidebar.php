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
<div x-data="rightSidebarComponent()" 
     x-init="initSidebar()"
     @toggle-sidebar-request.window="isOpen = true; activeTab = $event.detail.tab; if($event.detail.email) { loadContactByEmail($event.detail.email) }"
     class="shrink-0">
    <button @click="toggleSidebar('context')" 
            class="fixed right-4 bottom-20 md:bottom-6 z-40 bg-blue-600 hover:bg-blue-700 text-white p-3.5 rounded-full shadow-xl transition-all duration-300 ease-in-out hover:scale-105 flex items-center justify-center"
            :class="isOpen ? 'translate-x-24 opacity-0 pointer-events-none' : 'translate-x-0 opacity-100'">
        <span class="text-xl">🤖</span>
        <template x-if="notifications.length > 0">
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-black h-5 w-5 rounded-full flex items-center justify-center border-2 border-white dark:border-gray-900 animate-pulse"
                  x-text="notifications.length">
            </span>
        </template>
    </button>
    <aside :class="isOpen ? 'translate-y-0 shadow-2xl-top border-t md:border-t-0 md:border-l w-full md:w-96' : 'translate-y-full md:translate-y-0 md:w-0 md:border-0 md:opacity-0 overflow-hidden'" 
           class="fixed md:sticky bottom-0 left-0 right-0 top-24 md:top-0 h-[calc(100vh-6rem)] md:h-screen bg-slate-50 dark:bg-gray-900 border-slate-200 dark:border-gray-800 flex flex-col overflow-hidden transition-all duration-300 ease-in-out z-40 md:z-30 rounded-t-2xl md:rounded-t-none">
        
        <div class="md:hidden w-12 h-1 bg-slate-300 dark:bg-gray-750 rounded-full mx-auto mt-2 shrink-0"></div>
        <div class="p-3 border-b border-slate-200 dark:border-gray-700 flex flex-col space-y-2 shrink-0 bg-white dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500">Smart Control</span>
                <button @click="closeAndReset()" class="text-slate-400 hover:text-slate-600 dark:hover:text-gray-200 text-sm font-bold p-1">✕</button>
            </div>
            
            <div class="flex bg-slate-100 dark:bg-gray-750 p-0.5 rounded-lg text-xs">
                <button @click="activeTab = 'context'" 
                        :class="activeTab === 'context' ? 'bg-white dark:bg-gray-600 shadow-sm text-slate-900 dark:text-white font-semibold' : 'text-slate-500 dark:text-gray-400'"
                        class="flex-1 py-1 rounded-md transition-all text-center">⚡ Kontext</button>
                <button @click="activeTab = 'notifications'" 
                        :class="activeTab === 'notifications' ? 'bg-white dark:bg-gray-600 shadow-sm text-slate-900 dark:text-white font-semibold' : 'text-slate-500 dark:text-gray-400'"
                        class="flex-1 py-1 rounded-md transition-all text-center relative">
                    📋 Mitteilungen
                    <span x-show="notifications.length > 0" class="inline-block ml-1 px-1.5 py-0.2 bg-red-500 text-white text-[9px] font-bold rounded-full" x-text="notifications.length"></span>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-3 space-y-4 custom-scrollbar pb-24">
            
            <div x-show="activeTab === 'context'" class="space-y-4">
                <!--Kontakte -->
                <div class="space-y-3">
                    <div @click.stop="dispatchAction('contact_view', {hash:contactState?.contact?.id}, null)" 
                         class="bg-white dark:bg-gray-800 p-3 rounded-xl border border-slate-200 dark:border-gray-700 flex items-center justify-between text-xs cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-750 transition-all select-none shadow-sm">
                        <div class="flex items-center space-x-2 min-w-0">
                            <span class="flex h-2 w-2 rounded-full shrink-0" :class="contactState?.success ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                            <span class="font-bold text-slate-700 dark:text-gray-200 truncate" x-text="contactState?.contact?.display_name || 'Kein CRM-Kontext (Klicken zum Anlegen)'"></span>
                        </div>
                        <div class="flex items-center space-x-1.5 shrink-0 text-slate-400">
                            <span class="text-[10px] font-medium" x-text="contactState?.contact?.type === 'company' ? '🏢 Firma' : '👤 Privat'"></span>
                            <span class="text-[9px] transition-transform duration-200" :class="showManualForm ? 'rotate-180' : ''">▼</span>
                        </div>
                    </div>
                </div>
                <!-- Dokument -->
                <div class="space-y-3">
                    <h3 class="text-[10px] font-bold text-slate-400 dark:text-gray-500 uppercase tracking-wider flex items-center space-x-1.5 px-1">
                        <span>📄</span> <span>DMS Archiv-Suche</span>
                    </h3>
                    <div class="bg-white dark:bg-gray-800 p-2 rounded-xl border border-slate-200 dark:border-gray-700 shadow-sm">
                        <input 
                            type="text" 
                            placeholder="Dokument suchen..."
                            x-model="manualSearchQuery"
                            @input.debounce.300ms="searchDMS();"
                            class="w-full text-xs px-3 py-2 bg-slate-50 dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-lg focus:outline-none focus:border-blue-500 text-slate-700 dark:text-gray-200 transition-colors"
                        >
                        <div class="mt-2 space-y-1 overflow-y-auto max-h-[180px] pr-1 custom-scrollbar" 
                            x-show="paperlessDMSMatches.length > 0">
                            <template x-for="item in paperlessDMSMatches" :key="item.id">
                                <div class="relative group pl-4 py-2 flex items-center hover:bg-slate-50 dark:hover:bg-gray-800/50 rounded-lg transition-colors cursor-pointer"
                                    @click="linkToPaperless(item.id)">
                                    
                                    <div class="absolute left-0 top-0 bottom-0 w-px bg-slate-200 dark:bg-gray-700"></div>
                                    <div class="absolute left-[-3px] top-3 w-2 h-2 rounded-full border-2 border-white dark:border-gray-900 bg-emerald-500"></div>
                                    <div class="flex items-center gap-3 w-full text-[11px]">
                                        <span class="w-8 text-[9px] font-bold shrink-0 text-center"
                                            :class="{
                                                'text-emerald-600': item.score >= 0.8,
                                                'text-amber-500': item.score >= 0.5 && item.score < 0.8,
                                                'text-rose-500': item.score < 0.5
                                            }" 
                                            x-text="(item.score * 100).toFixed(0) + '%'">
                                        </span>
                                        <span class="flex-1 font-medium text-slate-700 dark:text-gray-300 truncate"
                                            :title="item.title"
                                            x-text="item.title">
                                        </span>
                                        <span class="text-slate-400 font-mono text-[10px] shrink-0" 
                                            x-text="item.created">
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                
                <!-- Verlauf -->
                <div class="space-y-3">
                    <h3 class="text-[10px] font-bold text-slate-400 dark:text-gray-500 uppercase tracking-wider flex items-center space-x-1.5">
                        <span>⏳</span> <span>Chronologischer Verlauf</span>
                    </h3>
                    <template x-for="archive in timelineData" :key="archive.year">
                        <div class="bg-white dark:bg-gray-800 border border-slate-200/60 dark:border-gray-700/60 rounded-xl overflow-hidden shadow-sm">
                            
                            <button @click="archive.is_open = !archive.is_open" 
                                    class="w-full px-3 py-2 bg-slate-50/80 dark:bg-gray-750/50 flex items-center justify-between border-b border-slate-100 dark:border-gray-700/50 transition-colors hover:bg-slate-100/60 dark:hover:bg-gray-750">
                                <span class="text-xs font-bold font-mono text-slate-700 dark:text-gray-300" x-text="'📅 Jahr ' + archive.year"></span>
                                <span class="text-[10px] text-slate-400 transition-transform duration-200" :class="archive.is_open ? 'rotate-90' : ''">▶</span>
                            </button>
                            <div x-show="archive.is_open" class="p-2 space-y-1.5 bg-slate-50/30 dark:bg-gray-900/10">
                                
                                <template x-for="monthGroup in archive.months" :key="monthGroup.name">
                                    <div class="space-y-1">
                                        
                                        <button @click="monthGroup.is_open = !(monthGroup.is_open ?? false)"
                                                class="w-full text-left px-2 py-1.5 hover:bg-slate-100/80 dark:hover:bg-gray-800/80 rounded-lg flex items-center justify-between transition text-[11px] font-semibold text-slate-600 dark:text-gray-400">
                                            <span x-text="'🔹 ' + monthGroup.name"></span>
                                            <span class="text-[9px] text-slate-400 transition-transform duration-200" :class="(monthGroup.is_open ?? false) ? 'rotate-90' : ''">▶</span>
                                        </button>
                                        <!-- Das Herz des Systems -->
                                        <div x-show="monthGroup.is_open ?? false" class="pl-2 ml-1 border-l border-slate-200 dark:border-gray-700 space-y-2 pt-1 pb-2">
                                            <template x-for="(item, index) in monthGroup.items" :key="item.id || index">
                                                <div class="relative group pl-4 py-1.5 flex items-center hover:bg-slate-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer"
                                                    @click.stop="dispatchAction(`${item.type}_view`, { hash: item.id }, $el)">
                                                    
                                                    <div class="absolute left-0 top-0 bottom-0 w-px bg-slate-200 dark:bg-gray-700"></div>
                                                    <div class="absolute left-[-3px] top-2.5 w-2 h-2 rounded-full border-2 border-white dark:border-gray-900 bg-slate-300 dark:bg-gray-600 group-hover:bg-emerald-500 transition-colors"></div>
                                                    
                                                    <div class="flex items-center gap-3 w-full text-[11px]">
                                                        <span class="w-4 shrink-0 flex justify-center" 
                                                            :class="{
                                                                'text-emerald-600': item.critical === 'green',
                                                                'text-rose-600': item.critical === 'red',
                                                                'text-slate-500': !item.critical
                                                            }">
                                                            <span class="text-[10px] w-6 shrink-0 text-slate-500">
                                                                <svg class="w-3 h-3">
                                                                    <use :href="'public/assets/sprite.svg#icon-' + {
                                                                        'mail': 'mail',
                                                                        'mail-send': 'mail-send',
                                                                        'contact': 'contact',
                                                                        'document': 'document',
                                                                        'paperless': 'document',
                                                                        'note': 'note',
                                                                        'appointment': 'appointment'
                                                                    }[item.type]"></use>
                                                                </svg>
                                                            </span>
                                                        </span>

                                                        <span x-show="item.is_flagged" class="text-[10px] shrink-0 animate-pulse" title="Markiert">📌</span>
                                                        
                                                        <span class="flex-1 font-medium truncate transition-colors"
                                                            :class="{
                                                                'text-emerald-600 dark:text-emerald-400': item.critical === 'green',
                                                                'text-rose-600 dark:text-rose-400': item.critical === 'red',
                                                                'text-amber-500 dark:text-amber-400': item.critical === 'yellow',
                                                                'text-slate-700 dark:text-gray-300': !item.critical || item.critical === 'none'
                                                            }"
                                                            x-text="(item.title || 'Unbenannt').substring(0, 30) + (item.title?.length > 30 ? '...' : '')"
                                                            :title="item.title">
                                                        </span>

                                                        <div class="opacity-0 group-hover:opacity-100 flex items-center space-x-1 transition-opacity duration-150 shrink-0 bg-slate-50 dark:bg-gray-800/90 pl-1 rounded">
                                                            <button @click.stop="dispatchAction('open_modal', { ...item, action_type: 'appointment' })" class="p-0.5 hover:bg-slate-200 dark:hover:bg-gray-700 rounded transition text-[10px]">📅</button>
                                                            <button @click.stop="dispatchAction('open_modal_notes', { ...item, action_type: 'note' }, item)" class="p-0.5 hover:bg-slate-200 dark:hover:bg-gray-700 rounded transition text-[10px]">📝</button>
                                                        </div>

                                                        <span class="font-mono text-[10px] shrink-0 transition-colors" 
                                                            :class="(new Date(item.date_raw) > new Date()) ? 'text-slate-900 dark:text-white font-bold' : 'text-slate-400'"
                                                            x-text="item.date">
                                                        </span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            
            <div x-show="activeTab === 'notifications'" class="space-y-3">
                <div class="flex items-center justify-between shrink-0">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Mitteilungszentrale</span>
                    <button x-show="notifications?.length > 0" @click="clearAllNotifications()" class="text-[10px] text-red-500 hover:underline">Alle löschen</button>
                </div>
                <div class="space-y-2">
                    <template x-for="item in notifications" :key="item.id">
                        <div class="p-3 rounded-xl border relative flex flex-col text-xs bg-white dark:bg-gray-800"
                             :class="{
                                'border-red-200 dark:border-red-900/40 bg-red-50/10': item.type === 'danger',
                                'border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/10': item.type === 'success',
                                'border-blue-200 dark:border-blue-900/40 bg-blue-50/10': item.type === 'info'
                             }">
                            <div class="flex justify-between font-bold text-slate-700 dark:text-gray-200 pr-4">
                                <span x-text="item.title"></span>
                                <span class="text-[9px] font-normal text-slate-400" x-text="item.time"></span>
                            </div>
                            <p class="text-slate-500 dark:text-gray-400 mt-1 text-[11px]" x-text="item.message"></p>
                            <button @click="clearNotification(item.id)" class="absolute top-2 right-2 text-slate-400 text-[10px]">✕</button>
                        </div>
                    </template>
                    <div x-show="!notifications || notifications.length === 0" class="text-center py-8 text-xs text-slate-400 italic">Keine neuen Mitteilungen.</div>
                </div>
            </div> 
        </div>
        <div class="p-3 bg-white dark:bg-gray-800 border-t border-slate-200 dark:border-gray-700 flex gap-2 shrink-0 sticky bottom-0 z-10 shadow-lg-top">
            <button @click="triggerOllama()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold py-2 rounded-lg transition-colors flex items-center justify-center space-x-1 shadow-sm">
                <span>🧠</span> <span>KI Kontext</span>
            </button>
            <div class="px-2.5 py-2 bg-slate-50 dark:bg-gray-900 border border-slate-200 dark:border-gray-700 rounded-lg text-[10px] font-mono text-slate-500 flex items-center shadow-sm">
                HA: <span class="ml-1 font-bold text-blue-500" x-text="typeof haStatus !== 'undefined' ? haStatus : 'Verbunden'"></span>
            </div>
        </div>
    </aside>
</div>