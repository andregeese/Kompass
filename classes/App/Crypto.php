<?php
namespace App;

class Crypto {
    private static $cipher = 'aes-256-gcm';

    public static function encrypt($pureText, $key) {
        $iv_length = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($iv_length);
        $ciphertext = openssl_encrypt($pureText, self::$cipher, $key, $options=0, $iv, $tag);
        
        // Verpacke Ciphertext, IV und den GCM-Tag als JSON oder Base64
        return base64_encode(json_encode([
            'c' => $ciphertext,
            'i' => base64_encode($iv),
            't' => base64_encode($tag)
        ]));
    }

    public static function decrypt($encryptedData, $key) {
        $data = json_decode(base64_decode($encryptedData), true);
        if (!$data) return false;
        
        $iv = base64_decode($data['i']);
        $tag = base64_decode($data['t']);
        
        return openssl_decrypt($data['c'], self::$cipher, $key, $options=0, $iv, $tag);
    }

}