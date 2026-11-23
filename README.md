# WP Daily Tools

One small, self-contained WordPress tool every day. Each tool is a single-file plugin in its own folder under `tools/`, so you can drop in just the one you need.

## Tools

| Day | Tool                                                           | What it does                                               |
| --- | -------------------------------------------------------------- | ---------------------------------------------------------- |
| 001 | [Quick Duplicate](tools/day-001-quick-duplicate)               | One-click duplicate for posts, pages and custom post types |
| 002 | [Last Login Column](tools/day-002-last-login-column)           | Sortable "Last Login" column on the Users screen           |
| 003 | [Trim Emojis & Embeds](tools/day-003-trim-emojis-embeds)       | Removes emoji and wp-embed scripts from the front end      |
| 004 | [Maintenance Toggle](tools/day-004-maintenance-toggle)         | One-click maintenance mode from the admin bar              |
| 005 | [GitHub Repos Shortcode](tools/day-005-github-repos-shortcode) | Show a GitHub user's repos as a card grid with a shortcode |
| 006 | [Simple Login Lockout](tools/day-006-simple-login-lockout)     | Locks out an IP after too many failed logins               |

## Conventions

- One folder per tool: `tools/day-NNN-slug/`
- Single PHP file, no dependencies, prefix `wpdtNNN_` on every function
- Each tool has its own short README
- Requires WordPress 5.8+ and PHP 7.4+

## License

GPL-2.0-or-later
