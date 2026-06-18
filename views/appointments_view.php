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

<div x-data="appointmentView"
    x-init="initComponent()"
    class="px-4 py-8 md:px-8 w-full mx-auto h-full flex flex-col min-h-0">

    <div class="flex items-center justify-between mb-4 shrink-0">
        <div>
            <h1 class="text-xl font-bold text-slate-800 dark:text-white">Terminkalender</h1>
            <p class="text-xs text-slate-400 dark:text-gray-500">Klick führt direkt zum verknüpften Element.</p>
        </div>
        
        <div class="flex items-center space-x-3 text-xs text-blue-500">
            <button @click="syncAppointments()" class="px-2.5 py-1 bg-slate-100 dark:bg-gray-700 hover:bg-slate-200 dark:hover:bg-gray-650 text-slate-700 dark:text-gray-200 rounded-lg font-medium transition-all text-[11px]">
                🔄 Full-Sync
            </button>
            <div x-show="loading" class="flex items-center space-x-1.5">
                <div class="animate-spin rounded-full h-3 w-3 border-b-2 border-blue-600"></div>
            </div>
        </div>
    </div>

    <div class="mb-4 flex items-center justify-between bg-slate-50 dark:bg-gray-800/40 border border-slate-100 dark:border-gray-750 p-2 rounded-xl shrink-0 select-none">
        <div class="flex gap-1">
            <button @click="navigateWindow(-7)" class="p-2 text-slate-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 hover:bg-white dark:hover:bg-gray-700 rounded-lg transition-all text-xs font-bold" title="Eine Woche zurück">
                ◀
            </button>
            <button @click="goToToday()" class="px-3 py-1 bg-white dark:bg-gray-700 hover:bg-slate-50 dark:hover:bg-gray-650 text-slate-700 dark:text-gray-200 rounded-lg font-semibold border border-slate-100 dark:border-gray-600 transition-all text-[11px]">
                Heute
            </button>
        </div>

        <div class="text-center">
            <span class="text-xs font-bold text-slate-700 dark:text-gray-200 block font-mono" x-text="dateRangeText"></span>
            <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 block mt-0.5" x-text="calendarWeekText"></span>
        </div>

        <button @click="navigateWindow(7)" class="p-2 text-slate-600 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400 hover:bg-white dark:hover:bg-gray-700 rounded-lg transition-all text-xs font-bold" title="Eine Woche vor">
            ▶
        </button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-100 dark:border-gray-700/60 flex-1 flex flex-col min-h-0 overflow-hidden">
        
        <div class="flex-1 overflow-y-auto p-6 space-y-3">
            
            <template x-if="!loading && appointments.length === 0">
                <div class="flex flex-col items-center justify-center py-12 text-slate-400 dark:text-gray-500">
                    <span class="text-3xl mb-2">💤</span>
                    <p class="text-sm">Keine Termine in diesem Zeitraum gefunden.</p>
                </div>
            </template>

            <template x-for="(appt, index) in appointments" :key="appt.unique_id">
                <div :class="{
                    'group flex items-center gap-4 p-3 rounded-xl border transition-all cursor-pointer': true,
                    'bg-blue-50/40 dark:bg-blue-950/15 border-blue-100 dark:border-blue-900/40 shadow-sm': appt.is_today == 1,
                    'border-transparent hover:bg-slate-50 dark:hover:bg-gray-750/50 hover:border-slate-100 dark:hover:border-gray-750': appt.is_today == 0,
                    'border-t border-t-slate-200 dark:border-t-gray-750 pt-5 mt-5 rounded-t-none': appt.week_transition == 1
                }">
                    
                    <div @click="handleItemClick(appt)" class="flex-1 flex items-start gap-4 min-w-0">
                        <div class="w-20 shrink-0 pt-0.5">
                            <span :class="appt.time_str.includes('🌴') ? 'text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider' : 'text-xs font-semibold text-slate-700 dark:text-gray-350'"
                                class="block" 
                                x-text="appt.time_str"></span>
                            <span class="text-[10px] text-slate-400 dark:text-gray-500 block mt-0.5"
                                x-text="appt.date_str"></span>
                        </div>

                        <div class="relative h-full flex flex-col items-center shrink-0 pt-2">
                            <div :class="appt.ceid ? 'bg-emerald-500' : (appt.uid ? 'bg-amber-500' : 'bg-blue-500')" class="w-2 h-2 rounded-full z-10 shadow-sm"></div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h4 class="text-sm font-semibold text-slate-800 dark:text-gray-200 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate"
                                    x-text="appt.summary || appt.title || '(Kein Titel)'"></h4>
                                
                                <template x-if="appt.is_today == 1">
                                    <span class="text-[9px] bg-blue-600 text-white font-bold px-1 py-0.2 rounded uppercase tracking-wider scale-90">Heute</span>
                                </template>
                            </div>
                            
                            <div class="flex items-center gap-2 mt-1">
                                <template x-if="appt.ceid">
                                    <span class="bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 text-[9px] px-1.5 py-0.5 rounded font-medium border border-emerald-100 dark:border-emerald-900/30">👤 Kontakt</span>
                                </template>
                                <template x-if="!appt.ceid">
                                    <span class="bg-slate-50 dark:bg-gray-700 text-slate-400 dark:text-gray-400 text-[9px] px-1.5 py-0.5 rounded">Unverknüpft</span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <button @click.stop="dispatchAction('open_modal', { targetEvent: 'open-appointment-modal', payload: appt })" 
                            class="p-2 text-slate-400 hover:text-blue-600 dark:text-gray-500 dark:hover:text-blue-400 rounded-lg hover:bg-slate-100 dark:hover:bg-gray-700 transition-colors"
                            title="Verknüpfung bearbeiten">
                        <span class="text-sm">🔗</span>
                    </button>
                </div>
            </template>

        </div>
    </div>
</div>

<div x-data="appointmentEditorComponent()" 
     x-show="isOpen" 
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
     x-cloak>
    
    <div @click.away="close()" 
         class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-slate-100 dark:border-gray-700 overflow-hidden transform transition-all">
        
        <div class="p-4 border-b border-slate-100 dark:border-gray-700 flex justify-between items-center bg-slate-50 dark:bg-gray-850">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-gray-200">
                <span x-text="form.id ? '🔗 Verknüpfung bearbeiten' : '➕ Neuer Termin'"></span>
            </h3>
            <button @click="close()" class="text-slate-400 hover:text-slate-600 dark:hover:text-gray-300 text-lg">✕</button>
        </div>

        <div class="p-5 space-y-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 dark:text-gray-400 mb-1">Titel / Notiz</label>
                <input type="text" x-model="form.summary" 
                       class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-xl focus:outline-none focus:border-blue-500 dark:text-gray-100">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-gray-400 mb-1">Startzeit</label>
                    <input type="datetime-local" x-model="form.start_time" 
                           class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-xl focus:outline-none focus:border-blue-500 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-gray-400 mb-1">Endzeit</label>
                    <input type="datetime-local" x-model="form.end_time" 
                           class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-xl focus:outline-none focus:border-blue-500 dark:text-gray-100">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-gray-700">
                <label class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2 flex justify-between items-center">
                    <span>👤 Kontakt verknüpfen</span>
                    <span x-show="loadingMatch" class="text-blue-500 italic lowercase animate-pulse">suche...</span>
                </label>

                <input type="text" 
                    x-model="emailQuery" 
                    @input.debounce.500ms="performMatchSearch()"
                    placeholder="Nach Name oder E-Mail suchen..."
                    class="w-full text-xs p-2 mb-2 rounded-xl border border-slate-200 dark:border-gray-700 bg-slate-50 dark:bg-gray-900 focus:border-blue-500 outline-none dark:text-gray-100">

                <template x-if="form.ceid">
                    <div class="mb-3 flex items-center justify-between p-2 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs text-emerald-800 dark:text-emerald-400">
                        <span class="font-medium flex items-center gap-1.5 truncate">
                            <span>✅ Verknüpft mit Kontakt-ID:</span>
                            <strong x-text="form.ceid"></strong>
                        </span>
                        <button type="button" @click="form.ceid = null" class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 font-bold px-1.5 py-0.5 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 rounded-md">✕</button>
                    </div>
                </template>

                <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto pt-1">
                    <template x-for="contact in sortedMatchResults" :key="contact.ceid">
                        <button type="button"
                            @click="toggleContactLink(contact)"
                            :class="Number(form.ceid) === Number(contact.ceid) ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-400' : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-gray-900 dark:border-gray-700 dark:text-slate-400'"
                            class="flex-shrink-0 inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-medium border transition-all hover:scale-[1.02]">

                            <span x-text="contact.email || contact.name" class="whitespace-nowrap truncate max-w-[160px]"></span>

                            <span class="ml-1.5 opacity-60 font-bold" x-text="form.ceid === contact.ceid ? '✕' : '+'"></span>
                        </button>
                    </template>
                </div>

                <div x-show="sortedMatchResults.length === 0 && emailQuery.length < 2 && !form.ceid" class="text-[11px] text-slate-400 italic mt-1">
                    Tippe oben einen Suchbegriff ein, um Kontakte zu finden.
                </div>
            </div>
        </div>

        <div class="p-4 bg-slate-50 dark:bg-gray-850 border-t border-slate-100 dark:border-gray-700 flex justify-end gap-2">
            <button @click="close()" 
                    class="px-4 py-2 text-xs font-medium text-slate-600 dark:text-gray-400 hover:bg-slate-100 dark:hover:bg-gray-700 rounded-xl transition-colors">
                Abbrechen
            </button>
            <button @click="save()" 
                    :disabled="loading"
                    class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 rounded-xl transition-colors flex items-center gap-1.5">
                <template x-if="loading">
                    <span class="animate-spin inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full"></span>
                </template>
                <span>Speichern</span>
            </button>
        </div>
    </div>
</div>