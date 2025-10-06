#!/usr/bin/env bash
# Script rapido per sincronizzare le modifiche nel build senza bump versione
set -eu

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
BUILD_ROOT="$ROOT_DIR/build"
TARGET_DIR="$BUILD_ROOT/fp-experiences"

# Verifica che la directory build esista
if [[ ! -d "$TARGET_DIR" ]]; then
    echo "❌ Build directory non trovata. Esegui prima: bash build.sh"
    exit 1
fi

echo "🔄 Sincronizzazione modifiche nel build..."

# Copia ricorsiva delle directory modificate
echo "  📂 Copiando src/..."
cp -rf "$ROOT_DIR/src/"* "$TARGET_DIR/src/" 2>/dev/null || true

echo "  🎨 Copiando assets/..."
cp -rf "$ROOT_DIR/assets/"* "$TARGET_DIR/assets/" 2>/dev/null || true

echo "  📄 Copiando templates/..."
cp -rf "$ROOT_DIR/templates/"* "$TARGET_DIR/templates/" 2>/dev/null || true

# Copia anche i file root che potrebbero cambiare
echo "  📝 Copiando file root..."
cp "$ROOT_DIR/fp-experiences.php" "$TARGET_DIR/" 2>/dev/null || true
cp "$ROOT_DIR/readme.txt" "$TARGET_DIR/" 2>/dev/null || true
cp "$ROOT_DIR/uninstall.php" "$TARGET_DIR/" 2>/dev/null || true

# Rigenera l'autoloader per assicurare che le modifiche vengano caricate
if [[ -d "$TARGET_DIR/vendor" ]]; then
    echo "  🔄 Rigenerando autoloader..."
    (cd "$TARGET_DIR" && composer dump-autoload -o 2>/dev/null) || echo "  ⚠️  Impossibile rigenerare autoloader (opzionale)"
fi

echo "✅ Build sincronizzato con successo!"
echo "📁 Modifiche applicate in: $TARGET_DIR"
