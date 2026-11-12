# Free Shipping Progress Bar for WooCommerce

A lightweight WooCommerce plugin that shows customers how much more they need to spend to unlock free shipping. It aims to raise average order value and reduce cart abandonment.

## Features
- Progress bar on the **cart page**, **checkout page** and **mini-cart**
- Updates live through WooCommerce AJAX fragments (add to cart, quantity change)
- Admin settings page: **WooCommerce > Free Shipping Bar** (enable/disable, threshold amount)
- Accessible markup (`role="progressbar"` with ARIA values)
- Security: capability checks, sanitised settings, escaped output
- Translation-ready (`fsb-free-shipping-bar` text domain)

## Installation
1. Download the zip and go to **Plugins > Add New > Upload Plugin**.
2. Activate it (WooCommerce must be active).
3. Open **WooCommerce > Free Shipping Bar** and set your threshold.
4. Make sure your WooCommerce **Free shipping** method uses the same minimum amount.

## How it works
- Reads `WC()->cart->get_displayed_subtotal()` and compares it with the threshold.
- Hooks used: `woocommerce_before_cart_table`, `woocommerce_before_checkout_form`, `woocommerce_widget_shopping_cart_before_buttons`, `woocommerce_add_to_cart_fragments`.
- Settings use the WordPress Settings API (`register_setting` with a sanitize callback).

## Limitations / roadmap
- Targets the **classic** cart and checkout. Block-based Cart/Checkout needs a Store API extension (planned).
- Planned: custom message text, colour options, per-zone thresholds, shortcode.

## Requirements
WordPress 6.0+, WooCommerce, PHP 7.4+

## Author
Sameer Shah
