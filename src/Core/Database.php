<?php

/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

namespace App\Core;

use PDO;
use App\Services\SystemProcessor;

class Database
{
    private static ?PDO $sharedPdo = null;
    protected ?PDO $pdo = null;

    public function __construct() {}
    /**
     * Zentrale Verbindung: Nutzt Instanz-Variable statt statischer Property
     */
    public function getConnection(): PDO
    {
        // 1. Bereits existierende statische Verbindung nutzen
        if (self::$sharedPdo !== null) {
            return self::$sharedPdo;
        }

        // Fallback: Falls lokal schon gesetzt
        if ($this->pdo !== null) {
            self::$sharedPdo = $this->pdo;
            return $this->pdo;
        }
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
        
        self::$sharedPdo = $this->pdo;

        return $this->pdo;
    }

    private function getDebugSql(string $sql, array $params): string
    {
        $isDebug = (defined('APP_DEBUG') && \APP_DEBUG === true);

        if (str_contains($sql, ':tenant_id')) {
            $isGlobalTable = preg_match('/users|tenants|global_config|error_logs|logs/i', $sql);
            if (!$isGlobalTable) {
                // Hier greift jetzt deine numerische ID aus der Session (z.B. 1)
                $currentTenantId = $_SESSION['tenant_id'] ?? ($isDebug ? 1 : null);

                if (!$currentTenantId) {
                    error_log("Sicherheitsblockade: Keine aktive tenant_id für dieses Query gefunden.");
                }
                $params['tenant_id'] = $currentTenantId;
            }
        }

        if (str_contains($sql, ':crypto_key')) {
            $tenantId = $_SESSION['tenant_id'] ?? null;
            if (!$tenantId) {
                error_log(__FUNCTION__ . ": ### Sicherheitsblockade: Keine tenant_id für CryptoKey vorhanden. ###");
                throw new \Exception("Sicherheitsblockade: Verschlüsselung ohne aktive Tenant-Sitzung nicht erlaubt.");
            }

            // 3. Das globale Master-Geheimnis aus der Umgebung holen
            // Falls deine DB-Klasse Zugriff auf deinen SystemProcessor-Service hat, nutze den.
            // Alternativ greifen wir hier direkt auf $_ENV oder getenv() zu:
            $systemProcess = new \App\Services\SystemProcessor();
            $globalSecret = $systemProcess->getRequiredEnv('ENC_KEY');
            
            if (empty($globalSecret)) {
                error_log(__FUNCTION__ . ": ### Konfigurationsfehler: ENC_KEY ist leer! ###");
                throw new \Exception("Kritischer Systemfehler: Verschlüsselungsschlüssel nicht konfiguriert.");
            }

            // 4. Den einzigartigen Tenant-CryptoKey berechnen (64-Zeichen Hex-String)
            $tenantCryptoKey = hash_hmac('sha256', (string)$tenantId, $globalSecret);
            $params['crypto_key'] = $tenantCryptoKey;//'CRYPTOKEY AUSGEBLENDET'; //$tenantCryptoKey;
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
    public function queryIt(string $query, array $params = [], bool $isTransactional = false, bool $returnInsertID = false, bool $debug = false)
    {
        $this->enforceQueryStandards($query);

        $result = new \stdClass();
        $result->success = false; // Standard: Fehler
        $result->data = new \stdClass;
        $result->data->lastId = null;
        $result->data->fetched = null;
        $result->error = null;
        $result->debug = null;
        
        // Debugging vorher
        if ($debug) {
            $result->debug = [
                "caller" => $this->getCallerInfo(),
                "sql"    => $this->getDebugSql($query, $params)
            ];
        }

        try {
            $pdo = $this->getConnection();

            $this->injectTenantId($query, $params);
            $this->injectCryptoKey($query, $params);

            // 1. LIMIT und OFFSET explizit im Array casten, BEVOR execute aufgerufen wird
            if (isset($params['limit']))  $params['limit']  = (int)$params['limit'];
            if (isset($params['offset'])) $params['offset'] = (int)$params['offset'];

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
                $result->success = false;
                $result->error = "DB FEHLER: Fehlende Parameter: " . implode(', ', $missing) . " im Query: $query";

                throw new \Exception("QUERY ABGEBROCHEN" . "DB FEHLER: Fehlende Parameter: " . implode(', ', $missing) . " im Query: $query"); // Abbruch, da Query sonst fehlschlägt
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
            $result->error = null;
            $result->data->lastId = $returnInsertID ? $pdo->lastInsertId() : null;
            $result->data->fetched = !$returnInsertID ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : null;
            $result->success = true;
            //error_log($result->debug['caller'] . " - SQL: " . $result->debug['sql']);


        } catch (\PDOException $e) {

            error_log("SQL FEHLER [" . $e->getCode() . "]: " . $e->getMessage() . " | Query: " . $query);
            $result->error = $e->getMessage();
            $result->success = false;

            if ($e->getCode() == '23000') {
                $result->error = "Eintrag vorhanden";
                $result->debug = [
                    "caller" => $this->getCallerInfo(),
                    "sql"    => $this->getDebugSql($query, $params)
                ];
                error_log($result->debug['caller'] . " - SQL: " . $result->debug['sql']);
            } else {
                $result->error = "PDO Fehler: " . $e->getMessage();
                $result->debug = [
                    "caller" => $this->getCallerInfo(),
                    "sql"    => $this->getDebugSql($query, $params)
                ];
                error_log($result->debug['caller'] . " - SQL: " . $result->debug['sql']);
            }
        } catch (\Throwable $e) {

            $this->getCallerInfo();
            $result->error = "Fehler in " . __FUNCTION__ . ": " . $e->getMessage();
            throw new \Exception($e->getMessage());
        }

        if ($debug) {
            error_log("DB DEBUG: " . print_r($result, true));
        }
        //tbd: ich möchte immer auf $result->success || $result->error im Frontend reagieren
        return (object)$result;
    }

    private function enforceQueryStandards(string $sql): void
    {
        // Wir trimmen das SQL und prüfen, ob es überhaupt ein SELECT-Statement ist
        if (!preg_match('/^\s*SELECT\b/i', $sql)) {
            return; // Kein SELECT (z.B. INSERT, UPDATE, DELETE) -> Überspringen
        }

        // 1. Prüfen, ob "SELECT *" (auch mit Tabellen-Alias wie "SELECT a.*") genutzt wird
        // Der Regex matcht: "SELECT", gefolgt von Whitespaces, gefolgt von einem optionalen "a." und dem Sternchen
        $hasSelectStar = preg_match('/\bSELECT\s+(?:\w+\.)?\*/i', $sql);

        // 2. Prüfen, ob das Wort "LIMIT" im SQL-String vorkommt
        $hasLimit = preg_match('/\bLIMIT\b/i', $sql);

        if ($hasSelectStar) {
            throw new \Exception(
                "Sicherheitsblockade: 'SELECT *' ist komplett verboten. Bitte zähle die benötigten Spalten explizit auf. ($sql)"
            );
        }
    }

    public function beginTransaction()
    {
        try {
            $conn = $this->getConnection();
            if (!$conn->inTransaction()) {
                $conn->beginTransaction();
            }
        } catch (\PDOException $e) {
            error_log("Transaction Start Fehler: " . $e->getMessage());
            throw $e; // Den Fehler nach oben durchreichen
        }
    }
    public function commit()
    {
        $this->getConnection()->commit();
    }
    public function rollBack()
    {
        $this->getConnection()->rollBack();
    }

    public function transaction(callable $callback)
    {
        $pdo = $this->getConnection();
        $isNested = $pdo->inTransaction();

        if (!$isNested) {
            $pdo->beginTransaction();
        }
        
        try {
            $result = $callback(); // Führt deinen Business-Code aus
            if (!$isNested) {
                $pdo->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if (!$isNested) {
                $pdo->rollBack();
            }
            error_log("Transaktion fehlgeschlagen: " . $e->getMessage());
            throw $e; // Fehler weiterreichen, damit du weißt, dass was passiert ist
        }
    }

    /**
     * Setzt tenant_id
     */
    private function injectTenantId(string &$sql, array &$params): void
    {
        // 1. Prüfen, ob der Platzhalter überhaupt im SQL-String existiert
        if (!str_contains($sql, ':tenant_id')) {
            return; // Nichts zu tun, sofort beenden
        }

        // Multi-Tenancy Logik
        $isGlobalTable = preg_match('/\b(users|tenants|global_config|error_logs|logs|system_errors)\b/i', $sql);
        if (!$isGlobalTable) {
            $tenantId = $_SESSION['tenant_id'] ?? null;
            if (!$tenantId) error_log(__FUNCTION__ . ": ### Sicherheitsblockade: Keine tenant_id. ###");


            // Automatischer Append nur, wenn :tenant_id im SQL steht
            if (str_contains($sql, ':tenant_id')) {
                $params['tenant_id'] = (int)$tenantId;
            }
        }
    }

    private function injectCryptoKey(string &$sql, array &$params): void
    {
        // 1. Prüfen, ob der Platzhalter überhaupt im SQL-String existiert
        if (!str_contains($sql, ':crypto_key')) {
            return; // Nichts zu tun, sofort beenden
        }

        // 2. Tenant-ID aus der Session holen (analog zu deiner Tenant-Logik)
        $tenantId = $_SESSION['tenant_id'] ?? null;
        if (!$tenantId) {
            error_log(__FUNCTION__ . ": ### Sicherheitsblockade: Keine tenant_id für CryptoKey vorhanden. ###");
            throw new \Exception("Sicherheitsblockade: Verschlüsselung ohne aktive Tenant-Sitzung nicht erlaubt.");
        }

        // 3. Das globale Master-Geheimnis aus der Umgebung holen
        // Falls deine DB-Klasse Zugriff auf deinen SystemProcessor-Service hat, nutze den.
        // Alternativ greifen wir hier direkt auf $_ENV oder getenv() zu:
        $systemProcess = new \App\Services\SystemProcessor();
        $globalSecret = $systemProcess->getRequiredEnv('ENC_KEY');
        
        if (empty($globalSecret)) {
            error_log(__FUNCTION__ . ": ### Konfigurationsfehler: ENC_KEY ist leer! ###");
            throw new \Exception("Kritischer Systemfehler: Verschlüsselungsschlüssel nicht konfiguriert.");
        }

        // 4. Den einzigartigen Tenant-CryptoKey berechnen (64-Zeichen Hex-String)
        $tenantCryptoKey = hash_hmac('sha256', (string)$tenantId, $globalSecret);

        // 5. Automatisch an die Bind-Parameter übergeben
        $params['crypto_key'] = $tenantCryptoKey;
        
    }

    public function getCallerInfo()
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? $trace[1];

        // Nutze hier den Standard error_log, um DB-Logger-Rekursion zu vermeiden
        return ("DB Query von: " . ($caller['class'] ?? '') . "->" . ($caller['function'] ?? ''));
    }
    public function __destruct()
    {
        $this->pdo = null;
    }
}
