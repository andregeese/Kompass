#!/bin/bash
# Lizenz-Header festlegen
HEADER="/**\n * Enterprise CRM Hub\n * @license BSL-1.1 (Business Source License)\n * @copyright 2026 YourCompany\n * @link https://github.com/your-username/repo\n */\n\n"

# Alle .php und .js Dateien durchlaufen
find . -type f \( -name "*.php" -o -name "*.js" \) -not -path "*/vendor/*" | while read file; do
    # Prüfen, ob der Header schon existiert
    if ! grep -q "Enterprise CRM Hub" "$file"; then
        echo "Signiere: $file"
        # Temporäre Datei erstellen, Header reinschreiben, Rest der Datei anhängen
        printf "$HEADER" | cat - "$file" > temp && mv temp "$file"
    fi
done
