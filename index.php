<?php

/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

date_default_timezone_set('Europe/Berlin');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");

/**
 * Kompass Hub - Core Controller
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
if ($isLoggedIn && !empty($_SESSION['tenant_id'])) {
    try {
        $dashboard = new \App\Dashboard($db);
        $tenantId = (int)$_SESSION['tenant_id'];
        
        // Prüfen, ob der Tenant existiert
        if (!$dashboard->isValidTenant($tenantId)) {
            error_log("SECURITY: Ungültige Tenant ID ($tenantId) in Session für User " . ($_SESSION['user_id'] ?? 'Unbekannt'));
            \App\Core\Auth::logout();
            header("Location: ./index.php?view=login&error=invalid_tenant");
            exit;
        }
    } catch (\Throwable $e) {
        // WICHTIG: Wenn die DB-Abfrage fehlschlägt, NIEMALS sofort ausloggen, 
        // sondern den Fehler nur loggen!
        error_log("Fehler bei Tenant-Validierung: " . $e->getMessage());
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
// Beispiel: Wert aus deiner Konfiguration, Datenbank oder Umgebungsvariable
$paperlessUrl = "http://192.168.0.3:8030"; 
?>

<!DOCTYPE html>
<html lang="de" class="h-full">

<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="digitaler_compass.svg">
    <link rel="apple-touch-icon" href="digitaler_compass.png">
    <link rel="apple-touch-icon" sizes="180x180" href="digitaler_compass_180.png">
    <link rel="preload" href="./public/assets/sprite.svg" as="image" type="image/svg+xml">

    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kompass Hub">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="<?= \App\Core\Auth::getCSRFToken(); ?>">
    <title>Kompass Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="./public/assets/compass.css?v=<?= filemtime(__DIR__ . '/public/assets/compass.css') ?>">
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

    
        
    <!-- Core-Scripts permanent laden -->
    <script src="./public/assets/js/crm.js?v=<?= filemtime(__DIR__ . '/public/assets/js/crm.js') ?>" defer></script>
    <script src="./public/assets/js/right_sidebar.js?v=<?= filemtime(__DIR__ . '/public/assets/js/right_sidebar.js') ?>" defer></script>
    


    <!-- Dynamisches Skript ohne blockierenden I/O Festplatten-Check -->
    <script src="./public/assets/js/<?= $jsFile ?>?v=<?= filemtime(__DIR__ . '/public/assets/js/'.$jsFile) ?>" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>

    <script>
        window.CRM_API_URL = './api.php';
        window.CRM_PAPERLESS = ''; // Initial leer

        document.addEventListener('DOMContentLoaded', async () => {
            try {
                let res = await apiCall('get_settings', {}, 'POST'); 
                console.log("Einstellungen vom Backend:", res);
                if (res?.success && res?.details && res.details?.settings) {
                    window.CRM_PAPERLESS = res.details.settings.PAPERLESS_URL || '';
                    console.log("Paperless URL geladen:", window.CRM_PAPERLESS);
                } else {
                    console.error("Fehler beim Laden der Einstellungen:", res);
                }
            } catch (err) {
                console.error("API-Aufruf fehlgeschlagen:", err);
            }
        });
    </script>
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
    <div style="display: none;">
        <?php echo file_get_contents(__DIR__ . '/public/assets/sprite.svg'); ?>
    </div>
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