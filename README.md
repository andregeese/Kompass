Enterprise CRM Hub
Source Available CRM System

Enterprise CRM Hub ist eine spezialisierte CRM-Lösung zur effizienten Verwaltung von Korrespondenz, Kontakten und Terminen.

⚠️ Wichtige Lizenzbedingungen (BSL 1.1)
Dieses Projekt wird als "Source Available"-Software unter der Business Source License (BSL) 1.1 bereitgestellt. Wir legen den Quellcode zu Transparenzzwecken offen, damit unsere Nutzer nachvollziehen können, wie das System arbeitet.

Durch die Nutzung dieser Software akzeptieren Sie folgende Bedingungen:

Nicht-kommerzielle Nutzung: Die private und interne Nutzung ist kostenlos gestattet.

Kommerzielle Nutzung: Jegliche kommerzielle Nutzung, Modifikation, Weiterverbreitung oder das Rebranding (Weiterführung unter anderem Namen) ist ohne ausdrückliche schriftliche Genehmigung des Urhebers untersagt.

Kein "Rebranding": Es ist strikt untersagt, den Code zu verändern, um ihn als eigenes Produkt oder unter einem anderen Namen zu vertreiben.

Integrität der Lizenz: Die in den Quelldateien enthaltenen Lizenz-Header dürfen weder entfernt noch manipuliert werden.

Über dieses Projekt
Der Quellcode dient der Transparenz und der Nachvollziehbarkeit. Die Kernlogik und das Urheberrecht liegen vollumfänglich bei CRM by abwurfzone / André Geese.

Kontakt & Genehmigungen
Für Anfragen bezüglich kommerzieller Lizenzen, Anpassungen oder Kooperationen kontaktieren Sie bitte den Urheber direkt.

Copyright (c) 2026 CRM by abwurfzone by André Geese. All rights reserved.



# 🚀 [CRM by abwurfzone]

**Dein lokales CRM für E-Mails, Dokumente & Termine – sicher gehostet bei dir.**

---

[CRM by abwurfzone] ist die einfache, datenschutzkonforme Lösung für KMU und Vereine, um den Überblick über Kontakte, E-Mail-Korrespondenz und wichtige Dokumente zu behalten – ohne monatliche Abogebühren und ohne Cloud-Zwang.

## 🌟 Warum [CRM by abwurfzone]?

* **Datensouveränität:** Deine Daten bleiben bei dir auf deinem Server oder NAS. Du behältst die volle Kontrolle.
* **Alles an einem Ort:** Synchronisiere deine E-Mails mit Paperless-Dokumenten, verwalte Termine und Notizen direkt am Kontakt.
* **Schnell & Einfach:** Dank Docker in unter 5 Minuten einsatzbereit.
* **DSGVO-freundlich:** Da kein externer Dienstleister auf deine Daten zugreift, ist die Einhaltung der Datenschutzrichtlinien deutlich einfacher.

---

## ⚖️ Lizenz & Kostenmodell

### Lizenz
Dieses Projekt steht unter der **GNU Affero General Public License v3.0 (AGPL-3.0)**. 
Das bedeutet für dich: Du darfst die Software frei nutzen, kopieren und anpassen. Wenn du den Code veränderst und anderen über ein Netzwerk zur Verfügung stellst, musst du die Änderungen ebenfalls unter der AGPL veröffentlichen. Das sichert den Open-Source-Gedanken für die gesamte Community.

### Kosten
*   **Software:** Die Basis-Version ist und bleibt **kostenlos**. Es gibt keine versteckten „Paywalls“ innerhalb der Software.
*   **Dienstleistung:** Wenn du Hilfe bei der Installation, individuellen Anpassungen oder laufender Wartung benötigst, biete ich professionelle Unterstützung als Dienstleistung an.

---

## ⚡ Schnellstart (Installation in 5 Minuten)

Voraussetzungen: [Docker](https://docs.docker.com/get-docker/) und [Docker Compose](https://docs.docker.com/compose/install/) müssen installiert sein.

1. **Repository klonen:**
```bash

```



git clone https://github.com/DEIN-USER/DEIN-REPO.git
cd DEIN-REPO

```

2.  **Konfiguration:**
    Kopiere die Vorlage und setze deine Einstellungen:
    ```bash
cp .env.example .env
# Öffne die .env Datei und passe das DB-Passwort an
nano .env

```

3. **Starten:**
```bash

```



docker-compose up -d

```

Dein CRM ist nun unter `http://localhost:8080` erreichbar.

---

## 🛡️ Sicherheit & Backup
Wir wissen, wie wichtig deine Daten sind. Im Ordner `/scripts` findest du ein einfaches `backup.sh` Skript, mit dem du per Knopfdruck oder Cronjob ein vollständiges Backup deines Datenbestands erstellen kannst. **Regelmäßige Backups sind der beste Schutz vor Datenverlust!**

---

## 🛠️ Support & Wartung
Du benötigst Unterstützung bei der Einrichtung oder möchtest, dass sich jemand um die Wartung und Updates kümmert?

*   **Community Support:** Nutze die [GitHub Issues](https://github.com/DEIN-USER/DEIN-REPO/issues) für Fragen oder Fehlerberichte.
*   **Professionelle Hilfe:** Wir bieten spezielle Support-Pakete für Vereine und Unternehmen an.
    *   [Hier klicken für mehr Informationen zu unseren Wartungsverträgen]: fly@abwurfzone.de

---

## 🤝 Unterstütze das Projekt
Dieses Projekt ist Open Source. Wenn dir das Tool gefällt und du es in deinem Verein oder Unternehmen nutzt, freuen wir uns über jede Unterstützung!

*   **Sponsoring:** Werde Sponsor auf [GitHub Sponsors] und hilf uns, das Projekt aktiv weiterzuentwickeln.
*   **Feedback:** Hast du Verbesserungsvorschläge oder ein Feature, das dir fehlt? Wir freuen uns auf dein Feedback!

---
*Entwickelt mit ❤️ für Vereine und KMU.*


-- AGE:
docker exec -it webserver composer dump-autoload -o --working-dir=/var/www/html/age_web
composer install!! ist wichtig, wenn ich nicht vendor ins git packe

Verschlüsselungen von id aus DB
UPDATE: 
AND id=CAST(AES_DECRYPT(UNHEX(:id), :crypto_key) AS UNSIGNED);";

SELECT 
HEX(AES_ENCRYPT(CAST(a.id as CHAR), :crypto_key)) as id, 

crypto_key ist ein schüssel der direkt injektiert wird in der SQL!


SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE table contact_emails;
TRUNCATE table contacts;
TRUNCATE table mails;
TRUNCATE table imap_sync_checkpoint;
TRUNCATE table contact_paperless_map;
TRUNCATE table notes;
SET FOREIGN_KEY_CHECKS = 1;

# SQL:
-- Adminer 5.4.2 MySQL 8.3.0 dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `uid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ical_data` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_perf_dates` (`tenant_id`,`start_time`,`end_time`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `contact_emails`;
CREATE TABLE `contact_emails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `tenant_id` int NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_id` (`contact_id`),
  KEY `idx_tenant_email` (`tenant_id`,`email`(191)),
  KEY `idx_tenant_email_hash` (`tenant_id`,`email_hash`),
  CONSTRAINT `contact_emails_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_emails_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `contact_paperless_map`;
CREATE TABLE `contact_paperless_map` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contact_id` int NOT NULL,
  `correspondent_id` int DEFAULT NULL,
  `tag_id` int DEFAULT NULL,
  `tenant_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `contact_id` (`contact_id`),
  KEY `idx_tenant_contact` (`tenant_id`,`contact_id`),
  CONSTRAINT `contact_paperless_map_ibfk_1` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_paperless_map_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_name` (`tenant_id`,`display_name`),
  KEY `idx_tenant_type_active` (`tenant_id`,`type`,`is_active`),
  CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `imap_sync_checkpoint`;
CREATE TABLE `imap_sync_checkpoint` (
  `tenant_id` int NOT NULL,
  `imap_folder` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_uid` int DEFAULT NULL,
  PRIMARY KEY (`tenant_id`,`imap_folder`),
  CONSTRAINT `imap_sync_checkpoint_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `mail_bodies`;
CREATE TABLE `mail_bodies` (
  `mail_id` int NOT NULL,
  `body_encrypted` text COLLATE utf8mb4_unicode_ci,
  `body_search_index` text COLLATE utf8mb4_unicode_ci,
  `body_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`mail_id`),
  CONSTRAINT `mail_bodies_ibfk_1` FOREIGN KEY (`mail_id`) REFERENCES `mails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `mails`;
CREATE TABLE `mails` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `contact_id` int DEFAULT NULL,
  `uid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sender` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_received` datetime DEFAULT NULL,
  `imap_folder` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `is_flagged` tinyint(1) DEFAULT '0',
  `is_answered` tinyint(1) DEFAULT '0',
  `is_newsletter` tinyint(1) DEFAULT '0',
  `sender_ceid` int DEFAULT NULL,
  `recipient_ceid` int DEFAULT NULL,
  `paperless_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sender_ceid` (`sender_ceid`),
  KEY `recipient_ceid` (`recipient_ceid`),
  KEY `tenant_id_message_id_imap_folder` (`tenant_id`,`message_id`,`imap_folder`),
  KEY `idx_tenant_folder_date` (`tenant_id`,`imap_folder`,`date_received` DESC),
  KEY `idx_tenant_contact` (`tenant_id`,`contact_id`),
  KEY `idx_tenant_subject` (`tenant_id`,`subject_hash`),
  KEY `mails_ibfk_4` (`paperless_id`),
  CONSTRAINT `mails_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mails_ibfk_2` FOREIGN KEY (`sender_ceid`) REFERENCES `contact_emails` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mails_ibfk_3` FOREIGN KEY (`recipient_ceid`) REFERENCES `contact_emails` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mails_ibfk_4` FOREIGN KEY (`paperless_id`) REFERENCES `contact_paperless_map` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `notes`;
CREATE TABLE `notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `mail_uid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_folder` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lokal_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ceid` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_mail` (`tenant_id`,`mail_uid`),
  KEY `idx_tenant_mail_folder` (`tenant_id`,`mail_folder`,`mail_uid`),
  KEY `notes_ibfk_2` (`ceid`),
  CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`ceid`) REFERENCES `contact_emails` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `system_errors`;
CREATE TABLE `system_errors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `line` int DEFAULT NULL,
  `trace` text COLLATE utf8mb4_unicode_ci,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at` DESC),
  KEY `idx_tenant_created` (`tenant_id`,`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tenant_config`;
CREATE TABLE `tenant_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int NOT NULL,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_config` (`tenant_id`,`config_key`),
  CONSTRAINT `tenant_config_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_username` (`tenant_id`,`username`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- 2026-07-06 15:00:43 UTC


SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 5



## PERFORMANCE CHECK:
SELECT start_time, query_time, rows_examined, rows_sent, sql_text 
FROM mysql.slow_log 
ORDER BY query_time DESC 
LIMIT 10;

SET GLOBAL log_output = 'TABLE';
SET GLOBAL slow_query_log = 1;

-- 2. Den Zeitschwellenwert sinnvoll hochsetzen
-- 0.5 Sekunden ist perfekt: Alles darunter ist für Web-Apps völlig okay.
-- Alles darüber wird für dich aufgezeichnet.
SET GLOBAL long_query_time = 0.5;

-- 3. Das "Index-Rauschen" abschalten
-- Wenn dies auf 1 steht, wird JEDE Abfrage geloggt, die keinen Index nutzt – 
-- selbst wenn sie auf einer Mini-Tabelle mit 2 Zeilen in 0,0001 Sekunden läuft. Das schalten wir ab.
SET GLOBAL log_queries_not_using_indexes = 0;

-- 4. Tisch abwischen (Altes Rauschen löschen)
TRUNCATE mysql.slow_log;