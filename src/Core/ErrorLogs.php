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
/**
 * Zentraler Fehler-Log-Service
 * Fängt alle Arten von Fehlern ab und speichert sie in der Datenbank.
 */
class ErrorLogs
{
    private static bool $isLogging = false;
    private static $db = null;
    public static function setDatabase($db) {
        self::$db = $db;
    }
    /**
     * Initialisiert alle globalen Error-Handler.
     * Einmalig in index.php und api.php aufzurufen.
     */
    public static function register(): void
    {
        // 1. Fängt Standard-PHP-Fehler (Warnings, Notices) ab
        set_error_handler(function ($level, $message, $file, $line) {
            $levels = [
                E_ERROR => 'Error',
                E_WARNING => 'Warning',
                E_NOTICE => 'Notice',
                E_USER_ERROR => 'User Error',
                E_DEPRECATED => 'Deprecated'
            ];
            $levelName = $levels[$level] ?? 'Unknown';
            self::logToDb($levelName, $message, $file, $line);
        });
        // 2. Fängt nicht abgefangene Exceptions ab
        set_exception_handler([self::class, 'handleException']);
        // 3. Fängt Fatale System-Fehler ab
        register_shutdown_function([self::class, 'handleShutdown']);
    }
    /**
     * Speichert einen Fehler in der Datenbank.
     */
    public static function logToDb($type, $message, $file, $line, ?string $trace = null): void
    {
        // Sicherheits-Sperre gegen Endlosschleifen
        if (self::$isLogging || !self::$db) {
            error_log("CRITICAL: ErrorLogs fallback: $message");
            return;
        }
        try {
            self::$isLogging = true;
            $url      = $_SERVER['REQUEST_URI'] ?? 'CLI';
            $tenantId = $_SESSION['tenant_id'] ?? null;
            $sql = "INSERT INTO system_errors (type, message, file, line, trace, url, tenant_id) 
                VALUES (:type, :message, :file, :line, :trace, :url, :tenant_id)";
        
            $params = [
                'type'      => $type,
                'message'   => $message,
                'file'      => $file,
                'line'      => $line,
                'trace'     => '...', 
                'url'       => $_SERVER['REQUEST_URI'] ?? 'cli',
                'tenant_id' => $_SESSION['tenant_id'] ?? 0
            ];
            self::$db->queryIt($sql, $params);
        } catch (\Throwable $e) {
            // Fallback: Wenn DB nicht erreichbar, schreibe in System-Logdatei
            error_log("CRITICAL: ErrorLogs failed: " . $e->getMessage() . " | Original: " . $message);
        } finally {
            self::$isLogging = false;
        }
    }
    public static function handleException(\Throwable $e): void
    {
        self::logToDb('Exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ein Systemfehler ist aufgetreten.']);
        exit;
    }
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR])) {
            self::logToDb('Fatal', $error['message'], $error['file'], $error['line']);
        }
    }
    /**
     * Bequeme Methode für Debugging-Ausgaben während der Entwicklung.
     */
    public static function debug($message): void
    {
        $content = is_array($message) ? print_r($message, true) : $message;
        self::logToDb('debug', $content, 'debug', 0);
    }
}