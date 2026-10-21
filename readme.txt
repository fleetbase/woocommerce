=== Fleetbase for WooCommerce ===
Contributors: fleetbase
Tags: delivery, local delivery, last mile, shipping, drivers
Requires at least: 6.8
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Last-mile delivery for WooCommerce. Live delivery quotes at checkout, your own drivers and vehicles, and automatic order sync with Fleetbase.

== Description ==

Fleetbase for WooCommerce connects your store to [Fleetbase](https://fleetbase.io), the open-source logistics platform, so you can run deliveries with your own drivers.

* **Live delivery quotes at checkout.** Prices come from your Fleetbase service rates (fixed, per distance, or tiered) and are charged as the shipping fee.
* **Automatic order sync.** Paid orders become delivery jobs in Fleetbase with the customer, address, items and delivery time.
* **Drivers and vehicles.** Manage your fleet from WordPress admin. Drivers use the free Fleetbase Navigator app for navigation and proof of delivery.
* **Status back to WooCommerce.** Dispatched, out for delivery, completed. Customers get a tracking link in their emails and account.
* **No per-delivery fees.** Fleetbase Cloud bills per driver and vehicle, never per order. Or self-host Fleetbase for unlimited use.

== External services ==

This plugin connects to the Fleetbase API (https://api.fleetbase.io) using the API key you enter in the setup wizard. It sends order details needed to make a delivery (pickup and delivery addresses, customer name, email and phone, item names, quantities, weights and dimensions, order totals and your chosen delivery time) and reads back delivery status, driver assignment and tracking information. Address lookups during setup use Fleetbase's geocoding. See the [Fleetbase terms of service](https://fleetbase.io/terms) and [privacy policy](https://fleetbase.io/privacy). Connecting to a self-hosted Fleetbase instance requires the Fleetbase Self-Hosted Connector add-on.

== Installation ==

1. Install and activate the plugin. WooCommerce 9.5 or newer is required.
2. Follow the setup wizard: connect your Fleetbase Cloud account with an API key, confirm your pickup location, and add "Fleetbase Delivery" to a shipping zone.
3. Create at least one service rate in Fleetbase. Customers will now see a delivery price at checkout.

== Frequently Asked Questions ==

= Do I need a Fleetbase account? =

Yes. Create a free trial at https://console.fleetbase.io/onboard. Fleetbase Cloud is $29/month plus $5/month per driver and vehicle, with no per-order fees. Fleetbase is open source and can also be self-hosted.

= Does it work with the block checkout? =

Yes, both the Cart and Checkout blocks and the classic shortcode checkout are supported. High-Performance Order Storage is supported.

== Changelog ==

= 1.0.0 =
* Initial release.
