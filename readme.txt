=== Telegram Error Notifier ===
Contributors: rashedul007
Tags: notifier, telegram, alert, WordPress, fatal error
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Telegram Error Notifier sends alerts to Telegram whenever your WordPress site encounters errors, ensuring you're always informed.

== Description ==

Telegram Error Notifier helps you stay informed of your WordPress website's issues in real time by sending notifications to your Telegram account. The plugin covers various types of alerts such as fatal errors, plugin activation issues, and other configurable WordPress events. It integrates with WooCommerce and Wordfence to send security alerts and transactional notifications as well.

Features include:

* Notifications for WordPress fatal errors.
* Integration with WooCommerce for product and order alerts.
* Wordfence integration to receive security alerts.
* Alerts on user login activities (differentiated for admin and non-admin users).
* Daily scheduled alert for the front page being set to 'no index'.

== Installation ==

1. Upload the `telegram-error-notifier` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure the Telegram settings via the 'Settings' menu.

== Frequently Asked Questions ==

= How do I configure the Telegram bot for notifications? =

You need to create a Telegram bot and get the API Token and Chat ID. Enter these details in the plugin settings page to start receiving alerts.

= Does this plugin work with WooCommerce? =

Yes, the plugin integrates with WooCommerce to send alerts related to products and orders.

== Changelog ==

= 1.9.2 =
* Optimized class initialization to use singleton pattern.
* Added daily scheduled alert if Wordfence is not installed.
* Improved integration with WooCommerce for better alerting.

= 1.9.1 =
* Added support for Wordfence alerts.
* Minor bug fixes.

= 1.9.0 =
* Initial release with basic Telegram error notification functionality.

== Upgrade Notice ==

= 1.9.2 =
Upgrade to benefit from WooCommerce and Wordfence alert improvements, as well as general performance enhancements.
