# Quiz access check + Safe Exam Browser — `get_quiz_access` (mobile app)

Audience: mobile developers. Replaces the `mod_quiz_get_quiz_access_information` call described in
[`SAFE_EXAM_BROWSER_MOBILE.md`](SAFE_EXAM_BROWSER_MOBILE.md) §3. The UX in §4 of that file is unchanged.

## TL;DR

| Before | Now |
|---|---|
| `POST /webservice/rest/server.php?wsfunction=mod_quiz_get_quiz_access_information&quizid=…` | `GET /local/academy/api.php?function=get_quiz_access&quizid=…&token=…` |
| Failed with `invalidresponse` on every SEB quiz | Works for every quiz, all messages are plain text |
| App had to look for `"quizaccess_seb"` in `activerulenames` | App reads `seb.required` |
| `start_quiz_attempt` started SEB quizzes anyway | `start_quiz_attempt` (and the other attempt calls) refuse with `errorcode: "sebrequired"` |
| Android could not take SEB quizzes | Android exam mode: `start_quiz_attempt` with `app_exam=1` (§6) |

**Why the old call failed:** Moodle's Safe Exam Browser rule puts HTML buttons (*Download Safe Exam
Browser*, *Launch Safe Exam Browser*…) into its messages, and Moodle rejects HTML in that web
service's reply. The new function removes the buttons and sends their links as data in `seb`.
Moodle core is not modified.

---

## 1. Request

```
GET /local/academy/api.php?function=get_quiz_access&quizid=<quizid>&token=<wstoken>[&lang=ar|en]
```

| Param | Required | Notes |
|---|---|---|
| `function` | yes | `get_quiz_access` |
| `quizid` | yes | the quiz **instance id** (`quizid` from `get_quizzes`), not the `cmid` |
| `token` | yes | the student's token (same as the other `local/academy/api.php` calls) |
| `lang` | no | `ar` or `en`: language of the messages |

Read-only, so `GET` is fine (`POST` works too).

## 2. Response

Same envelope as every `local/academy/api.php` call: `{"status":"success","data":{…}}`.

### 2.1 SEB quiz, student (e.g. quiz 19)

```json
{
  "status": "success",
  "data": {
    "quizid": 19,
    "cmid": 152,
    "courseid": 22,
    "canattempt": true,
    "canstart": false,
    "accessrules": [
      "This quiz has been configured so that students may only attempt it using the Safe Exam Browser."
    ],
    "activerulenames": ["quizaccess_openclosedate", "quizaccess_seb"],
    "preventaccessreasons": [
      "This quiz has been configured to use the Safe Exam Browser with client configuration."
    ],
    "preventnewattemptreasons": [],
    "seb": {
      "required": true,
      "canbypass": false,
      "mode": "client",
      "launchurl": null,
      "configurl": null,
      "downloadurl": "https://safeexambrowser.org/download_en.html",
      "quizurl": "https://<site>/mod/quiz/view.php?id=152",
      "appexam": true,
      "appexamcanstart": true
    }
  }
}
```

### 2.2 SEB quiz with a quiz configuration (`mode` = `manual` / `template` / `upload`)

The same, except `launchurl` and `configurl` are filled:

```json
"seb": {
  "required": true,
  "canbypass": false,
  "mode": "manual",
  "launchurl": "sebs://<site>/mod/quiz/accessrule/seb/config.php?cmid=152",
  "configurl": "https://<site>/mod/quiz/accessrule/seb/config.php?cmid=152",
  "downloadurl": "https://safeexambrowser.org/download_en.html",
  "quizurl": "https://<site>/mod/quiz/view.php?id=152",
  "appexam": true,
  "appexamcanstart": true
}
```

### 2.3 Normal quiz

```json
{
  "status": "success",
  "data": {
    "quizid": 1, "cmid": 52, "courseid": 22,
    "canattempt": true, "canstart": true,
    "accessrules": [],
    "activerulenames": ["quizaccess_openclosedate"],
    "preventaccessreasons": [],
    "preventnewattemptreasons": [],
    "seb": {
      "required": false, "canbypass": false, "mode": "none",
      "launchurl": null, "configurl": null, "downloadurl": null,
      "quizurl": "https://<site>/mod/quiz/view.php?id=52",
      "appexam": false, "appexamcanstart": false
    }
  }
}
```

### 2.4 Fields

| Field | Type | Meaning |
|---|---|---|
| `quizid`, `cmid`, `courseid` | int | ids of the quiz, its course module and its course |
| `canattempt` | bool | the user has the right to attempt quizzes here (role) |
| `canstart` | bool | **`start_quiz_attempt` will succeed now**: `canattempt` and no reasons below |
| `accessrules` | string[] | plain-text rules for the quiz info screen (time window, attempts, SEB…) |
| `activerulenames` | string[] | active rule plugins, e.g. `quizaccess_seb`, `quizaccess_openclosedate`. For information only, use `seb.required` instead |
| `preventaccessreasons` | string[] | why the user cannot enter the quiz **now** (SEB, closed, password, IP…). Show all of them |
| `preventnewattemptreasons` | string[] | why a **new** attempt cannot start (no attempts left, delay between attempts, not open yet…) |
| `seb.required` | bool | **this user must use Safe Exam Browser** → SEB flow, never start/open the quiz in the app |
| `seb.canbypass` | bool | SEB is on but this user (teacher/manager) may skip it. `required` is then `false` |
| `seb.mode` | string | `none` · `manual` · `template` · `upload` · `client` (teacher's choice) |
| `seb.launchurl` | string\|null | `sebs://…` (or `seb://` on http sites): opens SEB with this quiz's configuration. `null` in `client` mode or if the site hides the link |
| `seb.configurl` | string\|null | the same configuration as an `https://` file download. `null` like above |
| `seb.downloadurl` | string\|null | where to download SEB (site setting) |
| `seb.quizurl` | string | the quiz page on the website (to open inside SEB, copy or share) |
| `seb.appexam` | bool | the server accepts `start_quiz_attempt` with `app_exam=1` (Android exam mode, §6) for this user. `true` only when `seb.required` is `true` and the site switch is on |
| `seb.appexamcanstart` | bool | `start_quiz_attempt` with `app_exam=1` will succeed **now** (every rule but SEB passes: open/close dates, IP, attempts, delay) |

All texts are plain text: no HTML, no buttons. Show them as they are.

## 3. Decision rule

Call `get_quiz_access` when the student taps a quiz, **before** `start_quiz_attempt`.

```
data = get_quiz_access(quizid)

if data.seb.required:
    if android and data.seb.appexam:
        → Start in exam mode (§6)    // Start disabled + preventnewattemptreasons when !appexamcanstart
    else:
        → SEB screen (§4)            // never start_quiz_attempt, never a webview
elif not data.canstart:
    → quiz info screen with preventaccessreasons + preventnewattemptreasons, Start disabled
else:
    → normal flow (start_quiz_attempt → get_attempt_questions …)
```

Check `seb.required` first. On an SEB quiz `canstart` is also `false`, but the screen to show is the
SEB one.

## 4. SEB screen

Same UX as [`SAFE_EXAM_BROWSER_MOBILE.md`](SAFE_EXAM_BROWSER_MOBILE.md) §4.2, using these fields:

| Platform | `seb.launchurl` not null (`manual` / `template` / `upload`) | `seb.launchurl` null (`client`) |
|---|---|---|
| **iOS / iPadOS** | **Open in Safe Exam Browser** → open `seb.launchurl` (`UIApplication.open`). If `canOpenURL("sebs://")` is `false` → **Install Safe Exam Browser** (App Store, ETH Zürich) | Show: *"Open Safe Exam Browser and go to this quiz"* + **Copy quiz link** (`seb.quizurl`). If SEB is not installed → **Install Safe Exam Browser** |
| **Android** | `seb.appexam: true` → **Start in exam mode** (§6). Otherwise there is no SEB client: show the Android message, **Copy quiz link** / **Share** (`seb.quizurl`) | same |
| All | Show `accessrules` and `preventaccessreasons` | same |

Notes:
- Add `seb` and `sebs` to `LSApplicationQueriesSchemes` (iOS) so `canOpenURL` works.
- SEB does not share the app's login. The student signs in again inside SEB. **Never put the
  token or an autologin key in any SEB URL.**
- `seb.downloadurl` is the site's desktop download page (Windows / macOS). On iOS use the App Store.
- When the app comes back to the foreground, refresh `get_my_quiz_attempts` and `get_quiz_access`.
- Do not cache the result longer than the screen session: the teacher can change the settings at any time.

## 5. Server-side enforcement (new)

The app-rendered quiz calls now run the quiz access rules too, so an SEB quiz cannot be taken in
the app even if the app skips the check (old version, cached data, race).

| Call | SEB quiz, student | Other rule blocks (closed, not open yet, IP, delay, attempts) |
|---|---|---|
| `start_quiz_attempt` | `errorcode: "sebrequired"` | `errorcode: "quizaccessprevented"`, `error` = the reasons as one text |
| `get_attempt_questions` (open attempt) | `sebrequired`, unless the attempt was started with `app_exam=1` | not checked |
| `save_quiz_answer` | `sebrequired`, unless started with `app_exam=1` | not checked |
| `finish_quiz_attempt` / `submit_quiz_attempt` | `sebrequired`, unless started with `app_exam=1` | not checked |

```json
{"status":"fail","error":"This quiz can only be taken in Safe Exam Browser.","errorcode":"sebrequired"}
```

`error` is translated with `lang=ar`:
*لا يمكن أداء هذا الاختبار إلا من خلال متصفح الامتحان الآمن (Safe Exam Browser).*

App handling:
- `sebrequired` → go to the SEB screen (§4). Do not show it as an error.
- `quizaccessprevented` → show `error` on the quiz info screen and disable Start.
- Teachers / managers who can bypass SEB are not blocked (`seb.canbypass: true`).
- Reviewing finished attempts (`get_quiz_attempt`, `get_my_quiz_attempts`) is not blocked.

**Still not enforced by the app API:** a quiz **password** and other "check before start" rules.
`preventaccessreasons` does not list the password. If a quiz needs one, open it in a webview, as today.

## 6. Android exam mode (`app_exam=1`)

There is no Safe Exam Browser for Android. On Android the app takes an SEB quiz in its own locked
exam mode (screen pinning, no screenshots/recording, no overlays, hands the attempt in after leaving).

### 6.1 Start

```
POST /local/academy/api.php?function=start_quiz_attempt&token=<wstoken>
     quizid=<quizid>&app_exam=1
```

- Send `app_exam=1` **only** from Android, **only** in exam mode, and only when `seb.appexam` is `true`.
- The server then skips **only the Safe Exam Browser rule**. Every other rule still applies:
  open/close dates, IP, attempts allowed, delay between attempts → `quizaccessprevented`.
- Reply: the usual attempt plus `"appexam": true` (the attempt is recorded as an exam-mode attempt):

```json
{"status":"success","data":{"attemptid":31,"quizid":19,"attempt_number":1,"timestart":1794314973,
 "timelimit":0,"state":"inprogress","appexam":true}}
```

- `"appexam": false` in the reply = a normal attempt. That happens when `app_exam=1` is sent for a
  quiz without SEB, or by a teacher who bypasses SEB. Nothing to do differently.
- Without `app_exam=1`, an SEB quiz is still refused with `sebrequired`.

### 6.2 During the attempt

`get_attempt_questions`, `save_quiz_answer`, `finish_quiz_attempt`, `submit_quiz_attempt`: no extra
parameter. The server remembers that the attempt was started with `app_exam=1` and lets it through.
Any other attempt on an SEB quiz is still refused with `sebrequired`.

Keep the attempt in exam mode until `finish_quiz_attempt` / `submit_quiz_attempt` succeeds.

### 6.3 Site switch

`getsettings.php` → `"seb_android_app_exam"`:

| Value | Meaning |
|---|---|
| `"1"` or absent | exam mode allowed (default) |
| `"0"` | exam mode off for the whole site: `seb.appexam` is `false`, `app_exam=1` is refused with `sebrequired` |

Admin: *Site administration → Plugins → Local plugins → Mobile app settings* → **Safe Exam Browser quizzes in
the Android app exam mode**.

If the admin turns it off **during** an exam-mode attempt, the next attempt call fails with
`sebrequired`. Show the SEB / Android screen (§4).

### 6.4 Marking

- `get_my_quiz_attempts` → each attempt has `"appexam": true|false`.
- On the website, the attempt review page (`mod/quiz/review.php`) shows: *"This attempt was taken in
  the Android app exam mode, not in Safe Exam Browser."*, so teachers can tell it apart.

### 6.5 Security note

The server **cannot check** that the app really is in exam mode: `app_exam=1` is a request parameter.
The protection comes from the app's exam mode only, not from Safe Exam Browser. Never send
`app_exam=1` outside the locked exam mode, and keep the lock (pinning, screenshot block, leave
detection) on for the whole attempt.

## 7. Other errors

| `errorcode` | When |
|---|---|
| `notenrolled` | the user is not enrolled in the quiz's course (admins are not checked) |
| `invalidrecord` / `invalidparameter` | wrong or missing `quizid` |
| `invalidtoken` (HTTP 401) | dead token → log out |

## 8. Migration checklist (app)

- [ ] Replace the `mod_quiz_get_quiz_access_information` call with `get_quiz_access`.
- [ ] Use `seb.required` (not `activerulenames`) to decide the SEB flow.
- [ ] Use `seb.launchurl` for *Open in Safe Exam Browser*. Hide the button when it is `null`.
- [ ] Use `seb.quizurl` for *Copy quiz link* / *Share*.
- [ ] Handle `sebrequired` and `quizaccessprevented` from `start_quiz_attempt` and the attempt calls.
- [ ] Android: offer **Start in exam mode** only when `seb.appexam` is `true`; send `app_exam=1` only from exam mode.
- [ ] Read `getsettings.php` → `seb_android_app_exam` (`"0"` = off).
- [ ] Test: quiz 19 (SEB, client mode), an SEB quiz with a quiz configuration (`launchurl` set), quizzes 1 and 13 (normal), a closed quiz, a teacher account (bypass).
- [ ] Test (Android, student, quiz 19): `start_quiz_attempt` with `app_exam=1` → attempt with `"appexam": true`; without it → `sebrequired`; with the site switch off → `sebrequired`.
