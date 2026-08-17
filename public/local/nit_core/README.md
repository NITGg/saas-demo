# NIT Core SDK — `local_nit_core`

The shared foundation every NIT plugin depends on. It provides dependency
injection, typed configuration, caching, events, entities, tasks, and audit
scaffolding as **thin adapters over Moodle's own subsystems** (the Facade
Principle) — never reimplementations.

> **Status:** Phase 1 (M0 + M1). SDK API is **v0.x / unstable**. See
> [`CHANGELOG.md`](CHANGELOG.md) and the frozen Reference Architecture v1.0.

## What this milestone delivers

Plumbing only. **Branding (M5), Feature Flags (M6), and the Package Engine (M7)
are deliberately out of scope** and land in later milestones inside this same
plugin.

## Install

This plugin lives at `public/local/nit_core/` in a Moodle 5.x site. Visit
**Site administration → Notifications** to complete installation, or run:

```bash
php public/admin/cli/upgrade.php
```

## Public API (stable surface)

Everything under `classes/api/`, `classes/contract/`, `classes/base/`,
`classes/traits/` is `@api`. Everything else is `@internal`.

```php
use local_nit_core\api\config;
use local_nit_core\api\cache;
use local_nit_core\api\services;
use local_nit_core\contract\config_manager;

// Typed config (scoped to your own plugin).
$enabled = config::for_plugin('local_yourplugin')->get_bool('enabled', false);

// Named cache.
cache::set('yourarea', 'key', $value);
$value = cache::get('yourarea', 'key');

// Resolve a service (and override it in tests).
$cfg = services::get(config_manager::class);
```

### Defining an audited entity

```php
use local_nit_core\base\entity;

class thing extends entity {
    const TABLE = 'local_yourplugin_thing';
    protected static function define_properties() {
        return ['name' => ['type' => PARAM_TEXT]];
    }
}
// create()/update()/delete() automatically emit NIT audit events and
// populate timecreated/timemodified/usermodified (via core\persistent).
```

## Running the tests

From the Moodle root, with PHPUnit initialised:

```bash
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_nit_core_testsuite
```

or run the directory directly:

```bash
vendor/bin/phpunit public/local/nit_core/tests
```

## Repository note

For Phase 1 the M0 engineering files (`.github/`, `phpcs.xml`, `phpstan.neon`,
`bundles/`, `docs/`) live inside this plugin so everything is in one runnable
place. When the suite is extracted into the `nit-lms/` monorepo, these move to
the repository root per Reference Architecture §19.

## Documents
- Reference Architecture v1.0 (frozen)
- Phase 1 Engineering Specification (steps 1–6)
- [ADR-0001 — DI seam](docs/adr/ADR-0001-di-seam.md)
