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
use App\Services\ContactProcessor;
use Exception; // Falls du Exceptions nutzt
use Throwable;
use PDO;
/**
 * @property \App\Services\SystemProcessor $systemProcess
 */
class SystemProcessor extends \App\Core\Database
{
    use \App\Core\ServiceTrait;
    protected $dashboard;
    public function __construct($dashboard =null) {
        $this->dashboard = $dashboard;
    }
    /**
     * Prüft, ob die PHP-Installation die benötigten Erweiterungen und Funktionen für das Dashboard unterstützt.
     * @return array Eine Liste von Fehlermeldungen, falls Anforderungen nicht erfüllt sind. Leeres Array bedeutet "alles gut".
     */
    public function checkRequirements(): array
    {
        $errors = [];
        // Überprüfen der PHP-Version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $errors[] = "PHP 8.0 oder höher wird benötigt. Aktuelle Version: " . PHP_VERSION;
        }
        // Benötigte Erweiterungen
        $requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = "Die PHP-Erweiterung '$ext' ist nicht installiert oder aktiviert.";
            }
        }
        return $errors;
    }
    public function getPhpInfo(): string
    {
        ob_start();
        phpinfo();
        return ob_get_clean();
    }
    public function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unbekannt',
            'os' => PHP_OS,
        ];
    }
    public function isSystemReady($maxLoad = 2.5): bool
    {
        // sys_getloadavg() ist unter Linux/Ubuntu standardmäßig verfügbar
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            
            // $load[0] ist der Schnitt der letzten 1 Minute
            if (isset($load[0]) && $load[0] > $maxLoad) {
                return false; // Server ist zu beschäftigt!
            }
        }
        return true; // Alles im grünen Bereich
    }
    /**
     * Repariert verwaiste E-Mail-Zuordnungen für den aktuellen Tenant.
     * Nutzt die universelle queryIt()-Methode der Elternklasse.
     */
    public function repairTenantContacts(): void 
    {
        header('Content-Type: application/json');
        $tenantId = $_SESSION['tenant_id'] ?? null; 
        if (!$tenantId) {
            echo json_encode(['success' => false, 'error' => 'Keine Session.']);
            exit;
        }
        try {
            // 1. Alle verwaisten Mails holen
            $sqlGetUnlinked = "SELECT id, sender FROM mails WHERE contact_id IS NULL AND tenant_id = :tenant_id";
            $unlinkedMails = $this->queryIt($sqlGetUnlinked, ['tenant_id' => $tenantId]);
            if (empty($unlinkedMails)) {
                echo json_encode(['success' => true, 'message' => 'Alles bereits zugeordnet.']);
                exit;
            }
            // 2. Suche exakt die ID aus contact_emails (nicht contacts!)
            $sqlFindEmailEntry = "
                SELECT ce.id 
                FROM contact_emails ce
                INNER JOIN contacts c ON ce.contact_id = c.id
                WHERE ce.email = :email 
                AND c.tenant_id = :tenant_id
                LIMIT 1
            ";
            
            // 3. Update auf contact_emails.id (FOREIGN KEY Ziel)
            $sqlUpdateMail = "UPDATE mails SET contact_id = :ce_id WHERE id = :mail_id";
            $repairedCount = 0;
            $createdCount = 0;
            $contact = new ContactProcessor($this->dashboard);
            foreach ($unlinkedMails as $mail) {
                // Regex: Extrahiere E-Mail aus "Name <mail@test.de>" oder nur "mail@test.de"
                $senderRaw = $mail['sender'];
                if (preg_match('/<([^>]+)>/', $senderRaw, $matches)) {
                    $email = trim($matches[1]);
                } else {
                    $email = trim($senderRaw);
                }
                $email = strtolower($email);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                // Suche nach der ID in contact_emails
                $result = $this->queryIt($sqlFindEmailEntry, [
                    'email' => $email,
                    'tenant_id' => $tenantId
                ]);
                $ceId = !empty($result) ? $result[0]['id'] : null;
                // Fall: E-Mail noch nicht im System -> Neu anlegen
                if (!$ceId) {
                    $name = preg_replace('/<.*?>/', '', $senderRaw);
                    $newContactId = $contact->insertContact(trim($name), $email, 'company');
                    
                    // Wir müssen die neue ID aus contact_emails holen
                    $newResult = $this->queryIt("SELECT id FROM contact_emails WHERE email = :email LIMIT 1", ['email' => $email]);
                    $ceId = !empty($newResult) ? $newResult[0]['id'] : null;
                    $createdCount++;
                } else {
                    $repairedCount++;
                }
                // Update mit der ID aus contact_emails
                if ($ceId) {
                    $this->queryIt($sqlUpdateMail, [
                        'ce_id' => (int)$ceId,
                        'mail_id' => (int)$mail['id']
                    ]);
                }
            }
            echo json_encode(['success' => true, 'repaired' => $repairedCount, 'created' => $createdCount]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    /**
     * Testet die Verbindung und gibt eine Liste der Ordner wieder
     */
    public function testImap($host, $port, $user, $pass)
    {
     
        $connectionString = "{" . $host . ":" . $port . "/imap/ssl}";
    
        // Wir verbinden zum Root-Verzeichnis (ohne INBOX), um alle Ordner zu sehen
        $mbox = @imap_open($connectionString, $user, $pass, OP_READONLY, 1);
        
        if (!$mbox) {
            throw new \Exception("Konnte keine Verbindung aufbauen: " . imap_last_error());
        }
        $mailboxes = @imap_list($mbox, $connectionString, "*");
        
        if ($mailboxes === false) {
            imap_close($mbox);
            throw new \Exception("Konnte keine Ordner auflisten: " . imap_last_error());
        }
        $folders = [];
        foreach ($mailboxes as $mailbox) {
            // Pfad-Präfix entfernen, um nur den Namen zu behalten
            $folders[] = str_replace($connectionString, "", $mailbox);
        }
        
        imap_close($mbox);
        return $folders; // Rückg
    }

    public function getIMAPFolder()
    {
        error_log(__FUNCTION__);

        $a = $this->getRequiredEnv('SYNC_FOLDERS');
        error_log("IMAP FOLDERS: " . $a);

        return json_decode($a, true);
    }

    // NEU: Eine statische Brücke
    public static function getStaticEnv(string $key) {
        // Hier greifen wir direkt auf die Umgebung zu, 
        // ohne ein Dashboard-Objekt zu benötigen
        
        $envPath = __DIR__ . '/../../.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    if (trim($name) === $key && trim($value) !== '') {
                        return trim($value);
                    }
                }
            }
        }else{
            throw new \RuntimeException("Umgebungsvariable '$key' fehlt.");
        }
        return $value;
    }
    /**
     * tbd: $tenant_id = null
     */
    public function getRequiredEnv(string $key, ?int $tenantId = null):string
    {
        if (!$tenantId)
        {
            #error_log("tenant_id ist leer");
            $tenantId = $_SESSION['tenant_id'];    
        }
        #error_log("getRequiredEnv");
        #error_log("getRequiredEnv $key - $tenantId");
        $key = strtoupper($key);
        
        // 1. Priorität: .env Datei
        $envPath = ROOT_PATH .'/.env';
        if (file_exists($envPath)) {
            #error_log("Suche in .env $key");
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    if (trim($name) === $key && trim($value) !== '') {
                        return trim($value);
                    }
                }
            }
        }else{
            throw new \Exception("### .env ($envPath) nicht gefunden!! ###");
        }

        // 2. Priorität: Datenbank (tenant_config)
        if ($tenantId) {
            error_log("search Key In DB: " . $key);
            #error_log("Suche in .env $key");
            $sql = "SELECT config_value FROM tenant_config WHERE config_key = :key AND tenant_id = :tenant_id LIMIT 1";
            $params=[
                'key' => $key
            ];
            $dbResult = $this->queryit($sql, $params, false, false, false);
            
            if ($dbResult->success && !empty($dbResult->data->fetched)) {
                $row = $dbResult->data->fetched;
            
                if (!empty($row)) {                
                    $value = isset($row[0]['config_value']) ? $row[0]['config_value'] : null;
                    // AUTOMATISCHE ENTSCHLÜSSELUNG
                    // Prüfe, ob dieser Schlüssel zu denen gehört, die wir verschlüsselt haben
                    $encryptedKeys = ['IMAP_PASSWORD', 'PAPERLESS_TOKEN', 'IMAP_USER', 'PAPERLESS_NAME'];
                    
                    if (in_array($key, $encryptedKeys)) {
                        $masterKey = trim($this->getMasterKeyFromEnv()); 
                        $decrypted = \App\Core\Crypto::decrypt($value, $masterKey);
                        error_log("decrypted: " . $decrypted);
                        
                        // DEBUG: WICHTIG!
                        if ($decrypted === false) {
                            #error_log("FEHLER: Entschlüsselung von {$key} hat FALSE zurückgegeben!");
                            #error_log("Value aus DB: " . $value);
                            #error_log("Master-Key Hash: " . bin2hex(hash('sha256', $masterKey, true)));
                            throw new \Exception("Entschlüsselungs-Fehler für Key: " . $key);
                        }
                        
                        return $decrypted;
                    }
                }
                return $value;
            }
        }
        // 3. Wenn nirgendwo gefunden -> Exception
        error_log("KRITISCHER FEHLER: Konfigurations-Key {$key} weder in .env noch in DB gefunden.");
        throw new \Exception("System-Konfiguration unvollständig: {$key} konnte nicht geladen werden.");
    }
    private function getMasterKeyFromEnv(): string 
    {
        $envPath = __DIR__ . '/../../.env';
        
        if (!file_exists($envPath)) {
            throw new \Exception("Kritischer Fehler: .env Datei nicht gefunden.");
        }
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignoriere Kommentare
            if (strpos(trim($line), '#') === 0) continue;
            
            // Suche nach APP_KEY=...
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                if (trim($name) === 'APP_KEY') {
                    $key = trim($value);
                    if (empty($key)) {
                        throw new \Exception("APP_KEY in .env ist leer.");
                    }
                    return $key;
                }
            }
        }
        throw new \Exception("APP_KEY in .env Datei nicht definiert.");
    }

    public function globalSearch(string $query)
    {
        $allResults = [];
        $paperlessProcess = $this->getService('paperlessProcess', \App\Services\PaperlessProcessor::class);
        $pResult = $paperlessProcess->search($query,'');

        
        foreach ($pResult as $doc) {
            $allResults[] = [
                'type'    => 'document',
                'id'      => $doc['id'],
                'title'   => $doc['title'],
                'snippet' => 'Erstellt am: ' . $doc['created'],
                'score'   => $doc['score'] // Der berechnete Score aus deiner Logik
            ];
        }
        

        $formattedQuery = '%' . $query . '%';
        $params = ['query' => $formattedQuery];

        // 1. Parallel abfragen (oder nacheinander, ist bei 5 Tabellen egal)
        $queries = [
            'contact' => "SELECT HEX(AES_ENCRYPT(CAST(id as CHAR), :crypto_key)) as id, display_name as title, '' as snippet FROM contacts WHERE display_name LIKE :query AND tenant_id = :tenant_id LIMIT 10",
            'mail'    => "SELECT HEX(AES_ENCRYPT(CAST(m.id as CHAR), :crypto_key)) as id, m.message_id as title, mb.body_search_index as snippet FROM mails m LEFT JOIN mail_bodies mb ON mb.mail_id = m.id WHERE mb.body_search_index LIKE :query AND m.tenant_id = :tenant_id LIMIT 10",
            'note'    => "SELECT HEX(AES_ENCRYPT(CAST(id as CHAR), :crypto_key)) as id, category as title, content as snippet FROM notes WHERE content LIKE :query AND tenant_id = :tenant_id LIMIT 10",
            // NEU: Appointments & Paperless
            'appointment' => "SELECT HEX(AES_ENCRYPT(CAST(id as CHAR), :crypto_key)) as id, summary as title, location as snippet FROM appointments WHERE summary LIKE :query AND tenant_id = :tenant_id LIMIT 10",
            'paperless'   => "SELECT HEX(AES_ENCRYPT(CAST(id as CHAR), :crypto_key)) as id, title, '' as snippet FROM paperless_documents WHERE title LIKE :query AND tenant_id = :tenant_id LIMIT 10"
        ];

        
        foreach ($queries as $type => $sql) {
            $res = $this->queryIt($sql, $params, false, false, false);
            if ($res->success) {
                foreach ($res->data->fetched as $item) {
                    // Hier formatieren wir direkt in das von dir benötigte Format
                    $allResults[] = array_merge((array)$item, ['type' => $type]);
                }
            }
        }

        // 2. Ranking/Mapping (Hier die prozentuale Gewichtung)
        return $this->rankResults($allResults, $query);
    }

    private function rankResults(array $results, string $query): array
    {
        foreach ($results as &$item) {
            $score = 0;
            // Exakte Übereinstimmung am Anfang = 100 Punkte
            if (stripos($item['title'], $query) === 0) $score += 100;
            // Enthalten im Titel = 50 Punkte
            elseif (stripos($item['title'], $query) !== false) $score += 50;
            // Enthalten im Snippet = 20 Punkte
            elseif (isset($item['snippet']) && stripos($item['snippet'], $query) !== false) $score += 20;

            $item['score'] = $score;
        }

        // Sortieren nach Score absteigend
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($results, 0, 20); // Top 20
    }
}