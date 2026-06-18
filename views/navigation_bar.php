<?php
/**
 * START_LICENSE
 * Kompass Hub - Unified Responsive Navigation
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * END_LICENSE
 */
?>

<nav class="fixed bottom-0 inset-x-0 h-16 bg-white dark:bg-gray-800 border-t border-slate-200 dark:border-gray-700 flex flex-row justify-around items-center z-50 shadow-lg pb-safe
            md:relative md:bottom-auto md:inset-x-auto md:h-full md:w-64 md:flex-col md:justify-between md:border-t-0 md:border-r md:p-4 md:shadow-none md:shrink-0 md:pb-4">
    
    <div class="flex flex-row justify-around w-full items-center md:flex-col md:space-y-1 md:justify-start md:items-stretch">
        
        <a href="index.php?view=mails" 
           :class="currentView === 'mails' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm group">
            <svg class="w-5 h-5 md:w-4 md:h-4 shrink-0"><use href="public/assets/sprite.svg#icon-mail"></use></svg>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Mails</span>
        </a>

        <a href="index.php?view=notes" 
           :class="currentView === 'notes' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <svg class="w-5 h-5 md:w-4 md:h-4 shrink-0 group-hover:rotate-45 duration-300"><use href="public/assets/sprite.svg#icon-note"></use></svg>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Memos</span>
        </a>

        <a href="index.php?view=paperless" 
           :class="currentView === 'paperless' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <svg class="w-5 h-5 md:w-4 md:h-4 shrink-0 group-hover:rotate-45 duration-300"><use href="public/assets/sprite.svg#icon-document"></use></svg>
            <span class="text-[10px] md:text-sm whitespace-nowrap">DMS</span>
        </a>

        <a href="index.php?view=appointments" 
           :class="currentView === 'appointments' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <svg class="w-5 h-5 md:w-4 md:h-4 shrink-0 group-hover:rotate-45 duration-300"><use href="public/assets/sprite.svg#icon-appointment"></use></svg>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Termine</span>
        </a>

        <a href="index.php?view=contacts" 
           :class="currentView === 'contacts' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <svg class="w-5 h-5 md:w-4 md:h-4 shrink-0 group-hover:rotate-45 duration-300"><use href="public/assets/sprite.svg#icon-contact"></use></svg>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Kontakte</span>
        </a>

        <a href="index.php?view=setting" 
           :class="currentView === 'setting' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm group">
            <svg class="w-5 h-5 md:w-4 md:h-4 shrink-0 group-hover:rotate-45 duration-300"><use href="public/assets/sprite.svg#icon-setting"></use></svg>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Einstellungen</span>
        </a>

    </div>

    <div class="hidden md:block bg-slate-50 dark:bg-gray-850 p-3 rounded-xl space-y-2 text-[11px] border border-slate-100 dark:border-gray-700 w-full mt-auto">
        <div class="flex items-center justify-between text-slate-500">
            <span class="font-medium">Home Assistant:</span> 
            <span class="text-slate-700 dark:text-slate-300">Online</span>
        </div>
        <div class="flex items-center justify-between text-slate-500">
            <span class="font-medium">Ollama Core Engine:</span> 
            <span class="text-green-500 font-semibold">Aktiv</span>
        </div>
    </div>
</nav>