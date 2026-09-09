# Mobile App ↔ Academy API — integration guide

How the mobile app talks to a single academy. Every academy is its own Moodle
site at `https://<slug>.academy2026.nitg-eg.com`; the app is one binary that
points at whichever academy the user opened. There is **no cross-academy API** —
the app always talks to one academy's host.

> Academy **create / edit / delete** is **not** an app action — it's done by NIT
> staff / the client in the **web dashboard** (nit2). See the last section for
> that lifecycle; the app only *consumes* a live academy.

---

## 1. Base URL & the two auth models

- **Base URL:** `https://<slug>.academy2026.nitg-eg.com` (the academy the user
  is on). Discover per-academy config from its `getsettings.php` first.
- **TLS:** each academy has its own Let's Encrypt cert for its subdomain.

There are **two kinds of token**, do not mix them:

| Token | Where it comes from | Used for |
|---|---|---|
| **Admin token** (`admin_token`) | published in `getsettings.php` | *pre-login, public* calls only — registration, reading public settings. It's the academy's shared service token. |
| **User token** (`wstoken`) | `POST /login/token.php` after the user logs in | *everything the logged-in user does* — profile, courses, payments, quizzes, video. |

All web-service calls send the token as `wstoken=<token>` (Moodle WS) or
`token=<token>` (our custom `api.php` endpoints), plus `moodlewsrestformat=json`
for Moodle WS.

### Two response conventions
1. **Moodle web services** (`/webservice/rest/server.php?wsfunction=…`): return
   the function's data directly, or an error object
   `{ "exception": …, "errorcode": "…", "message": "…" }`. The one to handle
   globally is `errorcode: "invalidtoken"` → token dead → send the user to login.
2. **Our custom endpoints** (`/local/academy/api.php`, `/local/vimeo/api.php`,
   `/local/vdocipher/api.php`): a uniform envelope
   `{ "status": "success", "data": … }` or
   `{ "status": "fail", "error": "<readable>", "errorcode": "<code>" }`.
   **HTTP 401 is reserved for a dead token** (`authrequired` / `invalidtoken`) —
   that is the app's signal to end the session and return to login. Everything
   else stays HTTP 200 with `status: "fail"`.

---

## 2. App bootstrap (before login)

Call these first, unauthenticated, to configure the app for this academy.

| Endpoint | Method | Returns |
|---|---|---|
| `/local/multitopics/getsettings.php` | GET | App settings: `admin_token`, `app_name`, OAuth client ids (`google_client_id`, `apple_client_id`, `facebook_app_id`), store versions/URLs (`android_version`, `android_url`, `ios_version`, `ios_url`), watermark style, legal `links`. **Every value is a string.** |
| `/theme/nit/design_system.php` | GET | Branding + theme: `site` (name, url, `supportedapp`, `provisioned`, `status`, `defaultlanguage`, `languages`, `package`, and the **`features`** map), `brandcolors` (3 groups × 16 roles), `fonts`, `categorystyles`, `links`, and — **when the academy has uploaded assets** — `branding` (`logo`/`logomark`/`loginhero` as `{light,dark}`, `splash`, `courseplaceholder`, `emptystate`). |

Notes:
- **`site.supportedapp: false`** ⇒ the app must **refuse to open** this academy
  (it's a demo/unsupported tier). `site.status` (`active`/`suspended`) and
  `site.provisioned` gate access too.
- **`features` map** — a key missing = that feature is OFF. Includes
  `payments`, `coupons`, `subscriptions`, `vdocipher`, `vimeo`, `watermark`,
  `jobform`, `google_login`, `apple_login`, `facebook_login`, plus always-on
  cores. Branch app UI on this.
- **`branding` asset URLs load anonymously** (public pluginfile URLs). If a URL
  ever returns Moodle's login page, treat it as absent and fall back.
- `components` and `generated` are web/metadata — ignore them.

---

## 3. Authentication

### Login (get a user token)
```
POST /login/token.php
  username=<user>&password=<pass>&service=moodle_mobile_app
→ { "token": "<wstoken>", "privatetoken": "…" }
```
On bad credentials Moodle returns `{ "error": "Invalid login", … }`. A **blocked**
account is distinguished by `local_academy` (see `login_manager`) so the app can
show "account suspended" rather than a generic error.

### Password reset (OTP flow) — custom
```
GET/POST /local/academy/api.php?function=request_password_otp   (email)
POST     /local/academy/api.php?function=verify_password_otp    (email, otp)
POST     /local/academy/api.php?function=reset_password         (…, new password)
```
### Change password (logged in)
```
POST /local/academy/api.php?function=change_password&token=<wstoken>
```
> ⚠️ Any password change **invalidates the user's web-service tokens** — after
> `change_password` / `reset_password`, the app must re-login to get a fresh
> `wstoken`.

### Logout
Drop the stored `wstoken` locally (Moodle tokens are long-lived; there's no
server logout call needed for the app).

---

## 4. Registration / signup

Preferred: the dedicated signup service (validates against the academy's profile
fields + policies):

| Function (`/webservice/rest/server.php`) | Purpose |
|---|---|
| `local_profilefields_get_signup_form` | Fields + rules to render the signup screen |
| `local_profilefields_get_policy_documents` | Terms/privacy to accept at signup |
| `local_profilefields_signup_user` | Create the account |
| `local_profilefields_resend_confirmation` | Re-send the confirmation email |

Legacy/alternative: `core_user_create_users` with the **admin token** (nested
params `users[0][username|password|firstname|lastname|email|auth]`). Passwords
must satisfy the academy's policy (currently relaxed: min length 6).

---

## 5. Profile

| Function | Auth | Purpose |
|---|---|---|
| `local_profilefields_get_profile` / `local_profilefields_get_profile_form` | user | Read profile + the editable form spec |
| `local_profilefields_update_profile` | user | Save profile |
| `local_profilefields_get_completion_status` | user | Profile-completion gate |
| `/local/academy/api.php?function=get_my_profile` | user | App-shaped profile summary |
| `core_user_update_picture` | user | Avatar upload |
| `core_user_get_user_preferences` / `core_user_set_user_preferences` | user | Preferences |

---

## 5.1 Admin / owner: academy plan, subscription & payments

For the academy's **admin / owner** accounts logging in from the app (not
students). Everything here is academy-side (this Moodle) — the academy's *plan*
comes from `local_license`; *payments* are what students paid in this academy.

| What | Endpoint | Auth |
|---|---|---|
| **Package resources + subscription (mobile)** | `GET /local/academy/api.php?function=get_license_status&token=<wstoken>` | admin/owner (`local/academy:manageplatform`) |
| **Package resources + subscription (web)** | `/local/license/status.php` (Moodle admin page) | site admin |
| **Previous payments** (students' purchases in this academy) | `local_payments_get_payment_history` (Moodle WS) | user token |

`get_license_status` returns (mirrors the web status page, for the app):
```jsonc
{ "status": "success", "data": {
  "package": {                       // the licence's RESOURCES
    "enforced": true, "tier": "basic", "name": "Basic",
    "videosource": "vimeo",
    "features": ["coupons","subscriptions", …],
    "storagegb": 5,                                                    // GB moodledata quota
    "limits": { "maxcourses": 3, "maxteachers": 1, "quiz": -1, "video": -1, "pdf": -1 },  // -1 = unlimited
    "billing_in_nit2": true          // price + purchase date + upgrade/renew are in nit2 (below)
  },
  "usage": { "courses": 2, "teachers": 1, "quiz": 5, "video": 12, "pdf": 3 },   // live counts vs limits
  "subscription": {                  // the current term
    "expiry": 1790000000, "expirydate": "2026-09-01T…", "daysleft": 42,
    "is_expired": false, "is_suspended": false, "status": "active"   // active | expired | suspended
  }
} }
```
- Non-admin/owner tokens get `{ "status":"fail", "errorcode":"nopermissions" }`
  (HTTP 200 — do **not** log out; just hide the screen).
- `features` and `package.code/name` are *also* in `design_system.php` for the
  student UI; `get_license_status` adds the **numeric limits, live usage, and the
  subscription term** that only an admin/owner needs.

> **Price, purchase date, and upgrade/renew are NOT here** — the academy's Moodle
> can't take payment for its own plan. For the full billing view + actions, the
> app calls **nit2** (the owner's platform account):
> - `GET /api/academies/<slug>/plan` — full package (incl. **price**), subscription
>   (`subscribedAt`, `validUntil`, auto-renew, card), payments, and an `actions` block.
> - **Renew:** `POST /api/payments/kashier/create {purpose:"renew", slug, tier, cycle}`.
> - **Upgrade:** `POST /api/payments/kashier/create {purpose:"upgrade", slug, tier:<new>, cycle}` (prorated).
> - **Auto-renew:** `PATCH /api/subscriptions/<slug> {autoRenew}`.
>
> So: this academy endpoint = in-app **enforcement view** (limits, GB, usage,
> expiry) for the admin/owner; nit2 = **billing + upgrade/renew**.

## 6. Courses & content

- **Aggregated course content for the app:**
  `/local/multitopics/getalltopics.php` (per course) returns each activity with
  the app's playback hints — `mediatype`, and for video activities either
  `isvdocipher:true` + `otpurl` (VdoCipher) or `isvimeo:true` + `embedurl`
  (Vimeo), a `fileurl` for plain resources, `resourcetype`, etc.
- **Standard Moodle course WS:** `core_course_get_contents`,
  `core_course_get_courses_by_field`, `core_course_get_enrolled_courses_*`,
  `core_course_search_courses`, `core_enrol_get_users_courses`.
- **Teachers:** `/local/academy/api.php?function=get_all_teachers` /
  `browse_teachers` / `get_teacher` / `get_teacher_courses`.
- **Enrolment:**
  - Free course: `/local/academy/api.php?function=is_course_free` then
    `enrol_free_course` (user token), or `enrol_self_enrol_user` (Moodle WS).
  - Paid course: go through Payments (§8), which enrols on successful payment.

---

## 7. Quizzes

App-shaped quiz flow via `local_academy/api.php` (user token):
`get_quizzes` → `get_quiz` → `start_quiz_attempt` → `save_quiz_answer` (per
question) → `submit_quiz_attempt` / `finish_quiz_attempt` →
`get_quiz_attempt` / `get_my_quiz_attempts`. (Moodle's own `mod_quiz_*` WS are
also available if you prefer them.)

---

## 8. Video playback

Both are token-authed custom endpoints; call **immediately before playback** and
don't cache the result.

- **VdoCipher (DRM):** `GET /local/vdocipher/api.php?function=get_playback&cmid=<cmid>&token=<wstoken>`
  → `{ otp, playbackInfo }` → feed to the VdoCipher player SDK (watermarked).
  `getalltopics` gives you `otpurl` ready-made.
- **Vimeo:** `GET /local/vimeo/api.php?function=get_playback&cmid=<cmid>&token=<wstoken>`
  → `{ videoid, embedurl }` → load `embedurl` in a Vimeo player/iframe. The video
  is domain-private (embed-whitelisted to the academy). `getalltopics` gives you
  `embedurl` ready-made.

Which one an academy uses is set by its package (`features.vdocipher` /
`features.vimeo`).

---

## 9. Payments (`local_payments_*`, user token)

| Function | Purpose |
|---|---|
| `local_payments_get_courses_with_pricing` | Catalogue with prices/sale badges |
| `local_payments_get_course_price` | Price for one course |
| `local_payments_get_course_access` | Does the user already own it? |
| `local_payments_get_payment_methods` / `local_payments_get_provider_payment_methods` | Methods to show at checkout |
| `local_payments_create_checkout` | Start a checkout → returns the gateway redirect/session (Kashier) |
| `local_payments_verify_payment` | Confirm a payment after redirect/callback |
| `local_payments_get_purchased_courses` | The user's owned courses |
| `local_payments_get_payment_history` / `local_payments_get_invoice` | History + invoice |
| `local_payments_get_refund_options` / `local_payments_submit_refund` | Refunds (if enabled) |

Flow: `get_course_price` → `create_checkout` → open the gateway page → on return
`verify_payment` → the course is enrolled. Only present when `features.payments`
is true (i.e. the package has coupons/subscriptions/packages).

---

## 10. Subscriptions & coupons

- **Subscriptions** (`features.subscriptions`):
  `local_nit_subscriptions_get_available_subscriptions`,
  `…_get_my_subscriptions`, `…_get_subscription_payment_history`,
  `…_create_subscription_checkout`.
- **Coupons / discounts** (`features.coupons`):
  `local_nit_commerce_get_available_coupons`,
  `local_nit_commerce_preview_discount` (apply a code, preview the new total
  before checkout).

---

## 11. Notifications & messaging

Standard Moodle WS (user token): `message_popup_get_popup_notifications`,
`message_popup_get_unread_popup_notification_count`,
`core_message_get_conversations`, `core_message_send_instant_messages`, etc.
Push devices register via `core_user_add_user_device` /
`message_airnotifier_enable_device`.

---

## 12. Legal & account deletion

- Terms / privacy / delete-account pages are served (chrome-less, bilingual) by
  `/local/multitopics/legal.php?doc=terms|privacy|delete&lang=<en|ar>&embedded=1`.
  The URLs are also published in `design_system.links` and `getsettings.links`.
- `links.delete_account` is published for store compliance; **account deletion
  itself is handled in-app** (not a consumed endpoint today).

---

## 13. Global error / session handling

- **`errorcode: "invalidtoken"`** (Moodle WS) or **HTTP 401** with
  `errorcode: "authrequired" | "invalidtoken"` (custom endpoints) ⇒ the token is
  dead (expired, or wiped by a password change / a support reset). End the
  session and go to login. Nothing else should force a logout.
- Custom endpoints return `errorcode: "nopermissions"` (HTTP 200) when the token
  is valid but the user lacks a capability — show the message, don't log out.
- Treat any `features` key you don't recognise as harmless; treat a *missing*
  one as the feature OFF.

---

## Appendix — academy lifecycle (platform side, NOT the app)

For context only; the app never calls these. Academies are managed from the NIT
web dashboard (nit2), which drives the provisioning service on Server B:

| Action | Who | Mechanism |
|---|---|---|
| **Create** | client "Build your product" / NIT | nit2 → `POST /provision` → `create.sh` (new container, DB, config, branding, licence, app token) |
| **Edit branding** | NIT | nit2 → `POST /apply-branding/<slug>` |
| **Change plan** | NIT | nit2 → `POST /apply-license/<slug>` (+ integrations) |
| **Suspend / resume** | NIT | nit2 → `POST /suspend/<slug>` (`local_license/suspended`; the app sees `site.status`) |
| **Delete** | NIT | nit2 → `DELETE /deprovision/<slug>` → `destroy.sh` |
| **Update image** | NIT | nit2 → `POST /update-image/<slug>` / `bump-image.sh` |

The app observes the *results* of these via `getsettings.php` /
`design_system.php` (branding, `features`, `supportedapp`, `status`) — it does
not trigger them.
