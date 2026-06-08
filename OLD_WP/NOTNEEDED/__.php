/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */

<?php
namespace App;
use Exception;
class AuthController extends \App\Core\BaseController 
{
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => false, // Nur wenn HTTPS aktiv
                'use_strict_mode' => true
            ]);
        }
    }
    public static function check(): bool {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['fingerprint']) && 
               $_SESSION['fingerprint'] === self::getFingerprint();    }
    public static function login($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fingerprint'] = self::getFingerprint();
    }
    private static function getFingerprint(): string {
        return hash('sha256', $_SERVER['HTTP_USER_AGENT'] . session_id());
    }
    
    
    public function register() 
    {
        $this->cleanBuffer();
        $email     = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password  = $_POST['password'] ?? '';
        $p_url     = trim($_POST['paperless_url'] ?? '');
        $p_name    = trim($_POST['paperless_name'] ?? '');
        $p_token   = trim($_POST['paperless_token'] ?? '');
        if (!$email || empty($password) || empty($p_url) || empty($p_token)) {
            return ['success' => false, 'error' => 'Bitte alle Pflichtfelder korrekt ausfüllen.'];
        }
        if ($this->dashboard->checkUserInDB($email, $password) === true) {
            return ['success' => false, 'error' => 'Diese E-Mail-Adresse ist bereits registriert.'];
        }
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $configParam = [
            'paperless_url'   => $p_url,
            'paperless_name'  => $p_name,
            'paperless_token' => $p_token
        ];
        $newTenantId = $this->dashboard->registerUser($email, $passwordHash, $configParam);
        if ($newTenantId === false) {
            throw new \Exception("Registrierung im System fehlgeschlagen.");
        }
            $_SESSION['user_id']   = $user['id'];
        $_SESSION['tenant_id'] = $newTenantId;
        // 4. Fingerprint hinterlegen (Zusätzlicher Schutz gegen Session-Diebstahl)
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION['login_ip']   = $_SERVER['REMOTE_ADDR']; // Optional (Achtung bei Mobilfunk-Wechseln)
        return ['success' => true, 'details' => ['message' => 'Registrierung erfolgreich!']];
    }
}