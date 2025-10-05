#!/usr/bin/env bash
set -euo pipefail

WP_URL=${WP_URL:-http://localhost:8080}
WP_TITLE=${WP_TITLE:-FP Experiences Dev}
WP_ADMIN_USER=${WP_ADMIN_USER:-admin}
WP_ADMIN_PASS=${WP_ADMIN_PASS:-admin}
WP_ADMIN_EMAIL=${WP_ADMIN_EMAIL:-admin@example.com}

wait_for_wp() {
  until wp core is-installed --allow-root >/dev/null 2>&1; do
    echo "Waiting for WordPress to be ready..."
    sleep 3
  done
}

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
  wp core install --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --allow-root
fi

wp rewrite structure '/%postname%/' --hard --allow-root

wp plugin install woocommerce --activate --allow-root

# Activate FP Experiences from the mounted plugin directory
if wp plugin is-installed fp-experiences --allow-root; then
  wp plugin activate fp-experiences --allow-root || true
else
  # In case the slug differs, try activating by path
  wp plugin activate fp-experiences/fp-experiences.php --allow-root || true
fi

echo "✅ WP ready at $WP_URL"

