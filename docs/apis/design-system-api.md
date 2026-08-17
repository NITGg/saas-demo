# NIT Design System API

Public, read-only JSON endpoint that returns the site's **live design system** —
the same four things an admin manages on the web gallery page
(Site administration → Appearance → **NIT Design System**, i.e.
`theme/nit/gallery.php`):

1. **Brand Colors** — the semantic colour palette, in 3 swappable groups
2. **Category styles** — which colour group each course category uses
3. **Fonts** — the per-language (Arabic / English) font files
4. **Components** — the shared UI components (buttons, alerts) and their variants

Mobile / Flutter apps call it to keep their in-app branding in sync with whatever
an admin has set on the web.

- **Owner:** `theme_nit`
- **Source:** `theme/nit/design_system.php` (backed by `theme_nit_design_system_export()` in `theme/nit/lib.php`)
- **Auth:** none — this is public branding (already visible in the site's CSS / DOM)
- **Method:** `GET` (also answers `OPTIONS` for CORS pre-flight)

> **Related:** [`colour-palette-api.md`](colour-palette-api.md) is the older, flat
> colour endpoint. This one is broader (all four gallery tabs) and uses the newer
> **semantic** brand palette (groups × roles). If you only need raw hex colours,
> either works; for full branding use this one.

---

## Endpoint

```
GET https://academy2026.nitg-eg.com/moodle-new/theme/nit/design_system.php
```

> The path is `<moodle-wwwroot>/theme/nit/design_system.php`. On this install the
> wwwroot is `https://academy2026.nitg-eg.com/moodle-new`. Don't hard-code the
> host in the app — read it from your existing site/base-URL config.

### Headers returned

| Header | Value | Why |
|--------|-------|-----|
| `Content-Type` | `application/json; charset=utf-8` | JSON body |
| `Access-Control-Allow-Origin` | `*` | any origin (web/mobile) may fetch |
| `Access-Control-Allow-Methods` | `GET, OPTIONS` | allowed verbs |
| `Cache-Control` | `public, max-age=300` | cache for 5 minutes |

No API key, cookie, or token is needed. Do **not** send Moodle credentials to it.

---

## Response at a glance

```jsonc
{
  "generated": 1712345678,             // unix timestamp the response was built
  "site": {
    "name": "NIT Academy",
    "url":  "https://academy2026.nitg-eg.com/moodle-new"
  },
  "brandcolors":    { /* Tab 1 — see below */ },
  "categorystyles": { /* Tab 2 — see below */ },
  "fonts":          [ /* Tab 3 — see below */ ],
  "components":     [ /* Tab 4 — see below */ ]
}
```

Each of the four keys maps 1:1 to a tab on the gallery page. They're independent —
use only the ones your screen needs.

---

## Tab 1 — `brandcolors`

The **semantic** brand palette. Instead of raw colour names, every colour is a
**role** (Primary, Surface, Text primary, …). The same set of roles exists in
**3 groups** (Group 1/2/3) — think of a group as a full, swappable colour theme.

- **Group 1** is the site-wide default.
- **Groups 2 & 3** are alternate themes; on the web a component opts into one by
  adding a wrapper CSS class (`nit-brand-2` / `nit-brand-3`). In the app you just
  pick which group to render a given screen/category with (see Tab 2).

```jsonc
"brandcolors": {
  "roles": ["primary", "secondary", "accent", "accenttext", "background",
            "surface", "textprimary", "textsecondary", "borderprimary",
            "bordersecondary", "hoverbackground", "hovertext", "error",
            "success", "warning", "info"],   // role keys shared by every group
  "groups": [
    {
      "key": "g1",
      "name": "Group 1",
      "isdefault": true,
      "class": "",                            // web wrapper class ("" for default)
      "roles": [
        {
          "key": "g1_primary",                // unique token key (group + role)
          "role": "primary",                  // role key (same across groups)
          "label": "Primary",                 // human label
          "cssvar": "--nit-brand-primary",    // the CSS custom property on web
          "value": "#e5322d",                 // live value (admin override else default)
          "default": "#e5322d",               // design-system default
          "iscustom": false,                  // true if an admin changed it
          "usage": ["background main button", // where this colour is meant to be used
                    "checked toggles", "progress fill",
                    "notification dots", "navbar background"]
        }
        // … one object per role (16 roles)
      ]
    },
    { "key": "g2", "name": "Group 2", "class": "nit-brand-2", "roles": [ … ] },
    { "key": "g3", "name": "Group 3", "class": "nit-brand-3", "roles": [ … ] }
  ]
}
```

### The 16 roles

| role | label | typical usage |
|------|-------|---------------|
| `primary` | Primary | main button bg, active toggles, progress fill, navbar bg |
| `secondary` | Secondary | secondary button background |
| `accent` | Accent | accent fills |
| `accenttext` | Accent Text | link text, important words, underlines |
| `background` | Background | page background |
| `surface` | Surface | cards, dropdowns, side menu, inputs, tables, sections |
| `textprimary` | Text primary | body text, button text, input text, navbar text |
| `textsecondary` | Text secondary | secondary text, placeholders |
| `borderprimary` | Border primary | main border colour |
| `bordersecondary` | Border secondary | secondary border colour |
| `hoverbackground` | Hover Background | hover background |
| `hovertext` | Hover Text | hover text |
| `error` | Error | errors, destructive actions, invalid fields |
| `success` | Success | success, enrolled / active / paid, positive states |
| `warning` | Warning | warnings, caution, pending / expiring |
| `info` | Info | neutral notices, tips, hints |

> Read colours by **role key**, not by array index. Roles may be **added** over
> time; treat unknown ones as optional.

---

## Tab 2 — `categorystyles`

Which brand **group** each **main (top-level) course category** uses. This is how
you decide, per category, whether to paint its screens with Group 1, 2, or 3.

```jsonc
"categorystyles": {
  "groups": [                                 // the group vocabulary
    { "key": "g1", "name": "Group 1" },
    { "key": "g2", "name": "Group 2" },
    { "key": "g3", "name": "Group 3" }
  ],
  "categories": [
    {
      "id": 3,                                // Moodle category id
      "name": "Programming",
      "group": "g2",                          // which brand group it uses
      "groupname": "Group 2",
      "class": "nit-brand-2",                 // web wrapper class for that group
      "isdefault": false                      // true when group == g1 (default)
    }
    // … one object per visible main category
  ]
}
```

**How to use it:** when you render a category (or anything inside it), look up its
`id` here to get its `group`, then paint that screen using
`brandcolors.groups[<group>]`. Categories not listed, or with `group: "g1"`, use
the default Group 1.

> **Visibility-aware.** Because the endpoint is anonymous, `categories` contains
> only the categories a **guest** can see. Categories that require login/enrolment
> to view are omitted. Any category you can't find here → fall back to Group 1.

> Only **main** categories carry a style. Subcategories inherit their top-level
> parent's group — resolve a subcategory to its top-level ancestor, then look that
> ancestor up here.

---

## Tab 3 — `fonts`

The custom font per site language. English is applied when the site runs in
English, Arabic when it runs in Arabic. Each slot gives you the family name, the
text direction, and a **downloadable URL** to the actual `.ttf`/`.otf` file.

```jsonc
"fonts": [
  {
    "lang": "en",                             // "en" | "ar"
    "label": "English font",
    "family": "NIT Site Font EN",             // CSS family name used on web
    "rtl": false,                             // true for Arabic (right-to-left)
    "fallback": "-apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, …",
    "hasfont": true,                          // false → no font uploaded; use fallback
    "filename": "font-en.ttf",
    "url": "https://…/pluginfile.php/1/theme_nit/fontfileen/0/font-en.ttf"
  },
  {
    "lang": "ar",
    "label": "Arabic font",
    "family": "NIT Site Font AR",
    "rtl": true,
    "fallback": "\"Segoe UI\", Tahoma, \"Traditional Arabic\", …",
    "hasfont": false,                         // nothing uploaded → fall back
    "filename": "",
    "url": ""
  }
]
```

**How to use it:** for the active language, if `hasfont` is `true`, download the
file at `url`, register it as a font, and use it; otherwise fall back to a bundled
system font. The `url` is self-hosted on the Moodle server (never a third party).

---

## Tab 4 — `components`

An inventory of the shared UI components shown on the gallery's Components tab,
with their named variants and the web CSS classes that render them. Use this as a
reference for which button/alert styles the design system defines so your native
widgets match.

```jsonc
"components": [
  {
    "name": "Buttons",
    "variants": [
      { "label": "Primary",   "class": "btn btn-primary" },
      { "label": "Secondary", "class": "btn btn-secondary" },
      { "label": "Success",   "class": "btn btn-success" },
      { "label": "Warning",   "class": "btn btn-warning" },
      { "label": "Danger",    "class": "btn btn-danger" },
      { "label": "Outline",   "class": "btn btn-outline-primary" },
      { "label": "Disabled",  "class": "btn btn-primary", "disabled": true }
    ]
  },
  {
    "name": "Alerts",
    "variants": [
      { "label": "Primary", "class": "alert alert-primary" },
      { "label": "Success", "class": "alert alert-success" },
      { "label": "Warning", "class": "alert alert-warning" },
      { "label": "Danger",  "class": "alert alert-danger" }
    ]
  }
]
```

The `class` values are the web (Bootstrap-based) classes; in the app map each
variant to your equivalent widget style, coloured from the matching brand role
(e.g. a *Danger* button uses the `error` role, *Success* uses `success`).

---

## Flutter integration

### 1. Fetch + parse

```dart
import 'dart:convert';
import 'dart:ui';
import 'package:http/http.dart' as http;

class DesignSystem {
  final Map<String, Map<String, Color>> groups;   // groupKey -> (role -> Color)
  final Map<int, String> categoryGroup;           // categoryId -> groupKey
  final Map<String, FontSlot> fonts;              // lang -> FontSlot
  DesignSystem(this.groups, this.categoryGroup, this.fonts);
}

class FontSlot {
  final String family;
  final bool rtl;
  final bool hasFont;
  final String url;
  FontSlot(this.family, this.rtl, this.hasFont, this.url);
}

Future<DesignSystem> fetchDesignSystem(String baseUrl) async {
  final uri = Uri.parse('$baseUrl/theme/nit/design_system.php');
  final res = await http.get(uri).timeout(const Duration(seconds: 10));
  if (res.statusCode != 200) {
    throw Exception('Design system fetch failed: HTTP ${res.statusCode}');
  }
  final body = json.decode(res.body) as Map<String, dynamic>;

  // Brand colours: build groupKey -> (role -> Color).
  final groups = <String, Map<String, Color>>{};
  for (final g in (body['brandcolors']['groups'] as List)) {
    final roles = <String, Color>{};
    for (final r in (g['roles'] as List)) {
      roles[r['role'] as String] = _hexToColor(r['value'] as String);
    }
    groups[g['key'] as String] = roles;
  }

  // Category -> group.
  final categoryGroup = <int, String>{};
  for (final c in (body['categorystyles']['categories'] as List)) {
    categoryGroup[c['id'] as int] = c['group'] as String;
  }

  // Fonts by language.
  final fonts = <String, FontSlot>{};
  for (final f in (body['fonts'] as List)) {
    fonts[f['lang'] as String] = FontSlot(
      f['family'] as String, f['rtl'] as bool,
      f['hasfont'] as bool, f['url'] as String,
    );
  }

  return DesignSystem(groups, categoryGroup, fonts);
}

/// "#rrggbb" or "#rgb" -> Color (opaque).
Color _hexToColor(String hex) {
  var h = hex.replaceFirst('#', '').trim();
  if (h.length == 3) h = h.split('').map((c) => '$c$c').join();
  return Color(int.parse('FF$h', radix: 16));
}
```

### 2. Paint a screen with the right group

```dart
/// Resolve a role colour for a given category (falls back to Group 1).
Color colorFor(DesignSystem ds, int? categoryId, String role, Color fallback) {
  final groupKey = (categoryId != null ? ds.categoryGroup[categoryId] : null) ?? 'g1';
  return ds.groups[groupKey]?[role] ?? ds.groups['g1']?[role] ?? fallback;
}

// e.g. primary button colour on a Programming-category screen:
final primary = colorFor(ds, programmingCategoryId, 'primary', AppColors.primary);
```

### 3. Load a language font

```dart
import 'package:flutter/services.dart';

Future<void> loadFont(FontSlot slot) async {
  if (!slot.hasFont) return;                       // keep bundled fallback
  final bytes = await http.readBytes(Uri.parse(slot.url));
  final loader = FontLoader(slot.family)..addFont(Future.value(bytes.buffer.asByteData()));
  await loader.load();
  // Then set fontFamily: slot.family (and textDirection: rtl ? rtl : ltr) in your theme.
}
```

### 4. Recommended usage pattern

1. **Ship defaults compiled in.** Keep your existing `AppColors` / bundled fonts so
   the app renders correctly offline and before the first fetch.
2. **Fetch once on launch**, then apply. Rebuild `ThemeData` from Group 1 (the
   default), and rebuild per-screen when a category maps to Group 2/3.
3. **Cache locally** (e.g. `shared_preferences` for the JSON, file cache for the
   font files) and reuse on next launch; refresh in the background. The endpoint
   already sets a 5-minute HTTP cache.
4. **Fail soft.** On any network/parse error, keep the last good (or default)
   design system — never block the UI on this call. Always read by key with a
   fallback.

---

## Notes & guarantees

- **Live values.** As soon as an admin saves on the gallery page, the endpoint
  returns the new value (subject to the 5-minute cache).
- **Always complete.** Every group always contains all 16 roles; if a role wasn't
  customised, its `default` is returned as the `value`.
- **Additive changes only.** New roles / groups / components may appear; existing
  keys won't be renamed without notice. Read defensively (by key, with a fallback).
- **Anonymous & visibility-aware.** `categorystyles.categories` lists only
  guest-visible categories. Treat a missing category as Group 1.
- **Self-hosted fonts.** Font `url`s always point at the Moodle server's
  `pluginfile.php`, never a third party.
- **No pagination, no params.** A single GET returns the whole design system.

## Quick test

```bash
curl -s https://academy2026.nitg-eg.com/moodle-new/theme/nit/design_system.php | jq '.brandcolors.groups[0].roles[0]'
```

Should print the Group 1 **Primary** role, e.g.:

```json
{ "key": "g1_primary", "role": "primary", "label": "Primary",
  "cssvar": "--nit-brand-primary", "value": "#e5322d",
  "default": "#e5322d", "iscustom": false, "usage": [ … ] }
```
