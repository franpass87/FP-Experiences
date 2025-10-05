#!/usr/bin/env bash
set -euo pipefail

# Creates a demo Experience with tickets/addons/meta and generates a few slots.
# Usage:
#   bash tools/wp-seed-experience.sh

title=${EXP_TITLE:-Esperienza Demo}

post_id=$(wp post create --post_type=fp_experience --post_status=publish --post_title="$title" --porcelain --allow-root)
echo "Created Experience ID: $post_id"

# Basic meta (duration/base price)
wp post meta update "$post_id" _fp_duration_minutes 90 --allow-root
wp post meta update "$post_id" _fp_base_price 0 --allow-root

# Tickets
tickets='[
  {"slug":"adulto","label":"Adulto","price":25,"min":0,"max":0,"capacity":0},
  {"slug":"bambino","label":"Bambino","price":10,"min":0,"max":0,"capacity":0}
]'
wp post meta update "$post_id" _fp_ticket_types "$tickets" --allow-root

# Addons (optional)
addons='[
  {"slug":"cuffie","label":"Cuffie","price":5,"allow_multiple":true,"max":0}
]'
wp post meta update "$post_id" _fp_addons "$addons" --allow-root

# Availability: generate a couple of upcoming slots via recurrence API through REST would require auth; instead we insert via direct SQL helper using PHP eval.
# We will create two slots starting tomorrow at 10:00 and 15:00 local time, 90 min duration, capacity 12.

php -d detect_unicode=0 -r '
require_once "/var/www/html/wp-load.php";
\FP_Exp\Plugin::instance()->register_database_tables();
$tz = wp_timezone();
$tomorrow = new DateTimeImmutable("tomorrow", $tz);
foreach (["10:00","15:00"] as $time) {
  $startLocal = new DateTimeImmutable($tomorrow->format("Y-m-d")." ".$time, $tz);
  $endLocal = $startLocal->add(new DateInterval("PT90M"));
  $startUtc = $startLocal->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d H:i:s");
  $endUtc = $endLocal->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d H:i:s");
  \FP_Exp\Booking\Slots::generate_recurring_slots((int)$argv[1], [
    ["type"=>"specific","dates":[ $startLocal->format("Y-m-d H:i") ], "duration"=>90, "capacity_total"=>12]
  ], [], ["replace_existing"=>false]);
}
' "$post_id"

echo "✅ Seeded Experience $post_id with tickets, addons and slots."
echo "$post_id"


