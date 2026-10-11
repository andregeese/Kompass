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
<div x-data="dashboardView" class="flex-1 h-full overflow-y-auto w-full max-w-5xl mx-auto p-4 md:p-6 space-y-6">
    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center space-x-2">
                <span>🚀</span> 
                <span>Daten-Zentrale</span>
            </h2>
            <p class="text-[11px] text-slate-400 dark:text-gray-500 font-medium tracking-wide mt-0.5" x-text="getTodayString()"></p>
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto">
            <span class="text-[10px] uppercase font-bold tracking-wider bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 px-2.5 py-1 rounded-full border border-blue-200 dark:border-blue-900/40 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse"></span>
                Paperless: Ready
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <!-- Unklassifiziert / Posteingang -->
        <div class="bg-white dark:bg-gray-800 p-3 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm text-center space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 block">📥 Ohne Dokumententyp</span>
            <span class="text-xl font-black text-blue-600 dark:text-blue-400 block font-mono" x-text="paperlessInfo?.details?.paperless_info?.unclassified || 0">0</span>
        </div>

        <!-- Im DMS / In Bearbeitung -->
        <div class="bg-white dark:bg-gray-800 p-3 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm text-center space-y-1 relative overflow-hidden">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 block">⏳ Im DMS</span>
            <span class="text-xl font-black text-amber-500 block font-mono" x-text="paperlessInfo?.details?.paperless_info?.dms || 0">0</span>
            <div x-show="(paperlessInfo?.details?.paperless_info?.dms || 0) > 0" class="absolute inset-x-0 bottom-0 h-1 bg-amber-500/20 dark:bg-amber-500/10 animate-pulse"></div>
        </div>

        <!-- Archiviert -->
        <div class="bg-white dark:bg-gray-800 p-3 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm text-center space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 block">✅ Archiviert (letzte 4W)</span>
            <span class="text-xl font-black text-emerald-500 block font-mono" x-text="paperlessInfo?.details?.paperless_info?.archvied || paperlessInfo?.details?.paperless_info?.archived || 0">0</span>
        </div>

        <!-- Kontakte / Korrespondenten -->
        <div class="bg-white dark:bg-gray-800 p-3 rounded-2xl border border-slate-200 dark:border-gray-700 shadow-sm text-center space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 block">👥 Kontakte</span>
            <span class="text-xl font-black text-purple-600 dark:text-purple-400 block font-mono" x-text="paperlessInfo?.details?.paperless_info?.recipients || 0">0</span>
        </div>
    </div>

    <template x-if="acceleratorClusters.length > 0">
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 dark:from-gray-850 dark:to-gray-900 p-4 rounded-2xl shadow-md border border-slate-800 space-y-3 animate-fadeIn">
            <div class="flex items-center justify-between border-b border-slate-800/60 pb-2">
                <div class="flex items-center space-x-2">
                    <span class="text-sm">📯</span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Top Kontakte</h3>
                    <svg x-show="isLoadingClusters" class="w-3.5 h-3.5 text-blue-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div class="flex items-center space-x-2">
                    <select id="timeFilter" x-model="timeFilter" @change="changeTimeFilter($event)" class="bg-slate-900 text-slate-300 text-[10px] border border-slate-700 rounded-md px-2 py-0.5 focus:outline-none focus:border-blue-500">
                        <option value="4">4 Wochen</option>
                        <option value="8">8 Wochen</option>
                        <option value="12">12 Wochen</option>
                        <option value="20">20 Wochen</option>
                        <option value="40">40 Wochen</option>
                        <option value="56">56 Wochen</option>
                        <option value="all">Alle</option>
                    </select>
                    <span class="text-[9px] bg-blue-500/15 text-blue-400 border border-blue-500/20 px-2 py-0.5 rounded-md font-bold uppercase tracking-wider">Smart Vorschlag</span>
                </div>
            </div>

            <div class="space-y-2 max-h-[32vh] overflow-y-auto pr-1.5 scrollbar-thin scrollbar-thumb-slate-800">
                <template x-for="cluster in acceleratorClusters" :key="cluster.sender">
                    <div @click.stop="dispatchAction('search_mails', { query: cluster.sender, folder:cluster.folder })"
                        class="flex items-center justify-between p-3 bg-white/10 hover:bg-white/15 rounded-xl border border-white/10 transition-all gap-4 cursor-pointer group shadow-sm">
                        
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-bold text-white group-hover:text-blue-400 transition-colors truncate" x-text="cluster.showname"></div>
                            <div class="text-[9px] text-slate-300 font-mono mt-0.5" x-text="(cluster.mail_count || cluster.count) + ' Mails in ' + cluster.folder"></div>
                        </div>
                        
                        <button x-show="cluster.allow_acceleration == 1"
                                @click.stop="accelerateCluster(cluster)" 
                                class="bg-blue-600 hover:bg-blue-500 text-white font-bold text-[10px] px-3 py-1.5 rounded-lg transition-colors flex items-center space-x-1 shrink-0 shadow-sm">
                            <span>📦</span>
                            <span>Ab ins DMS</span>
                        </button>
                        
                        <span x-show="cluster.allow_acceleration == 0" 
                            class="bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 px-2.5 py-1.5 rounded-lg font-bold text-[9px] uppercase tracking-wider flex items-center space-x-1 shrink-0">
                            <span>✅</span>
                            <span>Im DMS</span>
                        </span>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 px-1 flex items-center gap-1.5">
                <span>📞</span> Kritische Wiedervorlagen
            </h3>
            <div class="space-y-2.5">
                <template x-for="memo in criticalMemos" :key="memo.id">
                    <div @click="$dispatch('navigate', { view: 'notes', id: memo.id })"
                         class="p-3 bg-white dark:bg-gray-800 rounded-xl border border-slate-200/80 dark:border-gray-700 shadow-sm space-y-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-750/60 transition-colors"
                         :class="isOverdue(memo.due_date) ? 'border-l-4 border-l-rose-500' : 'border-l-4 border-l-amber-500'">
                        <p class="text-xs text-slate-700 dark:text-gray-200 font-medium line-clamp-2" x-text="memo.content"></p>
                        <div class="flex items-center justify-between text-[9px] font-mono">
                            <span class="px-1.5 py-0.5 rounded font-bold uppercase tracking-wider border"
                                  :class="isOverdue(memo.due_date) ? 'bg-rose-50 dark:bg-rose-950/20 border-rose-100 dark:border-rose-900/40 text-rose-600 dark:text-rose-400 animate-pulse' : 'bg-amber-50 dark:bg-amber-950/20 border-amber-100 dark:border-amber-900/40 text-amber-600 dark:text-amber-400'">
                                <span x-text="isOverdue(memo.due_date) ? '🔥 Überfällig seit: ' : '📅 Fällig am: '"></span>
                                <span x-text="formatDate(memo.due_date)"></span>
                            </span>
                            <span class="text-slate-400 dark:text-gray-500 max-w-[120px] truncate" x-text="memo.email || 'Allgemein'"></span>
                        </div>
                    </div>
                </template>
                <template x-if="criticalMemos.length === 0">
                    <div class="text-center py-8 border border-dashed border-slate-200 dark:border-gray-700 rounded-xl bg-slate-50/50 dark:bg-gray-850/10">
                        <span class="text-lg opacity-40">☕</span>
                        <p class="text-[11px] text-slate-400 mt-1 italic">Keine fälligen Termine im Nacken.</p>
                    </div>
                </template>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 px-1 flex items-center justify-between">
                <span class="flex items-center gap-1.5">
                    <span>📨</span> Zu beantworten
                </span>
                <span class="text-[10px] font-normal lowercase tracking-normal text-slate-400" x-text="`${shouldBeAnswered.length} Kontakte`"></span>
            </h3>

            <div class="space-y-2.5">
                <template x-for="sBA in shouldBeAnswered" :key="sBA.contact_id || sBA.sender_email">
                    <div @click="$dispatch('navigate', { view: 'contacts', id: sBA.latest_mail_id })"
                        class="relative overflow-hidden p-3 pl-4 bg-white dark:bg-gray-800 rounded-xl border border-slate-200/80 dark:border-gray-700 shadow-sm flex items-center justify-between gap-3 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-750/60 transition-colors group">
                        
                        <!-- Dynamische Farbcodierung (Linker Indikator-Streifen) -->
                        <div class="absolute left-0 top-0 bottom-0 w-1.5 transition-colors"
                            :class="{
                                'bg-emerald-500 dark:bg-emerald-400': sBA.response_rate_pct >= 70,
                                'bg-amber-500 dark:bg-amber-400': sBA.response_rate_pct >= 40 && sBA.response_rate_pct < 70,
                                'bg-rose-500 dark:bg-rose-400': sBA.response_rate_pct < 40
                            }">
                        </div>

                        <!-- Kontakt & Datums-Informationen -->
                        <div class="min-w-0 space-y-0.5 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-medium text-slate-400 dark:text-gray-400 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span x-text="sBA.latest_unanswered_date ? new Date(sBA.latest_unanswered_date).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'Kein Datum'"></span>
                                </span>
                            </div>

                            <span class="text-xs text-slate-800 dark:text-gray-100 font-semibold truncate block group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors" 
                                x-text="sBA.contact_name">
                            </span>

                            <span class="text-[10px] text-slate-400 dark:text-gray-500 truncate block" 
                                x-text="sBA.associated_emails || sBA.sender_email">
                            </span>
                        </div>

                        <!-- Rechte Seite: Offene Mails & Antwortquote-Badge -->
                        <div class="flex items-center gap-2 shrink-0">
                            <!-- Badge: Anzahl offener Mails -->
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-slate-100 text-slate-600 dark:bg-gray-700 dark:text-gray-300"
                                title="Unbeantwortete E-Mails"
                                x-text="`${sBA.unanswered_count} Mail${sBA.unanswered_count > 1 ? 's' : ''}`">
                            </span>

                            <!-- Badge: Historische Antwortquote (%) -->
                            <div class="px-2 py-0.5 text-[10px] font-bold rounded-lg flex items-center gap-1 border"
                                :class="{
                                    'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/50': sBA.response_rate_pct >= 70,
                                    'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/50': sBA.response_rate_pct >= 40 && sBA.response_rate_pct < 70,
                                    'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-800/50': sBA.response_rate_pct < 40
                                }">
                                <span x-text="`${Math.round(sBA.response_rate_pct)}%`"></span>
                            </div>
                        </div>

                    </div>
                </template>
            </div>
            
        </div>

        <div class="space-y-3">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-gray-500 px-1 flex items-center gap-1.5">
                <span>🗓️</span> Anstehende Termine
            </h3>
            <div class="space-y-2.5">
                <template x-for="event in upcomingEvents" :key="event.id">
                    <div @click="$dispatch('navigate', { view: 'appointments', id: event.id })"
                         class="p-3 bg-white dark:bg-gray-800 rounded-xl border border-slate-200/80 dark:border-gray-700 shadow-sm flex items-center justify-between gap-4 cursor-pointer hover:bg-slate-50 dark:hover:bg-gray-750/60 transition-colors">
                        <div class="min-w-0 space-y-0.5">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-blue-500 dark:text-blue-400 block" x-text="event.category || 'Termin'"></span>
                            <span class="text-xs text-slate-700 dark:text-gray-200 font-semibold truncate block" x-text="event.summary || event.title"></span>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-xs font-black text-slate-800 dark:text-white font-mono block" x-text="event.start_time || event.time"></span>
                            <span class="text-[9px] text-slate-400 dark:text-gray-500 font-medium block" x-text="formatDate(event.start_time || event.date)"></span>
                        </div>
                    </div>
                </template>
                <template x-if="upcomingEvents.length === 0">
                    <div class="text-center py-8 border border-dashed border-slate-200 dark:border-gray-700 rounded-xl bg-slate-50/50 dark:bg-gray-850/10">
                        <span class="text-lg opacity-40">🍃</span>
                        <p class="text-[11px] text-slate-400 mt-1 italic">Kalender ist komplett frei.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <template x-if="lastDocs.length > 0">
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 dark:from-gray-850 dark:to-gray-900 p-4 rounded-2xl shadow-md border border-slate-800 space-y-3 animate-fadeIn">
            <div class="flex items-center justify-between border-b border-slate-800/60 pb-2">
                <div class="flex items-center space-x-2">
                    <span>📄</span>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">Unklassifizierte Dokumente (Letzte 5)</h3>
                </div>
                <span class="text-[9px] bg-amber-500/10 text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded-md font-bold uppercase tracking-wider">Paperless Inbox</span>
            </div>

            <div class="space-y-2 max-h-[25vh] overflow-y-auto pr-1.5">
                <template x-for="doc in lastDocs" :key="doc.id">
                    <div @click="dispatchAction('document_view', {hash:doc.id}, null)"
                        class="flex items-center justify-between p-2.5 bg-white/5 hover:bg-white/10 rounded-xl border border-white/5 transition-all cursor-pointer group">
                        
                        <div class="min-w-0 pr-2">
                            <div class="text-[11px] font-bold text-white group-hover:text-amber-400 transition-colors truncate" 
                                x-text="doc.title"></div>
                            
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-[9px] text-amber-500/80 font-medium truncate" 
                                    x-text="doc.correspondent_name"></span>
                                <span class="text-[9px] text-slate-500 font-mono">|</span>
                                <span class="text-[9px] text-slate-400 font-mono" 
                                    x-text="formatDate(doc.created)"></span>
                            </div>
                        </div>

                        <svg class="w-3 h-3 text-slate-500 group-hover:text-amber-400 shrink-0">
                            <use href="public/assets/sprite.svg#icon-document"></use>
                        </svg>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>