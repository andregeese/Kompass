<?php 
ini_set('display_errors', 1);         
ini_set('display_startup_errors', 1); 
error_reporting(E_ALL);               

$logFile= __DIR__."/php.log";
ini_set('log_errors', 1);
ini_set('error_log', $logFile);

// Sicherstellen, dass die Datei existiert und beschreibbar ist
if (!file_exists($logFile)) {
    touch($logFile);
    chmod($logFile, 0666);
}

$dbValue = "eyJjIjoiNGZXMzNoS3hLekc4SFlaOEtKUWF3QlZPeFVkR1RtU3NGXC9jYnVhM1ZRbEU5b1RoTXdieUxzV1VPWVN1eFwveTlMM0ZTV2NoOWppUlwveGVFXC93dytRaE5NaWVSNjkzS0VHYXExVFJsMEdneWt6NnI5MjNWU1Z4dHFVcHZNSDVxYUlaQnYycWMrcSs4NmtpT2g5a3FPaHAwMTZwRWc4TDl1d2FnUkJzUEE9PSIsImkiOiJGTytzT2tFSW9aOGRTUlVmIiwidCI6IlpOSkV0S3N4d1EzUXZ4NGxqY2RyOHc9PSJ9";

$raw = base64_decode($dbValue);
$json = json_decode($raw, true);

error_log("JSON-INHALT: " . print_r($json, true));

//phpinfo(); 
?>