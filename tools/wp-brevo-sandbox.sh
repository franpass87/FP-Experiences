#!/usr/bin/env bash
set -euo pipefail

# Configure Brevo with dummy/sandbox-like settings and simulate a reservation paid event.
# Usage:
#   bash tools/wp-brevo-sandbox.sh

API_KEY=${BREVO_API_KEY:-BREVO_TEST_KEY}
TEMPLATE_CONFIRM=${BREVO_TEMPLATE_CONFIRM:-1}
TEMPLATE_CANCEL=${BREVO_TEMPLATE_CANCEL:-2}
WEBHOOK_SECRET=${BREVO_WEBHOOK_SECRET:-secret}

# Store settings
php -d detect_unicode=0 -r '
require_once "/var/www/html/wp-load.php";
$settings = get_option("fp_exp_brevo", []);
if (!is_array($settings)) { $settings = []; }
$settings["enabled"] = true;
$settings["api_key"] = getenv("API_KEY");
$settings["webhook_secret"] = getenv("WEBHOOK_SECRET");
$settings["templates"] = [
  "confirmation" => (int) getenv("TEMPLATE_CONFIRM"),
  "cancel" => (int) getenv("TEMPLATE_CANCEL")
];
update_option("fp_exp_brevo", $settings);
echo "Saved Brevo settings";
'

echo "✅ Brevo sandbox configured"
