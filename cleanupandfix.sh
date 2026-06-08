#!/bin/bash

# Dein neuer, saubere Header-Text
NEW_HEADER="/**\n * Enterprise CRM Hub\n * @license BSL 1.1 - Proprietary, non-commercial use only.\n * @copyright 2026 CRM by abwurfzone by André Geese. All rights reserved.\n * @note Modification, rebranding, and commercial distribution are strictly prohibited.\n */"

find . -type f \( -name "*.php" -o -name "*.js" \) \
    -not -path "*/vendor/*" \
    -not -path "*/node_modules/*" \
    -not -path "*/.git/*" | while read file; do

    echo "Säubere: $file"

    # 1. Entferne ALLES, was wie ein Kommentar-Header aussieht (von /** bis */)
    # Wir löschen alle Zeilen, die mit /** anfangen und bei */ enden, wenn sie das CRM-Keyword enthalten
    sed -i '/\/\*\*$/,/\*\//d' "$file"
    
    # 2. Entferne die kaputten "/**" Zeilen, die ohne */ stehen geblieben sind
    sed -i '/^\/\*\*$/d' "$file"

    # 3. Entferne alle leeren Zeilen am Dateianfang
    sed -i '/^$/d' "$file"

    # 4. Füge den sauberen Header neu ein
    if [[ "$file" == *.php ]]; then
        (echo "<?php" && printf "$NEW_HEADER\n\n" && sed '/<?php/d' "$file") > "$file.tmp"
    else
        (printf "$NEW_HEADER\n\n" && cat "$file") > "$file.tmp"
    fi

    mv "$file.tmp" "$file"
done

echo "Alle Dateien wurden bereinigt und einheitlich neu signiert."