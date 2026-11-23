# Day 005 – GitHub Repos Shortcode

Show a GitHub user's public repositories as a responsive card grid on any page or post.

```
[wpdt_github_repos user="zaman1100"]
[wpdt_github_repos user="zaman1100" limit="9" columns="3" show_forks="no" hours="6"]
```

## Install
Copy `github-repos-shortcode.php` into `wp-content/plugins/github-repos-shortcode/` and activate it, or upload the plugin zip. Then paste the shortcode into a page (use a Shortcode block in the block editor).

## Attributes
| Attribute | Default | Meaning |
|---|---|---|
| `user` | – | GitHub username (required) |
| `limit` | 12 | Number of repos to show |
| `columns` | 3 | 1 to 4 columns (always 1 on mobile) |
| `show_forks` | no | `yes` to include forked repos |
| `hours` | 6 | How long to cache the GitHub response |

## Notes
- No API key needed. GitHub allows 60 unauthenticated requests per hour per server IP, and results are cached, so this is plenty.
- If GitHub is down or rate limiting, the last good copy (kept up to a week) is shown.
- Only logged-in editors see error messages; visitors see nothing on failure.

## Hooks
| Hook | Type | Purpose |
|---|---|---|
| `wpdt005_request_args` | filter | Change request args, e.g. add an `Authorization` header with a token |
