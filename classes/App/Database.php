<?php
// classes/Database.php
namespace App;

use PDO;
use PDOException;

class Database 
{
    private static ?PDO $instance = null;

    private static ?PDO $pdo = null;
    private $dashboard;

    public function __construct($dashboard) {
        $this->dashboard = $dashboard;
    }

    public function __destruct() {
        self::closeConnection();
    }

    // In deiner DB-Klasse oder dem Datenbank-Wrapper
    private function beginTransaction() {
        $pdo = self::getConnection();
        return $pdo->beginTransaction();
    }

    private function commit() {
        $pdo = self::getConnection();
        return $pdo->commit();
    }

    private function rollBack() {
        $pdo = self::getConnection();
        return $pdo->rollBack();
    }

    /**
     * Zentrale Verbindung zur Datenbank (Singleton-Pattern)
     * Stellt sicher, dass immer nur eine Verbindung aufgebaut wird und schützt so vor "Too many connections"-Fehlern.
     */
    protected static function getConnection(): PDO {
        if (self::$pdo === null) {
            // Hier deine echten DB-Daten eintragen
                $dsn = "mysql:host=" . \DB_HOST . ";dbname=" . \DB_NAME . ";charset=utf8mb4";            
                self::$pdo = new PDO($dsn, \DB_USER, \DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false // Schutz vor "Too many connections"
            ]);
        }
        return self::$pdo;
    }

    public function getCallerInfo() {
        // Hole den Backtrace
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        
        // Index 0: debug_backtrace() selbst
        // Index 1: Die Funktion, die getCallerInfo() aufgerufen hat (dein Ziel)
        // Index 2: Der Rufer, der Index 1 aufgerufen hat
        $caller = $trace[2] ?? $trace[1]; 
        
        error_log(
            print_r(
                [
                'file'     => $caller['file'] ?? 'unknown',
                'line'     => $caller['line'] ?? 'unknown',
                'function' => $caller['function'] ?? 'unknown',
                'class'    => $caller['class'] ?? 'n/a'
            ],true)
        );
    }

    /**
     * Universelle Query-Methode - Sicher gegen SQL-Injections geschützt,
     * liefert IMMER ein flaches fetchAll()-Array zurück.
     * tenant_id wird hier mit reingeladen
     * @param string $query Das SQL-Query mit benannten Platzhaltern (z.B. :id, :name)
     * @param array $params Ein assoziatives Array mit den Werten für die Platzhalter (z.B. ['id' => 5, 'name' => 'Test'])  
     * @return $return 
     */
    public function queryIt(string $query, array $params = [], bool $isTransactional = false, bool $returnInsertID = false)
    {
        //$this->getCallerInfo();

        try {
            $pdo = self::getConnection();

            //$isTransactional = (bool) preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $query);

            // Transaktion starten, falls angefordert
            if ($isTransactional) {
                $pdo->beginTransaction();
            }

            if (!$pdo) {
                throw new \Exception("Datenbankverbindung konnte nicht hergestellt werden.");
            }

            // 🚀 ERZWINGE EXCEPTIONS: Falls in getConnection vergessen, hier absichern!
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            if (str_contains($query, ':tenant_id')) {
                $isGlobalTable = preg_match('/\b(users|tenants|global_config)\b/i', $query);

                if (!$isGlobalTable) {
                    // Hier greift jetzt deine numerische ID aus der Session (z.B. 1)
                    $currentTenantId = $_SESSION['tenant_id'] ?? ($_GET['debug'] === 'true' ? 1 : null);
                    
                    if (!$currentTenantId) {
                        throw new \Exception("Sicherheitsblockade: Keine aktive tenant_id für dieses Query gefunden.");
                    }

                    $params['tenant_id'] = $currentTenantId;
                }
            }

            // Wenn keine Parameter da sind, direkt abfeuern
            if (empty($params)) {
                $stmt = $pdo->query($query);
                return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            $stmt = $pdo->prepare($query);
            if (!$stmt) {
                throw new \Exception("Fehler beim Vorbereiten des SQL-Statements.");
            }

            // Parameter sauber binden
            foreach ($params as $key => $value) {
                if (!str_contains($query, ':' . $key)) {
                    continue; 
                }
                
                if ($key === 'limit' || $key === 'offset') {
                    $stmt->bindValue(':' . $key, intval($value), \PDO::PARAM_INT);
                } else {
                    // Wenn der Key tenant_id ist, erzwinge Integer, falls es eine Zahl ist
                    if ($key === 'tenant_id' && is_numeric($value)) {
                        $stmt->bindValue(':' . $key, intval($value), \PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
                    }
                }
            }

            // 🚀 Ausführen - Wenn hier ein Fehler auftritt, MUSS PDO jetzt eine Exception werfen!
            $stmt->execute(); 
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $result = null;

            if ($returnInsertID) {
                $result = $pdo->lastInsertId();
            } else {
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            if ($isTransactional) $pdo->commit();
            return $result;

            //error_log($this->getDebugSql($query, $params));
            return $result;
            
        } catch (\PDOException $e) {
            error_log("Zentraler Datenbankfehler: " . $e->getMessage());
            error_log("🚨 TRACE STACK:\n" . $e->getTraceAsString());
            
            error_log($this->getDebugSql($query, $params));

            $showQuery = (isset($_GET['debug']) && $_GET['debug'] === 'true');

            //error_log("Zentraler Datenbankfehler in queryIt: " . $e->getMessage());
            // Wir werfen eine stinknormale Exception weiter, statt exit aufzurufen!
            throw new \Exception($e->getMessage(), (int)$e->getCode());
            
        } catch (\Throwable $e) {
            
            if ($isTransactional && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Zentraler Allgemeiner Fehler: " . $e->getMessage());
            error_log($this->getDebugSql($query, $params));

            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json', true, 500);
            echo json_encode([
                'success' => false,
                'error'   => "Allgemeiner Systemfehler: " . $e->getMessage()
            ]);
            //exit;
        }
    }


    private function getDebugSql(string $sql, array $params): string 
    {
        if (str_contains($sql, ':tenant_id')) {
            $isGlobalTable = preg_match('/\b(users|tenants|global_config)\b/i', $sql);

            if (!$isGlobalTable) {
                // Hier greift jetzt deine numerische ID aus der Session (z.B. 1)
                $currentTenantId = $_SESSION['tenant_id'] ?? ($_GET['debug'] === 'true' ? 1 : null);
                
                if (!$currentTenantId) {
                    throw new \Exception("Sicherheitsblockade: Keine aktive tenant_id für dieses Query gefunden.");
                }

                $params['tenant_id'] = $currentTenantId;
            }
        }

        foreach ($params as $key => $value) {
            // Sicherstellen, dass wir auch Doppelpunkte im Key abfangen (:tenant_id vs tenant_id)
            $placeholder = ':' . ltrim($key, ':');
            
            // Wert für SQL formatieren (Strings in Anführungszeichen, NULL abfangen)
            if ($value === null) {
                $formattedValue = 'NULL';
            } elseif (is_numeric($value)) {
                $formattedValue = $value;
            } else {
                $formattedValue = "'" . addslashes((string)$value) . "'";
            }
            
            // Platzhalter im SQL-String ersetzen
            $sql = str_replace($placeholder, $formattedValue, $sql);
        }
        return $sql;
    }
    

    


    /**
     * Durchsucht die Kontakte/Absender anhand eines Suchbegriffs.
     * @param string $query Der Suchbegriff (Name, E-Mail oder Firma)
     * @return array Liste der gefundenen Kontakte
     */
    public function searchContacts(string $query): array {
        $search = trim($query);
        if (strlen($search) < 2) {
            return [];
        }
        $searchTerm = "%" . $query . "%";

        // Wir suchen in Name, E-Mail und Firma nach dem Begriff
        $sql = "SELECT MIN(id) as id, sender as name, sender as email, '' as company FROM mails WHERE sender like :sender GROUP BY sender LIMIT 50";
        
        $ret = $this->queryIt($sql, [
            'salt' => defined('HASH_SALT') ? HASH_SALT : '',
            'hash' => $hash,
            'sender' => $searchTerm
        ]);
                
        return $ret;
    }


    

    /**
     * Schließt die Verbindung aktiv (Schutz vor "Too many connections")
     */
    public static function closeConnection() {
        self::$pdo = null;
    }
}