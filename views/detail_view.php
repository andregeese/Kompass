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

<div x-data="detailView()" x-init="initDetail()" 
     @route-change.window="
        // 1. Setze visuelle Markierungen für den neuen Hash
        selectedId = $event.detail.hash;
        
        // 2. Triggere das asynchrone Nachladen der API aus der detail.js
        if ($event.detail.hash && typeof checkCRMStatus === 'function') {
            dmsLoading = true;
            await checkCRMStatus($event.detail.hash);
            dmsLoading = false;
        }
     "
     class="flex-1 flex flex-col md:flex-row overflow-hidden bg-slate-50 dark:bg-gray-900">
    
    <div class="flex-1 flex flex-col overflow-hidden p-6 space-y-6">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 dark:border-gray-700 pb-4 shrink-0 gap-2">
            <div class="flex items-center space-x-4">
                <button @click="window.location.href = '?view=mails'" class="text-slate-500 hover:text-slate-700 dark:hover:text-white text-sm flex items-center space-x-1 transition">
                    <span>⬅</span> <span>Zurück zu Mails</span>
                </button>
                <h1 class="text-lg font-bold text-slate-800 dark:text-white">
                    Vorgang: <span class="font-medium text-slate-600 dark:text-slate-300" x-text="currentMail?.subject || 'Lade...'"></span>
                </h1>
            </div>
            <div class="text-xs text-slate-400 bg-slate-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg self-start sm:self-auto">
                Absender: <span class="font-mono text-slate-600 dark:text-gray-300" x-text="currentMail?.from_email || '...' "></span>
            </div>
        </div>
        <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-6 overflow-y-auto pr-2">
            
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-slate-200 dark:border-gray-700 flex flex-col h-[500px] shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 dark:text-gray-200 mb-3 flex items-center space-x-2 border-b border-slate-100 dark:border-gray-700 pb-2">
                    <span class="text-blue-500">📁</span> <span>Paperless Dokumente</span>
                </h3>
                <div class="flex-1 overflow-y-auto space-y-2">
                    <template x-if="loading">
                        <div class="text-center text-xs text-slate-400 py-8 animate-pulse">Lade Dokumente...</div>
                    </template>
                    <template x-for="doc in documents" :key="doc.id">
                        <div class="p-2.5 bg-slate-50 dark:bg-gray-900 rounded-lg border border-slate-100 dark:border-gray-750 flex items-center justify-between text-xs hover:border-blue-500/30 transition">
                            <span class="truncate font-medium text-slate-700 dark:text-gray-300" x-text="doc.title"></span>
                            <button @click="currentView = 'paperless'; selectedDocumentId = doc.id;" class="text-blue-500 hover:text-blue-600 font-semibold shrink-0 ml-2 transition">Öffnen</button>
                        </div>
                    </template>
                    <template x-if="!loading && documents.length === 0">
                        <div class="text-center text-xs text-slate-400 py-8">Keine Dokumente gefunden.</div>
                    </template>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-slate-200 dark:border-gray-700 flex flex-col h-[500px] shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 dark:text-gray-200 mb-3 flex items-center space-x-2 border-b border-slate-100 dark:border-gray-700 pb-2">
                    <span class="text-amber-500">📝</span> <span>Gefundene Notizen</span>
                </h3>
                <div class="flex-1 overflow-y-auto space-y-3">
                    <template x-if="loading">
                        <div class="text-center text-xs text-slate-400 py-8 animate-pulse">Durchsuche Notizen...</div>
                    </template>
                    <template x-for="note in notes" :key="note.id">
                        <div class="p-3 bg-amber-50/30 dark:bg-amber-950/10 border border-amber-200/40 dark:border-amber-900/20 rounded-xl space-y-1.5 text-xs">
                            <div class="font-bold text-slate-800 dark:text-gray-200" x-text="note.title"></div>
                            <p class="text-slate-600 dark:text-gray-400 leading-relaxed" x-html="highlightText(note.content)"></p>
                        </div>
                    </template>
                    <template x-if="!loading && notes.length === 0">
                        <div class="text-center text-xs text-slate-400 py-8">Keine passenden Notizen vorhanden.</div>
                    </template>
                </div>
            </div>
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-slate-200 dark:border-gray-700 flex flex-col h-[500px] shadow-sm">
                <h3 class="text-sm font-bold text-slate-700 dark:text-gray-200 mb-3 flex items-center space-x-2 border-b border-slate-100 dark:border-gray-700 pb-2">
                    <span class="text-emerald-500">📅</span> <span>Kalender-Einträge</span>
                </h3>
                <div class="flex-1 overflow-y-auto space-y-2">
                    <template x-if="loading">
                        <div class="text-center text-xs text-slate-400 py-8 animate-pulse">Lade Termine...</div>
                    </template>
                    <template x-for="event in appointments" :key="event.id">
                        <div class="p-2.5 bg-slate-50 dark:bg-gray-900 rounded-lg border border-slate-100 dark:border-gray-750 text-xs space-y-1">
                            <div class="flex justify-between font-semibold text-slate-700 dark:text-gray-200 gap-2">
                                <span class="truncate" x-text="event.title"></span>
                                <span class="text-[10px] text-blue-500 shrink-0 font-mono" x-text="formatDate(event.start)"></span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-gray-400" x-text="event.description"></p>
                        </div>
                    </template>
                    <template x-if="!loading && appointments.length === 0">
                        <div class="text-center text-xs text-slate-400 py-8">Keine Termine verknüpft.</div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    <div class="w-full md:w-80 bg-white dark:bg-gray-850 border-t md:border-t-0 md:border-l border-slate-200 dark:border-gray-700 p-6 flex flex-col shrink-0 overflow-y-auto">
        <h3 class="text-sm font-bold text-slate-800 dark:text-white mb-4 flex items-center space-x-2">
            <span>👤</span> <span>CRM Verknüpfung</span>
        </h3>
        <div x-show="crmLoading" class="flex items-center space-x-2 text-slate-500 text-xs py-4">
            <div class="animate-spin rounded-full h-3.5 w-3.5 border-2 border-blue-500 border-t-transparent"></div>
            <span>Prüfe CRM-Datenbank...</span>
        </div>
        <div x-show="!crmLoading && contactState" x-cloak class="space-y-5">
            
            <template x-if="contactState.status === 'matched'">
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-500/20 p-4 rounded-xl text-emerald-800 dark:text-emerald-400 space-y-2">
                    <div class="flex items-center space-x-1.5 text-xs font-bold uppercase tracking-wider">
                        <span>✓</span> <span>Aktiviert</span>
                    </div>
                    <div class="font-bold text-base tracking-tight leading-tight" x-text="contactState.contact.display_name"></div>
                    <div class="text-[11px] text-slate-400 dark:text-gray-400 flex items-center space-x-1">
                        <span>Typ:</span> 
                        <span class="font-medium text-slate-600 dark:text-slate-300" x-text="contactState.contact.type === 'company' ? 'Firma' : 'Privatperson'"></span>
                    </div>
                </div>
            </template>
            <template x-if="contactState.status === 'unknown' || contactState.status === 'suggestion'">
                <div class="space-y-4">
                    <template x-if="contactState.status === 'suggestion'">
                        <div class="bg-blue-50 dark:bg-blue-950/20 border border-blue-500/20 p-3 rounded-xl text-blue-700 dark:text-blue-400 text-xs">
                            <div class="font-bold mb-0.5">💡 DMS Match</div>
                            <span x-text="contactState.reason"></span>
                        </div>
                    </template>
                    <div class="bg-slate-50 dark:bg-gray-900/50 p-3 rounded-xl border border-slate-100 dark:border-gray-800 text-xs text-slate-500">
                        Absender ist neu im CRM. Trage ihn hier ein, um Mails und Dokumente dauerhaft zu verbinden.
                    </div>
                    <div class="space-y-3.5 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 dark:text-gray-500 mb-1">E-Mail-Adresse</label>
                            <input type="email" :value="formData.email" disabled
                                class="w-full bg-slate-100 dark:bg-gray-900 text-slate-400 dark:text-gray-500 border-0 rounded-lg px-3 py-2 cursor-not-allowed font-mono text-[11px]">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 dark:text-gray-500 mb-1">Anzeigename / Firma</label>
                            <input type="text" x-model="formData.name" placeholder="z.B. Müller GmbH"
                                class="w-full bg-slate-50 dark:bg-gray-900 text-slate-900 dark:text-white border border-slate-200 dark:border-gray-700 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold uppercase text-slate-400 dark:text-gray-500 mb-1">Kontakt-Typ</label>
                            <div class="flex space-x-4 mt-1.5">
                                <label class="flex items-center space-x-2 text-slate-700 dark:text-gray-300 cursor-pointer">
                                    <input type="radio" name="type" value="company" x-model="formData.type" class="text-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700">
                                    <span class="font-medium">Firma</span>
                                </label>
                                <label class="flex items-center space-x-2 text-slate-700 dark:text-gray-300 cursor-pointer">
                                    <input type="radio" name="type" value="person" x-model="formData.type" class="text-blue-500 focus:ring-blue-500 dark:bg-gray-900 dark:border-gray-700">
                                    <span class="font-medium">Person</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <div class="border-t border-slate-100 dark:border-gray-800 pt-4 space-y-2">
                <div class="flex justify-between items-center text-[10px] font-bold uppercase text-slate-400 dark:text-gray-500">
                    <span>🔗 DMS Korrespondenten</span>
                    <span class="text-blue-500 normal-case font-mono bg-blue-50 dark:bg-blue-950/40 px-1.5 py-0.5 rounded text-[9px]" 
                        x-show="currentSearchQuery" x-text="'Hit: ' + currentSearchQuery"></span>
                </div>
                <div class="relative flex items-center">
                    <input type="text" x-model="manualSearchQuery" @keydown.enter.prevent="triggerManualDMSSearch()" placeholder="Suchen... (Enter drücken)" 
                        class="w-full bg-slate-50 dark:bg-gray-900 text-xs text-slate-900 dark:text-white border border-slate-200 dark:border-gray-700 rounded-lg pl-3 pr-8 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                    <button @click.prevent="triggerManualDMSSearch()" class="absolute right-2 text-slate-400 hover:text-blue-500 text-xs">🔍</button>
                </div>
                <div class="max-h-36 overflow-y-auto border border-slate-100 dark:border-gray-800 rounded-lg p-2 bg-slate-50 dark:bg-gray-900/50 space-y-1 custom-scrollbar">
    
                    <div x-show="dmsLoading" class="text-center text-[11px] text-slate-400 py-4 animate-pulse">
                        Suche im DMS...
                    </div>
                    <div x-show="!dmsLoading">
                        <template x-for="corr in allCorrespondents" :key="corr.id">
                            <label class="flex items-center space-x-2 text-[11px] text-slate-700 dark:text-gray-300 cursor-pointer hover:bg-slate-100 dark:hover:bg-gray-800 p-1 rounded transition">
                                <input type="checkbox" 
                                    :value="corr.name" 
                                    x-model="selectedCorrespondents" 
                                    @change="contactState?.status === 'matched' ? saveContactDetail() : null" 
                                    class="rounded border-slate-300 dark:border-gray-700 text-blue-600 focus:ring-blue-500 bg-white dark:bg-gray-800">
                                <span x-text="corr.name" class="truncate"></span>
                                <span x-show="corr.score" class="text-[9px] text-slate-400 font-mono ml-auto" x-text="'(' + parseFloat(corr.score).toFixed(1) + ')'"></span>
                            </label>
                        </template>
                        <div x-show="allCorrespondents.length === 0" class="text-[11px] text-slate-400 italic p-1">
                            Keine Korrespondenten gefunden.
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1 pt-1">
                    <template x-for="mapping in selectedCorrespondents" :key="mapping">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300 border border-blue-200/40">
                            <span x-text="mapping"></span>
                        </span>
                    </template>
                </div>
            </div>
            <template x-if="contactState.status === 'unknown' || contactState.status === 'suggestion'">
                <button @click="saveContactDetail()" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-xl transition text-xs shadow-md shadow-blue-500/10 flex items-center justify-center space-x-2 mt-2">
                    <span>Speichern & Verknüpfen</span>
                </button>
            </template>
        </div>
    </div>
</div>