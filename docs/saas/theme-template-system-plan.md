# Theme Template System — plan & status

_Last updated: 2026-09-20 · branch `main` · image `v2026.09.68`_

> **North star.** Every academy picks one of ten theme templates (T1–T10). That
> template drives the **whole** storefront and app — homepage, catalog, course,
> dashboard, auth, checkout, profile, player, edit — as one coherent design. nit2
> requests the template at creation and sends brand (logo/colours), images and
> text; everything stays editable afterward in both Moodle and the nit2 build page.

This doc is the shared source of truth for where the theme-template work is and
what's left. A visual version lives as a Claude artifact; this markdown is the
in-repo copy the team edits alongside the code.

---

## How it fits together — three layers

1. **Template design tokens (look).** Each template T1–T10 is a token set —
   `--t-bg`, `--t-ink`, `--t-surface`, `--t-border`, font, radius. The *structure*
   is the template's own; the *accent* always comes from the academy brand
   (`--nit-brand-*`). One active template's tokens are injected site-wide.
2. **Surfaces (every screen).** Homepage sections **and** app screens all style
   through those tokens. Switch the template → every surface reskins together.
   Data & logic stay Moodle's own.
3. **Content pipeline.** nit2 sends logo, brand colours, hero/about/gallery images
   and text at creation; editable later in both Moodle admin and the nit2 build
   page via `data-nit-edit` hooks.

---

## The gap we're closing

**All nine T1 app screens are built with real data and real function — but their
look is hardcoded to T1's light palette.** A T4 "Dark Premium" academy today would
get a T4 homepage and a T1 (light) catalog. **Phase 2 — the per-template token
layer — is the fix**, and it turns T2–T10 into *token sets*, not ten rebuilds.

---

## Coverage — surface by surface

| Surface | Design + function (T1) | Template-aware | T2–T10 |
|---|---|---|---|
| Homepage (`site-index`) | ✅ Done | ✅ token-driven | 🔶 sections exist · chrome+hooks pending |
| Login / Signup (core overrides) | ✅ Done | ⬜ next | ⬜ via token set |
| Catalog (`local/nit_category`) | ✅ Done · real filter/sort | ⬜ next | ⬜ via token set |
| Dashboard (`/my`) | ✅ Done · real progress | ⬜ next | ⬜ via token set |
| Course landing (`format_topics`) | ✅ Done · prospects only | ⬜ next | ⬜ via token set |
| Checkout (Kashier modal) | ✅ Done · coupon/offer live | ⬜ next | ⬜ via token set |
| Profile (`user/edit`) | ✅ Done · real form | ⬜ next | ⬜ via token set |
| Player (`mod_vimeo` view) | ✅ Done · real embed + nav | ⬜ next | ⬜ via token set |
| Edit page (front-page edit mode) | ✅ Done · real toolbar | ⬜ next | ⬜ via token set |
| Selector + provisioning (nit2 → Moodle) | ✅ Done | ✅ template flows e2e | 🔶 per-template content schema |

---

## Phases

### Phase 0 — Foundations ✅ Done
- 10 homepage templates as section seed-HTML (`blocks/templates/tN`).
- Template selector — admin page + CLI (`homepage.php`, `apply_homepage_template.php`).
- nit2 provisioning sends the template (`BuildProductForm` → `create.sh`).
- Content pipeline for T1 (`data-nit-edit`, Moodle admin + nit2 form).
- Hybrid-palette rule locked: structure = template, accent = brand.
- Brand images ride provisioning (logo/hero/about/gallery).

### Phase 1 — T1 app screens ✅ Done (all 9)
- **Login + Signup** — split screen, real mform.
- **Catalog** — live category filter, working `?sort=`.
- **Dashboard** — real enrolments + completion progress.
- **Course landing** — prospects only; pricing + Kashier intact.
- **Checkout** — `checkout_modal.js` → T1 order-summary; `preview_discount` +
  Kashier `proceed()` byte-for-byte; card data stays on Kashier's hosted page.
- **Profile** — `scss/components/_profile.scss` restyles the real `/user/edit.php`
  form; no core edit, no shared-layout override.
- **Player** — `mod/vimeo/view.php` rebuilt: real Vimeo embed + real prev/next from
  `modinfo` + curriculum sidebar with completion; falls back to bare embed on error.
- **Edit page** — front-page edit toolbar via `before_standard_top_of_body_html`
  hook; real View page / Done editing; no fake publish (front-page editing is live).

### Phase 2 — Template token layer 🔶 Next up (the multiplier)
- Extract T1's hardcoded structure into a single token set (one source of truth).
- Make every app screen read the **active template's** tokens (drop hardcoded T1).
- Define token sets for T2–T10 (bg/ink/surface/border/font per template).
- Inject the active token set site-wide from config `theme_nit/homepage_template`.

### Phase 3 — T2–T10 parity across every surface
- Homepage chrome + content hooks for T2–T10 (nav · app-band · footer).
- Verify each template across all app screens.
- RTL / Arabic pass per template.

### Phase 4 — Content pipeline completeness (parallel)
- Per-template content schema in nit2 (extra fields; skip Moodle-owned data).
- Staging verification of send → render (can't PHP-lint locally).
- Edit parity confirmed in both surfaces.

### Phase 5 — Ship
- saas-demo image tags shipping (currently `v2026.09.68`).
- nit2 pushed to origin; **nit2 → prod remote is on hold** (release when ready).
- Deploy + upgrade: staging → prod.

---

## Guardrails we never cross

1. **Never modify Moodle core.** Theme overrides and plugins only — clean upgrades stay possible.
2. **Pricing, coupons, offers and Kashier checkout stay byte-for-byte.** We restyle the shell, never the logic.
3. **Functional, never design-only.** No fabricated durations or stats — real Moodle data or nothing.
4. **Inner pages render Moodle's own data.** nit2 only provisions the template, brand, images and text.

---

## Deploy

- Code-only theme/plugin change: `git pull && …/purge_caches.php`.
- New DB field, capability, or bumped `version.php` (e.g. the Checkout string):
  `git pull && …/upgrade.php --non-interactive && …/purge_caches.php`.
- Pushing a `v*` tag on `main` builds the GHCR `saas-moodle:<tag>` image.
