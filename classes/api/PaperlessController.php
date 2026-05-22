<?php
namespace App\Api;
use App\PaperlessProcessor; // Importiere den Prozessor
use Exception;

class PaperlessController extends BaseController {
    
    public function sync() {
        $hash = $_GET['hash'] ?? $_POST['hash'] ?? null;
        $id   = $_GET['id'] ?? $_POST['id'] ?? null;

        if ($hash !== null) {
            $syncResult = $this->mailProc->getSingleMail2Paperless($hash, $id);   
        } else {
            $syncResult = $this->mailProc->getMails2Paperless(2000);
        }

        $isSuccess = $syncResult['success'] ?? false;
        $statusType = $isSuccess ? 'success' : 'info_error';
        $errorMessage = !$isSuccess ? ($syncResult['error'] ?? 'Unbekannter Fehler beim Abgleich.') : null;

        $this->cleanBuffer();
        \App\Dashboard::sendResponse($isSuccess, $syncResult, $errorMessage, $statusType); 
        exit;
    }

    public function search() {
        $query = $_REQUEST['query'] ?? ''; 
        $date  = $_REQUEST['date'] ?? ''; 
        
        if (empty($query)) {
            throw new Exception("Kein Suchbegriff übergeben.");
        }

    
        $documents = $this->paperless->search($query, $date);

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
            throw new Exception("Fehlende Parameter für das Verknüpfen.");
        }

    
        $this->paperless->linkMailToPaperless($hash, $paperlessId);


        return [
            'success' => true,
            'details' => ['message' => 'Erfolgreich mit Paperless-Dokument verknüpft!']
        ];
    }

    public function getCorrespondentById() 
    {
        $query        = trim($_REQUEST['query'] ?? '');
        
        
        $correspondents = $this->paperless->searchForCorrespondet($query);
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
        $ret = $this->paperless->linkContact($contactid, $docId);

        return [
            'success' => true,
            'details' => $ret
        ];
    }

    public function getCorrespondents() 
    {
        $hash        = trim($_REQUEST['hash'] ?? '');
        $forceQuery  = trim($_REQUEST['force_query'] ?? '');
        $searchQuery = '';

        if (!empty($forceQuery)) {
            $searchQuery = $forceQuery;
        } elseif (!empty($hash)) {
            $email = $this->dashboard->getSenderByHash($hash);
            if ($email) {
                $searchQuery = substr(strrchr($email, "@"), 1) ?: $email;
            }
        }
        
        if (empty($searchQuery)) {
            return [
                'success' => false, 
                'error'   => 'Kein Suchbegriff ermittelbar (force_query und hash leer)'
            ];
        }

        $correspondents = $this->paperless->searchForCorrespondet($searchQuery);
        $finalDetails = is_array($correspondents) ? array_values($correspondents) : [];
        
        $this->cleanBuffer();
        \App\Dashboard::sendResponse(true, ['contacts' => $finalDetails]);
        exit;
    }

    public function getMailTree() 
    {
        $hash = $_GET['hash'] ?? '';


        if (empty($hash)) {
            throw new Exception("Kein gültiger Mail-Hash übergeben.");
        }

        $mailDetails = $this->mail->getMailByHash($hash); 
        if (empty($mailDetails)) {
            throw new Exception("Mail existiert nicht in der lokalen DB.");
        }
        
        $paperlessId = $this->paperless->getPaperlessIdByHash($hash);
        if (!$paperlessId) {
            throw new Exception("Diese E-Mail ist noch nicht mit Paperless verknüpft.");
        }

        
        $correspondentId = $this->paperless->getCorrespondentIdFromDocument($paperlessId);
        
        if (!$correspondentId) {
            throw new Exception("Kein Korrespondent für dieses Dokument in Paperless hinterlegt.");
        }

        $paperlessDocs = $this->paperless->fetchDocumentsByCorrespondentId($correspondentId);
        if (empty($paperlessDocs)) {
            throw new Exception("Keine Dokumente vom Paperless-Server zurückgegeben.");
        }

        // Dynamische Ermittlung der Basis-URL aus den Mandanteneinstellungen
        $settings = $this->dashboard->getTenantSettings($this->tenantId);
        $baseUrl  = rtrim($settings['paperless_url'] ?? 'http://192.168.0.72:8030', '/');

        $tree = [];
        $monthsDe = [
            '01' => 'Januar', '02' => 'Februar', '03' => 'März', '04' => 'April',
            '05' => 'Mai', '06' => 'Juni', '07' => 'Juli', '08' => 'August',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Dezember'
        ];

        foreach ($paperlessDocs as $doc) {
            $dateString = $doc['created'] ?? $doc['created_date'] ?? $doc['added'] ?? null;
            $timestamp  = $dateString ? strtotime($dateString) : time();

            $year      = date('Y', $timestamp);
            $monthNum  = date('m', $timestamp);
            $monthName = $monthsDe[$monthNum] ?? date('F', $timestamp);

            $docId = $doc['id'] ?? null;
            if (!$docId) continue;

            $tree[$year][$monthName][] = [
                'id'    => $docId,
                'title' => $doc['title'] ?? 'Unbenanntes Dokument',
                'link'  => $baseUrl . "/documents/" . $docId
            ];
        }

        krsort($tree);
        $this->cleanBuffer();
        \App\Dashboard::sendResponse(true, ['tree' => $tree]);
        exit;     
    }

    public function getTree() {
        $sender = $_GET['sender'] ?? '';
        if (empty($sender)) {
            throw new Exception("Kein Absender übergeben");
        }


        $paperlessDocs = $this->paperless->fetchDocumentsFromPaperless($sender); 

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