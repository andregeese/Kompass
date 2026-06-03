<?php
//api.php
define('ROOT_PATH', __DIR__);
ini_set('display_errors', 0);
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
require_once __DIR__ . '/vendor/autoload.php';

// 1. Initialisierung
\App\Core\ErrorLogs::register();
\App\Core\Auth::init();

header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';


try {
    // 1. Instanz erzeugen
    $db = new \App\Core\Database();

    // 2. Logger instruieren
    \App\Core\ErrorLogs::setDatabase($db);

    // 3. Dashboard mit DB-Instanz versorgen
    $dashboard = new \App\Dashboard($db);

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

        //Appointment
        'get_appointments'              => [App\Api\AppointmentsController::class, 'getAppointments'],
        'get_events'                    => [App\Api\AppointmentsController::class, 'getEventsParsed'],
        'full_sync'                     => [App\Api\AppointmentsController::class, 'fullSync'],
        'get_parti'                     => [App\Api\AppointmentsController::class, 'getPartition'],

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
        'get_emails_by_hash'         => [App\Api\ContactController::class, 'getContactByEmailHash'],  
        'get_emails_by_contact'         => [App\Api\ContactController::class, 'getEmailsByContact'],  
        'get_emails_by_search'           => [App\Api\ContactController::class, 'getEmailsBySearch'],  
        'list_contacts'                 => [App\Api\ContactController::class, 'getContacts'],
        'get_graph'                     => [App\Api\ContactController::class, 'getContacts'],    
        
        // System
        'system_status'                 => [App\Api\SystemController::class, 'status'],
        'repair_contacts'               => [App\Api\SystemController::class, 'repairTenantContacts'],
        'imap_mails'                    => [App\Api\SystemController::class, 'syncFolder'],
        'test_imap'                     => [App\Api\SystemController::class, 'testImap'],

    ];
    if (!array_key_exists($action, $routes)) {
        throw new \Exception("Aktion '$action' unbekannt.");
    }

    // 4. Controller ausführen
    $controllerClass = $routes[$action][0];
    $method = $routes[$action][1];
    
    // Hier greift der BaseController Konstruktor (Sicherheits-Check passiert automatisch!)
    $controller = new $controllerClass($dashboard);
    $result = $controller->$method();

    echo json_encode(array_merge(['success' => true], (array)$result));

} catch (Throwable $e) { 
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}