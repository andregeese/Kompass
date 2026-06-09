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

<nav class="w-64 bg-white dark:bg-gray-800 border-r border-slate-200 dark:border-gray-700 p-4 hidden md:flex flex-col justify-between shrink-0">
    <div class="space-y-1">
        <a href="index.php?view=mails" :class="currentView === 'mails' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 font-semibold' : 'text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-750'" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-xl text-sm transition-all">
            <span>📬</span> <span>E-Mails & Postkorb</span>
        </a>
        <a href="index.php?view=notes" :class="currentView === 'notes' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 font-semibold' : 'text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-750'" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-xl text-sm transition-all">
            <span>📝</span> <span>Notizen & Memos</span>
        </a>
        <a href="index.php?view=paperless" :class="currentView === 'paperless' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 font-semibold' : 'text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-750'" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-xl text-sm transition-all">
            <span>🗄️</span> <span>Paperless Archiv</span>
        </a>
        <a href="index.php?view=appointments" :class="currentView === 'appointments' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 font-semibold' : 'text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-750'" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-xl text-sm transition-all">
            <span>📅</span> <span>Kalender & Termine</span>
        </a>
        <a href="index.php?view=contacts" :class="currentView === 'contacts' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 font-semibold' : 'text-slate-600 dark:text-gray-400 hover:bg-slate-50 dark:hover:bg-gray-750'" class="w-full flex items-center space-x-3 px-4 py-2.5 rounded-xl text-sm transition-all">
            <span>👥</span> <span>Kontakte & Partner</span>        
        </a>
    </div>
    <div class="bg-slate-50 dark:bg-gray-850 p-3 rounded-xl space-y-2 text-[11px] border border-slate-100 dark:border-gray-700">
        <div class="flex items-center justify-between text-slate-500"><span class="font-medium">Home Assistant:</span> <span class="text-slate-700 dark:text-slate-300">Online</span></div>
        <div class="flex items-center justify-between text-slate-500"><span class="font-medium">Ollama Core Engine:</span> <span class="text-green-500 font-semibold">Aktiv</span></div>
    </div>
</nav>