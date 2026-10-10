# NIT Academy — Mobile API: updates since `MOBILE_API_V4.md`

This file lists **only** what changed after [`MOBILE_API_V4.md`](MOBILE_API_V4.md) (frozen at commit
`a735b3f27`, 2026-10-07 09:56). Read it on top of [`MOBILE_API.md`](MOBILE_API.md), `MOBILE_API_V2.md`,
`MOBILE_API_V3.md` and `MOBILE_API_V4.md`. Everything not listed here is exactly as described there.

**Summary:** one new endpoint, **Static pages & articles (CMS)** (§1). The **reports system** (§2) is web
only, with no mobile endpoints. There are also **app-visible changes to existing calls** (§3): the
legal/about links now point to the CMS pages, the purchase check needs an active enrolment, the
video-progress protocol has one new step, and payment messages arrive in the buyer's language. The
**device limit** (§4) brings a new sign-in endpoint with a per-install `deviceid`.

| # | Area | Affects | App change needed |
|---|---|---|---|
| 1 | Static pages & articles (CMS) | New endpoint `/local/nit_pages/api.php`: `get_pages`, `get_page`, `get_articles`, `get_article` | Yes, for new screens: pages (webview) and articles (native list/detail) |
| 2 | Reports | 12 admin/teacher reports under `/local/nit_reports/`. The 5 **teacher** reports also have a mobile API (row 8) | Admin reports: no (web only, open the link) |
| 3 | Legal & about links | `design_system.php` → `links.terms`, `links.privacy`, `links.about`, `links.faq`; `get_registration_form` → `termsurl`; `legal.php` content | No if the app opens the URLs it receives. `about` and `faq` are now always present |
| 4 | Purchase state (`is_purchased`) | `local_payments_get_course_price`, `local_payments_get_course_access`, `local_payments_get_courses_with_pricing`, `local_payments_create_checkout` | No: the field just becomes accurate |
| 5 | Video progress reaches 100% | the slices protocol (`MOBILE_API.md` §5.2, step 2) + `save_progress` | **Yes**: one new step when playback starts (§3.3) |
| 6 | Payment messages in the buyer's language | the `payment_confirmation` notification, the gateway page description | No |
| 7 | Device limit | New sign-in `POST /local/nit_devices/token.php` (+ `deviceid`); optional `deviceid` on Google sign-in and `register_student`; new errorcodes `devicelimit`, `appupdaterequired`; new `/local/nit_devices/api.php` → `get_my_devices` | **Yes**: send a per-install `deviceid` and handle the two errors (§4) |
| 8 | Teacher reports API | New endpoint `/local/nit_reports/api.php`: `get_reports`, `get_report` (the 5 teacher reports as cards + table) | Yes, for the new teacher "Reports" screen (§5) |
| 9 | Live-session rooms (Jitsi) | `mod_jitsi_get_session_info` (`jwt` empty until `available`), `/mod/jitsi/api_token.php` (new errorcodes `waitingforteacher`, `jitsinotconfigured`), `join_session` (attendance after the teacher arrives) | **Check**: don't join with an empty `jwt` (§3.5) |

---

## 1. Static pages & articles (CMS) — `/local/nit_pages/api.php` (new)

Admins manage static pages (About, Contact, Terms, Privacy, FAQ, Articles, and any custom page) and articles.
Every academy starts with 6 default pages, each with a `default_key`: `about`, `contact`, `terms`,
`privacy`, `faq`, `articles`.

`GET /local/nit_pages/api.php?function=<name>[&token=<token>][&lang=ar|en]` — the standard envelope
(`MOBILE_API.md` §1.4).

**Who can call it:**
- **The token is optional.** With no token, or with the **shared pre-login token**, the caller is a visitor.
  With the user's own token, the caller is that user. An invalid or expired token still returns HTTP 401
  `invalidtoken`.
- Visitors see published pages that are not "logged-in users only", and published articles.
- Signed-in users also see "logged-in users only" pages.
- Drafts, and articles with a future publish date, are visible only to admins with `local/nit_pages:manage`.

**Language:** `lang=ar|en` picks the language of `title`, `summary`, `slug`, the URLs and the HTML.
Without it, the user's language (or the site language for visitors) is used. `*_ar` / `*_en` fields give
both languages. The HTML never contains raw `{mlang}` markup.

### `get_pages` — GET
The pages the caller can see, default pages first.

| param | type | req | description |
|---|---|---|---|
| token | string | no | user token or shared token |
| lang | string | no | `ar` or `en` |

```json
{"status":"success","data":{"pages":[
  {"id":1,"slug":"about","slug_ar":"عن-المنصة","slug_en":"about","title":"About Us","title_ar":"عن المنصة",
   "title_en":"About Us","is_default":true,"default_key":"about","updated_at":1791377447,
   "embedded_url":"http://localhost:8082/local/nit_pages/page.php?p=about&embedded=1&lang=en",
   "url":"http://localhost:8082/local/nit_pages/page.php?p=about&lang=en"},
  {"id":2,"slug":"contact","slug_ar":"تواصل-معنا","slug_en":"contact","title":"Contact Us","…":"…"}]}}
```
- `slug` is the slug in the requested language (`slug_ar` for `lang=ar`). Either slug opens the page.
- **Open `embedded_url` in a webview**: a chrome-less page (no navbar or footer) in the requested
  language. It works without login. `url` is the full website page (for "open in browser" or sharing).
- `default_key` is empty for custom pages.

### `get_page` — GET
One page with its rendered HTML. Send **one** of `key`, `id`, `slug` (or `p`).

| param | type | req | description |
|---|---|---|---|
| key | string | one of | `about`, `contact`, `terms`, `privacy`, `faq`, `articles` |
| id | int | one of | page id |
| slug / p | string | one of | the Arabic or English slug |
| token | string | no | user token or shared token |
| lang | string | no | `ar` or `en` |

```json
{"status":"success","data":{"id":4,"slug":"سياسة-الخصوصية","slug_ar":"سياسة-الخصوصية","slug_en":"privacy",
 "title":"سياسة الخصوصية","title_ar":"سياسة الخصوصية","title_en":"Privacy Policy",
 "seo_desc":"سياسة الخصوصية وحماية بيانات المستخدمين في منصة بسطتهالك.","share_image":"",
 "is_default":true,"default_key":"privacy","updated_at":1791377447,
 "embedded_url":"http://localhost:8082/local/nit_pages/page.php?p=%D8%B3%D9%8A%D8%A7%D8%B3%D8%A9-%D8%A7%D9%84%D8%AE%D8%B5%D9%88%D8%B5%D9%8A%D8%A9&embedded=1&lang=ar",
 "url":"http://localhost:8082/local/nit_pages/page.php?p=%D8%B3%D9%8A%D8%A7%D8%B3%D8%A9-%D8%A7%D9%84%D8%AE%D8%B5%D9%88%D8%B5%D9%8A%D8%A9&lang=ar",
 "content_html":"<div dir=\"auto\" class=\"nit-page-section nit-page-legal\" style=\"…\">…</div>"}}
```
- `content_html` is the page's sections as full HTML, with inline styles. The simplest way to show a page
  is still `embedded_url` in a webview. Use `content_html` only if you render HTML yourself.
- `share_image` is `""` when not set. `seo_desc` is for sharing previews.

| errorcode | When | App action |
|---|---|---|
| `notfound` | no page for this key / id / slug | show "page not found" |
| `authrequired` (**HTTP 401**) | the page is "logged-in users only" and the caller is a visitor (no token, or the shared token) | **Do not log out here** (this 401 is not a dead token): send the visitor to the login screen |
| `nopermission` | the page is a draft (non-admin caller) | treat as not found. Note the spelling: `nopermission`, not `nopermissions` |

### `get_articles` — GET
Published articles, newest first, with search and paging.

| param | type | req | description |
|---|---|---|---|
| q / search | string | no | searched in the titles and summaries |
| page | int | no | 0-based, default 0 |
| perpage | int | no | default 9, max 50 |
| token | string | no | user token or shared token |
| lang | string | no | `ar` or `en` |

```json
{"status":"success","data":{"articles":[
  {"id":2,"slug":"V5-test-article","title":"مقال اختبار V5","title_ar":"مقال اختبار V5","title_en":"V5 test article",
   "summary":"ملخص عربي","summary_ar":"ملخص عربي","summary_en":"English summary",
   "cover_image":"http://localhost:8082/pluginfile.php/1/local_nit_pages/article_cover/2/cover.png",
   "author_name":"مدير تجريبي","status":"published","published_at":1791618304,
   "url":"http://localhost:8082/local/nit_pages/article.php?a=V5-test-article&lang=ar"}],
 "total":1,"page":0,"perpage":9,"pages":1}}
```
- `pages` = the number of pages of results. There are more while `page + 1 < pages`.
- `cover_image` loads without login. When a user token is sent, it comes as
  `…/webservice/pluginfile.php/…?token=<token>`, which also loads directly. It is `""` when the article has
  no cover (use a placeholder). It can also be an external `https://` URL.
- `author_name` is the author's name, or "Platform Team" / "فريق المنصة" when the author is unknown.
- `slug` is a single slug (not per language).

### `get_article` — GET
One article with its body. Send `id`, or `slug` (or `a`). A numeric `slug` is treated as an id.

| param | type | req | description |
|---|---|---|---|
| id | int | one of | article id |
| slug / a | string | one of | article slug |
| token | string | no | user token or shared token |
| lang | string | no | `ar` or `en` |

```json
{"status":"success","data":{"id":2,"slug":"V5-test-article","title":"V5 test article",
 "title_ar":"مقال اختبار V5","title_en":"V5 test article",
 "summary":"English summary","summary_ar":"ملخص عربي","summary_en":"English summary",
 "body_html":"<p>English body</p>",
 "cover_image":"http://localhost:8082/pluginfile.php/1/local_nit_pages/article_cover/2/cover.png",
 "author_name":"مدير تجريبي","status":"published","published_at":1791618304,
 "url":"http://localhost:8082/local/nit_pages/article.php?a=V5-test-article&lang=en"}}
```
- `body_html` is in the requested language, with a fallback to the other language when that one is empty.
- **Articles have no embedded (chrome-less) view.** `url` is the full website page with the navbar and
  footer. In the app, show `title`, `cover_image`, `author_name`, `published_at` and `body_html` natively.
- Errors: `notfound` (unknown, draft, or not published yet).

---

## 2. Reports — `/local/nit_reports/`

> The **admin / site reports have no mobile endpoints**: they are desktop web screens (give admins the
> link). The **5 teacher reports** (`students`, `courses`, `student_results`, `videos`, `teacher_dues`)
> also have a mobile API: see §5.

- Screen: `/local/nit_reports/index.php?report=<key>`. Export: `/local/nit_reports/export.php?report=<key>&format=pdf|excel|csv`.
- The 12 reports, and who sees each:

| key | Report | Who sees it |
|---|---|---|
| `students` | Students | teachers (their courses) and up |
| `courses` | Course performance | teachers (their courses) and up |
| `student_results` | Student results (quiz grades) | teachers (their courses) and up |
| `videos` | Video watching | teachers (their courses) and up |
| `teacher_dues` | Teacher earnings & payouts | teachers (their own) and up |
| `teachers` | Teachers | course/category managers and up |
| `sales` | Sales & revenue | site-level managers / admins |
| `subscriptions` | Subscriptions | site-level managers / admins |
| `packages` | Lesson packages (Flex) | site-level managers / admins |
| `lessons` | Live lessons & attendance (1:1 lessons and live sessions) | site-level managers / admins |
| `discounts` | Coupons & offers usage | site-level managers / admins |
| `codes` | Access codes (offline lesson / course / wallet codes from the finance plugin, **not** coupons) | site-level managers / admins |

- Capabilities: `local/nit_reports:view` (managers) and `local/nit_reports:viewteaching` (teachers and
  editing teachers, limited to the courses they teach).
- The old web page `/local/payments/report.php` now redirects to the `sales` report. The mobile call
  `local/payments/api.php?function=get_revenue_report` (`MOBILE_API.md` §6.1.3) is unchanged.
- The web page `/local/nit_finance/manage_withdrawals.php` is now titled "Teacher withdrawal requests". The
  mobile finance calls (`MOBILE_API.md` §7.4) are unchanged.

---

## 3. Changes to existing calls

### 3.1 Legal and "about" links now point to the CMS pages

- **`design_system.php` → `links`** (`MOBILE_API.md` §2.1.2):
  - `terms` and `privacy` now default to the CMS pages:
    `…/local/nit_pages/page.php?p=terms&embedded=1&lang=en|ar` (before: `…/local/multitopics/legal.php?doc=terms&embedded=1`).
  - **`about` and `faq` are now always present**, as `{en, ar}` URLs to the CMS pages. Before, they appeared
    only when an admin had set them.
  - `delete_account` is unchanged (`…/local/multitopics/legal.php?doc=delete&embedded=1&lang=…`).
  - A URL the academy admin sets explicitly still wins over these defaults.
  ```json
  "links":{"about":{"en":"http://localhost:8082/local/nit_pages/page.php?p=about&embedded=1&lang=en","ar":"…&lang=ar"},
           "privacy":{"en":"http://localhost:8082/local/nit_pages/page.php?p=privacy&embedded=1&lang=en","ar":"…"},
           "terms":{"en":"http://localhost:8082/local/nit_pages/page.php?p=terms&embedded=1&lang=en","ar":"…"},
           "delete_account":{"en":"http://localhost:8082/local/multitopics/legal.php?doc=delete&embedded=1&lang=en","ar":"…"},
           "faq":{"en":"…/local/nit_pages/page.php?p=faq&embedded=1&lang=en","ar":"…"}, "…":"…"}
  ```
- **`get_registration_form` → `termsurl`** (`MOBILE_API.md` §3.1) is now
  `…/local/nit_pages/page.php?p=terms&embedded=1&lang=<lang>`.
- **`/local/multitopics/legal.php?doc=terms|privacy`** (old links in older app builds) still works. It now
  shows the CMS page's content when that page has content, and falls back to the old built-in text
  otherwise. `doc=delete` is unchanged.

**App action:** none if the app opens the URLs it receives. If the app hard-coded `legal.php` links, switch
to the `links` from `design_system.php`.

### 3.2 `is_purchased` needs an active enrolment

Before, a course stayed "purchased" forever after one completed payment, even after the student was
unenrolled (for example, when an admin revoked the purchase or the access ended). The student could not buy
it again. Now `is_purchased` is `true` only when there is a completed payment **and** the student is still
actively enrolled in the course.

This affects `is_purchased` in `local_payments_get_course_price`, `local_payments_get_course_access` and
`local_payments_get_courses_with_pricing`, and the `alreadypurchased` check in `local_payments_create_checkout`.
A student who lost access sees the price again and can check out again.

Verified locally (same student, both courses with a completed payment): enrolled course →
`is_purchased: true`; course they are no longer enrolled in → `is_purchased: false`.

### 3.3 Video progress can now reach 100% — one new step in the app's player

**Problem:** the first stretch of playback (from the start to the first time update) was never counted,
because the protocol only marks slices *between two time updates*. On a short video, that first stretch is a
whole slice, so a video watched to the end stayed at **99%**. The website player is fixed.

**Fix in the mobile player — add this to `MOBILE_API.md` §5.2, step 2:**
> When playback starts (the player's *play* event, before the first time update), set `last` = the current
> playback position. From then on, apply step 2 as before.

Without this step, the app's own reports keep leaving slice 0 out on short videos (under ~25 s the first
time update falls after the first slice), and `percent` stays at 99.

**Server side:** upgrade `local_nit_videoprogress` 2026100700 repaired the saved rows that had **every
slice except slice 0** watched (they were exactly 99%): they are now 100%. Nothing else changed in the
calls (`save_progress`, `get_progress`, `get_course_progress`, `get_overview` keep the same parameters and
fields).

### 3.4 Payment messages in the buyer's language

- The `payment_confirmation` notification (bell, push, email) now uses the buyer's own language for the
  text and the course name. Before, bilingual course names could show raw `{mlang …}` markup.
- The payment description sent to the gateway ("Payment for <course>", shown on the Kashier page) uses the
  resolved course name.
- **App action:** none.

### 3.5 Live-session rooms (Jitsi): one access rule for web and mobile

`mod_jitsi_get_session_info`, `/mod/jitsi/api_token.php` and `join_session` now follow the same rule as the
web room page:
- **Moderator = the session's own teacher** (and site admins). Another teacher of the same course is no
  longer a moderator of a session that is not theirs. They are refused like any non-invited user:
  `available=false` from `get_session_info`, and HTTP 403 `notallowed` from `api_token.php`.
- **`mod_jitsi_get_session_info`: `jwt` is now `""` while `available` is `false`.** Before, the JWT came back
  even while the student was waiting for the teacher, so the room could be opened early. Keep polling every
  5 s while `available` is false (as documented); the JWT arrives with `available=true`. `available_info` is
  the message to show.
- **`/mod/jitsi/api_token.php` now applies the "waiting for teacher" gate:** new 403 errorcode
  `waitingforteacher` (poll again). The other 403 codes are unchanged: `notallowed`, `sessionnotavailable`,
  `sessionended`, `nopermissions`.
- **New 503 errorcode `jitsinotconfigured`** (`api_token.php`; a WS exception with the same code from
  `get_session_info`): the academy has no Jitsi secret set. Show "live sessions are not available"; this is
  an admin setup problem.
- **`join_session` records attendance only once the teacher is in the call** (for a session with a Jitsi
  room). Before that it still returns the session, with `teacher_present=false`. The student's attendance is
  recorded when `get_session_info` lets them in.
- `set_teacher_present` / `end_room` already used the session-teacher rule. No change.
- **App action:** don't join the room when `jwt` is empty; handle `waitingforteacher` and
  `jitsinotconfigured` if the app uses `api_token.php`.

---

## 4. Device limit — `local_nit_devices` (new)

An account may be used on a set number of devices (an admin setting, e.g. 2). **Browsers and app installs
count together.** A device stays registered until an admin removes it, so signing out does not free its
place. When one device too many signs in, the admin's policy decides: **refuse it** (`devicelimit`) or
**sign the oldest device out** (its token is deleted: its next call answers HTTP 401 `invalidtoken`, as
for any dead token). Site admins, managers and teachers have no limit. While the admin keeps the limit
switched off, nothing below changes anything.

**Why the app must change:** `/login/token.php` gives every phone of an account the **same** token, so
the server cannot tell two phones apart or sign one out. The app now sends a `deviceid` and gets a token
of its own per install.

### 4.1 `deviceid` — what to send
- A random id (e.g. a UUID without dashes), generated **once per install** and kept in the app's storage.
  The same value on every sign-in of that install; a new one after a reinstall. Letters, digits, `-`, `_`;
  at most 64 characters.
- `devicename`: the phone model shown to the admin, e.g. `Samsung SM-A515F` (optional).
- `platform`: `android` or `ios` (optional).

### 4.2 Sign in — `POST /local/nit_devices/token.php` (replaces `/login/token.php`)
Same fields as `/login/token.php` plus the device:

| Field | Required | Notes |
|---|---|---|
| `username` | yes | the email, as today |
| `password` | yes | |
| `service` | no | default `moodle_mobile_app` |
| `deviceid` | yes | §4.1 |
| `devicename`, `platform` | no | §4.1 |

```json
{"token":"<token>","privatetoken":"<privatetoken or null>","deviceid":"3f9c2a…"}
```
The same install signing in again gets the **same** token back while it is valid. Errors have the
`/login/token.php` shape (`error` = readable text in the user's language, `errorcode`):
```json
{"error":"الحساب ده مستخدم بالفعل على 2 أجهزة، وده أقصى عدد مسموح. …","errorcode":"devicelimit","maxdevices":2}
```
`invalidlogin`, `usernotconfirmed`, `siteunavailable` … are as before.

### 4.3 Google sign-in and registration
- `POST /local/googleauth/token.php`: add `deviceid` (+ `devicename`, `platform`). With it, the token is
  that install's own; `devicelimit` comes back as above (`{"error":"<text>","errorcode":"devicelimit","maxdevices":2}`).
- `register_student` (`local/academy/api.php`): add the same three fields. A new account has no devices, so
  it is never refused here.

### 4.4 The two new error codes — what the app shows
| errorcode | When | App action |
|---|---|---|
| `devicelimit` | the account already uses `maxdevices` devices (policy "refuse") | Show `error` as it is (it tells the student to ask support to remove a device). Do not retry. |
| `appupdaterequired` | an app build **without** `deviceid` signs in, and either the admin switched old versions off, or the account already has new-app devices | Show "update the app" with a link to the store. |

The old build keeps working until the admin switches old versions off: all its phones then count as
**one** device ("Mobile app (old version)").

### 4.5 `get_my_devices` — `GET /local/nit_devices/api.php?function=get_my_devices&token=<token>`
For an optional "My devices" screen (read only: removing a device is done by support).
```json
{"status":"success","data":{"enabled":true,"limited":true,"max":2,"policy":"block",
 "devices":[{"id":5,"kind":"mobile","name":"Pixel 8","platform":"android","current":true,
             "firstseen":1791620563,"lastseen":1791620583},
            {"id":3,"kind":"web","name":"Chrome — Windows","platform":"Windows","current":false,
             "firstseen":1791620563,"lastseen":1791620563}]}}
```
- `limited` false: this account has no limit (teacher, manager, admin) or the limit is off.
- `policy`: `block` (refuse a new device) or `replace` (sign the oldest out).
- `current`: the device making this call. `kind`: `web` or `mobile`. Times are Unix seconds.

### 4.6 Web views
Pages opened through `tool_mobile_get_autologin_key` (quiz, checkout …) belong to the phone: they do not
count as a browser.

---

## 5. Teacher reports — `/local/nit_reports/api.php` (new)

The 5 reports a teacher sees on the website, as data for a native "Reports" screen. They use the same
code, scope and filters as `/local/nit_reports/index.php`, so the numbers always match the website.

`GET /local/nit_reports/api.php?function=<name>&token=<user token>[&lang=ar|en]` — the standard envelope
(`MOBILE_API.md` §1.4).

**Who sees what:**
- **Teacher / editing teacher**: the 5 reports, limited to **the courses they teach**. Money columns
  (total paid, wallet, Flex) are hidden.
- **Platform manager / admin**: the same 5 reports for the whole site, with the money columns.
- **Student**: no reports (`get_reports` returns an empty list).
- The site-wide admin reports (`sales`, `subscriptions`, `packages`, `lessons`, `discounts`, `codes`,
  `teachers`) are **not** offered here. They stay on the website.

| key | Report |
|---|---|
| `students` | Students (enrolments, last access, completion, quiz average, finished videos) |
| `courses` | Course performance |
| `student_results` | Student results (quizzes, completion, videos per student and course) |
| `videos` | Video watching (viewers, started, finished, average watched) |
| `teacher_dues` | Earnings & payouts (for a teacher: their own) |

### `get_reports` — GET
The reports this user may open (use them as tabs).
```json
{"status":"success","data":{"reports":[
  {"key":"students","name":"الطلاب"},{"key":"courses","name":"أداء الكورسات"},
  {"key":"student_results","name":"نتايج الطلاب"},{"key":"videos","name":"مشاهدة الفيديوهات"},
  {"key":"teacher_dues","name":"أرباح المدرسين والسحب"}],"sitewide":false}}
```
`reports: []` → hide the Reports screen. `sitewide` is `true` for managers and admins (whole site).

### `get_report` — GET
One report: its filters, number cards, columns and one page of rows.

| param | type | req | description |
|---|---|---|---|
| report | string | yes | a `key` from `get_reports` |
| from, to | `YYYY-MM-DD` | no | period (`to` includes the whole day). Only when `period` is in `filters.offered` |
| courseid | int | no | one course, from `filters.options.courses` (`0` = all) |
| status | string | no | from `filters.options.statuses` (empty = `filters.options.statusall`) |
| userid | int | no | from `filters.options.users` (only when offered) |
| q | string | no | search (name, email … depending on the report) |
| sort | string | no | a column `key` whose `sortable` is true |
| dir | `asc` / `desc` | no | default `asc` |
| page | int | no | 0-based, default 0 |
| perpage | int | no | default 20, max 100 |

```json
{"status":"success","data":{
 "report":{"key":"students","name":"الطلاب"},
 "filters":{
   "offered":["period","course","status","q"],
   "values":{"from":"","to":"","courseid":0,"userid":0,"status":"","q":"","view":"","sort":"","dir":""},
   "options":{"courses":[{"id":25,"name":"الحصص المباشرة"},{"id":4,"name":"الفيزياء - الصف الثالث الثانوي"}],
              "statuses":[{"value":"current","label":"الطلاب الحاليين"},{"value":"left","label":"الطلاب اللي خرجوا"}],
              "statusall":"الكل"},
   "description":"من غير فلتر"},
 "cards":[{"id":"card_students","label":"الطلاب","value":"2","help":"الطلاب اللي مطابقين للفلاتر: …"},
          {"id":"card_activeweek","label":"نشطين آخر 7 أيام","value":"2","help":"…"}],
 "columns":[{"key":"name","label":"الاسم كاملا","help":"الاسم بالكامل.","sortable":true},
            {"key":"email","label":"عنوان البريد الإلكتروني","help":"…","sortable":true},
            {"key":"courses","label":"الكورسات","help":"…","sortable":false}, "…"],
 "rows":[{"name":"طالب تجريبي","email":"nit_test_buyer@example.com","phone":"01000000099","registered":"4/10/26",
          "courses":"2","left":"0","subscription":"—","subends":"—","lastaccess":"10/10/26، 11:29",
          "completion":"—","quiz":"—","video":"0 / 1","account":"نشط"}],
 "total":2,"page":0,"perpage":20}}
```
- **Show it like the website:** `cards` as number tiles, then a table (or one card per row) with `columns`
  as headers. Each row has exactly one value per column `key`, in column order.
- **Values are display text**, already formatted in the requested language: dates, `50.0%`, money with
  currency, `"0 / 1"`, and `"—"` for "no value". Show them as they are; do not parse them.
- `help` explains a column or card (show it behind an ⓘ icon). It can be `""`.
- **Filters:** show only the ones in `filters.offered` (`period` → from/to dates, `course`, `status`, `user`,
  `q`, `view`). `filters.options` holds their choices, and is `{}` when the report has none.
  `filters.values` echoes what was applied. `filters.description` is a one-line summary of it.
- Paging: more rows while `(page + 1) * perpage < total`.
- The columns differ by report, and by role (for example, `students` adds `paid`, `wallet` and `flex` for
  managers). Always build the table from `columns`; never hard-code them.
- The web page also has a chart and PDF / Excel / CSV export. They are not part of this API.

| errorcode | When | App action |
|---|---|---|
| `nopermissions` | the user has no reports (a student) | hide the Reports screen |
| `reportnotfound` | `report` is unknown, a site-only report, or not available to this user | go back to the list |
| `missingparam` | `report` not sent | app bug |
