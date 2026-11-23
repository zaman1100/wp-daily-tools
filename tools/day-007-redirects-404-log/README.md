# Day 007 – Redirects & 404 Log

See which broken URLs visitors actually hit, then fix them with a redirect in one click.

Go to **Tools → Redirects & 404s**.

- **404 Log:** every missing URL, how many times it was requested, when, and the last referrer. Type where it should go, press **Redirect**, done. The entry leaves the log.
- **Redirects:** add your own, with 301, 302, 307 or 308. See how many times each one has been used.
- **Wildcards:** `/old-blog/*` → `/news/*` moves a whole section and keeps the rest of the path.
- **Export CSV** of the log, safe to open in Excel or Sheets.

## Install

Copy the `redirects-404-log` folder into `wp-content/plugins/` and activate it, or upload the plugin zip.

## Notes

- Matching ignores case, query strings and trailing slashes.
- Redirects run before WordPress does anything else, so they also work for URLs that currently exist.
- Targets can be a path on this site (`/new-page`) or any full `http(s)` URL.
- A redirect to itself is refused, so you cannot create a loop by accident.
- Privacy: no IP addresses or user agents are stored. Only the path, a hit count, dates and the referrer.
- The log keeps the 1,000 most recent URLs. Only GET and HEAD requests are logged.
- Page caching plugins may serve a cached page before redirects run; clear the cache after adding one.
- Deleting the plugin removes both database tables.

## Hooks

| Hook                      | Type   | Purpose                                                               |
| ------------------------- | ------ | --------------------------------------------------------------------- |
| `wpdt008_should_log`      | filter | Return `false` to skip logging a path, e.g. bots or known junk        |
| `wpdt008_max_log`         | filter | Change how many 404 URLs are kept (default 1000, minimum 100)         |
| `wpdt008_redirect_target` | filter | Change the final URL, or return an empty value to cancel the redirect |
