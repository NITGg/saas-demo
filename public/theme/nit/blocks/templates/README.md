# NIT homepage templates

Ten selectable homepage "looks" for a generated academy. Each template is a **set
of section seed-HTML files** that get pasted into `block_nit_section` instances on
the Site home page (same mechanism as the single legacy seed set in
`theme/nit/blocks/*.html`). The front-page engine in
`theme/nit/templates/frontpage.mustache` and the data helpers in `theme/nit/lib.php`
are template-agnostic and are **not** forked per template.

Source designs: `docs/designs/Academy Homepage Templates/Academy Homepage Templates.dc.html`
(Claude Design). T1…T10 = Modern Minimal, Bold Gradient, Academic Classic, Dark
Premium, Warm Editorial, Soft Glass, Corporate Trust, Playful Rounded, Elegant
Mono, Vibrant Duotone.

```
templates/
  t1/  hero.html categories.html courses.html about.html
       subscriptions.html coupons.html gallery.html contact.html footer.html
  t2/ … t10/  (same 8–9 filenames)
```

## Non-negotiable contract (why the seeds still work as live blocks)

The engine finds content by `data-*` hooks, regardless of surrounding markup. Keep
these EXACT hooks/tokens or the section goes dead:

| Section | Container hook | Cloned template marker | Tokens the engine substitutes |
|---|---|---|---|
| hero / about | — | — | `data-nit-stat="courses\|categories\|topcategories\|subcategories\|students"` (count-up) |
| categories | `data-nit-categories` `data-limit` | `data-nit-category-card` (hidden **wrapper**; its **inner** node is cloned into the container) | `{{url}} {{name}} {{coursecount}} {{id}}` + `data-nit-category-image` (bg set from data) |
| courses | `data-nit-courses` `data-limit` (opt: `data-cta-label` `data-free-label` `data-continue-label` `data-enrolled-label`) | `data-nit-course-card` (hidden wrapper; inner cloned) | `{{url}} {{fullname}} {{teacher}} {{summary}} {{pricebadge}} {{cta}} {{price}}` + `data-nit-course-image` |
| subscriptions | `data-nit-subs` `data-endpoint` `data-commerce` `data-base` + `data-nit-subs-grid` + `data-nit-subs-msg` + confirm modal (`data-nit-modal`, all `data-nit-m-*`) | `data-nit-subs-card` (hidden) → **first child = the styled card** (script sets `firstElementChild.style.*`) | `{{name}} {{price}} {{days}} {{courses}} {{description}}`; hooks `data-nit-price-val` `data-nit-offer-final` `data-nit-orig-price` `data-nit-offer-label` `data-nit-features` `data-nit-desc-item` `data-nit-courses-item` `data-nit-courses-text` `data-nit-b2b` `data-nit-subscribed-badge` `data-nit-buy` |
| coupons | `data-nit-coupons` `data-endpoint` `data-base` + `data-nit-coupons-scroller` + `data-nit-coupons-msg` + arrows `data-nit-coupons-scroll` | `data-nit-coupons-card` (its **outer style attr** is reused by the loader — keep `display: none;` + the card width in it) | `{{value}} {{valuelabel}} {{code}} {{scope}} {{usage}} {{max}} {{dates}}`; hooks `data-nit-value` `data-nit-valuelabel` `data-nit-copy`(`data-code`) `data-nit-scope` `data-nit-usage` `data-nit-max` `data-nit-dates` |
| footer | `data-nit-section="footer"` | — | theme moves it to `fullwidth-bottom` and slots the download band above it |

**Subscriptions & coupons `<script>` blocks are copied verbatim** from the legacy
seeds — they are coupled to the DOM (`firstElementChild`, the hooks above, the
modal). Reskinning = change only style attributes on the wrapper / header / card;
never touch the script logic or the hooks.

## Rules every template file follows

1. **Hybrid palette.** Each section root sets local `--t-*` vars. Structure
   (bg/surface/ink/muted/border) = the template's own hex. Accent/CTA/highlights =
   the brand: `--t-accent: var(--nit-brand-primary)`,
   `--t-accent-2: var(--nit-brand-accent)`, `--t-on-accent: var(--nit-brand-on-primary,#fff)`.
   Everything inside uses `var(--t-*)`. Result: the academy's brand colour flows
   into the accent while the template keeps its signature look. (Verified: swapping
   `--nit-brand-primary` re-tints CTAs/badges only.)
2. **Bilingual** via `{mlang en}…{mlang}{mlang ar}…{mlang}` — including inside the
   `<script>` string literals (the site's multilang filter processes them). Static
   text in a hidden clone-template is filtered server-side *before* the JS clones
   it, so bilingual labels work there; only truly dynamic values use `{{tokens}}`.
3. **Responsive without media queries**: `clamp()` for type/spacing,
   `grid-template-columns: repeat(auto-fit, minmax(Npx, 1fr))`, `flex-wrap`. (Block
   HTML can't rely on `<style>` media queries surviving the editor.)
4. **No fake data.** The course data shape is `{id, fullname, summary, url, image,
   price, is_free, is_enrolled, teacher}` — there is **no** rating, lesson count,
   per-course category or strikethrough price. Drop those design bits; don't
   fabricate them.
5. **No duplicate chrome.** Drop the design's fake top-nav (the theme renders the
   real navbar) and the design's app-band (the theme auto-inserts the download band
   before the footer).
6. **Signature font** is injected once from `hero.html` via a guarded
   `<link id="nit-tpl-font-tN">`. The shared hover-fx `<script>` (id
   `nit-hoverfx-style`, idempotent) is included in every block so hover works
   regardless of block order.

## Fonts per template (from the designs)

T1 Manrope · T2 Plus Jakarta Sans · T3 Almarai · T4 Space Grotesk · T5 Alexandria ·
T6 Public Sans · T7 Baloo Bhaijaan 2 · T8 Archivo · T9 Syne · T10 (Changa/Reem Kufi).
Pair every Latin font with an Arabic-capable fallback (e.g. `'IBM Plex Sans Arabic'`).

## Local QA harness

`scratchpad/build-preview.mjs <tpl> <en|ar> [primaryHex] [accentHex]` inlines a set,
mocks `window.NIT_*` + the subscriptions/coupons REST + the front-page engine, and
strips `{mlang}` to one language. Serve the output over HTTP (not `file://`) to run
the scripts. Pass a primary hex to prove the hybrid palette flows.

## Still to wire (later phases, not in these files)

- A `homepage_template` theme config key + gallery picker.
- A server-side "apply template" action that rewrites the Site-home `nit_section`
  blocks from the chosen set (no core edits).
- nit2 provisioning: seed the chosen template's blocks at academy creation.
