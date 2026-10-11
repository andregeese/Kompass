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
            $contacts = $this->contactProcess->getContactListByQuery($query);
        }

        return ['contacts' => $contacts];        
    }


    public function getAllEmails()
    {
        $contacts = $this->contactProcess->getAllEmails();
        return ["emails" => $contacts];
    }

    public function getContactByHash()
    {
         
        return $this->process( $this->contactProcess, 'getContactByHash', 'contacts', '');
        
    }
    /**
     * Suche anhand eines Strings
     */
    public function getEmailsBySearch()
    {
        
        return $this->process($this->contactProcess, 'getEmailsBySearch', 'contact_emails', '');   
    }
    /**
     * Abfrage für Mails im Matching von Kontakten
     */
    public function getEmailsByContact()
    {
        return $this->process($this->contactProcess, 'getContactListByContactID', 'contacts');
    
    }
    
    public function getContactByIdMail() 
    {
        
        return $this->process(
            $this->contactProcess,
            'getContactByIdMail',
            'contact',
            ''
        ); 
    }
    /**
     * Holt alle Mails und Paperless verknüpfungen
     */
    public function getTimeLineContact()
    {
        return $this->process(
            $this->contactProcess, 
            'getTimelineData', 
            'timeline', 
            ''
        );
    }

    /**
     * Suche nach einer E-Mail-Adresse
     */
    public function searchMailAddress()
    {
        return $this->process(
            $this->contactProcess,
            'searchMailAddress',
            'mails',
            ''
        );
    }


    /**
     * @unused / @deprecated
     * Verknüpft Mail mit Konakt
     */
    public function autoLinkContact() 
    {
        error_log("!!!" .  _FILE__ . ":"  .__FUNCTION__ .  "das darf nicht mehr gehen!");
        return false;
        
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
        return $this->process(
            $this->contactProcess, 
            'updateContactEmailToContact', 
            "contact", 
            "Aktion erfolgreich"
        );
    }

    /**
     * Speichert den Kontakt aus dem Modal, nur den Namen und Art (keine Paperless, keine Mails, das geschieht on demand)
     */
    public function saveContact()
    {
        return $this->process(
            $this->contactProcess,
            'saveContact',
            'contact',
            'Kontakt erfolgreich aktualisiert'
        );
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
