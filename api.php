<?php

/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

//api.php
date_default_timezone_set('Europe/Berlin');
define('ROOT_PATH', __DIR__);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// 2. Wohin schreibt PHP interne Fehler?
$logFile = __DIR__ . "/debug.log";
ini_set('log_errors', 1);
ini_set('error_log', $logFile); // Hier muss 'error_log' stehen, nicht deine Klasse

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: X-CSRF-TOKEN, Content-Type");
    header("HTTP/1.1 200 OK");
    exit;
}
$all_headers = [];
foreach ($_SERVER as $name => $value) {
    if (substr($name, 0, 5) == 'HTTP_') {
        $all_headers[$name] = $value;
    }
}
//error_log("ALLE HTTP-HEADER: " . print_r($all_headers, true));
// 3. Sicherstellen, dass die Datei beschreibbar ist
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}
require_once __DIR__ . '/vendor/autoload.php';
// 1. Initialisierung
\App\Core\ErrorLogs::register();
\App\Core\Auth::init();
$receivedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($headers['X-CSRF-TOKEN'] ?? 'KEIN_TOKEN');
$action = $_REQUEST['action'] ?? '';
// Liste der Aktionen, die KEIN CSRF-Token benötigen
$publicActions = ['login', 'register'];
if (!in_array($action, $publicActions)) {
    // Hole das Token IMMER aus der korrekten $_SERVER Variable (da Nginx/PHP-FPM)
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
$action = $_REQUEST['action'] ?? '';
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
    'get_mail_by_hash'              => [App\Api\MailController::class, 'getMailByHash'],
    'get_mails_by_mailadress'       => [App\Api\MailController::class, 'getMailsByAddress'],
    'list_mails'                    => [App\Api\MailController::class, 'listMails'],
    'view'                          => [App\Api\MailController::class, 'viewMail'],
    'delete'                        => [App\Api\MailController::class, 'deleteMail'],
    'dms'                           => [App\Api\MailController::class, 'moveToDms'],
    'pdf'                           => [App\Api\MailController::class, 'handlePdf'],
    'sync'                          => [App\Api\MailController::class, 'syncICloud'],
    'track_folders'                 => [App\Api\MailController::class, 'trackFolders'],
    'stream_mail_html'              => [App\Api\MailController::class, 'streamHtml'], // Sonderfall: Rendert HTML
    'search_mail'                   => [App\Api\MailController::class, 'searchMail'],
    'get_recent_mails'              => [App\Api\MailController::class, 'getRecentMails'],
    //Appointment
    'get_appointment'               => [App\Api\AppointmentsController::class, 'getAppointment'],
    'get_appointments'              => [App\Api\AppointmentsController::class, 'getAppointments'],
    'get_events'                    => [App\Api\AppointmentsController::class, 'getEventsParsed'],
    'full_sync'                     => [App\Api\AppointmentsController::class, 'fullSync'],
    'get_parti'                     => [App\Api\AppointmentsController::class, 'getPartition'],
    'save_appointment'              => [App\Api\AppointmentsController::class, 'saveAppointment'],
    //Sidebar


    // Paperless
    'syncPaperless'                 => [App\Api\PaperlessController::class, 'sync'],
    'paperless_search'              => [App\Api\PaperlessController::class, 'search'],
    //'get_paperless_correspondents'  => [App\Api\PaperlessController::class, 'getCorrespondents'], //mail als 
    'paperless_mail_tree'           => [App\Api\PaperlessController::class, 'getMailTree'],
    'paperless_tree'                => [App\Api\PaperlessController::class, 'getTree'],
    'link_mail_to_paperless'        => [App\Api\PaperlessController::class, 'linkMail'],
    'get_correspondet'              => [App\Api\PaperlessController::class, 'getCorrespondentById'],
    'search_correspondents'         => [App\Api\PaperlessController::class, 'searchCorrespondents'],
    'get_correspondets'             => [App\Api\PaperlessController::class, 'getCorrespondents'],
    'link_contact_item'             => [App\Api\PaperlessController::class, 'linkContact'],

    // Contacts
    'search_contact'                => [App\Api\ContactController::class, 'getContactByHashMail'],
    'search_mail_address'           => [App\Api\ContactController::class, 'searchMailAddress'],
    'insert_contact'                => [App\Api\ContactController::class, 'insert'],
    'save_contact_manual'           => [App\Api\ContactController::class, 'saveSmallContact'],
    'save_contact'                  => [App\Api\ContactController::class, 'saveContact'],
    'get_contact_context'           => [App\Api\ContactController::class, 'getContactContext'],
    'auto_link_contact'             => [App\Api\ContactController::class, 'autoLinkContact'],
    'get_contact_timeline'          => [App\Api\ContactController::class, 'getTimeLineContact'],
    'get_all_emails'                => [App\Api\ContactController::class, 'getAllEmails'],
    'get_emails_by_hash'            => [App\Api\ContactController::class, 'getContactByEmailHash'],
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
    //NOTES
    'get_notes'                     => [App\Api\NotesController::class, 'getNotes'],
    'get_notes_by_uid'              => [App\Api\NotesController::class, 'getNotesByUID'],
    'save_note'                     => [App\Api\NotesController::class, 'saveNote'],
    'toggle_note'                   => [App\Api\NotesController::class, 'toogleNote'],
];

// 1. Spezieller Endpunkt für die Routen-Konfiguration
if ($action === 'get_allowed_actions') {
    echo json_encode(['success' => true, 'data' => array_keys($routes)]);
    exit;
}
if (!array_key_exists($action, $routes)) {
    throw new \Exception("Aktion '$action' unbekannt.");
}
// 4. Controller ausführen
$controllerClass = $routes[$action][0];
$method = $routes[$action][1];
try {
    // Hier greift der BaseController Konstruktor (Sicherheits-Check passiert automatisch!)
    $controller = new $controllerClass($dashboard);
    $result = $controller->$method();
    \App\Dashboard::sendResponse(true, $result ?? new \stdClass());
} catch (\Throwable $e) {
    error_log("API Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Status Code setzen
    http_response_code(500);

    // Versuch 1: Über dein Framework-Dashboard ausgeben
    try {
        // 💡 Wir übergeben die Fehlerdetails & den StackTrace als Array im zweiten Parameter!
        \App\Dashboard::sendResponse(false, [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()) // Macht ein schönes JSON-Array draus
        ], $e->getMessage());
        exit;
    } catch (\Throwable $internalError) {
        // 1. Immer sofort in den System-Log schreiben
        error_log("API Global Catch: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

        // 2. HTTP-Statuscode setzen
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        // 3. Pures, ungezähmtes JSON ausgeben inklusive StackTrace im Fallback
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'error'   => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => explode("\n", $e->getTraceAsString())
        ]);
        exit;
    }
}
