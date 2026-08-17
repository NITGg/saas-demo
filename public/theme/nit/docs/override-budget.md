# NIT override budget register

Every overridden **core Moodle** template is a manual merge on each Moodle
upgrade. This register makes each override a governed, justified decision. The
drift guard (`theme_nit/tests/override_drift_test.php`) enforces it in CI.

## Priority tiers (Reference Architecture §11)

| Tier | Screens | Approach |
|---|---|---|
| **P1 — Full** | Login · Dashboard · Catalog · Course · Checkout · Profile | Template (and, only if needed, renderer) override |
| **P2 — Style** | Quiz · Assignment · Forum · Gradebook · Calendar | SCSS / tokens only — no override |
| **P3 — None** | Admin · Backup · Restore · Teacher admin | Inherit Boost untouched |

## Current core-template overrides

**None.** M4 builds the machinery only. The reference welcome panel overrides a
**NIT-owned** template (`local_nit_core/output/welcome_panel`), which carries no
upgrade cost and is deliberately *not* counted here.

| Core template | Tier | Justification | Reviewed against Moodle |
|---|---|---|---|
| _(none yet)_ | — | — | — |

The drift guard (`test_core_template_overrides_are_budgeted`) only counts
templates under `theme/nit/templates/core/` that shadow `lib/templates/*`. The
items below are NOT core overrides and do not consume the P1 budget, but they
are Boost-derived and carry the same re-diff cost, so they are tracked here.

## Boost-derived (NIT-owned) layout overrides

| File | Derived from | Tier | Justification | Reviewed against Moodle |
|---|---|---|---|---|
| `layout/frontpage.php` | `theme_boost/layout/drawers.php` | P1 (Catalog/landing) | Adds 4 full-width Site-home block regions; Boost's frontpage exposes only `side-pre`. | 5.02 |
| `templates/frontpage.mustache` | `theme_boost/templates/drawers.mustache` | P1 (Catalog/landing) | Renders the 4 full-width regions around `#page-content`. NIT additions fenced with `NIT:` comments. | 5.02 |
| `templates/theme_boost/navbar.mustache` | `theme_boost/templates/navbar.mustache` | P1 (site-wide brand) | Rebuilds the legacy edumy header on Boost primitives: logo + site name together, language menu on the left, primary nav collapsed into a gear dropdown. Visual treatment in `scss/components/_navbar.scss`. NIT changes documented in the template's `@template` block. | 5.02 |

## Rules

1. Adding a row here is a reviewed decision — no undocumented core overrides.
2. On each Moodle upgrade, re-diff every listed template and update the last
   column.
3. Prefer template overrides over renderer overrides; prefer SCSS/tokens over
   both.
