# Research findings: Fleetbase for WooCommerce

Date: 2026-10-21. Sources: local Fleetbase source (`oss/fleetbase-dev`, `fleetbase-php`, `billing`, `oss/fleetbase-registry`, `fleetbase.io`), the WooCommerce 10.5.0 install in `wordpress/plugins/woocommerce`, fleetbase.io public docs, developer.woocommerce.com, wordpress.org, and competitor sites. Everything here was checked against code or a live page on the date above. Anything inferred rather than verified is marked.

---

## 1. Environment facts

| Item | Value |
|---|---|
| Sandbox WordPress | `wordpress/docker-compose.yml`, port 8800, `wordpress:latest`, MariaDB 10.6 |
| Sandbox WooCommerce | 10.5.0, HPOS enabled, block cart and checkout, one product, one order, no store address set |
| Local Fleetbase | `oss/fleetbase-dev` stack running, API 0.7.68 at `http://localhost:8000`, console at `:4200` |
| Container networking | The WP container must reach Fleetbase at `http://host.docker.internal:8000`; Fleetbase containers must reach WP at `http://host.docker.internal:8800` for webhooks |
| Local tooling | PHP 8.2.28, Composer, Node 22, pnpm. No `wp-cli` on host (use `docker exec` or a wp-cli container) |
| Fleetbase latest release | v0.7.72 (2026-10-20), several releases per week |
| PHP SDK | `fleetbase/fleetbase-php` 1.4.1, requires Guzzle 7 |
| Prior attempt | `github.com/fleetbase/woocommerce`: 3 commits from 2026-02-09, ~45 KB prototype. Calls `/v1/woocommerce/provision-account` and `/v1/usage/current`, neither of which exists in Fleetbase core. README quotes the retired "resource unit" pricing. Treat as reference only. |

---

## 2. Fleetbase API: what the plugin can rely on

### 2.1 Auth and transport
- `Authorization: Bearer flb_live_…` or `flb_test_…`. Test keys hit the sandbox DB connection. No org or version headers.
- Cloud base URL `https://api.fleetbase.io`, routes under `/v1`. Self-hosted hosts may carry a path prefix.
- Errors: `{"errors":[…]}` or `{"error":"…"}`. 422 on validation, 429 with `Retry-After` on rate limit (120 req/min per credential by default).
- Lists return bare arrays, `limit` 1–100 (default 30), `offset` or `page`, `sort`. No pagination metadata.
- `Idempotency-Key` is only honoured on inspections. `POST /v1/orders` is not idempotent. The plugin must dedupe on its side.
- API key format is identical on Cloud and self-hosted. The only reliable "is this Cloud?" check is: host is `api.fleetbase.io` and `GET /v1/organizations/current` returns 200.
- `GET /v1/organizations/current` hides `plan`, `billing_status` and `trial_ends_at` from API-key callers.

### 2.2 Orders
- `POST /v1/orders` with top-level `pickup` + `dropoff` (place id, address string, address object or coordinates), or `payload: {pickup, dropoff, entities[]}`.
- Place strings are geocoded server-side; unmatched addresses silently become `(0,0)`. Send a structured address object and validate the returned coordinates.
- `customer`: pass a `contact_…` id, or an object `{name,email,phone,internal_id,meta}` which does first-or-create on email. `email` must be present or creation fails.
- `service_quote: "quote_…"` attaches a quote; the purchase rate is created by a queued job, so it may be absent in the create response.
- `internal_id`, `meta`, `notes`, `scheduled_at`, `pod_required`, `pod_method` (`scan|signature|photo`), `dispatch` (bool), `adhoc`, `driver`, `vehicle`, `type`/`order_config` all accepted.
- Cash on delivery fields on an inline payload are ignored at creation. To set COD, create the payload first via `POST /v1/payloads` and pass its id.
- Entities accept `name, sku, price, currency, weight, weight_unit, length, width, height, dimensions_unit, declared_value, internal_id, meta, photo`. Quantity is not a field; store it in `meta.quantity` (Storefront does the same).
- Response: `id` (`order_…`), `status`, `tracking_number {tracking_number, url, qr_code, status}`, `driver_assigned`, `payload`, `distance`, `time`, `scheduled_at`, `meta`.
- Other endpoints: `PATCH /{id}/dispatch` (needs a driver or `adhoc`), `PATCH /{id}/schedule`, `DELETE /{id}/cancel`, `GET /{id}/tracker`, `GET /{id}/eta`, `GET /{id}/proofs`, `PUT /{id}` to assign `driver`.
- Default status flow: `created → dispatched → started → enroute → completed`, cancel code `canceled`. Custom order configs can define other codes; `GET /v1/order-configs` is read-only.

### 2.3 Service rates and quotes
- `POST/GET/PUT/DELETE /v1/service-rates`. Required: `service_name`, `service_type`, `rate_calculation_method` (`fixed_meter|fixed_rate|per_meter|per_drop|algo|parcel`), `currency`. Optional `service_area`, `zone`, `base_fee`, `per_meter_unit`, `per_meter_flat_rate_fee`, `meter_fees[{distance,fee}]`, COD fee fields, peak-hour fields, `duration_terms`, `estimated_days`.
- Money fields strip non-digits: send integer minor units (`1250` for $12.50).
- Public API cannot create parcel or per-drop fee tables, only `meter_fees`.
- `GET /v1/service-quotes?pickup=…&dropoff=…` (or `payload=` or `waypoints=`), optional `service_type`, `scheduled_at`, `cod`, `distance`, `time`, `single=1` (cheapest only), `service=<rate uuid>`.
- **Every quote call persists ServiceQuote rows** and quotes never expire. The plugin must cache quotes per cart/destination to avoid flooding the merchant's account.
- **Bug:** without a `payload` id, passing `currency` filters by a boolean and returns nothing. Do not send `currency`; filter client-side.
- Rates with a service area or zone only apply when every stop is inside the border, which is how serviceability is expressed.
- Response items: `id` (`quote_…`), `service_rate`, `service_name`, `amount` (minor units), `currency`, `items[] {amount, code}` with codes `BASE_FEE, PARCEL_FEE, COD_FEE, PEAK_HOUR_FEE, MULTI_ZONE_DISTANCE_FEE`.

### 2.4 Drivers and vehicles
- `POST /v1/drivers`: `name` required; `email`, `phone`, `password` optional but a driver without email or phone cannot sign in to Navigator. Also `status` (`active|available|inactive`), `vehicle`, `location`, `internal_id`, `timezone`, `meta`.
- Driver response includes `online`, `current_status`, `location`, `vehicle`, `current_job`.
- Assigning a driver is `PUT /v1/orders/{id}` with `driver`, or `driver` on create. No dedicated assign endpoint.
- `POST /v1/vehicles`: ~80 optional fields; `status` from a 19-value list; `plate_number`, `make`, `model`, `year`, `driver`.

### 2.5 Webhooks
- **Cannot be created with an API key.** Endpoints are managed only at `/int/v1/webhook-endpoints` under console session auth.
- Signing secret is the secret of the credential that triggered the event (a bcrypt hash string), or the literal `internal` for console-triggered events. Header is `Signature`, value `hash_hmac('sha256', json_encode($payload), $secret)`. This is not something a plugin can verify reliably.
- Delivery: POST JSON, 5 s timeout, 3 attempts at 10 s and 100 s backoff, then dropped.
- Events actually wired for orders: `order.created`, `order.updated`, `order.deleted`, `order.dispatched`, `order.dispatch_failed`, `order.driver_assigned`, `order.completed`, `order.canceled`, `order.failed`. `order.ready` and `order.started` are not emitted (inferred from listener registration). Also `tracking_status.created`, `driver.*`, `entity.*`, `waypoint.*`.
- Envelope: `{id, api_version, event, created_at, data}` where nested resources collapse to public id strings.

### 2.6 Places, contacts, tracking
- `POST /v1/places` with `address` or structured fields, forward geocodes; dedupes on company + owner + name + street1.
- `GET /v1/places/search?query=&geo=1` for geocoder-backed search.
- `POST /v1/contacts` with `type: customer`.
- Public tracking page URL comes back on the order as `tracking_number.url` (console `~/track/{n}`).
- `GET /public/track/{number}` works without a key.

### 2.7 Learned while building Phase 0–1
- `PUT /v1/service-rates/{id}` validates `currency` as required even on partial updates. Always send it.
- `POST /v1/service-areas` requires `country` and builds its border from `latitude`, `longitude` and `radius` (metres). Sending a GeoJSON polygon as `border` returns "Failed to create service area."
- `GET /v1/places/search?geo=1` returns geocoder hits with `id: null`, GeoJSON `location.coordinates` as `[lng, lat]`, and upper-cased `address`. Creating a place from a hit with `street1` plus `latitude`/`longitude` stores it without a second geocode. Local search takes about 3.5 s.
- `GET /v1/organizations/current` returns `id` (`company_…`), `name`, `currency`, `timezone`, `country`, `status`, `slug`.
- API credentials' `test_mode` is not mass assignable; the model observer generates the key from it on create.
- `POST /v1/orders` with `scheduled_at: 2026-10-21T11:00:00-07:00` comes back as `2026-10-21T11:00:00Z`: the offset is dropped (Laravel datetime cast). Send UTC (`…T18:00:00Z`) and it round-trips correctly.
- `GET /v1/orders?internal_id=X` filters by internal id (verified), which makes order creation idempotent from the plugin side.
- `POST /v1/payloads` with `pickup`, `dropoff`, `entities`, `type` and the COD fields, then `POST /v1/orders` with `payload: payload_…`, works for cash-on-delivery orders. The order response then carries `cod_amount`/`cod_currency`.
- The created order's `customer.id` is a `contact_…` id, usable directly as `customer` on later orders.
- Creating an order with `service_quote` makes Fleetbase create a `purchase_rate` with the quoted amount, visible on the order a few seconds later.
- `GET /v1/service-quotes` accepts `dropoff[street1]`, `dropoff[city]`, `dropoff[province]`, `dropoff[postal_code]`, `dropoff[country]` as a nested query array and geocodes it. An address outside every rate's service area returns `[]` (verified with Spokane against a Seattle area). Each distinct address creates a Place and one ServiceQuote row per matching rate. Quote `items` can repeat the `BASE_FEE` code for the distance component (`details: "Service Fee"`).

---

## 3. Fleetbase commercial facts

- **Cloud pricing (live page):** $29/mo platform, $5/mo per driver and per vehicle, $5 per extra storefront, orders unmetered, 7-day trial with card on file, 20% off annual. Defined in `billing/server/config/plans.php` as `cloud_v3` (currently `is_visible => false`, waiting for rollout).
- Self-hosted is free under AGPL-3.0; $2,500 one-time implementation service; $200/mo self-hosted upgrade service exists as a hidden plan that creates a billing-only Cloud account.
- **No license verification endpoint exists today** for third-party plugins. Reusable building blocks:
  - `billing` has `FeatureAddon` + `CompanyFeature` tables, `FeatureGatingService::hasFeature()`, `RequiresFeature` middleware and `GET billing/int/v1/features/check/{key}` (console-session auth only).
  - `registry-bridge` has paid extensions with Stripe Checkout, `RegistryExtensionPurchase`, `RegistryUser` tokens, `RegistryExtension::hasAccess()`. Gating is at download time only.
  - The AWS Marketplace package is not reusable.
- The marketing site already claims "native connectors for Shopify and WooCommerce". Neither exists. `oss/shopify-orders` is an untouched starter-extension scaffold.
- No GitHub issues or discussions request a WooCommerce integration; demand is not yet visible publicly.
- wordpress.org has no plugin matching "fleetbase". Slugs `fleetbase` and `fleetbase-for-woocommerce` are free. wp.org rejects slugs that start with `woocommerce-`.

---

## 4. WooCommerce 10.x integration points

### 4.1 Shipping method
- Subclass `WC_Shipping_Method`, register on `woocommerce_shipping_methods`, `supports = ['shipping-zones','instance-settings','instance-settings-modal']`.
- `calculate_shipping($package)`: `$package['destination']` has `country, state, postcode, city, address_1, address_2`; `contents` has cart items with product objects; `contents_cost`.
- `add_rate(['id','label','cost','taxes','calc_tax','meta_data'])`. Multiple rates via `get_rate_id('suffix')`. `meta_data` persists onto the order's shipping line item.
- Since WC 9.7 rates carry `description` and `delivery_time`, rendered by the Checkout block. Classic checkout needs `woocommerce_after_shipping_rate` to print them.
- Rates are cached in session keyed by md5 of the package plus the `shipping` transient version. Include extra inputs (scheduled date, slot) in the package via `woocommerce_cart_shipping_packages` so they form part of the key. Bump with `WC_Cache_Helper::get_transient_version('shipping', true)` when settings change.
- Delivery charge belongs on the shipping rate. `WC_Cart::add_fee` is for tips and surcharges only.

### 4.2 Block checkout
- `woocommerce_register_additional_checkout_field()` gives a select or date field in the `order`, `contact` or `address` section with validation, persistence, email and My Account output for free. Static or semi-static option lists only. Not rendered by the classic shortcode checkout in 10.5.
- Dynamic UI (slots depending on address or rate) needs an inner block under `woocommerce/checkout-shipping-methods-block` registered with `registerCheckoutBlock`, plus `extensionCartUpdate` for server round trips and `setExtensionData` to submit. `ExperimentalOrderShippingPackages` slot fills still carry the experimental prefix.
- `IntegrationInterface` registered on `woocommerce_blocks_checkout_block_registration` ships scripts and settings into the block.
- Store API hooks: `woocommerce_store_api_checkout_update_order_from_request`, `woocommerce_store_api_checkout_order_processed`, `woocommerce_store_api_cart_select_shipping_rate`, `woocommerce_store_api_cart_update_customer_from_request`.
- Classic equivalents: `woocommerce_checkout_fields`, `woocommerce_checkout_process`, `woocommerce_checkout_create_order`, `woocommerce_checkout_update_order_review`.

### 4.3 Orders and HPOS
- Declare `FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true)` and `'cart_checkout_blocks'` on `before_woocommerce_init`.
- Use `wc_get_order`, `$order->update_meta_data()`, `$order->save()`, `wc_get_orders(['meta_query'=>…])`. Never post meta.
- Status hooks: `woocommerce_payment_complete`, `woocommerce_order_status_{status}`, `woocommerce_order_status_changed`.
- Custom status: `register_post_status('wc-out-for-delivery')` + `wc_order_statuses` filter. Slug max 20 chars including `wc-`. There is no `wc_register_order_status()`.
- HPOS orders screen: `bulk_actions-woocommerce_page_wc-orders`, `handle_bulk_actions-woocommerce_page_wc-orders`, `manage_woocommerce_page_wc-orders_columns`; meta box screen id from `wc_get_page_screen_id('shop-order')`; `woocommerce_order_actions` for the actions dropdown.
- **Fulfillments** (10.2+, behind `woocommerce_feature_fulfillments_enabled`, beta): native tracking number, provider and URL rendered in My Account and emails. Feature-detect and write a fulfillment when enabled.

### 4.4 Admin, REST, jobs, logs
- Woo's own guidance: third-party service connection → `WC_Integration`; full tab → `WC_Settings_Page`. React admin pages via `wc_admin_register_page` + `woocommerce_admin_pages_list` JS filter, built with `@wordpress/scripts` and `@woocommerce/dependency-extraction-webpack-plugin`.
- `register_rest_route('fleetbase/v1', …)` with `permission_callback`; admin JS uses `X-WP-Nonce` via `@wordpress/api-fetch`. Public webhook route uses `__return_true` and verifies inside the callback.
- Action Scheduler is bundled: `as_enqueue_async_action`, `as_schedule_single_action`, `as_schedule_recurring_action`. No built-in retry; reschedule with backoff and cap attempts.
- `wc_get_logger()->info($msg, ['source' => 'fleetbase'])`, viewable under WooCommerce > Status > Logs.

### 4.5 Customer-facing
- `woocommerce_order_details_after_order_table` covers both the thank-you page and My Account order view.
- `woocommerce_email_after_order_table($order, $sent_to_admin, $plain_text, $email)` for tracking in emails; subclass `WC_Email` for a dedicated "Out for delivery" email. WC 10's block email editor is optional.

### 4.6 Distribution and compliance
- wp.org guidelines (updated 2026-03-11): GPL-compatible code and bundled libs; no obfuscated or remote-loaded code; §6 permits a free plugin that connects to a paid external service but forbids a service that exists solely to validate license keys; §5 forbids disabling functionality after a trial or quota; §7 requires disclosure of external calls in the readme; §10 "Powered by" links opt-in only.
- Plugin Check (PCP) must pass `plugin_repo` and `security` categories for new submissions.
- Headers: `Requires Plugins: woocommerce`, `WC requires at least`, `WC tested up to`, `Requires PHP`. WC 10.x needs WP 6.8 and PHP 7.4; PHP 8.1 becomes mandatory in WC 11.5/11.6 (early 2027).
- WooCommerce Marketplace pays 70% of net revenue, requires QIT validation.
- Composer isolation: avoid Guzzle in a WP plugin. Use `wp_remote_request`. If vendoring is unavoidable, Strauss is the WP-oriented prefixer.
- License note: wp.org accepts GPLv2-or-later-compatible licenses. GPLv3 plugins are accepted. AGPLv3 is GPLv3-compatible but is unusual on wp.org and may draw review questions; the existing GitHub repo is AGPL-3.0. Decision needed (see plan).

---

## 5. Competitor landscape

### 5.1 Platforms with WooCommerce connectors
| Product | Woo connector | Checkout | Driver app / POD | Pricing | Notes |
|---|---|---|---|---|---|
| Shipday | Official free plugin, 700+ installs, 4.2★ | Slot picker + flat delivery fee | Yes / photo, signature | Free to 300 orders/mo; $29–$99/mo + $0.06–$0.20 per extra order | Closest competitor. EU localisation complaints, slot-plugin conflicts, settings live on Shipday servers |
| Onfleet | None official; Zapier or €199/yr FleetConnector | None | Yes / yes | From $619/mo | Enterprise pricing |
| Tookan | Unmaintained sample; webhook or Zapier | None | Yes / yes | Free 100 tasks; $99–$129/mo + $0.12/task | Review: refund refused when fee calc "not possible" |
| Track-POD | In-product sync | None | Yes / yes | $49–$99 per driver, 3-driver minimum | |
| Detrack | wp.org plugin closed 2024 | None | Yes / yes | $29 per driver | |
| Uber Direct (W7S) | Marketplace $49/yr | Live quote as shipping method, block + HPOS | Courier's | Plus Uber per-delivery fee | Only competitor doing live quotes as a shipping method |
| Metrobi | Managed setup | None | Marketplace drivers | Per delivery | US only |
| GetSwift, Gophr, Stuart | Dead or closed | | | | |
| Bringg, Locus, eLogii, Dispatch Science, Nash | No Woo connector | | | $359–$4,900/mo or quote | Enterprise |

### 5.2 WooCommerce-native plugins
| Product | Installs | What it does | Pricing | Gaps |
|---|---|---|---|---|
| Local Delivery Drivers for WooCommerce (PowerfulWP) | 800+, 4.3★ | Driver role, assign from order, browser driver panel; premium adds routing, tracking, POD, SMS | $159/yr + $29/mo native app | Slow screens, support complaints, no live quotes, API requested by users |
| Orderable | 5,000+, 4.6★ | Delivery/pickup slots, zone fees, block + HPOS | Free; Pro $149/yr | No drivers, tracking or POD |
| Delivery & Pickup Date Time (CodeRockz) | 5,000+, 4.9★ | Date/time picker, block + HPOS | Free; Pro from $45/yr | Slots only |
| Shipping Zones by Drawing | 600+ | Polygon zones, distance limits | Free, 5 zones | Zones only |
| Calculate Prices based on Distance | 300+ | Google distance-based rates, block checkout | Free + Pro | Rates only |
| Delivery Drivers for WooCommerce (DeVore) | closed 2025 | | | Paid licenses never delivered, per reviews |

### 5.3 What the market lacks
1. No open-source or self-hostable option. Every platform is closed SaaS; every WP plugin that owned driver management is closed or a solo-vendor premium.
2. Per-delivery fees and order caps are universal. Fleetbase Cloud meters drivers and vehicles, not orders.
3. Driver management inside WP admin exists only in PowerfulWP, with weak reviews.
4. Live distance or zone-based quotes as a native shipping method exist only for Uber Direct and generic distance plugins. No fleet platform exposes its own rate engine at checkout.
5. Block checkout and HPOS support is unstated for Shipday, PowerfulWP and both free Uber plugins.
6. Merchants expect "integration" to mean automatic fee calculation. Shipping the quote feature in v1 is not optional.
7. Trust and longevity are selling points after three closed plugins and "paid but no files" reviews.

### 5.4 Positioning
"Open-source delivery management for WooCommerce. Your drivers, your rates, no per-delivery fees. Cloud or self-hosted." Anchor against Shipday Professional ($49 + $0.10/order) and PowerfulWP ($159/yr + $29/mo for the app). Lead with: live quotes as a native shipping method, the Navigator driver app included, and AGPL self-hosting with unlimited orders.
