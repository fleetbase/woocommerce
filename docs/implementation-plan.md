# Fleetbase for WooCommerce: product definition and implementation plan

Date: 2026-10-21. Companion document: [research.md](research.md) holds the verified facts this plan depends on. Section 10 lists the decisions that need Ron's call before the build starts; everything else is decided here with the reasoning given.

---

## 1. The product in one paragraph

**Fleetbase for WooCommerce** turns a WooCommerce store into a last-mile delivery operation. Merchants connect the plugin to Fleetbase Cloud or to their own Fleetbase instance, manage drivers, vehicles and delivery rates from WordPress admin, show customers a live delivery quote at checkout that they pay as the shipping charge, and have every paid order appear in Fleetbase for dispatch, driver navigation, proof of delivery and customer tracking. Status flows back into WooCommerce automatically. The plugin is free and open source. Cloud users pay only their Fleetbase Cloud subscription. Users connecting to a custom instance buy a self-hosted connector license.

Positioning: "Open-source delivery management for WooCommerce. Your drivers, your rates, no per-delivery fees. Cloud or self-hosted."

---

## 2. Who it is for

- **Local merchants with their own drivers**: restaurants, grocers, florists, pharmacies, furniture and appliance stores, hardware and building supply, garden centres. They run 1 to 30 drivers and want checkout to charge the right delivery fee and the driver to get the job on their phone.
- **Agencies building delivery stores** for those merchants. They want something maintained, open, and scriptable, and a self-host option for clients who will not accept SaaS.
- **Existing Fleetbase users** who sell through WooCommerce and currently re-key orders.

Jobs to be done:
1. Charge the customer the correct delivery price for their address, at checkout, before payment.
2. Get the paid order to a driver without re-keying.
3. Let the customer see where the order is.
4. Manage the small fleet (people, vehicles, prices) without a second dashboard for day-to-day work.
5. Keep the store working when the delivery backend is slow or down.

---

## 3. Packaging and commercial model

### 3.1 Two deployment modes, one plugin
| Mode | Host | Who pays what | Where the code ships |
|---|---|---|---|
| Fleetbase Cloud | `api.fleetbase.io` (fixed) | Plugin free. Merchant pays Fleetbase Cloud: $29/mo + $5/mo per driver and per vehicle. Orders are unmetered, so WooCommerce volume never changes their bill. | wordpress.org and GitHub |
| Self-hosted Fleetbase | Any host the merchant enters | Plugin free. Merchant buys a **Self-Hosted Connector license** per site (section 3.2). | Self-hosted connection is enabled by a small companion plugin distributed from fleetbase.io and GitHub, not wordpress.org |

Why split the self-hosted connector out: wordpress.org guideline 6 forbids a free plugin whose paid service "exists for the sole purpose of validating licenses or keys". Self-hosted users consume no Fleetbase-hosted service, so a license key gating that mode inside the wp.org build would be exactly that. Keeping the core plugin Cloud-only on wp.org, and shipping the self-hosted connector as a separately distributed add-on, is the model Woo's own ecosystem uses and keeps the wp.org listing clean. The add-on is tiny (a connection provider, a license field, a verification call) and the core plugin exposes the hook it plugs into.

Both plugins are open source. Buyers of the connector license get updates and support; the license verification keeps honest customers honest, which is the norm for GPL commercial plugins.

### 3.2 Self-Hosted Connector license
- Sold from fleetbase.io through the existing `billing` extension on Fleetbase Cloud, implemented as a `FeatureAddon` with key `woocommerce_selfhosted_connector`. Stripe handles payment, renewal and expiry; `CompanyFeature` records the entitlement. This reuses code that already exists rather than inventing a license store.
- The buyer gets a billing-only Cloud account (the pattern the Self-Hosted Upgrade Service already uses) and a license key shown in the console.
- **New Cloud endpoint** `POST https://api.fleetbase.io/v1/licenses/verify` with `{license_key, product: "woocommerce-selfhosted-connector", site_url, plugin_version}` returning `{valid, expires_at, grace_until, token}` where `token` is a signed JWT the plugin caches for 7 days. Re-verify daily; 14-day offline grace; after that the self-hosted connection drops to read-only (orders still sync out, admin management pages show a renewal notice). Nothing destructive happens on expiry.
- Suggested price (Ron's call): $249 per site per year, or $599 one-time per site. That sits under PowerfulWP's $159/yr + $29/mo app bundle on a yearly basis while being a far larger product, and well under any SaaS option for a store doing more than a handful of deliveries a day.

### 3.3 Naming and licensing
- Display name: **Fleetbase for WooCommerce**. Slug and text domain: `fleetbase-for-woocommerce`. PHP namespace `Fleetbase\WooCommerce`, prefix `fleetbase_wc_` for hooks, options and functions. Companion plugin: **Fleetbase Self-Hosted Connector**, slug `fleetbase-selfhosted-connector`.
- Repository: `github.com/fleetbase/woocommerce` already exists with a 3-commit prototype. Recommendation: replace its history with the new codebase (keeps the canonical URL) and archive the prototype on a branch.
- License: **GPLv3** for the plugin (see decision D1). AGPL's network clause adds nothing for code that runs inside a merchant's WordPress and is unusual on wordpress.org. The Fleetbase server stays AGPL; the plugin only talks to it over HTTP.

---

## 4. Feature scope

### 4.1 Version 1.0 (the single build this plan covers)

**A. Onboarding and connection**
- Setup wizard on first activation: choose Cloud or Self-hosted (self-hosted option appears only when the connector add-on is active), paste API key, test connection, confirm organisation, confirm or set the pickup location, add the Fleetbase shipping method to a zone, show webhook setup instructions with a copy button and a "Test webhook" check. Live and test API keys both supported with a mode badge in admin.
- Pickup location: created once as a Fleetbase Place from the WooCommerce store address, with the ability to geocode-search and pick a different Place. Stored by id.

**B. Checkout quotes as a shipping method**
- `fleetbase` shipping method, zone-aware, with per-instance settings: title, which service types to offer, show all quotes or cheapest only, taxable, fallback behaviour when Fleetbase is unreachable (hide method, or show a configured flat fallback rate labelled as estimate), quote cache lifetime.
- Quote request uses the pickup Place id and a structured dropoff address. One shipping rate per returned quote. Rate `meta_data` carries `quote_id`, `service_rate_id`, `request_id`, `amount`, `currency`, `service_name`. Rate `description` and `delivery_time` come from the service rate's `duration_terms` and `estimated_days`.
- Quotes are cached in a transient keyed on a normalised hash of pickup, dropoff, service type filter, scheduled date and cart weight band, for the configured lifetime (default 10 minutes). This is required because each Fleetbase quote call persists rows and quotes never expire.
- Currency: the store currency is compared against the quote currency client-side; mismatched quotes are dropped and logged. The `currency` query param is never sent (server-side bug).
- Serviceability: if Fleetbase returns no quotes for the address, the method is not offered, and an optional notice ("We don't deliver to this address yet") is shown through the Store API extension data in block checkout and `woocommerce_no_shipping_available_html` in classic.
- Works in both the block checkout and the classic shortcode checkout.

**C. Delivery scheduling at checkout**
- "Delivery time" control with two options: ASAP, or a date (and optional time window) within configurable lead time and horizon, respecting the store's weekly delivery hours set in plugin settings. Implemented as an inner block under the shipping methods block for block checkout (it must react to the selected rate and refresh quotes when the date changes) and as classic checkout fields via `woocommerce_checkout_fields`. Value is stored on the order and sent as `scheduled_at` to Fleetbase.
- Capacity-limited time slots are explicitly out of scope for 1.0 (section 4.2).

**D. Order sync (WooCommerce to Fleetbase)**
- Trigger: configurable set of statuses, default `processing` and `completed` for immediate sync, i.e. on `woocommerce_payment_complete` and status change. Orders with a non-Fleetbase shipping method are skipped unless "sync all orders" is on. Pickup-only and virtual orders are skipped.
- Runs through Action Scheduler with a unique action per order, retries with backoff (1 min, 5 min, 30 min, 2 h, 8 h, then flagged), and a per-order sync log stored in order meta plus the WooCommerce log.
- Mapping (see research 2.2): pickup = stored Place id; dropoff = shipping address as a structured object (fallback to billing), with the geocoded result checked for a non-zero location; customer = contact looked up or created by email, cached by WP user id; entities = one per line item with name, SKU, price, currency, weight, dimensions in store units, `meta.quantity`, `meta.product_id`, `meta.variation_id`; `internal_id` = order number; `service_quote` = quote id from the chosen rate; `scheduled_at` from the delivery time field; `notes` = customer note; `meta` = order id, order key, totals, payment method, delivery instructions, admin link; `pod_required` and `pod_method` from settings; `dispatch` from the "auto-dispatch" setting; `type` from the configured order config (default `transport`).
- Cash on delivery: when the WooCommerce payment method is COD, the payload is created first via `POST /v1/payloads` with `cod_amount` and `cod_currency`, then referenced in the order.
- Idempotency: a `_fleetbase_order_id` and `_fleetbase_sync_state` (`pending|syncing|synced|failed`) on the order, set inside a transactional guard before the API call. Before creating, the plugin queries `GET /v1/orders?internal_id=` as a last check.
- Stored on the WooCommerce order: Fleetbase order id, tracking number, tracking URL, QR URL, quote id, assigned driver, last known Fleetbase status, last sync time.

**E. Status sync (Fleetbase to WooCommerce)**
- Webhook receiver at `/wp-json/fleetbase/v1/webhook/{site_token}`. Because Fleetbase cannot register endpoints by API key, the wizard shows the URL and the exact console steps. The URL includes a random per-site token, which is the authentication. On receipt the plugin never trusts payload data: it enqueues a job that fetches the order from the API by id and reconciles. This sidesteps the fragile signature scheme while remaining safe.
- Polling fallback: a recurring Action Scheduler job every 5 minutes fetches open Fleetbase orders (status not completed or canceled, synced within the last 14 days) in one list call and reconciles. This covers merchants who never set up the webhook and any dropped webhook deliveries.
- Reconciliation maps Fleetbase status to WooCommerce: `dispatched`/`started`/`enroute` → custom status "Out for delivery" (optional; default on), `completed` → `completed`, `canceled` → adds a note and optionally sets `cancelled`. Driver assignment, ETA and proof-of-delivery availability are written as order notes. All mappings are configurable.
- When WooCommerce's Fulfillments feature is enabled, a fulfillment with tracking number, provider "Fleetbase" and tracking URL is created so native tracking UI lights up.
- WooCommerce-side cancellation or refund (`cancelled`, `refunded`) calls `DELETE /v1/orders/{id}/cancel` with a reason, if the Fleetbase order is not yet completed.

**F. Customer tracking**
- Tracking link and number on the thank-you page, My Account order view and the completed/processing emails. Dedicated "Your order is out for delivery" email (toggleable) with the driver's first name and ETA when available.
- `[fleetbase_tracking]` shortcode and a matching block that render an embedded tracking view for a given tracking number or for the current order, using the public tracking endpoint, with a link to the full Fleetbase tracking page.

**G. Order management in WordPress admin**
- Order edit screen meta box "Delivery": Fleetbase status, driver, vehicle, ETA, tracking link, quote breakdown, scheduled time, proof of delivery thumbnails, sync log. Actions: assign driver (dropdown from the live driver list), dispatch, cancel in Fleetbase, resync, open in console.
- Orders list: a "Delivery" column with status badge and driver; bulk actions "Send to Fleetbase" and "Dispatch". Both HPOS and legacy screens.
- Order actions dropdown entries mirror the meta box actions.

**H. Fleet management pages (React, under a "Fleetbase" menu)**
- **Deliveries**: all WooCommerce orders with a Fleetbase order, filterable by Fleetbase status and driver, with inline assign and dispatch. This is the daily dispatcher view. A live map is deferred (section 4.2).
- **Drivers**: list with online status, current status, vehicle and last location; create and edit (name, email, phone, password for Navigator sign-in, status, vehicle, internal id); deactivate. Includes a "Get the Navigator app" panel with store links and sign-in instructions.
- **Vehicles**: list; create and edit (name, plate, make, model, year, type, status, assigned driver).
- **Service rates**: list; create and edit with `service_name`, `service_type`, calculation method (`fixed_rate` with a distance-fee table, `per_meter` with base fee + per-unit fee, `fixed_meter`), currency locked to the store currency, optional service area or zone chosen from the existing list, COD fee, peak-hour fee, duration terms and estimated days. Amounts are entered as decimals and sent as minor units. Areas and zones are not drawn in WordPress; a link opens the Fleetbase console map.
- All pages go through plugin REST routes (`fleetbase/v1/*`) that proxy to Fleetbase server-side, so the API key never reaches the browser. Capability: `manage_woocommerce`, plus a `fleetbase_dispatcher` role that gets only Deliveries, Drivers and Vehicles.

**I. Settings**
- Tabs: Connection, Pickup location, Checkout, Order sync, Status mapping and notifications, Customer tracking, Webhooks and polling, Advanced (logging level, cache purge, reset).
- Connection status card with organisation name, key mode, last successful call, rate-limit headroom.

**J. Quality and compliance**
- PHP 8.1+, WordPress 6.8+, WooCommerce 9.5+ tested to 10.5. HPOS and block checkout compatibility declared.
- Full i18n, PCP clean, phpcs WooCommerce-Core ruleset, PHPStan level 6, unit and integration tests, Playwright end-to-end against the sandbox.
- readme.txt with the external services disclosure, privacy notes (what customer data is sent to Fleetbase), and uninstall cleanup.

### 4.2 Deferred (named so they are not accidentally pulled in)
- Capacity-limited time slots with per-slot fees. Candidate partner: certified compatibility with CodeRockz or Orderable instead of rebuilding.
- Multiple pickup locations and per-product pickup location.
- Live dispatch map with driver positions (needs SocketCluster or polling of `driver.location_changed`).
- Drawing service areas and zones inside WordPress.
- Route optimisation UI, driver pay and commission reports.
- Multi-vendor mapping (Dokan, WCFM).
- Overflow to third-party couriers (Uber Direct, DoorDash) through Fleetbase integrated vendors.
- SMS and WhatsApp customer notifications (Fleetbase extensions already cover these server-side).
- Account provisioning from inside WordPress (the prototype's idea). Needs a public signup endpoint on Cloud; v1 links to `console.fleetbase.io/onboard`.
- Automatic webhook registration (blocked on a Fleetbase API change, section 7).

---

## 5. Architecture

### 5.1 Repository layout
```
fleetbase-woocommerce/                   (git root, GitHub fleetbase/woocommerce)
├── fleetbase-for-woocommerce.php        plugin bootstrap, headers, requirements guard
├── uninstall.php
├── readme.txt                            wordpress.org readme
├── composer.json                         PSR-4 autoload only, dev tools (phpcs, phpstan, phpunit)
├── package.json                          @wordpress/scripts build for admin + checkout JS
├── src/
│   ├── Plugin.php                        container, boot sequence, feature detection
│   ├── Api/                              Fleetbase HTTP client (wp_remote_request), DTOs, exceptions, rate-limit aware
│   │   ├── Client.php
│   │   ├── Connection.php                host, key, mode; ConnectionProvider interface
│   │   ├── Resources/{Orders,Quotes,ServiceRates,Drivers,Vehicles,Places,Contacts,Payloads,Organizations}.php
│   │   └── Money.php                     minor-unit conversions
│   ├── Shipping/
│   │   ├── FleetbaseShippingMethod.php
│   │   ├── QuoteService.php              builds quote requests, caching, filtering
│   │   └── PackageExtender.php           adds scheduling inputs to the package hash
│   ├── Checkout/
│   │   ├── BlocksIntegration.php         IntegrationInterface, Store API extend + update callbacks
│   │   ├── ClassicCheckout.php           fields, validation, save
│   │   └── Scheduling.php               delivery hours, lead time, horizon, validation
│   ├── Sync/
│   │   ├── OrderMapper.php               WC_Order → Fleetbase order payload
│   │   ├── OrderSyncer.php               create, cancel, resync, state machine
│   │   ├── Reconciler.php                Fleetbase order → WC status, notes, fulfillments
│   │   ├── WebhookController.php         REST receiver
│   │   ├── Poller.php                    recurring reconciliation
│   │   └── Jobs.php                      Action Scheduler hooks and backoff
│   ├── Orders/
│   │   ├── MetaBox.php, ListColumns.php, BulkActions.php, OrderActions.php, Statuses.php
│   ├── Admin/
│   │   ├── Menu.php, Pages.php           registers React pages and settings screens
│   │   ├── Settings.php                  option schema, sanitisation, defaults
│   │   ├── Wizard.php
│   │   └── Rest/{DriversController,VehiclesController,ServiceRatesController,DeliveriesController,ConnectionController,SettingsController}.php
│   ├── Frontend/
│   │   ├── Tracking.php                  shortcode, block, order details, emails
│   │   └── Emails/OutForDeliveryEmail.php
│   ├── Support/{Logger,Cache,Capabilities,Compat,Address}.php
│   └── Hooks.php                         public filters/actions documented for developers
├── assets/src/
│   ├── admin/                            React app: deliveries, drivers, vehicles, service-rates, settings, wizard
│   ├── checkout/                         block checkout inner block + classic enhancements
│   └── tracking/                         tracking block
├── templates/                            overridable templates (emails, tracking)
├── languages/
├── tests/{Unit,Integration,E2E}/
├── .wp-env.json, phpcs.xml, phpstan.neon, .github/workflows/
└── docs/
```
Companion repo `fleetbase-selfhosted-connector/`: one bootstrap file, a `SelfHostedConnectionProvider`, a license settings panel, and the verification client.

### 5.2 Key design decisions
1. **No Guzzle, no SDK vendoring.** A 300-line client on `wp_remote_request` avoids dependency conflicts that plague WP plugins. The `fleetbase-php` SDK stays the reference for request shapes. If Ron wants SDK reuse later, giving the SDK a PSR-18 adapter seam would let the plugin inject a `WP_Http` transport.
2. **Server-side proxy for every admin call.** The browser only ever talks to `fleetbase/v1/*` on the store with a REST nonce; the API key stays in `wp_options` (encrypted at rest using `wp_salt('auth')`-derived key, decrypted only in the client).
3. **Webhooks are hints, the API is truth.** Reconcile from a fresh `GET /v1/orders/{id}` on every webhook and on every poll. Idempotent by construction.
4. **Quotes are cached, deliberately.** Keyed hash with a short TTL; cache is invalidated when the shipping method settings change or the scheduled date changes.
5. **One state machine per order** stored in order meta, with every transition logged, so support can read a single order and know what happened.
6. **Graceful degradation.** Fleetbase unreachable at checkout → configurable fallback; unreachable at sync → retry with backoff and an admin notice after the final failure; unreachable in admin pages → cached last-known lists with a banner.
7. **React admin built on `@wordpress/components` and `@wordpress/data`**, not `@woocommerce/components`, to avoid Woo admin's churn. Pages are registered through `wc_admin_connect_page` so they get the WooCommerce header and breadcrumbs.
8. **Blocks first, classic supported.** The scheduling control is an inner block for block checkout and classic fields for the shortcode. Shared validation and storage live in PHP.

### 5.3 Data stored in WordPress
- Options: `fleetbase_wc_settings` (array, versioned), `fleetbase_wc_connection` (encrypted), `fleetbase_wc_site_token`, `fleetbase_wc_pickup_place_id`, `fleetbase_wc_customer_map` is not an option; customer contact ids go in user meta `_fleetbase_contact_id`.
- Order meta: `_fleetbase_order_id`, `_fleetbase_tracking_number`, `_fleetbase_tracking_url`, `_fleetbase_quote_id`, `_fleetbase_status`, `_fleetbase_driver`, `_fleetbase_sync_state`, `_fleetbase_sync_log` (capped array), `_fleetbase_scheduled_at`.
- Custom status: `wc-out-for-delivery`.
- No custom tables. Action Scheduler and WC logs carry the rest.

### 5.4 Sequence: checkout to delivered
1. Customer enters address → WooCommerce computes packages → `FleetbaseShippingMethod::calculate_shipping` → `QuoteService` cache miss → `GET /v1/service-quotes` → rates added with quote meta.
2. Customer picks a Fleetbase rate and a delivery time → block `extensionCartUpdate` stores the time in session → package hash changes → quotes refresh with `scheduled_at`.
3. Order placed and paid → `woocommerce_payment_complete` → `OrderSyncer::enqueue` → Action Scheduler → `OrderMapper` builds payload → `POST /v1/orders` with `service_quote` → ids saved, note added, fulfillment created if available.
4. Dispatcher assigns a driver from the order screen or Fleetbase auto-dispatches → Fleetbase emits `order.driver_assigned`, `order.dispatched` → webhook → reconcile → status "Out for delivery" → customer email with tracking link.
5. Driver completes in Navigator with photo POD → `order.completed` → reconcile → WooCommerce `completed`, POD thumbnails on the order.

---

## 6. Fleetbase-side work

The plugin can ship against the Fleetbase API as it exists today. The items below make it better, and the first is required for the self-hosted fee.

| # | Change | Where | Needed for | Size |
|---|---|---|---|---|
| F1 | License feature add-on `woocommerce_selfhosted_connector`, Stripe price, license key issuance in console billing settings, public `POST /v1/licenses/verify` returning a signed token | `billing` extension | Self-hosted fee (section 3.2) | 2–3 days |
| F2 | Public `POST/GET/DELETE /v1/webhook-endpoints` under API-key auth, returning a per-endpoint signing secret once | `core-api` | One-click webhook setup in the wizard | 1–2 days |
| F3 | Sign webhooks with the endpoint's own secret, add `X-Fleetbase-Signature: t=<ts>,v1=<hmac>` header and keep legacy `Signature` | `core-api` | Verifiable webhooks for every integrator | 1 day |
| F4 | `GET /v1/service-quotes`: `preview=1` to skip persisting rows, fix the `currency` boolean bug, set `expired_at` (for example 30 minutes) on persisted quotes | `fleetops` | Keeps merchant accounts clean; quote reuse at order creation still works within the window | 1 day |
| F5 | Honour `Idempotency-Key` on `POST /v1/orders` | `core-api`/`fleetops` | Belt and braces for sync | 0.5 day |
| F6 | Expose `plan`, `billing_status`, `trial_ends_at` to API-key callers on `GET /v1/organizations/current`, or a `GET /v1/account/status` | `core-api`, `billing` | Trial and billing banners in the plugin | 0.5 day |
| F7 | Correct the website's "native WooCommerce connector" claim to link to the real listing once published | `fleetbase.io` | Marketing accuracy | hours |

The plugin is written so that F2 to F6 are detected at runtime: if the endpoint exists the wizard uses it, otherwise it falls back to manual instructions or polling. That lets the plugin ship before those PRs land.

---

## 7. Build plan

Effort is in focused working days for one engineer with this codebase in front of them. Phases are ordered so each one leaves the sandbox in a demonstrable state. Total: about 24 working days for the plugin, plus 5 to 7 days of Fleetbase-side work that can run in parallel.

### Phase 0: Foundations (2 days) — DONE 2026-10-21
- Repo scaffold, bootstrap file with headers and requirements guard, Composer and npm toolchains, phpcs, PHPStan, wp-env config, GitHub Actions (lint, static analysis, unit tests, plugin-check, build zip).
- Docker: add `../fleetbase-woocommerce:/var/www/html/wp-content/plugins/fleetbase-for-woocommerce` to the sandbox compose file; add a `wpcli` service; seed script that sets the store address, creates products with weights and dimensions, and enables Fulfillments.
- Fleetbase local seed: organisation, live and test API keys, a pickup place, two service rates (fixed and per-km), a service area, two drivers, two vehicles. Script it against `localhost:8000` so it is repeatable.
- API client with retries, rate-limit handling, error normalisation, and request logging. Unit tests with recorded responses.
- Done when: plugin activates clean on the sandbox, `wp plugin check` passes, CI is green, the client can create and read an order against local Fleetbase from a unit test.

### Phase 1: Connection, settings, wizard (3 days) — DONE 2026-10-21
- Settings schema and screens, connection provider interface with the Cloud provider, encrypted key storage, connection test, organisation display, pickup place creation and picker (geocode search), site token generation.
- Setup wizard (React) covering section 4.1 A.
- Done when: a fresh install can be connected to local Fleetbase (via the self-hosted provider stub in dev mode) and to Cloud, and shows a green status card.

### Phase 2: Shipping method and quotes (3 days) — DONE 2026-10-21
- `FleetbaseShippingMethod`, `QuoteService` with caching and filtering, rate meta, description and delivery time, fallback behaviour, no-service messaging in both checkouts.
- Done when: the sandbox checkout shows live rates from the local Fleetbase service rates, changes with address, hides outside the service area, and survives Fleetbase being stopped.

### Phase 3: Delivery scheduling (2 days) — DONE 2026-10-21
- Delivery hours, lead time, horizon settings. Inner block for block checkout with ASAP/date/time window and quote refresh. Classic checkout fields. Validation and order storage.
- Done when: a scheduled order carries the chosen time in both checkouts and quotes refresh when it changes.

### Phase 4: Order sync out (3 days)
- `OrderMapper`, `OrderSyncer`, Action Scheduler jobs with backoff, idempotency guards, COD payload path, customer contact cache, cancellation propagation, order meta box (read-only parts), order list column.
- Done when: paying for an order in the sandbox produces a correct Fleetbase order within seconds, with entities, quote, scheduled time and customer; replaying the hook does not duplicate; a failed call retries and surfaces in the log.

### Phase 5: Status sync in (3 days)
- Webhook receiver, reconciler, poller, custom status, status mapping settings, notes, Fulfillments integration, out-for-delivery email, tracking on thank-you, My Account and emails, tracking shortcode and block.
- Done when: driving an order through dispatch and completion in the Fleetbase console updates the WooCommerce order with no webhook configured (poll) and within seconds with the webhook configured; the customer sees the tracking link everywhere listed.

### Phase 6: Fleet management pages (5 days)
- REST proxy controllers with validation and capability checks. React pages: Deliveries, Drivers, Vehicles, Service rates, each with list, create, edit, and error states. Order screen actions: assign driver, dispatch, cancel, resync. Bulk actions.
- Done when: a dispatcher can run a day's deliveries without opening the Fleetbase console: create a driver who can sign into Navigator, create a rate that appears at checkout, assign and dispatch from WordPress.

### Phase 7: Self-Hosted Connector add-on (1 day plugin side, depends on F1)
- Connection provider for custom hosts, license field, verification client with caching and grace, renewal notices. Stub verification server for local tests.

### Phase 8: Hardening and release (2 days)
- Test matrix: block and classic checkout; HPOS on and off; PHP 8.1 and 8.3; WooCommerce 9.5 and 10.5; Cloud and self-hosted; Fulfillments on and off. Playwright flows for checkout, sync, and status round trip.
- Accessibility pass on admin pages and checkout control. Performance check: no synchronous Fleetbase calls on page loads other than checkout rate calculation.
- readme.txt, screenshots, external services disclosure, privacy section, changelog, GitHub release workflow, wordpress.org SVN deploy workflow, docs site page on fleetbase.io.
- wordpress.org submission and WooCommerce Marketplace submission (optional, decision D4).

### Suggested execution order and parallelism
Phases 0 → 1 → 2 → 4 → 5 are the critical path (13 days) and produce a sellable product on their own. Phase 3 and Phase 6 can be built in parallel sessions once Phase 1 exists, as they touch separate directories. Phase 7 waits on F1. Fleetbase-side F1 to F6 can start immediately in the `oss/fleetbase-dev` checkout.

---

## 8. Test strategy

- **Unit** (PHPUnit, no WordPress): client, money conversion, address normalisation, order mapper, quote cache key, reconciler state transitions, scheduling validation.
- **Integration** (wp-env, WooCommerce test helpers): shipping method against a mocked client, order sync hooks, webhook REST route, status mapping, HPOS and legacy order stores.
- **Contract** tests: recorded live responses from local Fleetbase 0.7.x for orders, quotes, drivers, vehicles, service rates; a nightly job replays them against the current Fleetbase `main` to catch API drift, which matters given the release cadence.
- **End-to-end** (Playwright): block checkout with live quote and scheduled time → payment → Fleetbase order exists → simulate dispatch and completion → WooCommerce order completed and tracking shown. Repeated for classic checkout.
- **Manual acceptance script** in `docs/acceptance.md` that a non-engineer can run against the sandbox in 15 minutes.

---

## 9. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Fleetbase API changes weekly | Contract tests, client with version pinning in `User-Agent`, feature detection, defensive parsing |
| Quote calls pollute merchant accounts | Caching, `single` and service-type filters, F4 on the Fleetbase side |
| Webhook signature cannot be verified | Site token in URL plus fetch-from-API reconciliation; F2 and F3 later |
| Geocoding returns (0,0) for a bad address | Validate coordinates in responses; mark order as needing attention; never silently dispatch |
| wp.org review rejects license gating | Self-hosted connector shipped outside wp.org; core plugin has no key checks |
| Merchants on classic checkout | Full classic support; both covered by e2e |
| Dispatch with no driver fails | "Auto-dispatch" setting defaults off unless Fleetbase `adhoc` is used; clear error in the order meta box |
| Store currency differs from rate currency | Rates filtered client-side, admin warning in the Service rates page |
| Support burden from drivers who cannot log in | Driver create form requires email or phone; Navigator panel explains sign-in |

---

## 10. Decisions for Ron before the build

- **D1 License for the plugin:** GPLv3 (recommended) or keep AGPL-3.0 as the GitHub repo currently is.
- **D2 Self-hosted connector price and term:** proposed $249/yr or $599 one-time per site, sold via Fleetbase Cloud billing. Confirm or change.
- **D3 Fleetbase-side changes in the same go:** build F1 to F6 now (recommended, 5 to 7 days in parallel) or ship the plugin with manual webhook setup and polling first.
- **D4 Distribution channels:** wordpress.org plus GitHub releases (recommended), with WooCommerce Marketplace as a later addition.
- **D5 Repository:** replace history in `github.com/fleetbase/woocommerce` (recommended) or start a new `fleetbase-woocommerce` repo.
- **D6 Admin UI depth for service areas:** link out to the console map (recommended for 1.0) or build polygon drawing in WordPress now (adds roughly 4 days).
- **D7 Scheduling scope:** ASAP plus date and time window (recommended) or full capacity-limited slots in 1.0 (adds roughly 3 days).

With D1 to D7 answered, the build can start at Phase 0 and run straight through.
