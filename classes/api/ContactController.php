<?php
namespace App\Api;
use App\ContactProcessor; // Importiere den Prozessor

class ContactController extends BaseController {

    public function __construct($dashboard) {
        // Übergibt das Dashboard an den BaseController, 
        // damit $this->dashboard dort gesetzt wird.
        parent::__construct($dashboard);
    }
    
    public function getContacts()
    {
        $query = trim($_REQUEST['query'] ?? '');
        //tbd: hashed

        if (empty($query)) {
            return ['success' => false, 'error' => 'Kein Mail-Hash übergeben.'];
        }
        

        $contacts = $this->contact->getContactList($query);


        return [
            'success' => true,
            'details' => ['contacts' => $contacts]
        ];
    }

    public function getEmailsByContact()
    {
        $query = trim($_REQUEST['query'] ?? '');
        //tbd: hashed

        if (empty($query)) {
            return ['success' => false, 'error' => 'Kein Mail-Hash übergeben.'];
        }
        

        $contacts = $this->contact->getEmailsByContact($query);


        return [
            'success' => true,
            'details' => ['contacts' => $contacts]
        ];
    }

    public function getContactById()
    {
        
    }

    public function getKorrespondentById()
    {

        
    }
    
    public function search() {
        $hash = trim($_REQUEST['hash'] ?? '');
        
        if (empty($hash)) {
            return ['success' => false, 'error' => 'Kein Mail-Hash übergeben.'];
        }
        
        //$email = $this->dashboard->getContactMailsBySelMail($hash); 
        $email = $this->dashboard->getContactByMail($hash); 

        return [
            'success' => true,
            'details' => ['contact' => $email]
        ];
        
        if (!$email) {
            return ['success' => false, 'error' => 'Absender zur Mail nicht gefunden.'];
        }
        
        $contactState = $this->dashboard->findContactByEmail($email);
        return [
            'success' => true,
            'details' => $contactState
        ];
    }


    public function getTimeLineContact()
    {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        // 2. JETZT mit $data aufrufen, nicht leer!
        $result = $this->contact->getContactTimeline($data); 
         return [
            'success' => true,
            'details' => $result
        ];
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
            $result = $this->contact->autoLinkContact($data); 
            return [
                'success' => true,
                'details' => $result
            ];
        }catch (Throwable $e) {   
            throw new \Exception("Fehler in " . __FUNCTION__. ":" . __LINE__);
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

        // 3. Validierung
        if (empty($name) || empty($email)) {
            return ['success' => false, 'error' => 'Name und E-Mail dürfen nicht leer sein.'];
        }

        // 4. Kontakt speichern
        $contactId = $this->dashboard->insertContact($name, $email, $type);

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
}