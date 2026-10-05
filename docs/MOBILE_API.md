# NIT Academy — Mobile API Reference

Everything the mobile app (student, teacher, parent, admin screens) can do with an academy, in one
document. Last updated **2026-10-05**. Replaces the older `MOBILE_API.md` / `mobile-app-api.md`
(see [§10.3 What changed](#103-what-changed-for-the-existing-app) for the differences that matter to the
existing app).

> Every screen of the platform is covered, including the Bassthalk home page sections, the teacher
> page and the course details page (§3.10).

## Contents

1. [Getting started](#1-getting-started) — base URL, tokens, the 3 API styles, errors, languages, money
2. [App bootstrap, Google sign-in, catalogue, course content, AI, job forms](#2-app-bootstrap-google-sign-in-catalogue-course-content-ai-job-forms)
3. [Sign-in, password, profile, home & course pages, lessons, quizzes, certificates, teachers](#3-sign-in-password-profile-home--course-pages-lessons-quizzes-certificates-teachers)
4. [Live sessions, Jitsi and video playback](#4-live-sessions-jitsi-and-video-playback)
5. [Video progress, course reviews, parent dashboard](#5-video-progress-course-reviews-parent-dashboard)
6. [Payments, coupons & offers, subscriptions](#6-payments-coupons--offers-subscriptions)
7. [Wallet, lessons sold one by one, codes, teacher earnings](#7-wallet-lessons-sold-one-by-one-codes-teacher-earnings)
8. [Lesson packages (Flex) and live 1:1 lessons](#8-lesson-packages-flex-and-live-11-lessons)
9. [Standard Moodle web services the app uses](#9-standard-moodle-web-services-the-app-uses)
10. [Reference: error codes, endpoint index, changes](#10-reference)

---

## 1. Getting started

### 1.1 Base URL

Each academy is its own site, so the base URL is per academy, e.g.
`https://<slug>.academy2026.nitg-eg.com` (local development: `http://localhost:8082`).
All paths below are relative to it.

### 1.2 Tokens

| Token | How to get it | Use it for |
|---|---|---|
| **User token** | `POST /login/token.php` (`username`=email, `password`, `service=moodle_mobile_app`) → `{"token":"…","privatetoken":"…"}`, `register_student` (§3.1), or Google sign-in (`POST /local/googleauth/token.php`, §2) | Everything the signed-in user does. |
| **Shared pre-login token** | `getsettings.php` → `data.admin_token` (§2.1) | Only pre-login calls: student registration, forgot password, profile-field form, academic structure, anonymous catalogue browsing, parent dashboard. It never gives access to a user's data. |

It is the same Moodle web-service token everywhere: our endpoints accept it as `token=` (or `wstoken=`),
Moodle's REST server as `wstoken=`. Store it securely; there is no server logout — drop it locally.
**A password change (`change_password`, `reset_password`) deletes all the user's tokens → log in again.**

### 1.3 The three API styles

| Style | URL | Success | Failure |
|---|---|---|---|
| **A. Token API** (our endpoints) | `GET\|POST /local/<plugin>/api.php?function=<name>&token=<token>&…` | `{"status":"success","data":…}` | `{"status":"fail","error":"<readable — show it>","errorcode":"<stable code>"}` |
| **B. Moodle REST web services** | `GET\|POST /webservice/rest/server.php?wstoken=<token>&moodlewsrestformat=json&wsfunction=<name>&…` | the function's JSON | `{"exception":"…","errorcode":"…","message":"…"}` |
| **C. Legacy raw endpoints** (kept for the existing app) | `getsettings.php`, `design_system.php`, `getalltopics.php`, `googleauth/token.php`, `mod/jitsi/api_token.php`, `local/academy/certificate.php` | documented per endpoint | documented per endpoint |

Token API endpoints (style A) — all share one implementation (`local_academy\api\endpoint`):

| Endpoint | Area |
|---|---|
| `/local/academy/api.php` | sign-in helpers, profile, lessons, quizzes, certificates, teachers, licence |
| `/local/nit_category/api.php` | catalogue |
| `/local/nit_finance/api.php` | wallet, lessons for sale, codes, teacher earnings, withdrawals |
| `/local/nit_flex/api.php` | lesson packages (Flex): buy with the wallet or online, my packages, Flex history; admin catalogue |
| `/local/nit_lessons/api.php` | live 1:1 lessons: teachers and free slots, booking, lesson actions, joining the room, teacher profile; admin |
| `/local/payments/api.php` | payment admin: revenue, transactions, course prices, gateways |
| `/local/nit_commerce/api.php` | coupons & offers |
| `/local/nit_subscriptions/api.php` | subscriptions, enrol in free / covered courses |
| `/local/nit_reviews/api.php` | course ratings & reviews |
| `/local/nit_videoprogress/api.php` | video watch progress |
| `/local/academysessions/api.php` | live sessions, Jitsi presence |
| `/local/vimeo/api.php`, `/local/vdocipher/api.php` | video playback + teacher uploads |
| `/local/parent/api.php` | parent dashboard |

### 1.4 Rules for style A (token API)

- **HTTP status:** `401` = `authrequired` / `invalidtoken` → the token is missing or dead: **log the user
  out**. `403` = `siteunavailable` → the academy is suspended or expired: show a blocking "academy
  unavailable" screen (site admins are let through). **Everything else is HTTP 200** — check `status`.
- **Writes need POST** (`application/x-www-form-urlencoded`); a GET returns `postrequired`.
- **`lang=ar|en`** (optional, any function) — language of names and error messages. Default: the
  user's language.
- **JSON parameters** (e.g. `answers`, `fields`, `courseids`, `items`) are sent as a JSON string in a
  normal form field.
- **Paging:** `page` (0-based) + `perpage`; responses carry `total`, `page`, `perpage`.
- **Money:** amounts you send are in major units as typed (`150`, `99.5`). Responses give both
  `<name>_minor` (int, piastres/cents) and `<name>` (float, pounds) plus `currency`. Decimal fields
  always keep their fraction (`150.0`) so typed clients get a stable type; parse them as `double`/`num`.
- **Timestamps** are unix seconds (`0` = none). **Names** are already translated (bilingual
  `{mlang}` markup resolved).
- **Images/files** are returned ready to load (`/webservice/pluginfile.php/…?token=…`).
- **Dropdown values:** where a field offers `options[{value,label}]`, show `label`, send back `value`
  exactly as received (it may contain `{mlang …}` markup — that is expected).

### 1.5 Rules for style B (Moodle REST)

- Add `moodlewssettingfilter=true&moodlewssettinglang=ar` (or `en`) to every call so bilingual
  course/activity names come back translated (otherwise you get raw `{mlang ar}…{mlang}{mlang en}…{mlang}`).
- `errorcode: "invalidtoken"` → log out. `accessexception` → the token's service/role is not allowed
  (server configuration; see `local/payments/cli/ws_diagnose.php`).
- Files from core functions are `pluginfile.php` URLs: insert `/webservice` and append `?token=<token>`
  (or `&token=`).
- No-login core functions (e.g. `tool_mobile_get_public_config`) are called through `POST /lib/ajax/service-nologin.php`.

### 1.6 Common error codes (style A)

| errorcode | Meaning | App action |
|---|---|---|
| `authrequired`, `invalidtoken` (401) | no / dead token | log out → login screen |
| `siteunavailable` (403) | academy suspended / expired | blocking screen |
| `nopermissions` | the user's role may not do this | hide the action |
| `postrequired`, `missingparam`, `invalidparameter`, `unknownfunction` | app bug | fix the call |
| `internalerror` | server problem (details are logged, never shown) | generic error + retry |
| anything else | a business rule (e.g. `invalidphone`, `lessonlocked`, `insufficientwallet`) | show `error` |

A full list is in [§10.1](#101-error-codes).

### 1.7 Roles

The same endpoints serve every role; permission is decided server-side from the token's user:
**student** (any signed-in user), **teacher** (teacher / editing teacher in a course), **manager /
owner** (system manager, `local/academy:manageplatform`), **site admin**, and **parent** (no account —
phone pair, §5.3). Use `get_full_profile` → `roles` (§3.3) to decide which screens to show; a call
answering `nopermissions` also means "hide this".

---

## 2. App bootstrap, Google sign-in, catalogue, course content, AI, job forms


Base URL: `https://<academy>` (local: `http://localhost:8082`). Tokens are Moodle web-service
tokens of the `moodle_mobile_app` service. `<token>` below = the user's token; *shared token* =
the pre-login token from `getsettings.php` (`admin_token`, or `user_token`).

Two response styles appear here:

| Style | Used by | Success | Failure |
|---|---|---|---|
| **Envelope** (`local_academy\api\endpoint`) | `/local/nit_category/api.php` | `{"status":"success","data":…}` | `{"status":"fail","error":"<readable>","errorcode":"<code>"}` — HTTP 401 = dead/missing token (log out), 403 = academy locked, else 200 |
| **Moodle REST** | `/webservice/rest/server.php?wstoken=<token>&moodlewsrestformat=json&wsfunction=<fn>` | the function's object | `{"exception":"…","errorcode":"…","message":"…"}` (HTTP 200) |
| Raw (legacy, kept for the existing app) | `getsettings.php`, `design_system.php`, `getalltopics.php`, `googleauth/token.php` | see each section | see each section |

---

### 2.1 App bootstrap (before login — no token)

#### 2.1.1 `GET /local/multitopics/getsettings.php`
Anonymous, read-only, no params. Headers: `Cache-Control: no-store`, `Access-Control-Allow-Origin: *`.
`OPTIONS` → empty 200 (CORS pre-flight). No rate limit.

**Contract: every value is a JSON string or `null`** (`null` = "not configured — use the app's
built-in default"; booleans are `"1"`/`"0"`, numbers are decimal strings).

```json
{"data":{
  "user_token":null, "admin_token":null, "google_client_id":null,
  "prevent_screen_recording":"1",
  "watermark":"1", "watermark_text":"{fullname} · {email}", "watermark_color":"ffffff",
  "watermark_speed":"0.002", "watermark_fontsize":"14",
  "videourl":null, "video_source":"all",
  "whatsapp_phone":null, "whatsapp_message":null,
  "allow_paymob":"0",
  "android_version":null, "android_url":null, "ios_version":null, "ios_url":null,
  "google_login":"1", "apple_login":"0", "facebook_login":"0",
  "server_timeout_duration":"30"
}}
```

| Key | Meaning |
|---|---|
| `admin_token` | Shared pre-login token (registration, forgot-password, anonymous catalogue browsing). `user_token`: a second shared token if provisioned. Both are returned anonymously by design. |
| `google_client_id` | OAuth client id for native Google Sign-In (same for every academy). |
| `prevent_screen_recording` | Block screenshots/recording in the app. |
| `watermark*` | Video overlay. `watermark`/`watermark_text` come from `local_vdocipher` config (fallback: this plugin's). `watermark_color` is exactly 6 hex digits, **no `#`**. `{fullname}`, `{email}` placeholders are filled by the app. |
| `video_source` | `all` \| `limited` \| `youtube` \| `vimeo` \| `vdocipher` — which video hosts the app may play (licence). |
| `whatsapp_message` | Already percent-encoded for a `wa.me` URL. |
| `android_version` / `ios_version` + `_url` | Force-update gate: if the installed version is lower, send the user to the store URL. |
| `google_login` / `apple_login` / `facebook_login` | Social login buttons (licence `mobile_features()` wins over plugin config). |
| `server_timeout_duration` | Network timeout in **minutes**. |

Errors: none (always 200).
Note: `docs/mobile-app-api.md` lists keys (`app_name`, `apple_client_id`, `facebook_app_id`, `links`)
this endpoint does **not** return — legal links come from `design_system.php` → `links`.

#### 2.1.2 `GET /theme/nit/design_system.php`
Anonymous, read-only, no params. `Cache-Control: public, max-age=300`, CORS `*`. No rate limit.
Returns the white-label theme + identity:

```json
{
  "generated": 1791187769,
  "site": {"name":"…","shortname":"…","url":"http://localhost:8082","wwwroot":"http://localhost:8082",
           "apiversion":1,"supportedapp":false,"provisioned":true,"status":"active",
           "defaultlanguage":"en","languages":["en","ar"],"package":{"code":"demo","name":"Demo"}},
  "categorystyles": {"groups":[{"key":"g1","name":"Group 1","scheme":"dark"},…],
                     "categories":[{"id":1,"name":"First Year of Middle School","group":"g1","groupname":"Group 1",
                                    "class":"","isdefault":true,
                                    "modes":{"light":{"group":"g1",…},"dark":{"group":"g2","class":"nit-brand-2",…}}},…]},
  "fonts": [{"lang":"en","label":"English font","family":"NIT Site Font EN","rtl":false,"fallback":"…",
             "hasfont":false,"filename":"","url":""}, {"lang":"ar",…}],
  "components": [{"name":"Buttons","variants":[{"label":"Primary","class":"btn btn-primary"},…]},…],
  "brandcolors": {"roles":["accent","primary","onprimary",…],"schemes":…,"defaultscheme":…,
                  "groups":[{"key":"g1","name":"Group 1","scheme":"dark","isdefault":true,"class":"",
                             "roles":[{"key":"g1_accent","role":"accent","section":"brand","label":"Accent (highlight)",
                                       "cssvar":"--nit-brand-accent","value":"#5488c4","default":"#5488c4",
                                       "iscustom":false,"usage":["…"]},…]},…]},
  "branding": {"logo":{"light":"…/pluginfile.php/1/core_admin/logo/0x200/…/logo2.png","dark":"…"},
               "logomark":{"light":"…","dark":"…"}},
  "links": {"privacy":{"en":"…/local/multitopics/legal.php?doc=privacy&embedded=1&lang=en","ar":"…"},
            "terms":{"en":"…","ar":"…"},"delete_account":{"en":"…","ar":"…"},
            "support_email":"support@example.com"}
}
```

- `site.supportedapp:false` ⇒ the app must refuse this academy (demo/unsupported tier).
  `site.provisioned:false` ⇒ the academy is not set up yet (it defaults to `true` when not configured).
  `site.status`: `active` | `expired` | `suspended` (from the licence) — anything but `active` ⇒ show the
  "academy unavailable" screen. `site.expires` (unix) and `site.features`
  (authoritative feature map, a missing key = OFF) appear only when the licence plugin enforces.
- `branding` is present only when something is published; each key optional (`logo`, `logomark`,
  `loginhero` as `{light,dark}`; `splash` {lottie_light…background_dark}; `courseplaceholder`; `emptystate`).
  URLs are public pluginfile URLs (no token).
- `links`: `privacy`/`terms`/`delete_account` always present (default to `legal.php` below);
  `about`, `faq` (`{en,ar}`), `support_email`, `support_phone`, `facebook`, `instagram`, `youtube`,
  `tiktok`, `website` only when set.
- `components` and `generated` are web metadata — ignore.
- Errors: none expected (always 200).

#### 2.1.3 `GET /local/multitopics/legal.php`
Anonymous. Opened in a webview.

| Param | Type | Req | Description |
|---|---|---|---|
| `doc` | string | no | `terms` (default) \| `privacy` \| `delete` (`delete-account` accepted). Unknown → `terms`. |
| `lang` | string | no | `ar` \| `en`; anything else → current language. |
| `embedded` | `1` | no | **The app always sends `embedded=1`**: bare, chrome-less HTML (`dir=rtl` for Arabic, light/dark aware), session-less, `Cache-Control: public, max-age=300`. Without it the themed web page is shown. |

Response: `text/html`. Content = admin override (`local_multitopics/{doc}_{lang}`, raw HTML) or the
built-in bilingual default naming the app (`app_name`), developer (`app_developer`), website and the
support email. Errors: none (always 200).

---

### 2.2 Google sign-in

#### `POST /local/googleauth/token.php`
No Moodle token. Exchanges a Google **ID token** (native Google Sign-In) for a Moodle web-service token.

| Param | Type | Req | Description |
|---|---|---|---|
| `idtoken` (or `id_token`) | string | yes | Google ID token (JWT). Form-encoded POST field, or a JSON body `{"idtoken":"…","service":"…"}`. |
| `service` | string | no | External service shortname; default `moodle_mobile_app`. |

Success (200):
```json
{"token":"<token>","privatetoken":"<privatetoken or null>","userid":12}
```
`privatetoken` is returned only over HTTPS and only for non-admins (same as `/login/token.php`);
it is needed for `tool_mobile_get_autologin_key` (webview auto-login).

Flow: Google Sign-In in the app (request the `profile` + `email` scopes so the avatar is imported)
→ POST the ID token here → store `token` → continue as after a password login.
Server side: verifies with Google `tokeninfo`, checks `iss`, `aud` ∈ configured client ids, `exp`,
`email_verified`; maps by email (must be unique); optionally auto-creates the account
(`allowcreate`, auth `newuserauth`); only links accounts whose auth is in `allowedlinkauth`
(default `oauth2`) so a Google login cannot take over a manual-password account; imports the Google
avatar if the user has no picture.

Errors — body `{"error":"<code>"}`:

| HTTP | `error` | App action |
|---|---|---|
| 405 | `method_not_allowed` | Use POST. (`OPTIONS` → 204 pre-flight.) |
| 503 | `webservices_disabled`, `plugin_disabled`, `no_clientids_configured` | Google login not available on this academy — hide the button / show a message. |
| 503 | `site_maintenance` | Maintenance message. |
| 429 | `rate_limited` | Too many attempts; honour the `Retry-After` header (seconds). |
| 400 | `idtoken_missing` | Bug in the app. |
| 401 | `invalid_idtoken`, `invalid_issuer`, `token_expired`, `email_not_verified` | Sign out of Google and retry. |
| 401 | `invalid_audience` (+ `received_aud`) | App's Google client id is not whitelisted on the server (`local_googleauth/clientids`). |
| 403 | `domain_not_allowed`, `auth_not_linkable`, `guest_not_allowed`, `user_suspended`, `user_not_confirmed` | Show "this account can't sign in with Google" (for `auth_not_linkable`: use the password login). |
| 403 | `site_unavailable` | The academy is suspended / expired → "academy unavailable" screen. |
| 404 | `user_not_found` | No account and auto-create is off → send to registration. |
| 404 | `service_not_available` | Wrong `service`. |
| 409 | `email_not_unique` | Contact support. |

Rate limit: per client IP, `local_googleauth/ratelimit` requests per minute (default **20**, `0` = off),
counted on every request that passes the method/enabled checks (failures count too).

---

### 2.3 Catalogue — `/local/nit_category/api.php`

`GET /local/nit_category/api.php?function=<name>&token=<token>[&lang=ar|en]` (envelope style).

**Auth:** any valid token. With the **shared pre-login token** the caller browses as a guest
(`"anonymous": true`): hidden categories / hidden courses are never listed and `enrolled` /
`covered` are always `false`; prices are resolved by the caller's IP country. With a user token
the user's own visibility, enrolment and subscription cover are used.

The same filtering logic backs the web page `/local/nit_category/index.php`
(class `local_nit_category\catalogue`), so app and web always agree.

#### 2.3.1 `get_categories`
No params (except `lang`). The category tree with recursive course counts.

```json
{"status":"success","data":{"anonymous":false,"categories":[
  {"id":6,"name":"Third Year of Secondary School","description":"","parent":0,"depth":1,"coursecount":5,"children":[]},
  {"id":5,"name":"Second Year of Secondary School","description":"","parent":0,"depth":1,"coursecount":3,"children":[]}
]}}
```
`description` is plain text. `children` has the same shape (any depth).

#### 2.3.2 `get_courses`

| Param | Type | Req | Description |
|---|---|---|---|
| `categoryid` (alias `id`) | int | no | Category; `0` (default) = whole catalogue. |
| `recursive` | 0/1 | no | Include subcategories (default `1`). |
| `q` | string | no | Case-insensitive search in course names (both languages) and summary. |
| `sort` | string | no | `recommended` (default, site order) \| `newest` \| `az` (by the stored name, like the web page). |
| `price` | string | no | `all` (default) \| `free` \| `paid` (paid = has an active local_payments price). |
| `level` | string | no | A value from `filters.levels[].value` (course custom field `level`; empty list when the field does not exist). |
| `rating` | int | no | Minimum average stars 1–5 (local_nit_reviews); `0` = all. |
| `page` | int | no | 0-based (default 0). |
| `perpage` | int | no | Default 20, max 50. |

Unknown filter values silently fall back to the default (same as the web page).

```json
{"status":"success","data":{
  "anonymous":false,"total":5,"page":0,"perpage":2,
  "filters":{"sort":"recommended","price":"all","level":"","rating":0,"levels":[],
             "pricingenabled":true,"ratingenabled":true},
  "courses":[{
    "id":7,"fullname":"English Language — Third Secondary","shortname":"demo-en-sec3",
    "summary":"Grammar, vocabulary and the novel, with exam-style practice for every unit.",
    "categoryid":6,"categoryname":"Third Year of Secondary School",
    "image":"http://localhost:8082/webservice/pluginfile.php/61/course/overviewfiles/cover.svg?token=<token>",
    "teachers":[{"id":5,"fullname":"Admin User"},{"id":7,"fullname":"منى عادل"}],
    "rating":{"avg":0.0,"count":0},"level":null,"timecreated":1791124075,
    "enrolled":false,"covered":false,"free":false,"haspricing":true,
    "price":160.0,"price_minor":16000,"currency":"EGP",
    "offerlabel":"","offerfinal":0.0,"offerfinal_minor":0
  }]
}}
```

Card logic (same as the web card):
1. `enrolled` → "Enrolled" + open the course.
2. else `covered` (active subscription covers it) → "In your subscription" + enrol button.
3. else `offerlabel != ""` and `offerfinal > 0` → strike `price`, show `offerfinal` + badge `offerlabel` (e.g. `-40%`).
4. else `haspricing` → `price` `currency` + Buy.
5. else → Free + enrol.

`image` is `""` when the course has no overview image (use the placeholder from `design_system.php`
→ `branding.courseplaceholder`). `level` is `null` when unset. Money: `price`/`offerfinal` major
units (float), `*_minor` integer minor units.

Errors:

| errorcode | When | App action |
|---|---|---|
| `authrequired` / `invalidtoken` (HTTP 401) | no / dead token | Re-fetch the shared token or log out. |
| `siteunavailable` (HTTP 403) | academy suspended / expired | Show the "academy unavailable" screen. |
| `categorynotfound` | unknown, or hidden from this caller | Go back to the category list. |
| `unknownfunction` | typo in `function` | — |
| `internalerror` | server error | Retry later. |

---

### 2.4 Course content — `GET /local/multitopics/getalltopics.php`

The app's main course screen. **Response shape unchanged** (existing app); new fields added.

| Param | Type | Req | Description |
|---|---|---|---|
| `courseid` | int | yes | Course id. |
| `wstoken` (or `token`) | string | yes | User token. |

Auth: token validated by the shared `local_academy\token_auth` (expiry, IP restriction, enabled
service, live/confirmed/non-suspended account). The user must be enrolled or have `moodle/course:view`.

```json
{
  "courseid":23,"fullname":"First Term Bundle (…)","shortname":"nit_parent_demo_2","format":"topics",
  "isavailable":true,"status":"available","other_fields":{},
  "parents":[{"id":"45","sectionnum":2,"name":"Unit 2 — Reading","parent":true,
    "activities":[{
      "id":"62","modname":"vimeo","name":"Reading skills","sectionnum":"2",
      "visible":true,"uservisible":true,
      "url":"http://localhost:8082/mod/vimeo/view.php?id=62","tags":[],
      "modicon":"http://localhost:8082/theme/image.php/nit/vimeo/icon",
      "resourcetype":"video/vimeo","mediatype":"vimeo","fileurl":"",
      "isvdocipher":false,"videoid":"76979871","otpurl":"",
      "isvimeo":true,"embedurl":"http://localhost:8082/local/vimeo/api.php?function=get_playback&cmid=62&token=<token>",
      "iscertificate":false,"restricted":false,"restrictioninfo":"",
      "locked":false,"forsale":false,"completed":false,"completiontracking":"none","watched_percent":null
    }],
    "topics":[{"id":"…","sectionnum":3,"name":"…","activities":[…]}]
  }]
}
```

- `status`: `available` | `not_started` | `ended` | `hidden`; `isavailable` false for `not_started`/`hidden`.
- `parents[]` = top-level sections (multitopics format nests `topics[]`; other formats have empty `topics`).
  Section 0 and sections the user cannot see are not returned.
- Per-module extras (unchanged): `resource`/`resource2`/`testnew` → `fileurl` (tokenized) + `mediatype`
  (`video|audio|image|pdf|document|file`); `vdocipher`/`resource2` with VdoCipher → `isvdocipher`, `videoid`,
  `otpurl` (GET right before playing); `vimeo` → `isvimeo`, `embedurl`; `assign` → `intro`, dates,
  `grade`, `introattachments[]`, `submissiontypes[]`, `submissionstatus`, `submissionwindow`;
  `quiz` → `nativerender {requires[], forcewebview, fallbackurl}`; `url` → `fileurl` (external URL);
  `customcert` → `iscertificate:true`, `downloadurl` (= `fileurl`).

New / changed per activity:

| Field | Meaning |
|---|---|
| `restricted` | **Now real.** `true` = Moodle shows the activity greyed-out ("Not available unless …") and the user cannot open it. Show it disabled with `restrictioninfo`. Activities whose restriction is set to "hide entirely" are still not returned. Staff who bypass restrictions get `false` (but still see `restrictioninfo`). |
| `restrictioninfo` | Plain-text reason, e.g. `Available from 1 January 2030`. `""` when available. |
| `locked` | Lesson-order lock (admin option "lock lessons in order"): an earlier tracked lesson is not complete. Show a lock; tell the user to finish the previous lessons. Staff are never locked. |
| `forsale` | Lesson sold separately (local_nit_finance) and not bought yet → show "Buy". |
| `completed` | Completion state is complete / complete-pass. |
| `completiontracking` | `none` \| `manual` (show a "mark as done" control) \| `auto`. |
| `watched_percent` | Video lessons: % watched (int), `null` when never started / not a tracked video. |

When `restricted`, `locked` or `forsale` is true, `fileurl`, `otpurl`, `embedurl` (and `downloadurl`,
`introattachments[].fileurl`) are `""` — never try to play/download such an item.

Errors — body `{"exception":"moodle_exception","errorcode":…,"message":…}`:

| HTTP | errorcode | App action |
|---|---|---|
| 401 | `invalidtoken` (`Token is required` / `Invalid token`) | Log out. |
| 403 | `siteunavailable` | Academy suspended / expired → "academy unavailable" screen (site admins are let through). |
| 400 | `invalidparameter` | `courseid` missing. |
| 404 | `invalidcourseid` | Course deleted → back to My courses. |
| 403 | `nopermissions` | Not enrolled → show the course page / buy flow. |

---

### 2.5 AI assistant (video lessons)

Moodle REST, `moodle_mobile_app` service, user token.
**Only VdoCipher video lessons (`modname = vdocipher`) are supported by the assistant today.**

#### 2.5.1 `local_nit_ai_get_status` (new)
Call when opening a video lesson to decide whether to show the "Ask AI" button.

| Param | Type | Req | Description |
|---|---|---|---|
| `cmid` | int | yes | Course module id of the lesson. |

```json
{"cmid":2,"available":false,"reason":"unsupported",
 "message":"The assistant only works on video lessons (VdoCipher) for now.",
 "placement":"aiplacement_nit_videoassist","provider":"","hastimestamps":false}
```
When available the shape is the same with `available:true`, `reason:""`, `message:""`,
`provider:"vdocipher"` (illustrative only — the local site has no VdoCipher lesson or AI provider,
so this case could not be captured).

- `available` → show the button. Otherwise hide it (the `message` is for debugging/teachers).
- `reason`: `unsupported` (not a VdoCipher lesson), `nopermission` (no `local/nit_ai:use`),
  `notentitled`, `notranscript` (teacher has not uploaded a transcript), `disabled`, `notapproved`,
  `stale` (video replaced after the transcript was approved), `placementdisabled` (site AI placement off),
  `noprovider` (no AI provider configured), `disabledincontext` (AI tools off for the course/activity).
- `hastimestamps` → answers contain `[MM:SS]` citations the player can seek to.
- Errors: `invalidrecord` (bad cmid), `requireloginerror` (no access to the course/activity).

#### 2.5.2 `local_nit_ai_ask` (existing)
| Param | Type | Req | Description |
|---|---|---|---|
| `cmid` | int | yes | Lesson cmid. |
| `question` | string | yes | Max 1000 chars (longer is cut). |
| `currenttime` | int | no | Playback position (s), `-1` = unknown. |
| `history[]` | `{role:"user"\|"assistant", text}` | no | Earlier turns (kept by the app only). |

Response: `{"success":true,"answer":"… [03:15] …","error":""}`; on refusal `success:false` with a
readable `error` (`err_unavailable`, `err_emptyquestion`, `err_aifailed`). Same gates as get_status.

Flow: open lesson → `get_status` → if `available`, show button → `ask` per question, sending the
running `history` and the player position.

---

### 2.6 Job forms (mod_jobform)

Moodle REST, `moodle_mobile_app` service. Errors are Moodle exceptions: `errorinvalidcmid` (cmid is
not a Job Form), `nopermissions` (missing capability), `requireloginerror` (no course access).

#### Student (existing)
| Function | Params | Returns |
|---|---|---|
| `mod_jobform_get_jobforms_by_courses` | `courseids[]` (empty = my courses), `lang` | `{jobforms:[{id,coursemodule,course,name,intro,introformat,certid,allowresubmit}],warnings}` |
| `mod_jobform_view_jobform` | `cmid` | `{status,warnings}` — log view + completion |
| `mod_jobform_get_form` | `cmid`, `lang` | `{jobform, access{cansubmit,certrequired,locked}, groups[], fields[{id,name,type,required,groupid,sortorder,multiple,fixedvalue,options[{value,label}]}], submission{status,timemodified,answers[{fieldid,value}]}}` |
| `mod_jobform_submit_form` | `cmid`, `answers[{fieldid,value}]`, `draft` | `{status,submissionid,errors[{fieldid,message}],warnings}`; exceptions `certificaterequired`, `alreadysubmitted` |

#### Teacher (new) — capability `mod/jobform:viewsubmissions` (teacher, editing teacher, manager)

##### `mod_jobform_get_submissions`
| Param | Type | Req | Description |
|---|---|---|---|
| `cmid` | int | yes | Job Form course module id. |
| `page` | int | no | 0-based (default 0). |
| `perpage` | int | no | 1–100 (default 20). |
| `status` | string | no | `submitted` (default — same list as the web Submissions tab) \| `draft` \| `all`. |

```json
{"jobform":{"id":2,"cmid":67,"name":"ZZ API test jobform"},
 "total":1,"page":0,"perpage":20,"candelete":1,
 "submissions":[{"id":3,"user":{"id":12,"fullname":"طالب تجريبي",
   "profileimageurl":"http://localhost:8082/theme/image.php/nit/core/1791188168/u/f1"},
   "status":"submitted","timecreated":1791188756,"timemodified":1791188756}],
 "warnings":[]}
```
Newest first. `profileimageurl` is the standard Moodle avatar URL (pluginfile URLs need the token appended, as for core functions).

##### `mod_jobform_get_submission`
| Param | Type | Req | Description |
|---|---|---|---|
| `submissionid` | int | yes | From `get_submissions`. |
| `lang` | string | no | Language for labels/values (`ar`/`en`). |

```json
{"submission":{"id":3,"cmid":67,"jobformid":2,"status":"submitted","timecreated":1791188756,
   "timemodified":1791188756,"user":{"id":12,"fullname":"طالب تجريبي","profileimageurl":"…"}},
 "groups":[],
 "answers":[
   {"fieldid":1,"name":"Full name","type":"text","groupid":0,"value":"Test Student","display":"Test Student"},
   {"fieldid":2,"name":"Birth date","type":"date","groupid":0,"value":"946684800","display":"1 January 2000"},
   {"fieldid":3,"name":"Has car","type":"checkbox","groupid":0,"value":"1","display":"Yes"},
   {"fieldid":4,"name":"City","type":"select","groupid":0,"value":"{mlang en}Cairo{mlang}{mlang ar}القاهرة{mlang}","display":"Cairo"}],
 "warnings":[]}
```
One row per **current** field of the activity, in field order (same as the web "View" page); show
`name` + `display`; `value` is the raw stored value. Group rows by `groupid` using `groups`.
Error `errorsubmissionnotfound` → the submission was deleted; refresh the list.

##### `mod_jobform_delete_submission`
| Param | Type | Req | Description |
|---|---|---|---|
| `submissionid` | int | yes | Submission to delete permanently (with its answers). |

Returns `{"status":true,"warnings":[]}`. Same rule as the web Submissions tab: `mod/jobform:viewsubmissions`
on the activity (a non-editing teacher may delete). Ask the user to confirm first.
Errors: `errorsubmissionnotfound`, `nopermissions`.


---

## 3. Sign-in, password, profile, home & course pages, lessons, quizzes, certificates, teachers

Endpoint (style A): `GET|POST /local/academy/api.php?function=<name>&token=<token>[&lang=ar|en]`

### 3.1 Login, registration, logout

**Login** (style C, Moodle core) — students sign in with their **email** and password:
```
POST /login/token.php
username=<email>&password=<password>&service=moodle_mobile_app
→ {"token":"<token>","privatetoken":"<privatetoken>"}
→ {"error":"Invalid login, please try again","errorcode":"invalidlogin"}
```
`errorcode` values worth handling: `invalidlogin`, `suspended` / `forcepasswordchangenotice`
(show the message), `sitemaintenance`. Google: see §2.2.

#### Student registration (the 3-step form) — pre-login, **shared token**

The same form and rules as the website page `/local/academy/register.php`. The account is **active
at once** (no approval, no email confirmation) and the call returns the new student's token, so the
app goes straight into the account. The student later signs in with the email + password.

##### `get_registration_form` (GET)
Fields in form order with their step (1–3), type and dropdown options, the password rules and the
terms page:
```json
{"status":"success","data":{"enabled":true,
 "fields":[
  {"name":"firstname","label":"الاسم الأول","step":1,"type":"text","required":true,"options":[]},
  {"name":"phone","label":"رقم الهاتف","step":1,"type":"phone","required":true,"options":[]},
  {"name":"grade","label":"الصف الدراسي","step":1,"type":"menu","required":true,"options":[
     {"value":"{mlang en}First Year of Middle School{mlang}{mlang ar}الصف الاول الاعدادى{mlang}",
      "label":"الصف الاول الاعدادى","categoryid":1}, "…"]},
  {"name":"national","label":"رقم الطالب القومي","step":1,"type":"nationalid","required":true,"options":[]},
  {"name":"studysystem","label":"النظام الدراسي","step":2,"type":"menu","options":[
     {"value":"{mlang en}general{mlang}{mlang ar}عام{mlang}","label":"عام"}, "…"]},
  {"name":"division","label":"الشعبة الدراسية","step":2,"type":"menu","options":[
     {"value":"{mlang ar}ادبى{mlang}{mlang en}Literary{mlang}","label":"ادبى",
      "systems":["{mlang en}general{mlang}{mlang ar}عام{mlang}","{mlang ar}أزهر{mlang}{mlang en}Azhar{mlang}"]}, "…"]},
  {"name":"email","label":"البريد الإلكتروني","step":3,"type":"email","required":true,"options":[]},
  {"name":"password","label":"كلمة السر","step":3,"type":"password","required":true,"options":[]}],
 "passwordpolicy":"يجب أن تتضمن كلمة المرور على الأقل 8 من الأحرف, على الأقل 1 من الأرقام, …",
 "termsurl":"http://…/local/multitopics/legal.php?doc=terms&embedded=1&lang=ar"}}
```
- The 18 fields: step 1 `firstname`, `secondname`, `thirdname`, `lastname`, `phone`, `grade`,
  `national`; step 2 `fatherphone`, `motherphone`, `school`, `guardianjob`, `studysystem`,
  `governorate`, `division`; step 3 `religion`, `gender`, `email`, `password`. **All are required.**
- `type`: `text`, `phone`, `nationalid` (14 digits), `email`, `password`, `menu`. For `menu` show
  `label`, send `value`. Divisions: show only those whose `systems` contains the chosen study system.
- `enabled:false` → registration is closed on this academy (admin: Site administration → Plugins → Local plugins → Academy → "Allow students to create an account"; independent of Moodle's "Self registration"): hide the "create account" button.
- Show `passwordpolicy` under the password field; open `termsurl` in a webview for the terms checkbox.
- Confirm-password is checked by the app (it is not sent).

##### `register_student` (POST)
| Param | Type | Req | Description |
|---|---|---|---|
| the 18 field names above | string | yes | menu fields: the option `value`; digits may be typed in Arabic (`٠١٠…`) |
| `agree` | 1 | yes | the student accepted the terms |

Success — the student is created, signed in, and this is **their own token** (store it and replace the
shared token for every next call):
```json
{"status":"success","data":{"userid":23,"token":"<new user token>","privatetoken":null,
 "profile":{"userid":23,"username":"reg.test1@example.com","fullname":"تجربة تسجيل",
            "email":"reg.test1@example.com","auth":"email","phone":"01011112222","…":"same as get_full_profile"}}}
```
Validation failure — one message per field (show each under its field and go back to the step of the
first one):
```json
{"status":"fail","error":"برجاء تصحيح الحقول المحددة.","errorcode":"invalidregistration",
 "errors":{"school":"هذا الحقل مطلوب","phone":"يرجى إدخال رقم هاتف صحيح (مثال: 01012345678).",
           "national":"الرقم القومي 14 رقم.","division":"هذه الشعبة لا تتبع النظام الدراسي المختار.",
           "email":"يوجد حساب بهذا البريد الإلكتروني بالفعل. سجّل الدخول.",
           "password":"يجب أن تتكون كلمة المرور على الأقل من 8 من الأحرف. …",
           "agree":"لازم توافق على الشروط والأحكام"}}
```
Other errors: `registrationdisabled` (closed), `toomanyregistrations` (more than 10 accounts from the
same network in an hour — try later), `postrequired`.

Rules (same on web and app): phones 7–25 chars of digits/`+`/spaces/`-`/`()`; national ID exactly 14
digits; dropdowns accept only their options; the division must belong to the study system; the email
must be valid and not used by another account (saved in lower case); the password must meet
`passwordpolicy`. Every answer is saved in the student's profile fields (see `get_full_profile`); the
student can edit them later with `update_my_profile` (§3.3).

**Logout:** delete the stored token on the device (optionally unregister the push device, §9).

### 3.2 Forgot password (OTP) and change password

Pre-login calls use the **shared token**; `change_password` uses the user's own token. All are POST.

| function | Params | Success `data` | Errors |
|---|---|---|---|
| `request_password_otp` | `email` | `{"sent":true,"expiresin":600}` — **always** generic success (never reveals if the email exists) | `invalidemail`, `toomanyrequests` (3 per 15 min) |
| `verify_password_otp` | `email`, `otp` (6 digits) | `{"resettoken":"…","expiresin":<seconds>}` | `otpinvalid`, `otpexpired` (10 min), `otplocked` (5 wrong tries) |
| `reset_password` | `resettoken`, `newpassword` | `{"reset":true}` → log in with the new password | `resetexpired`, `weakpassword` (message lists the policy) |
| `change_password` | `currentpassword`, `newpassword` | `{"changed":true}` → **all tokens are revoked: log in again** | `wrongpassword`, `weakpassword`, `authnochange` (Google account — hide the screen; see `canchangepassword`) |

Flow: email → `request_password_otp` → OTP screen → `verify_password_otp` → new password →
`reset_password` → login.

### 3.3 Profile

#### `get_my_profile` (GET) — short profile (legacy shape, unchanged)
```json
{"status":"success","data":{"userid":12,"username":"nit_test_buyer","fullname":"طالب تجريبي",
 "firstname":"طالب","lastname":"تجريبي","email":"nit_test_buyer@example.com","auth":"manual",
 "isgoogle":false,"canchangepassword":true,
 "profileimageurl":"http://localhost:8082/theme/image.php/nit/core/1791187430/u/f1",
 "profileimageurlsmall":"http://localhost:8082/theme/image.php/nit/core/1791187430/u/f2"}}
```

#### `get_full_profile` (GET) — everything the profile screen needs
The short profile plus phone, bio, language, roles and every academy profile field with its current
value (as a ready-to-render form):
```json
{"status":"success","data":{"userid":12, "…": "same fields as get_my_profile",
 "phone":"01000000099","phone2":"","city":"","country":"","bio":"","language":"ar",
 "timecreated":1791129673,"lastaccess":1791183783,"hasphoto":false,
 "roles":{"is_siteadmin":false,"is_manager":false,"is_teacher":false},
 "fields":[
  {"shortname":"secondname","name":"Second name","group":"personal","groupname":"Personal details",
   "type":"text","value":"","valuelabel":"","options":[]},
  {"shortname":"gender","name":"Gender","group":"personal","groupname":"Personal details","type":"menu",
   "value":"","valuelabel":"","options":[
     {"value":"{mlang ar}ذكر{mlang}{mlang en}Male{mlang}","label":"Male"},
     {"value":"{mlang ar}أنثى{mlang}{mlang en}Female{mlang}","label":"Female"}]},
  {"shortname":"division","name":"Division","group":"study","groupname":"Study details","type":"menu",
   "value":"","valuelabel":"","options":[
     {"value":"{mlang ar}ادبى{mlang}{mlang en}Literary{mlang}","label":"Literary",
      "systems":["{mlang en}general{mlang}{mlang ar}عام{mlang}","{mlang ar}أزهر{mlang}{mlang en}Azhar{mlang}"]}]}],
 "languages":[{"code":"ar","name":"عربي ‎(ar)‎"},{"code":"en","name":"English ‎(en)‎"}]}}
```
- `fields[].group`: `personal` (second/third name, national ID, gender, religious education,
  governorate, school), `study` (year, study system, division), `guardian` (father / mother / guardian
  phone, guardian job), `teacher` (teacher title — read-only for the user).
- `type`: `text` or `menu`. For `menu` show `options[].label` and send `options[].value`.
- **Linked dropdowns:** a division's `systems` lists the study-system values it belongs to → only show
  divisions whose `systems` contains the chosen study system.
- `roles`: decide which screens to show (teacher earnings, admin tools…).

#### `update_my_profile` (POST)
Send only what changes.

| Param | Type | Description |
|---|---|---|
| `firstname`, `lastname` | string | cannot be empty |
| `phone` | string | e.g. `01012345678` (7–25 chars of digits, `+`, spaces, `-`, `()`) |
| `bio` | string | plain text |
| `language` | string | a `languages[].code` |
| `fields` | JSON object | `{"school":"…","studysystem":"<value>","division":"<value>","fatherphone":"…"}` — keys are `fields[].shortname`; `""` clears a field |

Returns the same data as `get_full_profile`. Rules: dropdowns accept only their own option values;
the division must belong to the study system (changing the system clears a division that no longer
fits); phones and the 14-digit national ID are validated; `teachertitle` is ignored (staff-managed).
Errors: `requiredfield`, `invalidphone`, `invalidnationalid`, `invalidoption`, `divisionmismatch`,
`invalidlanguage`, `invalidparameter` (bad JSON in `fields`).

**Email** is changed on the website only (it needs a confirmation step). **Avatar:** upload the image
to `POST /webservice/upload.php` (`token`, `filearea=draft`, file) → take `itemid` → call Moodle
`core_user_update_picture` (`draftitemid=<itemid>`; `delete=1` removes it) — style B.

#### `get_profile_fields` (GET) — form spec only
Same `fields[]` as above, without values. Works with the **shared token** (to build forms before login).

#### `get_academic_structure` (GET) — years, study systems, divisions
Works with the shared token.
```json
{"status":"success","data":{
 "years":[{"id":1,"value":"{mlang en}First Year of Middle School{mlang}{mlang ar}الصف الاول الاعدادى{mlang}",
           "name":"First Year of Middle School","path":[1]}, "…"],
 "systems":[{"value":"{mlang en}general{mlang}{mlang ar}عام{mlang}","name":"general",
             "divisions":[{"value":"{mlang ar}ادبى{mlang}{mlang en}Literary{mlang}","name":"Literary"}, "…"]}]}}
```
`years[]` are the course categories (`id` = category id for the catalogue, `value` = what the user's
`year` field stores). `systems[].divisions` are the divisions of each system.

### 3.4 Course lessons (player)

#### `get_course_lessons` (GET) — `courseid`
The course's lessons in order with the learner's state — the same rules the web player applies
(lesson-order lock, lessons sold one by one, watched %, completion) and where to resume.
```json
{"status":"success","data":{"courseid":4,"fullname":"Physics — Third Secondary",
 "lockorder":false,"markcomplete":true,"resume_cmid":2,
 "progress":{"tracked":0,"completed":0,"percent":0},
 "sections":[{"name":"New section","lessons":[
   {"cmid":2,"instance":1,"name":"Lesson 1: Ohm's law","modname":"page","typename":"Page","video":false,
    "tracked":false,"manual":false,"completed":false,"locked":false,"forsale":false,
    "watched_percent":null,"weburl":"http://localhost:8082/mod/page/view.php?id=2"},
   {"cmid":3,"…":"…","forsale":true},
   {"cmid":34,"instance":1,"name":"درس فيديو تجريبي","modname":"vimeo","video":true,"…":"…","watched_percent":42}]}]}}
```
- `locked` → show a padlock ("finish the previous lessons"); `forsale` → show the price / buy
  (§7 `get_lesson_access`, `buy_lesson`); `manual` → show "mark as complete" (Moodle
  `core_completion_update_activity_completion_status_manually`, §9) when `markcomplete` is true.
- `resume_cmid` = first unfinished lesson ("Continue" button). `video` lessons play through §4.3/§4.4.
- Errors: `coursenotfound`, `notenrolled`.

`getalltopics.php` (§2.4) returns the same flags inside the full course content; use whichever fits the
screen.

#### `log_lesson_view` (POST) — `cmid`
Call when a lesson is opened (for videos: when playback starts). Logs the view and marks view-based
completion, like the web page.
```json
{"status":"success","data":{"cmid":2,"viewed":true,"completed":false}}
```
Errors: `lessonlocked`, `lessonforsale`, `notenrolled`, `nopermissions`, `invalidcoursemodule`.
(Moodle's own `mod_<x>_view_<x>` functions — e.g. `mod_page_view_page`, `mod_resource_view_resource` —
also work for core activity types.)

### 3.5 Enrolment helpers

| function | Method | Params | `data` | Errors |
|---|---|---|---|---|
| `is_course_free` | GET | `courseid` | `{"courseid":4,"is_free":false,"price":200.0,"currency":"EGP"}` | — |
| `enrol_free_course` | POST | `courseid` | `{"courseid":2,"enrolled":true}` | `coursenotfound`, `coursenotfree`, `enrolfailed` |

Prefer `local/nit_subscriptions/api.php?function=enrol_course` (§6.3.3): it also enrols into courses
covered by the user's subscription.

### 3.6 Quizzes (app-rendered)

For multiple-choice / true-false quizzes rendered natively. (Moodle's `mod_quiz_*` web services can be
used instead; `getalltopics` → `nativerender` tells you when a quiz needs a webview.)

| function | Method | Params | `data` |
|---|---|---|---|
| `get_quizzes` | GET | `courseid` (opt) | `[{"quizid":4,"cmid":57,"courseid":22,"name":"…","intro":"…","timelimit":0,"attempts_allowed":0}]` (my courses; admins: all) |
| `get_quiz` | GET | `cmid` | quiz + `questions[]` (below) |
| `start_quiz_attempt` | POST | `quizid` | `{"attemptid":9,"quizid":4,"attempt_number":1,"timestart":…,"timelimit":0,"state":"inprogress"}` |
| `save_quiz_answer` | POST | `attemptid`, `questionid`, `answer` (`3` or JSON `[3,5]`) | `{"attemptid":9,"questionid":12,"saved":true,"state":"inprogress"}` |
| `finish_quiz_attempt` | POST | `attemptid` | result (below) — grades the saved answers |
| `submit_quiz_attempt` | POST | `attemptid`, `answers` JSON `[{"questionid":12,"answer":3},{"questionid":13,"answer":[5,6]}]` | result — one-shot save + finish |
| `get_quiz_attempt` | GET | `attemptid` | review: `{attemptid, quizid, quiz_name, attempt_number, state, timestart, timefinish, score, max_score, percent, questions[]}` |
| `get_my_quiz_attempts` | GET | `quizid` | `[{"attemptid":9,"attempt_number":1,"state":"finished","score":2.0,"max_score":3.0,"percent":66.7,"timestart":…,"timefinish":…}]` |

Question shape (`get_quiz.questions[]`):
```json
{"slot":1,"questionid":12,"type":"multichoice","text":"…","images":["<url>"],"defaultmark":1.0,
 "supported":true,"single":true,
 "options":[{"id":31,"text":"…","images":[]},{"id":32,"text":"…","images":[]}]}
```
- `answer` = the chosen option `id` (or an array of ids when `single` is false). True/false has two options.
- `supported:false` → question type not rendered natively → open the quiz in a webview.
- Correct answers (`correct:true`) are shown only to managers.
- Image URLs (`images[]`) are served by `/local/academy/qfile.php?token=…` and load directly.
- Result (`finish_quiz_attempt` / `submit_quiz_attempt`):
  `{"attemptid":9,"state":"finished","score":2.0,"max_score":3.0,"percent":66.7,"results":[{"questionid":12,"type":"multichoice","mark":1.0,"max_mark":1.0,"correct":true}]}`
- Errors: `notenrolled`, Moodle quiz codes such as `attemptalreadyclosed`, `notyourattempt`,
  `attemptsexhausted`, `invalidquestionid` (show `error`).

### 3.7 Certificates

#### `get_my_certificates` (GET)
Certificates (mod_customcert) in my courses:
```json
{"status":"success","data":[{"cmid":70,"name":"Course certificate","courseid":4,"coursename":"…",
  "issued":true,"code":"AB12CD34","timeissued":1791190000,
  "downloadurl":"http://…/local/academy/certificate.php?cmid=70&token=<token>"}]}
```
`issued:false` = not earned/opened yet (the course's completion rules may still lock it).

#### PDF download (style C) — `GET /local/academy/certificate.php?cmid=<cmid>&token=<token>`
Streams the PDF (issues the certificate on first download, like the web). Errors are JSON
`{"error":"<code>"}`: 401 `invalidtoken`, 403 `notenrolled`, 404 `invalidcmid` / `not_a_certificate`,
501 `customcert_not_installed`.

### 3.8 Teachers directory

| function | Who | Params | `data` |
|---|---|---|---|
| `browse_teachers` | anyone (shared token OK) | `subject` (ignored, kept for compatibility) | `[{"userid":4,"fullname":"…","phone":"","headline":"","bio":"…","experience":"","photourl":"<url>","rating":0,"approved":1,…}]` |
| `get_teacher` | anyone | `teacherid` | teacher profile + `courses[]`; `teachernotfound` |
| `get_teacher_courses` | anyone | `teacherid` | `[{"id":2,"fullname":"…","shortname":"…","summary":"…","imageurl":"<url>",…}]` |
| `get_all_teachers` | manager | `search`, `courseid`, `categoryid`, `page`, `perpage` | `{"total":8,"page":0,"perpage":2,"teachers":[… with email]}` |

### 3.9 Academy licence (owner / manager)

#### `get_license_status` (GET) — needs `local/academy:manageplatform`
```json
{"status":"success","data":{
 "package":{"enforced":false,"tier":"demo","name":"Demo","videosource":"all","features":[],"storagegb":-1,
            "limits":{"maxcourses":-1,"maxteachers":-1,"quiz":-1,"video":-1,"pdf":-1},"billing_in_nit2":true},
 "usage":{"courses":17,"teachers":7,"quiz":6,"video":7,"pdf":0},
 "subscription":{"subscribedat":null,"subscribeddate":null,"expiry":null,"expirydate":null,"daysleft":null,
                 "is_expired":false,"is_suspended":false,"status":"active"}}}
```
`-1` = unlimited; `null` = no expiry. Price, renew and upgrade are handled by the NIT platform (nit2),
not by the academy. Non-managers get `nopermissions` (do **not** log out — just hide the screen).

### 3.10 Home page, teacher page, course page

The Bassthalk screens as data — the same sources and rules as the website pages (home page sections,
`/local/academy/teacher.php`, `/local/academy/course.php`). All are GET on `/local/academy/api.php`.

**Visitors:** these work with the **shared token** too. The caller is then treated as a website
visitor: no personal data (`enrolled`/`covered` false, `me` null), hidden courses are not shown,
and the course page's main button asks to log in (`action:"login"`). With the user's own token the
user's state is used.

Every course card / course page carries the same **`state`** (price card) object:
```json
{"enrolled":false,"covered":false,"free":false,"haspricing":true,
 "price":130.0,"price_minor":13000,"currency":"EGP",
 "hasoffer":true,"offerlabel":"-40%","finalprice":78.0,"finalprice_minor":7800,"discountpercent":40}
```
Show `finalprice`; when `hasoffer`, strike `price` and show the `discountpercent` badge. `free` → "مجاني".
Card button: `enrolled` → open the course; `covered` → enrol with the subscription
(`enrol_course`, §6.3.3); `haspricing` → buy (§6.1.1); otherwise free enrol (`enrol_course`).

#### `get_home_selected` — "كورسات مختارة"
```json
{"status":"success","data":{
 "years":[{"id":1,"name":"الصف الاول الاعدادى"},{"id":6,"name":"الصف الثالث الثانوي"}],
 "courses":[{"id":2,"fullname":"الإدارة والأعمال 1",
   "image":"http://…/webservice/pluginfile.php/16/course/overviewfiles/cover.jpg?token=<token>",
   "year":"الصف الاول الاعدادى","years":[1],"teachers":1,"lessons":1,"state":{…}}]}}
```
The admin-picked courses. Year chips filter the cards: a card belongs to every year id in `years`.
`teachers` / `lessons` are counts. `image` is `""` when the course has no picture (use the placeholder).

#### `get_home_teachers` — "المدرسين عندنا"
```json
{"status":"success","data":{
 "years":[{"id":6,"name":"الصف الثالث الثانوي"}],
 "systems":[{"name":"{mlang en}general{mlang}{mlang ar}عام{mlang}","label":"عام",
             "divisions":[{"name":"{mlang ar}ادبى{mlang}{mlang en}Literary{mlang}","label":"ادبى"}]}],
 "me":{"year":6,"system":"{mlang en}general{mlang}{mlang ar}عام{mlang}","division":""},
 "teachers":[{"id":6,"name":"أحمد سمير","title":"أستاذ اللغة العربية",
   "photo":"http://…/webservice/pluginfile.php/41/user/icon/nit/f3?rev=6309&token=<token>",
   "courses":[{"years":[6],"system":"{mlang en}general{mlang}{mlang ar}عام{mlang}","division":""}]}]}}
```
Filter on the device (no reload): a teacher matches when **one** of their `courses` matches the chosen
year (in `years`), study system (`system` = the system's `name`) and division (`division` = the
division's `name`; an empty course value matches any). `me` = the signed-in student's own year /
system / division to pre-select (`null` for visitors). Tap a teacher → `get_teacher_page`.

#### `get_home_lessons` — "المحاضرات المقترحة"
```json
{"status":"success","data":{"courses":[{"id":15,"fullname":"الرياضيات - الصف الأول الاعدادي",
  "image":"http://…/webservice/pluginfile.php/86/course/overviewfiles/cover.svg?token=<token>",
  "year":"١ ع","price":"مجاني","summary":"الأعداد النسبية والجبر والهندسة للصف الأول الإعدادي.",
  "created":1791124082,"modified":1791181745,"state":{…}}]}}
```
The latest courses. `year` is the short year label and `price` the ready-made price text of the web
card; use `state` for real numbers.

#### `get_teacher_page` — `teacherid`
```json
{"status":"success","data":{"id":6,"name":"أحمد سمير","title":"أستاذ اللغة العربية",
 "photo":"http://…/webservice/pluginfile.php/41/user/icon/nit/f3?rev=6309&token=<token>",
 "bio":"خبرة أكثر من 15 سنة في تدريس اللغة العربية للمرحلة الثانوية…",
 "years":[{"id":6,"name":"الصف الثالث الثانوي","short":"٣ ث","courses":1,"divisions":[],"label":"الصف الثالث الثانوي"}],
 "courses":[{"id":14,"fullname":"اللغة العربية - الصف الثاني الاعدادي","image":"…","year":"٢ ع",
   "price":"90 جنيه","summary":"…","created":…,"modified":…,"yearid":2,"division":"","enrolled":false,"state":{…}}],
 "counts":{"courses":4,"years":4,"students":12}}}
```
`years` are the tabs (filter `courses` by `yearid`; `label` includes the divisions). Errors:
`teachernotfound` (not a teacher, or a site admin).

#### `get_course_page` — `courseid`
```json
{"status":"success","data":{
 "course":{"id":4,"fullname":"الفيزياء - الصف الثالث الثانوي","shortname":"demo-phy-sec3",
   "summary":"الكهربية التيارية والكهرومغناطيسية…","summaryhtml":"<p>…</p>",
   "image":"http://…/webservice/pluginfile.php/48/course/overviewfiles/cover.svg?token=<token>",
   "category":{"id":6,"name":"الصف الثالث الثانوي"},"studysystem":"عام","division":"علمى رياضة",
   "subject":"","language":"","isfreeflag":false,"certificate":false,"hours":null,
   "timemodified":1791124069,"hasvideo":true},
 "counts":{"sections":1,"lessons":5,"assessments":0,"students":5},
 "teachers":[{"id":8,"fullname":"هشام فؤاد","title":"دكتور الفيزياء",
   "photo":"http://…/webservice/pluginfile.php/43/user/icon/nit/f3?rev=6317&token=<token>"}],
 "learn":[],"skills":[],"audience":[],"prerequisites":[],
 "price":{"enrolled":true,"covered":false,"free":false,"haspricing":true,"price":200.0,"price_minor":20000,
          "currency":"EGP","hasoffer":false,"offerlabel":"","finalprice":200.0,"finalprice_minor":20000,"discountpercent":0},
 "action":"open","resume_cmid":2,
 "sections":[{"id":10,"number":1,"name":"قسم جديد","summary":"","available":true,"availableinfo":"",
   "progress":{"tracked":false,"done":0,"total":5},
   "items":[
     {"cmid":2,"name":"الدرس 1: قانون أوم","modname":"page","islabel":false,"marker":"","price":null,
      "canopen":true,"locked":false,"forsale":false,"completed":false,"watched_percent":null},
     {"cmid":3,"name":"الدرس 2: دوائر التيار الكهربي","modname":"page","islabel":false,"marker":"buy",
      "price":{"price_minor":3000,"price":30.0,"currency":"EGP"},"canopen":false,"forsale":true,"…":"…"},
     {"cmid":34,"name":"درس فيديو تجريبي","modname":"vimeo","islabel":false,"marker":"owned",
      "canopen":true,"watched_percent":42,"…":"…"}]}],
 "forums":[]}}
```
- **About tab:** `course.summary` (or `summaryhtml`), `teachers` (tap → `get_teacher_page`), `subject`,
  `learn` ("هتتعلم إيه"), `skills`, `audience` / `prerequisites` (requirements), `language`,
  `certificate`, `hours`, `counts`. Empty lists = hide that block.
- **Price card:** `price` (the `state` object above) + `counts`. **Main button by `action`:**
  `login` (visitor → login screen, then reload) · `open` (enrolled → open `resume_cmid`, or the first
  lesson) · `enrol_subscription` (covered by the subscription → `enrol_course`) · `buy` (→ purchase flow
  §6.1.1) · `enrol_free` (→ `enrol_course`).
- **Lessons tab:** `sections[]` (one accordion row each). `available:false` → show `availableinfo`
  (e.g. "Available from …") instead of items. `progress`: when `tracked`, show `(done/total)`, else
  `(total دروس)`. `items[]` are lessons; an item with `part` is a sub-group:
  `{"part":"<title>","items":[…]}`. `islabel` items are plain text (not tappable).
- **Item `marker`:** `free` (free course) · `locked` (paid course, not bought) · `buy` (a lesson sold
  on its own — show `price`, buy with the wallet: `get_lesson_access` / `buy_lesson`, §7) · `owned`
  (bought on its own) · `""` (normal). `canopen` = the user can open it now; `locked` = lesson-order
  lock; `completed`, `watched_percent` = progress.
- **Forum tab:** `forums[]` (`cmid`, `name`) — hide the tab when empty.
- Errors: `coursenotfound` (missing, the site course, or hidden for this viewer).

---

## 4. Live sessions, Jitsi and video playback


All `api.php` endpoints below share one envelope:

```
GET|POST /local/<plugin>/api.php?function=<name>&token=<token>&...   (wstoken= also accepted)
→ {"status":"success","data":…}
→ {"status":"fail","error":"<readable, show it>","errorcode":"<stable code>"}
```

The HTTP status is 200, except **401** for `authrequired` / `invalidtoken` (the token is missing or dead: log the user out) and **403** for `siteunavailable` (the academy is suspended or expired: show a blocking screen). You can add `lang=ar|en` to get messages and names in that language.

Common errorcodes (any function): `authrequired`, `invalidtoken`, `siteunavailable`, `unknownfunction`, `postrequired` (send it as POST), `missingparam` (a required param is missing), `nopermissions` (the caller's role may not do this: hide the action), `internalerror` (retry later).

---

### 4.1 Live sessions — `/local/academysessions/api.php`

Auth: the user's web-service token (moodle_mobile_app). Who may call each function is listed with it.

> Compatibility: the original functions (`create_session`, `get_teacher_sessions`, `get_student_sessions`, `join_session`, `end_session`, `get_attendance`, `delete_session`, `update_session`) keep their names, params and fields. They **still accept GET** so older app builds keep working, but new code should POST writes. The newer write functions (`start_session`, `leave_session`, `set_teacher_present`, `end_room`) **require POST**.
>
> Changing a session (`start_session`, `end_session`, `update_session`, `delete_session`) needs managesessions in the course **and** being that session's own teacher; platform managers and site admins may change any session. Another teacher of the same course gets `notsessionteacher`.
>
> Session rows are returned as stored, so the original columns come back as strings (`"id":"1"`, `"start_time":"1791188840"`). The added fields are typed: `cmid` (int|null), `teacher_present` (bool), `title_formatted` (string), `link_visible` (bool).

#### Typical flows

**Teacher:** `create_session` (with `jitsiid`) → returns `cmid` → at start time `start_session` → open the room: `mod_jitsi_get_session_info(cmid)` (or `api_token.php?id=cmid`) → join the call with the Jitsi SDK → when the SDK fires *conference joined*, call `set_teacher_present(cmid, present=1)`. This lets the students in. On *conference left*, call `set_teacher_present(cmid, present=0)` → when the session is over, call `end_session(sessionid)`. For a standalone room (no session), call `end_room(cmid)` instead.

**Student:** `get_student_sessions` → once `link_visible` is true, call `join_session(sessionid)`. This records attendance and returns `cmid` → `mod_jitsi_get_session_info(cmid)`. While `available` is false (`available_info` = "Waiting for the teacher…"), poll every ~5 s, then join → on leaving the call, `leave_session(sessionid)` → after the session, `get_session_recordings(sessionid)`.

#### create_session — teacher (managesessions in the course)
| Param | Type | Req | Description |
|---|---|---|---|
| courseid | int | yes | course |
| title | text | yes | session title |
| starttime | int | yes | unix start |
| studentids | CSV ints | yes | students allowed to attend, e.g. `3,12` |
| meetinglink | url | no | external link (Google Meet) |
| duration | int | no | minutes, default 50 (1–1440) |
| googlemeetid | int | no | legacy |
| jitsiid | int | no | **jitsi instance id** of a Jitsi activity in the same course |

```json
{"status":"success","data":{"sessionid":1,"cmid":64}}
```
`cmid` is the Jitsi course-module id, or null when there is no Jitsi room. Errors: `invalidvalue` (bad title/starttime/duration/jitsiid; the message names the field), `studentnotenrolled` (a student id in `studentids` is not enrolled in the course), `coursenotfound`, `nopermissions`, `missingparam`.

#### get_teacher_sessions — teacher
| Param | Type | Req | Description |
|---|---|---|---|
| courseid | int | no | with it: every session of that course (needs managesessions **or** viewattendance there). Without it: the caller's own sessions |

```json
{"status":"success","data":[{"id":"1","googlemeetid":null,"jitsiid":"1","courseid":"2","teacherid":"4","groupid":null,
 "title":"ZZ API test session","start_time":"1791188840","duration":"50","meeting_link":"","status":"scheduled",
 "teacher_joined_at":null,"timecreated":"1791188242","timemodified":"1791188242",
 "cmid":64,"teacher_present":false,"title_formatted":"ZZ API test session",
 "students":[{"id":"3","firstname":"Sara","lastname":"NITTest","email":"nit_test_student@example.invalid"}],
 "attendance_count":0}]}
```
`status` is one of `scheduled`, `live`, `ended`, `cancelled`. Errors: `nopermissions` (the course was given but the caller is not staff there), `coursenotfound`.

#### start_session — the session's teacher (managesessions), POST
`sessionid` (int, required). Sets the status to `live` and returns the session row (same shape as above, without students).
Errors: `sessionnotfound`, `sessionended` (the session was ended or cancelled), `notsessionteacher`, `nopermissions`.

#### end_session — the session's teacher (managesessions)
`sessionid`. Sets the status to `ended` and closes every open attendance row (left_at + duration).
```json
{"status":"success","data":{"sessionid":1,"status":"ended"}}
```
Errors: `sessionnotfound`, `notsessionteacher`, `nopermissions`.

#### update_session — the session's teacher (managesessions)
| Param | Type | Req | Description |
|---|---|---|---|
| sessionid | int | yes | |
| title | text | no | |
| starttime | int | no | |
| meetinglink | url | no | |
| duration | int | no | minutes |

Only the non-empty params are changed.
```json
{"status":"success","data":{"sessionid":1,"updated":["title","duration"]}}
```
Errors: `sessionnotfound`, `notsessionteacher`, `nopermissions`.

#### delete_session — the session's teacher (managesessions)
`sessionid`. Deletes the session, its student list and its attendance → `{"sessionid":1,"deleted":true}`. Errors: `sessionnotfound`, `notsessionteacher`, `nopermissions`.

#### get_attendance — teacher (viewattendance)
`sessionid`.
```json
{"status":"success","data":[{"id":"2","sessionid":"1","userid":"3","joined_at":"1791188255","left_at":null,
 "duration_seconds":"49","firstname":"Sara","lastname":"NITTest","email":"nit_test_student@example.invalid"}]}
```
The session's teacher shows up in this list too (it is filled in by `set_teacher_present`). Errors: `sessionnotfound`, `nopermissions`.

#### get_student_sessions — student
No params. Returns the sessions the user is on the list for, that are scheduled or live and have not finished yet.
```json
{"status":"success","data":[{"id":"1","jitsiid":"1","courseid":"2","teacherid":"4","title":"ZZ API test session",
 "start_time":"1791188840","duration":"50","meeting_link":"","status":"scheduled","teacher_joined_at":null,
 "cmid":64,"teacher_present":false,"title_formatted":"ZZ API test session","link_visible":true}]}
```
`link_visible` turns true 30 min before the start. Before that, `meeting_link` is blanked.

#### join_session — student on the session list
`sessionid`. Records attendance. A rejoin after `leave_session` reopens the attendance row.
```json
{"status":"success","data":{"meeting_link":"","sessionid":1,"jitsiid":1,"cmid":64,"status":"live","teacher_present":true}}
```
Errors: `sessionnotfound`, `notallowed` (the user is not on the session's list), `sessionnotavailable` (too early: show the start time), `sessionended`.

#### leave_session — any participant who joined, POST
`sessionid`. Stamps `left_at` and `duration_seconds` (from the first join to now).
```json
{"status":"success","data":{"sessionid":1,"joined_at":1791188255,"left_at":1791188304,"duration_seconds":49}}
```
Errors: `sessionnotfound`, `notjoined`.

#### set_teacher_present — the room's moderator, POST
| Param | Type | Req | Description |
|---|---|---|---|
| cmid | int | yes | Jitsi course-module id |
| present | 1\|0 | no | default 1. Send 1 on *conference joined* and 0 on *conference left* |

This is the same logic as the web page's `teacher_present.php`. It stamps `teacher_joined_at`, which opens the student gate in `mod_jitsi_get_session_info` / view.php, and it also records the teacher's attendance and the lesson audit entry. Who may call it: holders of `mod/jitsi:moderate`. For a room linked to a session, only that session's teacher (or a site admin) may call it, the same as view.php.
```json
{"status":"success","data":{"cmid":64,"present":true,"sessionid":1,"teacher_joined_at":1791188270}}
```
For a standalone room the response is `sessionid:null`, `teacher_joined_at:null`. Nothing is stored because those rooms have no gate. Errors: `notjitsiactivity`, `nopermissions`.

#### end_room — the room's moderator, POST
`cmid`. Locks a **standalone** Jitsi activity so nobody can rejoin. This is the same as the web page's `ajax.php end_room`. For a room linked to a session, use `end_session`.
```json
{"status":"success","data":{"cmid":65,"ended":true,"ended_at":1791188375}}
```
Errors: `notjitsiactivity`, `nopermissions`.

#### get_session_recordings — participants / staff
| Param | Type | Req | Description |
|---|---|---|---|
| sessionid | int | one of | a live session |
| cmid | int | one of | a Jitsi activity (works for standalone rooms) |

These are the Vimeo recordings that Jibri uploaded (`academy_session_recordings`). The visibility rules match the web: staff always see them, students of a linked session see them once it has ended or its time window has passed, and standalone rooms show them to anyone who can open the activity.
```json
{"status":"success","data":{"sessionid":1,"cmid":64,"available":true,"recordings":[
 {"id":1,"sessionid":1,"cmid":64,"title":"ZZ test recording linked","duration":1234,"status":"ready",
  "vimeo_videoid":"111111111","playback_url":"https://player.vimeo.com/video/111111111",
  "embed_url":"https://player.vimeo.com/video/111111111","timecreated":1791188319}]}}
```
`available:false` with an empty list means "not yet". Show "Recordings appear after the session". Play `embed_url` in a WebView. The video is embed-whitelisted to the academy domain, so load it with that domain as the Referer / base URL. Errors: `invalidvalue` (neither param was sent), `sessionnotfound`, `notjitsiactivity`, `notallowed`, `nopermissions`.

---

### 4.2 Jitsi

#### mod_jitsi_get_session_info (standard WS, recommended)
```
GET /webservice/rest/server.php?wstoken=<token>&moodlewsrestformat=json&wsfunction=mod_jitsi_get_session_info&cmid=<cmid>
```
```json
{"cmid":64,"name":"ZZ API test room (linked)","available":false,
 "available_info":"Waiting for the teacher to start the meeting.","is_teacher":false,
 "server_url":"https://academy2026.nitg-eg.com:8443","room":"nit_localhost_64_8ac7341e","jwt":"<jwt>",
 "subject":"ZZ API test room (linked)","whiteboard_url":"https://academy2026.nitg-eg.com/whiteboard/#room=academy_wb_jitsi_64",
 "recordings":[{"id":2,"title":"ZZ test recording standalone","status":"ready",
   "playback_url":"https://player.vimeo.com/video/222222222","embed_url":"https://player.vimeo.com/video/222222222",
   "timecreated":1791188319}]}
```
- When `available` is false, the student must wait. Show `available_info` and poll about every 5 s. It flips to true once the teacher calls `set_teacher_present`.
- `recordings` is now filled. It uses the same rules as `get_session_recordings`. `playback_url` and `embed_url` are both the Vimeo player URL. `thumbnail_url` is not provided.
- Errors use the standard WS shape: `{"exception":…,"errorcode":…,"message":…}`.

#### /mod/jitsi/api_token.php (legacy)
```
GET /mod/jitsi/api_token.php?id=<cmid>&wstoken=<token>      (token= also accepted)
```
```json
{"server_url":"https://academy2026.nitg-eg.com:8443","room":"nit_localhost_64_8ac7341e","jwt":"<jwt>",
 "is_moderator":false,"subject":"ZZ API test room (linked)","display_name":"Sara NITTest","email":"nit_test_student@example.invalid"}
```
The token is now validated like every other API: expiry, IP restriction, service enabled, and suspended/unconfirmed account. Errors keep the old shape plus an errorcode: `{"error":"…","errorcode":"…"}`.

| HTTP | errorcode | Meaning |
|---|---|---|
| 401 | authrequired / invalidtoken | log out |
| 403 | siteunavailable | academy locked |
| 404 | invalidcoursemodule | not a Jitsi activity |
| 403 | nopermissions | cannot open the activity |
| 403 | notallowed | not on the session's list |
| 403 | sessionnotavailable | more than 30 min before the start |
| 403 | sessionended | the time window has passed |

Note: unlike `get_session_info`, this endpoint does **not** apply the "waiting for teacher" gate. Prefer `get_session_info`.

---

### 4.3 Vimeo — `/local/vimeo/api.php`

#### get_playback — any user who may view the lesson
| Param | Type | Req | Description |
|---|---|---|---|
| cmid | int | yes | a `mod_vimeo` activity or a resource with an attached Vimeo video |

```json
{"status":"success","data":{"videoid":"76979871","embedurl":"https://player.vimeo.com/video/76979871"}}
```
Before returning the URL, the endpoint applies **the same lesson rules as the web player**: enrolment + `local/vimeo:view` + activity visible, the lesson-order lock, and per-lesson sale. Staff with `local/vimeo:manage` (editing teachers and managers) always pass.

`get_playback` now also resolves `mod_vimeo` activities. Before this change, it only found videos attached through `attach_video`.

Errors:
- `lessonlocked`: the user must finish the previous lessons. Send them to the resume lesson.
- `lessonforsale`: the lesson must be bought. Open the purchase flow for this cmid.
- `vimeoapi`: no video, no access, or a Vimeo error. Show the message.
- `nopermissions`.

**After playback starts**, call `POST /local/academy/api.php?function=log_lesson_view&cmid=<cmid>`. This logs the view and marks view-based completion. Save the watch position and percentage with `local/nit_videoprogress` (endpoint documented separately).

#### Teacher CRUD (local/vimeo:manage in the course)
| Function | Method | Params | Data |
|---|---|---|---|
| create_upload | POST | title, size (bytes), courseid?, cmid? | Vimeo video id + tus upload link. PATCH the file bytes to the link |
| video_status | GET | videoid | refreshed status (`PRE-Upload…complete`) |
| list_videos | GET | courseid? | `[{"id":1,"videoid":"76979871","cmid":62,"courseid":23,"title":"Reading skills","status":"Processing","length":0}]` |
| attach_video | POST | videoid, cmid | the mapping row |
| delete_video | POST | videoid | `{"deleted":true}` |

Errors: `nopermissions` (not a teacher), `postrequired`, `vimeoapi` (Vimeo not configured, video unknown, or an upstream failure; the message explains which).

---

### 4.4 VdoCipher — `/local/vdocipher/api.php`

This endpoint now uses the shared envelope. Every failure now carries an `errorcode`, and a missing or invalid token now returns **HTTP 401** (previously HTTP 200).

#### get_playback — any user who may view the lesson
`cmid` (int, required): a `mod_vdocipher` activity or a resource with an attached VdoCipher video. The endpoint mints a short-lived OTP whose watermark carries the viewer's identity.
```json
{"status":"success","data":{"videoid":"…","otp":"…","playbackInfo":"…","watermark":"Sara NITTest · …","ttl":300}}
```
It applies the same lesson rules as Vimeo. Errors: `lessonlocked`, `lessonforsale`, `vdocipherapi` (no video, no access, VdoCipher not configured, or an upstream error), `nopermissions`.

Play it with the VdoCipher SDK using `otp` and `playbackInfo`. Call get_playback again when the OTP expires (`ttl` seconds). After playback starts, call `log_lesson_view` and save progress, as for Vimeo.

#### Teacher CRUD (local/vdocipher:manage)
| Function | Method | Params |
|---|---|---|
| create_upload | POST | title, courseid?, cmid? → S3 upload credentials + video id |
| video_status | GET | videoid |
| list_videos | GET | courseid? |
| attach_video | POST | videoid, cmid |
| delete_video | POST | videoid → `{"deleted":true}` |

Errors: `nopermissions`, `postrequired`, `vdocipherapi`.

---

## 5. Video progress, course reviews, parent dashboard


All three endpoints use the shared envelope (`\local_academy\api\endpoint`):

```
GET|POST /local/<plugin>/api.php?function=<name>&token=<token>&...
→ {"status":"success","data":...}
→ {"status":"fail","error":"<readable, show it>","errorcode":"<stable code>"}
```

- `token` (or `wstoken`) = the user's web-service token. Missing → HTTP 401 `authrequired`; dead → HTTP 401
  `invalidtoken` (log out). Academy suspended/expired → HTTP 403 `siteunavailable`. Everything else is HTTP 200.
- Optional `lang=ar|en` picks the language of names and messages.
- Writes must be `POST` (form-encoded body) → otherwise `postrequired`.
- Generic codes any function may return: `missingparam` (a required param is absent), `invalidparameter`,
  `nopermissions` (no access / not allowed), `internalerror` (server problem — show the message, retry later),
  `unknownfunction`.
- Timestamps are unix seconds. Decimal fields such as `avg` always keep their fraction (`4.0`, `3.5`);
  integer fields such as `percent` and `count` are plain integers.

---

### 5.1 Course reviews — `/local/nit_reviews/api.php`

Star ratings (1–5) with an optional text, one per user per course. Any logged-in user can read ratings and
reviews of a visible course. Only users **actively enrolled** in the course (role with
`local/nit_reviews:rate`: student/teacher) can write. Moderators (`local/nit_reviews:manage` in the course:
editing teacher, manager) can delete any review.

Typical flow: catalogue cards → `get_course_ratings` for all visible course ids in one call. Course page →
`get_course_reviews` (summary + first page) and `get_my_review` (to show "Rate" / "Edit your review"). Rating
sheet → `save_review` (create or update — same call). Use the `summary` returned by every write to refresh the
course header without another call.

#### get_course_ratings — GET

| param | type | req | description |
|---|---|---|---|
| courseids | JSON int array `[4,22]` **or** CSV `4,22` | yes | up to 200 course ids |

```json
{"status":"success","data":{"courses":[
  {"courseid":4,"avg":3.0,"count":2},
  {"courseid":22,"avg":0.0,"count":0}]}}
```
Courses with no reviews (or unknown ids) come back `avg 0, count 0`, in the order requested.
Errors: `invalidparameter` (malformed JSON), `toomanycourses` (> 200 ids — split the request).

#### get_course_reviews — GET

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course |
| page | int | no | 0-based, default 0 |
| perpage | int | no | default 20, max 50 |

Newest first (by last edit). `pictureurl` is ready to load (tokenized when it is an uploaded photo; a theme
default-avatar URL otherwise). `ismine` marks the caller's own review. `canrate` = caller may write a review;
`canmanage` = caller may delete any review (show a delete action on each item → `delete_review`).

```json
{"status":"success","data":{
  "courseid":4,
  "summary":{"avg":3.0,"count":2,"stars":{"1":0,"2":1,"3":0,"4":1,"5":0}},
  "canrate":true,"canmanage":false,
  "page":0,"perpage":1,"total":2,
  "reviews":[{"id":2,"userid":3,"fullname":"Sara NITTest",
    "pictureurl":"http://localhost:8082/theme/image.php/nit/core/1791187906/u/f1",
    "rating":2,"review":"ok","timecreated":1791187961,"timemodified":1791187961,"ismine":false}]}}
```
`review` may be `""` (stars only). More pages while `(page+1)*perpage < total`.
Errors: `coursenotfound` (no such course, site home, or hidden from the caller).

#### get_my_review — GET

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course |

```json
{"status":"success","data":{"courseid":4,"canrate":true,
  "review":{"id":1,"courseid":4,"userid":12,"rating":4,"review":"كورس ممتاز والشرح واضح جدًا",
            "timecreated":1791187960,"timemodified":1791187961},
  "summary":{"avg":3.0,"count":2}}}
```
`review` is `null` when the caller has not rated. Show the rating form only when `canrate` is true.
Errors: `coursenotfound`.

#### save_review — POST

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course |
| rating | int | yes | 1–5 |
| review | string | no | text, up to 2000 characters (HTML stripped); empty clears the text |

Creates or updates the caller's single review (the web page `/local/nit_reviews/rate.php` edits the same row).

```json
{"status":"success","data":{
  "review":{"id":1,"courseid":4,"userid":12,"rating":5,"review":"كورس ممتاز والشرح واضح جدًا",
            "timecreated":1791187960,"timemodified":1791187960},
  "summary":{"avg":5.0,"count":1}}}
```
Errors → show the message:
- `cannotrate` — caller is not actively enrolled (or lacks the rate permission). Hide the form.
- `invalidrating` — rating outside 1–5.
- `reviewtoolong` — more than 2000 characters.
- `coursenotfound`, `postrequired`.

#### delete_my_review — POST

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course |

Deletes the caller's own review (allowed even after the enrolment ended).
```json
{"status":"success","data":{"deleted":true,"summary":{"avg":0.0,"count":0}}}
```
Errors: `reviewnotfound` (nothing to delete — treat as done), `coursenotfound`, `postrequired`.

#### delete_review — POST (moderators)

| param | type | req | description |
|---|---|---|---|
| reviewid | int | yes | `id` from `get_course_reviews` |

Needs `local/nit_reviews:manage` in the review's course (editing teacher, manager).
```json
{"status":"success","data":{"deleted":true,"reviewid":2,"courseid":4,"summary":{"avg":4.0,"count":1}}}
```
Errors: `nopermissions` (student etc.), `reviewnotfound` (already deleted — refresh the list), `postrequired`.

---

### 5.2 Video progress — `/local/nit_videoprogress/api.php`

How much of each Vimeo / VdoCipher lesson the **token's user** really watched, and where to resume. Same data and
rules as the website player (`js/tracker.js` → web service `local_nit_videoprogress_save`); web and app update the
same row, so progress follows the student between devices.

#### The slices protocol (what the mobile player must do)

The video is cut into **100 slices, 1 % each** (slice *i* covers `[i/100·duration, (i+1)/100·duration)`).
`percent` = number of distinct slices ever played, so it is the share of the video really seen — seeking ahead
never counts.

1. **Before playing:** `get_progress(cmid)` → seek to `resume_position` (it is already `0` when the last
   position is < 5 s, or within 10 s of the end — i.e. finished, start over). Seek once, as soon as the player
   knows the duration (or at the first play at the latest). Offer "start from the beginning".
2. **While playing**, on every time update (`t` = current time, `last` = previous time, `d` = duration):
   only if `d > 0` and `0 < t − last ≤ 3` seconds (normal playback; a bigger jump or going back is a seek) mark
   every slice from `floor(last/d·100)` to `floor(min(t, d−0.001)/d·100)` as pending. Then `last = t`.
3. **On end** (`ended` event): if `d − last ≤ 3`, mark slices from `floor(last/d·100)` to 99.
4. **Report** with `save_progress`: at most every **15 s** while playing, and also on pause, on end, and when the
   app goes to background / the player screen closes. Send `position = floor(t)`, `duration = floor(d)` (0 if
   still unknown) and `slices` = the pending slice numbers as CSV (`"42,43,44"`, may be empty). Clear the pending
   set only after a `success`; on a network error keep them and send them with the next report.
5. **Never report before the user has pressed play** — opening a lesson without playing must not overwrite the
   saved position with 0.

Server rules: slices are merged (OR) into the stored 100-char mask; duration only grows (a longer reported value
wins); position is clamped to `[0, duration]`.

#### save_progress — POST

| param | type | req | description |
|---|---|---|---|
| cmid | int | yes | the vimeo / vdocipher course module id |
| position | int | yes | current playback second |
| duration | int | yes | video length in seconds, 0 if unknown |
| slices | string | no | CSV of slice numbers 0–99 played since the last report (`""` = none) |

```json
{"status":"success","data":{"percent":48,"position":40,"duration":62,"resume_position":40}}
```
Show `percent` in the progress bar.
Errors:
- `invalidslices` — `slices` is not CSV of 0–99 (client bug; drop the batch).
- `notvideo` — the cmid is not a video lesson (or does not exist).
- `nopermissions` — not enrolled / no access to the lesson; `lessonlocked` — the lesson is locked for this
  student (e.g. not bought yet). Stop reporting for this lesson.
- `postrequired`, `missingparam`.

#### get_progress — GET

| param | type | req | description |
|---|---|---|---|
| cmid | int | yes | video lesson |

```json
{"status":"success","data":{"cmid":34,"courseid":4,"provider":"vimeo","name":"درس فيديو تجريبي",
  "started":true,"percent":42,"position":30,"duration":62,"resume_position":30,
  "watched":"1111111111111111111111111111111111111111110000000000000000000000000000000000000000000000000000000000",
  "slices":[0,1,2,3,4,"…",41],"lastwatched":1791134286}}
```
- `watched` = 100-char mask (`1` = slice played); `slices` = the same as a list of indexes (handy to paint
  watched segments on the seek bar). A lesson never played: `started:false`, zeros, an all-`0` mask.
- `lastwatched` = time of the last report (0 if never).

Errors: `notvideo`, `nopermissions`, `lessonlocked` (same access checks as `save_progress`).

#### get_course_progress — GET

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course; caller must be actively enrolled |

Every visible video lesson of the course in course order (not-started ones at 0), plus the course average.
```json
{"status":"success","data":{"courseid":4,"coursename":"Physics — Third Secondary","percent":48,
  "lessons":[{"cmid":34,"name":"درس فيديو تجريبي","percent":48,"position":3,"duration":62,
    "lastwatched":1791188002,"url":"http://localhost:8082/mod/vimeo/view.php?id=34","resume_position":0}]}}
```
`percent` (course) = average of the lessons' percents. A course without video lessons returns `lessons: []`,
`percent: 0`. `url` is the web page of the lesson (not needed by the app).
Errors: `notenrolled` (not enrolled, or course does not exist).

#### get_overview — GET

| param | type | req | description |
|---|---|---|---|
| courseids | JSON array or CSV | no | limit to these courses; default = all my active courses. Courses I am not enrolled in are ignored. |

```json
{"status":"success","data":{"percent":62,"courses":[
  {"courseid":22,"coursename":"…","percent":62,"lessons":[
    {"cmid":49,"name":"شرح المبتدأ والخبر","percent":85,"position":60,"duration":600,"lastwatched":1791135988,
     "url":"…","resume_position":60},
    {"cmid":50,"name":"شرح كان وأخواتها","percent":0,"position":0,"duration":0,"lastwatched":0,"url":"…",
     "resume_position":0}]}]}}
```
Top-level `percent` = average over all listed lessons. Courses with no video lessons are omitted.
Errors: `invalidparameter` (malformed JSON in `courseids`).

---

### 5.3 Parent dashboard — `/local/parent/api.php`

Parents have **no account**. This call is **pre-login**: send the shared registration token from
`getsettings.php` (`admin_token`) as `token` (any valid token is accepted; the token's user is *not* treated as
the parent and gives no access). Access comes only from the phone pair: the child's own phone (profile phone 1 /
phone 2) **and** one of the guardian phones the child registered (parent / father / mother phone). Formatting
does not matter (`010…`, `+2010…`, `0020 10…`, spaces/dashes). Same rules and the **same per-IP throttle** as the
web page `/parent_dashboard`: 10 wrong pairs per IP → blocked for 15 minutes (shared with the website).

Flow: phone form → `get_child_report` → show the course list → on course tap, show its `sections` (lectures)
with three columns: videos, homework, exams. Keep the two phones in memory (or secure storage) to refresh.

#### get_child_report — POST

| param | type | req | description |
|---|---|---|---|
| studentphone | string | yes | the child's phone as typed |
| parentphone | string | yes | the parent's phone as typed |

```json
{"status":"success","data":{
  "student":{"id":12,"fullname":"طالب تجريبي"},
  "courses":[
    {"id":15,"name":"الرياضيات - الصف الأول الاعدادي",
     "sections":[{"name":"قسم جديد","started":0,"videos":[],"homework":[],"exams":[]}]},
    {"id":4,"name":"الفيزياء - الصف الثالث الثانوي",
     "sections":[{"name":"قسم جديد","started":1791129606,
       "videos":[{"name":"درس فيديو تجريبي","state":"done","percent":42}],
       "homework":[],"exams":[]}]}]}}
```
- `courses` = the child's active enrolments; `sections` = lectures (the general section only when it holds a
  reported item); only items visible to the child are listed. `started` = when the first item of the lecture was
  added (0 when empty) — web shows it as the lecture date.
- Item `state`: `done` (video watched > 0 %, or homework/exam grade released → `percent` = watched % or grade
  %), `pending` (homework submitted / exam finished, grade not released → `percent: null`), `absent` (not
  watched / not handed in / not taken → `percent: null`). Web labels items by order: "Video 1", "Homework 2"…
- An empty `courses` list = the child is not enrolled anywhere yet.

Errors → show the message:
- `invalidphone` — a phone is empty, has letters, or has fewer than 7 digits (not counted as an attempt).
- `studentnotfound` — no child matches this pair. The message never says which phone was wrong. Counts as
  one wrong attempt.
- `toomanyattempts` — this network made 10 wrong attempts; try again after 15 minutes (even a correct pair is
  refused meanwhile).
- `postrequired`, `authrequired` / `invalidtoken` (HTTP 401 — the app's registration token is missing or
  rotated: re-fetch `getsettings.php`).

---

## 6. Payments, coupons & offers, subscriptions


Base URL in examples: `http://localhost:8082` (use the academy's site URL).
Every call is authenticated with the user's **web-service token** (`moodle_mobile_app` service, the
same token used for `/webservice/rest/server.php`). Never put anything else in the URL.

Two transports are used here:

| Transport | URL | Envelope |
|---|---|---|
| **Moodle web services (WS)** | `GET/POST /webservice/rest/server.php?moodlewsrestformat=json&wstoken=<token>&wsfunction=<name>&...` | Raw JSON result. Errors: `{"exception":"…","errorcode":"…","message":"…"}` |
| **Token JSON API** (`api.php`) | `GET/POST /local/<plugin>/api.php?function=<name>&token=<token>&...` (`wstoken=` also accepted) | `{"status":"success","data":…}` / `{"status":"fail","error":"<readable>","errorcode":"<code>"}` |

Token API rules (shared by `local/payments/api.php`, `local/nit_commerce/api.php`, `local/nit_subscriptions/api.php`):

- HTTP **401** = `authrequired` / `invalidtoken` → log the user out. HTTP **403** = `siteunavailable`
  (academy suspended/expired) → show a blocking "academy unavailable" screen. Everything else is HTTP 200.
- Writes need **POST** (`application/x-www-form-urlencoded`); a GET returns `postrequired`.
- `lang=ar|en` (or `alang=`) picks the language of names and messages.
- Common errorcodes on every function: `nopermissions` (show "not allowed"), `missingparam` (app bug),
  `unknownfunction` (app bug), `internalerror` (show generic error, allow retry),
  `featureunavailable` (coupons/offers/subscriptions are not on the academy's licence → hide that feature).
- `nit_commerce/api.php` and `nit_subscriptions/api.php` also serve the admin web pages (session
  cookie + sesskey, envelope `status:"error"`). That mode is used **only when no token is sent** —
  the app must always send `token`.

WS tips: pass `moodlewssettingfilter=true` to WS calls so bilingual `{mlang}` course names are
resolved to the requested language (otherwise core course fields such as `fullname` in
`get_courses_with_pricing` come back with raw `{mlang ar}…{mlang}{mlang en}…{mlang}` markup).
Money in WS results is a major-unit float (`175.0` = 175 EGP). The new `local/payments/api.php`
returns both `*_minor` (int, piastres/cents) and the major float plus `currency`.

**There is no refund API.** Older docs mention `local_payments_get_provider_payment_methods`,
`local_payments_get_refund_options` and `local_payments_submit_refund` — **they do not exist**.
Refunds only arrive from the gateway webhook (status becomes `refunded`); admins can also revoke a
course purchase (`revoke_course_purchase`, below) with `refund=1` to mark it refunded (no money moves).

---

### 6.1 Payments

#### 6.1.1 Full course purchase flow (student)

1. **Price + state** — `local_payments_get_course_price(courseid)`
   - `is_enrolled` → open the course; `is_free` → call `enrol_course` (§6..3) instead of paying.
   - Optional: `local_payments_get_course_access(courseid)` → `has_pending_payment` / `order_id`
     tells you a checkout is already open (you can resume it by calling `create_checkout` again — it
     reuses the pending session when the amount is unchanged).
2. **Payment methods** — `local_payments_get_payment_methods(courseid)`. An empty list means no
   gateway is available for the user's country/currency → show "payment not available" and stop.
3. **Discounts** — `local_nit_commerce_preview_discount(item_type=course, item_id=courseid,
   coupon_code=<typed code or empty>)`. Show `final` as the price, `original` struck through when
   `discount > 0`. If `coupon_error` is non-empty, show it under the coupon field (the offer-only
   price is still returned). Offers apply automatically; the coupon is optional.
4. **Checkout** — `local_payments_create_checkout(courseid, coupon_code, lang)` → `checkout_url`,
   `order_id`, `amount` (charged, after discounts — show THIS), `expires_at`.
5. **Pay** — open `checkout_url` in an in-app browser / WebView (Kashier hosted page).
6. **Return** — when payment ends, the gateway redirects to
   `<site>/local/payments/callback.php?order_id=<order_id>&paymentStatus=SUCCESS|FAILED`.
   **Intercept that URL in the WebView and close it — do not load it** (it is a web page that needs a
   browser session). Take `order_id` from the URL (you also have it from step 4).
7. **Verify** — `local_payments_verify_payment(order_id)` → `success`, `status`, `courseid`,
   `enrolled`. `status=pending` → the webhook has not arrived yet: poll every 2–3 s for ~30 s.
   `failed` / `expired` → show failure + "try again" (go back to step 4).
8. **Done** — `success=true` and `enrolled=true` → open the course. The enrolment is made
   server-side (webhook or verify), the app never enrols a paid course itself.
   Receipts: `local_payments_get_payment_history`, `local_payments_get_invoice(transaction_id)`.

If the user closes the browser without paying, the pending transaction expires by itself
(`payment_ttl`, default 30 min).

#### 6.1.2 Web services (student)

All take optional `lang` / `alang`. Example responses are real (trimmed) from the test site.

| wsfunction | Params | Returns / notes |
|---|---|---|
| `local_payments_get_course_price` | `courseid` int (req), `country` ISO-2 (opt; otherwise detected) | `{"courseid":3,"country":"EG","currency":"EGP","price":175,"sale_price":0,"original_price":175,"discount_percentage":0,"is_sale_active":false,"sale_ends_at":0,"is_purchased":false,"is_enrolled":false,"is_free":false}` |
| `local_payments_get_payment_methods` | `courseid` (req), `country` (opt) | `[{"name":"kashier","display_name":"Kashier","priority":50}]` — `[]` = no gateway available |
| `local_payments_create_checkout` | `courseid` (req), `country` (opt), `lang` en/ar (gateway page language), `coupon_code` (opt) | `{order_id, checkout_url, expires_at, provider, transaction_id, amount, original_amount, currency}` |
| `local_payments_verify_payment` | `order_id` (req) | `{"success":true,"status":"completed","courseid":3,"enrolled":true}` (only the buyer may verify) |
| `local_payments_get_payment_history` | `page`, `perpage` (opt) | `[{"transaction_id":2,"order_id":"…","courseid":3,"course_name":"Arabic Language — Third Secondary","amount":157.5,"original_amount":175,"currency":"EGP","status":"completed","provider":"Kashier","payment_method":"card","invoice_number":"","timecreated":1791184879}]` — includes subscription/wallet payments (`courseid` 0, empty `course_name`) |
| `local_payments_get_purchased_courses` | — | `[{"courseid":3,"course_name":"…","amount":157.5,"currency":"EGP","order_id":"…","purchased_at":1791184879,"enrolled":true}]` |
| `local_payments_get_course_access` | `courseid` (req) | `{"courseid":3,"is_enrolled":false,"is_purchased":true,"payment_status":"completed","order_id":"…","has_pending_payment":false,"is_free":false}` |
| `local_payments_get_invoice` | `transaction_id` (req) | `{"invoice_number":"","amount":157.5,"original_amount":175,"currency":"EGP","status":"","order_id":"…","course_name":"…","payment_date":1791184879,"invoice_date":0}` — own transactions only |
| `local_payments_get_courses_with_pricing` | `field` + `value` (core `get_courses_by_field`: `id`, `ids`, `category`, …; empty = all), `country` (opt) | `{"courses":[{…core course fields…, "pricing_country":"EG","currency":"EGP","price":175,"sale_price":0,"original_price":175,"discount_percentage":0,"is_sale_active":false,"sale_ends_at":0,"is_free":false,"is_purchased":false,"is_enrolled":false}],"warnings":[]}` — use `moodlewssettingfilter=true` |

WS errorcodes the app should handle:

| errorcode | Where | App action |
|---|---|---|
| `noproviderfound` | create_checkout | "Payment not available in your region" |
| `alreadyenrolled` | create_checkout | Open the course |
| `alreadypurchased` | create_checkout | Open the course |
| `nopricefound` | get_course_price, create_checkout | No price rule for the user's country → "not available for purchase" |
| `err_couponnotfound`, `err_couponexpired`, `err_couponinactive`, `err_couponnotapplicable`, `err_couponusedup`, `err_couponalreadyusedbyuser`, `err_couponnotstarted` | create_checkout with `coupon_code` | Show `message` under the coupon field, let the user remove the coupon |
| `transactionnotfound` | verify_payment, get_invoice | Show "payment not found" |
| `invalidaccess` / `nopermissions` | verify_payment / get_invoice of another user's order | Should not happen; show generic error |
| `invalidtoken` / `accessexception` | any | Log out |

#### 6.1.3 Token API `local/payments/api.php` (admin / teacher tools)

`GET|POST /local/payments/api.php?function=<name>&token=<token>`

##### `get_revenue_report` — GET, needs `local/payments:viewreports` (managers)
Same numbers as `/local/payments/report.php` (completed transactions only), plus per-currency totals.
No params.
```json
{"status":"success","data":{"total_transactions":3,"total_revenue":467.5,
 "revenue_by_currency":[{"count":2,"revenue_minor":45750,"revenue":457.5,"currency":"EGP"},
                        {"count":1,"revenue_minor":1000,"revenue":10.0,"currency":"USD"}],
 "failed_count":1,"refund_count":0,"pending_count":0,
 "by_country":[{"country":"EG","count":2,"revenue_minor":45750,"revenue":457.5,"currency":"EGP"}],
 "by_provider":[{"providerid":1,"provider":"Kashier","count":2,"revenue_minor":45750,"revenue":457.5,"currency":"EGP"}],
 "top_courses":[{"courseid":3,"course":"Arabic Language — Third Secondary","purchases":1,"revenue_minor":15750,"revenue":157.5,"currency":"EGP"}]}}
```
`total_revenue` adds different currencies together (as the web page does) — display `revenue_by_currency`.
`pending_count` = pending and not yet expired. `top_courses` = top 20, course purchases only.

##### `get_transactions` — GET, needs `local/payments:viewalltransactions` (managers)
| Param | Type | Req | Description |
|---|---|---|---|
| `status` | string | no | `pending`, `completed`, `failed`, `cancelled`, `expired`, `timed_out`, `refunded`, `partially_refunded`, `voided`, `chargeback`, `duplicate` |
| `userid` | int | no | buyer |
| `courseid` | int | no | course (subscriptions/top-ups/packages have courseid 0) |
| `datefrom`, `dateto` | int (unix) | no | on creation time, inclusive |
| `page`, `perpage` | int | no | 0-based page, default 20, max 100 |
```json
{"status":"success","data":{"total":4,"page":0,"perpage":20,"transactions":[
 {"id":3,"order_id":"…","userid":12,"user_fullname":"طالب تجريبي","user_email":"nit_test_buyer@example.com",
  "item_type":"subscription","item_id":2,"courseid":0,"course_name":"","item_name":"ZZ plan",
  "amount_minor":30000,"amount":300.0,"original_amount_minor":30000,"original_amount":300.0,"currency":"EGP",
  "status":"completed","provider":"kashier","provider_display":"Kashier","payment_method":"wallet",
  "country":"EG","coupon_code":"","reject_reason":"","timecreated":1791188419,"timemodified":1791188479}]}}
```
`item_type`: `course`, `subscription`, `package`, `wallet_topup`. Errors: `invalidstatus`, `nopermissions`.

##### `get_course_prices` — GET, needs `local/payments:managecoursepricing` in the course (editing teacher of the course, manager)
| Param | Type | Req | Description |
|---|---|---|---|
| `courseid` | int | yes | |
```json
{"status":"success","data":{"course":{"id":3,"fullname":"Arabic Language — Third Secondary"},
 "prices":[{"id":1,"courseid":3,"country":"*","country_name":"Default (all countries)","currency":"EGP",
  "price_minor":17500,"price":175.0,"sale_price_minor":0,"sale_price":0.0,"start_date":0,"end_date":0,
  "is_default":true,"is_active":true,"timecreated":1791124068,"timemodified":1791124068}],
 "currencies":["USD","EGP","EUR","GBP","SAR","AED","KWD","BHD","QAR","OMR"]}}
```
A course with no active price is **free**. Errors: `coursenotfound`, `nopermissions`.

##### `save_course_price` — POST, same capability
Adds a rule (`priceid` 0/omitted) or edits one. Same rules as the web form.
| Param | Type | Req | Description |
|---|---|---|---|
| `courseid` | int | yes | |
| `priceid` | int | no | rule to edit |
| `country` | string | no | ISO-2 code (e.g. `EG`) or `*` = default for all countries (default `*`) |
| `currency` | string | yes | one of `currencies` |
| `price` | decimal | yes | major units, > 0 |
| `is_default` | 0/1 | no | default 0 — the FIRST rule of a course is always the default |
| `is_active` | 0/1 | no | default 1 |

Returns the saved rule (same shape as `prices[]`):
```json
{"status":"success","data":{"id":19,"courseid":2,"country":"EG","country_name":"Egypt","currency":"EGP","price_minor":9500,"price":95.0,…,"is_default":false,"is_active":true}}
```
Errors (show `error` next to the field): `pricepositive`, `invalidcurrency`, `invalidcountry`,
`onedefault` (another rule is already the default), `oneactivepercountry` (an active rule for that
country exists — deactivate or edit it), `pricenotfound`, `coursenotfound`, `nopermissions`, `postrequired`.

##### `delete_course_price` — POST, same capability
| Param | Type | Req |
|---|---|---|
| `courseid` | int | yes |
| `priceid` | int | yes |

`{"status":"success","data":{"deleted":true}}`. Errors: `pricenotfound`, `coursenotfound`, `nopermissions`.

##### `get_providers` — GET, needs `local/payments:manageproviders` (managers)
```json
{"status":"success","data":[{"id":1,"name":"kashier","display_name":"Kashier","plugin_name":"paymentprovider_kashier",
 "enabled":false,"priority":100,"supported_countries":["EG","SA","AE","KW","BH","QA","OM"],
 "supported_currencies":["EGP","USD","EUR","GBP","SAR","AED"],"timemodified":1786956118}]}
```
Empty `supported_*` array = all. Lower `priority` is tried first. Gateway credentials are web-only
(admin settings page).

##### `set_provider` — POST, same capability
| Param | Type | Req | Description |
|---|---|---|---|
| `id` | int | yes | provider id |
| `enabled` | 0/1 | no* | enable / disable |
| `priority` | int ≥ 0 | no* | ordering |

\* send at least one. Returns the provider (same shape). Errors: `providernotfound`,
`invalidpriority`, `nothingtochange`, `nopermissions`.

---

### 6.2 Coupons & offers

**Offers** are automatic discounts (no code). **Coupons** need a code typed at checkout. Both are
scoped to items (`course`, `package`, `subscription`, `program`) and stack: offer first, then coupon.
Both features can be switched off by the academy's licence → `featureunavailable` (token API) /
empty lists (WS).

#### 6.2.1 Web services (student)
| wsfunction | Params | Returns |
|---|---|---|
| `local_nit_commerce_get_available_coupons` | `lang` (opt) | `[{"id":1,"code":"ZZAPITEST","discount_type":"fixed","discount_value":20,"max_discount":null,"usage_type":"multiple","usage_limit":0,"startdate":0,"enddate":0,"status":"active","usage_count":0,"applies_to":[{"item_type":"course","item_id":3,"label":"…"}]}]` — public coupons to advertise |
| `local_nit_commerce_preview_discount` | `item_type` (`course`/`package`/`subscription`/`program`), `item_id`, `coupon_code` (opt), `country` (opt) | `{"original":175,"offers":[{"id":1,"name":"ZZ API test offer","discount":17.5}],"offer_id":1,"offer_name":"…","offer_discount":17.5,"coupon_id":1,"coupon_code":"ZZAPITEST","coupon_discount":20,"discount":37.5,"final":137.5,"coupon_error":""}` |

There is no WS for offers — use the token API below.

#### 6.2.2 Token API `local/nit_commerce/api.php`
`GET|POST /local/nit_commerce/api.php?function=<name>&token=<token>`

Student functions:

| function | Method | Params | Returns |
|---|---|---|---|
| `get_available_offers` | GET | — | `[{"id":1,"name":"ZZ API test offer","name_raw":"…","discount_type":"percent","discount_value":10,"startdate":0,"enddate":0,"status":"active","usage_count":0,"applies_to":[{"item_type":"course","item_id":3,"label":"Arabic Language — Third Secondary"}]}]` — active, in-window offers (use for an "Offers" screen / badges on course cards) |
| `get_available_coupons` | GET | — | same as the WS |
| `preview_discount` | GET | `item_type`, `item_id`, `coupon_code` (opt) | same as the WS (`coupon_error` only present when the coupon was rejected) |

Admin functions (managers; capability `local/nit_commerce:managecoupons` for coupons,
`local/nit_commerce:manageoffers` for offers):

| function | Method | Params | Returns |
|---|---|---|---|
| `get_coupons` | GET | — | all coupons (same shape as available coupons) |
| `create_coupon` | POST | `code` (req), `discount_type` `percent`/`fixed`, `discount_value`, `max_discount` (opt, percent cap), `usage_type` `once`/`multiple`, `usage_limit` (0 = unlimited), `startdate`, `enddate` (unix, 0 = open), `active` 0/1, `items` JSON `[{"item_type":"course","item_id":3}]` | `{"id":1}` |
| `update_coupon` | POST | `id` + same fields, `status` `active`/`inactive` instead of `active` | `[]` |
| `activate_coupon` / `deactivate_coupon` / `delete_coupon` | POST | `id` | `[]` (a used coupon cannot be deleted → `couponhasusages`, deactivate it) |
| `get_offers` | GET | — | all offers |
| `create_offer` | POST | `name` (req), `discount_type`, `discount_value`, `startdate`, `enddate`, `active`, `items` JSON | `{"id":1}` |
| `update_offer` | POST | `id` + same fields, `status` | `[]` |
| `activate_offer` / `deactivate_offer` / `delete_offer` | POST | `id` | `[]` (`offerhasusages` → deactivate instead) |
| `get_discount_targets` | GET | — | `{"categories":[{"id":1,"name":"…","courses":[{"id":15,"fullname":"…"}]}],"packages":[…],"subscriptions":[…],"programs":[]}` — choices for `items` (needs either capability) |

Errorcodes: `couponcoderequired`, `couponcodetaken`, `couponnotfound`, `offernamerequired`,
`offernotfound`, `discounttype`, `discountvalue`, `discountpercent`, `maxdiscount`, `daterange`,
`usagetype`, `status`, `itemtype`, `itemnotfound`, `couponhasusages`, `offerhasusages` — show `error`.
Plus the common ones (`nopermissions`, `postrequired`, `featureunavailable`).

---

### 6.3 Subscriptions

A subscription plan unlocks a set of courses for `duration_days`. Buying is a gateway checkout like a
course; after payment the user opens covered courses with `enrol_course` (enrolment ends when the
subscription expires). Only one active normal subscription at a time.

#### 6.3.1 Web services (student)
| wsfunction | Params | Returns |
|---|---|---|
| `local_nit_subscriptions_get_available_subscriptions` | `lang` (opt) | `[{"id":2,"name":"ZZ API test plan","description":"","price":300,"duration_days":30,"status":"active","b2b_enabled":0,"courses_count":1,"courses":[{"id":5,"fullname":"…"}],"seat_options":[],"offer_label":"","offer_final":0}]` |
| `local_nit_subscriptions_get_my_subscriptions` | — | `[{"id":2,"subscriptionid":2,"name":"ZZ API test plan","type":"normal","price_paid":300,"status":"active","timeactivated":1791188284,"expires_at":1793780284,"remaining_days":30,"duration_days":30,"courses":[{"id":5,"fullname":"…"}]}]` (active first) |
| `local_nit_subscriptions_get_subscription_payment_history` | — | `[{id, subscriptionid, name, order_id, amount, currency, status, payment_method, coupon_code, timecreated}]` |
| `local_nit_subscriptions_create_subscription_checkout` | `subscriptionid` (req), `type` `normal`/`b2b`, `seats` (b2b), `coupon_code`, `country`, `lang`, `return_url` | `{order_id, checkout_url, expires_at, provider, …}`. Errors: `err_subnotfound`, `err_checkoutfailed` (message e.g. "You already have an active subscription"), `err_paymentsunavailable` |

#### 6.3.2 Token API `local/nit_subscriptions/api.php` — student functions
`GET|POST /local/nit_subscriptions/api.php?function=<name>&token=<token>`

| function | Method | Params | Returns |
|---|---|---|---|
| `get_available_subscriptions` | GET | — | same as the WS |
| `get_my_active_subscription` | GET | — | `{"has_active":true,"subscriptionid":2,"expires_at":1793780284,"name":"ZZ API test plan","days_left":30,"price_paid":300}` (`has_active:false` and zeros when none) |
| `get_my_subscriptions` | GET | — | same as the WS |
| `get_subscription_payment_history` | GET | — | same as the WS |
| `create_subscription_checkout` | POST | same as the WS (`alang` for the gateway language) | same as the WS; errors `subnotfound`, `checkoutfailed`, `noproviderfound`, `paymentsunavailable` |
| `enrol_course` | POST | `courseid` | see below |

#### 6.3.3 `enrol_course` (new) — POST
Enrols the user into a course they may open **without paying**: a FREE course (no active price) or a
course covered by their active subscription. Same logic as the web page `/local/nit_subscriptions/enrol.php`.

| Param | Type | Req | Description |
|---|---|---|---|
| `courseid` | int | yes | |

```json
{"status":"success","data":{"courseid":5,"enrolled":true,"already_enrolled":false,"access":"subscription","timeend":1793780284}}
{"status":"success","data":{"courseid":2,"enrolled":true,"already_enrolled":false,"access":"free","timeend":0}}
{"status":"success","data":{"courseid":4,"enrolled":true,"already_enrolled":true,"access":"enrolled","timeend":0}}
{"status":"fail","error":"This course is paid and not covered by your subscription. Please buy it first.","errorcode":"paymentrequired"}
```
`access`: `free`, `subscription` (enrolment ends at `timeend` = subscription expiry), `enrolled` (was
already enrolled). Errorcodes: `paymentrequired` → start the purchase flow (§6..1) or offer a
subscription; `coursenotfound` (also for a hidden course); `enrolfailed` (retry / contact support); `postrequired`; `featureunavailable`.

**Flow — open a course:**
1. `local_payments_get_course_price` (or `get_courses_with_pricing`).
2. `is_enrolled` → open. `is_free` → `enrol_course` → open.
3. Paid: if `get_my_active_subscription.has_active` and the course is in the plan's `courses`
   (`get_my_subscriptions[].courses`) → `enrol_course` → open. Otherwise (or on `paymentrequired`)
   show "Buy" (§6..1) and/or "Subscribe".

**Flow — subscribe:** `get_available_subscriptions` → (optional) `local_nit_commerce_preview_discount
(item_type=subscription, item_id=<plan id>, coupon_code)` → `create_subscription_checkout` → open
`checkout_url` → intercept `…/local/payments/callback.php?order_id=…` → `local_payments_verify_payment(order_id)`
(`success:true` → the subscription is active) → refresh `get_my_active_subscription` → use `enrol_course`
for covered courses.

#### 6.3.4 Token API — admin functions (capability `local/nit_subscriptions:managesubscriptions`)

| function | Method | Params | Returns |
|---|---|---|---|
| `get_subscriptions` | GET | — | all plans (any status) |
| `create_subscription` | POST | `name` (req), `description`, `price` (req), `duration_days` (req), `active` 0/1, `b2b_enabled` 0/1, `seat_options` JSON `[{"seats":10,"discount_percent":5}]` | `{"id":2}` |
| `update_subscription` | POST | `id` + same fields, `status` `active`/`inactive` instead of `active` | `[]` |
| `activate_subscription` / `deactivate_subscription` | POST | `id` | `[]` |
| `delete_subscription` | POST | `id` | `[]` (`subhaspurchases` → deactivate instead) |
| `get_categories_with_courses` | GET | — | course picker tree |
| `set_subscription_courses` | POST | `subscriptionid`, `courseids` JSON `[5,6]` | `[{"id":5,"fullname":"…"}]` |
| `get_all_user_subscriptions` | GET | — | `[{"id":2,"userid":12,"user_fullname":"…","user_email":"…","name":"…","type":"normal","price_paid":300.0,"status":"active","expires_at":1793780284}]` |
| `unsubscribe_user` | POST | `purchaseid` | `[]` |
| `get_all_course_purchases` | GET | — | every paid single-course purchase |
| `revoke_course_purchase` | POST | `transactionid`, `refund` 0/1 | `[]` — unenrols the buyer; marks the transaction cancelled (or refunded; no money is returned by the gateway) |
| `get_reminder_settings` | GET | — | `{"enabled":true,"days":[30,7,1,0],"preview":{"due":0,"active":0},"max_days":365,"max_entries":10}` |
| `preview_reminder_settings` | GET | `days` e.g. `30,7,1` | `{"due":0,"active":0}` |
| `save_reminder_settings` | POST | `enabled` 0/1, `days` | settings + preview |

Errorcodes: `subnamerequired`, `subnameempty`, `pricenegative`, `durationpositive`, `subnotfound`,
`subhaspurchases`, `coursenotfound`, `seatspositive`, `discountrange`, `status`, `reminderdaysrequired`
— show `error`.

---

### 6.4 Admin functions — summary

| Task | Call | Who |
|---|---|---|
| Revenue dashboard | `payments/api.php get_revenue_report` | manager (`local/payments:viewreports`) |
| All transactions | `payments/api.php get_transactions` | manager (`viewalltransactions`) |
| Course prices | `payments/api.php get_course_prices / save_course_price / delete_course_price` | course editing teacher, manager (`managecoursepricing`) |
| Gateways on/off + order | `payments/api.php get_providers / set_provider` | manager (`manageproviders`) |
| Coupons / offers | `nit_commerce/api.php get_coupons … delete_offer` | manager |
| Subscription plans, subscribers, course purchases, reminders | `nit_subscriptions/api.php` admin functions | manager |
| Refunds | not available (webhook only) | — |

The app can decide which admin screens to show by trying the read call (`nopermissions` = hide) or
from the user's role.

---

## 7. Wallet, lessons sold one by one, codes, teacher earnings


Wallets, lessons sold one by one, access codes, teacher earnings and withdrawals.

### 7.1 Endpoint and rules

```
GET|POST {site}/local/nit_finance/api.php?function=<name>&token=<token>[&lang=ar|en]&...
```

- **Auth:** the user's web-service token (`token=` or `wstoken=`), the same token used by `/local/academy/api.php`.
- **Envelope:** `{"status":"success","data":{...}}` or `{"status":"fail","error":"<readable, translated>","errorcode":"<code>"}`.
- **HTTP codes:** `401` means `authrequired` or `invalidtoken` (log the user out). `403` means `siteunavailable` (the academy is suspended or expired). Every other result, including business errors, is HTTP `200`.
- **Writes** must be `POST` (form-encoded). Otherwise the call fails with `postrequired`.
- **`lang`** is optional (`ar` or `en`). It sets the language of messages and labels. The default is the user's language.
- **Money in:** send amounts in pounds as typed: `"150"`, `"99.5"`, `"١٥٠"`. Use at most 2 decimals.
- **Money out:** every amount appears twice: `<name>_minor` is an int in piastres (`15000`), and `<name>` is a float in pounds (`150.0`). A `currency` field is always `"EGP"`. Floats always include a decimal part (`150.0`). In Dart, read them as `num` or `double`.
- **Timestamps** are Unix seconds. Names and notes are already formatted (mlang filtered).
- **Paging** (where listed) uses `page` (0-based) and `perpage`. The default `perpage` is 20 (max 100) for student and teacher lists, and 50 (max 200) for admin lists. The response includes `total`, `page` and `perpage`.

#### Common errorcodes (any function)

| errorcode | Meaning / app action |
|---|---|
| `authrequired`, `invalidtoken` (HTTP 401) | Log out / re-login |
| `siteunavailable` (HTTP 403) | Show "academy unavailable" |
| `postrequired` | Bug in the app: send POST |
| `missingparam`, `invalidparameter` | Bug in the app: a parameter is missing or invalid |
| `nopermissions` | The user may not call this. Hide the feature |
| `unknownfunction` | Wrong function name |
| `internalerror` | Server error. Show a generic error and retry later |
| `walletbusy` | Another payment by the same user is running. Retry in a moment |

#### Who may call what

| Group | Functions | Rule |
|---|---|---|
| Student (any logged-in user) | get_wallet, get_wallet_history, get_my_purchases, create_topup_checkout, get_topup_status, redeem_code, get_course_lesson_prices, get_lesson_access, buy_lesson | Acts on the token's own wallet |
| Teacher | get_teacher_wallet, get_teacher_wallet_history, get_my_earnings, request_withdrawal, get_my_withdrawals | The user has a teacher or editing-teacher role anywhere, or already has earnings or a teacher wallet. Otherwise the call fails with `notateacher` |
| Admin | get_finance_summary, list_withdrawals, process_withdrawal, list_wallets, get_wallet_ledger, adjust_wallet, list_codes, generate_codes, disable_code | Capability `local/nit_finance:manage` (system), which the manager role has. Otherwise the call fails with `nopermissions` |
| Pricing | set_lesson_price | Capability `local/nit_finance:setprice` in the lesson's course (the manager role by default). Otherwise the call fails with `nopermissions` |

---

### 7.2 Student

#### get_wallet (GET)
Returns the student wallet balance and the top-up options. Takes no parameters.

```json
{"status":"success","data":{"balance_minor":15000,"balance":150.0,"balance_text":"150 ج.م","currency":"EGP",
 "topup":{"online_payment_available":false,"min_minor":1000,"min":10.0,"max_minor":5000000,"max":50000.0,
          "presets":[50.0,100.0,200.0,500.0]},
 "can_redeem_code":true,"is_teacher":false}}
```
- When `topup.online_payment_available` is `false`, hide or disable online top-up. Codes still work.
- When `is_teacher` is `true`, show the teacher earnings screens.

#### get_wallet_history (GET)
Returns the student wallet ledger, newest first.

| Param | Type | Req | Description |
|---|---|---|---|
| page | int | no | 0-based |
| perpage | int | no | default 20, max 100 |

```json
{"status":"success","data":{"type":"student","userid":12,"balance_minor":15000,"balance":150.0,"currency":"EGP",
 "total":6,"page":1,"perpage":2,"lines":[
  {"id":41,"kind":"adjustment","kind_label":"تسوية","amount_minor":-2025,"amount":-20.25,
   "balance_after_minor":15000,"balance_after":150.0,"itemtype":"","itemid":0,"purchaseid":0,
   "note":"API test: reverse code top-up","timecreated":1791188400},
  {"id":37,"kind":"topup","kind_label":"شحن رصيد","amount_minor":2025,"amount":20.25,"balance_after_minor":17025,
   "balance_after":170.25,"itemtype":"code","itemid":10,"purchaseid":0,"note":"CGF3-59WF-RJ8J","timecreated":1791188357}]}}
```
- `kind` is one of `topup`, `purchase`, `earning`, `withdrawal` or `adjustment`. `amount` is signed: positive means money in.
- `itemtype` is one of `cm` (lesson), `course`, `code`, `payment` (online top-up), `withdrawal`, `lesson` (live lesson) or `""`.

#### get_my_purchases (GET)
Returns the lessons and courses the student bought or unlocked, newest first. Takes no parameters.

```json
{"status":"success","data":{"purchases":[{"id":6,
  "item":{"type":"cm","id":34,"cmid":34,"courseid":4,"name":"درس فيديو تجريبي","coursename":"الفيزياء - الصف الثالث الثانوي",
          "type_label":"درس","price_minor":7550,"price":75.5},
  "amount_minor":5000,"amount":50.0,"currency":"EGP","method":"wallet","method_label":"المحفظة","timecreated":1791131738}]}}
```
- `item.type` is `cm` (one lesson; open it by `cmid`) or `course` (the whole course; `cmid` is 0).
- `method` is `wallet` or `code`.
- `amount` is what the student paid. `item.price` is the lesson's current price.

#### create_topup_checkout (POST)
Starts an online top-up of the student wallet through the payment gateway (Kashier).

| Param | Type | Req | Description |
|---|---|---|---|
| amount | string/number | yes | Pounds, from `topup.min` to `topup.max` (10 to 50,000) |
| return_url | string | no | A local path (for example `/local/nit_finance/wallet.php`) where the web callback redirects after payment. The app normally leaves it out |

Success response, built from `local_payments` (not captured live, because no gateway is enabled on the dev site):
```json
{"status":"success","data":{"order_id":"PAY-XXXX-2026-01234567","checkout_url":"https://checkout.kashier.io/...",
 "expires_at":1791190000,"provider":"kashier","transaction_id":321,"amount_minor":15050,"amount":150.5,
 "currency":"EGP","callback_url":"http://localhost:8082/local/payments/callback.php"}}
```
**Flow:**
1. Call `create_topup_checkout`, keep `order_id`, and open `checkout_url` in an in-app browser.
2. When the browser goes to a URL that starts with `callback_url` (success, or `paymentStatus=FAILED`), close it. That page requires a web login, so the app should not wait for it to render.
3. Call `get_topup_status(order_id)` until `credited` is `true`, or until `status` is final (not `pending`). The gateway webhook credits the wallet on the server. `get_topup_status` also asks the gateway when the webhook is late.
4. Refresh with `get_wallet`.

Errors:

| errorcode | When / app action |
|---|---|
| `amountpositive` | The amount is empty, invalid or ≤ 0. Ask again |
| `topuprange` | The amount is outside the min/max. The message names the limits |
| `paymentunavailable` | No online gateway is enabled. Hide top-up and suggest a code |
| `topup_badamount`, `paymentinitiationfailed` | Raised by local_payments (the gateway refused). Show the message and retry later |

Verified errors: `postrequired`, `amountpositive` (`amount=abc`), `topuprange` (`amount=5`), `paymentunavailable` (dev site), `missingparam`.

#### get_topup_status (GET)
Reports the state of the user's own online top-up.

| Param | Type | Req | Description |
|---|---|---|---|
| order_id | string | yes | From create_topup_checkout |

```json
{"status":"success","data":{"order_id":"PAY-...","status":"completed","paid":true,"credited":true,
 "amount_minor":15050,"amount":150.5,"balance_minor":30050,"balance":300.5,"currency":"EGP"}}
```
- `status` is a local_payments status: `pending`, `completed`, `failed`, `expired` and so on.
- Errors: `ordernotfound` (unknown order, someone else's order, or not a wallet top-up). Verified with `order_id=PAY-X-1`.

#### redeem_code (POST)
Redeems a code bought offline. The code can unlock a lesson or a whole course, or add wallet credit.

| Param | Type | Req | Description |
|---|---|---|---|
| code | string | yes | Any case. Dashes and spaces are optional (`cgf359wfrj8j` works) |

```json
{"status":"success","data":{"type":"wallet","item":null,"purchaseid":0,"amount_minor":2025,"amount":20.25,
 "balance_minor":17025,"balance":170.25,"currency":"EGP","message":"تم إضافة 20.25 ج.م لمحفظتك."}}
```
- For a lesson or course code, `type` is `cm` or `course`, and `item` has the same shape as in get_my_purchases. Open the lesson or course after redeeming.
- Show `message` to the user.

| errorcode | Meaning |
|---|---|
| `codeinvalid` | Unknown or disabled code |
| `codeused` | Already used |
| `codeexpired` | Expired |
| `alreadyowned` | The student already has this item. The code is kept unused |
| `itemnotfound` | The lesson or course of the code was deleted |

#### get_course_lesson_prices (GET)
Lists the lessons of a course that are sold on their own, with the student's state for each. It also says whether the whole course is sold or owned.

| Param | Type | Req | Description |
|---|---|---|---|
| courseid | int | yes | |

```json
{"status":"success","data":{"course":{"id":4,"fullname":"الفيزياء - الصف الثالث الثانوي","sold":true,
   "price":{"price_minor":20000,"price":200.0,"currency":"EGP"},"owned":false,"enrolled":true,"staff":false,"lessons_only":false},
 "balance_minor":15000,"balance":150.0,"currency":"EGP","lessons":[
  {"cmid":3,"name":"الدرس 2: دوائر التيار الكهربي","modname":"page","section":1,"price_minor":3000,"price":30.0,
   "owned":false,"open":false,"can_buy":true},
  {"cmid":34,"name":"درس فيديو تجريبي","modname":"vimeo","section":1,"price_minor":7550,"price":75.5,
   "owned":true,"open":true,"can_buy":false}]}}
```
- Lessons come in course order. Only lessons with a price are listed. Unpriced lessons follow the rules in get_lesson_access.
- `course.sold` means the whole course has an online price (`local_payments`). `course.price` is `null` when it is not sold. `course.owned` means the student bought, unlocked or subscribed to the whole course, so every lesson is open.
- `lessons_only` is `true` when the student got into a paid course only by buying single lessons. In that case unpriced lessons stay closed for them.
- `staff` is `true` for teachers and managers of the course. Everything is open for them.
- Errors: `coursenotfound` (missing, the site course, or hidden from the user). Verified with `courseid=99999`.

#### get_lesson_access (GET)
Says whether the student can open one lesson and whether they can pay for it from the wallet. Call it before opening a lesson, or when a lesson reports itself as locked.

| Param | Type | Req | Description |
|---|---|---|---|
| cmid | int | yes | Course module id |

```json
{"status":"success","data":{"cmid":3,"name":"الدرس 2: دوائر التيار الكهربي","modname":"page","courseid":4,
 "coursename":"الفيزياء - الصف الثالث الثانوي","priced":true,"price_minor":3000,"price":30.0,"currency":"EGP",
 "open":false,"owned":false,"owns_course":false,"staff":false,"enrolled":true,
 "balance_minor":15000,"balance":150.0,"can_afford":true,"shortfall_minor":0,"shortfall":0.0,
 "course_sold":true,"topup_available":false}}
```
Decide what to show from these fields:
- If `open`, open the lesson.
- If `can_afford`, offer "Pay `price` from wallet", which calls `buy_lesson`.
- If neither, the student is short by `shortfall`. Offer a top-up (when `topup_available`), a code, or buying the whole course (when `course_sold`).

If the student owns the lesson but the enrolment after payment had failed, this call retries the enrolment (as the web buy page does).

Errors: `itemnotfound` (missing, being deleted, or hidden). Verified with `cmid=999999`.

#### buy_lesson (POST)
Buys one lesson with the student wallet. The student is enrolled in the course if they were not already.

| Param | Type | Req | Description |
|---|---|---|---|
| cmid | int | yes | |

```json
{"status":"success","data":{"purchaseid":11,"cmid":3,"courseid":4,"name":"الدرس 2: دوائر التيار الكهربي",
 "amount_minor":3000,"amount":30.0,"balance_minor":14025,"balance":140.25,"currency":"EGP","enrolled":true,"open":true}}
```

| errorcode | Meaning / app action |
|---|---|
| `insufficientwallet` | Not enough balance. Offer a top-up or a code |
| `alreadyowned` | The lesson is already open (bought, course owned, or the user is staff) |
| `notforsale` | The lesson has no price of its own |
| `itemnotfound` | The lesson is missing or hidden |
| `walletbusy` | Retry |
| `noenrol` | Manual enrolment is disabled on the site (an admin problem) |

---

### 7.3 Teacher

> **Two balances.** Teacher money is tracked in two places:
> - **The earnings and withdrawals model** (`nit_earning` − `nit_withdrawal`). This model gives `available_balance`, `total_earned`, `pending_withdrawals` and `total_withdrawn`. **Withdrawal limits use it.** Show `available_balance` as "what you can withdraw".
> - **The teacher wallet ledger** (`nit_wallet`), returned as `wallet_balance` and by `get_teacher_wallet_history`. Each sale credits it, and a **paid** withdrawal debits it.
>
> The two figures differ because pending and approved withdrawals reduce only `available_balance`. Earnings recorded before wallets existed also appear only in the earnings model, so `wallet_balance` can be lower, or even negative after old earnings are paid out. Example from the dev site: `available_balance` 0, `wallet_balance` 0, `total_earned` 40, `total_withdrawn` 40.

#### get_teacher_wallet (GET)
Takes no parameters.
```json
{"status":"success","data":{"teacherid":4,"available_balance_minor":1450,"available_balance":14.5,
 "total_earned_minor":9000,"total_earned":90.0,"pending_withdrawals_minor":3550,"pending_withdrawals":35.5,
 "total_withdrawn_minor":4000,"total_withdrawn":40.0,"wallet_balance_minor":0,"wallet_balance":0.0,"currency":"EGP",
 "teacher_percent":40,"can_withdraw":true,
 "methods":[{"value":"bank","label":"Bank transfer"},{"value":"wallet","label":"Mobile wallet"},{"value":"cash","label":"Cash"}]}}
```
`teacher_percent` is the teacher's share of each sale: their own value or the site default.

#### get_teacher_wallet_history (GET)
Takes `page` and `perpage`. Returns the teacher wallet ledger, with the same shape as get_wallet_history (`type` is `"teacher"`). The `kind` values are `earning` (a sale or live lesson), `withdrawal` (paid out) and `adjustment` (a reversed live lesson).

#### get_my_earnings (GET)
Takes `page` and `perpage`. Returns every earning, newest first.
```json
{"status":"success","data":{"total":1,"page":0,"perpage":20,"earnings":[{"id":1,"source":"cm","lessonid":1,"cmid":1,
 "item_name":"Announcements","courseid":2,"coursename":"Business Administration 1","studentid":3,"student_name":"Sara NITTest",
 "value_minor":10000,"value":100.0,"teacher_amount_minor":4000,"teacher_amount":40.0,"teacher_percent":40,"currency":"EGP",
 "status":"active","status_label":"Counted","timecreated":1785917112}]}}
```
- `source` is `cm` for a lesson sold on its own (`cmid` is set) or `lesson` for a live lesson paid with Flex (`lessonid` is the live-lesson id and `cmid` is 0).
- `value` is the amount the student paid. `teacher_amount` is the teacher's share.
- `status` is `active` (counted) or `reversed`.

#### request_withdrawal (POST)

| Param | Type | Req | Description |
|---|---|---|---|
| amount | string/number | yes | Pounds, > 0 and ≤ `available_balance` |
| method | string | no | `bank` (default), `wallet` or `cash` |
| account | string | no | Payout details (IBAN, phone…), up to 255 characters |

```json
{"status":"success","data":{"withdrawal":{"id":4,"teacherid":4,"teacher_name":"Tarek NITTest","amount_minor":2550,"amount":25.5,
  "currency":"EGP","method":"wallet","method_label":"Mobile wallet","account":"01000000000","reference":"","status":"pending",
  "status_label":"Pending","reason":"","timecreated":1791188295,"timeprocessed":0},
 "available_balance_minor":2450,"available_balance":24.5,"currency":"EGP"}}
```

| errorcode | Meaning |
|---|---|
| `notateacher` | The user is not a teacher |
| `amountpositive` | The amount is invalid or ≤ 0 |
| `insufficientbalance` | The amount is more than the available balance |
| `badmethod` | `method` is not bank, wallet or cash |
| `busy` | Another request from the same teacher is running. Retry |

#### get_my_withdrawals (GET)
Returns `{"withdrawals":[ <withdrawal>, ... ]}`, newest first. Each item has the same shape as above.

Withdrawal `status` moves through these states:
- `pending` → `approved` → `paid`
- `pending` or `approved` → `rejected`. Then `reason` is set and the amount returns to `available_balance`.

When a withdrawal is `paid`, `reference` holds the payment reference.

---

### 7.4 Admin (`local/nit_finance:manage`)

#### get_finance_summary (GET)
Takes no parameters.
```json
{"status":"success","data":{"currency":"EGP","platform_wallet":{"balance_minor":3000,"balance":30.0},
 "wallets":{"student":{"count":1,"total_minor":15000,"total":150.0},"teacher":{"count":1,"total_minor":2000,"total":20.0}},
 "platform_report":{"current_money_minor":96000,"current_money":960.0,"undistributed_money_minor":90000,"undistributed_money":900.0,
   "teachers_money_minor":2000,"teachers_money":20.0,"platform_earnings_minor":9000,"platform_earnings":90.0,
   "total_payments_minor":100000,"total_payments":1000.0,"total_paid_out_minor":4000,"total_paid_out":40.0},
 "withdrawals":{"pending":{"count":0,"amount_minor":0,"amount":0.0},"approved":{...},"rejected":{...},
   "paid":{"count":1,"amount_minor":4000,"amount":40.0}},
 "codes":{"active":2,"used":1,"disabled":1},
 "sales":{"wallet":{"count":1,"amount_minor":5000,"amount":50.0},"code":{"count":0,"amount_minor":0,"amount":0.0}}}}
```
- `platform_wallet` is the platform's ledger balance (its share of lesson sales, live lessons and expired Flex).
- `platform_report` contains the same cards as the web page "Financial reports" (`manage_withdrawals.php`). Total payments and undistributed money come from the Flex plugin. They are 0 when that plugin is missing.
- `sales` counts active lesson and course purchases by payment method.

#### list_withdrawals (GET)

| Param | Type | Req | Description |
|---|---|---|---|
| status | string | no | `pending`, `approved`, `rejected` or `paid`. Empty means all |
| page, perpage | int | no | Default 50, max 200 |

Returns `{"total","page","perpage","withdrawals":[<withdrawal>]}`, newest first. Each item has the same shape as for teachers, with `teacher_name`.
Errors: `badstatus`.

#### process_withdrawal (POST)

| Param | Type | Req | Description |
|---|---|---|---|
| id | int | yes | Withdrawal id |
| action | string | yes | `approve`, `reject` or `pay` |
| reason | string | for reject | Required when rejecting |
| reference | string | no | Payment reference (for `pay`) |

```json
{"status":"success","data":{"withdrawal":{"id":4,"teacherid":4,"teacher_name":"Tarek NITTest","amount_minor":2550,"amount":25.5,
 "currency":"EGP","method":"wallet","method_label":"محفظة موبايل","account":"01000000000","reference":"","status":"rejected",
 "status_label":"مرفوض","reason":"API test cleanup","timecreated":1791188295,"timeprocessed":1791188324}}}
```
`pay` also debits the teacher wallet ledger.

| errorcode | Meaning |
|---|---|
| `badaction` | `action` is not one of the three |
| `withdrawalnotfound` | Wrong id |
| `withdrawalstate` | The action is not allowed in the current state (for example `pay` on a pending request, or approving twice) |
| `reasonrequired` | Reject without a reason |

#### list_wallets (GET)

| Param | Type | Req | Description |
|---|---|---|---|
| type | string | yes | `student` or `teacher` |
| q | string | no | Matches part of the full name, email, username or phone |
| page, perpage | int | no | Default 50, max 200 |

```json
{"status":"success","data":{"type":"teacher","total":1,"page":0,"perpage":50,"wallets":[{"userid":5,"fullname":"Admin User",
 "email":"Admin@gmail.com","balance_minor":2000,"balance":20.0,"currency":"EGP","timemodified":1791133123,
 "teacher_percent":40,"available_balance_minor":2000,"available_balance":20.0}]}}
```
- Only users who already have a wallet are listed. Order is biggest balance first.
- Teacher rows add `teacher_percent` and the withdrawable `available_balance`.
- Errors: `badwallettype`.

#### get_wallet_ledger (GET)

| Param | Type | Req | Description |
|---|---|---|---|
| type | string | yes | `platform`, `teacher` or `student` |
| userid | int | not for platform | Owner of the wallet |
| page, perpage | int | no | Default 50, max 200 |

Has the same shape as get_wallet_history, plus `fullname` (empty for the platform):
```json
{"status":"success","data":{"fullname":"Admin User","type":"teacher","userid":5,"balance_minor":2000,"balance":20.0,"currency":"EGP",
 "total":1,"page":0,"perpage":2,"lines":[{"id":15,"kind":"earning","kind_label":"أرباح","amount_minor":2000,"amount":20.0,
 "balance_after_minor":2000,"balance_after":20.0,"itemtype":"cm","itemid":34,"purchaseid":6,"note":"درس فيديو تجريبي","timecreated":1791131738}]}}
```
Errors: `badwallettype`, `usernotfound`.

#### adjust_wallet (POST)
Adds credit to, or takes credit from, a **student** wallet, like the web page "Wallets".

| Param | Type | Req | Description |
|---|---|---|---|
| userid | int | yes | The student |
| amount | string | yes | Signed pounds: `50` adds credit, `-20.5` takes it |
| note | string | no | Shown in the ledger |

```json
{"status":"success","data":{"userid":12,"fullname":"طالب تجريبي","line":{"id":41,"kind":"adjustment","kind_label":"تسوية",
 "amount_minor":-2025,"amount":-20.25,"balance_after_minor":15000,"balance_after":150.0,"itemtype":"","itemid":0,"purchaseid":0,
 "note":"API test: reverse code top-up","timecreated":1791188400},"balance_minor":15000,"balance":150.0,"currency":"EGP"}}
```
- A positive amount is recorded as `topup` and a negative one as `adjustment`.
- Errors:
  - `amountnonzero`: zero or invalid amount.
  - `usernotfound`.
  - `insufficientwallet`: you cannot take more than the balance. The message is worded for the student ("your wallet").

#### list_codes (GET)

| Param | Type | Req | Description |
|---|---|---|---|
| status | string | no | `active` (unused), `used` or `disabled`. Empty means all |
| batch | string | no | Exact batch id (from generate_codes) |
| q | string | no | Part of the code, any case |
| page, perpage | int | no | Default 50, max 200 |

```json
{"status":"success","data":{"total":1,"page":0,"perpage":5,"codes":[{"id":12,"code":"2PSC-P8YC-VJ2P","itemtype":"cm","itemid":34,
 "courseid":4,"item_label":"درس: الفيزياء - الصف الثالث الثانوي › درس فيديو تجريبي","amount_minor":7550,"amount":75.5,
 "currency":"EGP","status":"active","status_label":"غير مستخدم","batch":"B261005091906-VFkx","note":"API test","timeexpires":0,
 "expired":false,"createdby":13,"usedby":0,"usedby_name":"","timeused":0,"purchaseid":0,"timecreated":1791188346}]}}
```
Newest codes come first. `timeexpires` is 0 when the code never expires.

#### generate_codes (POST)

| Param | Type | Req | Description |
|---|---|---|---|
| type | string | yes | `cm` (one lesson), `course` or `wallet` (credit) |
| itemid | int | for cm/course | cmid or courseid |
| count | int | yes | 1–500 |
| amount | string | see below | Pounds the student paid offline. For `cm` it may be empty, which means the lesson's price. It is required for `course`. It must be > 0 for `wallet` |
| expires | string | no | `0` or empty means never. Also accepts a future Unix time, or a future date `YYYY-MM-DD` (valid until the end of that day) |
| note | string | no | For example who bought the codes |

```json
{"status":"success","data":{"batch":"B261005091904-fNp5","count":2,"itemtype":"wallet","itemid":0,"amount_minor":2025,"amount":20.25,
 "currency":"EGP","timeexpires":1830297599,"codes":[{"id":10,"code":"CGF3-59WF-RJ8J"},{"id":11,"code":"QBZV-UBNA-3P6A"}]}}
```
The amount of a lesson or course code is split between the teacher and the platform when the code is redeemed. A wallet code only adds credit.

| errorcode | Meaning |
|---|---|
| `chooseitem` | Bad `type`, or a missing `itemid` |
| `itemnotfound` | The lesson or course does not exist |
| `badprice` | The amount is invalid (or empty for course or wallet) |
| `amountpositive` | A wallet code with amount 0 |
| `codecount` | `count` is outside 1–500 |
| `badexpiry` | The date is in the past or invalid |

#### disable_code (POST)

| Param | Type | Req | Description |
|---|---|---|---|
| id | int | yes | Code id |

Returns `{"code": <code row as in list_codes, status "disabled">}`.
Errors: `codenotfound`, `codenotactive` (the code is already used or disabled).

#### set_lesson_price (POST)
Needs `local/nit_finance:setprice` in the lesson's course. This is the same field as "Lesson price" in the activity settings.

| Param | Type | Req | Description |
|---|---|---|---|
| cmid | int | yes | |
| price | string | yes | Pounds. `0` or empty removes the price (the lesson is no longer sold on its own) |

```json
{"status":"success","data":{"cmid":2,"courseid":4,"name":"الدرس 1: قانون أوم","priced":true,"price_minor":1250,"price":12.5,"currency":"EGP"}}
```
Errors: `nopermissions`, `itemnotfound`, `badprice`.

---

## 8. Lesson packages (Flex) and live 1:1 lessons

A **package** gives the student **Flex**. One Flex books one live 1:1 lesson of 60 minutes with any
teacher who takes bookings. The lesson runs in a Jitsi room. A student holds **one active package at a
time**.

The money follows the same rules as the website:
- The student pays for the package from the wallet or online. Coupons and offers apply.
- The money stays undistributed until a lesson uses a Flex.
- **Completed lesson:** the teacher's share (their own percent, else the site default) goes to the
  teacher wallet, and the rest goes to the platform. It appears in §7 `get_my_earnings` with
  `source:"lesson"`.
- **Student absent / late cancel:** the Flex is used and the platform keeps its whole value.
- **Teacher absent / teacher cancel / early cancel:** the Flex goes back to the student.
- **Flex left when a package ends:** it expires, and its value goes to the platform.

### 8.1 Endpoints and rules

```
GET|POST {site}/local/nit_flex/api.php?function=<name>&token=<token>[&lang=ar|en]&...      (packages)
GET|POST {site}/local/nit_lessons/api.php?function=<name>&token=<token>[&lang=ar|en]&...   (lessons)
```

- Same token, envelope, HTTP codes, `POST` rule, money and paging rules as §7.1.
- **Times** are Unix seconds. Send `time` as one of the `time` values returned by `get_teacher_slots`.
- **Licence:** when the academy's plan has no `packages` feature, every call fails with
  `featureunavailable`. Hide the feature.
- **Who may call what:**

| Group | Functions | Rule |
|---|---|---|
| Student (any logged-in user) | `nit_flex`: get_packages, get_package_quote, buy_package_wallet, create_package_checkout, get_package_checkout_status, get_my_flex, get_my_packages, get_package_payments, get_flex_history · `nit_lessons`: get_teachers, get_teacher_slots, request_lesson | Acts for the token's user |
| Student or teacher of the lesson | get_my_lessons, get_lesson, get_lesson_join and the lesson actions (§8.4) | The engine checks that the user is this lesson's student or teacher and that the lesson is in the right state (`forbidden`, `badstate`) |
| Teacher | get_teacher_profile, update_teacher_profile | The user has a teacher or editing-teacher role in any course. Otherwise `notateacher` |
| Admin | `nit_flex`: `admin_*` | Capability `local/nit_flex:managepackages` (manager) |
| Admin | `nit_lessons`: `admin_*` | Capability `local/nit_lessons:managesettings` (manager) |

**Teacher earnings and withdrawals** are not here. They use the §7.3 functions on
`/local/nit_finance/api.php`.

---

### 8.2 Packages — student (`/local/nit_flex/api.php`)

#### get_packages (GET)
Returns the packages for sale, the student's active package and the wallet balance. Takes no
parameters.
```json
{"status":"success","data":{"packages":[{"id":6,"name":"flex 10","description":"flex 10 description","flex_count":10,
 "expiration_days":0,"validity_label":"Never expires","status":"active","price_minor":20000,"price":200.0,
 "final_price_minor":20000,"final_price":200.0,"price_per_flex_minor":2000,"price_per_flex":20.0,"currency":"EGP"}],
 "can_buy":false,
 "active":{"id":12,"packageid":6,"name":"flex 10","total_flex":10,"remaining_flex":9,"reserved_flex":0,"consumed_flex":1,
  "status":"active","status_label":"Active","source":"online","timeactivated":1791189719,"expires_at":0,
  "price_paid_minor":20000,"price_paid":200.0,"currency":"EGP"},
 "online_payment":false,"wallet_balance_minor":45000,"wallet_balance":450.0,"currency":"EGP"}}
```
- `final_price` is the price after automatic offers (no coupon). Show `price` crossed out when the two
  differ.
- `can_buy` is `false` while a package is active. Disable the buy buttons and show `active`.
- `online_payment` is `false` when no gateway is set up. Hide "Pay online" and keep "Pay from wallet".
- `expires_at` is `0` when the package never expires.

#### get_package_quote (GET)
Returns the price to pay, with an optional coupon. Call it when the buy sheet opens and again on
"Apply coupon".

| Param | Type | Req | Description |
|---|---|---|---|
| packageid | int | yes | package |
| coupon_code | text | no | coupon as typed |

```json
{"status":"success","data":{"packageid":6,"coupon_code":"NOPE","coupon_applied":false,"coupon_error":"Coupon not found.",
 "original_price_minor":20000,"original_price":200.0,"discount_minor":0,"discount":0.0,
 "final_price_minor":20000,"final_price":200.0,"wallet_balance_minor":45000,"wallet_balance":450.0,
 "can_pay_wallet":false,"can_pay_online":false,"currency":"EGP"}}
```
- An invalid coupon does **not** fail the call. It returns the price without the coupon and puts the
  reason in `coupon_error`. Show that reason under the coupon box.
- `can_pay_wallet` is `false` when the balance is below `final_price` or a package is already active.
  Offer the §7 top-up in that case.

Errors: `notfound`, `packagenotavailable`.

#### buy_package_wallet (POST)
Buys the package with the student wallet. The coupon is reserved in the same step, so a coupon that
has just run out cancels the purchase.

| Param | Type | Req | Description |
|---|---|---|---|
| packageid | int | yes | package |
| coupon_code | text | no | coupon |

```json
{"status":"success","data":{"purchase":{"id":13,"packageid":6,"name":"flex 10","total_flex":10,"remaining_flex":10,
 "reserved_flex":0,"consumed_flex":0,"status":"active","status_label":"Active","source":"online","timeactivated":1791200000,
 "expires_at":0,"price_paid_minor":16000,"price_paid":160.0,"currency":"EGP"},
 "wallet_balance_minor":29000,"wallet_balance":290.0,"currency":"EGP"}}
```
Errors: `insufficientwallet` (→ top up), `alreadyhaspackage`, `packagenotavailable`, `notfound`,
`walletbusy`, coupon codes (`couponnotfound`, `couponexpired`, `couponusedup`, …).

#### create_package_checkout (POST)
Starts an online payment.

| Param | Type | Req | Description |
|---|---|---|---|
| packageid | int | yes | package |
| coupon_code | text | no | coupon |

```json
{"status":"success","data":{"order_id":"NIT-…","checkout_url":"https://checkout.kashier.io/…","expires_at":1791201800,
 "transaction_id":88,"amount_minor":16000,"amount":160.0,"original_amount_minor":20000,"original_amount":200.0,
 "currency":"EGP","callback_url":"https://…/local/payments/callback.php"}}
```

Flow:
1. Open `checkout_url` in a web view.
2. When the web view reaches `callback_url…`, close it.
3. Poll `get_package_checkout_status` with `order_id`.

Errors: `noonline` (no gateway → use the wallet), `alreadyhaspackage`, `freeonwallet` (the discount
makes it free → use `buy_package_wallet`), `packagenotavailable`, `paymentinitiationfailed`.

#### get_package_checkout_status (GET)
Returns where an online payment stands. While the payment is not complete, it asks the gateway, which
grants the package if the payment went through.

| Param | Type | Req | Description |
|---|---|---|---|
| order_id | text | yes | from create_package_checkout |

```json
{"status":"success","data":{"order_id":"NIT-…","status":"completed","paid":true,"granted":true,"purchaseid":13,
 "credited_to_wallet":false,"active":{"id":13,"…":"…"},"amount_minor":16000,"amount":160.0,"currency":"EGP"}}
```
- `granted:true`: done. Show `active`.
- `credited_to_wallet:true`: the order was paid while another package was already active. The money
  went to the wallet instead. Tell the student.
- `paid:false`: still `pending`, or `failed` / `cancelled`. Poll a few times, then show the status.

Errors: `ordernotfound`.

#### get_my_flex (GET)
Returns the Flex balance for the header or badge. Takes no parameters.
```json
{"status":"success","data":{"available_flex":9,"reserved_flex":0,"has_package":true,"active":{"id":12,"…":"…"}}}
```
`reserved_flex` counts the Flex booked for confirmed lessons that have not happened yet.

#### get_my_packages (GET)
Returns `{"packages":[<purchase>…]}`, active first. Each package has the same shape as `active` above.
`status` is one of `active`, `fully_used`, `expired`, `cancelled`.

#### get_package_payments (GET)
Returns package payments, newest first. The `amount` of a `refund` row is negative.
```json
{"status":"success","data":{"payments":[{"id":5,"packageid":6,"name":"flex 10","method":"wallet","method_label":"Wallet",
 "transaction_no":"TXNC9CEF46BAE8949","reference":"","status":"success","timecreated":1791189719,
 "amount_minor":20000,"amount":200.0,"currency":"EGP"}]}}
```
`method` is one of `wallet`, `online`, `offline`, `bank`, `cash`, `refund`.

#### get_flex_history (GET)
Takes `page` and `perpage` (default 50, max 200). Returns the Flex ledger, newest first.
```json
{"status":"success","data":{"total":3,"page":0,"perpage":50,"history":[{"id":46,"purchaseid":12,"lessonid":14,
 "type":"consume","amount":0,"balance_before":9,"balance_after":9,"reason":"Lesson completed","timecreated":1791190275,
 "type_label":"Used"}]}}
```
- `type` is one of `purchase`, `assign`, `reserve` (−1, booked), `consume` (0, used), `return` (+1),
  `expire`, `adjust` (removed by an admin).
- `amount` is the change to the available balance.

---

### 8.3 Booking — student (`/local/nit_lessons/api.php`)

Flow:
1. `get_teachers`.
2. `get_teacher_slots(teacherid)`.
3. The student picks a subject (from the teacher's `subjects`) and a free slot, and writes a note.
4. `request_lesson`.

Nothing is reserved yet. The Flex is reserved when the teacher accepts.

#### get_teachers (GET)
Returns the teachers who take bookings.

| Param | Type | Req | Description |
|---|---|---|---|
| subject | text | no | filter: subject contains this text (any case) |

```json
{"status":"success","data":{"teachers":[{"id":22,"fullname":"مدرس الحصص","headline":"مدرس فيزياء",
 "subjects":[{"value":"فيزياء","name":"فيزياء"},{"value":"Physics","name":"Physics"}],
 "picture_url":"https://…/theme/image.php/nit/core/…/u/f1"}]}}
```
Send `subjects[].value` back as `subject`.

#### get_teacher_slots (GET)
Returns the teacher's one-hour slots for the next 14 days. Days with no free slot are left out.

| Param | Type | Req | Description |
|---|---|---|---|
| teacherid | int | yes | teacher |
| lessonid | int | no | when moving a lesson, its own time counts as free |

```json
{"status":"success","data":{"teacherid":22,"duration":60,"teacher_timezone":"Africa/Cairo","subjects":["فيزياء","Physics"],
 "days":[{"date":1791241200,"date_label":"Tuesday, 6 October 2026",
   "slots":[{"time":1791277200,"label":"10:00 AM","free":true},{"time":1791280800,"label":"11:00 AM","free":false}]}]}}
```
- Show taken slots disabled (`free:false`). Slots are built from the teacher's weekly hours (08:00–20:00
  when they set none), minus booked lessons and the minimum booking notice.
- `label` is in the user's timezone.

Errors: `teachernotbookable`.

#### request_lesson (POST)
Books a lesson. The student needs an active package with Flex left.

| Param | Type | Req | Description |
|---|---|---|---|
| teacherid | int | yes | teacher |
| subject | text | yes | one of the teacher's `subjects[].value` |
| time | int | yes | a free slot `time` |
| note | text | yes | what the student wants to study |

Returns the new lesson (§8.4 shape) with `status:"pending"`. The teacher gets a notification.

Errors: `noflex` (→ packages), `teachernotbookable`, `subjectnotoffered`, `outsidehours`, `timeconflict`,
`minbooking`, `noterequired`, `subjectrequired`, `selfbooking`, `teachernotfound`.

---

### 8.4 Lessons — student and teacher (`/local/nit_lessons/api.php`)

#### get_my_lessons (GET)
Returns the user's lessons in this order: needs an answer first, then upcoming (soonest first), then
past.

| Param | Type | Req | Description |
|---|---|---|---|
| role | text | no | `student`, `teacher`, or empty for both |
| status | text | no | filter, one of the statuses below |

#### get_lesson (GET) — `lessonid`
Returns one lesson. Shape:
```json
{"status":"success","data":{"id":15,"studentid":12,"teacherid":22,"subject":"Physics","status":"confirmed",
 "requested_time":1791284400,"confirmed_time":1791284400,"effective_time":1791284400,"duration":60,"note":"API test",
 "reject_reason":null,"cancel_reason":null,"flex_state":"reserved","actual_start":0,"actual_end":0,"cmid":0,
 "teacher_name":"مدرس الحصص","student_name":"طالب تجريبي","timecreated":1791200000,"timemodified":1791200100,
 "proposals":[],"my_role":"teacher","status_label":"Confirmed","flex_state_label":"Flex booked",
 "pending_suggestion":null,"pending_reschedule":null,
 "actions":[{"action":"start","function":"start_lesson","value":"","needs":"none","label":"Start lesson"},
            {"action":"cancel","function":"cancel_lesson_teacher","value":"","needs":"reason_required","label":"Cancel lesson"},
            {"action":"request_time_update","function":"request_time_update","value":"","needs":"time","label":"Change time"}],
 "can_join":false,"join_cmid":0}}
```

**Status values:**

| status | Meaning |
|---|---|
| `pending` | New request, waiting for the teacher |
| `waiting_student` | The teacher suggested another time, waiting for the student |
| `waiting_teacher` | The student suggested another time, waiting for the teacher |
| `confirmed` | Booked. The Flex is reserved |
| `in_progress` | The teacher started it. Join the room |
| `completed` | Done. The Flex was used |
| `student_absent` | Done. The Flex was used |
| `teacher_absent` | The Flex was returned |
| `cancelled` | Cancelled by the student |
| `cancelled_teacher` | Cancelled by the teacher |
| `rejected` | Declined by the teacher |

- `flex_state` is one of `none`, `reserved`, `consumed`, `returned`.
- `effective_time` is the time to show: the confirmed time, or else the requested time.
- `pending_suggestion` is the time the other side suggested (`proposed_time`, `role`) while the status is
  `waiting_*`. `pending_reschedule` is an open "change time" request on a confirmed lesson.

**Actions.** Draw one button per `actions[]` item. Each item tells you which function to call and
what to ask first:

| needs | Ask first, then send |
|---|---|
| `none` | nothing (show a confirmation) |
| `reason` | optional text → `reason` |
| `reason_required` | required text → `reason` |
| `note` | optional text → `reason` (completion note) |
| `time` | a slot from `get_teacher_slots(teacherid, lessonid)` → `time` |

#### Lesson action functions (POST)
Every action takes `lessonid`, plus `value`, `time` or `reason` as the action's `needs` says. Each one
returns the updated lesson. The other side gets a notification.

| function | value | Who | Effect |
|---|---|---|---|
| `teacher_respond_lesson` | `accept` / `reject` / `suggest` (+`time`) | teacher | accept → `confirmed` and the Flex is reserved. reject → `rejected`. suggest → `waiting_student` |
| `student_respond_lesson` | `accept` / `reject` / `suggest` (+`time`) | student | accept → `confirmed` and the Flex is reserved. reject → `cancelled`. suggest → `waiting_teacher` |
| `start_lesson` | — | teacher | → `in_progress`. Creates the Jitsi room. Allowed from 30 minutes before the start (setting) |
| `complete_lesson` | — (`reason` = note) | teacher | → `completed`. The Flex is used and the teacher is paid. Allowed 45 minutes after the start (setting) |
| `report_student_absent` | — | teacher | → `student_absent`. The Flex is used and the platform keeps it. Allowed 15 minutes after the start time |
| `report_teacher_absent` | — | student | → `teacher_absent`. The Flex is returned |
| `cancel_lesson_request` | — | student | Withdraws a request before it is confirmed. Nothing was reserved |
| `cancel_lesson_student` | — | student | Cancels a confirmed lesson. Up to 120 minutes before (setting) the Flex is returned. Later, the Flex is used |
| `cancel_lesson_teacher` | — (`reason` required) | teacher | → `cancelled_teacher`. The Flex is returned |
| `request_time_update` | — (+`time`) | either | Asks to move a confirmed lesson. Allowed until 120 minutes before (setting) |
| `respond_time_update` | `accept` / `reject` | the other side | Answers a move request |

Errors: `forbidden`, `badstate` (the lesson changed: refresh), `lessonnotfound`, `badaction`,
`reasonrequired`, `notime`, `minbooking`, `timeconflict`, `tooearlytostart`, `completetooearly`,
`absencetooearly`, `updatedeadline`, `updatepending`, `noupdaterequest`, `noflex`, `nolessonscourse`
(rooms not set up by the admin).

#### get_lesson_join (GET) — `lessonid`
Returns the room of a lesson that has started (`can_join:true`).
```json
{"status":"success","data":{"lessonid":14,"cmid":69,"sessionid":7,"is_teacher":true,
 "join_url":"https://…/mod/jitsi/view.php?id=69"}}
```
Open it like any live-session room (§4.1–4.2): `mod_jitsi_get_session_info(cmid)` (or
`/mod/jitsi/api_token.php?id=cmid`) and join with the Jitsi SDK.
- The teacher calls `set_teacher_present(cmid, 1/0)` (§4.1) on conference joined / left.
- The student is held in the lobby until the teacher is in the call.

Errors: `roomnotready`, `forbidden`.

---

### 8.5 Teacher profile (`/local/nit_lessons/api.php`)

#### get_teacher_profile (GET)
Takes no parameters.
```json
{"status":"success","data":{"available":true,"headline":"مدرس فيزياء","subjects":["فيزياء","Physics"],
 "hours":[{"dayofweek":0,"starttime":"10:00","endtime":"22:00"}],"bookable":true,"timezone":"Africa/Cairo",
 "days":[{"value":0,"label":"Sunday"},{"value":1,"label":"Monday"}]}}
```
- `bookable` is `true` when students can book this teacher: `available` is on and there is at least one
  subject.
- `dayofweek` runs from `0` (Sunday) to `6`. Times are in the teacher's `timezone`.

#### update_teacher_profile (POST)
Replaces the whole profile and returns it.

| Param | Type | Req | Description |
|---|---|---|---|
| available | 0/1 | no | students can book (default 0) |
| headline | text | no | short line under the name |
| subjects | JSON array of strings | yes | e.g. `["فيزياء","Physics"]` |
| hours | JSON array | no | `[{"dayofweek":0,"starttime":"16:00","endtime":"20:00"}]`. Each range must be at least 1 hour. Empty means 08:00–20:00 every day |

Errors: `notateacher`, `subjectsrequired` (available without a subject), `badhours`, `invalidparameter`
(bad JSON).

---

### 8.6 Admin

#### Packages (`/local/nit_flex/api.php`, `local/nit_flex:managepackages`)

| function | Method | Params | Returns |
|---|---|---|---|
| `admin_list_packages` | GET | — | `packages[]`: the package shape plus `name_ar`, `name_en`, `description_ar`, `description_en`, `purchases` |
| `admin_save_package` | POST | `id` (0 = new), `name_ar`, `name_en`, `description_ar`, `description_en`, `flex_count`, `price` (pounds), `expiration_days` (0 = never), `active` (0/1) | the package |
| `admin_set_package_status` | POST | `id`, `active` (0/1) | `{id, status}` |
| `admin_delete_package` | POST | `id` | `{id, deleted}`. Only packages nobody bought |
| `admin_assign_package` | POST | `studentid`, `packageid`, `amount` (pounds, empty = price), `method` (`offline`, `bank`, `cash`, `wallet`), `reference` | the purchase. `wallet` takes the amount from the student wallet |
| `admin_list_purchases` | GET | `page`, `perpage` | `purchases[]`: each purchase has `student_name`, `student_email`, `can_unassign`, and the purchase fields |
| `admin_unassign_package` | POST | `purchaseid`, `refund` (0/1) | `{purchaseid, cancelled, refunded_minor, refunded}`. The refund is the value of the unused Flex, and it goes back to the student wallet |

Errors: `nameflexrequired`, `flexpositive`, `daysnegative`, `badprice`, `packageinuse` (bought before:
deactivate it instead), `studentnotfound`, `studenthaspackage`, `insufficientwallet`, `notfound`.

#### Live lessons (`/local/nit_lessons/api.php`, `local/nit_lessons:managesettings`)

| function | Method | Params | Returns |
|---|---|---|---|
| `admin_list_lessons` | GET | `status`, `page`, `perpage` | `lessons[]`: each with `id`, `student_name`, `teacher_name`, `subject`, `status`, `status_label`, `flex_state`, `time`, `teacher_amount_minor`, `platform_amount_minor`, `can_reverse` |
| `admin_reverse_flex` | POST | `lessonid`, `reason` | `{lessonid, reversed}`. Returns the Flex to the student and takes the teacher and platform shares back from their wallets |
| `admin_get_settings` | GET | — | `min_booking_minutes`, `cancel_deadline_minutes`, `update_deadline_minutes`, `start_allowed_minutes`, `complete_allowed_minutes`, `absence_report_minutes`, `lessons_courseid`, `rooms_ready` |
| `admin_update_settings` | POST | `settings` = JSON object of the keys above (minutes, ≥ 0) | the settings |

Errors: `reasonrequired`, `earningnotfound`, `alreadyreversed`, `settingnegative`.

---

## 9. Standard Moodle web services the app uses

Style B: `/webservice/rest/server.php?wstoken=<token>&moodlewsrestformat=json&moodlewssettingfilter=true&moodlewssettinglang=<ar|en>&wsfunction=<name>`.
All are in the `moodle_mobile_app` service (verified on this site).

| Need | wsfunction | Key params |
|---|---|---|
| Site + current user (id, name, picture, site name, functions) | `core_webservice_get_site_info` | — |
| Public site config (no token, via `service-nologin.php`) | `tool_mobile_get_public_config` | — |
| My courses | `core_enrol_get_users_courses` | `userid` |
| My courses by timeline (in progress / future / past) | `core_course_get_enrolled_courses_by_timeline_classification` | `classification=all\|inprogress\|future\|past` |
| Course sections + activities (raw) | `core_course_get_contents` | `courseid` (the app normally uses `getalltopics.php`, §2.4) |
| Courses by id / category | `core_course_get_courses_by_field` | `field=id\|ids\|category`, `value` |
| Course search | `core_course_search_courses` | `criterianame=search`, `criteriavalue` |
| Activity completion of a course | `core_completion_get_activities_completion_status` | `courseid`, `userid` |
| Mark an activity done / not done (manual completion) | `core_completion_update_activity_completion_status_manually` | `cmid`, `completed=1\|0` |
| Log a view of a core activity | `mod_page_view_page`, `mod_resource_view_resource`, `mod_url_view_url`, `mod_assign_view_assign`, … | instance id (e.g. `pageid`) |
| Assignments (submit) | `mod_assign_get_submission_status`, `mod_assign_save_submission`, `mod_assign_submit_for_grading` | `assignid` … |
| Quizzes (Moodle engine, webview-free alternative to §3.6) | `mod_quiz_get_quizzes_by_courses`, `mod_quiz_start_attempt`, `mod_quiz_get_attempt_data`, `mod_quiz_save_attempt`, `mod_quiz_process_attempt`, `mod_quiz_get_attempt_review` | — |
| Grades | `gradereport_user_get_grade_items`, `gradereport_overview_get_course_grades` | `courseid` |
| User profile of someone (e.g. a teacher) | `core_user_get_users_by_field` | `field=id`, `values[0]=` |
| Avatar | `core_user_update_picture` | `draftitemid` (from `/webservice/upload.php`), `delete` |
| Preferences | `core_user_get_user_preferences`, `core_user_set_user_preferences` | — |
| Notifications list | `message_popup_get_popup_notifications` | `useridto`, `limit`, `offset` |
| Unread notifications badge | `message_popup_get_unread_popup_notification_count` | `useridto` |
| Mark notifications read | `core_message_mark_all_notifications_as_read` | `useridto` |
| Conversations / messages | `core_message_get_conversations`, `core_message_get_conversation_messages`, `core_message_send_instant_messages` | — |
| Push notifications device | `core_user_add_user_device` (+ `message_airnotifier_enable_device`) | `appid`, `name`, `model`, `platform`, `version`, `pushid`, `uuid` |
| Calendar / deadlines | `core_calendar_get_action_events_by_timesort`, `core_calendar_get_calendar_upcoming_view` | — |
| Webview auto-login (open a web page logged in) | `tool_mobile_get_autologin_key` (needs `privatetoken`) | `privatetoken` |
| Delete my account (store requirement) | `tool_dataprivacy_create_data_request` | `type=2` (delete), `comments` |

Also in the service (documented in their sections): `local_payments_*` (9), `local_nit_commerce_*` (2),
`local_nit_subscriptions_*` (4), `local_nit_videoprogress_save`, `local_nit_ai_ask`,
`local_nit_ai_get_status`, `local_profilefields_get_profile_fields`, `mod_jitsi_get_session_info`,
`mod_jobform_*` (7).

---

## 10. Reference

### 10.1 Error codes

Codes shared by every style-A endpoint are in §1.6. Business codes by area (show `error` to the user
unless an action is listed):

| Area | errorcodes |
|---|---|
| Site / auth | `authrequired`, `invalidtoken` (401 → log out), `siteunavailable` (403 → blocking screen), `nopermissions` (hide the feature) |
| Registration | `invalidregistration` (+ `errors` per field), `registrationdisabled`, `toomanyregistrations` |
| Profile | `requiredfield`, `invalidphone`, `invalidnationalid`, `invalidoption`, `divisionmismatch`, `invalidlanguage` |
| Password | `invalidemail`, `toomanyrequests`, `otpinvalid`, `otpexpired`, `otplocked`, `resetexpired`, `weakpassword`, `wrongpassword`, `authnochange` |
| Courses / lessons | `coursenotfound`, `notenrolled`, `lessonlocked` (→ resume lesson), `lessonforsale` (→ buy flow), `coursenotfree`, `enrolfailed`, `paymentrequired` (→ buy / subscribe), `categorynotfound` |
| Quizzes | `notenrolled`, `attemptsexhausted`, `attemptalreadyclosed`, `notyourattempt`, `invalidquestionid` |
| Teachers | `teachernotfound` |
| Video | `vimeoapi`, `vdocipherapi`, `notvideo`, `invalidslices` |
| Reviews | `cannotrate`, `invalidrating`, `reviewtoolong`, `reviewnotfound`, `toomanycourses` |
| Parent | `invalidphone`, `studentnotfound`, `toomanyattempts` (wait 15 min) |
| Live sessions | `sessionnotfound`, `sessionnotavailable`, `sessionended`, `notallowed`, `notjoined`, `notjitsiactivity`, `notsessionteacher`, `studentnotenrolled`, `invalidvalue` |
| Wallet / finance | `insufficientwallet`, `alreadyowned`, `notforsale`, `itemnotfound`, `walletbusy`, `noenrol`, `amountpositive`, `topuprange`, `paymentunavailable`, `ordernotfound`, `codeinvalid`, `codeused`, `codeexpired`, `notateacher`, `insufficientbalance`, `badmethod`, `busy`; admin: `badaction`, `withdrawalnotfound`, `withdrawalstate`, `reasonrequired`, `badstatus`, `badwallettype`, `usernotfound`, `amountnonzero`, `chooseitem`, `badprice`, `codecount`, `badexpiry`, `codenotfound`, `codenotactive` |
| Lesson packages | `featureunavailable` (hide the feature), `alreadyhaspackage`, `packagenotavailable`, `notfound`, `noonline`, `freeonwallet`, `ordernotfound`, `insufficientwallet`; admin: `nameflexrequired`, `flexpositive`, `daysnegative`, `packageinuse`, `studentnotfound`, `studenthaspackage` |
| Live lessons | `noflex` (→ packages), `teachernotbookable`, `subjectnotoffered`, `outsidehours`, `timeconflict`, `minbooking`, `noterequired`, `subjectrequired`, `selfbooking`, `forbidden`, `badstate` (refresh), `lessonnotfound`, `notime`, `tooearlytostart`, `completetooearly`, `absencetooearly`, `updatedeadline`, `updatepending`, `noupdaterequest`, `reasonrequired`, `roomnotready`, `nolessonscourse`, `notateacher`, `subjectsrequired`, `badhours`; admin: `earningnotfound`, `alreadyreversed`, `settingnegative` |
| Payments / commerce | `featureunavailable` (hide the feature), coupon codes (`couponnotfound`, `couponexpired`, …), admin: `pricepositive`, `invalidcurrency`, `invalidcountry`, `onedefault`, `oneactivepercountry`, `pricenotfound`, `providernotfound`, `invalidpriority`, `nothingtochange`, `invalidstatus`, subscription admin codes (§6.3.4) |

### 10.2 Endpoint index

**Style A — `/local/<plugin>/api.php?function=…`** (S = student/any user, T = teacher, M = manager,
P = shared pre-login token is enough; ✱ = new in this version)

| Plugin | Functions |
|---|---|
| `academy` | P: `get_home_selected`✱, `get_home_teachers`✱, `get_home_lessons`✱, `get_teacher_page`✱, `get_course_page`✱, `get_registration_form`✱, `register_student`✱, `request_password_otp`, `verify_password_otp`, `reset_password`, `get_profile_fields`✱, `get_academic_structure`✱, `browse_teachers`, `get_teacher`, `get_teacher_courses` · S: `change_password`, `get_my_profile`, `get_full_profile`✱, `update_my_profile`✱, `get_course_lessons`✱, `log_lesson_view`✱, `get_my_certificates`✱, `is_course_free`, `enrol_free_course`, `get_quizzes`, `get_quiz`, `start_quiz_attempt`, `save_quiz_answer`, `finish_quiz_attempt`, `submit_quiz_attempt`, `get_quiz_attempt`, `get_my_quiz_attempts` · M: `get_all_teachers`, `get_license_status` |
| `nit_category` ✱ | P/S: `get_categories`, `get_courses` |
| `nit_finance` ✱ | S: `get_wallet`, `get_wallet_history`, `get_my_purchases`, `create_topup_checkout`, `get_topup_status`, `redeem_code`, `get_course_lesson_prices`, `get_lesson_access`, `buy_lesson` · T: `get_teacher_wallet`, `get_teacher_wallet_history`, `get_my_earnings`, `request_withdrawal`, `get_my_withdrawals` · M: `get_finance_summary`, `list_withdrawals`, `process_withdrawal`, `list_wallets`, `get_wallet_ledger`, `adjust_wallet`, `list_codes`, `generate_codes`, `disable_code`, `set_lesson_price` |
| `nit_flex` ✱ | S: `get_packages`, `get_package_quote`, `buy_package_wallet`, `create_package_checkout`, `get_package_checkout_status`, `get_my_flex`, `get_my_packages`, `get_package_payments`, `get_flex_history` · M: `admin_list_packages`, `admin_save_package`, `admin_set_package_status`, `admin_delete_package`, `admin_assign_package`, `admin_list_purchases`, `admin_unassign_package` |
| `nit_lessons` ✱ | S: `get_teachers`, `get_teacher_slots`, `request_lesson`, `student_respond_lesson`, `report_teacher_absent`, `cancel_lesson_request`, `cancel_lesson_student` · S/T: `get_my_lessons`, `get_lesson`, `get_lesson_join`, `request_time_update`, `respond_time_update` · T: `teacher_respond_lesson`, `start_lesson`, `complete_lesson`, `report_student_absent`, `cancel_lesson_teacher`, `get_teacher_profile`, `update_teacher_profile` · M: `admin_list_lessons`, `admin_reverse_flex`, `admin_get_settings`, `admin_update_settings` |
| `payments` ✱ | M: `get_revenue_report`, `get_transactions`, `get_providers`, `set_provider` · T/M: `get_course_prices`, `save_course_price`, `delete_course_price` |
| `nit_commerce` (token mode ✱) | S: `get_available_offers`, `get_available_coupons`, `preview_discount` · M: `get_coupons`, `create_coupon`, `update_coupon`, `activate_coupon`, `deactivate_coupon`, `delete_coupon`, `get_offers`, `create_offer`, `update_offer`, `activate_offer`, `deactivate_offer`, `delete_offer`, `get_discount_targets` |
| `nit_subscriptions` (token mode ✱) | S: `get_available_subscriptions`, `get_my_active_subscription`, `get_my_subscriptions`, `get_subscription_payment_history`, `create_subscription_checkout`, `enrol_course`✱ · M: `get_subscriptions`, `create_subscription`, `update_subscription`, `activate_subscription`, `deactivate_subscription`, `delete_subscription`, `get_categories_with_courses`, `set_subscription_courses`, `get_all_user_subscriptions`, `unsubscribe_user`, `get_all_course_purchases`, `revoke_course_purchase`, `get_reminder_settings`, `preview_reminder_settings`, `save_reminder_settings` |
| `nit_reviews` ✱ | S: `get_course_ratings`, `get_course_reviews`, `get_my_review`, `save_review`, `delete_my_review` · T/M: `delete_review` |
| `nit_videoprogress` ✱ | S: `save_progress`, `get_progress`, `get_course_progress`, `get_overview` |
| `academysessions` | S: `get_student_sessions`, `join_session`, `leave_session`✱, `get_session_recordings`✱ · T: `create_session`, `get_teacher_sessions`, `start_session`✱, `update_session`, `end_session`, `delete_session`, `get_attendance`, `set_teacher_present`✱, `end_room`✱ |
| `vimeo`, `vdocipher` | S: `get_playback` · T: `create_upload`, `video_status`, `list_videos`, `attach_video`, `delete_video` |
| `parent` ✱ | P: `get_child_report` |

**Style B — new/changed Moodle web services:** `local_nit_ai_get_status`✱,
`mod_jobform_get_submissions`✱, `mod_jobform_get_submission`✱, `mod_jobform_delete_submission`✱,
`mod_jitsi_get_session_info` (recordings now filled), `local_payments_get_courses_with_pricing` /
`get_payment_history` / `get_purchased_courses` / `get_invoice` (fixes).

**Style C — raw endpoints:** `/login/token.php`, `/local/googleauth/token.php`,
`/local/multitopics/getsettings.php`, `/theme/nit/design_system.php`, `/local/multitopics/legal.php`,
`/local/multitopics/getalltopics.php`, `/local/academy/certificate.php`, `/local/academy/qfile.php`
(quiz images, URLs come ready-made), `/mod/jitsi/api_token.php`, `/webservice/upload.php`,
`/lib/ajax/service-nologin.php`.

### 10.3 What changed for the existing app

Backwards compatible: every existing function name, parameter and response field is kept. Things to
know when updating the app:

0. **Bassthalk screens are new:** `get_home_selected`, `get_home_teachers`, `get_home_lessons`,
   `get_teacher_page`, `get_course_page` (§3.10) — work with the shared token for visitors.
   **Student registration is new too:** `get_registration_form` + `register_student` (§3.1) create an
   active account with all the academy fields and return its token. Use them instead of Moodle's
   `auth_email_signup_user` (which needs email confirmation and does not take the academy fields).
1. **One envelope everywhere (style A):** every failure now also carries `errorcode`; business errors
   now show their real message (before: a generic "internal error"). HTTP 401 for a missing/dead token
   is now used by **all** token endpoints (VdoCipher used to answer 200), and HTTP 403
   `siteunavailable` when the academy is suspended/expired.
2. **Lesson rules enforced in the API:** `vimeo` / `vdocipher` `get_playback` and `getalltopics.php`
   now apply the lesson-order lock and per-lesson sale like the website: expect `lessonlocked` /
   `lessonforsale`, and empty `embedurl` / `otpurl` / `fileurl` for locked or unpaid items.
3. **`getalltopics.php`:** restricted activities are now returned greyed-out (`restricted:true` +
   `restrictioninfo`) instead of being dropped; new fields `locked`, `forsale`, `completed`,
   `completiontracking`, `watched_percent`; accepts `token=` as well as `wstoken=`; full token checks.
4. **Live sessions:** session rows now include `cmid` and `teacher_present`; `get_teacher_sessions` with
   a `courseid` requires staff rights in that course; only a session's own teacher (or a
   manager/admin) may start, end, update or delete it; `create_session` checks that the listed students
   are enrolled; mobile teachers can now open the student gate (`set_teacher_present`).
5. **`mod/jitsi/api_token.php`:** full token validation (expiry, IP, service, suspended account) and
   `errorcode` in errors; also accepts `token=`.
6. **Google sign-in:** refuses sign-in while the academy is suspended (`site_unavailable`).
7. **`design_system.php`:** `site.provisioned` defaults to `true` when not configured (it wrongly said
   `false`); `site.status` can now be `suspended`.
8. **Older docs were wrong about:** `local_profilefields_get_signup_form`, `…_signup_user`,
   `…_get_profile`, `…_update_profile` (never existed — use §3.1 / §3.3);
   `local_payments_get_provider_payment_methods`, `local_payments_get_refund_options`,
   `local_payments_submit_refund` (do not exist — there is no refund API); several `getsettings.php`
   keys (`app_name`, `apple_client_id`, `facebook_app_id`, `links` are not returned — links are in
   `design_system.php`).

### 10.4 Known limitations

- Payments go through the Kashier hosted page; the app opens `checkout_url` and must intercept the
  return URL `…/local/payments/callback.php?…` (it needs a browser session), then verify with
  `local_payments_verify_payment` / `get_topup_status` / `get_package_checkout_status` (§8.2).
- No refund API (refunds arrive only through the gateway webhook).
- The AI assistant supports VdoCipher lessons only.
- The parent throttle is per IP and shared with the website (10 wrong attempts → 15 minutes).
- Teacher money has two figures (withdrawable balance vs. wallet ledger) — see §7.
- Live lessons: slots are one hour, built from the teacher's weekly hours in the teacher's timezone. The
  room is a Jitsi activity in the course chosen in the admin settings (`rooms_ready` in
  `admin_get_settings`); without it `start_lesson` fails with `nolessonscourse`.
