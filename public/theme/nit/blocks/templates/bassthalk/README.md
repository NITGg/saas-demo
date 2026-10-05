# Bassthalk homepage template

The bassthalk.com home page, rebuilt as **HTML blocks** ("NIT Section" blocks, mode
"Raw HTML") on the Site home. Each `.html` file here is the full content of one block —
this folder is the source of truth: change the file, then paste it into the block again
(or re-apply the template, see below).

| File | Section | Data |
|---|---|---|
| `hero.html` | "منصة متكاملة بها كل ما / يحتاجه الطالب ليتفوق" + "ابدأ رحلتك" | static; the button goes to `/local/academy/start.php` (visitor → registration, student → last activity) |
| `teachers.html` | "المدرسين عندنا / مش زي أي مدرسين!" | the script loads `…/homedata.php?section=teachers`: users with a teacher role in a visible course + the year (= the course category) and Study system / Division of each course; filters (year, division grouped by system) work in the page; a logged-in student starts on their own year / division; the line under the name = the teacher's profile field "لقب المدرس"; the first teacher is the wide card |
| `how.html` | "إزاي بسطتهالك بتشتغل؟" (5 cards) | static — copy / delete a card to add / remove one (see the comment at the top of the file) |
| `selected.html` | "كورسات مختارة / مخصوص ليك" (subject cards) | the script loads `…/homedata.php?section=subjects`: one card per year (course category) + **Subject** (course settings → group "custom fields" → المادة) of the visible courses, with its teacher and course counts; the card opens `/local/academy/subject.php` (the subject page); every card is 332px wide |
| `lessons.html` | "كورسات مختارة / مخصوص ليك" + "المحاضرات المقترحة" (slider with dots) | the script loads `…/homedata.php?section=lessons`: the newest visible courses — for a logged-in student, those of their year (and division); price from the fee enrolment / payments plugin ("مجاني" when free); "الكل" → the course catalogue |

Pictures are in `theme/nit/pix/` and referenced as `/theme/nit/pix/<file>`.

## Putting the sections on the home page

**Option A — apply the template (creates / updates all five blocks):**
Site administration → Appearance → **Homepage templates** → **Bassthalk** → Apply.
Re-applying replaces the blocks' HTML with these files (edits made in the blocks are lost).

**Option B — one block by hand:**
1. Home page → turn **Edit mode** on (switch in the navbar).
2. **Add a block** (top of the page) → **NIT Section**.
3. In the block settings: mode **Raw HTML**, paste the file's content, Width = Full, no frame
   (plain), hide the title → Save.
4. Each block's **⋮** menu → Configure / Move / Delete.

## Rules for these files (why they look like this)

- **Arabic + English:** every text is written twice, `{mlang ar}…{mlang}{mlang en}…{mlang}`
  (the multilang2 filter keeps the page language's one) — also inside attributes
  (`data-placeholder`, `aria-label`, `data-label`) that the scripts read. Sections take the
  page direction (RTL in Arabic, LTR in English): use logical CSS (`text-align:start`,
  `inset-inline-start`, `margin-inline-start`), and mirror any left/right offset under
  `html[dir="ltr"]`. Course / category / teacher names come translated from the server.
- **Colours only from Brand Colors → Bassthalk (g18):** every section root has
  `class="nit-brand-18"` and uses `var(--nit-brand-<role>)` (roles `bthhero*`, `bthhow*`,
  `bthsel*`). Never write a hex colour here.
- **No URLs in text or CSS:** Moodle's "Convert URLs into links" filter turns any
  `https://…` it finds — even inside `<style>` — into an `<a>` tag and breaks the CSS.
  That is why there is no `@import` for the fonts (Tajawal / Almarai are already loaded by
  the navbar on every page). Links in attributes (`href`, `src`) are safe.
- **No smiley codes in SVG paths:** Moodle's emoticon filter replaces text like `8-.`, `8-)`,
  `:-)`, `B-)` with an `<img>` — also inside an SVG `d="…"`, which breaks the icon. Put a space
  in such number runs (`3.38 -.36`, not `3.38-.36`).
- **Section hooks:** the root `data-nit-section="hero|how|selected"` is how the template
  finds the block to update — keep it.
- Page-level placement (the hero behind the floating navbar, edge-to-edge width) is in the
  theme: `scss/post.scss`, block "Bassthalk homepage template" (body class
  `nit-bth-template`, set while this template is the active one).
