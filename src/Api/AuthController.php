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
        try{
            $rawInput = file_get_contents('php://input');
            $data     = json_decode($rawInput, true);
            $email    = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            // 1. Benutzer validieren (Deine Dashboard-Logik)
            $user = $this->dashboard->verifyCredentials($email, $password);
            
            if ($user) {
                $uid = (int)$user['id'];
                $tid = (int)$user['tenant_id'];
                // 2. Jetzt erst die Auth-Logik aufrufen
                Auth::login($uid, $tid);
                $this->dashboard->sendResponse(true, "Logged In", null, 'success');
                return ['success' => true];
            }else{
                $this->dashboard->sendResponse(false, null, "User not found: ". print_r($user, true), 'error');    
            }
        }catch(\Throwable $e) {
            
            $this->dashboard->sendResponse(false, null, "Login fehlgeschlagen! <br/>" . $e->getMessage(), 'error');
            return ['success' => false, 'error' => 'Login fehlgeschlagen'];
        }
        
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
            
            return $this->dashboard->sendResponse(true, print_r($ret, true), "", 'success');
        }catch(\Throwable $e) {
            return $this->dashboard->sendResponse(false, "", $e->getMessage(), 'error');
        }
        
    }
}