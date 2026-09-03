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

<header class="bg-white dark:bg-gray-800 border-b border-slate-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between shadow-sm shrink-0 gap-2">
    <!-- Links: Logo & Titel (Auf Mobile kompakt) -->
    <a href="index.php?view=start" class="flex items-center space-x-2 md:space-x-3 shrink-0 hover:opacity-80 transition-opacity">
        <span class="text-xl">
            <svg class="w-12 h-12 md:w-12 md:h-12 shrink-0" viewBox="0 0 60 60">
                <use href="public/assets/sprite.svg#icon-custom-compass"></use>
            </svg>
        </span>
        <h1 class="text-sm md:text-lg font-bold tracking-tight bg-gradient-to-r from-blue-600 to-indigo-500 bg-clip-text text-transparent dark:from-blue-400 hidden sm:block">
            Kompass
        </h1>
    </a>
    
    <!-- Mitte: Globale Echtzeit-Kontaktsuche (Jetzt immer sichtbar!) -->
    <div class="flex-1 max-w-md mx-2 md:mx-6 relative">
        <input type="text" x-model="searchQuery" @input.debounce.400ms="triggerSearch()" placeholder="Suche..." 
            class="w-full bg-slate-100 dark:bg-gray-700 border-0 rounded-xl px-3 py-1.5 text-xs md:text-sm focus:ring-2 focus:ring-blue-500 placeholder-slate-400 dark:placeholder-gray-400 text-slate-900 dark:text-white transition-all">
        <div class="absolute right-3 top-2" x-show="loading">
            <div class="animate-spin rounded-full h-4 w-4 border-2 border-blue-500 border-t-transparent"></div>
        </div>
        <div x-show="results && results.length > 0" 
            @click.away="results = []"
            class="absolute left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg shadow-xl max-h-[300px] overflow-y-auto z-[9999] p-1" 
            x-cloak>
                    
            <template x-for="(item, index) in results" :key="item.id || index">
                <div class="relative group pl-2 py-2 flex items-center hover:bg-slate-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer border-b border-slate-50 dark:border-gray-700/50 last:border-0"
                    @click.stop="dispatchAction(`${item.type}_view`, { hash: item.id }, $el)">
                    
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

                    <div class="flex-1 min-w-0 pr-2">
                        <div class="font-bold text-[11px] truncate text-slate-800 dark:text-gray-200" 
                            x-text="item.title"></div>
                        <div x-show="item.snippet" 
                            class="text-[9px] text-slate-400 truncate" 
                            x-text="item.snippet"></div>
                    </div>

                    <div class="opacity-0 group-hover:opacity-100 flex items-center space-x-1 transition-opacity duration-150 shrink-0 bg-slate-50 dark:bg-gray-800/90 pl-1 rounded">
                        <button @click.stop="dispatchAction('open_modal', { ...item, action_type: 'appointment' })" 
                                title="Termin erstellen" 
                                class="p-0.5 hover:bg-slate-200 dark:hover:bg-gray-700 rounded transition text-[10px]">
                            📅
                        </button>
                        <button @click.stop="dispatchAction('open_modal_notes', { ...item, action_type: 'note' }, item)" 
                                title="Notiz hinzufügen" 
                                class="p-0.5 hover:bg-slate-200 dark:hover:bg-gray-700 rounded transition text-[10px]">
                            📝
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
    <!-- Rechts: Actions & Logout -->
    <div class="flex items-center space-x-2 md:space-x-3 shrink-0">
        <!-- Online/Offline Punkt -->
        <div class="h-2 w-2 rounded-full transition-colors duration-300" :class="isOnline ? 'bg-green-500 shadow-green-500/50 shadow' : 'bg-amber-500 shadow-amber-500/50'"></div>
        <!-- Darkmode Toggle -->
        <button @click="toggleTheme()" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-gray-700 text-slate-500 dark:text-slate-400 transition-colors text-sm">
            <span x-text="isDarkMode ? '☀️' : '🌙'"></span>
        </button>
        <!-- 🚪 Logout Button -->
        <a href="./index.php?view=login&logout=1" 
           @click="if(typeof logout === 'function') { logout(); }" 
           class="p-2 rounded-xl hover:bg-red-50 dark:hover:bg-red-950/30 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 transition-colors text-sm flex items-center justify-center" 
           title="Abmelden">
            <span>🚪</span>
        </a>
    </div>
</header>