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

The plugin is free, with Fleetbase Cloud or your own server. Fleetbase Cloud is $29/month plus $5/month per driver and per vehicle, with orders unmetered. Fleetbase is open source, so you can also host it yourself and connect the plugin to it at no cost.

== External services ==

This plugin relies on Fleetbase, a delivery management service, to price deliveries, create delivery jobs and report their status. Nothing is sent until you connect the plugin in its setup wizard, and activating the plugin contacts no one.

= Fleetbase API =

What it is: the API of Fleetbase Cloud, provided by Fleetbase, or of a Fleetbase server you host yourself.

Where data goes: https://api.fleetbase.io when you choose Fleetbase Cloud. When you choose "Self-hosted Fleetbase", it goes only to the API host you enter, and nothing is sent to Fleetbase Cloud.

What is sent, and when:

* When you connect: the API key you enter. With "Connect with Fleetbase", your store's admin address is sent once so the store can register as an app, your browser opens Fleetbase to sign in and approve, and Fleetbase returns an API key for the store. The plugin then registers your store's status-update address with Fleetbase.
* During setup: your store address, to find your pickup location.
* At checkout, when a customer enters or changes a delivery address: that address, the pickup location and the chosen delivery time, to get delivery prices.
* When an order is sent for delivery (by default when it is paid and uses Fleetbase Delivery): the pickup location, the customer's delivery address, name, email and phone number, item names, SKUs, quantities, prices, weights and dimensions, order totals, payment method, the chosen delivery time and the WooCommerce order number.
* While a delivery is open: requests for its current status, every few minutes if status updates cannot reach your store.
* When you manage drivers, vehicles, service rates or deliveries in WordPress admin: the details you enter.

What is received: delivery prices, delivery status, driver name, tracking numbers and links, proof-of-delivery links, and your drivers, vehicles and rates.

Fleetbase Cloud terms of service: https://fleetbase.io/terms
Fleetbase Cloud privacy policy: https://fleetbase.io/privacy

A self-hosted server is run by you or your provider, under your own terms.

The plugin sends nothing to Fleetbase or anyone else about visitors who do not check out, and it does not send usage data.

== Source code ==

The JavaScript and CSS in `assets/build` are compiled. The readable source is in `assets/src` in the plugin's public repository: https://github.com/fleetbase/woocommerce

To build it yourself, install Node.js and pnpm, then run `pnpm install` and `pnpm build` in the repository.

== Installation ==

1. Install and activate the plugin. WooCommerce 9.5 or newer is required.
2. Open Fleetbase → Setup in WordPress admin and follow the wizard: click "Connect with Fleetbase", sign in and approve, then confirm your pickup location and add "Fleetbase Delivery" to a shipping zone. You can paste an API key instead if you prefer.
3. Create at least one service rate under Fleetbase → Service rates. Customers will now see a delivery price at checkout.

Status updates are set up automatically when you connect. If Fleetbase cannot reach your store's address, the wizard shows the webhook URL to add in your Fleetbase console, and the plugin checks for updates every few minutes in the meantime.

== Frequently Asked Questions ==

= Do I need a Fleetbase account? =

You need Fleetbase Cloud or your own Fleetbase server. Create a free Cloud trial at https://console.fleetbase.io/onboard.

= What does "Connect with Fleetbase" do? =

It signs you in to Fleetbase and asks you to approve this store for one of your organizations. Fleetbase then creates an API key named after your store (listed under Developers → API Keys), and the plugin uses that key exactly as if you had pasted it. Only an administrator of the organization can approve. Removing the store under Account → Connected apps in Fleetbase revokes the key.

= Can I connect to my own Fleetbase instance? =

Yes, at no cost. Choose "Self-hosted Fleetbase" in the setup wizard and enter your API host. A site administrator has to do this, and the server needs https unless it is on a private network. "Connect with Fleetbase" needs Fleetbase core-api 1.6.74 or newer; on older servers, paste an API key instead. Automatic status updates need your store to have a public address that your server can reach.

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
