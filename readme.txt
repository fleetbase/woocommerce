=== Fleetbase – Local Delivery, Drivers & Tracking for WooCommerce ===
Contributors: fleetbase
Tags: local delivery, delivery drivers, delivery tracking, driver app, delivery date
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Local delivery for WooCommerce with your own drivers: distance-based delivery fees, delivery date and time slots, a driver app and delivery tracking.

== Description ==

Fleetbase for WooCommerce adds local delivery to your store, run by your own drivers, with no per-delivery fees. It connects WooCommerce to [Fleetbase](https://fleetbase.io), the open-source logistics platform, which sends each order to a driver and reports its progress back to the order.

It is made for shops that deliver their own orders: restaurants, grocers, bakeries, florists, pharmacies and other local businesses that today charge a flat delivery fee, take orders over the phone or pay a delivery app a cut of every order.

**At checkout**

* Delivery fees from your own rates: a base fee plus a price per kilometre or mile, or a price for each distance tier, limited to the delivery zones you serve. Addresses outside them see a message you write.
* Same-day or scheduled delivery: customers choose "as soon as possible", or a delivery date and time slot within your delivery hours, lead time and booking window.
* Works with the Cart and Checkout blocks and the classic checkout.

**Dispatch and drivers**

* Paid orders become delivery jobs in Fleetbase automatically, with the customer, address, items, quote and delivery time. Cash-on-delivery amounts are passed along.
* Assign drivers and dispatch from the Deliveries page in WordPress admin.
* Drivers get each job in the free Fleetbase Navigator driver app for iOS and Android, with turn-by-turn navigation and photo or signature proof of delivery.
* Manage your delivery fleet, its drivers and vehicles, and your delivery rates without leaving WordPress. A dispatcher role lets staff run deliveries without changing store settings.

**Delivery tracking**

* Status comes back to WooCommerce on its own: "Out for delivery" when the driver leaves, "Completed" when the order is delivered, with proof-of-delivery links on the order.
* Customers get a tracking link on the thank-you page, in their account and in emails, an "out for delivery" email, and a delivery tracking page.

**Pricing**

The plugin is free, with Fleetbase Cloud or your own server. Fleetbase Cloud is $29/month plus $5/month per driver and per vehicle, with orders unmetered. Fleetbase is open source, so you can also host it yourself and connect the plugin to it at no cost.

**Learn more**

* [Fleetbase for WooCommerce](https://fleetbase.io/woocommerce): the overview, with pricing and setup.
* [How a delivery works](https://fleetbase.io/woocommerce/delivery-management): one order followed from checkout through dispatch and tracking to proof of delivery.
* [Features, screenshots and requirements](https://fleetbase.io/woocommerce/plugin).
* [WooCommerce delivery plugins compared](https://fleetbase.io/compare/woocommerce-delivery-plugins): how this plugin compares with other ways to deliver WooCommerce orders.

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

= Can I charge delivery fees by distance? =

Yes. Each service rate in Fleetbase is either a base fee plus a price per kilometre or mile, or a set price for each distance tier, and can be limited to a service area or zone. At checkout the plugin quotes your rates for the customer's address and shows the matching prices.

= Can customers choose a delivery date and time? =

Yes. Set your delivery hours, how much notice you need and how many days ahead customers can book. Customers then choose "as soon as possible" or a day and time window at checkout, and the chosen time is sent to Fleetbase with the order.

= Is there an app for drivers? =

Yes, Fleetbase Navigator, free for iOS and Android. Drivers see the deliveries assigned to them, navigate to each stop and collect photo or signature proof of delivery. Their progress updates the WooCommerce order.

= Can customers track their delivery? =

Yes. Customers get a tracking link on the thank-you page, in their account and in their order emails, and an email when the order is out for delivery. You can also add a tracking page anywhere with the `[fleetbase_tracking]` shortcode or block.

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

= 1.0.1 =
* New plugin name and a clearer directory listing. No code changes.

= 1.0.0 =
* Initial release.
