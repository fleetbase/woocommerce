# Fleetbase for WooCommerce

Last-mile delivery for WooCommerce stores, powered by [Fleetbase](https://fleetbase.io), the open-source logistics platform. Live delivery quotes at checkout, your own drivers and vehicles, automatic order sync, and status back to WooCommerce. No per-delivery fees.

[![CI](https://github.com/fleetbase/woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/fleetbase/woocommerce/actions/workflows/ci.yml)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-blue.svg)](LICENSE)

## What it does

**At checkout**
- A "Fleetbase Delivery" shipping method that quotes live prices from your Fleetbase service rates (flat, per distance or tiered), limited to the service areas you define.
- A delivery-time control: as soon as possible, or a day and time window within your delivery hours, lead time and booking horizon.
- Works with the Cart and Checkout blocks and the classic shortcode checkout. A configurable "we don't deliver there yet" message for addresses outside your area.

**After the order**
- Paid orders become Fleetbase orders automatically: pickup, geocoded dropoff, customer contact, line items with weights and dimensions, the checkout quote, the delivery window and cash-on-delivery amounts. Sync runs in the background with retries and never creates duplicates.
- Drivers receive the job in the free [Fleetbase Navigator](https://fleetbase.io/platform/navigator) app with turn-by-turn navigation and photo or signature proof of delivery.
- Status flows back through a webhook receiver and a polling fallback: "Out for delivery" when the driver leaves, "Completed" on delivery, with proof-of-delivery links, order notes and WooCommerce Fulfillments support.
- Customers get a tracking link on the thank-you page, in their account and in emails, an "out for delivery" email, and a tracking page via the `[fleetbase_tracking]` shortcode or block.

**In WordPress admin**
- Deliveries, Drivers, Vehicles and Service rates pages, so day-to-day work does not need a second dashboard.
- A dispatcher role for staff who run deliveries without store-wide permissions.
- A setup wizard, a dashboard with connection health, and a per-order delivery box with a sync log.

## Requirements

- WordPress 6.8 or newer, WooCommerce 9.5 or newer, PHP 8.1 or newer.
- A [Fleetbase Cloud](https://console.fleetbase.io/onboard) account. The plugin is free; Fleetbase Cloud bills per driver and vehicle, never per order.
- Self-hosted Fleetbase instances are supported through the separate Fleetbase Self-Hosted Connector add-on.

## Installation

1. Download `fleetbase-for-woocommerce.zip` from the [latest release](https://github.com/fleetbase/woocommerce/releases) and install it under Plugins → Add New → Upload, or install it from the WordPress plugin directory once listed.
2. Activate it and follow the setup wizard: connect with a Fleetbase API key, confirm the pickup location and add "Fleetbase Delivery" to a shipping zone.
3. Create at least one service rate under Fleetbase → Service rates. Customers now see a delivery price at checkout.
4. Optional: paste the webhook URL from the wizard into your Fleetbase console for instant status updates.

A 15-minute walkthrough of every feature is in [docs/acceptance.md](docs/acceptance.md).

## Data sent to Fleetbase

Nothing is sent before you connect. Afterwards the plugin sends what a delivery needs: addresses, the customer's name, email and phone, item details and totals, the chosen delivery time, and the drivers, vehicles and rates you manage in WordPress. It receives delivery status, driver names, tracking and proof-of-delivery links. See the "External services" section of [readme.txt](readme.txt).

## Development

```bash
composer install
pnpm install
pnpm build          # or `pnpm start` for a watch build
```

Quality gates, all of which run in CI:

```bash
composer lint       # phpcs, WooCommerce-Core ruleset
composer analyse    # PHPStan level 6 (also with phpstan-php81.neon)
composer test       # PHPUnit, no WordPress runtime needed
pnpm lint:js
pnpm test:e2e       # Playwright + axe against a running store, see docs/dev-setup.md
scripts/build.sh    # produces build/fleetbase-for-woocommerce.zip
```

[docs/dev-setup.md](docs/dev-setup.md) describes the Docker sandbox, the seed scripts, the test matrix and the gotchas we hit along the way.

### Architecture in one paragraph

`src/Api` is a dependency-free client over `wp_remote_request` with retries and typed exceptions. `src/Shipping` holds the shipping method and a cached quote service. `src/Checkout` holds scheduling, the forced inner block for the Checkout block and the classic checkout fields. `src/Sync` holds the order mapper, the Action Scheduler syncer, the reconciler, the webhook receiver and the poller. `src/Admin` holds settings, the REST proxies (the API key never reaches the browser) and the React admin app in `assets/src/admin`. Hooks for developers are prefixed `fleetbase_wc_`.

## Releasing

Releases follow the same convention as the other Fleetbase repositories: a `release/v<version>` branch merged into `main` becomes the tag, the GitHub Release and the WordPress.org update, with nothing typed by hand.

1. Branch from `main` as `release/v1.x.y`.
2. Set the version in `fleetbase-for-woocommerce.php` (`Version:`), `readme.txt` (`Stable tag:`) and `package.json`, add the `## [1.x.y]` section to `CHANGELOG.md`, and write `RELEASE.md` with the version on its first line.
3. Open a pull request to `main` and merge it. The Release Tag workflow checks that every version source agrees with the branch name and pushes the `v1.x.y` tag. The Release workflow then builds the zip, publishes the GitHub Release from `RELEASE.md`, and deploys that tag to WordPress.org (once the `SVN_USERNAME` and `SVN_PASSWORD` secrets exist).

If validation fails after the merge, fix `main` and run the Release Tag workflow by hand with the version.

## Support

- Issues and feature requests: [GitHub issues](https://github.com/fleetbase/woocommerce/issues)
- Fleetbase documentation: [fleetbase.io/docs](https://fleetbase.io/docs)
- Community: [Fleetbase Discord](https://discord.gg/fleetbase)

## License

GPLv3 or later. See [LICENSE](LICENSE).
