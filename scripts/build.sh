#!/usr/bin/env bash
# Produces build/fleetbase-for-woocommerce.zip: production composer autoloader, built assets, no dev files.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="fleetbase-for-woocommerce"
OUT="${ROOT}/build"
STAGE="${OUT}/${SLUG}"

rm -rf "${OUT}"
mkdir -p "${STAGE}"

( cd "${ROOT}" && pnpm install --frozen-lockfile --silent && pnpm build >/dev/null )

rsync -a "${ROOT}/" "${STAGE}/" \
  --exclude-from="${ROOT}/.distignore" \
  --exclude '.git' --exclude 'vendor' --exclude 'node_modules'

( cd "${STAGE}" && cp "${ROOT}/composer.json" . && cp "${ROOT}/composer.lock" . \
  && composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --quiet \
  && rm -f composer.json composer.lock )

( cd "${OUT}" && zip -qr "${SLUG}.zip" "${SLUG}" )
echo "Built ${OUT}/${SLUG}.zip"
