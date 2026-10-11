<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

namespace App\Services;
use Exception; // Falls du Exceptions nutzt
use Throwable;
use Sabre\DAV\Client;
use Sabre\VObject\Reader;
/**
 * @property \App\Services\AppointmentsProcessor $appointProcess
 */
class AppointmentsProcessor extends BaseProcessor
{
    protected $dashboard;
    public function __construct($dashboard) {
        parent::__construct($dashboard);
        $this->dashboard = $dashboard;
    }
    // Zentrale Client-Fabrik
    private function getClient($uri = 'https://p41-caldav.icloud.com/') 
    {
        $systemService = $this->dashboard->getService('systemProcess', \App\Services\SystemProcessor::class);
        $settings = [
            'baseUri'  => $uri,
            'userName' => $systemService->getRequiredEnv('IMAP_USER'),
            'password' => $systemService->getRequiredEnv('IMAP_PASSWORD'),
        ];
        $client = new Client($settings);
        $client->addCurlSetting(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $client->addCurlSetting(CURLOPT_USERAGENT, 'DAVKit/1.1 (534.50.1)');
        $client->addCurlSetting(CURLOPT_HTTPHEADER, ['X-Apple-Request-UUID: ' . bin2hex(random_bytes(8))]);
        return $client;
    }

    /**
     * Holt alle Appointments zu einer Mail-ID (TIMELINE)
     */
    public function getAppointmentsById($id)
    {
        error_log("Lade alle Appointments");
        // alle Notizen zu einer Mail
        $sql = "SELECT DISTINCT 
                a.*,
                TRIM(
                    SUBSTRING_INDEX(
                        SUBSTRING_INDEX(
                            REPLACE(REPLACE(a.ical_data, '\r', ''), '\n', '<BR>'), 
                        'SUMMARY:', -1), 
                    '<BR>', 1)
                ) AS extracted_summary
                FROM `appointments` a
                -- 1. Finde alle E-Mail-Adressen/IDs, die zum selben Kontakt gehören
                JOIN `contact_emails` ce_all 
                ON (a.ceid = ce_all.id) -- OR LOWER(TRIM(n.email)) = LOWER(TRIM(ce_all.email)))
                -- 2. Schlage die Brücke zum gemeinsamen Hauptkontakt (contacts)
                JOIN `contact_emails` ce_current_mail 
                ON ce_all.contact_id = ce_current_mail.contact_id
                -- 3. Knüpfe an die aktuell in der Sidebar geöffnete Mail an (über sender oder recipient)
                JOIN `mails` m 
                ON (m.sender_ceid = ce_current_mail.id) -- OR m.recipient_ceid = ce_current_mail.id)
                -- 4. Filter für die Sidebar
                WHERE m.id = CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED)
                AND m.tenant_id = :tenant_id
                AND a.tenant_id = :tenant_id
                -- Neueste Notizen immer zuerst anzeigen
                ORDER BY a.updated_at DESC;";
        $params = ['id' => $id];

        $dbResult = $this->queryIt($sql, $params, false, false, false);

        if (!$dbResult->success) {
            throw new \Exception("Fehler bei Appointment!");
        }
        return $dbResult->data->fetched;
    }

    /**
     * Holt alle Appointments aus der DB
     */
    public function getAppointments($start_date, $end_date)
    {
        
        return $this->queryIt(
            "WITH RECURSIVE calendar AS (
                SELECT CAST(:start_date AS DATE) AS d_date
                UNION ALL
                SELECT DATE_ADD(d_date, INTERVAL 1 DAY)
                FROM calendar
                WHERE d_date < :end_date
            )
            SELECT 
                HEX(AES_ENCRYPT(CAST(a.id as CHAR), :crypto_key)) as id, 
                a.uid, 
                a.ceid, 
                a.summary, 
                a.start_time, 
                a.end_time,
                CONCAT(a.id, '_', DATE_FORMAT(calendar.d_date, '%Y%m%d')) AS unique_id,
                DATE_FORMAT(calendar.d_date, '%a, %d. %b') AS date_str,
                CASE 
                    WHEN DATE(COALESCE(a.end_time, a.start_time)) > DATE(a.start_time) THEN '🌴 Ganztägig' 
                    -- 2. Wenn ein Enddatum existiert
                    WHEN a.end_time IS NOT NULL AND a.end_time != a.start_time THEN 
                        CONCAT(DATE_FORMAT(a.start_time, '%H:%i'), ' - ', DATE_FORMAT(a.end_time, '%H:%i'))
                    
                    -- 3. Fallback: Nur die Startzeit
                    ELSE DATE_FORMAT(a.start_time, '%H:%i')
                END AS time_str,
                IF(calendar.d_date = CURDATE(), 1, 0) AS is_today,
                CASE 
                    WHEN WEEKOFYEAR(calendar.d_date) != LAG(WEEKOFYEAR(calendar.d_date)) OVER (ORDER BY calendar.d_date ASC, a.start_time ASC) THEN 1 
                    ELSE 0 
                END AS week_transition
            FROM calendar
            -- COALESCE fängt ab, falls end_time NULL ist, und nimmt dann einfach start_time an
            JOIN appointments a ON calendar.d_date >= DATE(a.start_time) 
                            AND calendar.d_date <= DATE(COALESCE(a.end_time, a.start_time))
            ORDER BY calendar.d_date ASC, a.start_time ASC;",
            [
                'start_date'    => $start_date,
                'end_date'      => $end_date,
                'crypto_key'    => $this->dashboard->getTenantCryptoKey()
            ],
            false,
            false,
            false
        );
    }

    /**
     * aktualisiert ein Appointment
     */
    public function updateAppointment($appointment_id, $ceid='')
    {
        $sql = "UPDATE appointments 
                   SET ceid = CAST(AES_DECRYPT(UNHEX(:ceid), :crypto_key) AS UNSIGNED)
                 WHERE tenant_id=:tenant_id 
                   AND id=CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED);";
        
        return $this->queryIt(
            $sql,
            [
                'ceid' => empty($ceid) ? NULL : $ceid,
                'id'    => $appointment_id
            ],
            false,
            false,
            true
        );

    }

    /**
     * zieht exakt ein Appointment aus der DB
     * @param int $id
     */
    public function getAppointment(int $id)
    {
        $sql = "SELECT summary, start_time, end_time, ceid, uid, ical_data 
                  FROM appointments 
                 WHERE tenant_id=:tenant_id 
                   AND id=:crypto_key";

        return $this->queryIt(
            $sql, 
            ['id' => $id]
        );
    }


    public function getUpcomingEvents()
    {
        $sql = "SELECT summary, start_time, end_time, ceid, uid, ical_data  
                  FROM appointments 
                 WHERE tenant_id=:tenant_id
                   AND `start_time` >= now()
                   AND `end_time` <= now()+ INTERVAL 7 DAY";

        return $this->queryIt($sql, [], false, false, false);
    }

    private function getPrincipalPath()
    {
        $client = $this->getClient();
        $body = '<?xml version="1.0" encoding="utf-8" ?><D:propfind xmlns:D="DAV:"><D:prop><D:current-user-principal/></D:prop></D:propfind>';
        $response = $client->request('PROPFIND', '/', $body, ['Depth' => '0']);
        if ($response['statusCode'] === 207) {
            $xml = new \SimpleXMLElement($response['body']);
            $xml->registerXPathNamespace('D', 'DAV:');
            $results = $xml->xpath('//D:current-user-principal/D:href');
            return (string)$results[0];
        }
        throw new Exception("Konnte Principal Path nicht ermitteln.");
    }

    /**
     * alle Kalender ermitteln, Name und URL
     */
    public function getCalendars($calendarHomeSet)
    {
        // WICHTIG: Die baseUri muss die URL des Calendar-Home-Sets sein!
        $client = $this->getClient($calendarHomeSet); 
        
        $response = $client->propFind($calendarHomeSet, [
            '{DAV:}displayname',
            '{urn:ietf:params:xml:ns:caldav}calendar-description'
        ], 1);
        $calendars = [];
        foreach ($response as $href => $props) {
            if (isset($props['{DAV:}displayname'])) {
                $calendars[] = ['url' => $href, 'name' => $props['{DAV:}displayname']];
                //error_log("Kalender-URL: " . $href );
            }
        }
        return $calendars;
    }
    /**
     * Hole die Events aus dem Kalender ($calenderURL)
     */
    private function getEvents(string $calendarUrl)
    {
        //error_log(__FILE__ . ": " . __FUNCTION__ . " =>" . print_r($calendarUrl, true));
        $client = $this->getClient($calendarUrl);
        $report = '<?xml version="1.0" encoding="utf-8" ?>
<C:calendar-query xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav">
    <D:prop>
        <D:getetag/>
        <C:calendar-data/>
    </D:prop>
    <C:filter>
        <C:comp-filter name="VCALENDAR">
            <C:comp-filter name="VEVENT"/>
        </C:comp-filter>
    </C:filter>
</C:calendar-query>';
        
        $response = $client->request(
            'REPORT', 
            $calendarUrl, 
            $report, 
            [
                'Content-Type' => 'application/xml; charset=utf-8',
                'Depth'        => '1'
            ]
        );
        // DEBUG: Das ist der wichtigste Schritt!
        //error_log("DEBUG: Response Status: " . $response['statusCode']);
        
        // Wir loggen den Response-Body direkt, VOR der weiteren Verarbeitung
        //file_put_contents('caldav_debug.xml', $response['body']); 
        //error_log("DEBUG: Body in caldav_debug.xml gespeichert.");
        //error_log(print_r($response, true));
        
        // DEBUG: Schau dir an, was wirklich zurückkommt
        if ($response['statusCode'] !== 207) {
            error_log("FEHLER BEI REPORT: Status " . $response['statusCode']);
            error_log("BODY: " . $response['body']);
            throw new Exception("CalDAV REPORT Request fehlgeschlagen: " . $response['body']);
        }
        
        return $response['body'];
    }
    /**
     * Run Full Sync
     */
    public function runFullSync()
    {
        try {
            // 1. Authentifizierung und Pfad-Ermittlung erfolgt intern durch die anderen Methoden
            // Wir holen uns den Pfad zum "Home-Set"
            $principalPath = $this->getPrincipalPath();
            
            $client = $this->getClient(); // Nutzt die Auth-Daten aus der Instanz
            $response = $client->propFind($principalPath, ['{urn:ietf:params:xml:ns:caldav}calendar-home-set'], 0);
            
            // Extraktion (wie von dir bereits implementiert)
            $calendarHomeSet = $response['{urn:ietf:params:xml:ns:caldav}calendar-home-set'][0]['value'];
            
            // 2. Liste aller Kalender abrufen
            $calendars = $this->getCalendars($calendarHomeSet);
            
            // 3. Für jeden Kalender den Sync ausführen
            foreach ($calendars as $i => $calendar) {
                if ($i == 4) {
                    //error_log("Synchronisiere Kalender: " . $calendar['name'] . " => " . $calendar['url'] );
                    $this->syncCalendarToDb($calendar['url']);
                }
            }
            return true;
            
            //error_log("Sync erfolgreich abgeschlossen.");
            
        } catch (\Exception $e) {
            error_log("Sync abgebrochen: " . $e->getMessage());
        }
    }

    public function syncCalendarToDb($calendarUrl, $tableName = 'appointments')
    {
        // 1. Stelle sicher, dass $calendarUrl absolut ist
        if (strpos($calendarUrl, 'https://') !== 0) {
            $calendarUrl = 'https://p' . $this->getPartition(). "-caldav.icloud.com" . $calendarUrl;
        }
        
        // 1. Termine abrufen
        $icsData = $this->getEventsParsed($calendarUrl);

        
        // 2. Parsen mit Sabre VObject
        //$vcalendar = \Sabre\VObject\Reader::read($icsData);
        foreach ($icsData as $_event) 
        {
            $this->transaction( function () use ($tableName, $_event) 
            {
                $this->queryit("UPDATE $tableName SET status = 'del' WHERE status='active'");

                $dbResult = $this->queryit("
                        INSERT INTO $tableName (uid, summary, start_time, end_time, location, ical_data, updated_at, tenant_id, status)
                        VALUES (:uid, :summary, :start, :end, :loc, :data, NOW(), :tenant_id, 'active')
                        ON DUPLICATE KEY UPDATE 
                            summary = VALUES(summary), 
                            start_time = VALUES(start_time), 
                            end_time = VALUES(end_time),
                            status = 'active',
                            location = VALUES(location),
                            ical_data = VALUES(ical_data),
                            updated_at = NOW();"
                        ,
                        [
                            'uid'     => $_event['uid'],
                            'summary' =>  (string)$_event['summary'],
                            'start'   => $_event['start'],
                            'end'     => $_event['end'],
                            'loc'     => $_event['loc'],
                            'data'    => $_event['data']
                        ],
                        true,
                        false,
                        false
                        );
                
                if (!$dbResult->sucess)
                {
                    return $dbResult->error;
                }
            });
        }
    }

    // In deiner AppointmentsProcessor.php
    public function getEventsParsed(string $calendarUrl):array
    {
        //error_log(__FILE__ . ": " . __FUNCTION__ . " =>" . print_r($calendarUrl, true));
        // 1. Stelle sicher, dass $calendarUrl absolut ist
        if (strpos($calendarUrl, 'https://') !== 0) {
            $calendarUrl = 'https://p' . $this->getPartition(). "-caldav.icloud.com" . $calendarUrl;
            //$calendarUrl = 'https://p41-caldav.icloud.com' . $calendarUrl;
        }
        
        //error_log($calendarUrl);
        $xmlData = $this->getEvents($calendarUrl);
    
        // Wir nutzen SimpleXML, um den <cal:calendar-data> Tag zu extrahieren
        $xml = new \SimpleXMLElement($xmlData);
        $xml->registerXPathNamespace('D', 'DAV:');
        $xml->registerXPathNamespace('C', 'urn:ietf:params:xml:ns:caldav');
        
        $results = $xml->xpath('//C:calendar-data');
        
        $events = [];
        foreach ($results as $calData) 
        {
            try {
                $dom = dom_import_simplexml($calData);
                $icsString = $dom->textContent;
                // 1. Validierung: Ist der String überhaupt befüllt?
                if (empty($icsString) || strlen(trim($icsString)) < 10) {
                    error_log("DEBUG: Überspringe leeren/ungültigen ICS-Knoten");
                    continue;
                }
                // 2. Folding entfernen
                $icsString = preg_replace("/\r\n[ \t]/", "", $icsString);
                $icsString = preg_replace("/\n[ \t]/", "", $icsString);
                // 3. Typ-Sicherer Aufruf
                // Wir stellen sicher, dass wir einen String übergeben
                $vcalendar = \Sabre\VObject\Reader::read((string)$icsString);
                if (!$vcalendar) {
                    continue;
                }
                foreach ($vcalendar->VEVENT as $event) 
                {
                    $startObj = $event->DTSTART->getDateTime();
                    $endObj   = $event->DTEND->getDateTime();
                    $startTime = $startObj->format('Y-m-d H:i:s');
                    $endTime   = $endObj->format('Y-m-d H:i:s');
                    // 3. Optional: Wenn du sichergehen willst, dass alles UTC ist (empfohlen für DBs)
                    $startObj->setTimezone(new \DateTimeZone('UTC'));
                    $startTimeUtc = $startObj->format('Y-m-d H:i:s');
                    $events[] = [
                        'uid'    => (string)$event->UID,
                        'summary' => (string)$event->SUMMARY,
                        'start' => $startTime,
                        'end' => $endTime,
                        'data' => $event->serialize(),
                        'loc' => isset($event->LOCATION) ? (string)$event->LOCATION : ''
                    ];
                }
            } catch (\Throwable $e) {
                error_log("Parser-Fehler in Event: " . $e->getMessage());
                continue; 
            }
        }
        return $events;
    }
    public function getFullCalendarBaseUrl()
    {
        $user = $this->systemProcess->getRequiredEnv('IMAP_USER');
        $pw   = $this->systemProcess->getRequiredEnv('IMAP_PASSWORD');
        // Wir starten bei einer neutralen Adresse
        $settings = [
            'baseUri'  => 'https://caldav.icloud.com/',
            'userName' => $user,
            'password' => $pw,
        ];
        $client = new \Sabre\DAV\Client($settings);
        // WICHTIG: Damit erkennt SabreDAV den 401/Digest-Prozess automatisch
        $client->addCurlSetting(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
        try {
            // Ein einfacher Propfind auf den Root-Principal
            // Apple wird hier mit einer 401 antworten, aber uns die korrekte Partition im Header schicken
            $response = $client->propFind('/principals/', ['{DAV:}current-user-principal']);
            
            // Nach der Authentifizierung (durch SabreDAVs internen Auth-Handler) 
            // ist die baseUri im Client oft schon auf die korrekte Partition korrigiert
            return $client->getSettings()['baseUri'];
            
        } catch (\Exception $e) {
            // Falls SabreDAV den Fehler wirft, können wir die Header des letzten Requests analysieren
            // Dies ist der Moment, in dem die Partition 'pXX' bekannt wird
            error_log("Fehler bei Discovery: " . $e->getMessage());
            return null;
        }
    }
    public function getPartition()
    {
        $user = $this->systemProcess->getRequiredEnv('IMAP_USER');
        $pw   = $this->systemProcess->getRequiredEnv('IMAP_PASSWORD');
        
        // Wir starten bei der allgemeinen iCloud-Adresse
        $url = 'https://p01-caldav.icloud.com/principals/'; 
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC); // Wichtig für iCloud
        curl_setopt($ch, CURLOPT_USERPWD, "$user:$pw");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PROPFIND");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Depth: 1',
            'User-Agent: DAVKit/1.1 (534.50.1)',
            'X-Apple-Request-UUID: ' . bin2hex(random_bytes(8))
        ]);
        curl_setopt($ch, CURLOPT_HEADER, true); // Wir brauchen die Header für die Partition
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        
        // 1. Partition erkennen
        if (preg_match('/x-apple-user-partition: (\d+)/', $headers, $matches)) {
            $partition = $matches[1];
            //error_log("Gefundene Partition: " . $partition);
            // Hier könntest du nun deine baseUri dynamisch anpassen auf:
            // https://p$partition-caldav.icloud.com/
        }
        curl_close($ch);
        // Rückgabe der Zahl als String, nicht den Text "Erfolgreich..."
        if (preg_match('/x-apple-user-partition: (\d+)/', $headers, $matches)) {
            return $matches[1]; 
        }
        //return "Erfolgreich partitioniert.";
    }


    public function saveAppointment($data)
    {
        $sql = "UPDATE appointments 
            SET summary = :summary, 
                ceid = :ceid 
            WHERE id = :id AND tenant_id = :tenant_id";
            
        return $this->queryIt($sql, [
            'summary'   => $data['title'],
            'ceid'      => !empty($data['ceid']) ? (int)$data['ceid'] : null,
            'id'        => (int)$data['id']
        ]);

        
    }


}