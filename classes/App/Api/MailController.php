<?php
namespace App\Api;
use App\MailProcessor; // Importiere den Prozessor

use Exception;

class MailController extends BaseController 
{
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }
    
    public function listMails() 
    {
        try{
            
            $page   = isset($_GET['page'])   ? (int)$_GET['page']   : 1;
            $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 20;
            $folder = isset($_GET['folder']) ? $_GET['folder']      : 'INBOX';
            $search = isset($_GET['search']) ? $_GET['search']      : '';

            if (!empty($search) && $page > 1) {
                $this->cleanBuffer();
                return ['success' => true, 'details' => ['mails' => []]];
            }
            

            $mails = $this->mailProcess->getMailsPaginated($page, $limit, $folder, $search);
            $cleanMails = $this->recursiveUtf8Encode($mails);
            
            return [
                'success' => true,
                'details' => [
                    'mails' => $cleanMails
                ]
            ];
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage());

            return [
                'success' => false,
                'error'   => 'Fehler beim Laden der E-Mails: ' . $e->getMessage()
            ];
        }
    }

    private function recursiveUtf8Encode(array $data) {
            array_walk_recursive($data, function (&$item, $key) {
            // Falls das Feld 'hash' (oder 'id_hash') heißt und binär ist:
            if (($key === 'hash' || $key === 'id_hash') && !mb_check_encoding($item, 'UTF-8')) {
                $item = bin2hex($item); // Binär -> Hex (jetzt ist es ein druckbarer String)
            } 
            // Standard UTF-8 Sanitisierung
            elseif (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            }
        });
        return $data;
    }

    /**
     * Suche nach einer Mail
     */
    public function searchMail()
    {
        $search = $_GET['query'] ?? '';
        $folder = $_GET['folder'] ?? 'INBOX'; // Standardwert, falls kein Ordner übergeben wird
        $page   = (int)($_GET['page'] ?? 1);
        $limit  = (int)($_GET['limit'] ?? 50);
        
        // 2. Validierung: Suchbegriff darf nicht komplett leer sein
        if (empty($search)) {
            throw new \Exception("Kein Suchbegriff übergeben.");
        }

        // 3. Aufruf des Processors mit den nun existierenden Variablen
        $mailDetails = $this->mailProcess->searchMail($folder, $search, $page, $limit); 
        
        if (!$mailDetails) {
            return ['success' => false, 'message' => "Keine Mails gefunden."];
        }

        // ACHTUNG: Hier hast du einen Design-Fehler im Controller:
        // Dein MailProcessor gibt jetzt eine LISTE (Array von Mails) zurück, 
        // aber dein Code unten geht von EINEM Objekt aus.
        return [
            'success' => true,
            'details' => [
                'mails' => $mailDetails // Gib einfach das Array der gefundenen Mails zurück
            ]
        ];
    }

    /**
     * Zeige die E-Mail an
     */
    public function viewMail() {
        $hash = $_GET['id'] ?? '';
        if (empty($hash)) {
            throw new \Exception("Kein Mail-Id-Hash übergeben.");
        }

        $mailDetails = $this->mailProcess->getMailByHash($hash); 
        
        if (!$mailDetails) {
            throw new \Exception("Mail existiert nicht in der lokalen Datenbank.");
        }

        return [
            'success' => true,
            'details' => [
                'subject'      => $this->decrypt($mailDetails['subject_hash']),
                'date'         => $mailDetails['date_received'],
                'sender'       => $mailDetails['sender'],
                'paperless_id' => $mailDetails['paperless_id']
            ]
        ];
    }

    /**
     * lösche die E-Mail
     */
    public function deleteMail() {
        $hash = $_POST['id'] ?? '';
        if (!$hash) {
            header('HTTP/1.1 400 Bad Request');
            return ['success' => false, 'error' => 'ID fehlt'];
        }
        try{
            return $this->mailProcess->deleteMailByHash($hash);

        } catch (Exception $e) {
            error_log("Fehler bei moveToDms: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Verschiebe die E-Mail nach DMS
     */
    public function moveToDms() {
        $hash = $_POST['id'] ?? '';
        if (!$hash) {
            header('HTTP/1.1 400 Bad Request');
            return ['success' => false, 'error' => 'ID fehlt'];
        }

        try {
            // 2. Logik delegieren (Kein method_exists mehr, wir vertrauen unserer IDE!)
            $this->mailProcess->moveToDMS($hash);
            
            // 3. Antwort zurückgeben
            return ['success' => true];

        } catch (Exception $e) {
            error_log("Fehler bei moveToDms: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function handlePdf() {
        $hash = $_POST['id'] ?? '';
        $mail = $this->mailProcess->getMailByHash($hash); 

        $success = false;
        if (method_exists($this->mailProcess, 'moveToDMS')) {
            $this->mailProcess->moveToDMS($hash);
            $success = true;
        }
        return [
            'success' => $success,
            'details' => ['Mail verschoben']
        ];
    }

    /**
     * Sychronisiere die Postfächer
     */
    public function syncICloud() 
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        $folder = isset($_GET['folder']) ? trim($_GET['folder']) : 'INBOX';
        $allowedFolders = ['INBOX', 'DMS', 'DMSDone'];
        
        if (!in_array($folder, $allowedFolders)) {
            throw new \Exception("Ungültiger Ordner-Name übergeben.");
        }
        try{

            $syncResult = $this->mailProcess->syncWithICloud(true, 200, $folder);
            $this->cleanBuffer(); 
            return $syncResult; // Gibt das komplette Sync-Array direkt zurück
        } catch (Exception $e) {
            error_log("Fehler bei moveToDms: " . $e->getMessage());
            header('HTTP/1.1 500 Internal Server Error');
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 
     */
    public function trackFolders() {
        ignore_user_abort(false);
        $syncResult = $this->mailProcess->trackAndSyncFolderStatus();
        return [
            'success' => true,
            'details' => [
                'updated' => $syncResult['updated'] ?? 0,
                'logs'    => $syncResult['logs'] ?? []
            ]
        ];
    }

    /**
     * stream die Mail
     */
    public function streamHtml() 
    {
        $hash = $_GET['hash'] ?? '';
        if (empty($hash)) {
            throw new \Exception("Fehler: Kein gültiger Mail-Hash übergeben.");
        }

        $htmlContent = $this->mailProcess->getDecryptedMailBody($hash);

        if (empty($htmlContent)) {
            $mailDetails = $this->mailProcess->getMailByHash($hash); 
            $htmlContent = $this->mailProcess->getHtmlBody($hash); 

            if (empty($htmlContent)) {
                throw new \Exception("Der IMAP-Server hat keinen Inhalt für diese E-Mail zurückgegeben.");
            }

            $plainText = $this->mailProcess->getTxtBody($hash) ?? strip_tags($htmlContent);
            $this->mailProcess->saveMailWithContent($hash, $htmlContent, $plainText);
        }
        
        // Sonderfall: Direkter HTML-Stream an den Browser unterbricht JSON-Rückgabe
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' *; script-src 'none';");
        echo $htmlContent;
        exit;
    }
}