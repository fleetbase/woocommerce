=== Fleetbase for WooCommerce ===
Contributors: fleetbase
Tags: delivery, local delivery, last mile, shipping, drivers
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Last-mile delivery for WooCommerce. Live delivery quotes at checkout, your own drivers and vehicles, and automatic order sync with Fleetbase.

== Description ==

Fleetbase for WooCommerce connects your store to [Fleetbase](https://fleetbase.io), the open-source logistics platform, so you can run deliveries with your own drivers and no per-delivery fees.

**At checkout**

* Live delivery prices from your Fleetbase service rates: flat, per distance, or tiered, limited to the areas you serve.
* Customers choose "as soon as possible" or a day and time window within your delivery hours.
* Works with the Cart and Checkout blocks and the classic checkout.

**After the order**

* Paid orders become delivery jobs in Fleetbase automatically, with the customer, address, items, quote and delivery time. Cash-on-delivery amounts are passed along.
* Drivers get the job in the free Fleetbase Navigator app for iOS and Android, with navigation and photo or signature proof of delivery.
* Status comes back to WooCommerce: "Out for delivery" when the driver leaves, "Completed" when it is delivered, with proof-of-delivery links on the order.
* Customers get a tracking link on the thank-you page, in their account and in emails, plus an "out for delivery" email.

**In WordPress admin**

* Deliveries page to assign drivers, dispatch, refresh and cancel.
* Drivers, Vehicles and Service rates pages so day-to-day work does not need a second dashboard.
* A dispatcher role for staff who run deliveries but should not change store settings.

**Pricing**

The plugin is free. Fleetbase Cloud is $29/month plus $5/month per driver and per vehicle, with orders unmetered. Fleetbase is open source and can also be self-hosted.

== External services ==

This plugin connects to the Fleetbase API at https://api.fleetbase.io using the API key you enter in the setup wizard. Nothing is sent before you connect.

What is sent: for each order you choose to deliver, the pickup location, the customer's delivery address, name, email and phone number, item names, SKUs, quantities, prices, weights and dimensions, order totals, payment method, your chosen delivery time and the WooCommerce order number. At checkout, the customer's address is sent to obtain delivery quotes. During setup, your store address is sent for geocoding. Drivers, vehicles and service rates you manage in WordPress are created in your Fleetbase account.

What is received: delivery status, driver name, tracking numbers and links, proof-of-delivery links, delivery quotes, and your drivers, vehicles and rates.

Fleetbase terms of service: https://fleetbase.io/terms. Privacy policy: https://fleetbase.io/privacy.

The plugin does not send any data to Fleetbase or anyone else about visitors who do not check out, and it does not phone home.

== Installation ==

1. Install and activate the plugin. WooCommerce 9.5 or newer is required.
2. Follow the setup wizard: connect your Fleetbase Cloud account with an API key, confirm your pickup location and add "Fleetbase Delivery" to a shipping zone.
3. Create at least one service rate under Fleetbase → Service rates. Customers will now see a delivery price at checkout.
4. Optional: add the webhook URL shown in the wizard to your Fleetbase console for instant status updates. Without it the plugin checks every few minutes.

== Frequently Asked Questions ==

= Do I need a Fleetbase account? =

Yes. Create a free trial at https://console.fleetbase.io/onboard.

= Can I connect to my own Fleetbase instance? =

Self-hosted Fleetbase is supported through the Fleetbase Self-Hosted Connector add-on, available from fleetbase.io.

= Does it work with the block checkout? =

Yes, both the Cart and Checkout blocks and the classic shortcode checkout are supported. High-Performance Order Storage and the Fulfillments feature are supported.

= Which orders are sent to Fleetbase? =

By default, orders that used the Fleetbase Delivery shipping method and reached the Processing or Completed status. You can change the trigger statuses or send every physical order in Settings.

= What happens if Fleetbase is down? =

Checkout keeps working: hide the delivery method or offer a flat fallback rate, your choice. Orders queue and sync with retries once Fleetbase is reachable.

== Screenshots ==

1. Live delivery quotes and the delivery-time picker in the Checkout block.
2. The Fleetbase dashboard in WordPress admin.
3. Deliveries page with assign and dispatch actions.
4. Service rates editor.
5. Order screen with the Fleetbase delivery box and sync log.
6. Customer tracking page.

== Changelog ==

= 1.0.0 =
* Initial release.
