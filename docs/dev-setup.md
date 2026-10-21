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
scripts/build.sh   # produces build/fleetbase-for-woocommerce.zip and build/fleetbase-for-woocommerce/
```

### WordPress Plugin Check

Run it against the staged build, never the working copy: with `node_modules` and `vendor` present the
check walks tens of thousands of files and takes many minutes. Plugin Check must be active for the
`wp plugin check` command to exist; deactivate it afterwards because it hooks into every request.

```bash
scripts/build.sh
rm -rf ../wordpress/plugins/fbwc-dist && cp -R build/fleetbase-for-woocommerce ../wordpress/plugins/fbwc-dist
cd ../wordpress
docker compose run --rm wpcli plugin activate plugin-check
docker compose run --rm wpcli plugin check fbwc-dist --categories=plugin_repo,security
docker compose run --rm wpcli plugin deactivate plugin-check
rm -f wordpress/wp-content/object-cache.php   # Plugin Check leaves this drop-in behind if interrupted
```

Because the staged folder is not named `fleetbase-for-woocommerce` (that name is the live dev mount), the
check reports text-domain and slug mismatches against `fbwc-dist`. Ignore those; CI runs the same check
under the real slug. Every other finding is real.

### If the sandbox gets slow

The local Fleetbase dev stack's `scheduler` container runs `artisan schedule:run` every minute, and
under Rosetta some of its jobs take 5 to 30 minutes. They pile up until they use every core of the
Docker VM, which makes every WordPress request take 30 seconds or more. Nothing in this plugin needs
that scheduler. Stop it while developing:

```bash
docker stop fleetbase-dev-scheduler-1    # bring it back with: docker start fleetbase-dev-scheduler-1
```

## Checkout testing

`scripts/seed-store.sh` puts a "Washington" zone (US:WA) at the top of the zone order with Fleetbase Delivery
attached. WooCommerce uses the first matching zone, so any broader "United States" zone must sort below it or the
Fleetbase method never runs. A Seattle address (for example 1124 Pike St, 98101) is inside the seeded service area;
Spokane (99201) is outside it and should show no delivery options. The page `/classic-checkout/` holds the
shortcode checkout for testing the non-block path.

To simulate a Fleetbase outage without stopping containers, point the stored connection host at a dead port and
bump the quote cache version, then restore it:

```bash
docker compose run --rm wpcli eval '$c=get_option("fleetbase_wc_connection"); $c["host"]="http://host.docker.internal:8999"; update_option("fleetbase_wc_connection",$c,false); \Fleetbase\WooCommerce\Shipping\QuoteCacheVersion::bump();'
```

### Scheduling

The delivery-time control is a WooCommerce inner block registered with `lock.remove = true`, which makes the
Checkout block force-render it inside the shipping methods block without the merchant editing the page. The
classic checkout gets a `<select>` in the order review table instead. Both write the choice to the WooCommerce
session, which is part of the shipping package and therefore of the rate cache key, so quotes refresh with
`scheduled_at`. Order meta: `_fleetbase_delivery_choice`, `_fleetbase_scheduled_at` (ISO 8601 in the store
timezone), `_fleetbase_delivery_label`.

## Gotchas learned on the way

- `wp-api-fetch` already carries WordPress's root URL and nonce middleware. Never register a second root middleware; pass paths as `/fleetbase/v1/...`.
- Translated strings must not be built at `plugins_loaded`; the menu builds its labels lazily.
- `wp-scripts` emits `style-admin.css` for an `admin` entry that imports SCSS.
- Fleetbase `PUT /v1/service-rates/{id}` requires `currency` even for partial updates.
- Fleetbase `POST /v1/service-areas` takes `latitude`, `longitude` and `radius` (metres) and builds the border itself; a raw polygon fails.
- `ApiCredential.test_mode` is not mass assignable; the seed sets it with a direct update.
- Quote calls from the WordPress container normally take 0.4 to 2 s locally, but the first geocode of a new address
  can spike past 10 s when the host is loaded. The checkout timeout is a setting (`checkout.quote_timeout`, default 10 s).
- The Checkout block renders a rate's `description` and `delivery_time`; the shortcode checkout needs
  `woocommerce_after_shipping_rate`, which the plugin hooks.
- `woocommerce_blocks_loaded` fires before `plugins_loaded` priority 20. Anything that must run on it (Store API
  update callbacks, extension data) has to check `did_action()` and run immediately when the hook already fired.
- WooCommerce's block packages (`wc.blocksCheckout`, `wc.wcSettings`) are consumed as globals with script
  dependencies `wc-blocks-checkout` and `wc-settings`; the WooCommerce dependency-extraction plugin is not used.
