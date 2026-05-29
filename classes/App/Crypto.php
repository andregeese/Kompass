<?php
namespace App;

class Crypto {
    private static $cipher = 'aes-256-gcm';

    public static function encrypt($pureText, $key) {
        $keyHash = hash('sha256', $key, true);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));
        
        $ciphertext = openssl_encrypt($pureText, self::$cipher, $keyHash, OPENSSL_RAW_DATA, $iv, $tag);
        
        return base64_encode(json_encode([
            'c' => base64_encode($ciphertext),
            'i' => base64_encode($iv),
            't' => base64_encode($tag)
        ]));
    }

    public static function decrypt($encryptedData, $key) {
        $keyHash = hash('sha256', $key, true);
        $decoded = json_decode(base64_decode($encryptedData), true);
        
        if (!$decoded || !isset($decoded['c'], $decoded['i'], $decoded['t'])) return false;
        
        return openssl_decrypt(
            base64_decode($decoded['c']), 
            self::$cipher, 
            $keyHash, 
            OPENSSL_RAW_DATA, 
            base64_decode($decoded['i']), 
            base64_decode($decoded['t'])
        );
    }
}