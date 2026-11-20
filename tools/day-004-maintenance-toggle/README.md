# Day 004 – Maintenance Toggle

Turn maintenance mode on or off with one click from the admin bar.

- **ON:** visitors get a 503 "Back soon" page (search engines treat 503 as temporary). Users who can manage options browse the site normally.
- **OFF:** the site behaves as usual.

The admin bar item turns red while maintenance mode is on.

## Install
Copy `maintenance-toggle.php` into `wp-content/plugins/maintenance-toggle/` and activate it, or upload the plugin zip.

## Notes
- `wp-login.php`, `wp-admin`, AJAX, cron and the REST API are not blocked.
- Page caching plugins can serve a cached page to visitors; clear the cache after toggling.
- State is stored in the option `wpdt004_maintenance_on`.

## Hooks
| Hook | Type | Purpose |
|---|---|---|
| `wpdt004_message` | filter | Change the message shown to visitors |
| `wpdt004_retry_after` | filter | Change the `Retry-After` seconds (default 3600) |
