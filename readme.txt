=== VW Fraud Checker ===
Contributors: vendweave, webexpertrabbi
Donate link: https://vendweave.com/
Tags: fraud, risk, ecommerce, courier, woocommerce
Requires at least: 7.0
Tested up to: 8.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detect suspicious customers by phone number. Aggregate courier delivery, return and cancellation metrics to reduce fraud.

== Description ==

VW Fraud Checker empowers Bangladeshi ecommerce and logistics teams to evaluate customer risk before shipping. Input a customer's phone number to pull historical delivery performance from your connected courier partners. Visual ratios highlight risky behavior so your fulfillment team can make smarter decisions.

* Courier adapter architecture for Pathao, Steadfast, RedX and more.
* Background sync ensures your fraud database stays fresh.
* Shortcode `[vw_fraud_checker]` for frontend search widgets.
* Exportable admin dashboard with CSV and data audit features.
* Privacy-first design: respect customer data retention and security best practices.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/vw-fraud-checker` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Fraud Checker → Settings** to configure courier API credentials or import CSV data.
4. Add the `[vw_fraud_checker]` shortcode to any page or post to embed the fraud check form.

== Frequently Asked Questions ==

= Which couriers are supported? =
The first public release ships with a mock adapter. Real courier integrations will roll out soon after community feedback. You can extend the plugin with custom adapters using the documented interface.

= Does this work with WooCommerce? =
Yes. VW Fraud Checker is built to complement WooCommerce stores. Future releases will surface fraud scores directly on order screens.

= How is customer data protected? =
Only the minimum required metadata is stored, and you control the retention policy. Follow the setup guide for instructions on encrypting API keys and scheduling data purges.

== Screenshots ==

1. Admin dashboard overview with risk summary cards.
2. Phone number lookup form embedded via shortcode.
3. Courier adapter settings screen for API credentials.

== Changelog ==

= 0.1.0 =
* Initial scaffolding release with admin skeleton, shortcode placeholder, REST endpoint stub and database schema.

== Upgrade Notice ==

= 0.1.0 =
This is the initial developer preview. Avoid production use until courier adapters and security hardening are completed.
