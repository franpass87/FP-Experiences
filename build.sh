#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")" && pwd)"
TOOLS_DIR="$ROOT_DIR/tools"

PLUGIN_FILE="$(php -r '
$root = $argv[1];
$candidates = glob($root . "/*.php");
foreach ($candidates as $candidate) {
    $handle = fopen($candidate, "rb");
    if ($handle === false) {
        continue;
    }
    $buffer = "";
    $linesRead = 0;
    while (! feof($handle) && $linesRead < 20) {
        $buffer .= (string) fgets($handle);
        $linesRead++;
    }
    fclose($handle);
    if (preg_match("/^\\s*\\*\\s*Plugin Name\\s*:/mi", $buffer) && preg_match("/^\\s*\\*\\s*Version\\s*:/mi", $buffer)) {
        echo basename($candidate);
        exit(0);
    }
}
exit(1);
' "$ROOT_DIR")"

if [[ -z "$PLUGIN_FILE" ]]; then
    echo "Unable to determine plugin main file" >&2
    exit 1
fi

PLUGIN_SLUG="${PLUGIN_FILE%.php}"
PLUGIN_PATH="$ROOT_DIR/$PLUGIN_FILE"

SET_VERSION=""
BUMP_TYPE="patch"
ZIP_NAME=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --set-version)
            [[ $# -ge 2 ]] || { echo "Missing value for --set-version" >&2; exit 1; }
            SET_VERSION="$2"
            shift 2
            ;;
        --set-version=*)
            SET_VERSION="${1#*=}"
            shift 1
            ;;
        --bump)
            [[ $# -ge 2 ]] || { echo "Missing value for --bump" >&2; exit 1; }
            BUMP_TYPE="$2"
            shift 2
            ;;
        --bump=*)
            BUMP_TYPE="${1#*=}"
            shift 1
            ;;
        --zip-name)
            [[ $# -ge 2 ]] || { echo "Missing value for --zip-name" >&2; exit 1; }
            ZIP_NAME="${2}"
            shift 2
            ;;
        --zip-name=*)
            ZIP_NAME="${1#*=}"
            shift 1
            ;;
        --help|-h)
            cat <<USAGE
Usage: bash build.sh [options]
  --set-version=X.Y.Z  Set an explicit version.
  --bump=patch|minor|major  Bump the current version (default: patch).
  --zip-name=name.zip  Override the generated zip file name.
USAGE
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

if [[ -n "$SET_VERSION" && -n "$BUMP_TYPE" && "$BUMP_TYPE" != "patch" ]]; then
    echo "--set-version cannot be combined with --bump" >&2
    exit 1
fi

if [[ -n "$SET_VERSION" ]]; then
    php "$TOOLS_DIR/bump-version.php" --set="$SET_VERSION"
else
    case "$BUMP_TYPE" in
        major|minor|patch)
            php "$TOOLS_DIR/bump-version.php" --"$BUMP_TYPE"
            ;;
        *)
            echo "Invalid bump type: $BUMP_TYPE" >&2
            exit 1
            ;;
    esac
fi

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer dump-autoload -o

BUILD_ROOT="$ROOT_DIR/build"
TARGET_DIR="$BUILD_ROOT/$PLUGIN_SLUG"

rm -rf "$TARGET_DIR"
mkdir -p "$TARGET_DIR"

RSYNC_EXCLUDES=(
    "--exclude=.git"
    "--exclude=.github"
    "--exclude=tests"
    "--exclude=docs"
    "--exclude=node_modules"
    "--exclude=*.md"
    "--exclude=.idea"
    "--exclude=.vscode"
    "--exclude=build"
    "--exclude=.gitattributes"
    "--exclude=.gitignore"
    "--exclude=phpcs.xml*"
    "--exclude=tools"
    "--exclude=build.sh"
    "--exclude=.codex-progress.json"
    "--exclude=.rebuild-state.json"
)

rsync -a --delete "${RSYNC_EXCLUDES[@]}" "$ROOT_DIR/" "$TARGET_DIR/"

if [[ -z "$ZIP_NAME" ]]; then
    TIMESTAMP="$(date +%Y%m%d%H%M)"
    ZIP_NAME="$PLUGIN_SLUG-$TIMESTAMP.zip"
fi

ZIP_PATH="$BUILD_ROOT/$ZIP_NAME"
rm -f "$ZIP_PATH"

(
    cd "$BUILD_ROOT"
    zip -rq "$(basename "$ZIP_PATH")" "$PLUGIN_SLUG"
)

if ! FINAL_VERSION=$(php -r '
$file = $argv[1];
$contents = file_get_contents($file);
if ($contents === false) {
    exit(1);
}
if (! preg_match("/^\\s*\\*\\s*Version\\s*:\\s*(\\S+)/mi", $contents, $matches)) {
    exit(1);
}
echo $matches[1];
' "$PLUGIN_PATH"); then
    echo "Failed to read final version." >&2
    exit 1
fi

echo "Version: $FINAL_VERSION"
echo "Zip: $ZIP_PATH"
