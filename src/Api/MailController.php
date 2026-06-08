<?php
namespace App\Api;
use App\Services\MailProcessor; // Importiere den Prozessor

use Exception;

class MailController extends \App\Core\BaseController 
{
    public function __construct($dashboard) {
        parent::__construct($dashboard); // Das ist entscheidend!
    }
    
    public function listMails() 
    {
        // 1. Parameter aus dem zentralen Request-Objekt holen
        $page   = (int)($this->request['page'] ?? 1);
        $limit  = (int)($this->request['limit'] ?? 20);
        $folder = $this->request['folder'] ?? 'INBOX';
        $search = $this->request['search'] ?? '';

        if (!empty($search) && $page > 1) {
            $this->cleanBuffer();
            return ['mails' => []];
        }
        
        $mails = $this->mailProcess->getMailsPaginated($page, $limit, $folder, $search);
        $cleanMails = $this->recursiveUtf8Encode($mails);
        
        return [
            'mails' => $cleanMails
        ];

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
        $search = $this->request['query'] ?? '';
        $folder = $this->request['folder'] ?? 'INBOX'; 
        $page   = (int)$this->request['page'] ?? 1;
        $limit  = (int)$this->request['limit'] ?? 50;
        
        // 2. Validierung: Suchbegriff darf nicht komplett leer sein
        if (empty($search)) {
            throw new \Exception("Kein Suchbegriff übergeben.");
        }

        // 3. Aufruf des Processors mit den nun existierenden Variablen
        $mailDetails = $this->mailProcess->searchMail($folder, $search, $page, $limit); 
        
        if (!$mailDetails) {
            if (empty($mailDetails)) {
                return [
                    "mails" => [],
                    "ui" => [
                        'notify'  => true,
                        'title'   => 'Suche',
                        'message' => 'Keine E-Mails gefunden.',
                        'type'    => 'info'
                    ]
                ];
            }
        }

        return [
            "mails" => $mailDetails
        ];
    }

     public function getMailsByAddress()
    {
        $query = $this->request['query'] ?? '';
        
        // 2. Validierung: Suchbegriff darf nicht komplett leer sein
        if (empty($query)) {
            throw new \Exception("Kein Suchbegriff übergeben.");
        }

        // 3. Aufruf des Processors mit den nun existierenden Variablen
        $mailDetails = $this->mailProcess->getMailsByAddress($query); 
        
        if (!$mailDetails) {
            if (empty($mailDetails)) {
                return [
                    "mails" => [],
                    "ui" => [
                        'notify'  => true,
                        'title'   => 'Suche',
                        'message' => 'Keine E-Mails gefunden.',
                        'type'    => 'info'
                    ]
                ];
            }
        }

        return [
            "mails" => $mailDetails
        ];
    }

    public function getMailByHash()
    {
        $hash = $this->request['hash'] ?? null;
        if (empty($hash)) {
            throw new \Exception("Kein Mail-Id-Hash übergeben.");
        }

        $mailDetails = $this->mailProcess->getMailByHash($hash); 
        
        if (!$mailDetails) {
            throw new \Exception("Mail existiert nicht in der lokalen Datenbank.");
        }

        return [
            'subject'      => $this->dashboard->decrypt($mailDetails['subject_hash']),
            'date'         => $mailDetails['date_received'],
            'sender'       => $mailDetails['sender'],
            'paperless_id' => $mailDetails['paperless_id']
        ];

    }

    /**
     * Zeige die E-Mail an
     */
    public function viewMail() {
        $hash = $this->request['id'] ?? '';

        if (empty($hash)) {
            throw new \Exception("Kein Mail-Id-Hash übergeben.");
        }

        $mailDetails = $this->mailProcess->getMailByHash($hash); 
        
        if (!$mailDetails) {
            throw new \Exception("Mail existiert nicht in der lokalen Datenbank.");
        }

        return [
            'subject'      => $this->dashboard->decrypt($mailDetails['subject_hash']),
            'date'         => $mailDetails['date_received'],
            'sender'       => $mailDetails['sender'],
            'paperless_id' => $mailDetails['paperless_id']
        ];
    }

    /**
     * lösche die E-Mail
     */
    public function deleteMail() 
    {
        $hash = $this->request['id'] ?? '';
        if (!$hash) {
            throw new \Exception("die ID zum löschen fehlt");
        }
    
        $success= $this->mailProcess->deleteMailByHash($hash);
        if (!$success) {
            throw new \Exception("Löschen fehlgeschlagen.");
        }

        return [
            "mail" => [],
            'ui' => [
                'notify'  => true,
                'title'   => 'E-Mail',
                'message' => 'E-Mail wurde erfolgreich gelöscht.',
                'type'    => 'success'
            ]
        ];
    }

    /**
     * Verschiebe die E-Mail nach DMS
     */
    public function moveToDms() {
        $hash = $this->request['id'] ?? null;
        if (!$hash) {
            throw new \Exception("ID zum verschieben fehlt");
        }

    
        // 2. Logik delegieren (Kein method_exists mehr, wir vertrauen unserer IDE!)
        $success = $this->mailProcess->moveToDMS($hash);
        
        if (!$success) {
            throw new \Exception("Verschieben fehlgeschlagen.");
        }

        // 2. Einheitliche Antwort
        // Dein Frontend-Dispatcher liest das 'ui'-Objekt und zeigt den Toast automatisch an.
        return [
            "mail" => [],
            'ui' => [
                'notify'  => true,
                'title'   => 'DMS Archiv',
                'message' => 'E-Mail wurde erfolgreich verschoben.',
                'type'    => 'success'
            ]
        ];
    }

    /**
     * @deprecated
     */
    public function handlePdf() {
        $hash = $_POST['id'] ?? '';
        $mail = $this->mailProcess->getMailByHash($hash); 

        $success = false;
        if (method_exists($this->mailProcess, 'moveToDMS')) {
            $this->mailProcess->moveToDMS($hash);
            $success = true;
        }
        return [
            "mail" => [],
            'ui' => [
                'notify'  => true,
                'title'   => 'DMS Archiv',
                'message' => 'E-Mail wurde erfolgreich verschoben.',
                'type'    => 'success'
            ]
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
        
        $folder = $this->request['folder'] ?? null;
        $allowedFolders = ['INBOX', 'DMS', 'DMSDone','Sent Messages'];
        
        if (!in_array($folder, $allowedFolders)) {
            throw new \Exception("Ungültiger Ordner-Name übergeben.");
        }
        ob_start();
        
        $syncResult = $this->mailProcess->syncWithICloud(100, $folder);
        //$updateCount = $this->mailProcess->deltaSync($folder, 100);
        
        ob_end_clean();

        return [
            "sync"      => $syncResult //,"update"    => $updateCount
        ]; // Gibt das komplette Sync-Array direkt zurück
    }

    /**
     * 
     */
    public function trackFolders() 
    {
        ignore_user_abort(false);
        //$syncResult = $this->mailProcess->trackAndSyncFolderStatus();
        return [
            'updated' => $syncResult['updated'] ?? 0,
            'logs'    => $syncResult['logs'] ?? []
        ];
    }

    /**
     * streamt die Mail
     */
    public function streamHtml() 
    {
        $hash = $this->request['hash'] ?? ''; // Nutze dein zentrales Request-Objekt!

        if (empty($hash)) {
            throw new \Exception("Kein gültiger Mail-Hash übergeben.");
        }

        // Die Logik bleibt im Processor
        $body = $this->mailProcess->getMailBody($hash);
        error_log("MAILBody GEFUNDEN: " . $body);

        // Jetzt die Ausnahme-Ausgabe
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' *; script-src 'none';");
        
        echo $body;
        exit; // Wichtig: Hier hart beenden, damit der Dispatcher nicht mehr greift
        
    }

    public function getRecentMails()
    {
        $success = $this->mailProcess->getRecentMails();
        
        if (!$success) {
            throw new \Exception("Letzte Mails fehlgeschlagen.");
        }

        error_log(print_r($success, true));

        // 2. Einheitliche Antwort
        // Dein Frontend-Dispatcher liest das 'ui'-Objekt und zeigt den Toast automatisch an.
        return [
            'mails' => $success,
            'ui' => [
                'notify'  => true,
                'title'   => 'Letze Mails',
                'message' => 'E-Mail wurde erfolgreich abgerufen.',
                'type'    => 'success'
            ]
        ];

    }
}