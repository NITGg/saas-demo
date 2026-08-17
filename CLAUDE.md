# Working in this repository

This repo is a full **Moodle 5.2** codebase with our academy customizations layered on top.

## Golden rule: never modify Moodle core

All customization MUST live in our own plugins and theme. **Do not edit Moodle
core files.** Core = everything that ships with Moodle, e.g. `public/lib/`,
`public/course/`, `public/user/`, `public/admin/`, `public/enrol/`,
`public/question/`, `public/grade/`, `public/message/`, the core activity
modules under `public/mod/`, and the stock themes `public/theme/boost` and
`public/theme/classic`.

Why: an untouched core means we can pull Moodle security/point releases with a
plain upgrade and never hit a merge conflict or lose a fix. Every core edit is a
liability we have to re-apply and re-test on every Moodle update.

## Where our code goes

| Need | Put it in |
|------|-----------|
| Business logic, APIs, payments, subscriptions, custom web-service functions | a plugin under `public/local/*` (e.g. `local_payments`, `local_academy`) |
| A front-page / dashboard content block | `public/blocks/nit_*` |
| Any visual / layout / template change | the custom theme `public/theme/nit` |
| Overriding a core template (e.g. a Boost mustache) | **copy it into `theme/nit/templates/...`** — the theme override wins; never edit the original under `theme/boost` |
| Adding a menu link, capability, or setting | plugin `db/*.php` (`access.php`, `settings.php`, `upgrade.php`) or an admin setting — not a core edit |

If something seems to require a core change, stop and look for the extension
point first: a **hook** (`db/hooks.php`), a **callback**, a **theme override**,
or a plugin **external function**. Moodle almost always has one.

## Known exception to clean up

`public/theme/boost/templates/core/login_panel.mustache` was edited directly
(login page left panel). This is the one core modification in the repo and
should be migrated to `theme/nit/templates/core/login_panel.mustache` so
`theme/boost` returns to stock.

## How to verify no core was touched

List everything changed on top of the Moodle base import, excluding our plugins
and theme — the result should be empty (aside from config/CI/docs):

```bash
# 09316d082 is the "first commit" = the stock Moodle import.
for h in $(git log --no-merges --pretty="%h" | grep -v 09316d082); do
  git show "$h" --pretty=format: --diff-filter=M --name-only
done | sort -u \
  | grep -vE '^public/(local|theme/nit|blocks/nit_section)/' \
  | grep -vE '\.upgradenotes/|\.github/|^\.git|docs/|README|SECURITY|robots|config\.php'
```

## Deploying to the server

- Plugin/theme code-only change (no new DB fields, no version bump):
  `git pull && docker compose exec moodle php admin/cli/purge_caches.php`
- New DB schema, capability, or a bumped `version.php`:
  `git pull && docker compose exec moodle php admin/cli/upgrade.php --non-interactive && … purge_caches.php`

## Do NOT commit these (environment-specific)

`000-default.conf`, `docker-compose.yml`, `Dockerfile`, `moodle_backup.sql`,
`moodledata.zip`, `php-moodle.ini` — they are git-ignored; keep them that way.
Note `public/config.php` is environment-specific too and ideally should not be
tracked.

## Handy tools

- `public/local/payments/cli/ws_diagnose.php --token=… [--fix] [--function=NAME]`
  — diagnose/repair web-service `accessexception` for a token.
