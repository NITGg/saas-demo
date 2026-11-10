# NIT Academy — Safe Exam Browser (SEB): spec for the mobile app

Audience: mobile developers. Read on top of [`MOBILE_API.md`](MOBILE_API.md) §2.4 (`getalltopics`) and
§3.6 (app-rendered quizzes).

**In one line:** a teacher can mark a quiz *"Require the use of Safe Exam Browser"*. Such a quiz can
only be taken inside the official **Safe Exam Browser** app, never inside our app (natively or in a
webview). The app's job is to **detect it, block it, and hand the student over to SEB**.

| # | Topic | App change needed |
|---|---|---|
| 1 | What SEB is and where the teacher turns it on | No |
| 2 | How the server enforces it | No (background) |
| 3 | How the app knows a quiz needs SEB | **Yes** |
| 4 | What the app must do (UX flow) | **Yes** |
| 5 | Known backend gap: the native quiz API does not enforce SEB yet | Backend fix; app must not rely on the server alone until then |
| 6 | Test checklist | QA |

---

## 1. What it is

[Safe Exam Browser](https://safeexambrowser.org) is a locked-down browser made by ETH Zürich. While a
quiz runs inside it the student cannot switch apps, copy/paste, open other sites, take screenshots
(platform-dependent) etc. Moodle supports it through the core quiz access rule **`quizaccess_seb`**
(stock Moodle 5.2 plugin, `public/mod/quiz/accessrule/seb/`, not customised by us).

**Where it's set:** Quiz → *Edit settings* → **Safe Exam Browser** → *Require the use of Safe Exam
Browser*. Options (stored in `quizaccess_seb_quizsettings.requiresafeexambrowser`):

| Value | Option shown to the teacher | What the server checks |
|---|---|---|
| `0` | No | nothing — a normal quiz |
| `1` | Yes – Configure manually | **Config Key** header (settings built from the form: quit password, URL filter, camera/mic, reload, task bar…) |
| `2` | Yes – Use an existing template | **Config Key** header (settings from an admin template) |
| `3` | Yes – Upload my own config | **Config Key** header (teacher's own `.seb` file) |
| `4` | Yes – Use SEB client config | only that the **User-Agent contains `SEB`** (+ Browser Exam Key, if the teacher listed any) |

Extra per-quiz options the teacher may set: *Show SEB download button*, *Allowed browser exam keys*,
*Exit SEB button with quit link*, *Quit password*, *Ask user to confirm quitting*.

**Who is exempt:** users with capability `quizaccess/seb:bypassseb` in the quiz context (by default
teachers / managers / admins). For them the quiz behaves normally. **Students are never exempt.**

**Supported SEB clients:** Windows, macOS and **iOS / iPadOS** (App Store: "Safe Exam Browser").
There is **no official SEB for Android.**

---

## 2. How the server enforces it (background)

On every quiz page (view, start attempt, attempt, summary) Moodle runs the rule's `prevent_access()`:

1. Rule off, or user can bypass → allowed.
2. Already validated earlier in this Moodle session for this quiz → allowed.
3. Mode `4`: `User-Agent` must contain `SEB`.
4. Modes `1–3`: header `X-SafeExamBrowser-ConfigKeyHash` must equal
   `sha256(<full page URL> + <quiz config key>)`. If the header is missing but SEB is detected,
   Moodle redirects SEB to reconfigure itself.
5. If the teacher listed *Allowed browser exam keys*: header `X-SafeExamBrowser-RequestHash` must equal
   `sha256(<full page URL> + <one allowed key>)`.
6. Success is cached in the Moodle session for that quiz.

On failure the student sees *"The Safe Exam Browser keys could not be validated…"* / *"This quiz has
been configured to use the Safe Exam Browser…"* plus up to three buttons:
**Download Safe Exam Browser**, **Launch Safe Exam Browser** (`sebs://…`), **Download SEB config file**
(`https://…`).

> **Consequence for the app:** these headers are computed by the SEB client from its own config and
> keys. **The app must never try to fake them** (setting a `SEB` user agent or computing hashes in the
> webview). It is a cheating vector, it breaks on config changes, and it defeats the whole point.

Useful URLs (replace `<site>` = `https://academy2026.nitg-eg.com/jitsi_life`, `<cmid>` = quiz cmid):

| Purpose | URL |
|---|---|
| Open the quiz in SEB (preferred) | `sebs://academy2026.nitg-eg.com/jitsi_life/mod/quiz/accessrule/seb/config.php?cmid=<cmid>` |
| Same, over HTTP(S) (downloads the `.seb` file; SEB opens it) | `<site>/mod/quiz/accessrule/seb/config.php?cmid=<cmid>` |
| SEB download page (admin setting `quizaccess_seb/downloadlink`) | default `https://safeexambrowser.org/download_en.html` |
| Quiz page in a normal browser | `<site>/mod/quiz/view.php?id=<cmid>` |

The `sebs://` scheme is the `https` URL with the scheme swapped (`seb://` for plain http — not used on
production). `config.php` requires a logged-in user who can see the quiz.

---

## 3. How the app knows a quiz needs SEB

> **Updated:** use **`get_quiz_access`** (`/local/academy/api.php`). See
> [`SEB_QUIZ_ACCESS_MOBILE.md`](SEB_QUIZ_ACCESS_MOBILE.md). The core call below fails with
> `invalidresponse` on every SEB quiz, because the SEB rule returns HTML buttons. The rest of this
> section is kept for history.

`getalltopics` (`nativerender`) and the `local_academy` quiz endpoints **do not report SEB today**
(see §5). Until they do, use the core web service that already ships in the mobile service:

**`mod_quiz_get_quiz_access_information`** — `POST /webservice/rest/server.php`,
`wsfunction=mod_quiz_get_quiz_access_information`, `quizid=<quizid>`, user token.

```json
{
  "canattempt": true, "canmanage": false, "canpreview": false, "canreviewmyattempts": true,
  "canviewreports": false,
  "accessrules": ["This quiz has been configured so that students may only attempt it using the Safe Exam Browser."],
  "activerulenames": ["quizaccess_seb"],
  "preventaccessreasons": ["This quiz has been configured to use the Safe Exam Browser with client configuration."],
  "warnings": []
}
```

Decision rule (call it when the student taps a quiz, before `start_quiz_attempt`):

| Condition | Meaning | App action |
|---|---|---|
| `activerulenames` contains `"quizaccess_seb"` **and** `preventaccessreasons` is non-empty | SEB required for this user | **SEB flow (§4)** — do not start the attempt, do not open a webview |
| `activerulenames` contains `"quizaccess_seb"` **and** `preventaccessreasons` is empty | User can bypass (teacher/admin) | Open the quiz normally |
| no `quizaccess_seb` | Normal quiz | Existing flow (§3.6 native or webview) |

Notes:
- `preventaccessreasons` can also hold other rules' reasons (password, time window, IP). Show them all.
- `accessrules[]` are human-readable texts for the quiz info screen.
- Cache the result per quiz for the screen session only; settings can change at any time.

---

## 4. What the app must do

### 4.1 Course content list
- A quiz that needs SEB shows a **lock / shield badge** and the label *"Requires Safe Exam Browser"*
  (`يتطلب متصفح الامتحان الآمن`).
- Never open such a quiz in the native renderer or in a webview — even if `nativerender.requires`
  says the app supports every question type.

### 4.2 Quiz info screen (SEB quiz)
Show: quiz name, `accessrules[]` texts, attempts used / allowed (`get_my_quiz_attempts`), and the
buttons below depending on the platform.

| Platform | Buttons | Behaviour |
|---|---|---|
| **iOS / iPadOS** | **Open in Safe Exam Browser** (primary) | `UIApplication.open("sebs://…config.php?cmid=<cmid>")`. Add `seb` and `sebs` to `LSApplicationQueriesSchemes` so `canOpenURL` works. |
| | **Install Safe Exam Browser** (shown when `canOpenURL("sebs://")` is `false`) | Open the App Store page of *Safe Exam Browser* (ETH Zürich). |
| **Android** | — no SEB client exists — | Show: *"This quiz can only be taken in Safe Exam Browser on a computer (Windows / macOS) or an iPad / iPhone."* Offer **Copy quiz link** (`<site>/mod/quiz/view.php?id=<cmid>`) and **Share**. Never open a webview. |
| All | **Review my attempts** | Allowed — review of finished attempts is not blocked by SEB in our flow (`get_quiz_attempt`). |

Hand-off details:
- SEB is a separate browser: **it does not share the app's login.** The student signs in to the
  website inside SEB (username/password or Google, as on the web). Do not pass the app token or an
  autologin key in the SEB URL.
- After launching SEB, the app stays on the quiz info screen. When the app returns to foreground,
  refresh `get_my_quiz_attempts` so the new attempt / grade appears.
- If the teacher set a quit link, SEB shows an *Exit Safe Exam Browser* button after the attempt; the
  student then comes back to our app manually.

### 4.3 Server errors to handle anyway
If the app ever calls a quiz function for an SEB quiz (old cache, race), core quiz web services
(`mod_quiz_start_attempt`, `mod_quiz_get_attempt_data`, …) fail with errorcode
`attempterror` / `notavailable` and the SEB message. Map any such error to the §4.2 screen instead of
showing a raw error.

### 4.4 Strings

| Key | English | Arabic |
|---|---|---|
| seb_required | Requires Safe Exam Browser | يتطلب متصفح الامتحان الآمن |
| seb_open | Open in Safe Exam Browser | افتح في متصفح الامتحان الآمن |
| seb_install | Install Safe Exam Browser | ثبّت متصفح الامتحان الآمن |
| seb_android | This quiz can only be taken in Safe Exam Browser on a computer (Windows / macOS) or an iPad / iPhone. | لا يمكن أداء هذا الاختبار إلا من خلال متصفح الامتحان الآمن على كمبيوتر (Windows / macOS) أو iPad / iPhone. |
| seb_login_hint | You will need to sign in again inside Safe Exam Browser. | ستحتاج إلى تسجيل الدخول مرة أخرى داخل متصفح الامتحان الآمن. |
| seb_copy_link | Copy quiz link | نسخ رابط الاختبار |

---

## 5. Known backend gap (must be fixed on the server)

> **Fixed for SEB:** `start_quiz_attempt`, `get_attempt_questions`, `save_quiz_answer`,
> `finish_quiz_attempt` and `submit_quiz_attempt` now refuse an SEB quiz with
> `errorcode: "sebrequired"`. `start_quiz_attempt` also refuses other blocking rules (closed, IP,
> delay…) with `quizaccessprevented`. The quiz password is still not checked. Details:
> [`SEB_QUIZ_ACCESS_MOBILE.md`](SEB_QUIZ_ACCESS_MOBILE.md) §5. The `getalltopics` part below is
> still a proposal.

The app-rendered quiz API in `local_academy` (`start_quiz_attempt`, `get_attempt_questions`,
`save_quiz_answer`, `finish_quiz_attempt`, `submit_quiz_attempt` —
`public/local/academy/classes/quiz_manager.php`) **does not run Moodle's quiz access rules**. It checks
enrolment and the attempts limit only, then calls `quiz_prepare_and_start_new_attempt()` directly.

So today **a student can take an SEB quiz inside our app** if the app calls `start_quiz_attempt`.
The same applies to the other access rules (quiz password, open/close time, IP range).
`getalltopics` also always returns `nativerender.forcewebview: false`.

Until the server is fixed, the app **must** do the §3 check before calling `start_quiz_attempt`.

Planned server change (proposal — not implemented yet; this doc will be updated when it lands):
- `start_quiz_attempt` (and the other attempt calls) run `quiz_access_manager::prevent_access()` and
  fail with errorcode **`sebrequired`** (or `quizaccessprevented` for other rules) with a readable
  `error`.
- `getalltopics` → quiz `nativerender` gains `"seb": {"required": true, "launchurl": "sebs://…",
  "configurl": "https://…", "downloadurl": "…"}` and `forcewebview` stays `false` (a webview is not
  enough either). The app can then skip the extra `mod_quiz_get_quiz_access_information` call.

---

## 6. Test checklist

Setup: one quiz with SEB = **No**, one with **Yes – Configure manually**, one with
**Yes – Use SEB client config**. Student account + teacher account enrolled in the course.

| # | Case | Expected |
|---|---|---|
| 1 | Student, SEB = No | Normal native / webview flow |
| 2 | Student, SEB manual, iOS with SEB installed | Badge in list; info screen; "Open in SEB" launches SEB at the quiz; after sign-in the attempt works in SEB |
| 3 | Student, SEB manual, iOS without SEB | "Install Safe Exam Browser" opens the App Store |
| 4 | Student, SEB client config, Android | Android message + copy/share link; no webview, no `start_quiz_attempt` call |
| 5 | Teacher, any SEB quiz | Quiz opens normally (bypass) |
| 6 | Student finishes in SEB, returns to app | Attempt and grade appear after refresh |
| 7 | Student, SEB quiz, call `start_quiz_attempt` directly (API test) | **Today: succeeds (gap §5).** After the server fix: `sebrequired` |
| 8 | Teacher switches a quiz from No → Yes while the app is open | Next tap on the quiz shows the SEB screen |
