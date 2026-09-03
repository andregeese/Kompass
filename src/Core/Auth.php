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
 * Zentraler Authentifizierungs-Service
 * Verantwortlich für Session-Management und Fingerprinting.
 */
class Auth {
    
    /**
     * Initialisiert die Session mit Sicherheits-Attributen.
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure'   => true, // Erfordert HTTPS
                'use_strict_mode' => true,
                'cookie_samesite' => 'Strict'
            ]);
        }
    }
    /**
     * Prüft, ob der Benutzer authentifiziert ist und validiert den Fingerprint.
     */
    public static function check(): bool {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['fingerprint']) && 
               $_SESSION['fingerprint'] === self::getFingerprint();
    }
    /**
     * Erstellt einen Fingerprint basierend auf User-Agent und Session-ID.
     * Erschwert Session-Hijacking (Diebstahl der Session-ID).
     */
    public static function getFingerprint(): string {
        // Wir nehmen zusätzlich Teile der Accept-Header, die sich bei 
        // einer Session-Übernahme durch einen Angreifer meist ändern.
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';
        
        // Ein statischer "Pepper" schützt vor einfachem Brute-Force des Hashes
        $pepper = 'CRM_SECURE_2026_'; 
        return hash('sha256', $pepper . $ua . $lang . session_id());
    }
    public static function validateFingerprint(): bool {
        // 1. Prüfen, ob überhaupt ein Fingerprint in der Session hinterlegt ist
        if (empty($_SESSION['fingerprint'])) {
            return false;
        }
        // 2. Den aktuellen Fingerprint des Requests berechnen
        $currentFingerprint = self::getFingerprint();
        // 3. Vergleichen (hash_equals schützt auch hier vor Timing-Attacken)
        return hash_equals($_SESSION['fingerprint'], $currentFingerprint);
    }

    /**
     * Führt den Login durch und regeneriert die Session-ID.
     */
    public static function login(int $userId, int $tenantId) 
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);
        
        $_SESSION['user_id']   = $userId;
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['fingerprint'] = self::getFingerprint();
        $csrfToken = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $csrfToken;

        session_write_close();

        return $csrfToken;
    }

    /**
     * Zerstört die Session sicher.
     */
    public static function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    /**
     * Erzeugt ein Token, falls keines existiert, und speichert es in der Session
     */
    public static function getCSRFToken(): string {
        return $_SESSION['csrf_token'] ?? '';
    }
    
    /**
     * Prüft, ob das gesendete Token mit dem in der Session übereinstimmt
     */
    public static function verifyCsrfToken(?string $token): bool {
        // Falls keine aktive Session läuft, starten bzw. wiederöffnen
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        // Debug-Log für die Nachvollziehbarkeit
        error_log("VERIFY: Session-Token-Status: " . (isset($_SESSION['csrf_token']) ? "Vorhanden" : "FEHLT"));
        
        if (empty($_SESSION['csrf_token'])) {
            return false;
        }

        return !empty($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
}