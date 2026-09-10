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
use App\Services\PaperlessProcessor; // Importiere den Prozessor
use Exception;

class PaperlessController extends \App\Core\BaseController {
    
    public function __construct($dashboard)
    {
        parent::__construct($dashboard); 
    }

    public function getPaperlessInfos() {
        return $this->process($this->paperlessProcess, 'getPaperlessInfos', 'paperless_info', '');
    }
    /**
     * Sucht in Paperless nach Dokumenten anhand von 
     * @param string $query
     * @param string $query
     */
    public function search() {
        $query = $this->request['query'] ?? null; 
        $date  = $this->request['date'] ?? null; 
        
        if (empty($query)) {
            throw new \Exception("Kein Suchbegriff übergeben.");
        }
    
        $documents = $this->paperlessProcess->search($query, $date);
        return ['documents' => $documents];
    }
    /**
     * Verknüpft die Mail mit Paperless
     */
    public function linkMail() 
    {
        $hash        = $this->request['hash'] ?? null; // Achte auf den Key 'hash', nicht 'id'!
        $paperlessId = $this->request['paperless_id'] ?? null;
        if (empty($hash) || empty($paperlessId)) {
            throw new \Exception("Fehlende Parameter für das Verknüpfen der E-Mail.");
        }
    
        $ret = $this->paperlessProcess->linkMailToPaperless($hash, $paperlessId);
        return [
            "paperless"=> $ret,
            "ui" => [
                'notify'  => true,
                'title'   => 'Suche',
                'message' => 'Erfolgreich mit Paperless-Dokument verknüpft!',
                'type'    => 'info'
            ]
        ];
    }

    public function getTotalDocumentCount()
    {
        return $this->process($this->paperlessProcess, 'getTotalDocumentCount', 'c_docs', '');
    }
    
    public function getLastDocuments()
    {
        return $this->process($this->paperlessProcess, 'getLastDocuments', 'last_docs', '');
    }

    public function getLastUnclassifiedDocuments()
    {
        return $this->process($this->paperlessProcess, 'getLastUnclassifiedDocuments', 'last_docs', '');
    }

    public function getCorrespondentById() 
    {
        
        // Hier wird jetzt korrekterweise die ID (als Int casten!) übergeben
        return $this->process($this->paperlessProcess, 'getCorrespondentById', 'activeCorres');
    }

    public function getCorrespondentByContact() 
    {
        
        // Hier wird jetzt korrekterweise die ID (als Int casten!) übergeben
        return $this->process($this->paperlessProcess, 'getCorrespondentByContact', 'activeCorres');
    }

    public function searchCorrespondents() 
    {
        $query = $this->request['query'] ?? null;
        
        // Die Logik, wie Daten formatiert werden, kann im Service oder hier liegen
        $results = $this->paperlessProcess->searchForCorrespondent($query);
        
        return ['correspondents' => $results];
    }

    public function getTagsBySearch()
    {
        return $this->process($this->paperlessProcess, 'getTagsBySearch', 'tags', '');
    }

    public function getTagsByContact()
    {
        return $this->process($this->paperlessProcess, 'getTagsByContact', 'tags', '');
    }

    public function updateTagToContact()
    {
        return $this->process($this->paperlessProcess, 'updateTagToContact', 'tagContact','');
    }


    /**
     * Verbindet Kontakt mit PaperlessKorrespondent
     */
    public function linkContact()
    {
        return $this->process($this->paperlessProcess, 'linkContact', 'match', 'YIPPI');
        $contactid        = $this->request['contactId'] ?? null;
        $correspondentId  = $this->request['correspondentId'] ?? null;
        
        $ret = $this->paperlessProcess->linkContact($contactid, $correspondentId);
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
     * Hole alle Korrespondeten
     */
    public function getCorrespondents() 
    {
        $query = $this->request['query'];
        $forceQuery = $this->request['forceQuery'];
        error_log("getCorrespondents aufgerufen mit query: " . print_r($_REQUEST, true));
        
        if (!empty($forceQuery) && empty($query)) {
            error_log("Suche nach Korrespondenten mit force_query: $forceQuery");
            $searchQuery = $forceQuery;
        }
        
        // Hole alle Kontakt, wenn Suche leer
        if (empty($query)) {
            error_log("Keine Sucheingabe, hole alle Korrespondenten");
            $correspondents = $this->paperlessProcess->getAllCorrespondents($query);
            
            return ['correspondents' => $correspondents];
        }else{
            $correspondents = $this->paperlessProcess->searchForCorrespondent($query);
            $finalDetails = is_array($correspondents) ? array_values($correspondents) : [];
            return ['correspondents' => $finalDetails];
        }
    }

    /**
     * @deprecated
     * Holt die Mails und Korrespondeten zu einer mail
     */
    public function getMailTree() 
    {
        $hash = $this->request['hash'] ?? null;
        //Finde die Mail zu dem hash
        $mailDetails = $this->mailProcess->getMailByHash($hash); 
        //Kontakte ermitteln
        $contacts = $this->contactProcess->getEmailsAndContactsByHash($hash, $mailDetails["contact_id"]); 
        $paperlessCorres = $contacts[0]["paperless_id"];
        $ret = $this->paperlessProcess->getMailTree($hash, $paperlessCorres);
        return [$ret];
        
    }
    /**
     * @deprecated
     * Erstellt den Baum in ganz schön!
     */
    public function getTree() 
    {
        
        $sender = $this->request['sender'] ?? null;
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
        return ['tree' => $tree];
    }
}