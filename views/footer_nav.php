<footer class="md:hidden bg-white dark:bg-gray-800 border-t border-slate-200 dark:border-gray-700 h-16 flex items-center justify-around px-2 shrink-0 z-30 shadow-lg">
    <a href="index.php?view=mails" :class="currentView === 'mails' ? 'text-blue-500 font-bold' : 'text-slate-400 dark:text-gray-400'" class="flex flex-col items-center space-y-0.5 transition-colors">
        <span class="text-lg">📬</span><span class="text-[10px]">Mails</span>
    </a>
    <a href="index.php?view=notes" :class="currentView === 'notes' ? 'text-blue-500 font-bold' : 'text-slate-400 dark:text-gray-400'" class="flex flex-col items-center space-y-0.5 transition-colors">
        <span class="text-lg">📝</span><span class="text-[10px]">Memos</span>
    </a>
    <a href="index.php?view=paperless" :class="currentView === 'paperless' ? 'text-blue-500 font-bold' : 'text-slate-400 dark:text-gray-400'" class="flex flex-col items-center space-y-0.5 transition-colors">
        <span class="text-lg">🗄️</span><span class="text-[10px]">DMS</span>
    </a>
    <a href="index.php?view=appointments" :class="currentView === 'appointments' ? 'text-blue-500 font-bold' : 'text-slate-400 dark:text-gray-400'" class="flex flex-col items-center space-y-0.5 transition-colors">
        <span class="text-lg">📅</span><span class="text-[10px]">Termine</span>
    </a>
    <div x-data="{ 
        checkNotificationPermission() {
            if (Notification.permission !== 'granted') {
                Notification.requestPermission();
            }
        }
    }">
        <button @click="checkNotificationPermission()" class="px-3 py-1.5 bg-slate-100 dark:bg-gray-750 rounded-lg text-xs font-semibold">
            🔔 Mitteilungen erlauben
        </button>
    </div>
</footer>