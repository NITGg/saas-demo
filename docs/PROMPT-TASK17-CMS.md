# Prompt for the next AI session — Task 17: Static pages (CMS) + MOBILE_API_V5.md

> Copy everything below the line into a new AI coding session opened on
> `D:\My work\NIT\Projects\Bassthalk Clone`. The AI starts with no memory of earlier work.

---

You are working on **"Bassthalk Clone"**: an Arabic/English e-learning platform built on **Moodle 5.2**.

- Code: `D:\My work\NIT\Projects\Bassthalk Clone\moodle` (Moodle web root is `moodle\public`).
- Local site: http://localhost:8082. It runs in Docker container `bassthalk_moodle_app`, where the web root is
  `/var/www/html/public` and `config.php` is at `/var/www/html/config.php`.
- Demo server: https://academy2026.nitg-eg.com/bassthalk/. You have no shell or database access to it; the user
  deploys it.
- The user writes in Arabic. **Reply to the user in Arabic (Egyptian colloquial is fine), short and clear.**
  Code, comments, commit messages and English language strings stay in English.

## 0. Read first, and the rules you must follow

1. Read `moodle\CLAUDE.md` and `moodle\AGENT_TESTING.md` and follow them. Key points:
   - **Never modify Moodle core.** Custom code lives only in `public/local/*`, `public/blocks/nit_*` and the theme
     `public/theme/nit`. To change a core template, copy it into `theme/nit/templates/...`.
   - Every behaviour change ships with a test. Tests are Moodle PHPUnit tests under the plugin's `tests/` folder.
   - **Never commit or push.** The user makes all commits.
2. Read `moodle\docs\TASKS-ROADMAP-2026-10.md`, especially "تاسك 17", and mark it done when you finish. This file
   is edited normally.
3. **Mobile API docs rule (from the user):**
   - Never edit `docs/MOBILE_API.md` or `MOBILE_API_V2.md`, `V3.md` or `V4.md`.
   - Create **`moodle\docs\MOBILE_API_V5.md`**. It is a *delta* file that lists only what changed since V4. Copy
     the layout of `MOBILE_API_V4.md`: a title "updates since `MOBILE_API_V4.md`", a short summary, a table of
     changes, then numbered sections.
   - All later API changes go into V5 until the user says to start V6.
4. **Environment facts that save time:**
   - **PHPUnit is not installed** in the container (no `vendor/`). Write the tests anyway, lint them
     (`php -l`), and say clearly that they could not be run.
   - Verify behaviour with CLI scripts instead: write the script to a temp folder, then run it with
     `MSYS_NO_PATHCONV=1 docker exec -i bassthalk_moodle_app php < script.php`. The script starts with
     `define('CLI_SCRIPT', true); require('/var/www/html/config.php');`. For data you create, open a delegated
     transaction and roll it back at the end.
   - Purge caches: `docker exec bassthalk_moodle_app php /var/www/html/admin/cli/purge_caches.php`.
   - Upgrade: `docker exec bassthalk_moodle_app php /var/www/html/admin/cli/upgrade.php --non-interactive`.
   - The Bash tool on this Windows machine breaks on heredocs that contain Arabic text or apostrophes. Write such
     files with the file-writing tool instead.
   - Many existing files use CRLF line endings, so use exact-string edits. Windows `curl` turns Arabic arguments
     into `?`, so send request bodies from a file.
   - Moodle DML:
     - A named placeholder must not appear twice in one query.
     - Use `$DB->sql_concat()`, `sql_like()` and `sql_fullname()`.
     - `get_records_sql` keys the result by its first column.
   - Test logins are in `public/local/nit_finance/cli/seed_test_accounts.php`. Never print credentials in chat.
     The admin password belongs to the user; do not use it.
5. **Things that bit earlier work (and apply here):**
   - **Bilingual text:** both languages go in one field as `{mlang en}…{mlang}{mlang ar}…{mlang}`. The multilang
     filter resolves this through `format_string()` / `format_text()`. The helper `theme_nit_ml($en, $ar)` in
     `theme/nit/lib.php` builds the string.
   - **Filters inside HTML:** Moodle's "Convert URLs into links" filter turns any `https://…` written as text,
     even inside `<style>`, into a link. So use no `@import` and no URLs in CSS text; `href` and `src` attributes
     are safe. The emoticon filter changes `8-)` and `:-)` inside SVG path data, so put spaces in such number runs.
   - **Sub-folder install:** the demo server lives under `/bassthalk/`. Build URLs with `moodle_url`, never a bare
     `/path`. `block_nit_section` already rewrites root URLs (`site_relative_urls()`).
   - **Fresh database on the demo server, and no Arabic language pack there.** Every Arabic string must be in the
     plugin's `lang/ar` file. Any setting or default content must be created by `db/install.php` or an upgrade
     step, never by hand.
   - **Admin setting defaults are saved on upgrade.** Use `''` for "not set yet" list settings.
   - **HTML files used as seed content only reach existing sites through an upgrade step.** A version bump plus an
     upgrade function writes them; editing the file alone changes nothing on a live site.
   - When the user reports something strange, find and reproduce the exact cause before answering. Do not reply
     with "maybe X, maybe Y".

## 1. What already exists (read these before designing)

- **Home page sections:** block `public/blocks/nit_section` (`block_nit_section.php`, `edit_form.php`).
  - The admin picks "visual" (TinyMCE) or "html" (raw HTML with inline `<style>`/`<script>`) mode, plus layout
    fields: width, alignment, margins, "plain" frame.
  - Content is stored in the block config and rendered through `format_text`, so `{mlang}` works.
  - Templates are files in `theme/nit/blocks/templates/<id>/*.html`. Read the two READMEs there for the house
    rules, the `data-nit-*` hooks and the bassthalk visual style. The registry is
    `theme/nit/classes/local/homepage_templates.php`.
  - `theme/nit/classes/local/template_applier.php` writes those files into blocks (`apply()`,
    `refresh_sections()`).
  - There is an in-page editor: `theme/nit/edit.php`, `theme/nit/js/editor.js`,
    `theme/nit/classes/local/homepage_content.php` (`data-nit-edit` attributes).
- **Navbar:** `theme/nit/templates/theme_boost/navbar.mustache` overrides Boost.
  - Today it shows the logo, search, a gear dropdown, language, notifications and the user menu. **There is no
    row of page links.**
  - The gear items come from `theme_nit_navmenu_links('gear')` (`lib.php`, setting `theme_nit/navmenu_gear`).
  - The home-page editor has a "nav pages" picker: `theme_nit_site_pages()` and `theme_nit_save_nav_pages()` in
    `theme/nit/lib.php`. It writes core `$CFG->custommenuitems` with links such as `/#nit-about`, `/#nit-faq` and
    `/#nit-contact`.
  - `theme/nit/config.php` removes the core home/myhome/courses items.
  - Check how (and whether) the custom menu shows in the bar before you choose an approach.
- **Footer:** `theme/nit/templates/theme_boost/footer.mustache` plus `theme_nit_footer_context()`. **The CMS must
  not touch the footer** (user decision).
- **Terms and privacy today:** `public/local/multitopics/legal.php?doc=terms|privacy|delete[&lang=ar|en][&embedded=1]`.
  - `embedded=1` returns a bare, sessionless HTML page for the app's webview.
  - Content comes from config `local_multitopics/{doc}_{lang}`. There is no admin form for it; otherwise PHP
    defaults are generated by `local_multitopics_legal_default()`.
  - Links to it are built in:
    - `theme_nit_links_export()` in `theme/nit/lib.php`, used by the app bootstrap `theme/nit/design_system.php`.
      It also has `link_about_*` and `link_faq_*` slots.
    - `local/academy/classes/local/registration.php` (`termsurl`).
- **FAQ / contact / about today:** only static sections on the home page (`faq.html`, `contact.html`,
  `about.html` in the theme templates). There is no news, no FAQ data and no contact form.
- **Mobile API:** every plugin's `api.php` uses the shared helper `\local_academy\api\endpoint`
  (`public/local/academy/classes/api/endpoint.php`). The docblock shows usage: `boot()`, `authenticate()`,
  `public_viewer()`, `is_shared_token()`, `run()`, `paging()` and `file_url()`.
  - Visitors browse with a shared pre-login token.
  - `local/nit_category/api.php` is a short example.
  - Docs conventions are in `docs/MOBILE_API.md` §1.3–1.4: writes are POST, an optional `lang=ar|en`, and
    `{mlang}` already resolved in responses.

## 2. The task — what the user decided

Build a CMS plugin (suggested name `local_nit_pages`). The admin manages static pages from the dashboard without
code changes. **All decisions below are final, from the user:**

0. **Do not change the home page at all.** That covers its sections, its in-page editor's behaviour and its
   "nav pages" picker. The home sections about, FAQ and contact stay as they are. There is also no
   "latest articles" block for the home page.
1. **How a page is built:** each page gets its **own block area**. The admin adds `nit_section` blocks to it with
   the same editor as the home page (HTML or visual), so there is no limit on pages or blocks.
   - Recommended mechanism: blocks scoped by page type plus `subpagepattern` = page id.
   - Check the theme layouts and pick a region that supports full-width sections like the home page.
   - Normal Moodle block editing is fine. Reuse pieces of the home in-page editor only if you can do it without
     changing anything on the home page.
2. **Languages:**
   - The page title and the slug have **separate Arabic and English fields**.
   - Block content uses `{mlang}` like the home page.
   - Direction (RTL/LTR) follows the page language automatically.
3. **Admin screen, for site admins only** (no manager access): a table of pages with title, link (with a copy
   button) and status (published or draft).
   - Actions: add, edit, delete, and "view / edit content".
   - **Default pages cannot be deleted**, only switched off.
4. **Navbar: do NOT build our own navbar feature.**
   - The user's rule: if Moodle or our theme already has a way to put links in the navbar, use that instead of
     building a "show in navbar" option.
   - So the CMS has no navbar or footer settings. The admin adds page links with the existing mechanism:
     - Moodle's **Custom menu items** (Site administration → Appearance → Themes → Advanced settings,
       `custommenuitems`, which supports a `|lang` per line); or
     - the theme's existing navbar link settings (Site pages → Navbar menus).
   - Find out which of these shows **as text links directly in the navbar bar** (not inside the gear or an icon
     menu), and that it works on a phone. Tell the user which one to use and how, step by step.
   - Warning: the home-page editor's "nav pages" picker (`theme_nit_save_nav_pages()`) **rewrites
     `custommenuitems`**. Check whether saving it would wipe links the admin added by hand.
   - If it would, do **not** change the home page or its editor. Explain the problem to the user in Arabic and ask
     what to do.
   - Do not add the default pages to the navbar automatically.
5. **Default pages**, created on install (and on the demo server through the install or upgrade step):
   - **About us** (عن المنصة)
   - **Contact us** (تواصل معنا)
   - **Terms and conditions** (الشروط والأحكام)
   - **Privacy policy** (سياسة الخصوصية)
   - **FAQ** (الأسئلة الشائعة)
   - **Articles** (المقالات)

   Each starts with **ready-made starter content in the bassthalk style**, built from `nit_section` blocks, which
   the admin then edits.
   - **About us** and **Contact us** start with content **copied** from the home page's about and contact
     sections: their current texts in both languages, the contact details and the social links. Read them; do not
     change the home page.
   - The Arabic name of the articles page is **"المقالات"** (not "الأخبار").
6. **Articles: a real articles system.**
   - Each article has a title (ar/en), slug, summary (ar/en), body (ar/en, rich text), cover image, status
     (draft/published), publish date, author and timestamps.
   - The Articles page shows its blocks (optional intro) **plus an automatic list**: newest first, with search
     and pagination (**9 per page**), as cards with image, title, date and summary.
   - **No categories or tags.**
   - Each article has its own detail page.
   - The admin gets an articles management screen (list, add, edit, delete, publish/unpublish).
7. **Contact us: content only, no form.** It is a normal block page with the contact details (phone, email,
   address, social links, map). Do not build a form or a messages inbox.
8. **FAQ: a ready-made block, no data model.** Provide a `nit_section` HTML template with an accessible
   accordion (questions and answers in `{mlang}`, inline CSS and JS, keyboard friendly). The admin edits it like
   any block. Do not build FAQ tables or categories.
9. **Terms and privacy:**
   - Move the current text into the new Terms and Privacy pages: the stored config if set, otherwise the PHP
     defaults, in both languages, as `{mlang}` block content.
   - **Keep `legal.php` working** so the app's existing links do not break. It must show the new pages' content,
     including `embedded=1` mode.
   - `doc=delete` (account deletion) is not part of the CMS: leave it as it is.
   - Point `theme_nit_links_export()` (terms, privacy, and the about/faq slots) and the registration `termsurl`
     at the new pages.
10. **URL format:** `/local/nit_pages/page.php?p=<slug>`, and the same style for articles, e.g.
    `/local/nit_pages/article.php?a=<slug>`.
11. **Who can see pages:**
    - Published pages are open to **visitors without login** (needed for Terms, Privacy and search engines).
    - Each page has a "logged-in users only" option.
    - Drafts are visible to admins only.
12. **Per page:**
    - An SEO description (ar/en).
    - A share image (og:image).
    - A **"last updated" date** shown at least on Terms and Privacy.
13. **Mobile API:** add `public/local/nit_pages/api.php` using the shared endpoint helper. It must accept the
    visitor's shared token. Functions, as a guideline:
    - the list of published pages (title in the requested language, slug, embedded URL);
    - one page: title, last updated, SEO data, and an embedded HTML URL for a webview (like `legal.php`
      `embedded=1`), plus the resolved HTML if practical;
    - the article list, with paging and search;
    - one article.

    **Document it in `MOBILE_API_V5.md`** (see §3).
14. Every string goes in both `lang/en` and `lang/ar`. Add a `privacy/provider.php`.
15. Write PHPUnit tests, for example:
    - slug and default-page rules (defaults cannot be deleted);
    - visibility (draft, logged-in only, guest);
    - article listing and search;
    - the `legal.php` fallback to the new pages.

    Also verify everything in the browser at http://localhost:8082 as admin, as a visitor and on a phone width,
    in both Arabic and English.

If something important is still unclear, **ask the user before building it** (in Arabic, with your recommended
default next to each question).

## 3. `MOBILE_API_V5.md`: also document today's earlier work

V5 must describe the two features worked on today, the **reports** and the **static pages**, plus every other
API-visible change not already in V4. Check V4 first so nothing is listed twice.

1. **Static pages (CMS):** everything from §2 point 13. Give each function its method, parameters, response
   example and errors, in the same style as V4.
2. **Reports (`public/local/nit_reports`, done today): web only, no mobile endpoints.** Write a short section so
   the mobile developer knows it exists and that there is nothing to integrate.
   - There are 12 reports at `/local/nit_reports/index.php?report=<key>`:
     - `students`, `teachers`, `courses`, `student_results`, `videos`;
     - `sales`, `subscriptions`, `packages`, `lessons`;
     - `discounts`, `codes`, `teacher_dues` (shown as "Teacher earnings & payouts").
   - Export: `/local/nit_reports/export.php?report=<key>&format=pdf|excel|csv`.
   - Access: admins see everything; a scoped manager sees their courses; a teacher sees the teaching reports of
     their own courses.
   - The old revenue page `/local/payments/report.php` now redirects to the sales report.
   - `local/nit_finance/manage_withdrawals.php` is now titled "Teacher withdrawal requests" and no longer shows
     the platform total cards.
3. **Behaviour changes from today that the app can see** (confirm each in the code before writing):
   - **Course "already purchased":** `\local_payments\price_resolver::is_purchased()` now needs a completed
     payment **and** an active enrolment.
     - A student whose enrolment was removed or ended sees the price again and can buy the course again.
     - This changes `is_purchased` in the web-service functions `get_courses_with_pricing`, `get_course_access`
       and `get_course_price` under `public/local/payments/classes/external/`, and in the course card data
       (`price_resolver::card_context()`).
   - **Video progress (`local_nit_videoprogress`):**
     - A short video watched to the end now reaches **100%**. Before, the first slice was never counted, so it
       stayed at 99%.
     - The fix is in `js/tracker.js` (playback start sets the starting point).
     - Upgrade step `2026100700` corrects saved rows stuck at 99% for that reason.
     - The `percent` fields returned by `local/nit_videoprogress/api.php` can therefore now be 100 where they
       were 99.
   - Payment confirmation messages and course names inside them are now in the buyer's language (`{mlang}`
     resolved). Check whether V4 already says this; add it only if not.

## 4. When you finish

- Mark task 17 done in `docs/TASKS-ROADMAP-2026-10.md`, with a short summary of what was built.
- Tell the user, in Arabic:
  - what was built;
  - what you tested, and that PHPUnit could not run;
  - what to test on the server, step by step;
  - how to add the page links to the navbar with the existing mechanism (§2 point 4);
  - the deploy command:
    `git pull && docker compose exec moodle php admin/cli/upgrade.php --non-interactive && docker compose exec moodle php admin/cli/purge_caches.php`
- **Never commit or push.** The user makes all commits. The working tree already holds uncommitted work
  from earlier today (reports and fixes): leave it as it is.

## 5. Decided earlier — do not change

The reports' "Completion" column shows "—" for courses whose activities have no completion condition. The user
decided to **leave this as Moodle does it**: teachers set completion conditions themselves. Do not build
automatic completion.
