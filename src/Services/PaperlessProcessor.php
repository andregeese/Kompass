<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

namespace App\Services;
use App\Services\SystemProcessor;
use Exception;
use Throwable;
/**
 * @property \App\Services\PaperlessProcessor $paperlessProcess
 */
class PaperlessProcessor extends \App\Core\Database
{
    use \App\Core\ServiceTrait;
    private string $apiUrl;
    private string $token;
    protected $dashboard;

    public function __construct($dashboard) 
    {
        parent::__construct($dashboard);     
        $this->dashboard = $dashboard;
        
        $this->apiUrl = $this->systemProcess->getRequiredEnv('PAPERLESS_URL');
        $this->token = $this->systemProcess->getRequiredEnv('PAPERLESS_TOKEN');
    }
    /**
     * Zentraler cURL-Handler für alle Paperless-API-Requests
     */
    private function request(string $endpoint, string $method = 'GET', array $postData = []): string
    {
        $url = $this->apiUrl . $endpoint;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Token " . $this->token
        ]);
        
        // Falls POST-Daten (z.B. beim Upload) übergeben werden
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        error_log("PaperlessURL:" . $url);
        // Globale Fehlerprüfung für alle Endpunkte
        if ($httpCode === 0) {
            throw new \Exception("Paperless-Server ist offline oder nicht erreichbar. (cURL: $curlError)");
        }
        if ($httpCode === 401 || $httpCode === 403) {
            throw new \Exception("Zugriff verweigert. Der Paperless API-Token ist ungültig.");
        }
        
        // Je nach Methode erwarten wir andere Erfolgs-Codes (GET: 200, POST: 201)
        $expectedCode = ($method === 'POST') ? 201 : 200;
        if ($httpCode !== $expectedCode) {
            // Versuche die Fehlermeldung von Paperless zu extrahieren, falls vorhanden
            $apiError = !empty($result) ? $result : 'Keine Antwortdetails';
            throw new \Exception("Paperless-API meldet Status $httpCode. Details: " . $apiError);
        }
    
        return $result;
    }

    public function getCorrespondentByContact($cid)
    {
        $sql = "SELECT correspondent_id FROM contact_paperless_map WHERE tenant_id=:tenant_id AND contact_id=:cid;";
        $params = [
            'cid' => $cid
        ];

        $dbResult = $this->queryIt($sql, $params, false, false, false);

        if (!$dbResult->success){
            throw new Exception("Fehler beim Abrufen der Korrespondenten");
        }
    

        $corrIds = array_column($dbResult->data->fetched, 'correspondent_id');

        // 2. Falls keine IDs gefunden wurden, direkt abbrechen (spart API-Call)
        if (empty($corrIds)) {
            return [];
        }

        //jetzt den namen von correspondent in api finden
        $b = $this->getCorrespondentsById($corrIds);

        return $b;

    }

    public function getPaperlessIdsByMailID(string $id): array
    {
        error_log(__FUNCTION__);
        
        $sql = "SELECT 
                GROUP_CONCAT(DISTINCT m.paperless_id) as dokumente,
                GROUP_CONCAT(DISTINCT cpm.correspondent_id) AS korrespondenten
                -- CASE 
                --     WHEN m.paperless_id IS NOT NULL THEN 'linked' 
                --     ELSE 'standalone' 
                -- END AS relation_type
            FROM mails AS m
            -- 1. Von Mail zu der E-Mail-Adresse, von der sie kam
            INNER JOIN contact_emails ce ON m.sender_ceid = ce.id
            -- 2. Von der E-Mail-Adresse zum Kontakt
            INNER JOIN contacts c ON ce.contact_id = c.id
            -- 3. Vom Kontakt zu den Paperless-Mappings
            LEFT JOIN contact_paperless_map cpm ON c.id = cpm.contact_id
            WHERE m.id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED) AND m.tenant_id=:tenant_id;";

        $dbResult = $this->queryIt($sql, ['id' => $id], false, false, true);
        
        // 1. Prüfen, ob überhaupt ein Ergebnis da ist
        if (!empty($dbResult->data) && is_array($dbResult->data->fetched)) {
            // Da GROUP_CONCAT nur eine Zeile liefert, nehmen wir den ersten Index [0]
            $row = $dbResult->data->fetched[0];
            // Filtert leere Strings (falls nur NULL vorhanden war) und gibt ein sauberes Array
            $resAr['dokumente'] = array_filter(explode(',', $row['dokumente'] ?? ''));
            $resAr['korrespondenten'] = array_filter(explode(',', $row['korrespondenten'] ?? ''));
        } else {
            $resAr['dokumente'] = [];
            $resAr['korrespondenten'] = [];
        }
        #error_log(print_r($resAr, true));
        // Wandle das Ergebnis-Array in ein flaches Array von IDs um
        return $resAr;
    }

    public function getLastDocuments()
    {
        $fourWeeksAgo = date('Y-m-d', strtotime('-4 weeks'));
        $params = [
            'query'    => 'tag:unclassified', 
            'ordering' => '-created',
            'created__gte' => $fourWeeksAgo,
            'page_size'    => 200 // Groß genug, um die 4 Wochen abzudecken
        ];

        $docResponse = $this->request('/api/documents/'.'?' . http_build_query($params));
        $docData = json_decode($docResponse, true);
        $documents = $docData['results'] ?? [];

        if (empty($documents)) {
            return []; // Keine Dokumente in den letzten 4 Wochen
        }

        // 3. Korrespondenten-IDs aus den Dokumenten extrahieren und zählen
        $correspondentIds = [];
        foreach ($documents as $doc) {
            if (!empty($doc['correspondent'])) {
                $correspondentIds[] = $doc['correspondent'];
            }
        }

        // Zählt wie oft jede ID vorkommt (z.B. [ID 12 => 5, ID 4 => 2])
        $countedIds = array_count_values($correspondentIds);
        
        // Sortieren: Höchste Anzahl zuerst
        arsort($countedIds);
        
        // Die Top 5 IDs abschneiden
        $top5Ids = array_slice($countedIds, 0, 5, true);

        if (empty($top5Ids)) {
            return [];
        }

        // 4. Alle Korrespondenten-Namen EINMALIG abrufen (statt 5 Einzel-Requests)
        $corrResponse = $this->request('/api/correspondents/?page_size=1000');
        $corrData = json_decode($corrResponse, true);
        $allCorrespondents = $corrData['results'] ?? [];

        // Eine Map bauen: [ID => Name]
        $correspondentMap = [];
        foreach ($allCorrespondents as $c) {
            $correspondentMap[$c['id']] = $c['name'];
        }

        // 5. Endergebnis für dein Dashboard zusammenbauen
        $finalTop5 = [];
        foreach ($top5Ids as $id => $count) {
            $finalTop5[] = [
                'id'    => $id,
                'name'  => $correspondentMap[$id] ?? 'Unbekannter Absender',
                'count' => $count
            ];
        }

        // Falls deine API-Basisklasse das Format nicht automatisch anhängt, hier mit angeben
        $url = '/api/documents/' . '?' . http_build_query($params);
        
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ungültiges JSON von Paperless erhalten.");
        }

        $results = $paperlessData['results'] ?? [];

        return $results;
    }

    private function getCorrespondentsById(array $ids):array
    {
        if (empty($ids))
        {
            return [];
        }
        $idString = implode(',', $ids);
        
        $params = [
            'id__in'   => $idString
        ];
        // Falls deine API-Basisklasse das Format nicht automatisch anhängt, hier mit angeben
        $url = '/api/correspondents/?' . http_build_query($params);
        
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ungültiges JSON von Paperless erhalten.");
        }
        $results = $paperlessData['results'] ?? [];

        // Paperless liefert die Liste im 'results'-Key
        $results = $paperlessData['results'] ?? ["LEER"];
        $formatted = [];
        foreach ($results as $key => $item) 
        {
            if (empty($item['id'])) continue;
            if (!isset($item['id']) || !isset($item['name'])) {
                continue;
            }
            $formatted[] = [
                'id'    => (int)$item['id'],
                'name'  => (string)$item['name']
            ];
        }
        
        return $formatted;
    }

    public function getAllCorrespondentsBy()
    {
        $params = [
            'page_size' => 5000 // Holt bis zu 5000 Korrespondenten auf einmal
        ];
        $url = '/api/correspondents/?' . http_build_query($params);
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        //error_log(print_r($paperlessData, true));
        $correspondents = [];
        // Paperless liefert die Daten bei Pagination im Unterknoten 'results'
        if (isset($paperlessData['results'])) {
            foreach ($paperlessData['results'] as $item) {
                //error_log(print_r($item, true));
                $correspondents[] = [
                    'id'   => $item['id'],
                    'name' => $item['name']
                ];
            }
        }
        $this->dashboard->sendResponse(true, $correspondents);
        //return $correspondents;
    }

    
    public function getAllCorrespondents()
    {
        $params = [
            'page_size' => 5000 // Holt bis zu 5000 Korrespondenten auf einmal
        ];
        $url = '/api/correspondents/?' . http_build_query($params);
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        //error_log(print_r($paperlessData, true));
        $correspondents = [];
        // Paperless liefert die Daten bei Pagination im Unterknoten 'results'
        if (isset($paperlessData['results'])) {
            foreach ($paperlessData['results'] as $item) {
                //error_log(print_r($item, true));
                $correspondents[] = [
                    'id'   => $item['id'],
                    'name' => $item['name']
                ];
            }
        }
        $this->dashboard->sendResponse(true, $correspondents);
        //return $correspondents;
    }

    /**
     * Holt den Kontakt direkt
     */
    public function searchForCorrespondentById(int $id):array {
        
        $url = '/api/correspondents/' . $id . '/';
        
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ungültiges JSON von Paperless erhalten.");
        }
        error_log($url . ": " . print_r($jsonResponse, true));
        
        $results = $paperlessData['results'] ?? [];

        return $results;
    }

    /**
     * Durchsucht Paperless über die native Such-API nach passenden Korrespondenten inkl. Score Rating.
     * * @param string $query Der Suchbegriff (z.B. die Domain 'mueller.de')
     * @return array Liste der eindeutigen Korrespondenten, sortiert nach dem höchsten Score
     */
    public function searchForCorrespondent(string $query): array {
        $query = trim($query);
        
        $params = [
            'name__icontains'   => $query
        ];
        // Falls deine API-Basisklasse das Format nicht automatisch anhängt, hier mit angeben
        $url = '/api/correspondents/?' . http_build_query($params);
        
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ungültiges JSON von Paperless erhalten.");
        }
        $results = $paperlessData['results'] ?? [];
        error_log(print_r($results, true));

        // Paperless liefert die Liste im 'results'-Key
        $results = $paperlessData['results'] ?? ["LEER"];
        $formatted = [];
        foreach ($results as $key => $item) 
        {
            if (empty($item['id'])) continue;
            if (!isset($item['id']) || !isset($item['name'])) {
                continue;
            }
            $formatted[] = [
                'id'    => (int)$item['id'],
                'name'  => (string)$item['name'],
                'score' => 1.0 
            ];
        }
        
        return $formatted;
    }
    /**
     * Holt den bereinigten Absender (reine E-Mail-Adresse) einer E-Mail anhand des Mail-Hashes.
     * * @param string $hash Der SHA-256 Hash der Mail-ID + Salt
     * @return string|null Die isolierte E-Mail-Adresse (z.B. 'hans@mueller.de') oder null
     */
    public function getSenderByHash(string $hash): ?string 
    {
        //$sql = "SELECT sender FROM mails WHERE tenant_id=:tenant_id AND id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED) LIMIT 1";
        $sql = "SELECT sender FROM mails as m
                LEFT JOIN contact_emails as ce ON ce.contact_id=m.sender_ceid
                WHERE tenant_id=:tenant_id AND id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED) LIMIT 1";
        
        $rows = $this->queryIt($sql, [
            'salt' => $this->dashboard->getHashSalt(),
            'hash' => $hash
        ]);
        
        if (empty($rows) || !isset($rows[0]['sender'])) {
            return null;
        }
        error_log("Rohdaten aus DB für Hash $hash: " . print_r($rows[0]['sender'], true));
        $rawSender = $rows[0]['sender'];
        // Regex: Filtert die E-Mail-Adresse heraus, falls Name + <mail@domain.de> drin steht
        if (preg_match('/<([^>]+)>/', $rawSender, $matches)) {
            return strtolower(trim($matches[1]));
        }
        // Falls nur die reine E-Mail-Adresse im Feld stand
        return strtolower(trim($rawSender));
    }
    /**
     * Durchsucht die Paperless-Instanz nach einem Suchbegriff, mit optionaler Datumseins- oder -eingrenzung. Dokumente, deren Erstelldatum exakt mit dem Mail-Datum übereinstimmt, erhalten eine höhere Gewichtung für die Sortierung und werden im Frontend hervorgehoben. Das ermöglicht es, relevante Dokumente schneller zu erkennen, selbst wenn die Volltextsuche nicht perfekt ist.
      * @param string $query Der Suchbegriff für Titel und Inhalt
      * @param string|null $mailDate Optionales Datum der Mail (z.B. "2024-06-15"), um die Suche auf Dokumente mit diesem Erstelldatum zu fokussieren
      * @return array Eine Liste von gefundenen Dokumenten mit ID, Titel, Erstelldatum, Link und einem Flag für Datumstreffer
     */
    public function search(string $query, mixed $mailDate = ''): array
    {
        $params = [
            'query'     => $query,
            'sort'      => '-created',
            'page'      => '1',
            'page_size' => '30' // Begrenzung direkt an der API schont den RAM
        ];
        $targetDate = !empty($mailDate) ? date('Y-m-d', strtotime($mailDate)) : null;
        if ($targetDate) {
            $params['created__date__gte'] = date('Y-m-d', strtotime($mailDate . ' -1 days'));
            $params['created__date__lte'] = date('Y-m-d', strtotime($mailDate . ' +1 days'));
        }
        
        $url = '/api/documents/' . '?' . http_build_query($params);
        //$url = '/api/documents/?' . http_build_query($params);
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ungültiges JSON von Paperless erhalten.");
        }
        $foundDocuments = [];
        
        if (!empty($paperlessData['results']) && is_array($paperlessData['results'])) {
            //$foundDocuments["results"] = $paperlessData['results'];
            foreach ($paperlessData['results'] as $doc) {
                if (empty($doc['id'])) continue;
                $docCreatedRaw = $doc['created'] ?? '';
                $docCreatedFormated = !empty($docCreatedRaw) ? date('Y-m-d', strtotime($docCreatedRaw)) : '';
                
                $isDateMatch = ($targetDate && $docCreatedFormated === $targetDate);
                
                // Wir holen den echten Such-Score der Paperless-Engine (Standard: 1.0, falls nicht gesetzt)
                $nativeScore = isset($doc['__search_hit__']['score']) ? (float)$doc['__search_hit__']['score'] : 1.0;
                // 💡 Strategie-Tuning: Ein exakter Datumstreffer multipliziert den Relevanz-Score massiv!
                $finalScore = $isDateMatch ? ($nativeScore * 10) : $nativeScore;
                $foundDocuments[] = [
                    'id'            => (int)$doc['id'],
                    'title'         => (string)($doc['title'] ?? 'Unbekannt'),
                    'created'       => !empty($docCreatedRaw) ? date('d.m.Y H:i:s', strtotime($docCreatedRaw)) : date('d.m.Y H:i:s'),
                    'link'          => $this->apiUrl . '/documents/' . $doc['id'],
                    'is_date_match' => $isDateMatch,
                    'originscore'   => $nativeScore,
                    'score'         => $finalScore
                ];
            }
        }
        if (empty($foundDocuments)){
            error_log(__FUNCTION__.": keine Dokumente gefunden!");
        }
        // Fallback auf die nackte 'all' Liste, falls die Volltextsuche 0 Ergebnisse lieferte
        if (empty($foundDocuments) && !empty($paperlessData['all']) && is_array($paperlessData['all'])) {
            foreach (array_slice($paperlessData['all'], 0, 25) as $docId) { // Auch hier direkt slicen!
                if (!is_numeric($docId)) continue;
                $foundDocuments[] = [
                    'id'            => (int)$docId,
                    'title'         => 'Paperless Dokument #' . $docId,
                    'created'       => date('d.m.Y'), 
                    'link'          => $this->apiUrl . '/documents/' . $docId,
                    'is_date_match' => false,
                    'score'         => 0.0
                ];
            }
        }
        if (is_array($foundDocuments && count($foundDocuments)>0)){
            // Sortierung nach berechnetem Kombi-Score DESC
            usort($foundDocuments, function($a, $b) {
                return $b['originscore'] <=> $a['originscore'];
            });
        }
        
        return $foundDocuments;
    }
    public function getDocumentById($docs)
    {
        // Fall A: Mehrere IDs (Batch-Abfrage über Filter)
        if (is_array($docs) && !empty($docs)) {
            $params = [
                'id__in'    => implode(',', $docs),
                'page'      => '1',
                'page_size' => count($docs)
            ];
            
            $url = '/api/documents/?' . http_build_query($params);
        } 
        // Fall B: Einzelne ID (Direkt-Abfrage)
        else {
            $url = '/api/documents/?id=' . $docs;
        } 
        $foundDocuments=[];
        $jsonResponse = $this->request($url); //TBD
        $paperlessData = json_decode($jsonResponse, true);
        // Fallback auf die nackte 'all' Liste, falls die Volltextsuche 0 Ergebnisse lieferte
        if (!empty($paperlessData['results'])) {
            foreach ($paperlessData['results'] as $_res) { 
                error_log("DOC:" . print_r($_res,true));// Auch hier direkt slicen!
                $foundDocuments[] = [
                    'id'            => (int)$_res['id'] ?? null,
                    'title'         => 'Paperless Dokument #' . $_res['title'] ?? '',
                    'created'       => $_res['created'] ?? '', 
                    'link'          => $this->apiUrl . '/documents/' . $_res['docId'],
                    'is_date_match' => false,
                    'score'         => null
                ];
            }
        }else{
            error_log("typeof paperlessData: " . print_r($paperlessData));
        }
        error_log("## EOF" . __FUNCTION__. "##");
        return $foundDocuments;
    }
    /**
     * Spezielle Suche NUR im Titel, um die Trefferqualität zu erhöhen. Optional mit Datumseins- oder -eingrenzung. Alle gefundenen Dokumente werden zurückgegeben, aber solche mit exakt passendem Erstelldatum erhalten ein Flag für die Hervorhebung im Frontend.
     * @param string $subject Der Suchbegriff für den Titel
     * @param string $mailDate Optionales Datum der Mail (z.B. "2024-06-15"), um die Suche auf Dokumente mit diesem Erstelldatum zu fokussieren
     * @return array Eine Liste von gefundenen Dokumenten mit ID, Titel, Erstelldatum, Link und einem Flag für Datumstreffer
     */
    public function searchBySubject(string $subject, string $mailDate = '', &$log = array()): array
    {
        // Spezielle Suche NUR im Titel
        $params = [
            //'title__icontains' => $subject,
            'title__iexact' => $subject,
            'page' => '1',
            'page_size' => '30'
        ];
        $targetDate = null;
        if (!empty($mailDate)) {
            $targetDate = date('Y-m-d', strtotime($mailDate));
            $params['created__date__gte'] = date('Y-m-d', strtotime($mailDate . ' -7 days'));
            $params['created__date__lte'] = date('Y-m-d', strtotime($mailDate . ' +2 days'));
        }
        $url = '/api/documents/?' . http_build_query($params);
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        // 💡 Diese Logs landen jetzt durch das `&$log` im Funktionskopf direkt im Haupt-Log!
        $log[] = "<small style='color:gray;'>DEBUG Paperless-URL: " . htmlspecialchars($url) . "</small><br>";
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ungültiges JSON von Paperless erhalten.");
        }
        $foundDocuments = [];
        if (!empty($paperlessData['results']) && is_array($paperlessData['results'])) {
            
            foreach ($paperlessData['results'] as $doc) {
                if (empty($doc['id'])) continue;
                $docCreatedRaw = $doc['created'] ?? '';
                $docCreatedFormated = !empty($docCreatedRaw) ? date('Y-m-d', strtotime($docCreatedRaw)) : '';
                
                $isDateMatch = ($targetDate && $docCreatedFormated === $targetDate);
                // 💡 FIX: Die Dokumente müssen dem Rückgabe-Array auch HINZUGEFÜGT werden!
                $foundDocuments[] = [
                    'id' => $doc['id'],
                    'title' => $doc['title'] ?? 'Unbenannt',
                    'created' => $docCreatedFormated,
                    'is_date_match' => $isDateMatch,
                    // Falls du die Web-URL zu Paperless mitsenden willst (anpassen falls nötig):
                    'link' => '/document/' . $doc['id'] 
                ];
            }
        }
        $log[] = "<small style='color:gray;'>DEBUG Trefferanzahl in Paperless: " . count($foundDocuments) . "</small><br>";
        return $foundDocuments;
    
    }
    public function fetchDocumentsFromPaperless(string $senderName): array
    {
        // Nutzt deinen funktionierenden Query-Parameter aus dem Screenshot
        $url = "/api/documents/?correspondent__name__iexact=" . urlencode($senderName);
        // 1. Wir holen die Dokumente. Die API liefert standardmäßig bereits alle Details im 'results'-Array!
        // Falls deine Paperless-Instanz sehr viele Treffer hat, limitieren wir die API direkt auf max 50 Ergebnisse.
        $url = "/api/documents/?correspondent__name__iexact=" . urlencode($senderName) . "&page_size=50";
        
        $RAWResponse = $this->request($url);
        $result = json_decode($RAWResponse, true);
        if (!is_array($result) || empty($result['results'])) {
            return [];
        }
        $documentsDetails = $result['results'];
        // 2. Sortierung nach Score DESC im Arbeitsspeicher (Keine weiteren DB- oder API-Abfragen!)
        usort($documentsDetails, function ($a, $b) {
            $scoreA = isset($a['__search_hit__']['score']) ? (float)$a['__search_hit__']['score'] : 0.0;
            $scoreB = isset($b['__search_hit__']['score']) ? (float)$b['__search_hit__']['score'] : 0.0;
            
            if ($scoreA === $scoreB) {
                // Falls kein Such-Score da ist (oder beide gleich sind), sortieren wir nach Erstellungsdatum neuere zuerst
                $dateA = isset($a['created']) ? strtotime($a['created']) : 0;
                $dateB = isset($b['created']) ? strtotime($b['created']) : 0;
                return $dateB <=> $dateA; 
            }
            
            // Höchster Such-Score nach oben
            return $scoreB <=> $scoreA;
        });
        // 3. Erst NACH der Sortierung schneiden wir die besten 25 ab
        if (count($documentsDetails) > 25) {
            $documentsDetails = array_slice($documentsDetails, 0, 25);
        }
        // Optional: Den Score für das Frontend flach injizieren
        foreach ($documentsDetails as &$doc) {
            $doc['search_score'] = isset($doc['__search_hit__']['score']) ? (float)$doc['__search_hit__']['score'] : 0.0;
        }
        unset($doc); // Referenz brechen
        return $documentsDetails;
    }
    /**
     * Holt ein einzelnes Dokument anhand seiner ID aus Paperless, um den Korrespondenten-ID zu erfahren
     */
    public function getCorrespondentIdFromDocument($documentId)
    {
        $url = '/api/documents/' . intval($documentId) . '/';
        
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        
        // Falls das Dokument existiert und einen Korrespondenten hat
        if ($jsonResponse && isset($paperlessData['correspondent'])) {
            return $paperlessData['correspondent']; // Gibt die numerische ID des Korrespondenten zurück
        }
        return false;
    }
    /**
     * Holt alle Dokumente eines Korrespondenten aus Paperless anhand seiner numerischen ID
     */
    public function fetchDocumentsByCorrespondentId($correspondentId=null)
    {            
        if (!$correspondentId) {
            return false;
        }
        $idString = is_array($correspondentId) 
        ? implode(',', array_map('intval', $correspondentId)) 
        : intval($correspondentId);

        $url = "/api/documents/?correspondent__id__in=" . $idString . "&page_size=500&tag=132";

        //$url = "/api/documents/?correspondent__id__in=" . intval($correspondentId) . "&page_size=10";
        $RAWResponse = $this->request($url);
        $result = json_decode($RAWResponse, true);
        
        if (!is_array($result) || empty($result['results'])) {
            return [];
        }
        // Falls du den Count im Log behalten willst:
        //error_log(__FUNCTION__ . ": Anzahl Dokumente in Paperless: ##" . ($result['count'] ?? 0) ."##");
        //error_log(print_r($result['results'], true));
        // Da 'results' bereits alle Details enthält, können wir es direkt zurückgeben!
        return $result['results'];
    
    }
    
    /**
     * Sendet eine E-Mail als Dokument zu Paperless
     */
    public function uploadMail(array $mail, string $decryptedSubject, string $decryptedBody): int
    {
        $tempFile = sys_get_temp_dir() . '/mail_' . uniqid() . '.html';
        
        $htmlContent = "<html><head><meta charset='utf-8'><style>body{font-family:sans-serif;padding:20px;} .meta{margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid #ccc;}</style></head><body>";
        $htmlContent .= "<div class='meta'><strong>Von:</strong> " . htmlspecialchars($mail['sender'], ENT_QUOTES, 'UTF-8') . "<br>";
        $htmlContent .= "<strong>Datum:</strong> " . $mail['date'] . "<br>";
        $htmlContent .= "<strong>Betreff:</strong> " . htmlspecialchars($decryptedSubject, ENT_QUOTES, 'UTF-8') . "</div>";
        $htmlContent .= $decryptedBody;
        $htmlContent .= "</body></html>";
        file_put_contents($tempFile, $htmlContent);
        $formattedDate = date('Y-m-d', strtotime($mail['date']));
        $safeSubject = preg_replace('/[^A-Za-z0-9_\-\s]/', '', $decryptedSubject); 
        $uploadName = $formattedDate . " - " . $safeSubject . ".html";
        $cfile = curl_file_create($tempFile, 'text/html', $uploadName);
        
        $postData = [
            'document' => $cfile,
            'title'    => $decryptedSubject,
        ];
        try {
            // Auch hier: Übergabe an den zentralen Request-Handler!
            $jsonResponse = $this->request('/api/documents/post_document/', 'POST', $postData);
        } finally {
            // Unlink MUSS im finally-Block stehen, damit die Temp-Datei AUCH bei Fehlern gelöscht wird
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        $responseData = json_decode($jsonResponse, true);
        return (int)($responseData['document_id'] ?? $responseData['id'] ?? 0);
    }


    /**
     * Verknüpft eine Mail mit einem Paperless-Dokument und setzt den Status auf archived.
     * @param string $hash Der SHA-256 Hash der Mail-ID + Salt
     * @param int $paperlessId Die ID des zugeordneten Paperless-Dok
     * @return void
     */
    public function linkMailToPaperless(string $hash, int $paperlessId): void 
    {
        $sql = "UPDATE mails 
                SET status = 'archived', paperless_id = :p_id 
                WHERE tenant_id=:tenant_id AND id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)";
                
        $this->queryIt($sql, [
            'p_id' => $paperlessId,
            'salt' => $this->dashboard->getHashSalt(),
            'hash' => $hash
        ]);
    }

    public function getTagsBySearch(string $query)
    {
        $params = [
            'name__icontains'   => $query, 
            'ordering'          => 'name',
            'page_size'         => 20
            //'fields'            => 'id,name' // Groß genug, um die 4 Wochen abzudecken
        ];

        $tagResponse = $this->request('/api/tags/?' . http_build_query($params));
        $result = json_decode($tagResponse, true);

        return $result['results'];
    }

    /**
     * Holt alle Tags aus der DB
     */
    public function getTagsByContact($cid)
    {
        $sql = "SELECT tag_id 
                  FROM contact_paperless_map 
                 WHERE tenant_id=:tenant_id 
                   AND contact_id=:cid;";
        $params = [
            'cid' => $cid
        ];

        $dbResult = $this->queryIt($sql, $params, false, false, false);

        if (!$dbResult->success){
            throw new Exception("Fehler beim Abrufen der Tags");
        }
    

        $corrIds = array_column($dbResult->data->fetched, 'correspondent_id');

        // 2. Falls keine IDs gefunden wurden, direkt abbrechen (spart API-Call)
        if (empty($corrIds)) {
            return [];
        }

        //jetzt den namen von correspondent in api finden
        $b = $this->getTagsById($corrIds);

        return $b;

    }

    //holt den Namen des Tag/der Tags aus Paperless
    private function getTagsById(string | array $ids)
    {
        if (empty($ids))
        {
            return [];
        }
        $idString = implode(',', $ids);
        
        $params = [
            'id__in'   => $idString
        ];
        // Falls deine API-Basisklasse das Format nicht automatisch anhängt, hier mit angeben
        $url = '/api/tags/?' . http_build_query($params);
        
        $jsonResponse = $this->request($url);
        $paperlessData = json_decode($jsonResponse, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Ungültiges JSON von Paperless erhalten.");
        }
        $results = $paperlessData['results'] ?? [];

        // Paperless liefert die Liste im 'results'-Key
        $results = $paperlessData['results'] ?? ["LEER"];
        $formatted = [];
        foreach ($results as $key => $item) 
        {
            if (empty($item['id'])) continue;
            if (!isset($item['id']) || !isset($item['name'])) {
                continue;
            }
            $formatted[] = [
                'id'    => (int)$item['id'],
                'name'  => (string)$item['name']
            ];
        }
        
        return $formatted;
    }


    /**
     * tbd: Sicherstellen, dass es DER Kontakt ist!
     * security-patch notwendig
     * 
     * Verknüpft Korrespondent aus Paperless mit Kontakt
     * @param $contact_id Kontakt
     * @param $paperless_id Paperless
     */
    public function linkContact(int $contactId, int $corrId, string $action)
    {
        $params = [
            'cid'      => $contactId,
            'corrId'   => $corrId
        ];

        $sql = "DELETE 
                  FROM contact_paperless_map 
                 WHERE 
                    contact_id=:cid
                  AND correspondent_id=:corrId
                  AND tenant_id=:tenant_id";

        if (strtolower($action) == "link"){
            $sql = "INSERT INTO contact_paperless_map (contact_id, correspondent_id, tenant_id) 
                         VALUES (:cid, :corrId, :tenant_id);";
        }
        
        //$sql = "UPDATE contacts SET paperless_id =:paperless WHERE tenant_id=:tenant_id AND id= :cid;";
        
        return $this->queryIt($sql, $params);
    }

    public function updateTagToContact(int $cid, int $tagId, string $action)
    {
        $params = [
            'cid'      => $cid,
            'tagid'    => $tagId
        ];

        $sql = "DELETE 
                  FROM contact_paperless_map 
                 WHERE 
                    contact_id=:cid
                  AND tag_id=:tagid
                  AND tenant_id=:tenant_id";

        if (strtolower($action) == "link"){
            $sql = "INSERT INTO contact_paperless_map (contact_id, tag_id, tenant_id) 
                         VALUES (:cid, :tagid, :tenant_id);";
        }
        
        return $this->queryIt($sql, $params);
    }


    /**
     * @deprecated
     * erstellt den MailTree von Paperless und Mails
     */
    public function getMailTree($hash, $correspondentid= null)
    {
        if (empty($hash) || $correspondentid === null) return [];
        // 3. Jetzt die Dokumente holen
        $paperlessDocs = $this->fetchDocumentsByCorrespondentId($correspondentid);
        if (empty($paperlessDocs)) return [];
        //alle results
        $paperlessDocsRes = paperlessDocs["results"];
        // Dynamische Ermittlung der Basis-URL aus den Mandanteneinstellungen
        //$settings = $this->dashboard->getTenantSettings($this->tenantId);
        //$baseUrl  = rtrim($settings['paperless_url'] ?? 'http://192.168.0.72:8030', '/');
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
        return krsort($tree);
    }
}