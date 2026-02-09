# Fleetbase for WooCommerce

![Fleetbase Logo](https://fleetbase.io/images/fleetbase-logo.png)

Official Fleetbase integration for WooCommerce - Open source logistics management for your online store.

## Description

Fleetbase for WooCommerce brings professional logistics and delivery management to your WooCommerce store. Automate order fulfillment, provide real-time tracking to customers, and scale your delivery operations with the power of Fleetbase, the open-source logistics operating system.

### Key Features

- **Frictionless Onboarding**: Get started in 60 seconds with automatic Fleetbase Cloud account provisioning
- **200-Unit Free Trial**: Test with up to 100 orders at no cost, no credit card required
- **Automatic Order Sync**: Orders are automatically sent to Fleetbase when placed
- **Real-Time Shipping Rates**: Display live delivery quotes at checkout
- **Customer Tracking**: Provide customers with real-time order tracking
- **Webhook Integration**: Automatic order status updates from Fleetbase to WooCommerce
- **Resource Usage Dashboard**: Monitor your usage and costs in real-time
- **Flexible Deployment**: Use Fleetbase Cloud or self-host for unlimited usage

### Why Fleetbase?

- **Open Source**: Complete transparency and customization
- **Usage-Based Pricing**: Pay only for what you use, no hidden fees
- **Complete TMS**: Full transportation management system, not just shipping labels
- **Driver Apps**: Native iOS and Android apps for your delivery team
- **Multi-Vendor Ready**: Built for marketplace scenarios from the ground up

## Installation

### From WordPress.org (Recommended)

1. In your WordPress admin, go to **Plugins > Add New**
2. Search for "Fleetbase for WooCommerce"
3. Click **Install Now** and then **Activate**
4. Follow the setup wizard to create your Fleetbase account

### Manual Installation

1. Download the latest release from [GitHub Releases](https://github.com/fleetbase/fleetbase-woocommerce/releases)
2. Upload the `fleetbase-woocommerce` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Follow the setup wizard to create your Fleetbase account

## Requirements

- WordPress 5.8 or higher
- WooCommerce 6.0 or higher
- PHP 7.4 or higher
- HTTPS enabled (recommended for security)

## Quick Start

1. **Install and Activate** the plugin
2. **Complete the Setup Wizard**:
   - Enter your email and create a password
   - Your Fleetbase account will be created automatically
   - The plugin will configure itself with your API credentials
3. **Start Selling**: Orders will automatically sync to Fleetbase
4. **Monitor Usage**: View your resource consumption in the Fleetbase dashboard

## Configuration

### Settings

Access plugin settings at **Fleetbase > Settings** in your WordPress admin.

- **API Configuration**: View your API connection status
- **Auto-Sync Orders**: Enable/disable automatic order synchronization
- **Tracking Page**: Enable customer-facing order tracking

### Shipping Zones

1. Go to **WooCommerce > Settings > Shipping**
2. Select a shipping zone
3. Click **Add shipping method**
4. Select **Fleetbase** from the dropdown
5. Configure the method title and enable it

## Usage

### Viewing Synced Orders

Synced orders will have a Fleetbase tracking number added to the order notes. You can view the full order details in your [Fleetbase Console](https://console.fleetbase.io).

### Customer Tracking

Customers can track their orders by:
1. Using the tracking link in their order confirmation email
2. Visiting your tracking page (add the `[fleetbase_tracking_page]` shortcode to any page)

### Monitoring Usage

View your resource usage at **Fleetbase > Dashboard**:
- Current trial status
- Resource unit consumption
- Plan recommendations based on your usage

## Pricing

### Free Trial

- **200 resource units** (equivalent to 100 orders)
- **All features included**
- **No credit card required**

### Fleetbase Cloud Plans

After your trial, choose a plan based on your usage:

- **Starter**: $200/month - 300 units
- **Scale**: $400/month - 1,200 units
- **Pro**: $600/month - 2,400 units
- **Elite**: $800/month - 3,600 units
- **Enterprise**: $1,000/month - 5,000 units
- **Enterprise Plus**: $1,500/month - 7,500 units

[View detailed pricing](https://fleetbase.io/pricing)

### Self-Hosted Option

For high-volume merchants, deploy Fleetbase on your own infrastructure for unlimited usage. [Learn more about self-hosting](https://github.com/fleetbase/fleetbase).

## Support

- **Documentation**: [docs.fleetbase.io](https://docs.fleetbase.io)
- **Community Support**: [GitHub Discussions](https://github.com/fleetbase/fleetbase-woocommerce/discussions)
- **Bug Reports**: [GitHub Issues](https://github.com/fleetbase/fleetbase-woocommerce/issues)
- **Email Support**: Available for paid Fleetbase Cloud plans

## Contributing

We welcome contributions from the community! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/fleetbase/fleetbase-woocommerce.git

# Install in your WordPress plugins directory
ln -s /path/to/fleetbase-woocommerce /path/to/wordpress/wp-content/plugins/

# Activate the plugin in WordPress
```

## License

This plugin is licensed under the GPL v2 or later.

```
Fleetbase for WooCommerce
Copyright (C) 2026 Fleetbase Pte. Ltd.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Credits

Developed and maintained by [Fleetbase](https://fleetbase.io).

## Links

- [Fleetbase Website](https://fleetbase.io)
- [Fleetbase GitHub](https://github.com/fleetbase/fleetbase)
- [Documentation](https://docs.fleetbase.io)
- [Console](https://console.fleetbase.io)
