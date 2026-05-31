<?php
namespace App\Api;
use App\Services\PaperlessProcessor; // Importiere den Prozessor
use Exception;


class PaperlessController extends \App\Core\BaseController {
    
    public function sync() 
    {
        $hash = $_GET['hash'] ?? $_POST['hash'] ?? null;
        $id   = $_GET['id'] ?? $_POST['id'] ?? null;

        if ($hash !== null) {
            $syncResult = $this->mailProcessProc->getSingleMail2Paperless($hash, $id);   
        } else {
            $syncResult = $this->mailProcessProc->getMails2Paperless(2000);
        }

        $isSuccess = $syncResult['success'] ?? false;
        $statusType = $isSuccess ? 'success' : 'info_error';
        $errorMessage = !$isSuccess ? ($syncResult['error'] ?? 'Unbekannter Fehler beim Abgleich.') : null;

        $this->cleanBuffer();
        \App\Dashboard::sendResponse($isSuccess, $syncResult, $errorMessage, $statusType); 
        exit;
    }

    /**
     * Sucht in Paperless nach Dokumenten anhand von 
     * @param string $query
     * @param string $query
     */
    public function search() {
        $query = $_REQUEST['query'] ?? ''; 
        $date  = $_REQUEST['date'] ?? ''; 
        
        if (empty($query)) {
            throw new \Exception("Kein Suchbegriff übergeben.");
        }
    
        $documents = $this->paperlessProcess->search($query, $date);

        return [
            'success' => true,
            'details' => ['documents' => $documents]
        ];
    }

    /**
     * Verknüpft die Mail mit Paperless
     */
    public function linkMail() 
    {
        // JSON-Body lesen
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        $hash        = $data['hash'] ?? ''; // Achte auf den Key 'hash', nicht 'id'!
        $paperlessId = $data['paperless_id'] ?? '';

        error_log(print_r($data, true));

        if (empty($hash) || empty($paperlessId)) {
            throw new \Exception("Fehlende Parameter für das Verknüpfen.");
        }

    
        $this->paperlessProcess->linkMailToPaperless($hash, $paperlessId);


        return [
            'success' => true,
            'details' => ['message' => 'Erfolgreich mit Paperless-Dokument verknüpft!']
        ];
    }

    public function getCorrespondentById() 
    {
        $query        = trim($_REQUEST['query'] ?? '');
        
        $correspondents = $this->paperlessProcess->searchForCorrespondent($query);
        $finalDetails = is_array($correspondents) ? array_values($correspondents) : [];

        return [
            'success' => true,
            'details' => $finalDetails
        ];
        
    }

    public function linkContact()
    {
        $contactid        = trim($_POST['contact_id'] ?? '');
        $docId            = trim($_POST['item_id'] ?? '');
        $ret = $this->paperlessProcess->linkContact($contactid, $docId);

        return [
            'success' => true,
            'details' => $ret
        ];
    }

    /**
     * Hole alle Korrespondeten
     */
    public function getCorrespondents() 
    {
        error_log("getCorrespondents aufgerufen mit query: " . print_r($_REQUEST, true));
        $hash        = trim($_REQUEST['hash'] ?? '');
        $forceQuery  = trim($_REQUEST['force_query'] ?? '');
        $searchQuery = '';

        if (!empty($forceQuery) && empty($hash)) {
            error_log("Suche nach Korrespondenten mit force_query: $forceQuery");
            $searchQuery = $forceQuery;
        } elseif (!empty($hash)) {
            error_log("Suche nach Korrespondenten für Hash: $hash");
            $email = $this->paperlessProcess->getSenderByHash($hash);
            error_log("Gefundene E-Mail für Hash $hash: $email");

            if (is_array($email) && count($email) === 0) {
                error_log("Keine E-Mail für Hash $hash gefunden.");
            }else{ 
                error_log("E-Mail für Hash $hash: " . print_r($email, true));
                $searchQuery = substr(strrchr($email, "@"), 1) ?: $email;
            }
        }
        
        // Hole alle Kontakt, wenn Suche leer
        if (empty($searchQuery)) {
            error_log("Keine Sucheingabe, hole alle Korrespondenten");
            $correspondents = $this->paperlessProcess->getAllCorrespondents($searchQuery);
            return [
                'success' => true, 
                'details' => $correspondents
            ];

        }else{

            $correspondents = $this->paperlessProcess->searchForCorrespondent($searchQuery);
            $finalDetails = is_array($correspondents) ? array_values($correspondents) : [];

            return [
                'success' => true, 
                'details'   => ['contacts' => $finalDetails]
            ];

        }
    }

    /**
     * Holt die Mails und Korrespondeten zu einer mail
     */
    public function getMailTree() 
    {
        try{
            $hash = $_GET['hash'] ?? '';

            //Finde die Mail zu dem hash
            $mailDetails = $this->mailProcess->getMailByHash($hash); 

            //Kontakte ermitteln
            $contacts = $this->contactProcess->getEmailsAndContactsByHash($hash, $mailDetails["contact_id"]); 

            $paperlessCorres = $contacts[0]["paperless_id"];

            $ret = $this->paperlessProcess->getMailTree($hash, $paperlessCorres);

            $this->dashboard->sendResponse(true, $ret);
        } catch (\Throwable $e) {   
            $this->dashboard->sendResponse(false, "Fehler in " . __FUNCTION__. ":" . __LINE__ . ": ". $e->getMessage());
        }
        
        //return $ret;    
    }


    /**
     * obsolete
     */
    public function getTree() {
        $sender = $_GET['sender'] ?? '';
        if (empty($sender)) {
            throw new \Exception("Kein Absender übergeben");
        }

        $paperlessDocs = $this->paperlessProcess->fetchDocumentsFromPaperless($sender); 

        // Auch hier die URL dynamisch beziehen
        $settings = $this->dashboard->getTenantSettings($this->tenantId);
        $baseUrl  = rtrim($settings['paperless_url'] ?? (defined('PAPERLESS_API_URL') ? PAPERLESS_API_URL : ''), '/');

        $tree = [];
        $monate = [
            1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 
            5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
        ];

        foreach ($paperlessDocs as $doc) {
            $timestamp = strtotime($doc['created']);
            $year      = date('Y', $timestamp);
            $monthNum  = (int)date('n', $timestamp);
            $monthName = $monate[$monthNum];

            $tree[$year][$monthName][] = [
                'id'    => $doc['id'],
                'title' => $doc['title'],
                'link'  => $baseUrl . "/documents/" . $doc['id']
            ];
        }

        krsort($tree);
        return [
            'success' => true,
            'details' => ['tree' => $tree]
        ];
    }
}