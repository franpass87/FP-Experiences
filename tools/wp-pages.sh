#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   EXP_ID=123 bash tools/wp-pages.sh
# If EXP_ID is not provided, experience pages using id-dependent shortcodes will be skipped.

EXP_ID=${EXP_ID:-}

create_page() {
  local title="$1"
  local content="$2"
  local slug="$3"

  local existing_id
  existing_id=$(wp post list --post_type=page --name="$slug" --field=ID --allow-root)
  if [[ -n "$existing_id" ]]; then
    wp post update "$existing_id" --post_title="$title" --post_content="$content" --post_status=publish --allow-root >/dev/null
    echo "Updated page: $title ($slug)"
  else
    wp post create --post_type=page --post_title="$title" --post_name="$slug" --post_content="$content" --post_status=publish --allow-root >/dev/null
    echo "Created page: $title ($slug)"
  fi
}

# List (archive advanced)
create_page "Esperienze" "[fp_exp_list]" "esperienze"

# Checkout
create_page "Checkout Esperienze" "[fp_exp_checkout]" "checkout-esp"

if [[ -n "$EXP_ID" ]]; then
  # Single Experience (full page layout)
  create_page "Esperienza Demo" "[fp_exp_experience id=\"$EXP_ID\"]" "esperienza-demo"

  # Widget (sidebar widget with calendar)
  create_page "Widget Esperienza" "[fp_exp_widget id=\"$EXP_ID\" sticky=\"1\" show_calendar=\"1\"]" "widget-esp"
fi

echo "✅ Pages ready. Remember to set the created pages in your menus as needed."


