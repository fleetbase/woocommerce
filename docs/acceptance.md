# Acceptance script (about 15 minutes)

Run this on the sandbox (`docs/dev-setup.md`) or any store with the plugin, a connected Fleetbase account, a pickup location, one service rate and one driver.

1. **Connect.** WordPress admin → Fleetbase → Setup. Choose the account type, paste a live or test API key, click Test connection, then Connect. Expected: the organisation name appears and the dashboard shows green rows for organisation, host and API key.
2. **Pickup.** In the wizard's pickup step search your store address and click "Use as pickup". Expected: the dashboard's pickup row turns green and shows the address.
3. **Shipping method.** WooCommerce → Settings → Shipping → your delivery zone → Add shipping method → Fleetbase Delivery. Expected: the dashboard's shipping method row turns green.
4. **Quote at checkout.** Add a product to the cart, open checkout, enter an address inside your service area. Expected: one option per matching service rate, each with its price and delivery promise. Enter an address outside the area. Expected: no delivery options.
5. **Delivery time.** Under the shipping options choose "Choose a day and time", pick a day and a window. Expected: the quotes refresh and the chosen window stays selected.
6. **Place the order** with cash on delivery. Expected: the thank-you page shows "Delivery time:" and the shipping line shows the chosen rate.
7. **Sync.** Open Fleetbase → Deliveries in WordPress admin within a minute. Expected: the order appears with a tracking number and status "Created". WooCommerce → Orders shows "Sent to Fleetbase" in the Delivery column, and the order's Fleetbase delivery box lists the Fleetbase id, tracking link and sync log.
8. **Dispatch.** On the Deliveries page choose a driver, click Assign, then Dispatch. Expected: the row shows "Dispatched" and the WooCommerce status becomes "Out for delivery". The order has notes for the driver assignment and dispatch, and the customer receives the "out for delivery" email.
9. **Driver app.** The driver signs in to Fleetbase Navigator with the email or phone and password you set on the Drivers page. Expected: the job is visible; completing it with a photo marks it delivered.
10. **Completion.** After the driver completes the job (or you complete it in the Fleetbase console), wait for the next poll or the webhook. Expected: the WooCommerce order becomes "Completed", the note "Delivered by Fleetbase" appears with proof-of-delivery links, and the customer's order page shows "Delivered".
11. **Tracking page.** Visit the page holding the Delivery tracking block or `[fleetbase_tracking]` shortcode and enter the tracking number. Expected: the status timeline for that delivery.
12. **Manage the fleet.** Fleetbase → Drivers: add a driver. Fleetbase → Vehicles: add a vehicle. Fleetbase → Service rates: add a rate. Expected: each appears in the Fleetbase console, and the new rate is quoted at checkout immediately.
13. **Outage.** Temporarily set an invalid API host (or block the Fleetbase host) and reload checkout. Expected: with the fallback off, the delivery method is hidden; with the flat fallback on, the fallback rate appears. Orders placed meanwhile sync once Fleetbase is reachable again.
