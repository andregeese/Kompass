<?php
namespace App;

class ErrorLogs
{
    private static ?\PDO $db = null;

    /**
     * Initiiert eine eigene, frische DB-Verbindung nur für die Fehler
     */
    private static function initDb() 
    {
        if (self::$db !== null) return;
        
        try {
            // 🚀 WICHTIG: Trage hier deine echten DB-Daten ein!
            $dsn = "mysql:host=" . \DB_HOST . ";dbname=" . \DB_NAME . ";charset=utf8mb4";            
                self::$db = new \PDO($dsn, \DB_USER, \DB_PASS, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_PERSISTENT         => false // Schutz vor "Too many connections"
            ]);
        } catch (\Throwable $e) {
            // Absicherung: Wenn die DB komplett weg ist, schreiben wir ins PHP-File-Log
            error_log("🚨 ErrorLogger DB-FAIL: " . $e->getMessage());
            
            // 2. 🚀 DER RETTER: Wenn du display_errors aktiv hast, 
            // erzwingen wir die Ausgabe als JSON, damit dein safeFetch im Frontend 
            // sofort aufschreit und dir sagt, dass die Logger-Verbindung Schrott ist!
            if (ini_get('display_errors')) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'status'  => 'critical_error',
                    'error'   => 'ErrorLogger-Crash: ' . $e->getMessage()
                ]);
                exit;
            }
        }
    }

    /**
     * Speichert den Fehler in die DB
     */
    public static function logToDb(string $type, string $message, string $file, int $line, ?string $trace = null) 
    {
        self::initDb();
        if (!self::$db) return;

        try {
            $sql = "INSERT INTO system_errors (type, message, file, line, trace, url) 
                    VALUES (:type, :message, :file, :line, :trace, :url)";
            
            $stmt = self::$db->prepare($sql);
            $stmt->execute([
                'type'    => $type,
                'message' => $message,
                'file'    => $file,
                'line'    => $line,
                'trace'   => $trace,
                'url'     => $_SERVER['REQUEST_URI'] ?? 'CLI/Unknown'
            ]);
        } catch (\Throwable $e) {
            error_log("🚨 Fehler beim Schreiben in system_errors: " . $e->getMessage());
        }
    }

    /**
     * Handler für normale PHP-Fehler (Notices, Warnings)
     */
    public static function handleNotification($errno, $errstr, $errfile, $errline) 
    {
        // Wenn der Fehler durch das '@' Symbol unterdrückt wurde, ignorieren
        if (!(error_reporting() & $errno)) return false;

        $type = 'PHP_ERROR (' . $errno . ')';
        self::logToDb($type, $errstr, $errfile, $errline);
        return false; // Erlaubt PHP, den Fehler normal weiterzuverarbeiten
    }

    /**
     * Handler für unaufgefangene Exceptions (throw new \Exception)
     */
    public static function handleException(\Throwable $exception) 
    {
        self::logToDb(
            'EXCEPTION',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        // Da es eine unaufgefangene Exception war, müssen wir dem Frontend 
        // trotzdem ein sauberes JSON senden, sonst bleibt die UI hängen!
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'status'  => 'critical_error',
            'error'   => 'Ein interner Systemfehler ist aufgetreten.'
        ]);
        exit;
    }

    /**
     * Handler für fatale Abstürze (z.B. Memory Limit exceeded, Syntax Error)
     */
    public static function handleShutdown() 
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::logToDb(
                'FATAL_ERROR',
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }
}