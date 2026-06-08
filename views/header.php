/<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
?>

<header class="bg-white dark:bg-gray-800 border-b border-slate-200 dark:border-gray-700 px-4 py-3 flex items-center justify-between shadow-sm shrink-0 gap-2">
    <!-- Links: Logo & Titel (Auf Mobile kompakt) -->
    <div class="flex items-center space-x-2 md:space-x-3 shrink-0">
        <span class="text-xl">🚀</span>
        <h1 class="text-sm md:text-lg font-bold tracking-tight bg-gradient-to-r from-blue-600 to-indigo-500 bg-clip-text text-transparent dark:from-blue-400 hidden sm:block">Enterprise CRM</h1>
    </div>
    
    <!-- Mitte: Globale Echtzeit-Kontaktsuche (Jetzt immer sichtbar!) -->
    <div class="flex-1 max-w-md mx-2 md:mx-6 relative">
        <input type="text" x-model="searchQuery" @keyup.enter="triggerSearch()" placeholder="Suche..." 
            class="w-full bg-slate-100 dark:bg-gray-700 border-0 rounded-xl px-3 py-1.5 text-xs md:text-sm focus:ring-2 focus:ring-blue-500 placeholder-slate-400 dark:placeholder-gray-400 text-slate-900 dark:text-white transition-all">
        <div class="absolute right-3 top-2" x-show="loading">
            <div class="animate-spin rounded-full h-4 w-4 border-2 border-blue-500 border-t-transparent"></div>
        </div>
        <div x-show="results && results.length > 0" 
            @click.away="results = []"
            class="absolute left-0 right-0 mt-1 bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-lg shadow-xl max-h-60 overflow-y-auto z-50 p-1 space-y-0.5" x-cloak>
            
            <template x-for="c in results" :key="c.id">
                <div @click="selectContact(c.id)" 
                    class="p-2 hover:bg-slate-100 dark:hover:bg-gray-700 rounded-md cursor-pointer transition text-xs">
                    <div class="font-bold text-slate-800 dark:text-gray-200" x-text="c.name"></div>
                    <div class="text-slate-400 truncate" x-text="c.email + (c.company ? ' • ' + c.company : '')"></div>
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