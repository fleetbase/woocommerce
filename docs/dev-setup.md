# Developer setup

Everything runs against the two Docker stacks already in this workspace: the WordPress + WooCommerce sandbox in `../wordpress` and the local Fleetbase API in `../oss/fleetbase-dev`.

## One-time

```bash
# 1. Plugin toolchain
composer install
pnpm install
pnpm build            # or `pnpm start` for a watch build

# 2. Local Fleetbase: company "Woo Test Store", live + test API keys, a Seattle pickup place,
#    a service area, two service rates, two vehicles, two drivers. Prints the keys.
scripts/seed-fleetbase.sh

# 3. Sandbox store: Seattle store address, products with weights and dimensions, a Washington
#    shipping zone, dev constants in wp-config.php, a sandbox admin user, and the plugin activated.
scripts/seed-store.sh
```

The sandbox compose file mounts this repository at `wp-content/plugins/fleetbase-for-woocommerce` and adds a `wpcli` service. From `../wordpress`:

```bash
docker compose run --rm wpcli plugin list
```

## URLs and hosts

| What | Where |
|---|---|
| Store | http://localhost:8800 |
| Admin | http://localhost:8800/wp-admin (user `fbwc-admin`; password is set by `scripts/seed-store.sh`, see `FBWC_DEV_PASS`) |
| Plugin pages | Fleetbase menu: Dashboard, Setup, Settings |
| Fleetbase API from your Mac | http://localhost:8000 |
| Fleetbase API from inside the WP container | http://host.docker.internal:8000 (the wizard prefills this) |
| Fleetbase console | http://localhost:4200 |
| Plugin log | WooCommerce → Status → Logs, source `fleetbase` |

`scripts/seed-store.sh` defines `FLEETBASE_WC_ALLOW_CUSTOM_HOST` so the wizard offers the "Custom host (development)" connection type. Production builds never show it; self-hosted connections come from the companion add-on.

## Quality gates

```bash
composer lint      # phpcs, WooCommerce-Core ruleset
composer analyse   # phpstan level 6
composer test      # phpunit (no WordPress runtime needed)
pnpm lint:js
docker compose -f ../wordpress/docker-compose.yml run --rm wpcli plugin check fleetbase-for-woocommerce \
  --categories=plugin_repo,security --exclude-directories=vendor,node_modules,tests,docs,scripts,build \
  --exclude-files=phpunit.xml.dist,phpstan.neon,phpcs.xml,webpack.config.js,composer.json,composer.lock,package.json,pnpm-lock.yaml
scripts/build.sh   # produces build/fleetbase-for-woocommerce.zip
```

## Gotchas learned on the way

- `wp-api-fetch` already carries WordPress's root URL and nonce middleware. Never register a second root middleware; pass paths as `/fleetbase/v1/...`.
- Translated strings must not be built at `plugins_loaded`; the menu builds its labels lazily.
- `wp-scripts` emits `style-admin.css` for an `admin` entry that imports SCSS.
- Fleetbase `PUT /v1/service-rates/{id}` requires `currency` even for partial updates.
- Fleetbase `POST /v1/service-areas` takes `latitude`, `longitude` and `radius` (metres) and builds the border itself; a raw polygon fails.
- `ApiCredential.test_mode` is not mass assignable; the seed sets it with a direct update.
