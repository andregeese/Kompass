<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub - Unified Responsive Navigation
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * END_LICENSE
 */
?>

<nav class="fixed bottom-0 inset-x-0 h-16 bg-white dark:bg-gray-800 border-t border-slate-200 dark:border-gray-700 flex flex-row justify-around items-center z-50 shadow-lg 
            md:relative md:bottom-auto md:inset-x-auto md:h-full md:w-64 md:flex-col md:justify-between md:border-t-0 md:border-r md:p-4 md:shadow-none md:shrink-0">
    
    <div class="flex flex-row justify-around w-full items-center md:flex-col md:space-y-1 md:justify-start md:items-stretch">
        
        <a href="index.php?view=mails" 
           :class="currentView === 'mails' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <span class="text-lg md:text-base">📬</span>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Mails</span>
        </a>

        <a href="index.php?view=notes" 
           :class="currentView === 'notes' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <span class="text-lg md:text-base">📝</span>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Memos</span>
        </a>

        <a href="index.php?view=paperless" 
           :class="currentView === 'paperless' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <span class="text-lg md:text-base">🗄️</span>
            <span class="text-[10px] md:text-sm whitespace-nowrap">DMS</span>
        </a>

        <a href="index.php?view=appointments" 
           :class="currentView === 'appointments' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <span class="text-lg md:text-base">📅</span>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Termine</span>
        </a>

        <a href="index.php?view=contacts" 
           :class="currentView === 'contacts' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
           class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            <span class="text-lg md:text-base">👥</span>
            <span class="text-[10px] md:text-sm whitespace-nowrap">Kontakte</span>
        </a>

        <a href="index.php?view=setting" 
        :class="currentView === 'setting' ? 'text-blue-500 font-bold md:bg-blue-50 md:text-blue-600 dark:md:bg-blue-900/30 dark:md:text-blue-400 md:font-semibold' : 'text-slate-400 dark:text-gray-400 md:text-slate-600 dark:md:text-gray-400 md:hover:bg-slate-50 dark:md:hover:bg-gray-750'" 
        class="flex flex-col items-center space-y-0.5 transition-all md:flex-row md:space-x-3 md:space-y-0 md:px-4 md:py-2.5 md:rounded-xl md:text-sm">
            
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 md:w-4 md:h-4 transition-transform group-hover:rotate-45 duration-300">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.43l-1.003.767c-.292.223-.434.59-.38.954.007.048.01.096.01.144 0 .048-.003.096-.01.144-.054.364.088.73.38.954l1.003.767a1.125 1.125 0 0 1 .26 1.43l-1.296 2.247a1.125 1.125 0 0 1-1.37.49l-1.216-.456c-.356-.133-.751-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.43l1.004-.767c.292-.224.434-.59.38-.954a1.71 1.71 0 0 1 0-.288c.054-.364-.088-.73-.38-.954l-1.004-.767a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.49l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            
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