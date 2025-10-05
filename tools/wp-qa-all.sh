#!/usr/bin/env bash
set -euo pipefail

# End-to-end quick QA:
# 1) Seed experience + slots
# 2) Create pages with shortcodes
# 3) Toggle consent ON
# 4) Smoke REST/frontend
# 5) Smoke checkout

BASE=${BASE:-http://localhost:8080}

echo "==> Seeding demo experience"
EXP_ID=$(docker compose run --rm wpcli bash -lc "bash /var/www/html/wp-content/plugins/fp-experiences/tools/wp-seed-experience.sh" | tail -n1 | tr -d '\r')
echo "Seeded EXP_ID=$EXP_ID"

echo "==> Creating pages"
docker compose run --rm -e EXP_ID="$EXP_ID" wpcli bash -lc "bash /var/www/html/wp-content/plugins/fp-experiences/tools/wp-pages.sh"

echo "==> Toggle tracking consent ON"
docker compose run --rm wpcli bash -lc "MODE=on bash /var/www/html/wp-content/plugins/fp-experiences/tools/wp-toggle-consent.sh"

echo "==> REST/frontend smoke"
docker compose run --rm wpcli bash -lc "bash /var/www/html/wp-content/plugins/fp-experiences/tools/wp-rest-smoke.sh"

echo "==> Checkout smoke"
docker compose run --rm -e EXP_ID="$EXP_ID" wpcli bash -lc "bash /var/www/html/wp-content/plugins/fp-experiences/tools/wp-checkout-smoke.sh"

echo "✅ QA all done — EXP_ID=$EXP_ID"


