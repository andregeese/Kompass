<?php
namespace App\Api;

use App\Core\Auth; // Die reine Logik

class AuthController extends \App\Core\BaseController 
{
    /**
     *  Benutzer einloggen
    */    
    public function login() 
    {

        $email    = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // 1. Benutzer validieren (Deine Dashboard-Logik)
        $user = $this->dashboard->verifyCredentials($email, $password);
        
        if ($user) {
            $uid = (int)$user['id'];
            $tid = (int)$user['tenant_id'];
            // 2. Jetzt erst die Auth-Logik aufrufen
            Auth::login($uid, $tid);
            return ['success' => true];
        }
        
        return ['success' => false, 'error' => 'Login fehlgeschlagen'];
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