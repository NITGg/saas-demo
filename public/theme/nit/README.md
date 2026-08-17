# NIT Theme — `theme_nit`

The presentation shell for the NIT LMS Framework: a thin **Boost child** theme.
This is the **M2 Foundation** release — the theme skeleton and its asset
pipeline. It deliberately looks like Boost; the visible NIT design arrives next.

> **Status:** Phase 2 (M2). Foundation only. See Reference Architecture v1.0
> and the Phase 2 Engineering Specification.

## What this milestone delivers

- Boost child (`parents = ['boost']`) that installs, is selectable, and renders
  every Boost layout (inherited — no overrides).
- SCSS pipeline layered on Boost via callbacks (`lib.php`), with an **empty
  three-tier token seam** (`scss/_tokens.scss`) ready for M3.
- AMD/JS pipeline (`amd/src` → `amd/build`), **Vanilla / no jQuery**.
- Self-hosted font structure (no external CDN), RTL-safe, Privacy null-provider.

## What is deliberately NOT here

Design tokens & components (M3), the view-model/renderer overrides (M4), and
branding presets (M5). M2 changes nothing visual vs Boost on purpose.

## Install

Copy to `public/theme/nit/`, then **Site administration → Notifications**, or:

```bash
php public/admin/cli/upgrade.php
```

Select it under **Site administration → Appearance → Themes → Theme selector**.

## Building assets

The JS build needs Node ≥ 22.11 (Moodle 5.x uses grunt + esbuild):

```bash
npx grunt amd --root=theme/nit
```

`amd/build/init.min.js` is committed per Moodle convention; regenerate it after
editing `amd/src/`.

## Structure notes

- `lib.php` extends Boost by calling `theme_boost_get_main_scss_content()` — NIT
  never forks Boost.
- Layouts are inherited from Boost; the first template overrides land at M4 under
  the override budget.
- No `pix/screenshot.png` yet (added with the M3 visual identity); the theme
  selector shows a placeholder until then.
