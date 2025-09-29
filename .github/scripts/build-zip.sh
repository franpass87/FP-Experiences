#!/usr/bin/env sh
set -eu

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

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT INT TERM HUP

STAGE_DIR="$TMP_DIR/fp-experiences"
mkdir -p "$STAGE_DIR"

rsync -a "$REPO_DIR/" "$STAGE_DIR/" \
  --exclude '.git/' \
  --exclude '.gitignore' \
  --exclude '.gitattributes' \
  --exclude '.github/' \
  --exclude 'docs/' \
  --exclude 'node_modules/' \
  --exclude 'vendor/' \
  --exclude 'composer.json' \
  --exclude 'composer.lock' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'phpcs.xml' \
  --exclude 'phpcs.xml.dist' \
  --exclude '*.map' \
  --exclude '*.min.*' \
  --exclude '*.zip' \
  --exclude '*.log' \
  --exclude '*.sh' \
  --exclude '*.md'

rm -rf "$STAGE_DIR/docs"

if [ -f "$ZIP_PATH" ]; then
  rm -f "$ZIP_PATH"
fi

( cd "$TMP_DIR" && zip -rq "$ZIP_PATH" fp-experiences )

if [ ! -f "$ZIP_PATH" ]; then
  echo "Error: ZIP archive was not created" >&2
  exit 1
fi

echo "Created $ZIP_PATH"
