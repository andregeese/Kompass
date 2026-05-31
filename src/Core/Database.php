<?php
namespace App\Core;

use PDO;
use App\Services\SystemProcessor;

class Database 
{
    private ?PDO $pdo = null;
    

    public function __construct() {
        
    }

    /**
     * Zentrale Verbindung: Nutzt Instanz-Variable statt statischer Property
     */
    public function getConnection(): PDO 
    {
        if ($this->pdo) return $this->pdo;
        $systemService = new SystemProcessor(null);

        $host = $systemService->getStaticEnv('DB_HOST');
        $db   = $systemService->getStaticEnv('DB_NAME');
        $user = $systemService->getStaticEnv('DB_USER');
        $pass = $systemService->getStaticEnv('DB_PASS');

        if (!$host || !$db) {
            throw new \RuntimeException("Datenbank-Konfiguration unvollständig.");
        }

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
        
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false 
        ]);

        return $this->pdo;
    }

    /**
     * Database class for handling database connections and operations
    * @deprecated Use queryIt() instead. This method is obsolete and replaced by queryIt().
     * This class manages database connectivity, query execution, and data retrieval
     * for the age_web application.
     */
    public function queryItOld(string $query, array $params = [], bool $isTransactional = false, bool $returnInsertID = false)
    {
        //$this->getCallerInfo();
        //error_log(__FUNCTION__ . ": " . $query);
        //error_log(print_r($params, true));

        try {
            $pdo = self::getConnection();

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

            //error_log($this->getDebugSql($query, $params));
            
            // 🚀 Ausführen - Wenn hier ein Fehler auftritt, MUSS PDO jetzt eine Exception werfen!
            $stmt->execute(); 

            // Erst HIER entscheiden, was zurückgegeben wird
            if ($returnInsertID) {
                $result = $pdo->lastInsertId();
            } else {
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
            
            $stmt->closeCursor(); // Erst nach dem Fetch schließen

            //error_log($this->getDebugSql($query, $params));
            if ($isTransactional) $pdo->commit();
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
     * Executes a database query with optional transaction support and parameter binding.
     *
     * @param string $query The SQL query to execute. Can include placeholders for prepared statements.
     * @param array $params Optional. Array of parameters to bind to the query. Default is empty array.
     * @param bool $isTransactional Optional. If true, wraps the query in a transaction. Default is false.
     * @param bool $returnInsertID Optional. If true, returns the last inserted ID instead of fetched results. Default is false.
     *
     * @return mixed Returns the last inserted ID (int) if $returnInsertID is true, otherwise returns an array of fetched rows.
     *
     * @throws \Exception When a database error occurs. The exception message contains the original PDO error message.
     *
     * @note This method automatically injects the tenant ID into queries for multi-tenant support.
     * @note Transactions are automatically rolled back on errors.
     * @note Error logging avoids writing to the database to prevent cascading failures.
     * @note Caller information is logged for debugging purposes.
     */
    public function queryIt(string $query, array $params = [], bool $isTransactional = false, bool $returnInsertID = false)
    {
        // Debugging vorher
        #$this->getCallerInfo();
        #error_log("DB Query $query");
        #$a = $this->getDebugSql($query, $params);
        #error_log("### DEBUG SQL ###");
        #error_log(print_r($a, true));
        #error_log("### EOF: DEBUG SQL ###");

        try {
            $pdo = $this->getConnection();
            $this->injectTenantId($query, $params);

            

            // 1. LIMIT und OFFSET explizit im Array casten, BEVOR execute aufgerufen wird
            if (isset($params['limit']))  $params['limit']  = (int)$params['limit'];
            if (isset($params['offset'])) $params['offset'] = (int)$params['offset'];

            if ($isTransactional) $pdo->beginTransaction();

            // 2. Prepare das Statement
            $stmt = $pdo->prepare($query);

            // 1. Extrahiere alle Platzhalter aus dem SQL (z.B. :id, :tenant_id)
            preg_match_all('/:([a-zA-Z0-9_]+)/', $query, $matches);
            $requiredParams = array_unique($matches[1]);

            // 2. Abgleich: Was fehlt?
            $missing = [];
            foreach ($requiredParams as $req) {
                if (!array_key_exists($req, $params)) {
                    $missing[] = $req;
                }
            }

            if (!empty($missing)) {
                $msg = "DB FEHLER: Fehlende Parameter: " . implode(', ', $missing) . " im Query: $query";
                error_log($msg);
                throw new \Exception($msg); // Abbruch, da Query sonst fehlschlägt
            }

            // --- DER WICHTIGE TEIL: Manuelles Binden für ALLE Parameter ---
            foreach ($params as $key => $value) {
                $placeholder = ':' . $key;
                // Wenn es limit oder offset ist, MUSS es als Integer gebunden werden
                if ($key === 'limit' || $key === 'offset') {
                    $stmt->bindValue($placeholder, (int)$value, \PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($placeholder, $value);
                }
            }

            $stmt->execute();

            $result = $returnInsertID ? $pdo->lastInsertId() : $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if ($isTransactional) $pdo->commit();
            return $result;

        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            
            $this->getCallerInfo();
            error_log("DB FEHLER: " . $e->getMessage());
            throw new \Exception("Datenbankfehler: " . $e->getMessage());
        }
    }

    private function injectTenantId(string &$sql, array &$params): void 
    {
        // 1. Prüfen, ob der Platzhalter überhaupt im SQL-String existiert
        if (!str_contains($sql, ':tenant_id')) {
            return; // Nichts zu tun, sofort beenden
        }
        
        // Multi-Tenancy Logik
        $isGlobalTable = preg_match('/\b(users|tenants|global_config)\b/i', $sql);
        if (!$isGlobalTable) {
            $tenantId = $_SESSION['tenant_id'] ?? null;
            if (!$tenantId) error_log(__FUNCTION__ . ": ### Sicherheitsblockade: Keine tenant_id. ###");
            
            
            // Automatischer Append nur, wenn :tenant_id im SQL steht
            if (str_contains($sql, ':tenant_id')) {
                $params['tenant_id'] = (int)$tenantId;
            }
        }
    }

    public function getCallerInfo() {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? $trace[1]; 
        
        // Nutze hier den Standard error_log, um DB-Logger-Rekursion zu vermeiden
        error_log("DB Query von: " . ($caller['class'] ?? '') . "->" . ($caller['function'] ?? ''));
    }

    public function __destruct() {
        $this->pdo = null;
    }
}