=== Simple Hardening ===
Contributors: zaman1100
Tags: security, hardening, xmlrpc, user enumeration, wordpress version
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Three small security tweaks with on/off switches: hide the WordPress version, block user name enumeration, and disable XML-RPC.

== Description ==

Simple Hardening closes a few common information leaks and attack doors without a heavy security suite. Each tweak has its own switch under Settings > Hardening.

* **Hide WordPress version** (on by default): removes the generator tag from your page source.
* **Block user name enumeration** (on by default): hides the public REST user list and stops `?author=1` from revealing login names to logged-out visitors.
* **Disable XML-RPC** (off by default): turns off the legacy `xmlrpc.php` API that attackers often abuse.

No external services, no tracking, no database tables.

This plugin reduces exposure; it is not a complete security solution. Keep WordPress, themes and plugins updated and use strong passwords.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the zip from Plugins > Add New.
2. Activate it.
3. Go to Settings > Hardening to review the switches.

== Frequently Asked Questions ==

= Why is XML-RPC off by default? =

Jetpack, the WordPress mobile app and some remote publishing tools need it. Turn it on only if you do not use any of them.

= Will blocking the user list break anything? =

Logged-in users are not affected, so the block editor works normally. A few plugins or headless setups read the public users endpoint without logging in; if something breaks, switch this option off.

= Does hiding the version make my site secure? =

No. It only removes an easy hint for bots. Updating WordPress is what protects you.

== Changelog ==

= 1.0.0 =
* First release.
