#!/usr/bin/env bash
# Seeds the WordPress + WooCommerce sandbox (wordpress/docker-compose.yml) for plugin development:
# store address in Seattle, products with weights and dimensions, a shipping zone, dev constants.
# Usage: scripts/seed-store.sh  (run from anywhere; uses the wpcli compose service)
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
COMPOSE_DIR="${COMPOSE_DIR:-${HERE}/../../wordpress}"
wp() { (cd "${COMPOSE_DIR}" && docker compose run --rm -T wpcli "$@"); }

echo "==> Store address and currency"
wp option update woocommerce_store_address "400 Broad St" >/dev/null
wp option update woocommerce_store_address_2 "" >/dev/null
wp option update woocommerce_store_city "Seattle" >/dev/null
wp option update woocommerce_store_postcode "98109" >/dev/null
wp option update woocommerce_default_country "US:WA" >/dev/null
wp option update woocommerce_currency "USD" >/dev/null
wp option update woocommerce_weight_unit "kg" >/dev/null
wp option update woocommerce_dimension_unit "cm" >/dev/null
wp option update timezone_string "America/Los_Angeles" >/dev/null
wp option update woocommerce_calc_taxes "no" >/dev/null
wp option update woocommerce_enable_guest_checkout "yes" >/dev/null

echo "==> Payment: enable cash on delivery and bank transfer for test checkouts"
wp option patch update woocommerce_cod_settings enabled yes >/dev/null 2>&1 || wp option update woocommerce_cod_settings '{"enabled":"yes","title":"Cash on delivery","description":"Pay the driver on delivery.","instructions":"","enable_for_methods":"","enable_for_virtual":"no"}' --format=json >/dev/null
wp option patch update woocommerce_bacs_settings enabled yes >/dev/null 2>&1 || wp option update woocommerce_bacs_settings '{"enabled":"yes","title":"Direct bank transfer","description":"","instructions":"","account_details":""}' --format=json >/dev/null

echo "==> Products"
create_product() { # name price weight length width height sku
  if wp post list --post_type=product --title="$1" --format=ids | grep -q .; then echo "exists: $1"; return; fi
  wp wc product create --user=1 --name="$1" --type=simple --regular_price="$2" --weight="$3" --dimensions="{\"length\":\"$4\",\"width\":\"$5\",\"height\":\"$6\"}" --sku="$7" --manage_stock=false --status=publish --porcelain >/dev/null && echo "created: $1"
}
create_product "Sourdough Loaf" 8.50 0.9 30 15 10 WOO-BREAD
create_product "Weekly Veg Box" 32.00 6.5 45 35 25 WOO-VEGBOX
create_product "Case of Sparkling Water (12)" 18.00 13.2 40 27 25 WOO-WATER
create_product "Potted Olive Tree" 65.00 9.0 40 40 90 WOO-OLIVE

echo "==> Shipping zone: Washington state"
wp eval '
$zones = WC_Shipping_Zones::get_zones();
foreach ( $zones as $z ) { if ( "Washington" === $z["zone_name"] ) { echo "zone exists\n"; return; } }
$zone = new WC_Shipping_Zone();
$zone->set_zone_name( "Washington" );
$zone->set_locations( [ [ "code" => "US:WA", "type" => "state" ] ] );
$zone->save();
echo "created zone " . $zone->get_id() . " (add the Fleetbase Delivery method from the wizard or WooCommerce > Settings > Shipping)\n";
'

echo "==> Dev constants in wp-config.php"
wp config set FLEETBASE_WC_ALLOW_CUSTOM_HOST true --raw --type=constant >/dev/null
wp config set FLEETBASE_WC_DEFAULT_HOST "http://host.docker.internal:8000" --type=constant >/dev/null
wp config set WP_DEBUG true --raw --type=constant >/dev/null
wp config set WP_DEBUG_LOG true --raw --type=constant >/dev/null
wp config set WP_DEBUG_DISPLAY false --raw --type=constant >/dev/null

echo "==> Activate plugin"
wp plugin activate fleetbase-for-woocommerce >/dev/null && echo "activated"
echo "Done. Open http://localhost:8800/wp-admin/admin.php?page=fleetbase-setup"
