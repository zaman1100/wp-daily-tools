# Day 003 – Trim Emojis & Embeds

Removes small bits of front-end bloat WordPress loads by default:

- Emoji detection script, emoji styles and the `s.w.org` DNS prefetch
- The `wp-embed` script and oEmbed discovery links in `<head>`

Embedding YouTube, Vimeo and other providers in your content keeps working. Emoji typed into posts still display normally, since modern browsers render them natively.

## Install
Copy `trim-emojis-embeds.php` into `wp-content/plugins/trim-emojis-embeds/` and activate it, or upload the plugin zip.

## Hooks
| Hook | Type | Purpose |
|---|---|---|
| `wpdt003_disable_emojis` | filter | Return `false` to keep the emoji scripts |
| `wpdt003_disable_embeds` | filter | Return `false` to keep the embed script |
