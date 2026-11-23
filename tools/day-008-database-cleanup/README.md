# Day 008 – Database Cleanup

See how much junk is sitting in your database and clear each type safely with one click.

Go to **Tools → Database Cleanup**. Each row shows how many items were found and has its own **Clean** button, or use **Clean everything above**.

| Item | What is removed |
|---|---|
| Old post revisions | Revisions older than 30 days (newer ones are kept) |
| Auto-drafts | Empty auto-drafts older than 7 days |
| Trashed posts and pages | Everything in the trash |
| Spam comments | Comments marked as spam |
| Trashed comments | Comments in the comment trash |
| Expired transients | Cached data that has already expired |
| Orphaned post meta | Custom field rows whose post no longer exists |

## Install
Copy `database-cleanup.php` into `wp-content/plugins/database-cleanup/` and activate it, or upload the plugin zip.

## Notes
- Back up first. Removed items cannot be restored.
- Posts and comments are removed with WordPress's own delete functions, so their meta, terms and children are cleaned too.
- Large sites are cleaned in batches of 500. If items remain, the notice says so; press the button again.
- Published content, approved comments and unexpired transients are never touched.
- Needs the `manage_options` capability (administrators). Every action is nonce-protected.
- The plugin stores nothing, so there is nothing to uninstall.

## Hooks
| Hook | Type | Purpose |
|---|---|---|
| `wpdt008_revision_days` | filter | Only remove revisions older than this many days (default 30, use 0 for all) |
| `wpdt008_autodraft_days` | filter | Only remove auto-drafts older than this many days (default 7) |
| `wpdt008_batch_size` | filter | Items processed per click (default 500) |
