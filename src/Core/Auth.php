<?php

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
        return hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . session_id());
    }

    /**
     * Führt den Login durch und regeneriert die Session-ID.
     */
    public static function login(int $userId, int $tenantId) {
        session_regenerate_id(true);
        
        $_SESSION['user_id']   = $userId;
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['fingerprint'] = self::getFingerprint();
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
}