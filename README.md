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