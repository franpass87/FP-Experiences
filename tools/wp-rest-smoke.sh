#!/usr/bin/env bash
set -euo pipefail

BASE=${BASE:-http://localhost:8080}

echo "==> GET /wp-json/fp-exp/v1/ping"
code=$(curl -s -o /tmp/ping.json -w "%{http_code}" "$BASE/wp-json/fp-exp/v1/ping") || code=000
echo "HTTP $code"
if [[ "$code" != "200" ]]; then
  echo "Ping failed" >&2; exit 1
fi
cat /tmp/ping.json | jq . || cat /tmp/ping.json

echo "==> POST /wp-json/fp-exp/v1/tools/clear-cache (unauthenticated)"
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BASE/wp-json/fp-exp/v1/tools/clear-cache") || code=000
echo "HTTP $code (expected 401 if not authenticated)"
if [[ "$code" != "401" && "$code" != "403" ]]; then
  echo "Unexpected response code for protected endpoint" >&2; exit 1
fi

echo "==> Frontend smoke: /esperienze"
code=$(curl -s -o /tmp/list.html -w "%{http_code}" "$BASE/esperienze") || code=000
echo "HTTP $code"
grep -qi "data-fp-exp" /tmp/list.html || echo "(info) data-fp-exp not found; check shortcode was added"

echo "==> Frontend smoke: /checkout-esp"
code=$(curl -s -o /tmp/checkout.html -w "%{http_code}" "$BASE/checkout-esp") || code=000
echo "HTTP $code"
grep -qi "data-fp-exp" /tmp/checkout.html || echo "(info) data-fp-exp not found; check shortcode was added"

echo "✅ REST and frontend smoke completed"


