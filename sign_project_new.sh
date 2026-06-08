#!/bin/bash

# Dein Header mit Markern für die Maschine
HEADER="/**\n * START_LICENSE\n * Enterprise CRM Hub\n * @license BSL 1.1 - Proprietary, non-commercial use only.\n * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.\n * @note Modification, rebranding, and commercial distribution are strictly prohibited.\n * END_LICENSE\n */"

find . -type f \( -name "*.php" -o -name "*.js" \) \
    -not -path "*/vendor/*" \
    -not -path "*/node_modules/*" \
    -not -path "*/.git/*" | while read file; do
    
    echo "Fixiere: $file"

    # 1. Entferne den gesamten Block zwischen START_LICENSE und END_LICENSE (inklusive der Kommentar-Zeilen drumherum)
    # Das sed-Kommando löscht von START_LICENSE bis END_LICENSE und die jeweils angrenzenden Zeilen
    sed -i '/START_LICENSE/,/END_LICENSE/d' "$file"
    sed -i '/\/\*$/,/\*\//d' "$file" # Löscht Reste der Kommentar-Begrenzer

    # 2. Bereinige eventuelle Leerzeilen am Anfang
    sed -i '/^$/d' "$file"

    # 3. Füge den Header ganz neu ein
    if [[ "$file" == *.php ]]; then
        # PHP: PHP-Tag sichern, Header darunter
        (echo "<?php" && printf "$HEADER\n\n" && sed '/<?php/d' "$file") > "$file.tmp"
    else
        # JS: Direkt am Anfang
        (printf "$HEADER\n\n" && cat "$file") > "$file.tmp"
    fi

    mv "$file.tmp" "$file"
done