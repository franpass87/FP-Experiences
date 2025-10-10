#!/usr/bin/env bash
# Script per aggiornare rapidamente la versione del plugin

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
PLUGIN_FILE="$REPO_DIR/fp-experiences.php"

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

print_usage() {
    cat <<EOF
🔧 Update Plugin Version

Uso: $0 <nuova-versione>

Esempi:
  $0 0.3.7        # Aggiorna a versione 0.3.7
  $0 1.0.0        # Aggiorna a versione 1.0.0

Questo script aggiorna la versione in:
  - fp-experiences.php (Plugin Name header)
  - fp-experiences.php (FP_EXP_VERSION constant)

EOF
}

if [ $# -eq 0 ]; then
    echo -e "${RED}❌ Errore: Versione non specificata${NC}"
    echo
    print_usage
    exit 1
fi

NEW_VERSION="$1"

# Valida il formato della versione (semantic versioning)
if ! echo "$NEW_VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+$'; then
    echo -e "${RED}❌ Errore: Formato versione non valido${NC}"
    echo "   La versione deve essere nel formato X.Y.Z (es. 0.3.7)"
    exit 1
fi

if [ ! -f "$PLUGIN_FILE" ]; then
    echo -e "${RED}❌ Errore: File plugin non trovato: $PLUGIN_FILE${NC}"
    exit 1
fi

# Ottieni la versione attuale
CURRENT_VERSION=$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" | head -n 1 | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//;s/[[:space:]]*$//')

echo -e "${YELLOW}📦 Aggiornamento versione plugin${NC}"
echo "   Versione attuale: $CURRENT_VERSION"
echo "   Nuova versione:   $NEW_VERSION"
echo

# Conferma
read -p "Procedere con l'aggiornamento? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo -e "${YELLOW}⚠️  Operazione annullata${NC}"
    exit 0
fi

# Backup del file originale
cp "$PLUGIN_FILE" "$PLUGIN_FILE.bak"

# Aggiorna la versione nell'header del plugin
sed -i.tmp "s/^\([[:space:]]*\*[[:space:]]*Version:[[:space:]]*\).*/\1$NEW_VERSION/" "$PLUGIN_FILE"

# Aggiorna la costante FP_EXP_VERSION
sed -i.tmp "s/define('FP_EXP_VERSION', '[^']*')/define('FP_EXP_VERSION', '$NEW_VERSION')/" "$PLUGIN_FILE"

# Rimuovi file temporanei
rm -f "$PLUGIN_FILE.tmp"

# Verifica che la modifica sia avvenuta
NEW_VERSION_CHECK=$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" | head -n 1 | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//;s/[[:space:]]*$//')

if [ "$NEW_VERSION_CHECK" = "$NEW_VERSION" ]; then
    echo -e "${GREEN}✅ Versione aggiornata con successo!${NC}"
    echo
    echo "Modifiche effettuate:"
    echo "  • Plugin header: Version: $NEW_VERSION"
    echo "  • Costante PHP:  FP_EXP_VERSION = '$NEW_VERSION'"
    echo
    echo "Backup salvato in: $PLUGIN_FILE.bak"
    echo
    echo -e "${YELLOW}📝 Prossimi passi:${NC}"
    echo "  1. Verifica le modifiche: git diff fp-experiences.php"
    echo "  2. Commit le modifiche:   git commit -am 'Bump version to $NEW_VERSION'"
    echo "  3. Push su main:          git push origin main"
    echo "  4. Il workflow automatico creerà la release!"
else
    echo -e "${RED}❌ Errore: Aggiornamento fallito${NC}"
    echo "   Ripristino backup..."
    mv "$PLUGIN_FILE.bak" "$PLUGIN_FILE"
    exit 1
fi
