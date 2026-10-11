<?php
/**
 * START_LICENSE
 * Kompass Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 Kompass by abwurfzone by André Geese. All rights reserved.
 * @note Modification, rebranding, and commercial distribution are strictly prohibited.
 * END_LICENSE
 */
namespace App\Api;
use App\Core\Auth; // Die reine Logik
class AuthController extends \App\Core\BaseController 
{
    /**
     *  Benutzer einloggen
    */    
    public function login() 
    {
        $rawInput = file_get_contents('php://input');
        $data     = json_decode($rawInput, true);
        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        
        // Debug-Ausgabe für Entwicklung:
        if (empty($email) || empty($password)) {
            throw new \Exception("E-Mail oder Passwort wurde nicht übergeben.");
        }
        
        // 3. Benutzer verifizieren
        $user = $this->dashboard->verifyCredentials($email, $password);

        if (!$user) {
            throw new \Exception("Zugangsdaten ungültig!");
        }

        // 4. Auth-Session aufbauen
        $uid = (int)$user['id'];
        $tid = (int)$user['tenant_id'];
        
        // Auth::login generiert das neue CSRF-Token und schreibt die Session
        $csrfToken = Auth::login($uid, $tid);

        // 5. Daten zurückgeben (Der Router kümmert sich um sendResponse)
        return [
            'success' => true,
            'status'  => 'success',
            'details' => [
                'message'    => 'Logged In',
                'user_id'    => $uid,
                'tenant_id'  => $tid,
                'csrf_token' => $csrfToken,
                'ui' => [
                    'notify'  => true,
                    'title'   => 'Willkommen',
                    'message' => 'Erfolgreich angemeldet.',
                    'type'    => 'success'
                ]
            ],
            'error' => null
        ];
        
    }
    /**
     * Benutzer anlegen
     */
    public function register()
    {
        try{
            error_log("REGISTER DATA: " . print_r($_SERVER, true));
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            // 2. Debugging: Logge, was wirklich ankam
            error_log("RAW INPUT: " . $rawInput);
            // 3. Fallback: Falls $data leer ist, prüfe, ob es doch ein Form-POST war
            $email = $data['email'] ?? $_POST['email'] ?? null;
            $password = $data['password'] ?? $_POST['password'] ?? null;

            if (!$email) {
                throw new \Exception("E-Mail fehlt im Request.");
            }
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $ret = $this->dashboard->registerUser($email, $hashedPassword);
            // 1. Passwort sicher hashen
            // PASSWORD_DEFAULT nutzt aktuell den sichersten Algorithmus (aktuell bcrypt)
            
            //$this->dashboard->sendResponse(true, print_r($ret, true), "", 'success');
        }catch(\Throwable $e) {
            //$this->dashboard->sendResponse(false, "", $e->getMessage(), 'error');
        }
        
    }

    /**
     * Passwort-Zurücksetzen anfordern (Sendet Reset-Link)
     */
    public function forgotPassword(string $email, string $newPassword)
    {
        // 1. Prüfen, ob der User existiert
        $checkSql = "SELECT id FROM users WHERE email = :email LIMIT 1";
        $check    = $this->db->queryIt($checkSql, ['email' => $email], false, false, false);

        if (!$check->success || empty($check->data->fetched)) {
            return false;
        }

        // 2. Passwortsicher hashen
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        // 3. In DB speichern
        $updateSql = "UPDATE users SET password_hash = :hash WHERE email = :email";
        $res       = $this->db->queryIt($updateSql, ['hash' => $hash, 'email' => $email], false, false, false);

        return $res->success;
    }

    /**
     * Neues Passwort anhand des Tokens speichern
     */
    public function resetPassword()
    {
        try {
            $rawInput    = file_get_contents('php://input');
            $data        = json_decode($rawInput, true);
            
            $token       = trim($data['token'] ?? $_POST['token'] ?? '');
            $newPassword = $data['password'] ?? $_POST['password'] ?? '';

            if (empty($token)) {
                throw new \Exception("Sicherheitstoken fehlt.");
            }

            if (empty($newPassword) || strlen($newPassword) < 8) {
                throw new \Exception("Das neue Passwort muss mindestens 8 Zeichen lang sein.");
            }

            // Delegiert die Prüfung des Tokens und das Speichern an das Dashboard
            $success = $this->dashboard->resetPasswordWithToken($token, $newPassword);

            if ($success) {
                //$this->dashboard->sendResponse(true, "Passwort wurde erfolgreich geändert.", null, 'success');
                return ['success' => true];
            } else {
                throw new \Exception("Der Link ist ungültig oder abgelaufen.");
            }

        } catch (\Throwable $e) {
            //$this->dashboard->sendResponse(false, null, $e->getMessage(), 'error');
            return ['success' => false];
        }
    }
}