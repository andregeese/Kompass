<?php

namespace App;
use Exception; // Falls du Exceptions nutzt
use Throwable;
use PDO;

class System
{
    /**
     * Prüft, ob die PHP-Installation die benötigten Erweiterungen und Funktionen für das Dashboard unterstützt.
     * @return array Eine Liste von Fehlermeldungen, falls Anforderungen nicht erfüllt sind. Leeres Array bedeutet "alles gut".
     */
    public function checkRequirements(): array
    {
        $errors = [];

        // Überprüfen der PHP-Version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $errors[] = "PHP 8.0 oder höher wird benötigt. Aktuelle Version: " . PHP_VERSION;
        }

        // Benötigte Erweiterungen
        $requiredExtensions = ['pdo_mysql', 'mbstring', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $errors[] = "Die PHP-Erweiterung '$ext' ist nicht installiert oder aktiviert.";
            }
        }

        return $errors;
    }

    public function getPhpInfo(): string
    {
        ob_start();
        phpinfo();
        return ob_get_clean();
    }

    public function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unbekannt',
            'os' => PHP_OS,
        ];
    }

    public function isSystemReady($maxLoad = 2.5): bool
    {
        // sys_getloadavg() ist unter Linux/Ubuntu standardmäßig verfügbar
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            
            // $load[0] ist der Schnitt der letzten 1 Minute
            if (isset($load[0]) && $load[0] > $maxLoad) {
                return false; // Server ist zu beschäftigt!
            }
        }
        return true; // Alles im grünen Bereich
    }
}