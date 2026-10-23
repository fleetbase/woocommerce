#!/usr/bin/env bash
# Seeds the local Fleetbase dev stack (oss/fleetbase-dev) for plugin development:
# a USD company "Woo Test Store", live + test API keys, a pickup place in Seattle,
# a service area, two service rates, two drivers and two vehicles.
#
# Usage: scripts/seed-fleetbase.sh [container] [api-url]
set -euo pipefail

CONTAINER="${1:-fleetbase-dev-application-1}"
API="${2:-http://localhost:8000}"
HERE="$(cd "$(dirname "$0")" && pwd)"

echo "==> Creating company and API keys in ${CONTAINER}"
docker cp "${HERE}/seed-fleetbase.php" "${CONTAINER}:/tmp/seed-fleetbase.php"
OUT="$(docker exec "${CONTAINER}" sh -c 'cd /fleetbase/api && php artisan tinker /tmp/seed-fleetbase.php' 2>&1 | tr -d '\r')"
echo "${OUT}"
LIVE_KEY="$(echo "${OUT}" | sed -n 's/^LIVE_KEY=//p' | tail -1)"
TEST_KEY="$(echo "${OUT}" | sed -n 's/^TEST_KEY=//p' | tail -1)"
if [ -z "${LIVE_KEY}" ]; then echo "Could not read LIVE_KEY from seed output" >&2; exit 1; fi

req() { # method path [json]
  if [ $# -ge 3 ]; then
    curl -sS -X "$1" "${API}/v1/$2" -H "Authorization: Bearer ${LIVE_KEY}" -H "Content-Type: application/json" -H "Accept: application/json" -d "$3"
  else
    curl -sS -X "$1" "${API}/v1/$2" -H "Authorization: Bearer ${LIVE_KEY}" -H "Accept: application/json"
  fi
}
have() { # path name-field value  -> prints id if exists
  req GET "$1?limit=100" | python3 -I -c "import sys,json
items=json.load(sys.stdin)
for i in items:
    if str(i.get('$2',''))=='$3': print(i['id']); break"
}

echo "==> Organisation: $(req GET organizations/current | python3 -I -c 'import sys,json; print(json.load(sys.stdin).get("name"))')"

echo "==> Pickup place"
PLACE_ID="$(have places name 'WOO TEST STORE' || true)"
if [ -z "${PLACE_ID}" ]; then
  PLACE_ID="$(req POST places '{"name":"Woo Test Store","street1":"400 Broad St","city":"Seattle","province":"Washington","postal_code":"98109","country":"US","latitude":47.6205,"longitude":-122.3493,"type":"store"}' | python3 -I -c 'import sys,json; print(json.load(sys.stdin)["id"])')"
fi
echo "place: ${PLACE_ID}"

echo "==> Service area (greater Seattle)"
AREA_ID="$(have service-areas name 'Seattle Metro' || true)"
if [ -z "${AREA_ID}" ]; then
  # The public API builds the border from a centre point and radius (metres).
  AREA_ID="$(req POST service-areas '{"name":"Seattle Metro","type":"city","status":"active","country":"US","latitude":47.6205,"longitude":-122.3493,"radius":15000}' | python3 -I -c 'import sys,json; d=json.load(sys.stdin); print(d.get("id",""))' || true)"
fi
echo "service area: ${AREA_ID:-none}"

echo "==> Service rates"
AREA_JSON=""
if [ -n "${AREA_ID}" ]; then AREA_JSON=",\"service_area\":\"${AREA_ID}\""; fi
if [ -z "$(have service-rates service_name 'Standard Delivery' || true)" ]; then
  req POST service-rates "{\"service_name\":\"Standard Delivery\",\"service_type\":\"standard\",\"rate_calculation_method\":\"per_meter\",\"currency\":\"USD\",\"base_fee\":500,\"per_meter_unit\":\"km\",\"per_meter_flat_rate_fee\":150,\"duration_terms\":\"Same day, 2-4 hours\",\"estimated_days\":0${AREA_JSON}}" >/dev/null && echo "created Standard Delivery (\$5 + \$1.50/km)"
fi
if [ -z "$(have service-rates service_name 'Express Delivery' || true)" ]; then
  req POST service-rates "{\"service_name\":\"Express Delivery\",\"service_type\":\"express\",\"rate_calculation_method\":\"fixed_rate\",\"currency\":\"USD\",\"base_fee\":0,\"per_meter_unit\":\"km\",\"meter_fees\":[{\"distance\":5,\"fee\":1200},{\"distance\":15,\"fee\":1800},{\"distance\":40,\"fee\":2900}],\"duration_terms\":\"Within 90 minutes\",\"estimated_days\":0${AREA_JSON}}" >/dev/null && echo "created Express Delivery (fixed tiers)"
fi

echo "==> Attach service area to rates"
if [ -n "${AREA_ID}" ]; then
  for RID in $(req GET "service-rates?limit=50" | python3 -I -c 'import sys,json; [print(r["id"]) for r in json.load(sys.stdin)]'); do
    req PUT "service-rates/${RID}" "{\"service_area\":\"${AREA_ID}\",\"currency\":\"USD\"}" >/dev/null
  done
fi

echo "==> Vehicles"
V1="$(have vehicles plate_number 'WOO-001' || true)"; [ -z "$V1" ] && V1="$(req POST vehicles '{"plate_number":"WOO-001","make":"Ford","model":"Transit Connect","year":"2023","status":"active","type":"van"}' | python3 -I -c 'import sys,json; print(json.load(sys.stdin)["id"])')"
V2="$(have vehicles plate_number 'WOO-002' || true)"; [ -z "$V2" ] && V2="$(req POST vehicles '{"plate_number":"WOO-002","make":"Toyota","model":"Prius","year":"2022","status":"active","type":"car"}' | python3 -I -c 'import sys,json; print(json.load(sys.stdin)["id"])')"
echo "vehicles: ${V1} ${V2}"

echo "==> Drivers"
if [ -z "$(have drivers name 'Dana Driver' || true)" ]; then
  req POST drivers "{\"name\":\"Dana Driver\",\"email\":\"dana.driver@example.test\",\"phone\":\"+12065550101\",\"password\":\"DeliverIt123!\",\"status\":\"active\",\"vehicle\":\"${V1}\",\"location\":{\"latitude\":47.62,\"longitude\":-122.35}}" >/dev/null && echo "created Dana Driver"
fi
if [ -z "$(have drivers name 'Sam Courier' || true)" ]; then
  req POST drivers "{\"name\":\"Sam Courier\",\"email\":\"sam.courier@example.test\",\"phone\":\"+12065550102\",\"password\":\"DeliverIt123!\",\"status\":\"active\",\"vehicle\":\"${V2}\",\"location\":{\"latitude\":47.61,\"longitude\":-122.33}}" >/dev/null && echo "created Sam Courier"
fi

cat <<SUMMARY

Seed complete.
  API host (from host):      ${API}
  API host (from WP docker): http://host.docker.internal:8000
  Live key:                  ${LIVE_KEY}
  Test key:                  ${TEST_KEY}
  Pickup place:              ${PLACE_ID}
SUMMARY
