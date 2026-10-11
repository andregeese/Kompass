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
define('ROOT_PATH', __DIR__);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Interne PHP-Fehler in Datei umleiten
$logFile = __DIR__ . "/debug.log";
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

// CORS / Preflight HANDLING (CORS-Headers für Cookies & modern JS-Fetch)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: X-CSRF-TOKEN, Content-Type, Authorization");
    header("HTTP/1.1 200 OK");
    exit;
}

// Log-Datei initialisieren
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

require_once __DIR__ . '/vendor/autoload.php';

// Session Cookie Parameter vor Auth::init() setzen (Sicher gegen Port- & SameSite-Issues)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'domain'   => '',
        'secure'   => false, // Auf true setzen, sobald HTTPS aktiv ist
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// 1. Initialisierung
\App\Core\ErrorLogs::register();
\App\Core\Auth::init();

$action = $_REQUEST['action'] ?? '';

// Öffentliche Aktionen (ohne CSRF / Session Check)
$publicActions = ['login', 'register', 'forgot_password', 'reset_password'];

if (isset($_GET['debug']) && $_GET['debug'] === 'debugMe') {
    // Debug Bypass
} elseif (!in_array($action, $publicActions, true)) {
    
    // Fix: Sicheres Auslesen des CSRF-Tokens aus $_SERVER
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (!\App\Core\Auth::verifyCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'CSRF Token ungültig']);
        exit;
    }

    if (!\App\Core\Auth::validateFingerprint()) {
        error_log("SICHERHEITSWARNUNG: Fingerprint-Mismatch für User ID " . ($_SESSION['user_id'] ?? 'Guest'));
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sitzung abgelaufen oder ungültig.']);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

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
    'forgot_password'               => [App\Api\AuthController::class, 'forgotPassword'],
    'reset_password'                => [App\Api\AuthController::class, 'resetPassword'],
    'global_search'                 => [App\Api\SystemController::class, 'globalSearch'],

    // Settings
    'get_settings'                  => [App\Api\SettingsController::class, 'get'],
    'save_settings'                 => [App\Api\SettingsController::class, 'save'],

    'save_push_subscription'        => [App\Api\PushController::class, 'subscribe'],

    // Mails
    'get_mail_by_id'                => [App\Api\MailController::class, 'getMailById'],
    'get_mails_by_mailadress'       => [App\Api\MailController::class, 'getMailsByAddress'],
    'get_accelerate_cluster'        => [App\Api\MailController::class, 'getAccelorateClusters'],
    'list_mails'                    => [App\Api\MailController::class, 'listMails'],
    'view'                          => [App\Api\MailController::class, 'viewMail'],
    'delete'                        => [App\Api\MailController::class, 'deleteMail'],
    'delete_all_mails'              => [App\Api\MailController::class, 'deleteAllMailsByHash'],
    'dms'                           => [App\Api\MailController::class, 'moveToDms'],
    'pdf'                           => [App\Api\MailController::class, 'handlePdf'],
    'sync_mails'                    => [App\Api\MailController::class, 'syncMails'],
    'track_folders'                 => [App\Api\MailController::class, 'trackFolders'],
    'stream_mail_html'              => [App\Api\MailController::class, 'streamHtml'],
    'search_mail'                   => [App\Api\MailController::class, 'searchMail'],
    'get_recent_mails'              => [App\Api\MailController::class, 'getRecentMails'],
    'get_mail_data_for_note'        => [App\Api\MailController::class, 'getMailById'],
    'get_mails_that_should_be_answered' => [App\Api\MailController::class, 'getMailsThatShouldBeAnswered'],

    // Appointments
    'get_appointment'               => [App\Api\AppointmentsController::class, 'getAppointment'],
    'get_appointments'              => [App\Api\AppointmentsController::class, 'getAppointments'],
    'get_upcoming_events'           => [App\Api\AppointmentsController::class, 'getUpcomingEvents'],
    'get_events'                    => [App\Api\AppointmentsController::class, 'getEventsParsed'],
    'full_sync'                     => [App\Api\AppointmentsController::class, 'fullSync'],
    'get_parti'                     => [App\Api\AppointmentsController::class, 'getPartition'],
    'save_appointment'              => [App\Api\AppointmentsController::class, 'saveAppointment'],
    'update_appointment_contact'    => [App\Api\AppointmentsController::class, 'updateAppointment'],

    // Paperless
    'get_paperless_info'            => [App\Api\PaperlessController::class, 'getPaperlessInfos'],
    'get_last_documents'            => [App\Api\PaperlessController::class, 'getLastDocuments'],
    'get_last_unclassified'         => [App\Api\PaperlessController::class, 'getLastUnclassifiedDocuments'],
    'get_total_document_count'      => [App\Api\PaperlessController::class, 'getTotalDocumentCount'],
    'syncPaperless'                 => [App\Api\PaperlessController::class, 'sync'],
    'paperless_search'              => [App\Api\PaperlessController::class, 'search'],
    'paperless_mail_tree'           => [App\Api\PaperlessController::class, 'getMailTree'],
    'paperless_tree'                => [App\Api\PaperlessController::class, 'getTree'],
    'link_mail_to_paperless'        => [App\Api\PaperlessController::class, 'linkMail'],
    'get_correspondet'              => [App\Api\PaperlessController::class, 'getCorrespondentById'],
    'get_correspondent_by_contact'  => [App\Api\PaperlessController::class, 'getCorrespondentByContact'],
    'get_tags_by_search'            => [App\Api\PaperlessController::class, 'getTagsBySearch'],
    'get_tags_by_contact'           => [App\Api\PaperlessController::class, 'getTagsByContact'],
    'update_tag_to_contact'         => [App\Api\PaperlessController::class, 'updateTagToContact'],
    'search_correspondents'         => [App\Api\PaperlessController::class, 'searchCorrespondents'],
    'search_tags'                   => [App\Api\PaperlessController::class, 'searchTags'],
    'get_correspondets'             => [App\Api\PaperlessController::class, 'getCorrespondents'],
    'link_contact_item'             => [App\Api\PaperlessController::class, 'linkContact'],
    'update_paperless_to_contact'   => [App\Api\PaperlessController::class, 'linkContact'],

    // Contacts
    'search_contact'                => [App\Api\ContactController::class, 'getContactByIdMail'],
    'search_mail_address'           => [App\Api\ContactController::class, 'searchMailAddress'],
    'insert_contact'                => [App\Api\ContactController::class, 'insert'],
    'save_contact_manual'           => [App\Api\ContactController::class, 'saveSmallContact'],
    'save_contact'                  => [App\Api\ContactController::class, 'saveContact'],
    'get_contact_context'           => [App\Api\ContactController::class, 'getContactContext'],
    'auto_link_contact'             => [App\Api\ContactController::class, 'autoLinkContact'],
    'get_contact_timeline'          => [App\Api\ContactController::class, 'getTimeLineContact'],
    'get_all_emails'                => [App\Api\ContactController::class, 'getAllEmails'],
    'get_contact_by_hash'           => [App\Api\ContactController::class, 'getContactByHash'],
    'get_emails_by_contact'         => [App\Api\ContactController::class, 'getEmailsByContact'],
    'get_emails_by_search'          => [App\Api\ContactController::class, 'getEmailsBySearch'],
    'list_contacts'                 => [App\Api\ContactController::class, 'getContacts'],
    'get_graph'                     => [App\Api\ContactController::class, 'getContacts'],
    'update_mail_to_contact'        => [App\Api\ContactController::class, 'updateContactEmailToContact'],

    // System
    'system_status'                 => [App\Api\SystemController::class, 'status'],
    'repair_contacts'               => [App\Api\SystemController::class, 'repairTenantContacts'],
    'imap_mails'                    => [App\Api\SystemController::class, 'syncFolder'],
    'test_imap'                     => [App\Api\SystemController::class, 'testImap'],
    'get_allowed_actions'           => [App\Api\SystemController::class, 'getAllowedActions'],
    'get_imap_folder'               => [App\Api\SystemController::class, 'getIMAPFolder'],

    // Notes
    'get_notes'                     => [App\Api\NotesController::class, 'getNotes'],
    'get_notes_by_uid'              => [App\Api\NotesController::class, 'getNotesByUID'],
    'save_note'                     => [App\Api\NotesController::class, 'saveNote'],
    'toggle_note'                   => [App\Api\NotesController::class, 'toogleNote'],
    'get_critical_memos'            => [App\Api\NotesController::class, 'getCriticalMemos'],
];

// Endpunkt für die Routen-Konfiguration
if ($action === 'get_allowed_actions') {
    echo json_encode(['success' => true, 'data' => array_keys($routes)]);
    exit;
}

// Controller-Ausführung in den try-catch Block legen
try {
    if (!array_key_exists($action, $routes)) {
        throw new \Exception("Aktion '$action' unbekannt.");
    }

    $controllerClass = $routes[$action][0];
    $method = $routes[$action][1];

    $controller = new $controllerClass($dashboard);
    $responseBody = $controller->$method();

    if (!is_array($responseBody) || !isset($responseBody['success'])) {
        $responseBody = [
            'success' => true,
            'status'  => 'success',
            'details' => $responseBody ?? new \stdClass(),
            'error'   => null
        ];
    }

    \App\Dashboard::sendResponse(
        (bool)($responseBody['success'] ?? true), 
        $responseBody['details'] ?? new \stdClass(), 
        $responseBody['error'] ?? null, 
        $responseBody['status'] ?? 'success' // <-- FIX: Garantiert IMMER einen String!
    );

} catch (\Throwable $e) {
    error_log("API Global Catch: " . $e->getMessage());
    
    \App\Dashboard::sendResponse(
        false, 
        [
            'ui' => [
                'notify'  => true,
                'title'   => 'Fehler',
                'message' => $e->getMessage(),
                'type'    => 'danger'
            ]
        ], 
        $e->getMessage(), 
        'error'
    );
}