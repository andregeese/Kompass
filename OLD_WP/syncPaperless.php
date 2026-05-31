<?php
// cli_sync.php

// 1. Absicherung: Darf nur über die CLI gestartet werden
if (php_sapi_name() !== 'cli') {
    die("Dieses Skript darf nur über die Befehlszeile (CLI) ausgeführt werden.\n");
}

try {
    echo "--- Initialisiere Umgebung ---\n";

    // 2. Deine Config laden (stellt $pdo zur Verfügung)
    require_once __DIR__ . '/config.php'; 

    if (!isset($pdo) || !($pdo instanceof \PDO)) {
        throw new \Exception("Datenbankverbindung (\$pdo) wurde in config.php nicht korrekt initialisiert.");
    }

    // 3. Autoloader laden (falls du Composer nutzt, sonst deine eigenen Requires)
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    echo "--- Starte Paperless Massen-Synchronisation ---\n";

    // 4. Instanziere deine Klassen
    // Hinweis: Falls dein Dashboard die $pdo braucht, reichst du sie hier durch.
    $dashboard = new \App\Dashboard($pdo); 
    $mailProc  = new \App\MailWorker($dashboard);

    // 5. Massenverarbeitung starten (Limit z. B. 10 Mails pro Cron-Durchlauf)
    $limit = 10;
    $result = $mailProc->getMails2Paperless($limit);

    // 6. Auswertung für die Konsole / Logdatei
    if (isset($result['success']) && $result['success']) {
        echo "✔ Prozess erfolgreich beendet.\n";
        
        if (!empty($result['matches'])) {
            foreach ($result['matches'] as $match) {
                $status = $match['match'] ? "✅ SYNCED" : "❌ SKIPPED";
                echo "[$status] Mail-ID: {$match['id']} | Betreff: {$match['subject']} | Grund: {$match['reason']}\n";
            }
        } else {
            echo "ℹ Keine neuen unverarbeiteten Mails in der Queue.\n";
        }
    } else {
        echo "⚠️ Fehler bei der Verarbeitung: " . ($result['error'] ?? 'Unbekannter Fehler') . "\n";
    }

} catch (\Throwable $e) {
    echo "🚨 Schwerwiegender Systemfehler im CLI-Skript: " . $e->getMessage() . "\n";
    echo "Datei: " . $e->getFile() . " on Line " . $e->getLine() . "\n";
    exit(1);
}