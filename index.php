<?php
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
error_log("DEBUG: Session: " . print_r($_SESSION, true));

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

$currentView = $_GET['view'] ?? 'mails';

// 📂 3. STATISCHES JS-MAPPING STATT TEUREM `file_exists()` FILING
$viewJsMapping = [
    'login'        => 'login.js',
    'mails'        => 'mails.js',
    'notes'        => 'notes.js',
    'paperless'    => 'paperless.js',
    'appointments' => 'appointments.js',
    'detail'       => 'detail.js',
    'setting'      => 'settings.js',
    'contacts'     => 'contacts.js'
];
$jsFile = $viewJsMapping[$currentView] ?? 'mail.js';


?>
<!DOCTYPE html>
<html lang="de" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Enterprise CRM Hub</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { gray: { 750: '#1f293d', 850: '#111827' } }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { -webkit-tap-highlight-color: transparent; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
        #calendar-container {
            display: flex;
            flex-direction: column;
            /* Verhindert, dass der Kalender den Container sprengt */
            height: 700px; 
        }

        #calendar {
            flex-grow: 1;
            overflow: hidden; /* Verhindert doppelte Scrollbars */
        }

        /* Falls du doch innerhalb scrollen willst, falls der Inhalt zu lang ist */
        .fc-scroller {
            overflow-y: auto !important;
        }
    </style>
    
    <script>
        window.CRM_API_URL = './api.php';
    </script>
    
    <!-- Core-Scripts permanent laden -->
    <script src="./public/assets/js/crm.js?v=<?= filemtime(__DIR__ . '/public/assets/js/' . $jsFile) ?>" defer></script>
    <script src="./public/assets/js/right_sidebar.js?v=<?= filemtime(__DIR__ . '/public/assets/js/' . $jsFile) ?>" defer></script>
    
    <!-- Dynamisches Skript ohne blockierenden I/O Festplatten-Check -->
    <script src="./public/assets/js/<?= $jsFile ?>?v=<?= filemtime(__DIR__ . '/public/assets/js/' . $jsFile) ?>" defer></script>

    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://d3js.org/d3.v7.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>

</head>
<body x-data="crmEngine()" 
      x-init="
        currentView = '<?php echo htmlspecialchars($currentView  ?? ''); ?>';
        selectedHash = '<?php echo htmlspecialchars($selectedHash ?? ''); ?>';
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
                
                // 2. SIDEBAR
                if (!@include __DIR__ . '/views/sidebar.php') {
                    throw new \Exception("Kritischer Fehler: Sidebar-View ('views/sidebar.php') konnte nicht geladen werden.");
                }

                // 3. MAIN DYNAMIC VIEW
                echo '<main class="flex-1 flex overflow-hidden relative min-h-0">';
                $viewPath = __DIR__ . "/views/{$currentView}_view.php";
                if (!@include $viewPath) {
                    throw new \Exception("Kritischer Fehler: Die View '{$currentView}' konnte unter dem Pfad '{$viewPath}' nicht geladen werden.");
                }
                echo '</main>';

                // 4. RIGHT SIDEBAR
                if (!@include __DIR__ . '/views/right_sidebar.php') {
                    throw new \Exception("Kritischer Fehler: Rechte Sidebar ('views/right_sidebar.php') konnte nicht geladen werden.");
                }

            echo '</div>';

            // 5. FOOTER (wird immer geladen)
            if (!@include __DIR__ . '/views/footer_nav.php') {
                throw new \Exception("Kritischer Fehler: Footer-Nav ('views/footer_nav.php') konnte nicht geladen werden.");
            }

        } catch (Throwable $e) {
            // Falls IRGENDWAS oben fehlschlägt oder eine Datei fehlt:
            // Wir leeren den bisherigen Buffer (damit kein kaputtes HTML steht) und werfen die Exception
            ob_end_clean();
            throw $e;
        }
        ?>

    </div>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('./sw.js')
                .then(registration => {
                    console.log('ServiceWorker registriert:', registration.scope);

                    // 🚀 DER RETTER FÜR DIE HOMESCREEN-APP:
                    // Prüft im Hintergrund sofort, ob sich die sw.js geändert hat
                    registration.update();

                    // Wenn ein neuer Service Worker gefunden wurde und wartet (skipWaiting)
                    registration.onupdatefound = () => {
                        const installingWorker = registration.installing;
                        if (installingWorker == null) return;
                        
                        installingWorker.onstatechange = () => {
                            if (installingWorker.state === 'installed') {
                                if (navigator.serviceWorker.controller) {
                                    // Ein neues Update ist da! Wir leeren den Cache und laden neu.
                                    console.log('🔄 Neue App-Version gefunden. Cache wird erneuert...');
                                    
                                    /*if ('caches' in window) {
                                        caches.keys().then(names => {
                                            for (let name of names) caches.delete(name);
                                        });
                                    }*/
                                    
                                    // Kurzer Delay, damit der Cache sicher leer ist, dann Reload
                                    setTimeout(() => {
                                        //window.location.reload(true);
                                    }, 2000);
                                }
                            }
                        };
                    };
                })
                .catch(err => console.error('ServiceWorker Registrierungsfehler:', err));
            });
        }
    </script>
</body>
</html>