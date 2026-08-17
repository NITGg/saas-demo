# NIT Colour Palette API

Public, read-only JSON endpoint that returns the site's **live colour palette** —
the same tokens the web theme (`theme_nit`) is built from. Mobile / Flutter apps
call it to keep their in-app colours in sync with whatever an admin has set on the
web (Site administration → Appearance → **NIT Design System** gallery page).

- **Owner:** `theme_nit`
- **Source:** `theme/nit/colours.php` (backed by `theme_nit_colours_all()` in `theme/nit/lib.php`)
- **Auth:** none — the colours are public branding (already visible in the site CSS)
- **Method:** `GET` (also answers `OPTIONS` for CORS pre-flight)

---

## Endpoint

```
GET https://academy2026.nitg-eg.com/moodle-new/theme/nit/colours.php
```

> The path is `<moodle-wwwroot>/theme/nit/colours.php`. On this install the
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

## Response

```jsonc
{
  "generated": 1712345678,          // unix timestamp the response was built
  "colours": {                       // flat map: key -> "#rrggbb"  (the easy path)
    "primary": "#2a50c8",
    "secondary": "#626c7a",
    "accentgold": "#e8b84b",
    "accentgolddark": "#c9922a",
    "accentteal": "#00a99d",
    "navbarbg": "#0a1628",
    "navbarsurface": "#10203a",
    "navbarborder": "#1b2c48",
    "navbaraccent": "#e8b84b",
    "navbaraccenthover": "#f0c86a",
    "navbartext": "#cdd5e0",
    "navbarpanel": "#0d2149",
    "navbarpaneltext": "#8a9ab5",
    "navbarpanelborder": "#dedede",
    "background": "#ffffff",
    "surface": "#f7f8fa",
    "textprimary": "#171b22",
    "textsecondary": "#626c7a",
    "border": "#dce1e8",
    "success": "#1e7a54",
    "warning": "#9a6410",
    "error": "#b23a2e",
    "info": "#0e7c86",
    "darkprimary": "#6c9bd6",
    "darkbackground": "#0a1628",
    "darksurface": "#0f1e33",
    "darksurfacevariant": "#13293f",
    "darktextprimary": "#ffffff",
    "darktextsecondary": "#8a9ab5",
    "darkborder": "#244766"
  },
  "tokens": [                         // same data, with metadata (for a settings/debug UI)
    {
      "key": "primary",
      "group": "Brand",
      "label": "Primary",
      "value": "#2a50c8",             // live value (admin override, else default)
      "default": "#2a50c8",           // design-system default
      "iscustom": false               // true if an admin changed it from default
    }
    // ... one object per token
  ]
}
```

- **`colours`** — use this in most cases: a plain `key → hex` map.
- **`tokens`** — use when you also want the group, human label, or to show which
  colours were customised (`iscustom`).
- Every value is a `#rgb` or `#rrggbb` string.

---

## Token reference

Grouped as they appear in the web editor. `key` is what the JSON uses.

### Brand
| key | meaning |
|-----|---------|
| `primary` | main brand colour (buttons, links, active states) |
| `secondary` | muted secondary actions |
| `accentgold` | gold accent (hero badges, highlights) |
| `accentgolddark` | darker gold (gradient start) |
| `accentteal` | teal accent |

### Navbar
| key | meaning |
|-----|---------|
| `navbarbg` | top-bar background (navy) |
| `navbarsurface` | round icon-button background |
| `navbarborder` | bar borders / dividers |
| `navbaraccent` | gold wordmark & icons |
| `navbaraccenthover` | gold hover |
| `navbartext` | muted text on the bar |
| `navbarpanel` | dropdown panel background |
| `navbarpaneltext` | dropdown item text |
| `navbarpanelborder` | dropdown divider |

### Neutrals
| key | meaning |
|-----|---------|
| `background` | page background |
| `surface` | subtle fill |
| `textprimary` | body text |
| `textsecondary` | muted text |
| `border` | borders / dividers |

### Semantic
| key | meaning |
|-----|---------|
| `success` | positive / confirmation |
| `warning` | caution / pending |
| `error` | errors / destructive |
| `info` | informational |

### Dark
| key | meaning |
|-----|---------|
| `darkprimary` | primary for dark surfaces |
| `darkbackground` | dark page background |
| `darksurface` | dark card surface |
| `darksurfacevariant` | raised dark fill |
| `darktextprimary` | text on dark |
| `darktextsecondary` | muted text on dark |
| `darkborder` | border on dark |

> New tokens may be **added** over time. Treat unknown keys as optional and never
> assume a fixed count — read by key, not by index.

---

## Flutter integration

### 1. Fetch + parse

```dart
import 'dart:convert';
import 'dart:ui';
import 'package:http/http.dart' as http;

/// Fetches the live palette. Returns a map of token key -> Color.
Future<Map<String, Color>> fetchPalette(String baseUrl) async {
  final uri = Uri.parse('$baseUrl/theme/nit/colours.php');
  final res = await http.get(uri).timeout(const Duration(seconds: 10));

  if (res.statusCode != 200) {
    throw Exception('Palette fetch failed: HTTP ${res.statusCode}');
  }

  final body = json.decode(res.body) as Map<String, dynamic>;
  final colours = (body['colours'] as Map<String, dynamic>);

  return colours.map((key, value) => MapEntry(key, _hexToColor(value as String)));
}

/// "#rrggbb" or "#rgb" -> Color (opaque).
Color _hexToColor(String hex) {
  var h = hex.replaceFirst('#', '').trim();
  if (h.length == 3) {
    h = h.split('').map((c) => '$c$c').join(); // #abc -> aabbcc
  }
  return Color(int.parse('FF$h', radix: 16));
}
```

### 2. Map onto your `AppColors`

The API keys are close to the app's `AppColors`. Suggested mapping:

| `AppColors` field | API key |
|-------------------|---------|
| `primary` | `primary` |
| `secondary` | `secondary` |
| `accent` | `accentgold` |
| `background` | `background` |
| `surface` | `surface` |
| `textPrimary` | `textprimary` |
| `textSecondary` | `textsecondary` |
| `border` | `border` |
| `success` | `success` |
| `warning` | `warning` |
| `error` | `error` |
| `darkPrimary` | `darkprimary` |
| `darkBackground` | `darkbackground` |
| `darkSurface` | `darksurface` |
| `darkSurfaceVariant` | `darksurfacevariant` |
| `darkTextPrimary` | `darktextprimary` |
| `darkTextSecondary` | `darktextsecondary` |
| `darkBorder` | `darkborder` |

```dart
class RemotePalette {
  final Map<String, Color> _c;
  RemotePalette(this._c);

  // Fall back to the compiled-in default if a key is ever missing.
  Color get(String key, Color fallback) => _c[key] ?? fallback;

  Color get primary        => get('primary',        AppColors.primary);
  Color get accent         => get('accentgold',     AppColors.accent);
  Color get background     => get('background',      AppColors.background);
  Color get surface        => get('surface',        AppColors.surface);
  Color get textPrimary    => get('textprimary',    AppColors.textPrimary);
  Color get darkBackground => get('darkbackground', AppColors.darkBackground);
  // ...map the rest the same way.
}
```

### 3. Recommended usage pattern

1. **Ship defaults compiled in.** Keep the existing `AppColors` constants so the
   app renders correctly offline / before the first fetch.
2. **Fetch once on launch**, then apply. Rebuild your `ThemeData` from the
   returned colours.
3. **Cache locally** (e.g. `shared_preferences`) and reuse on next launch; refresh
   in the background. The endpoint already sets a 5-minute HTTP cache.
4. **Fail soft.** On any network/parse error, keep the last good (or default)
   palette — never block the UI on this call.

---

## Notes & guarantees

- **Live values.** As soon as an admin saves a colour on the gallery page, the
  endpoint returns the new value (subject to the 5-minute cache).
- **Always complete.** Every token is always present — if not customised, its
  `default` is returned as the `value`.
- **Additive changes only.** New tokens may appear; existing keys won't be
  renamed without notice. Read defensively (by key, with a fallback).
- **No pagination, no params.** A single GET returns the whole palette.

## Quick test

```bash
curl -s https://academy2026.nitg-eg.com/moodle-new/theme/nit/colours.php | jq .colours.primary
```

Should print e.g. `"#2a50c8"`.
