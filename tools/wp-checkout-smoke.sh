#!/usr/bin/env bash
set -euo pipefail

BASE=${BASE:-http://localhost:8080}

# Find an experience and its first upcoming slot
EXP_ID=${EXP_ID:-}
if [[ -z "$EXP_ID" ]]; then
  EXP_ID=$(docker compose run --rm wpcli bash -lc "wp post list --post_type=fp_experience --field=ID --allow-root | head -n1" | tr -d '\r')
fi

if [[ -z "$EXP_ID" ]]; then
  echo "No fp_experience found. Run wp-seed-experience.sh first." >&2
  exit 1
fi

echo "Using experience ID: $EXP_ID"

# Query first upcoming slot via php (prints slot ID and start UTC)
read -r SLOT_ID SLOT_START <<<"$(docker compose run --rm wpcli bash -lc "php -r 'require_once "/var/www/html/wp-load.php"; \$s=\\FP_Exp\\Booking\\Slots::get_upcoming_for_experience((int)$EXP_ID,1); if (!\$s){echo "0 0"; exit; } echo (int)\$s[0]["id"]." ".\$s[0]["start_datetime"];'" | tr -d '\r')"

if [[ "$SLOT_ID" == "0" ]]; then
  echo "No upcoming slots. Seed slots first." >&2
  exit 1
fi

echo "Using slot ID: $SLOT_ID (start UTC: $SLOT_START)"

# Build a minimal cart via PHP (sets the transient using Cart API in the same container)
docker compose run --rm wpcli bash -lc "php -r '
require_once "/var/www/html/wp-load.php";
\FP_Exp\\Booking\\Cart::instance()->bootstrap_session();
\$session = \FP_Exp\\Booking\\Cart::instance()->get_session_id();
\$items = [[
  "experience_id" => (int) getenv("EXP_ID"),
  "slot_id" => (int) getenv("SLOT_ID"),
  "tickets" => ["adulto" => 2, "bambino" => 1],
  "addons" => ["cuffie" => 1]
]];
\FP_Exp\\Booking\\Cart::instance()->set_items(\$items, ["currency"=>get_option("woocommerce_currency","EUR")]);
echo \$session;'
" | tr -d '\r' > /tmp/fp_sid.txt

SID=$(cat /tmp/fp_sid.txt)
if [[ -z "$SID" ]]; then
  echo "Failed to create cart session" >&2
  exit 1
fi

echo "Cart session: $SID"

# Prepare checkout payload
NONCE=$(docker compose run --rm wpcli bash -lc "php -r 'require_once "/var/www/html/wp-load.php"; echo wp_create_nonce("fp-exp-checkout");'" | tr -d '\r')

read -r HTTP_CODE RESPONSE <<<"$(curl -s -w " %{http_code}" -b "fp_exp_sid=$SID" -H "x-wp-nonce: $NONCE" -H "Content-Type: application/json" \
  -X POST "$BASE/wp-json/fp-exp/v1/checkout" \
  --data '{
    "nonce": "'$NONCE'",
    "contact": {"email":"demo@example.com","first_name":"Demo","last_name":"User"},
    "billing": {"country":"IT"},
    "consent": {"tos": true}
  }')

echo "$RESPONSE"
echo "HTTP $HTTP_CODE"

if [[ "$HTTP_CODE" != "200" ]]; then
  echo "Checkout smoke failed" >&2
  exit 1
fi

echo "✅ Checkout smoke completed"


