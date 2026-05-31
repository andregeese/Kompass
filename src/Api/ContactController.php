<?php
namespace App\Api;
use App\Services\ContactProcessor; // Importiere den Prozessor
use App\Services\PaperlessProcessor;

class ContactController extends \App\Core\BaseController {

    public function __construct($dashboard) {
        // Übergibt das Dashboard an den BaseController, 
        // damit $this->dashboard dort gesetzt wird.
        parent::__construct($dashboard);
    }
    
    public function getContacts()
    {

        //Array ( [action] => list_contacts [page] => 1 [limit] => 20 [query] => [CF_AppSession] => d8145ad5e1ccd7ca [PHPSESSID] => a4023d5e3ce512989a14d53d3f4f4c5b )

        $query = trim($_REQUEST['query'] ?? '');
        $id = trim($_REQUEST['id'] ?? '');

        //return ['success' => false, 'error' => print_r($_GET, true)];

        //tbd: hashed

        if (empty($query) && empty($id)) {
            return ['success' => false, 'error' => 'Kein Query übergeben.'];
        }
        
        //Suche mail mit Hash
        if (!empty($id)){
            $contacts = $this->contactProcess->getContactById($id);
        }else{
            $contacts = $this->contactProcess->getContactList($query);
        }

        $this->dashboard->sendResponse(true, ['contacts' => $contacts]);
        return [
            'success' => true,
            'details' => ['contacts' => $contacts]
        ];
    }

    /**
     * Abfrage für Mails im Matching von Kontakten
     */
    public function getEmailsByContact()
    {
        $query = trim($_REQUEST['query'] ?? '');

        if (empty($query)) {
            return ['success' => false, 'error' => 'Keine Query übergeben.'];
        }
        
        if (is_numeric($query))
        {
            $contacts = $this->contactProcess->getContactListByContactID($query);
        }else{
            $contacts = $this->contactProcess->getContactList($query);
        }
        
        
        return [
            'success' => true,
            'details' => ['contacts' => $contacts]
        ];
    }

    
    public function getContactByHashMail() {
        $hash = trim($_REQUEST['hash'] ?? '');
        
        if (empty($hash)) {
            return ['success' => false, 'error' => 'Kein Mail-Hash übergeben.'];
        }
        
        $email = $this->contactProcess->getContactByMail($hash); 

        return [
            'success' => true,
            'details' => ['contact' => $email]
        ];
    }


    /**
     * Holt alle Mails und Paperless verknüpfungen
     */
    public function getTimeLineContact()
    {
        try{
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);
            $salt = $this->dashboard->getHashSalt();
            $hash = $data['hash'] ?? '';

            // 2. Validierung
            if (!isset($data['hash'])) {
                return ['success' => false, 'error' => 'Kein Hash übergeben'];
            }

            $ret = $this->contactProcess->getTimelineData($hash, $salt, 6);
            
            $this->dashboard->sendResponse(true, $ret);

        } catch (\Throwable $e) {   
            $this->dashboard->sendResponse(false, "Fehler in " . __FUNCTION__. ":" . __LINE__ . ": ". $e->getMessage());
        }
    }

    /**
     * Verknüpft Mail mit Konakt
     */
    public function autoLinkContact() 
    {
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
    public function getContactContext($hash='') {
        $hash = trim($_REQUEST['hash'] ?? '');
        
        if (empty($hash)) {
            return ['success' => false, 'error' => 'Kein Mail-Hash übergeben.'];
        }
        
        //tbd: $hash nicht korrekt
        $contacts = $this->dashboard->getContactContext($hash); 

        return [
            'success' => true,
            'details' => $contacts
        ];
    }

    /**
     * 
     */
    public function insert() 
    {
        $data = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawInput = file_get_contents('php://input');
            $decoded = json_decode($rawInput, true);
            error_log(__FILE__.":".__LINE__."=> " . print_r($decoded, true));

            // Wenn $decoded ein Array ist, haben wir JSON empfangen
            if (is_array($decoded)) {
                $data = $decoded;
            } else {
                // Fallback auf FormData
                $data = $_POST;
            }
        }

        // 2. Daten sicher extrahieren (jetzt aus dem $data Array)
        $name  = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $type  = trim($data['type'] ?? 'company');
        $paperless_id  = trim($data['paperless_id'] ?? '??');

        // 3. Validierung
        if (empty($name) || empty($email)) {
            return ['success' => false, 'error' => 'Name und E-Mail dürfen nicht leer sein.'];
        }

        // 4. Kontakt speichern
        $contactId = $this->contactProcess->insertContact($name, $email, $type, $paperless_id);

        if ($contactId) {
            return [
                'success' => true,
                'details' => [
                    'message'    => 'Kontakt erfolgreich angelegt!',
                    'contact_id' => $contactId
                ]
            ];
        }

        return [
            'success' => false,
            'error'   => 'Fehler beim Erstellen des Kontakts in der Datenbank.'
        ];
    }

    /**
     * Speichert aus der View (namen und Email)
     */
    public function saveSmallContact()
    {
        try{
            $input = json_decode(file_get_contents('php://input'), true);
        
            // 2. Variablen sicher aus dem Array extrahieren
            // Wir nutzen das Null-Coalescing-Operator (??) für Standardwerte
            $id          = $input['id'] ?? null;
            $name        = $input['displayname'] ?? '';
            $email       = $input['email'] ?? '';
            $type        = $input['type'] ?? 'private';

            if (!$id) {
                throw new \Exception("Kontakt-ID fehlt.");
            }
            $this->contactProcess->insertContact($name, $email, $type);
            
            $this->dashboard->sendResponse(true, "Kontakt wurde aktualisiert");

        } catch (\Throwable $e) {   
            $this->dashboard->sendResponse(false, "Fehler in " . __FUNCTION__. ":" . __LINE__ . ": ". $e->getMessage());
        }
    }

    /**
     * Speichert den Kontakt aus dem Modal
     */
    public function saveContact()
    {
        try{
            
            $input = json_decode(file_get_contents('php://input'), true);
            $emails = $input['emails'];

            $contact_id = $input['id']; // Die ID des Hauptkontakts
            $emails_to_link = [];

            foreach ($input['emails'] as $email_entry) 
            {
                if (is_array($email_entry)) {
                    // Fall: Array mit ID und Email
                    $emails_to_link[] = $email_entry;
                } else {
                    // Fall: Nur String (hier nehmen wir id => null an)
                    $emails_to_link[] = ['ceid' => null, 'email' => $email_entry];
                }
            }
            
            $ret = $this->contactProcess->saveContactEmails($contact_id, $emails_to_link);
            
            return [
                'success' => true,
                'details'   => 'Kontakt wurde aktualisiert.'
            ];
        } catch (\Throwable $e) {   
            //throw new \Exception("Fehler in " . __FUNCTION__. ":" . __LINE__);
            $this->dashboard->sendResponse(false, "Fehler in " . __FUNCTION__. ":" . __LINE__ . ": ". $e->getMessage());            
        }
    }
}

