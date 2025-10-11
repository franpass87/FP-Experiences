#!/usr/bin/env bash
set -euo pipefail

# Script per verificare che lo zip contenga tutti i file necessari e aggiornati
# Usage: bash tools/verification/verify-zip-contents.sh [zip-file]

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)

# Determina il file zip da verificare
if [ $# -eq 1 ]; then
    ZIP_FILE="$1"
else
    # Trova l'ultimo zip creato
    ZIP_FILE=$(find "$REPO_DIR" -maxdepth 1 -name "fp-experiences-*.zip" -type f -printf '%T@ %p\n' | sort -rn | head -n 1 | cut -d' ' -f2-)
    if [ -z "$ZIP_FILE" ]; then
        echo "❌ Nessun file zip trovato. Esegui prima lo script di build." >&2
        exit 1
    fi
fi

if [ ! -f "$ZIP_FILE" ]; then
    echo "❌ File zip non trovato: $ZIP_FILE" >&2
    exit 1
fi

echo "🔍 Verifico: $(basename "$ZIP_FILE")"
echo ""

# Crea una directory temporanea per estrarre lo zip
TEMP_DIR=$(mktemp -d)
trap "rm -rf '$TEMP_DIR'" EXIT

# Estrai lo zip
unzip -q "$ZIP_FILE" -d "$TEMP_DIR"

# Trova la directory principale (dovrebbe essere fp-experiences/)
PLUGIN_DIR=$(find "$TEMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n 1)
if [ -z "$PLUGIN_DIR" ]; then
    echo "❌ Struttura zip non valida: nessuna cartella trovata" >&2
    exit 1
fi

PLUGIN_NAME=$(basename "$PLUGIN_DIR")
echo "📦 Cartella plugin: $PLUGIN_NAME"
echo ""

# Lista dei file critici che DEVONO essere presenti
CRITICAL_FILES=(
    "$PLUGIN_NAME/fp-experiences.php"
    "$PLUGIN_NAME/src/Plugin.php"
    "$PLUGIN_NAME/src/Activation.php"
    "$PLUGIN_NAME/vendor/autoload.php"
    "$PLUGIN_NAME/composer.json"
    "$PLUGIN_NAME/readme.txt"
    "$PLUGIN_NAME/uninstall.php"
)

# Lista delle directory critiche che DEVONO essere presenti
CRITICAL_DIRS=(
    "$PLUGIN_NAME/src"
    "$PLUGIN_NAME/src/Admin"
    "$PLUGIN_NAME/src/Booking"
    "$PLUGIN_NAME/src/Front"
    "$PLUGIN_NAME/assets"
    "$PLUGIN_NAME/assets/js"
    "$PLUGIN_NAME/assets/css"
    "$PLUGIN_NAME/templates"
    "$PLUGIN_NAME/vendor"
    "$PLUGIN_NAME/languages"
)

# File che NON dovrebbero essere presenti (dev/test files)
FORBIDDEN_PATTERNS=(
    "*/tests/*"
    "*/docs/*"
    "*/.git/*"
    "*/.github/*"
    "*/node_modules/*"
    "*.md"
    "*.log"
    "*.map"
    "phpcs.xml*"
    "phpunit.xml*"
    "phpstan.neon*"
    ".gitignore"
    ".gitattributes"
    "composer.lock"
    "build.sh"
    "*.sh"
)

# Verifica file critici
echo "✅ Verifica file critici:"
MISSING_FILES=0
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$TEMP_DIR/$file" ]; then
        echo "   ✓ $file"
    else
        echo "   ✗ MANCANTE: $file"
        MISSING_FILES=$((MISSING_FILES + 1))
    fi
done
echo ""

# Verifica directory critiche
echo "✅ Verifica directory critiche:"
MISSING_DIRS=0
for dir in "${CRITICAL_DIRS[@]}"; do
    if [ -d "$TEMP_DIR/$dir" ]; then
        FILE_COUNT=$(find "$TEMP_DIR/$dir" -type f | wc -l)
        echo "   ✓ $dir ($FILE_COUNT files)"
    else
        echo "   ✗ MANCANTE: $dir"
        MISSING_DIRS=$((MISSING_DIRS + 1))
    fi
done
echo ""

# Verifica che vendor/ contenga le dipendenze
echo "✅ Verifica dipendenze Composer:"
if [ -d "$TEMP_DIR/$PLUGIN_NAME/vendor" ]; then
    VENDOR_DIRS=$(find "$TEMP_DIR/$PLUGIN_NAME/vendor" -mindepth 1 -maxdepth 1 -type d ! -name "composer" | wc -l)
    if [ "$VENDOR_DIRS" -gt 0 ]; then
        echo "   ✓ vendor/ contiene $VENDOR_DIRS package(s)"
        find "$TEMP_DIR/$PLUGIN_NAME/vendor" -mindepth 1 -maxdepth 1 -type d ! -name "composer" -exec basename {} \; | while read pkg; do
            echo "      - $pkg"
        done
    else
        echo "   ⚠️  vendor/ è vuota o contiene solo composer/"
    fi
else
    echo "   ✗ vendor/ non trovata"
fi
echo ""

# Verifica file che non dovrebbero essere presenti
echo "🚫 Verifica file non desiderati:"
FORBIDDEN_FOUND=0
for pattern in "${FORBIDDEN_PATTERNS[@]}"; do
    if [ "$pattern" = "*.md" ]; then
        # Cerca file .md nella root o in sottocartelle (escluso vendor)
        FOUND=$(find "$TEMP_DIR/$PLUGIN_NAME" -type f -name "*.md" ! -path "*/vendor/*" 2>/dev/null || true)
    elif [ "$pattern" = "*.sh" ]; then
        FOUND=$(find "$TEMP_DIR/$PLUGIN_NAME" -type f -name "*.sh" 2>/dev/null || true)
    elif [[ "$pattern" == */* ]]; then
        # Pattern con directory
        FOUND=$(find "$TEMP_DIR/$PLUGIN_NAME" -path "$TEMP_DIR/$PLUGIN_NAME/${pattern#*/}" 2>/dev/null || true)
    else
        # File singoli
        FOUND=$(find "$TEMP_DIR/$PLUGIN_NAME" -name "$pattern" 2>/dev/null || true)
    fi
    
    if [ -n "$FOUND" ]; then
        echo "   ⚠️  Trovato: $pattern"
        echo "$FOUND" | sed 's|^|      |'
        FORBIDDEN_FOUND=$((FORBIDDEN_FOUND + 1))
    fi
done

if [ "$FORBIDDEN_FOUND" -eq 0 ]; then
    echo "   ✓ Nessun file non desiderato trovato"
fi
echo ""

# Verifica che i file PHP siano aggiornati rispetto al repository
echo "🔄 Verifica aggiornamento file PHP critici:"
PHP_FILES=(
    "fp-experiences.php"
    "src/Plugin.php"
    "src/Activation.php"
)

OUTDATED=0
for php_file in "${PHP_FILES[@]}"; do
    REPO_FILE="$REPO_DIR/$php_file"
    ZIP_FILE_PATH="$TEMP_DIR/$PLUGIN_NAME/$php_file"
    
    if [ -f "$REPO_FILE" ] && [ -f "$ZIP_FILE_PATH" ]; then
        if diff -q "$REPO_FILE" "$ZIP_FILE_PATH" > /dev/null 2>&1; then
            echo "   ✓ $php_file è aggiornato"
        else
            echo "   ✗ $php_file NON è aggiornato rispetto al repository"
            OUTDATED=$((OUTDATED + 1))
        fi
    fi
done
echo ""

# Verifica versione
echo "📌 Verifica versione:"
ZIP_VERSION=$(grep -E '^\s*\*\s*Version:' "$TEMP_DIR/$PLUGIN_NAME/fp-experiences.php" | head -n 1 | sed -E 's/^\s*\*\s*Version:\s*//;s/\s*$//')
REPO_VERSION=$(grep -E '^\s*\*\s*Version:' "$REPO_DIR/fp-experiences.php" | head -n 1 | sed -E 's/^\s*\*\s*Version:\s*//;s/\s*$//')

echo "   ZIP:  $ZIP_VERSION"
echo "   REPO: $REPO_VERSION"

if [ "$ZIP_VERSION" = "$REPO_VERSION" ]; then
    echo "   ✓ Le versioni corrispondono"
else
    echo "   ✗ Le versioni NON corrispondono!"
fi
echo ""

# Statistiche finali
TOTAL_FILES=$(find "$TEMP_DIR/$PLUGIN_NAME" -type f | wc -l)
TOTAL_SIZE=$(du -sh "$TEMP_DIR/$PLUGIN_NAME" | cut -f1)

echo "📊 Statistiche:"
echo "   File totali: $TOTAL_FILES"
echo "   Dimensione: $TOTAL_SIZE"
echo ""

# Riepilogo finale
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
ERRORS=$((MISSING_FILES + MISSING_DIRS + OUTDATED))

if [ "$ERRORS" -eq 0 ] && [ "$ZIP_VERSION" = "$REPO_VERSION" ]; then
    echo "✅ VERIFICA COMPLETATA CON SUCCESSO!"
    echo ""
    echo "   Lo zip contiene tutti i file necessari e aggiornati."
    if [ "$FORBIDDEN_FOUND" -gt 0 ]; then
        echo "   ⚠️  Attenzione: trovati $FORBIDDEN_FOUND file(s) non desiderati"
    fi
else
    echo "❌ VERIFICA FALLITA!"
    echo ""
    [ "$MISSING_FILES" -gt 0 ] && echo "   - $MISSING_FILES file(s) critici mancanti"
    [ "$MISSING_DIRS" -gt 0 ] && echo "   - $MISSING_DIRS directory critiche mancanti"
    [ "$OUTDATED" -gt 0 ] && echo "   - $OUTDATED file(s) non aggiornati"
    [ "$ZIP_VERSION" != "$REPO_VERSION" ] && echo "   - Versione non corrispondente"
fi
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

exit $ERRORS
