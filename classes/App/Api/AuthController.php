<?php
namespace App\Api;

use Exception;

class AuthController extends BaseController {
    
    public function login() {
        $this->cleanBuffer();
        $email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email || empty($password)) {
            return ['success' => false, 'error' => 'Ungültige Anmeldedaten.'];
        }

        $userResult = $this->dashboard->loginUser($email, $password);
        $user = !empty($userResult) ? $userResult[0] : null;

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['tenant_id'] = $user['id'];

            // 4. Fingerprint hinterlegen (Zusätzlicher Schutz gegen Session-Diebstahl)
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['login_ip']   = $_SERVER['REMOTE_ADDR']; // Optional (Achtung bei Mobilfunk-Wechseln)
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'E-Mail-Adresse oder Passwort falsch.'];
    }

    public function register() {
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