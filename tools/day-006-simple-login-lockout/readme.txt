=== Simple Login Lockout ===
Contributors: zaman1100
Tags: security, login, brute force, lockout
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stops brute-force password guessing by temporarily locking out an IP address after too many failed logins.

== Description ==

Bots constantly try to guess WordPress passwords. Simple Login Lockout counts failed logins per IP address and, once the limit is reached, blocks that IP for a set time, even if it then enters the correct password.

* Works on wp-login.php and XML-RPC logins
* Choose how many attempts are allowed and how long the lockout lasts
* No account, no external service, no tracking
* Tiny: one file, uses WordPress transients, nothing added to your database except one settings option
* Developer hooks for proxies and CDNs

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the zip from Plugins > Add New.
2. Activate it.
3. Optional: go to Settings > Login Lockout to change the limits (default: 5 attempts, 15 minute lockout).

== Frequently Asked Questions ==

= I locked myself out. What do I do? =

Wait for the lockout to end (15 minutes by default), or rename the plugin folder over FTP to deactivate it.

= My site is behind Cloudflare or another proxy. =

By default the plugin trusts only the connecting IP address. Behind a trusted proxy, use the `wpdt006_client_ip` filter to return the real visitor IP, otherwise all visitors may share one counter.

= Does it send emails or log attempts? =

No. It only counts and blocks.

== Changelog ==

= 1.0.0 =
* First release.
