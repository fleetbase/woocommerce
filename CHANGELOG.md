# Changelog

All notable changes to Fleetbase for WooCommerce are listed here. The format follows Keep a Changelog, and the project uses semantic versioning.

## [1.0.0] — unreleased

### Added
- "Connect with Fleetbase": sign in and approve in Fleetbase instead of pasting an API key. Each store registers itself as an OAuth app (PKCE, no secret) and Fleetbase creates an API key for it; removing the store under Connected apps in Fleetbase revokes it. Needs Fleetbase 1.6.74 or newer.
- Status updates are registered automatically with Fleetbase's webhook endpoint API and verified with the `X-Fleetbase-Signature` header. When Fleetbase cannot reach the store, the wizard falls back to the manual webhook step and the poller.
- Setup wizard: connect a Fleetbase Cloud account, confirm the pickup location, add the shipping method and set up status updates.
- "Fleetbase Delivery" shipping method with live quotes from your service rates, shown in both the block and classic checkout, with caching and an optional flat fallback during outages.
- Delivery scheduling at checkout: as soon as possible, or a day and time window within configurable hours, lead time and horizon.
- Automatic order sync to Fleetbase on payment or configurable statuses, with retries, idempotent creation, cash-on-delivery support and a per-order sync log.
- Status sync back from Fleetbase through a webhook receiver and a polling fallback; "Out for delivery" order status; proof-of-delivery links; WooCommerce Fulfillments support.
- Customer tracking on the thank-you and account pages, in emails, through an "Out for delivery" email, and via the `[fleetbase_tracking]` shortcode and block.
- Deliveries, Drivers, Vehicles and Service rates pages in WordPress admin, with a Fleetbase dispatcher role.
