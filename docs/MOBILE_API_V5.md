# NIT Academy — Mobile API: updates since `MOBILE_API_V4.md`

This file lists **only** what changed after [`MOBILE_API_V4.md`](MOBILE_API_V4.md) (frozen at commit
`a735b3f27`, 2026-10-07 09:56). Read it on top of [`MOBILE_API.md`](MOBILE_API.md), `MOBILE_API_V2.md`,
`MOBILE_API_V3.md` and `MOBILE_API_V4.md`. Everything not listed here is exactly as described there.

**Summary:** one new endpoint, **Static pages & articles (CMS)** (§1). The **reports system** (§2) is web
only, with no mobile endpoints. There are also **app-visible changes to existing calls** (§3): the
legal/about links now point to the CMS pages, the purchase check needs an active enrolment, the
video-progress protocol has one new step, payment messages arrive in the buyer's language, and a teacher
sets how many Flex one live lesson costs (§3.7). The
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
| 10 | Messaging between students | When the admin turns on "Prevent messaging between students": `core_message_send_instant_messages` (per-message error), `send_messages_to_conversation` / `create_contact_request` (errorcode `studentmessaging_blocked`), `canmessage = false` between two students | **Check**: hide the message box when `canmessage` is `false` (§3.6) |
| 11 | Flex price per live lesson | `/local/nit_lessons/api.php`: `get_teachers` (+`lesson_flex`), `get_teacher_profile` (+`lesson_flex`), `update_teacher_profile` (+optional `lesson_flex`), every lesson (+`flex_count`), `request_lesson` (new errorcode `notenoughflex`) | **Yes**: show the price, a "Flex per lesson" box for teachers, handle `notenoughflex` (§3.7) |
| 12 | Push Notifications (Firebase FCM) | Direct Firebase Cloud Messaging (HTTP v1) replaced Airnotifier. FCM token registered via `core_user_add_user_device` | **Yes**: Add Firebase to mobile app and register FCM token on login (§6) |
| 13 | Course Jitsi activities with a session time | Session rows (+`audience`: `invited` / `course`); `get_student_sessions` also returns course sessions; the session reminder links to the activity; a user not enrolled in the course can't join any room | No (§3.8) |

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

**Leaving and coming back, how a session ended (10/10):**
- **New fields on session rows** (`get_teacher_sessions`, `get_student_sessions`, `start_session`; returned as
  stored, so they are strings or `null` like the other columns):

  | Field | Meaning |
  |---|---|
  | `teacher_first_join` | when the session's teacher first came into the call (lateness is measured from it) |
  | `teacher_last_leave` | when the teacher last left the call. The session goes on; this is not the end |
  | `ended_at` | when the session was ended |
  | `ended_by` | user id who ended it; `null` = the system |
  | `end_reason` | `teacher` (its teacher), `admin` (someone else), `schedule` (its time ran out), `lesson` (its private lesson was completed or an absence was reported); `null` for sessions ended before 10/10 |

- **`leave_session` → `duration_seconds`** is now the time actually spent in the call. Each leave and rejoin is
  kept, and time spent out between them does not count. Before, it was "last leave − first join".
- **`set_teacher_present`**: only the session's own teacher opens or closes the student gate. A site admin who
  joins is recorded as being in the call, but the students still wait for the teacher.
- **App action:** none required. Show `end_reason` / `ended_at` if useful.

> **Step-by-step guide for the app:** [`apis/jitsi-rooms-mobile-guide.md`](apis/jitsi-rooms-mobile-guide.md)
> (with answers to the app team's questions). **New 11/10:** while in the call, repeat `room_presence(present=1)` every
> 60 s (heartbeat). A stretch silent for 3 min is closed by the server at the last heartbeat.
> Recommended: open the room's web page in a WebView (everything below then works as on the website).

**In the call: students see the teacher only, hosts never wait (10–11/10). Applies to every room: lesson
rooms and course Jitsi activities:**
- **`mod_jitsi_get_session_info` has new fields:**

  | Field | Meaning |
  |---|---|
  | `isolate_students` | `true` for every student (non-host). They must see **the teacher only** |
  | `auto_admit` | `true` for a host (teacher, manager, admin). See the waiting room below |
  | `lobby` | `true` for a course room with a waiting room (below). `false` for lesson rooms |
  | `host_names` | display names of the room's hosts (its teachers, then every site admin) |
  | `access_code` | `ok`, or why the user is not in yet: `waitingforteacher`, `sessionnotavailable`, `sessionended`, `notallowed`, `unavailable` |
  | `teacher_name` | the (first) teacher's display name, to pin |
  | `teacher_names` | the teachers' display names in order (course teachers first, then admins / managers); pin the first one in the call |

- **App action (Jitsi Meet SDK), when `isolate_students` is true.** These are what the web page does:
  - config `channelLastN: 1`
  - `disableTileView: true`
  - `filmstrip: {disabled: true, disableStageFilmstrip: true}`
  - `autoPinLatestScreenShare: false`
  - remove the tile-view button
  - pin the first participant in `teacher_names` who is in the call; else any moderator (from the room roles)
  - pin their **screen share** while they share it, otherwise their camera
  - re-pin on: participant joined / left / role changed, content-sharing changed, and every few seconds
  - while no teacher is in the call, cover the video with a "the teacher is not in the call" notice

**Course rooms: the waiting room is Jitsi's own lobby (11/10).** A course Jitsi activity has "Students wait for the
teacher to let them in" (on by default). The teacher answers the student **inside the meeting** (Jitsi's "wants to
join" notification with Admit / Reject).
- **Student:** gets a JWT only **while a host is in the call** (until then `available=false`,
  `access_code=waitingforteacher`; poll every 5 s). With the JWT they join, land in the lobby and knock.
  - The SDK shows its knocking screen.
  - Turn auto-knock on and make the name read-only (`readOnlyName: true`).
- **Host, when `lobby` is true:**
  - on conference joined, turn the lobby on;
  - on a knocking participant whose name is in `host_names`, admit them at once (hosts never wait for each other);
  - leave everyone else to the SDK's built-in admit / reject prompt.
- **Host, when `lobby` is false** (lesson rooms): turn the lobby off on join, and admit every knocker.
- **"A host is in the call"** is what the room's attendance log says. So the app **must** call `room_presence`
  (below) on joined / left. Otherwise students wait for a teacher who is on the app.
- Removed: Moodle's own request list (`get_room_admissions`, `decide_room_admission`, `ask_room_admission`, codes
  `waitingforapproval` / `admissiondenied`). They existed for a few hours on 11/10 and were never released to the app.

**The room's attendance log (11/10).** Every time anyone is in a room's call is recorded, and the room's hosts see
it on the web page "Attendance log" (activity menu, or `/mod/jitsi/attendance.php?id=<cmid>`, with a CSV download).
- **App action:** call `POST /local/academysessions/api.php?function=room_presence&cmid=…&present=1` when the
  conference is joined, and `present=0` when it is left.
- Joining is only recorded while the room lets the user in.
- ⚠️ **This is enforced in the clients (web page and app), not by the Jitsi server.** A student with a modified
  client could still request other students' video. Server-side isolation is planned
  (`docs/CAMERA-PRIVACY-STUDY.md`).

### 3.6 Messaging: "Prevent messaging between students"

New admin setting (Site administration → Messaging → Messaging settings, off by default). When it is on,
two students can't message each other. A teacher (anyone with `moodle/site:messageanyuser`, at the site
level or in a course), a manager or an admin can still message anyone and be messaged by anyone. It applies
to the core `core_message_*` web services the app already calls:
- `core_message_send_instant_messages`: a message to another student comes back with `msgid = -1`,
  `errormessage` = "You cannot send messages to other students." (in the user's language) and
  `cantsendtouser` = the recipient's id. The other messages in the same call are sent as usual, in the same
  order.
- `core_message_send_messages_to_conversation` (an individual conversation with another student) and
  `core_message_create_contact_request` (to another student): a WS exception with errorcode
  `studentmessaging_blocked`.
- `core_message_get_member_info`, `get_conversation`, `get_conversations`, `get_conversation_between_users`,
  `get_conversation_members`, `message_search_users`, `get_user_contacts`, `get_contact_requests`: another
  student comes back with `canmessage = false`, `requirescontact = false`, `canmessageevenifblocked = false`.
- Group conversations (course-group messaging) are not affected.
- **App action:** when `canmessage` is `false`, hide the message box and show "You can't message this user"
  (the web drawer already does this). Show `errormessage` / the exception message if a send fails.

### 3.7 Live lessons: the teacher sets the Flex price of a lesson

A lesson used to cost exactly 1 Flex. Now each teacher sets **how many Flex one lesson with them costs**
(Profile → Teacher profile on the web, or `update_teacher_profile`): any whole number, 1 or more, with
no maximum. A teacher who never set it costs 1 Flex, so nothing changes for them.

- The price is fixed on the lesson **when the student requests it** (`flex_count`). If the teacher changes
  their price later, lessons already requested keep their old price.
- Everything that moved 1 Flex now moves `flex_count`: the teacher accepting reserves `flex_count`, a
  completed lesson / late cancel / student absent uses `flex_count`, an early cancel / teacher cancel /
  teacher absent / admin reversal returns `flex_count`. The teacher's earning is its percent of
  `flex_count` × the value of one Flex of the student's package.

**`get_teachers`** — each teacher has `lesson_flex` (int, ≥ 1):
```json
{"id":22,"fullname":"مدرس الحصص","headline":"مدرس فيزياء","lesson_flex":3,"subjects":[…],"picture_url":"…"}
```
Show it ("3 Flex per lesson"). Disable "Request a lesson" when the student's `remaining_flex` (from
`get_my_flex`) is less than `lesson_flex`.

**Every lesson** (`get_my_lessons`, `get_lesson`, and the result of every lesson action) has
`flex_count` (int, ≥ 1): the Flex this lesson costs.

**`request_lesson`** — new errorcode **`notenoughflex`**: the student has Flex, but fewer than the
teacher's `lesson_flex`. The message names the price; send the student to the packages screen, as for
`noflex`. (`noflex` still means no Flex at all.) If the student spends Flex between the request and the
teacher accepting, the accept fails with `noflex`, as before.

**`get_teacher_profile`** — one new field:
```json
{"available":true,"headline":"مدرس فيزياء","lesson_flex":3,"subjects":[…],"hours":[…], …}
```

**`update_teacher_profile`** — one new optional param:

| Param | Type | Req | Description |
|---|---|---|---|
| lesson_flex | int | no | Flex per lesson, 1 or more (no maximum). **Leave it out to keep the saved price** |

New errorcode **`badlessonflex`**: `lesson_flex` is less than 1.

### 3.8 Course Jitsi activities with a session time (12/10)

A teacher can now give a course Jitsi activity a **session time** on the web (activity settings → "Session
time": start + length in minutes). Such an activity gets a live session row like the ones the app creates,
with a new field **`audience`**:

| `audience` | Who the session is for |
|---|---|
| `invited` | the students listed on it (every session before 12/10, and every session the app creates) |
| `course` | **every student of the course**, including students who enrol after it was scheduled |

- **`get_student_sessions`** now also returns the `course` sessions of the student's courses (the student
  list follows the course; a newly enrolled student appears within 5 minutes, or as soon as they open the
  room). `get_teacher_sessions` returns them to the teacher who scheduled them. Every row has `audience`
  (string) and `cmid` (the Jitsi activity).
- **Joining:** the same calls as before (`mod_jitsi_get_session_info` / `api_token.php`, then `room_presence`):
  - the room opens to students 30 minutes before the start and closes at the end
    (`sessionnotavailable` / `sessionended`);
  - students wait until a teacher is in the call (`waitingforteacher`);
  - **every editing teacher of the course** hosts the room (`is_teacher=true`, moderator JWT). Any of them
    being in the call lets the students in, and `teacher_names` lists all of them, as in a course room without
    a time;
  - `lobby` follows the activity's "Students wait for the teacher to let them in" setting.
- **Reminders:** the session reminder notification (`sessionreminder`) goes to every student on the session
  and to the teacher who scheduled it, at the admin's lead times. Its `url` is the activity
  (`/mod/jitsi/view.php?id=<cmid>`), no longer the course page.
- **Not enrolled = not allowed:** any Jitsi room (with or without a time) now refuses a user who is not
  enrolled in its course (a course guest): `notallowed` / `available=false`.
- `set_teacher_present` (app API) still accepts the session's own teacher only; the other teachers of a
  `course` session are recorded by `room_presence`.
- **App action:** none required. Show `audience=course` sessions in the student's list like the others.

---

## 4. Device limit — `local_nit_devices` (new)

An account may be used on a set number of devices (an admin setting, e.g. 2). **Browsers and app installs
count together.** A device stays registered until an admin removes it, so signing out does not free its
place. When one device too many signs in, the admin's policy decides: **refuse it** (`devicelimit`) or
**sign the oldest device out** (its token is deleted: its next call answers HTTP 401 `invalidtoken`, as
for any dead token). Students and teachers are limited; site admins and managers have no limit. While the admin keeps the limit
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
- `limited` false: this account has no limit (site admin or manager) or the limit is off.
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

---

## 6. Push notifications (Firebase Cloud Messaging — FCM)

The backend now delivers all push notifications directly via **Firebase Cloud Messaging (HTTP v1)** through the `message_fcm` plugin (Airnotifier is retired and disabled).

> **Complete integration guide for the app team:** [`apis/firebase-push-notifications-mobile-guide.md`](apis/firebase-push-notifications-mobile-guide.md)

### 6.1 Setup in the Mobile App
1. Add Firebase to the project:
   - **Android**: Add `google-services.json` to `android/app/`.
   - **iOS**: Add `GoogleService-Info.plist` to `ios/Runner/` (or iOS project root).
2. Install the Firebase Messaging package (e.g. `firebase_messaging` in Flutter / React Native).
3. Request notification permissions from the user.
4. Retrieve the **FCM Registration Token**: `FirebaseMessaging.instance.getToken()`.

### 6.2 Registering the Device Token on Login

Immediately after the user successfully logs in (or whenever the FCM token refreshes via `onTokenRefresh`), call the standard Moodle Web Service function:

**`POST /webservice/rest/server.php?wstoken=<user_token>&wsfunction=core_user_add_user_device&moodlewsrestformat=json`**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `appid` | string | yes | App Package Name / Bundle ID, e.g. `com.bassthalk.app` |
| `name` | string | yes | Device display name, e.g. `Samsung Galaxy S23` or `iPhone 15 Pro` |
| `model` | string | yes | Device hardware model, e.g. `SM-S911B` or `iPhone16,1` |
| `platform` | string | yes | Operating system: `Android` or `iOS` |
| `version` | string | yes | OS version string, e.g. `14` or `17.4` |
| `pushid` | string | yes | **The FCM Registration Token** obtained from Firebase Messaging |
| `uuid` | string | yes | Unique device/install UUID (matches the install `deviceid` from §4) |

**Success response:**
```json
[]
```
*(Empty array of warnings indicates success).*

### 6.3 Removing the Device on Logout

When the user logs out from the app, call:

**`POST /webservice/rest/server.php?wstoken=<user_token>&wsfunction=core_user_remove_user_device&moodlewsrestformat=json`**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `uuid` | string | yes | The unique device/install UUID |
| `appid` | string | no | Optional. The app id / package name |

**Success response:**
```json
{
  "removed": true,
  "warnings": []
}
```

### 6.4 Notification Payload Format (What the app receives)

Notifications sent by the server arrive with both standard visible notification fields and custom metadata in `data`:

```json
{
  "notification": {
    "title": "إشعار جديد",
    "body": "تمت إضافة واجب جديد في كورس الفيزياء."
  },
  "data": {
    "notification_id": "1845",
    "eventtype": "assign_notification",
    "component": "mod_assign",
    "contexturl": "http://localhost:8082/mod/assign/view.php?id=45",
    "timecreated": "1728659100",
    "userfromid": "3",
    "userfromname": "أحمد محمود"
  }
}
```

- **Android channel:** `moodle_notifications` (priority: `HIGH`, click_action: `FLUTTER_NOTIFICATION_CLICK`).
- **iOS APNs:** High priority (`10`), sound: `default`, badge: `1`.
