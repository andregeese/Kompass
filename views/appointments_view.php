<div x-data="appointmentView" 
     x-init="initCalendar()" 
     class="px-6 py-12 lg:px-8 w-full max-w-4xl mx-auto">
    
    <div x-show="loading" class="flex justify-center p-10">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>

    <div id="calendar-container" class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 h-[700px] overflow-hidden">
        <div id="calendar"></div>
    </div>
</div>