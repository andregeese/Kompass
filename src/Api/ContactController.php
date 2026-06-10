<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
namespace App\Api;
use App\Services\ContactProcessor; // Importiere den Prozessor
use App\Services\PaperlessProcessor;
use Exception;
class ContactController extends \App\Core\BaseController {
    public function __construct($dashboard) {
        // Übergibt das Dashboard an den BaseController, 
        // damit $this->dashboard dort gesetzt wird.
        parent::__construct($dashboard);
    }
    
    public function getContacts()
    {
        $query = $this->request['query'] ?? null;
        $id = $this->request['id'] ?? null;
        
        if (empty($query) && empty($id)) {
            throw new \Exception("Kontakt nicht auffindbar (id / query nicht gesetzt)");
        }
        
        //Suche mail mit Hash
        if (!empty($id)){
            error_log("BY ID");
            $contacts = $this->contactProcess->getContactById($id);
        }else{
            error_log("BY Query");
            $contacts = $this->contactProcess->getContactList($query);
        }

        return ['contacts' => $contacts];        
    }


    public function getAllEmails()
    {
        $contacts = $this->contactProcess->getAllEmails();
        return ["emails" => $contacts];
    }
    public function getContactByEmailHash()
    {
         
        $hash = $this->request['hash'] ?? null;
        if (empty($hash)) {
            return ['success' => false, 'error' => 'Kein Hash übergeben.'];
        }
        
        $contacts = $this->contactProcess->getContactByEmailHash($hash);
        
        return ['contact_emails' => $contacts];
        
    }
    /**
     * Suche anhand eines Strings
     */
    public function getEmailsBySearch()
    {
        $query = $this->request['query'];
        if (empty($query)) {
            throw new \Exception("Emailsuche ist leer");
        }
        
        $contacts = $this->contactProcess->getEmailsBySearch($query);
        
        return ['contact_emails' => $contacts];
        
    }
    /**
     * Abfrage für Mails im Matching von Kontakten
     */
    public function getEmailsByContact()
    {
        $query = $this->request['query'] ?? null;
        if (empty($query)) {
            throw new \Exception('Keine Query übergeben.');
        }
        
        if (is_numeric($query))
        {
            $contacts = $this->contactProcess->getContactListByContactID($query);
        }else{
            $contacts = $this->contactProcess->getContactList($query);
        }
        
        return ['contacts' => $contacts];
    
    }
    
    public function getContactByHashMail() 
    {
        $hash = $this->request['hash'] ?? '';
        
        if (empty($hash)) {
            throw new \Exception("Kein E-Mail-Hash übergeben");            
        }
        
        $email = $this->contactProcess->getContactByMail($hash); 
        return ['contact' => $email];
    }
    /**
     * Holt alle Mails und Paperless verknüpfungen
     */
    public function getTimeLineContact()
    {
        $hash = $this->request['hash'] ?? null;
        
        if (empty($hash)) {
            throw new \Exception("Kein Hash übergeben");
        }
        $salt = $this->dashboard->getHashSalt();
        $TL = $this->contactProcess->getTimelineData($hash, $salt);
        return ["timeline" => $TL];
    }
    /**
     * Suche nach einer E-Mail-Adresse
     */
    public function searchMailAddress()
    {
        $query = $this->request->get('query');
        
        // 2. Validierung: Suchbegriff darf nicht komplett leer sein
        if (empty($query)) {
            throw new \Exception("Kein Suchbegriff übergeben.");
        }
        // 3. Aufruf des Processors mit den nun existierenden Variablen
        $mailDetails = $this->contactProcess->searchMailAddress($query); 
        
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
    /**
     * @unused / @deprecated
     * Verknüpft Mail mit Konakt
     */
    public function autoLinkContact() 
    {
        return ["das darf nicht mehr gehen!"];
        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            if ($data === null) {
                return [
                    'success' => false,
                    'details' => "autoLink hat keine Daten"
                ];
            }
            // 2. JETZT mit $data aufrufen, nicht leer!
            $result = $this->contactProcess->autoLinkContact($data); 
            return [
                'success' => true,
                'details' => $result
            ];
        } catch (\Throwable $e) {   
            $this->dashboard->sendResponse(false, "", "Fehler in " . __FUNCTION__. ":" . __LINE__ . ": ". $e->getMessage());
        }
    }
    
    /**
     * Hole alle Informationen zu dem Kontakt
     */
    public function getContactContext($hash='') 
    {
        $hash = $this->request['hash'] ?? null;
        
        if (empty($hash)) {
            throw new \Exception('Kein Mail-Hash übergeben.');
        }
        
        //tbd: $hash nicht korrekt
        $contacts = $this->dashboard->getContactContext($hash); 
        return [
            'details' => $contacts
        ];
    }
    /**
     * 
     */
    public function insert() 
    {
        // 1. Daten direkt aus dem zentralen Request-Objekt holen
        $name         = trim($this->request['name'] ?? '');
        $email        = trim($this->request['email'] ?? '');
        $type         = trim($this->request['type'] ?? 'company');
        $paperless_id = trim($this->request['paperless_id'] ?? '??');
        // 2. Validierung (Exception wirft Fehler für den Dispatcher)
        if (empty($name) || empty($email)) {
            throw new \Exception('Name und E-Mail dürfen nicht leer sein.');
        }
        // 3. Kontakt speichern
        $contactId = $this->contactProcess->insertContact($name, $email, $type, $paperless_id);
        // 4. Erfolg zurückgeben
        if (!$contactId) {
            throw new \Exception('Fehler beim Erstellen des Kontakts in der Datenbank.');
        }
        return [
            'message'    => 'Kontakt erfolgreich angelegt!',
            'contact_id' => $contactId
        ];
    }

    /**
     * Speichert aus der View (namen und Email)
     */
    public function saveSmallContact()
    {
        $id          = $this->request['id'] ?? null;
        $name        = $this->request['displayname'] ?? null;
        $email       = $this->request['email'] ?? null;
        $type        = $this->request['type'] ?? 'person';
    
        if (!$id) {
            throw new \Exception("Kontakt-ID fehlt.");
        }
        $contactId = $this->contactProcess->insertContact($name, $email, $type);
        
        return [
            'ui' => [
                'notify'  => true,
                'title'   => 'Kontakt',
                'message' => 'Kontakt wurde gespeichert.',
                'type'    => 'success'
            ]
        ];
    }

    /**
     * 
     */

    public function updateContactEmailToContact()
    {
        $cid        = $this->request['cid'];
        $ceid       = $this->request['ceid'];
        $action     = $this->request['action'];
        
        if (empty($cid))
        {
            throw new \Exception("keine ContactID vorhanden");
        }

        $ret = $this->contactProcess->updateContactEmailToContact($cid, $ceid, $action);

        return [
            "paperless"=> $ret,
            "ui" => [
                'notify'  => true,
                'title'   => 'Suche',
                'message' => 'Keine E-Mails gefunden.',
                'type'    => 'info'
            ]
        ];
    }

    /**
     * Speichert den Kontakt aus dem Modal, nur den Namen und Art (keine Paperless, keine Mails, das geschieht on demand)
     */
    public function saveContact()
    {
        
        // 1. Daten aus dem Request holen (inklusive der paperless_id aus dem Modal)
        $contact_id     = $this->request['id'] ?? null;
        $displayname    = $this->request['displayname'] ?? null;
        $type           = $this->request['type'] ?? null;

        if (empty($contact_id) || empty($displayname) )
        {
            throw new \Exception("Name oder ID fehlen");
        }

        $data =  [
            'id' => $contact_id,
            'type' => $type,
            'name'  => $displayname];

        $dbResult = $this->contactProcess->saveContact($data);
        
        if (!$dbResult->success) {
            throw new \Exception($dbResult->error ?? "Fehler beim Aktualisieren des Kontakts.");
        }

        // Wenn wir hier ankommen, war es wirklich ein ERFOLG
        return [
            'contact' => $dbResult->data, // Reines Datenobjekt ans Frontend geben
            'ui' => [
                'notify'  => true,
                'title'   => 'Erfolg',
                'message' => 'Kontakt wurde aktualisiert.',
                'type'    => 'success'
            ]
        ];
    }
}
