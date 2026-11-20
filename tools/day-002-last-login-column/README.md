# Day 002 – Last Login Column

Adds a sortable **Last Login** column to **Users → All Users**. Shows a relative time ("3 hours ago") with the exact date on hover, and a dash for users who have never logged in.

## Install

Copy `last-login-column.php` into `wp-content/plugins/last-login-column/` and activate it, or upload the plugin zip.

## Notes

- Tracking starts on activation: existing users show a dash until their next login.
- Stored in user meta key `wpdt002_last_login` as a Unix timestamp.
- Uninstalling leaves the meta in place; delete the key `wpdt002_last_login` from `wp_usermeta` if you want it gone.
- | 002 | [Last Login Column](tools/day-002-last-login-column) | Sortable "Last Login" column on the Users screen |
