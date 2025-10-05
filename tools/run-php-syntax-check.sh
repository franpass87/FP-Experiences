#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

search_dirs=()
for dir in src templates build/fp-experiences/src build/fp-experiences/templates tests; do
    if [ -d "${ROOT_DIR}/${dir}" ]; then
        search_dirs+=("${ROOT_DIR}/${dir}")
    fi
done

php_files=()
if [ ${#search_dirs[@]} -gt 0 ]; then
    while IFS= read -r -d '' file; do
        php_files+=("$file")
    done < <(find "${search_dirs[@]}" -type f -name '*.php' -print0)
fi

for file in "${ROOT_DIR}/fp-experiences.php" "${ROOT_DIR}/uninstall.php"; do
    if [ -f "$file" ]; then
        php_files+=("$file")
    fi
done

if [ ${#php_files[@]} -eq 0 ]; then
    echo "No PHP files found to lint." >&2
    exit 0
fi

checked=0
for file in "${php_files[@]}"; do
    php -l "$file" > /dev/null
    checked=$((checked + 1))
done

echo "PHP syntax check passed for ${checked} files."
