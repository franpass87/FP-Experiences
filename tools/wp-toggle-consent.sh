#!/usr/bin/env bash
set -euo pipefail

# Toggle tracking consent defaults and minimal config so snippets can render.
# Usage:
#   MODE=on  bash tools/wp-toggle-consent.sh
#   MODE=off bash tools/wp-toggle-consent.sh

MODE=${MODE:-on}

if [[ "$MODE" != "on" && "$MODE" != "off" ]]; then
  echo "MODE must be 'on' or 'off'" >&2
  exit 1
fi

php -d detect_unicode=0 -r '
require_once "/var/www/html/wp-load.php";
$settings = get_option("fp_exp_tracking", []);
if (!is_array($settings)) { $settings = []; }

$enabled = ($argv[1] === "on");

// Ensure channels exist and are enabled with dummy IDs
$settings["ga4"] = is_array($settings["ga4"] ?? null) ? $settings["ga4"] : [];
$settings["ga4"]["enabled"] = true; // keep channel enabled
if (empty($settings["ga4"]["gtm_id"]) && empty($settings["ga4"]["measurement_id"])) {
  $settings["ga4"]["gtm_id"] = "GTM-TEST"; // GTM fake id is enough to render snippet
}

$settings["google_ads"] = is_array($settings["google_ads"] ?? null) ? $settings["google_ads"] : [];
$settings["google_ads"]["enabled"] = true;
if (empty($settings["google_ads"]["conversion_id"])) {
  $settings["google_ads"]["conversion_id"] = "AW-TEST";
}
if (empty($settings["google_ads"]["conversion_label"])) {
  $settings["google_ads"]["conversion_label"] = "LABELTEST";
}

$settings["meta_pixel"] = is_array($settings["meta_pixel"] ?? null) ? $settings["meta_pixel"] : [];
$settings["meta_pixel"]["enabled"] = true;
if (empty($settings["meta_pixel"]["pixel_id"])) {
  $settings["meta_pixel"]["pixel_id"] = "1234567890";
}

$settings["clarity"] = is_array($settings["clarity"] ?? null) ? $settings["clarity"] : [];
$settings["clarity"]["enabled"] = true;
if (empty($settings["clarity"]["project_id"])) {
  $settings["clarity"]["project_id"] = "clarity-test";
}

// Consent defaults
$settings["consent_defaults"] = [
  "ga4" => $enabled,
  "google_ads" => $enabled,
  "meta_pixel" => $enabled,
  "clarity" => $enabled,
];

update_option("fp_exp_tracking", $settings);
echo $enabled ? "Consent ON" : "Consent OFF";
' "$MODE"

echo "\n✅ Tracking consent set to $MODE."


