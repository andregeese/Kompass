<?php
// api.php
set_time_limit(30);
ob_start();

session_start();
$tenantId = $_SESSION['tenant_id'] ?? null;


//Debug?
$isDebugMode = isset($_GET['debug']) && $_GET['debug'] === 'true';

//throw new \Exception ("ende");

$action = $_REQUEST['action'] ?? ''; 

ini_set('display_errors', 1);         
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);               

$logFile= __DIR__."/php.log";
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

// Sicherstellen, dass die Datei existiert und beschreibbar ist
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

require_once __DIR__ . '/vendor/autoload.php'; 
require_once __DIR__ . '/config.php'; 


set_error_handler([\App\ErrorLogs::class, 'handleNotification']);
//set_exception_handler([\App\ErrorLogs::class, 'handleException']);
// ZENTRALER ERROR-HANDLER
set_exception_handler(function ($e) {
    // 1. Logge den Fehler für dich
    error_log("Globaler API Fehler: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    // 2. Sende immer ein korrektes JSON an das Frontend
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ein Serverfehler ist aufgetreten: ' . $e->getMessage()
    ]);
    exit;
});

register_shutdown_function([\App\ErrorLogs::class, 'handleShutdown']);

error_log("=== API aufgerufen am " . date('Y-m-d H:i:s') . " ===");

// --- Globale Notfall-Absicherung für Fatal Errors ---
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR)) {
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'details' => [],
            'error'   => "Kritischer Systemfehler: " . $error['message'] . " in Zeile " . $error['line']
        ]);
        exit;
    }
});

header('Content-Type: application/json');

//if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    // Alarm! Die Session wurde vermutlich auf ein anderes Gerät kopiert.
//    session_destroy();
//    throw new \Exception("Session-Sicherheitsfehler. Bitte neu einloggen.");
//}

$response = [
    'success' => false,
    'details' => [],
    'error'   => null
];

try {
    if ($isDebugMode) {
        // 🚀 HIER DIE RETTUNG: Schreibe es direkt in die Session!
        if (!isset($_SESSION['tenant_id'])) {
            $_SESSION['tenant_id'] = '5';
        }
        $tenantId = $_SESSION['tenant_id']; 
        error_log("=== API-DEBUG-MODE: Authentifizierung umgangen ===");
    }

    // Session-Schutz (Ausnahmen: Login, Registrierung, FaceID)
    if (!$tenantId && !in_array($action, ['login', 'register', 'verify_faceid'])) {
        header('Content-Type: application/json', true, 403);
        echo json_encode([
            'success' => false,
            'error'   => 'Nicht autorisierter API-Zugriff. Bitte loggen Sie sich neu ein.',
            'debug_received_action' => $action
        ]);
        exit;
    }

    $masterKey = getenv('DB_CRYPTO_KEY') ?: 'DEIN_FALLBACK_MASTER_KEY_NUR_FUER_DEV';
    
    // Kern-Instanzen vorbereiten
    $dashboard = new \App\Dashboard();       

// --- ROUTER MAP ---
$routes = [
    // Auth
    'login'                         => [App\Api\AuthController::class, 'login'],
    'register'                      => [App\Api\AuthController::class, 'register'],
    
    // Settings
    'get_settings'                  => [App\Api\SettingsController::class, 'get'],
    'save_settings'                 => [App\Api\SettingsController::class, 'save'],
    
    // Mails
    'list_mails'                    => [App\Api\MailController::class, 'listMails'],
    'view'                          => [App\Api\MailController::class, 'viewMail'],
    'delete'                        => [App\Api\MailController::class, 'deleteMail'],
    'dms'                           => [App\Api\MailController::class, 'moveToDms'],
    'pdf'                           => [App\Api\MailController::class, 'handlePdf'],
    'sync'                          => [App\Api\MailController::class, 'syncICloud'],
    'track_folders'                 => [App\Api\MailController::class, 'trackFolders'],
    'stream_mail_html'              => [App\Api\MailController::class, 'streamHtml'], // Sonderfall: Rendert HTML
    'search_mail'                   => [App\Api\MailController::class, 'searchMail'],

    //Sidebar
    
    
    // Paperless
    'syncPaperless'                 => [App\Api\PaperlessController::class, 'sync'],
    'paperless_search'              => [App\Api\PaperlessController::class, 'search'],
    'paperless_match'               => [App\Api\PaperlessController::class, 'match'],
    //'get_paperless_correspondents'  => [App\Api\PaperlessController::class, 'getCorrespondents'], //mail als 
    'paperless_mail_tree'           => [App\Api\PaperlessController::class, 'getMailTree'],
    'paperless_tree'                => [App\Api\PaperlessController::class, 'getTree'],
    'link_mail_to_paperless'        => [App\Api\PaperlessController::class, 'linkMail'],
    'get_correspondet'              => [App\Api\PaperlessController::class, 'getCorrespondentById'],
    'get_correspondets'             => [App\Api\PaperlessController::class, 'getCorrespondents'],
    'link_contact_item'             => [App\Api\PaperlessController::class, 'linkContact'],
    
    // Contacts
    'search_contact'                => [App\Api\ContactController::class, 'getContactByHashMail'],
    'insert_contact'                => [App\Api\ContactController::class, 'insert'],
    'save_contact_manual'           => [App\Api\ContactController::class, 'saveSmallContact'],
    'save_contact'                  => [App\Api\ContactController::class, 'saveContact'],
    'get_contact_context'           => [App\Api\ContactController::class, 'getContactContext'],
    'auto_link_contact'             => [App\Api\ContactController::class, 'autoLinkContact'],
    'get_contact_timeline'          => [App\Api\ContactController::class, 'getTimeLineContact'],
    'list_contacts'                 => [App\Api\ContactController::class, 'getContacts'],
    'get_emails_by_contact'         => [App\Api\ContactController::class, 'getEmailsByContact'],    
    'get_graph'                     => [App\Api\ContactController::class, 'getContacts'],    
    
    // System
    'system_status'                 => [App\Api\SystemController::class, 'status'],
    'repair_contacts'               => [App\Api\SystemController::class, 'repairTenantContacts'],
    'imap_mails'                    => [App\Api\SystemController::class, 'syncFolder'],
    'test_imap'                     => [App\Api\SystemController::class, 'testImap'],

];

    if (!array_key_exists($action, $routes)) {
        throw new \Exception("Aktion '$action' ist unbekannt.");
    }


    // Controller instanziieren und ausführen
    $controllerClass = $routes[$action][0];
    $method = $routes[$action][1];
    
    $controller = new $controllerClass($dashboard);
    
    
    //$controller = new $controllerClass($dashboard);    
    //error_log(print_r($controller, true));

    // Aufruf der Methode
    $result = $controller->$method();

    // Wenn der Controller Daten zurückgibt, weise sie der Response zu
    if (is_array($result)) {
        $response = array_merge($response, $result);
        //throw new \Exception (print_r($response, true));
}

} catch (Throwable $e) { 
    \App\Database::closeConnection();
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $response['success'] = false;
    $response['error'] = $e->getMessage();
    $response['details']['trace'] = explode("\n", $e->getTraceAsString());
    
    error_log("API-FEHLER: " . $e->getMessage() . "\nStacktrace:\n" . $e->getTraceAsString());
}

// Zentraler, sauberer Output-Buffer-Abschluss
if (ob_get_length()) ob_end_clean();

echo json_encode($response) ?: json_encode([
    'success' => false,
    'error'   => "JSON Encoding Fehler: " . json_last_error_msg()
    ]);
    exit;