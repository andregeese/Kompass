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

<div x-data="appointmentView"
    x-init="syncAppointments()"
    class="px-6 py-12 lg:px-8 w-full max-w-4xl mx-auto">

    <div x-show="loading" class="flex justify-center p-10">
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>
    <div id="calendar-container" class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-700 h-[700px] overflow-auto">
        <div id="calendar"></div>
    </div>
</div>
<div x-data="appointmentEditor"
    @open-appointment-modal.window="loadAppointment($event.detail)"
    x-show="isOpen"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-md p-6 border border-gray-200 dark:border-gray-700"
        @click.away="isOpen = false">

        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Kontakt verknüpfen</h2>

        <div class="relative mb-4">
            <input type="text"
                x-model="contactSearch"
                @input.debounce.300ms="performContactSearch()"
                placeholder="Name oder E-Mail suchen..."
                class="w-full p-3 rounded-xl border border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-900 text-sm">
            <div x-show="loading" class="absolute right-3 top-3 text-xs text-blue-500">Suche...</div>
        </div>
        <div class="flex flex-nowrap gap-1.5 overflow-x-auto pb-2 scrollbar-none snap-x">
            <template x-for="contact in filteredContacts" :key="contact.ceid">
                <button type="button"
                    @click="formData.ceid = contact.ceid; contactSearch = contact.email"
                    :class="formData.ceid === contact.ceid ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600'"
                    class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-medium border transition-all">

                    <span x-text="contact.email" class="whitespace-nowrap truncate max-w-[120px]"></span>

                    <span class="ml-1.5 opacity-50" x-text="formData.ceid === contact.ceid ? '✕' : '+'"></span>
                </button>
            </template>
        </div>
        <button @click="save()" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold transition-all">
            Speichern
        </button>
    </div>
</div>