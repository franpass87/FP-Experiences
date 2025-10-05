#!/usr/bin/env bash
set -euo pipefail

# Mark an FP Experiences WooCommerce order as paid to trigger emails/ICS/Brevo.
# Usage:
#   bash tools/wp-order-complete.sh [ORDER_ID]

ORDER_ID=${1:-}

php -d detect_unicode=0 -r '
require_once "/var/www/html/wp-load.php";

function complete_order($order_id){
  if (!function_exists("wc_get_order")) { echo "WooCommerce missing\n"; return 1; }
  $order = wc_get_order((int)$order_id);
  if (!$order) { echo "Order not found\n"; return 1; }
  $order->payment_complete();
  echo "Completed order: ".$order->get_id()."\n";
  return 0;
}

$arg = (int)($argv[1] ?? 0);
if ($arg > 0) {
  exit(complete_order($arg));
}

// Find the latest pending order created via fp-exp
$query = new WP_Query([
  "post_type" => "shop_order",
  "post_status" => ["wc-pending","wc-processing"],
  "posts_per_page" => 1,
  "orderby" => "date",
  "order" => "DESC",
  "meta_query" => [[
    "key" => "_fp_exp_isolated_checkout",
    "value" => "yes",
  ]]
]);

if (!$query->have_posts()) { echo "No pending fp-exp orders found\n"; exit(1); }
$post = $query->posts[0];
exit(complete_order($post->ID));
' "$ORDER_ID"

echo "✅ Marked order paid"


