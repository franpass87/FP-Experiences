#!/usr/bin/env bash
set -euo pipefail

SLUG="fp-experiences"
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)
PLUGIN_FILE="$REPO_DIR/fp-experiences.php"

if [ ! -f "$PLUGIN_FILE" ]; then
  echo "Error: fp-experiences.php not found in repository root" >&2
  exit 1
fi

VERSION=$(grep -E '^[[:space:]]*\*[[:space:]]*Version:' "$PLUGIN_FILE" | head -n 1 | sed -E 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//;s/[[:space:]]*$//')

if [ -z "$VERSION" ]; then
  echo "Error: Unable to detect plugin version from fp-experiences.php" >&2
  exit 1
fi

ZIP_NAME="fp-experiences-$VERSION.zip"
ZIP_PATH="$REPO_DIR/$ZIP_NAME"

# Pulizia dist
rm -rf "$REPO_DIR/dist"
mkdir -p "$REPO_DIR/dist/$SLUG"

# Copia file plugin nella cartella slug
# IMPORTANTE: Include vendor/ perché contiene le dipendenze necessarie dopo composer install
rsync -a "$REPO_DIR/" "$REPO_DIR/dist/$SLUG/" \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.vscode' \
  --exclude '.idea' \
  --exclude 'tests' \
  --exclude 'docs' \
  --exclude 'node_modules' \
  --exclude 'dist' \
  --exclude '.gitignore' \
  --exclude '.gitattributes' \
  --exclude 'composer.lock' \
  --exclude 'phpstan.neon' \
  --exclude 'phpstan.neon.dist' \
  --exclude 'phpcs.xml' \
  --exclude 'phpcs.xml.dist' \
  --exclude 'phpunit.xml' \
  --exclude 'phpunit.xml.dist' \
  --exclude '*.md' \
  --exclude '*.map' \
  --exclude '*.log' \
  --exclude '*.sh'

# Crea lo ZIP
cd "$REPO_DIR/dist"
zip -rq "$ZIP_PATH" "$SLUG"
cd "$REPO_DIR"

if [ ! -f "$ZIP_PATH" ]; then
  echo "Error: ZIP archive was not created" >&2
  exit 1
fi

echo "✅ Creato: $ZIP_NAME"
echo "📦 Contenuto: cartella $SLUG/ con tutti i file del plugin (incluso vendor/)"
