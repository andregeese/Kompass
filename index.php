<?php

/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

date_default_timezone_set('Europe/Berlin');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

/**
 * Enterprise CRM Hub - Core Controller
 * Lädt Composer-Autoloading und steuert die modulare View-Injektion.
 */
define('ROOT_PATH', __DIR__);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// 2. Wohin schreibt PHP interne Fehler?
$logFile = __DIR__ . "/php.log";
ini_set('log_errors', 1);
ini_set('error_log', $logFile); // Hier muss 'error_log' stehen, nicht deine Klasse
// 3. Sicherstellen, dass die Datei beschreibbar ist
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
set_error_handler(function ($severity, $message, $file, $line) {
    // Falls der Fehler durch ein '@' unterdrückt wurde, ignorieren
    if (!(error_reporting() & $severity)) {
        return;
    }
    // Verwandelt JEDE Warnung (z.B. Undefined array key) in eine Exception!
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function () {
    $error = error_get_last();

    // Prüfen, ob das Skript wegen eines fatalen Fehlers abgebrochen ist
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        // Da wir nicht wissen, ob schon Header gesendet wurden, löschen wir den Buffer
        if (ob_get_length()) ob_end_clean();

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'status'  => 'fatal_error',
            'error'   => 'Ein fataler Systemfehler ist aufgetreten (Shutdown-Handler).',
            'details' => [
                'message' => $error['message'],
                'file'    => $error['file'],
                'line'    => $error['line']
            ]
        ]);
        exit;
    }
});
$db = new \App\Core\Database();
\App\Core\ErrorLogs::setDatabase($db);
\App\Core\Auth::init();
$view = $_GET['view'] ?? 'dashboard';
// 1. Logout-Logik (wie von dir bereitgestellt)
if ($view === 'login' && isset($_GET['logout'])) {
    \App\Core\Auth::logout(); // Nutze die Methode aus deiner Auth.php
    header("Location: ./index.php?view=login");
    exit;
}
// 2. Authentifizierungs-Check
$isLoggedIn = \App\Core\Auth::check();
error_log("DEBUG: Is logged in: " . ($isLoggedIn ? 'JA' : 'NEIN'));
#error_log("DEBUG: Session: " . print_r($_SESSION, true));
// Wenn nicht eingeloggt und nicht auf der Login-Seite -> Weiterleitung
if (!$isLoggedIn && $view !== 'login') {
    header("Location: ./index.php?view=login");
    exit;
}
// 3. Optional: Tenant-Validierung (Zusätzliche Sicherheit)
if ($isLoggedIn && isset($_SESSION['tenant_id'])) {
    $dashboard = new \App\Dashboard($db);
    $valid = $dashboard->isValidTenant((int)$_SESSION['tenant_id']);
    error_log("DEBUG: Tenant valid: " . ($valid ? 'JA' : 'NEIN'));
    if (!$dashboard->isValidTenant((int)$_SESSION['tenant_id'])) {
        \App\Core\Auth::logout();
        header("Location: ./index.php?view=login&error=invalid_tenant");
        exit;
    }
}
$currentView = $_GET['view'] ?? 'start';
// 📂 3. STATISCHES JS-MAPPING STATT TEUREM `file_exists()` FILING
$viewJsMapping = [
    'login'        => 'login.js',
    'mails'        => 'mails.js',
    'notes'        => 'notes.js',
    'paperless'    => 'paperless.js',
    'appointments' => 'appointments.js',
    'detail'       => 'detail.js',
    'setting'      => 'settings.js',
    'contacts'     => 'contacts.js',
    'start'        => 'start.js'
];
$jsFile = $viewJsMapping[$currentView] ?? 'start.js';
?>

<!DOCTYPE html>
<html lang="de" class="h-full">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="digitaler_compass.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="<?= \App\Core\Auth::getCSRFToken(); ?>">
    <title>Enterprise CRM Hub</title>
    <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
        <defs>
            <symbol id="icon-mail" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </symbol>
            <symbol id="icon-mail-send" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
            </symbol>
            <symbol id="icon-appointment" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </symbol>
            <symbol id="icon-document" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </symbol>
            <symbol id="icon-note" viewBox="0 0 24 24">
            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </symbol>
        </defs>
        </svg>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gray: {
                            750: '#1f293d',
                            850: '#111827'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] {
            display: none !important;
        }

        body {
            -webkit-tap-highlight-color: transparent;
        }

        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }

        /* Container-Struktur */
        #calendar-container {
            display: flex;
            flex-direction: column;
            height: 700px;
        }

        #calendar {
            flex-grow: 1;
            overflow: hidden;
            /* Haupt-Container bleibt fix */
        }

        /* Scroll-Verhalten & Grid-Feinabstimmung */
        .fc-scroller {
            overflow-y: auto !important;
        }

        .fc .fc-daygrid-body {
            background-color: transparent !important;
        }

        .fc-theme-standard td,
        .fc-theme-standard th {
            border-color: #f1f5f9 !important;
            /* Dezente Linien */
        }

        /* Header & Toolbar */
        .fc .fc-header-toolbar {
            margin-bottom: 1rem !important;
        }

        .fc .fc-button-primary {
            background-color: transparent !important;
            border: 1px solid #e2e8f0 !important;
            color: #475569 !important;
            padding: 4px 12px !important;
            font-size: 0.8rem !important;
            border-radius: 8px !important;
            transition: all 0.2s ease;
        }

        .fc .fc-button-primary:hover {
            background-color: #f8fafc !important;
            color: #1e293b !important;
        }

        /* Zellen & Datum */
        .fc .fc-daygrid-day-top {
            flex-direction: row !important;
            padding: 4px !important;
        }

        .fc .fc-daygrid-day-number {
            font-size: 0.75rem !important;
            font-weight: 500;
            color: #94a3b8 !important;
            /* Slate-400 */
            padding: 4px !important;
            text-decoration: none !important;
        }
    </style>

    <script>
        window.CRM_API_URL = './api.php';
        window.CRM_PAPERLESS = 'http://192.168.0.72:8030'
    </script>

    <!-- Core-Scripts permanent laden -->
    <script src="./public/assets/js/crm.js?v=<?= filemtime(__DIR__ . '/public/assets/js/crm.js') ?>" defer></script>
    <script src="./public/assets/js/right_sidebar.js?v=<?= filemtime(__DIR__ . '/public/assets/js/right_sidebar.js') ?>" defer></script>
    

    <!-- Dynamisches Skript ohne blockierenden I/O Festplatten-Check -->
    <script src="./public/assets/js/<?= $jsFile ?>?v=<?= filemtime(__DIR__ . '/public/assets/js/'.$jsFile) ?>" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
</head>

<body x-data="crmEngine()"
    x-init="
        currentView = '<?php echo htmlspecialchars($currentView  ?? ''); ?>';
        selectedHash = '<?php echo htmlspecialchars($selectedHash ?? ''); ?>';"
    
    @navigate.window="
        const view = $event.detail.view;
        const id = $event.detail.id;
        if (view) {
            window.location.href = `./index.php?view=${view}&id=${id}`;
        }
    "
    class="h-full bg-slate-50 dark:bg-gray-900 text-slate-900 dark:text-gray-100 antialiased selection:bg-blue-500 selection:text-white pb-safe">
    <div x-show="!isOnline" x-cloak class="bg-amber-500 text-white text-xs font-semibold px-4 py-2 flex items-center justify-between sticky top-0 z-50 shadow-md">
        <div class="flex items-center space-x-2">
            <span>⚠️ Funkloch aktiv – App läuft im ausfallsicheren Offline-Cache Modus.</span>
        </div>
        <span class="bg-amber-600 px-2 py-0.5 rounded text-[10px]">LOCAL STORAGE</span>
    </div>
    <div class="h-full flex flex-col relative overflow-hidden">

        <?php
        try {
            // 1. HEADER (wird immer geladen)
            echo '<div class="shrink-0 z-50">';
            if (!@include __DIR__ . '/views/header.php') {
                throw new \Exception("Kritischer Fehler: Header-View ('views/header.php') konnte nicht geladen werden.");
            }
            echo '</div>';
            echo '<div class="flex-1 flex overflow-hidden relative">';

            // 2. DIE NEUE UNIFIZIERTE NAVIGATION (Ersetzt die alte sidebar.php)
            if (!@include __DIR__ . '/views/navigation_bar.php') {
                throw new \Exception("Kritischer Fehler: Navigation ('views/navigation_bar.php') konnte nicht geladen werden.");
            }
            // 3. MAIN DYNAMIC VIEW
            echo '<main class="flex-1 flex overflow-hidden relative min-h-0 pb-16 md:pb-0">';
            $viewPath = __DIR__ . "/views/{$currentView}_view.php";
            if (!@include $viewPath) {
                die("Kritischer Systemfehler: View-Datei nicht gefunden.");
                //throw new \Exception("Kritischer Fehler: Die View '{$currentView}' konnte unter dem Pfad '{$viewPath}' nicht geladen werden.");
            }
            echo '</main>';
            // 4. RIGHT SIDEBAR
            if (!@include __DIR__ . '/views/right_sidebar.php') {
                throw new \Exception("Kritischer Fehler: Rechte Sidebar ('views/right_sidebar.php') konnte nicht geladen werden.");
            }
            echo '</div>';
        } catch (Throwable $e) {
            // Falls IRGENDWAS oben fehlschlägt oder eine Datei fehlt:
            // Wir leeren den bisherigen Buffer (damit kein kaputtes HTML steht) und werfen die Exception
            ob_end_clean();
            throw $e;
        }
        ?>
    </div>
    <div x-data="{ 
                show: false, 
                isDetail: false,
                message: '', 
                item: {},
                buttons: [], // Neu: Hier landen die dynamischen Buttons
                
                openConfirm(msg, buttonsOrCallback) { 
                    this.isDetail = false;
                    this.message = msg; 
                    
                    // Abwärtskompatibilität: Wenn es ein alter Callback ist, baue Standard-Buttons
                    if (typeof buttonsOrCallback === 'function') {
                        this.buttons = [
                            { label: 'Abbrechen', class: 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200', onClick: () => {} },
                            { label: 'Bestätigen', class: 'bg-red-600 hover:bg-red-700 text-white', onClick: buttonsOrCallback }
                        ];
                    } else if (Array.isArray(buttonsOrCallback)) {
                        // Wenn es das neue Array ist, direkt zuweisen
                        this.buttons = buttonsOrCallback;
                    }
                    
                    this.show = true; 
                },
                openDetail(data) {
                    this.isDetail = true;
                    this.item = data;
                    this.show = true;
                }
            }"
            @open-item-modal.window="openDetail($event.detail)"
            @confirm-modal.window="openConfirm($event.detail.message, $event.detail.buttons)"
            x-show="show"
            x-cloak
            class="fixed inset-0 z-[999999] flex items-center justify-center px-4"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0">

            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="show = false"></div>

            <div class="relative bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-sm p-6 transform transition-all"
                x-show="show"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95">

                <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Bestätigung</h3>
                <p class="text-slate-600 dark:text-slate-300 text-sm mb-6" x-text="message"></p>

                <div class="flex flex-wrap gap-3">
                    <template x-for="(btn, index) in buttons" :key="index">
                        <button @click="if(btn.onClick) btn.onClick(); show = false"
                            :class="btn.class || 'bg-slate-100 dark:bg-slate-700 hover:bg-slate-200'"
                            class="flex-1 min-w-[100px] px-4 py-2.5 rounded-xl font-medium transition-colors text-sm"
                            x-text="btn.label">
                        </button>
                    </template>
                </div>
            </div>
        </div>
</body>

</html>