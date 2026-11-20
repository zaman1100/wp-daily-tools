# Day 001 – Quick Duplicate

Adds a **Duplicate** link to the row actions of posts, pages and custom post types. The copy is created as a draft with the same content, excerpt, meta, featured image and taxonomies.

## Install
Copy `quick-duplicate.php` into `wp-content/plugins/quick-duplicate/` and activate it, or zip the folder and upload it.

## Notes
- WooCommerce products are excluded on purpose (WooCommerce already ships its own duplicator that handles variations).
- Nonce and capability checked on every request.

## Hooks
| Hook | Type | Purpose |
|---|---|---|
| `wpdt001_excluded_post_types` | filter | Change which post types get no Duplicate link |
| `wpdt001_skip_meta_keys` | filter | Meta keys that are not copied |
| `wpdt001_post_duplicated` | action | Runs after duplication (`$new_id`, `$original_id`) |
