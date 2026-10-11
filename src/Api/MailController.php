<?php

/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

namespace App\Api;

use App\Services\MailProcessor; // Importiere den Prozessor
use App\Services\PushService;
use Exception;

class MailController extends \App\Core\BaseController
{
    public function __construct($dashboard)
    {
        parent::__construct($dashboard); // Das ist entscheidend!
    }

    public function listMails()
    {
        error_log("###" . __FUNCTION__ . "###");

        $search = trim($this->request['search'] ?? '');
        if (empty($search)) {
            unset($this->request['search']);
        } else {
            $this->request['search'] = $search;
        }

        // Default-Werte sicherstellen, falls Parameter komplett fehlen
        // Damit die Reflection-Pipeline nicht meckert
        $this->request['page']   = (int)($this->request['page'] ?? 1);
        $this->request['limit']  = (int)($this->request['limit'] ?? 20);
        $this->request['folder'] = $this->request['folder'];

        // 2. Spezial-Logik abfangen (cleanBuffer)
        // Wenn 'search' gesetzt ist und page > 1, greift deine Logik:
        if (isset($this->request['search']) && $this->request['page'] > 1) {
            $this->cleanBuffer();
            return ['mails' => []];
        }

        // 3. Pipeline ausführen
        $processResult = $this->process($this->mailProcess, 'getMailsPaginated', 'mails');
        
        // 4. Kodieren
        if (isset($processResult['details']['mails'])) {
            $processResult['details']['mails'] = $this->recursiveUtf8Encode($processResult['details']['mails']);
        }

        // 4. Jetzt erst das fertige Array zurückgeben
        return $processResult;
    }

    private function recursiveUtf8Encode(array $data)
    {
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

    public function getMailById()
    {
        return $this->process(
            $this->mailProcess,
            'getMailById',
            'mail',
            ''
        );
    }
    /**
     * Suche nach einer Mail
     */
    public function searchMail()
    {
        return $this->process(
            $this->mailProcess,
            'searchMail',
            'mails',
            "" // Nachricht leer, da wir bei einer Suche kein "Erfolg"-Popup wollen
        );

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
        return $this->process(
            $this->mailProcess,
            'getMailsByAddress',
            'mails'
        );
    }

    /**
     * Zeige die E-Mail an
     */
    public function viewMail()
    {
        $hash = $this->request['id'] ?? '';
        if (empty($hash)) {
            throw new \Exception("Kein Mail-Id-Hash übergeben.");
        }
        $mailDetails = $this->mailProcess->getMailById($hash);

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

    public function deleteAllMailsByHash()
    {
        return $this->process($this->mailProcess, 'deleteAllMailsByHash', "allMails", 'Erfolgreich gelöscht');
        error_log("ALLE LÖSCHEN: Response");
        error_log(print_r($a, true));
        error_log("EOF ALLE LÖSCHEN:");
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

        $success = $this->mailProcess->deleteMailByHash($hash);
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
    public function moveToDms()
    {
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
    public function handlePdf()
    {
        $hash = $_POST['id'] ?? '';
        $mail = $this->mailProcess->getMailById($hash);
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
    public function syncMails()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        return $this->process(
            $this->mailProcess, 
            'syncMails', 
            'sync_result', 
            '' //dumm wenn man nach jedem Sync etwas ausgibt, nervt den User
        );
        
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
        return $this->process($this->mailProcess, 'getMailBody', "mail",'');
        $hash = $this->request['hash'] ?? ''; // Nutze dein zentrales Request-Objekt!
        if (empty($hash)) {
            throw new \Exception("Kein gültiger Mail-Hash übergeben.");
        }
        // Die Logik bleibt im Processor
        $body = $this->mailProcess->getMailBody($hash);
        
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

    public function getAccelorateClusters()
    {
        return $this->process($this->mailProcess, 'getAccelorateClusters', 'accelorate_clusters','');
    }

    public function getMailsThatShouldBeAnswered()
    {
        return $this->process($this->mailProcess, 'getMailsThatShouldBeAnswered', 'mails_to_answer','');
    }
}
