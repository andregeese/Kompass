<?php
/**
 * START_LICENSE
 * Enterprise CRM Hub
 * @license BSL 1.1 - Proprietary, non-commercial use only.
 * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.
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
            $email = $_POST['email'];
            $plainPassword = $_POST['password'];
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
            $ret = $this->dashboard->registerUser($email, $hashedPassword);
            // 1. Passwort sicher hashen
            // PASSWORD_DEFAULT nutzt aktuell den sichersten Algorithmus (aktuell bcrypt)
            
            return $this->dashboard->sendResponse(true, print_r($ret, true), "", 'success');
        }catch(\Throwable $e) {
            return $this->dashboard->sendResponse(false, "", $e->getMessage(), 'error');
        }
        
    }
}