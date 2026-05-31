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
        //error_log(print_r($_POST, true));
        error_log("RAW INPUT: " . file_get_contents('php://input'));
        error_log("POST VARS: " . print_r($_POST, true));

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
    public function register($email, $plainPassword) {
        // 1. Passwort sicher hashen
        // PASSWORD_DEFAULT nutzt aktuell den sichersten Algorithmus (aktuell bcrypt)
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // 2. Hash in der Datenbank speichern
        $stmt = $this->db->prepare("INSERT INTO users (username, password_hash) VALUES (:email, :hash)");
        $stmt->execute([
            'email' => $email,
            'hash'  => $hashedPassword
        ]);
    }
}