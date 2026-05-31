<?php
// index.php
$jsVersion = file_exists('script/dashboard.js') ? filemtime('script/dashboard.js') : time();
?>
<!DOCTYPE html>
<html lang="de" class="h-full overflow-hidden select-none">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Mail Dashboard</title>
    
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <script src="script/dashboard.js?v=<?php echo $jsVersion; ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .sticky-bar {
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        .scroll-column {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        [x-cloak] { display: none !important; }

        #mail-preview-iframe {
            background-color: #ffffff;
        }
    </style>
</head>
<body 
    x-data="mailApp" 
    x-init="trackFoldersSilent(true); startBackgroundSync(); activeTab = 'paperless'; isOpen = false;"
    class="h-full bg-slate-50 text-slate-800 font-sans overflow-hidden antialiased flex flex-col justify-between"
>

    <!-- NAVBAR -->
    <nav class="bg-white px-4 py-3 border-b border-slate-200 flex items-center justify-between shrink-0 shadow-sm h-[56px]">
        <div class="flex items-center justify-between w-full">
            <a class="text-base font-bold tracking-tight text-slate-900 flex items-center gap-2" href="#">
                <span>📬</span> MailProcessor Dashboard
            </a>
            
            <div class="hidden lg:flex items-center text-xs text-slate-500 gap-6 mx-auto">
                <div>
                    <span class="text-slate-400 font-medium">Home Assistant:</span> 
                    <span x-html="haInfoHtml" class="text-slate-700"></span>
                </div>
                <div>
                    <span class="text-slate-400 font-medium">Server Status:</span> 
                    <span x-html="serverInfoHtml" class="text-slate-700"></span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button 
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 transition flex items-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-sm" 
                    @click="triggerNavbarSync($event)"
                    :disabled="loading"
                >
                    <span x-show="!loading">🔄</span>
                    <span x-show="loading" class="animate-spin inline-block w-3 h-3 border-2 border-current border-t-transparent rounded-full" role="status"></span>
                    <span>Server Sync</span>
                </button>

                <button 
                    class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold px-3 py-1.5 rounded-lg border border-slate-300 transition flex items-center gap-1.5 disabled:opacity-50 cursor-pointer shadow-sm" 
                    @click="syncPaperless()"
                    :disabled="paperlessLoading"
                >
                    <span x-show="!paperlessLoading">🔄</span>
                    <span x-show="paperlessLoading" class="animate-spin inline-block w-3 h-3 border-2 border-current border-t-transparent rounded-full" role="status"></span>
                    <span>Paperless Sync</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- MAIN INTERFACE -->
    <main class="flex-1 flex overflow-hidden relative h-[calc(100vh-56px-50px)] lg:h-[calc(100vh-56px)] bg-slate-50">
        
        <!-- SPALTE 1: E-MAIL HISTORIE & ORDNER -->
        <section class="scroll-column w-full lg:w-[360px] border-r border-slate-200 bg-slate-100/60 flex flex-col overflow-hidden shrink-0">
            
            <div class="sticky-bar bg-slate-50/95 backdrop-blur-md p-4 border-b border-slate-200/60 space-y-3 shrink-0">
                <h5 class="text-sm font-bold text-slate-500 uppercase tracking-wider px-1" x-text="currentFolder === 'INBOX' ? 'Posteingang' : currentFolder"></h5>
                
                <div class="flex bg-slate-200/50 p-1 rounded-xl border border-slate-200 shadow-inner" role="group">
                    <button type="button" class="flex-1 py-1.5 text-xs font-medium rounded-lg transition cursor-pointer" 
                            :class="currentFolder === 'INBOX' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            @click="switchFolder('INBOX')">
                        Inbox
                    </button>
                    <button type="button" class="flex-1 py-1.5 text-xs font-medium rounded-lg transition cursor-pointer" 
                            :class="currentFolder === 'DMS' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            @click="switchFolder('DMS')">
                        DMS
                    </button>
                    <button type="button" class="flex-1 py-1.5 text-xs font-medium rounded-lg transition cursor-pointer" 
                            :class="currentFolder === 'DMSDone' ? 'bg-white text-slate-900 shadow-sm font-semibold' : 'text-slate-500 hover:text-slate-800'"
                            @click="switchFolder('DMSDone')">
                        Done
                    </button>
                </div>

                <div class="relative">
                    <div class="relative flex items-center">
                        <span class="absolute left-3 text-slate-400 text-xs"><i class="bi bi-search"></i></span>
                        <input 
                            type="text" 
                            class="w-full pl-9 pr-8 py-2 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500 transition" 
                            placeholder="Mails in diesem Ordner suchen..." 
                            x-model="mailSearchQuery"
                            @keyup.escape="mailSearchQuery = ''"
                        >
                        <button 
                            class="absolute right-2 text-slate-400 hover:text-slate-600 p-1 text-xs" 
                            type="button" 
                            x-show="mailSearchQuery.trim().length > 0" 
                            @click="mailSearchQuery = ''"
                            x-cloak
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    
                    <div class="flex justify-between items-center px-1 mt-1.5 text-[11px]" x-show="mailSearchQuery.trim().length > 0" x-cloak>
                        <span class="text-slate-400" x-text="`Gefunden: ${filteredMails.length} von ${mails.length}`"></span>
                        <a href="#" @click.prevent="mailSearchQuery = ''" class="text-blue-500 hover:underline">Leeren</a>
                    </div>
                </div>
            </div>

            <!-- E-Mail Liste -->
            <div class="scroll-column flex-1 p-3 space-y-2.5" id="mail-list">
                <template x-for="mail in filteredMails" :key="mail.hash">
                    <div class="group relative border rounded-xl p-3 bg-white transition flex flex-col gap-2.5 overflow-hidden shadow-sm"
                        x-init="$nextTick(() => { if (typeof initSwipe === 'function') initSwipe($el, mail); })"
                        :class="{
                            'border-blue-500 ring-1 ring-blue-500/20 bg-blue-50/20': selectedHash === mail.hash,
                            'border-emerald-200 bg-emerald-50/40': mail.paperless_id,
                            'border-slate-200 hover:border-slate-300 hover:shadow': selectedHash !== mail.hash && !mail.paperless_id
                        }">
                        
                        <div class="absolute inset-0 z-10 cursor-pointer" 
                            @click="selectedHash = mail.hash; viewMail(mail); loadPaperlessTreeByMail(mail.hash); showMobilePreview = true; activeTab = 'paperless'; isOpen = false;"></div>

                        <div class="relative z-20 pointer-events-none flex flex-col gap-1.5 w-full">
                            <div class="flex w-full justify-between items-start gap-3">
                                <strong class="text-xs font-semibold text-slate-900 truncate max-w-[75%]" x-text="mail.subject"></strong>
                                <small class="text-[10px] font-mono text-slate-500 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded whitespace-nowrap" x-text="mail.date"></small>
                            </div>
                            
                            <div class="flex justify-between items-center gap-2 mt-1">
                                <p class="text-[11px] text-slate-500 truncate max-w-[55%]" x-text="mail.sender"></p>
                                
                                <div class="flex items-center gap-1 shrink-0 pointer-events-auto text-xs">
                                    
                                    <template x-if="currentFolder !== 'INBOX'">
                                        <div class="flex gap-1">
                                            <button class="w-8 h-8 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition shadow-sm flex items-center justify-center cursor-pointer" 
                                                    title="In Paperless nach Match suchen" @click.stop="showInPaperless(mail.hash)">🔍</button>
                                            
                                            <button class="w-8 h-8 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition shadow-sm flex items-center justify-center cursor-pointer" 
                                                    title="Löschen" @click.stop="deleteMail(mail)">🗑️</button>
                                        </div>
                                    </template>

                                    <button class="h-8 border font-bold text-[10px] px-2.5 rounded-lg transition uppercase tracking-wider shadow-sm cursor-pointer" 
                                            x-show="currentFolder !== 'DMSDone'"
                                            :class="(currentFolder === 'DMS') && mail.paperless_id ? 'bg-emerald-600 text-white border-emerald-500' : 'bg-white hover:bg-slate-50 text-blue-600 border-slate-200'" 
                                            @click.stop="(currentFolder === 'DMS') && mail.paperless_id ? openPaperless(mail.paperless_id) : moveToDMS(mail, 'Recht')">
                                        DMS
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2 space-y-1 pointer-events-auto" 
                                x-show="mail.paperless_id && (currentFolder === 'DMS' || currentFolder === 'DMSDone')" 
                                x-cloak>
                                
                                <button class="w-full text-center bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-600 hover:text-white text-[11px] font-medium py-1 rounded-md transition cursor-pointer shadow-sm" 
                                        @click.stop="openPaperless(mail.paperless_id, true)">
                                    👁️ Im Paperless ansehen
                                </button>
                                
                                <button class="w-full text-center bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-600 hover:text-white text-[11px] font-medium py-1 rounded-md transition cursor-pointer shadow-sm" 
                                        @click.stop="loadPaperlessTreeByMail(mail.hash)">
                                    🔄 Paperless Struktur
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div class="text-center py-4 flex items-center justify-center gap-2" :class="loading ? '' : 'hidden'">
                    <div class="animate-spin inline-block w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full"></div>
                    <span class="text-xs text-slate-400">Mails werden geladen...</span>
                </div>
            </div>
        </section>

        <!-- RECHTER BEREICH: INHALT & DMS ZUORDNUNG -->
        <section class="fixed lg:static inset-0 w-full lg:w-auto lg:flex-1 bg-slate-50 z-[1050] lg:z-10 flex flex-col overflow-hidden transition-transform duration-300" 
                 :class="showMobilePreview ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'">
            
            <!-- Mobile Header Navigation Tabs -->
            <div class="p-3 lg:hidden border-b border-slate-200 bg-white sticky top-0 z-50 shrink-0 shadow-sm space-y-2.5">
                <button class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold py-2 rounded-xl transition border border-slate-300 cursor-pointer flex items-center justify-center gap-2" @click="showMobilePreview = false">
                    ⬅ Zurück zur Posteingangsliste
                </button>
                
                <div class="flex bg-slate-100 p-1 rounded-xl border border-slate-200 shadow-inner" x-show="currentMail">
                    <button type="button" class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition cursor-pointer"
                            :class="activeTab === 'mail' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                            @click="activeTab = 'mail'">
                        📧 E-Mail Ansicht
                    </button>
                    <button type="button" class="flex-1 py-1.5 text-xs font-semibold rounded-lg transition cursor-pointer flex items-center justify-center gap-1.5"
                            :class="activeTab === 'paperless' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                            @click="activeTab = 'paperless'">
                        📂 Paperless View
                    </button>
                </div>
            </div>

            <div class="flex flex-col lg:flex-row h-full w-full overflow-hidden">
                
                <!-- SPALTE 2: IFRAME E-MAIL VIEW -->
                <div class="flex-1 p-4 flex flex-col min-w-0 h-full" 
                    :class="{'hidden lg:flex': activeTab !== 'mail', 'flex': activeTab === 'mail'}">
                    
                    <div x-show="currentHash" class="h-full flex flex-col" x-cloak>
                        <h3 class="text-md lg:text-lg font-bold text-slate-900 leading-snug break-words" x-text="currentMailSubject"></h3>
                        <div class="text-xs text-slate-500 mt-1 mb-3">
                            Absender: <span class="font-semibold text-slate-700" x-text="currentMailSender"></span> | 
                            Datum: <span class="font-mono text-slate-600" x-text="currentMailDate"></span>
                        </div>
                        
                        <div class="flex-1 border border-slate-200 bg-white rounded-xl overflow-hidden shadow-sm relative min-h-[350px] lg:min-h-0 flex items-center justify-center">
                            
                            <div id="mail-preview-spinner" class="absolute flex flex-col items-center justify-center gap-3 hidden">
                                <svg class="animate-spin h-8 w-8 text-slate-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs font-medium text-slate-500 animate-pulse">Lade E-Mail via IMAP...</span>
                            </div>

                            <iframe 
                                id="mail-preview-iframe"
                                class="w-full h-full border-none hidden"
                                style="min-height: 500px;">
                            </iframe>
                        </div>
                    </div>

                    <div class="h-full flex flex-col items-center justify-center text-slate-400 py-12" x-show="!currentHash">
                        <div class="text-4xl mb-3">📥</div>
                        <h5 class="text-sm font-semibold text-slate-400">Keine E-Mail ausgewählt</h5>
                    </div>
                </div>

                <!-- SPALTE 3: PAPERLESS COUPLING & SYNC LOGS -->
                <div class="scroll-column w-full lg:w-[500px] p-4 border-t lg:border-t-0 lg:border-l border-slate-200 bg-slate-100/40 shrink-0 pb-24 lg:pb-4 flex flex-col justify-between overflow-y-auto gap-4"
                     :class="{'hidden lg:flex': activeTab !== 'paperless', 'flex': activeTab === 'paperless'}">
                    
                    <div class="space-y-4 flex-1">
                        <!-- Status Link Header: Wird NUR angezeigt, wenn eine ID existiert -->
                        <template x-if="currentMail && currentMail.paperless_id">
                            <div class="flex justify-between items-center bg-emerald-50 border border-emerald-200 rounded-lg p-2 shadow-sm animate-fade-in">
                                <span class="text-xs font-medium text-emerald-800">
                                    ✅ Verknüpft mit Paperless-ID: <strong x-text="currentMail.paperless_id"></strong>
                                </span>
                                <button @click="isOpen = !isOpen" 
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded text-[10px] font-semibold transition cursor-pointer">
                                    <span x-text="isOpen ? 'Verlauf anzeigen 📁' : 'Suche einblenden 🔍'"></span>
                                </button>
                            </div>
                        </template>
                        
                        <!-- ANZEIGE-LOGIK 1: SMARTE ZUORDNUNG (Suche) -->
                        <!-- Zeigt sich, wenn KEINE ID vorhanden ist ODER wenn eine da ist, aber manuell die Suche (isOpen) erzwungen wurde -->
                        <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm space-y-4" 
                             x-show="!currentMail?.paperless_id || isOpen" x-transition>
                            <h5 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Smarte Zuordnung</h5>
                            
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-slate-400 text-xs">🔍</span>
                                <input type="text" id="manualSearchQuery" class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500 transition" placeholder="Manuelle Suche..." x-model="manualSearchQuery">
                            </div>

                            <div class="space-y-3">
                                <div class="text-center text-slate-400 py-3 text-xs flex items-center justify-center gap-2" x-show="paperlessLoading">
                                    <div class="animate-spin inline-block w-3 h-3 border-2 border-blue-500 border-t-transparent rounded-full"></div>Suche im DMS...
                                </div>
                                
                                <template x-if="paperlessMatches.length > 0 && !paperlessLoading">
                                    <h6 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider" x-text="'Smarte Treffer (' + paperlessMatches.length + '):'"></h6>
                                </template>

                                <div class="scroll-column space-y-2 max-h-[220px] pr-1">
                                    <template x-for="doc in paperlessMatches" :key="doc.id">
                                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 text-xs shadow-sm border-l-4"                             
                                            :class="{
                                                'border-l-emerald-500': doc.originscore >= 0.85,
                                                'border-l-amber-500': doc.originscore < 0.85 && doc.originscore >= 0.40,
                                                'border-l-rose-500': doc.originscore < 0.40
                                            }">
                                            <div class="font-semibold text-slate-800 truncate mb-1" x-text="'📄 ' + doc.title" :title="doc.title"></div>
                                            <div class="text-[10px] text-slate-400 font-mono mb-2">
                                                Erstellt: <strong class="text-slate-600" x-text="doc.created"></strong>
                                            </div>
                                            <div class="flex justify-between items-center gap-2 pt-1 border-t border-slate-200">
                                                <a :href="doc.link" target="_blank" class="text-[11px] text-blue-600 hover:underline">👁️ Öffnen</a>
                                                <button class="bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-bold px-2 py-1 rounded-md transition shadow-sm cursor-pointer" @click="linkToPaperless(doc.id)">🔗 Matchen</button>
                                            </div>
                                            <span class="text-[9px] text-slate-400 font-mono" x-text="'Score: ' + doc.originscore"></span>
                                        </div>
                                    </template>
                                </div>

                                <template x-if="paperlessMatches.length === 0 && !paperlessLoading && currentMailSubject">
                                    <div class="text-center p-4 text-slate-400 border border-dashed border-slate-200 rounded-xl bg-slate-50 text-xs">
                                        Keine Bezüge gefunden.
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- ANZEIGE-LOGIK 2: HISTORISCHER BAUM (Tree) -->
                        <!-- Zeigt sich nur, wenn eine ID vorhanden ist UND der Toggle nicht auf Suche geschaltet wurde -->
                        <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm space-y-4" 
                             x-show="currentMail?.paperless_id && !isOpen" x-transition>
                            <div class="pt-1">
                                <div x-show="paperlessTree && Object.keys(paperlessTree).length > 0">
                                    <h6 class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5">📁 Verlauf des Absenders:</h6>
                                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-2.5 max-h-[280px] overflow-y-auto space-y-3">
                                        
                                        <template x-for="(months, year) in paperlessTree" :key="year">
                                            <div class="space-y-1.5">
                                                <div class="font-mono text-xs font-bold text-slate-700 border-b border-slate-200 pb-1" x-text="'📅 Jahr ' + year"></div>
                                                
                                                <template x-for="(docs, month) in months" :key="month">
                                                    <div class="pl-2 space-y-1">
                                                        <div class="text-[11px] text-slate-600 font-medium" x-text="'🔹 ' + month"></div>
                                                        
                                                        <ul class="space-y-1.5 pl-2.5">
                                                            <template x-for="(doc, index) in docs" :key="doc.id || index">
                                                                <li class="flex items-center justify-between gap-2 text-[11px] border-b border-slate-100 pb-1 group/item">
                                                                    <a :href="doc.link" target="_blank" class="text-blue-600 hover:underline truncate max-w-[70%]" x-text="'📄 ' + (doc.title || 'Unbenannt')" :title="doc.title"></a>
                                                                    
                                                                    <button 
                                                                        class="bg-slate-200 hover:bg-emerald-600 hover:text-white text-slate-700 text-[9px] font-bold px-1.5 py-0.5 rounded transition shadow-sm cursor-pointer opacity-70 group-hover/item:opacity-100" 
                                                                        @click.stop="linkToPaperless(doc.id)"
                                                                    >
                                                                        🔗 Matchen
                                                                    </button>
                                                                </li>
                                                            </template>
                                                        </ul>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <div x-show="paperlessStatusText" class="text-center py-4 bg-slate-50 border border-slate-200 border-dashed rounded-xl">
                                    <p class="text-xs text-slate-500 font-medium" x-text="paperlessStatusText"></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- DMS SYNC PROTOKOLL -->
                    <div class="bg-slate-900 text-slate-100 font-mono text-[11px] p-4 rounded-xl max-h-[220px] overflow-y-auto space-y-1.5 shadow-inner shrink-0 mt-auto">
                        <h5 class="text-slate-400 border-b border-slate-700 pb-2 uppercase tracking-wider text-[10px] font-bold">DMS Sync Protokoll</h5>
                        
                        <template x-for="(logLine, index) in syncLogs" :key="index">
                            <div x-html="logLine" class="leading-relaxed break-words"></div>
                        </template>

                        <template x-if="syncLogs.length === 0 && !paperlessLoading">
                            <div class="text-slate-500 italic text-center py-2">Noch kein Sync-Protokoll verfügbar.</div>
                        </template>
                    </div>

                </div>
            </div>
        </section>
    </main>

    <!-- MOBILE FOOTER STATUS BAR -->
    <footer class="mobile-footer fixed-bottom h-[50px] bg-white border-t border-slate-200 flex lg:hidden items-center justify-around px-4 text-slate-500 text-xs shadow-md shrink-0">
        <div class="truncate max-w-[45%] flex flex-col">
            <span class="text-slate-400 font-bold uppercase text-[9px] tracking-wider">Home Assistant</span>
            <span x-html="haInfoHtml" class="text-slate-700 truncate"></span>
        </div>
        <div class="border-l border-slate-200 h-6"></div>
        <div class="truncate max-w-[45%] flex flex-col">
            <span class="text-slate-400 font-bold uppercase text-[9px] tracking-wider">Server Status</span>
            <span x-html="serverInfoHtml" class="text-slate-700 truncate"></span>
        </div>
    </footer>

</body>
</html>