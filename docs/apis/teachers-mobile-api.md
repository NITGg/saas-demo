# Teachers — mobile API

This guide covers everything the app needs for **teachers** (the Preply-style tutor features):

- **Join as a teacher**: one sign-up that creates the account and sends the teacher application.
- **Teacher profile**: the teacher's own data, its review status, and the lessons settings.
- **Re-approval**: an approved teacher's changes wait for the academy's approval before students see them.
- **Find a teacher**: search with filters, the teacher cards, and the public teacher page.
- **Reviewer**: the list of applications with the status tabs, one application, and approve / reject.

All of it is one endpoint: **`/local/nit_tutors/api.php`**. The rules are exactly the ones of the website,
because the API calls the same server code.

> **Server:** `https://academy2026.nitg-eg.com/jitsi_life`. Deploy needs `upgrade.php` (new DB columns);
> `update.sh` already runs it.

---

## 0. Calling convention

The envelope is the same as the other academy endpoints (`/local/academy/api.php`, `/local/nit_lessons/api.php`):

```
GET|POST {site}/local/nit_tutors/api.php?function=<name>&token=<wstoken>&lang=ar|en&...

→ {"status":"success","data": ...}
→ {"status":"fail","error":"<readable, show it>","errorcode":"<stable code>"}
→ {"status":"fail","error":"...","errorcode":"invalidteacherdata"|"invalidregistration",
   "errors":{"<field>":"<message>", ...}}        ← form errors, one message per field
```

- **`token`**: the user's mobile token. `wstoken` is accepted too.
  - **Pre-login calls** use the **shared registration token**, the same one `register_student` uses.
    These are `get_teacher_signup_form`, `register_teacher`, and the search calls when a visitor uses them.
- **HTTP 401** (`authrequired`, `invalidtoken`) means the token is missing or dead: sign the user out.
  **HTTP 403** (`siteunavailable`) means the academy is suspended. Everything else is HTTP 200: read `status`.
- Calls that change something **must be POST**. Calls that carry files must be **`multipart/form-data`**.
  Otherwise you get `postrequired`.
- `lang=ar|en` picks the language of labels and messages.
- **Labels are already translated.** Show `label` / `name` fields as they are.
  - A **`value`** may contain bilingual `{mlang}` markup, e.g. `"{mlang ar}اللغة الإنجليزية{mlang}{mlang en}English{mlang}"`.
    **Never show a `value`.** Show its `label`, and send the `value` back unchanged.
  - Subject values are like this; take the labels from `form.subjects`.
- **Times** are unix seconds. Weekly hours are `"HH:MM"` in the **teacher's own timezone** (`lessons.timezone`).
- **File URLs** already carry the token (`webservice/pluginfile.php?...&token=`), so load them directly.
- **JSON parameters** (`subjects`, `quals`, `fields`, `hours`) are sent as **one form field holding a JSON string**.

### Functions

| Function | Who | Method | Purpose |
|---|---|---|---|
| `get_teacher_signup_form` | shared token | GET | Account fields, password rules, terms link, and the teacher form rules |
| `register_teacher` | shared token | POST multipart | Create the account and send the application |
| `get_application` | the teacher | GET | Status, what is missing, a waiting change, the form values, lessons settings, form rules |
| `save_application` | the teacher | POST multipart | Save the profile (`send=1` also sends it for review) |
| `submit_application` | the teacher | POST | Send the saved application for review |
| `get_teacher_filters` | anyone | GET | Filter options and sort options of the teacher search |
| `search_teachers` | anyone | GET | Teacher cards for filters, paged |
| `get_tutor` | anyone | GET | The public teacher page (same as `local/academy/api.php → get_teacher_page`) |
| `admin_list_applications` | reviewer | GET | Status tabs with counts, plus the rows |
| `admin_get_application` | reviewer | GET | One teacher as the reviewer sees them (the new version of a waiting change) |
| `admin_decide` | reviewer | POST | `approve` / `reject` (with a note) |

"Reviewer" means a user with the capability `local/nit_tutors:review` (site admins have it).
Anyone else gets `nopermissions`.

---

## 1. The teacher's life cycle

```
 sign-up ──► pending ──approve──► approved ──reject (note)──► suspended ──approve──► approved
   │            │  └─reject (note)─► rejected ──save+send again──► pending
   │            └─ (reviewer can't decide a draft that was never sent)
   └─ draft (account made, application not sent: the teacher sends it from the profile)
```

| `status` | Meaning | Teacher can edit? | Listed in search? |
|---|---|---|---|
| `draft` | Not sent yet | yes | no |
| `pending` | Waiting for the reviewer | **no** (`editable:false`) | no |
| `changes` | Sent back for changes (old data) | yes | no |
| `rejected` | Rejected with a reason (`reviewnote`) | yes, and can send again if the academy allows | no |
| `approved` | A teacher: listed and bookable | yes, **but changes wait for approval** | yes |
| `suspended` | Stopped by the reviewer | yes | no |

- Until approval the account is an ordinary **student** account: the user can study as a student.
- An applicant is **never listed** in the search, and has no public teacher page.

### Re-approval of an approved teacher's changes

When an **approved** teacher saves a change to their profile, students keep seeing the **old, approved**
version until a reviewer approves the change. This covers the headline, subjects, bio, intro video,
qualifications, and extra fields. Meanwhile:

- `get_application` → `haspending: true`, a ready-made `pendingnotice` sentence, and `values` showing the
  **new** version so the teacher keeps editing it.
- Saving again replaces the waiting version: the latest one is reviewed.
- Saving the approved values again withdraws the change.
- The reviewer sees it under the **`updates`** tab ("New changes"). `approve` puts it live; `reject` (with a
  note) throws it away. The teacher stays approved either way and gets a notification.
- **Not reviewed** (saved at once): taking bookings (`available`), the price (`lesson_flex`), and the weekly hours.
- `reviewrequired: true` in `get_application` means this teacher's changes are reviewed.

---

## 2. Join as a teacher

### 2.1 `get_teacher_signup_form` (GET, shared token)

```json
{
  "enabled": true,
  "account": {
    "fields": [
      {"name":"firstname","type":"text","required":true},
      {"name":"lastname","type":"text","required":true},
      {"name":"email","type":"email","required":true},
      {"name":"phone","type":"tel","required":true},
      {"name":"password","type":"password","required":true},
      {"name":"password2","type":"password","required":true}
    ],
    "passwordpolicy": "The password must have at least 8 characters, ...",
    "termsurl": "https://.../local/nit_pages/page.php?p=terms&embedded=1&lang=en"
  },
  "teacher": { ...the teacher form, see §2.3... }
}
```

`enabled:false` means registration is closed on this academy: hide "Join as a teacher".

### 2.2 `register_teacher` (POST multipart, shared token)

It sends **the account plus the whole teacher form** in one request, like the website's 5-step wizard.
Validate each step in the app if you like, but the server checks everything again.

| Field | Notes |
|---|---|
| `firstname`, `lastname`, `email`, `phone`, `password`, `password2` | Account. Phone: digits, 7–15, `+` allowed; Arabic-Indic digits are accepted |
| `agree` | `1`: accepts the terms (`termsurl`) |
| `headline`, `bio`, `subjects`, `videotype`, `videourl`, `quals`, `fields` + files | The teacher form, §2.4 |
| `deviceid`, `devicename`, `platform` | Optional, the same as `register_student` (device limit) |

**Required at sign-up:** bio, at least one subject, the intro video (if the academy requires it), at least
`qualifications.min` qualifications, and every `required` extra field.

**Success**:

- With email confirmation on: `{"userid":162,"confirmationrequired":true,"email":"...","message":"An email should have been sent..."}`.
  There is no token yet. The user confirms by email and then signs in normally.
- With confirmation off: `{"userid":162,"token":"...","privatetoken":null,"confirmationrequired":false,"application":{...get_application...}}`.
  The user is signed in and their application is **pending**.

**Field errors** come back as `errorcode: "invalidregistration"` with `errors`:

```json
{"status":"fail","error":"Please correct the highlighted parts.","errorcode":"invalidregistration",
 "errors":{"email":"Invalid email address","password2":"The two passwords do not match.",
           "subjects":"At least one subject","quals":"At least 1 qualification(s)","qual0":"Write the qualification's name."}}
```

Error keys: `firstname lastname email phone password password2 agree headline bio subjects video quals
qual<i> field<fieldid>`. `qual<i>` is the *i*-th row of your `quals` array.

### 2.3 The teacher form rules (`teacher` above, and `form` in `get_application`)

```json
{
  "subjects": [{"value":"{mlang ar}...{mlang en}English{mlang}","label":"English"}, ...],
  "headlinemaxlength": 255,
  "video": {"required":true,"types":["link","upload"],"linkhosts":["youtube","vimeo"],
            "accepted":[".mp4",".webm",".mov",".m4v"],"maxbytes":209715200,"maxlabel":"200.0 MB"},
  "qualifications": {"min":1,"max":10,"accepted":[".pdf",".jpg",".jpeg",".png",".webp"],"maxbytes":10485760,
                     "maxlabel":"10.0 MB","minyear":1950,"maxyear":2027},
  "fields": [
    {"id":1,"shortname":"languages","label":"Languages spoken","description":"","datatype":"select",
     "required":true,"options":[{"value":"{mlang ...}","label":"English"}]}
  ],
  "hours": {"days":[{"value":6,"label":"Saturday"},{"value":0,"label":"Sunday"}, ...],"minminutes":60}
}
```

- `fields[].datatype` is one of `text`, `textarea`, `number`, `url`, `date`, `select`, `checkbox`, `file`.
- Use the server's limits (`accepted`, `maxbytes`) for the file pickers. The server enforces them anyway.

### 2.4 The teacher form values (sign-up and `save_application`)

| Field | Type | Notes |
|---|---|---|
| `headline` | text | One line under the name, max 255 |
| `bio` | text | "About you", plain text; line breaks are kept |
| `subjects` | JSON array | `["<value>", ...]` from `form.subjects` |
| `videotype` | `link` \| `upload` | |
| `videourl` | text | A YouTube or Vimeo link when `videotype=link` |
| `videofile` | **file** | When `videotype=upload` and the teacher picked a new video. Leave it out to keep the current one |
| `quals` | JSON array | `[{"row":0,"id":4,"title":"BA English","institution":"Cairo University","year":2015,"delete":false}, ...]` |
| `qualfile[<i>]` | **file** | Certificate of the *i*-th item of `quals` (0-based position in the array). Leave it out to keep the current one |
| `fields` | JSON object | `{"<fieldid>":"<value>"}`. select: the option `value`; checkbox: `1`/`0`; date: `YYYY-MM-DD`; number: digits |
| `fieldfile[<fieldid>]` | **file** | The answer of a `file` field |

**Qualifications:**

- Send **all** rows you show.
  - An existing row keeps its `id` **and** `row`, exactly as `get_application` gave them.
    `row` ties a certificate that is waiting for review to its line.
  - A new row has `id: 0` and no `row`.
  - To remove a row, send it with `"delete": true`.
- A row with an empty `title` is ignored.
- A changed qualification loses its "verified" mark until the reviewer approves it.

### Example (Dart / Dio)

```dart
final form = FormData.fromMap({
  'function': 'register_teacher', 'token': sharedToken, 'lang': 'ar',
  'firstname': 'Sara', 'lastname': 'Ali', 'email': 'sara@example.com', 'phone': '01012345678',
  'password': pw, 'password2': pw, 'agree': 1,
  'headline': 'IELTS coach', 'bio': 'I teach English…',
  'subjects': jsonEncode([englishValue]),
  'videotype': 'link', 'videourl': 'https://youtu.be/xxxx',
  'quals': jsonEncode([{'title': 'BA English', 'institution': 'Cairo University', 'year': 2015}]),
  'qualfile[0]': await MultipartFile.fromFile(certPath, filename: 'ba.pdf'),
  'fields': jsonEncode({'1': englishLangValue}),
});
final res = await dio.post('$site/local/nit_tutors/api.php', data: form);
```

---

## 3. The teacher profile (teacher's own token)

### 3.1 `get_application` (GET)

Works for every signed-in user: a user who never applied gets `status: "draft"` and empty values.

```json
{
  "userid": 158,
  "status": "approved", "statuslabel": "Approved",
  "statushelp": "You are an approved teacher: students can find and book you. When you change your profile, ...",
  "reviewnote": "",                 // the reviewer's message (rejected / changes / suspended)
  "timesubmitted": 1792314496, "timereviewed": 1792314499,
  "editable": true,                 // false while pending: show the form read-only
  "cansubmit": false,               // true: show "Save and send for review"
  "missing": [{"key":"video","label":"An intro video"}],   // what blocks sending (draft/changes/rejected)
  "haspending": true, "timepending": 1792332751,
  "pendingnotice": "Your changes are waiting for the academy's review (sent 18/10/26, 17:12). Students see your approved profile until then. ...",
  "reviewrequired": true,           // this teacher's changes are reviewed
  "badges": [{"name":"Super Tutor","color":"warning","description":"Highly rated by students, ..."}],
  "publicpage": true, "publicurl": "https://.../local/academy/teacher.php?id=158",
  "values": {
    "headline": "IELTS coach",
    "bio": "I teach English…",
    "subjects": ["{mlang ar}...{mlang en}English{mlang}"],
    "videotype": "link", "videourl": "https://www.youtube.com/watch?v=xxxx",
    "videofile": null,                               // {filename,url,mimetype,isimage} for an uploaded video
    "quals": [{"row":0,"id":4,"title":"BA English","institution":"Cairo University","year":2016,
               "verified":true,"file":{"filename":"ba.pdf","url":"https://.../webservice/pluginfile.php/...?token=...",
               "mimetype":"application/pdf","isimage":false}}],
    "fields": {"1":"{mlang ...}"},
    "fieldfiles": {"7": {"filename":"cv.pdf","url":"..."}}
  },
  "video": {"status":"approved","statuslabel":"Approved","note":"",
            "player":{"embed":"https://www.youtube-nocookie.com/embed/xxxx?rel=0","src":null,"mimetype":""}},
  "lessons": {"available":true,"lesson_flex":2,
              "hours":[{"dayofweek":1,"starttime":"09:00","endtime":"12:00"}],"timezone":"Africa/Cairo"},
  "form": { ...§2.3... }
}
```

- `values` is what the form shows: the **waiting** version when `haspending`, otherwise the live data.
- `video.player` is what plays now. Use `embed` in a WebView, or `src` (an uploaded file) in a video player.
- `color` of a badge is one of `warning primary success info error secondary`. Map it to your palette.

**Suggested screen** (it mirrors the website's "Teacher profile" page):

1. A status banner: `statuslabel`, `statushelp`, `reviewnote`, the `missing` list, and `pendingnotice`.
2. **About you**: `bio`.
3. **Teacher profile card**: the "take bookings" switch, `headline`, price (`lesson_flex`, in Flex),
   `subjects` (pick from `form.subjects`), and weekly hours.
4. **Intro video**: link or upload.
5. **Qualifications**: rows plus certificates.
6. **More information**: the admin's `form.fields`.
7. Buttons: **Save**, and **Save and send for review** when `cansubmit`.

### 3.2 `save_application` (POST multipart)

**Send the whole form every time:** the §2.4 values plus the lessons card:

| Field | Notes |
|---|---|
| `available` | `1`/`0` take bookings (needs at least one subject) |
| `lesson_flex` | Price per lesson in Flex, a whole number ≥ 1. Empty = keep |
| `hours` | JSON `[{"dayofweek":0..6,"starttime":"HH:MM","endtime":"HH:MM"}]`; each range ≥ `form.hours.minminutes`. `dayofweek`: 0 = Sunday … 6 = Saturday |
| `send` | `1`: also send the application for review (only when `cansubmit`) |

If you leave out all of `available`, `lesson_flex` and `hours`, the lessons settings are not touched.

**Success**:

```json
{"saved":true,"waitsforreview":true,"submitted":false,
 "message":"Saved. Your changes are shown to students after the academy approves them; ...",
 "application":{ ...get_application... }}
```

Show `message`. `waitsforreview:true` means the change waits for approval (§1).

**Errors**: `errorcode: "invalidteacherdata"` with `errors`, using the §2.2 keys plus `hours`, `lessonflex`
and `general`. **Nothing is saved when there are field errors.** Other codes:

- `noteditable`: the application is pending.
- `incomplete`: `send=1` but something is missing; the message lists it.

### 3.3 `submit_application` (POST)

Sends the saved application for review. It returns `{"submitted":true,"message":"...","application":{...}}`.
Errors are `incomplete`, `noteditable`, and `noreapply` (the academy does not allow re-applying after a rejection).

---

## 4. Find a teacher (anyone)

Only **approved** teachers who take bookings are listed. Applicants and suspended teachers never are.
An approved teacher with a waiting change shows their **approved** data.

### 4.1 `get_teacher_filters` (GET)

```json
{"subjects":[{"value":"...","label":"English"}],
 "days":[{"value":6,"label":"Saturday"}, ...],
 "times":[{"value":"morning","label":"Morning (6:00–12:00)"},{"value":"afternoon","label":"Afternoon (12:00–17:00)"},{"value":"evening","label":"Evening (17:00–24:00)"}],
 "ratings":[{"value":"4.5","label":"4.5 ★ and up"}, ...],
 "badges":[{"value":1,"label":"Super Tutor"}],
 "fields":[{"id":1,"label":"Languages spoken","options":[{"value":"...","label":"English"}]}],
 "sorts":[{"value":"recommended","label":"Recommended"},{"value":"rating","label":"Best rated"},
          {"value":"price_asc","label":"Price: low to high"},{"value":"price_desc","label":"Price: high to low"},{"value":"lessons","label":"Most lessons"}],
 "perpage":24}
```

### 4.2 `search_teachers` (GET)

| Param | Notes |
|---|---|
| `q` | Name, headline or subject (Arabic-tolerant) |
| `subject` | A `subjects[].value` |
| `pricemin`, `pricemax` | Flex per lesson |
| `day` | 0..6. With `time`: free at that time **in the viewer's timezone** |
| `time` | `morning` (6–12), `afternoon` (12–17), `evening` (17–24) |
| `rating` | `3`, `4`, `4.5` (minimum average) |
| `badge` | A badge id |
| `fields` | JSON `{"<fieldid>":"<option value>"}` (only the fields listed in `get_teacher_filters`) |
| `sort` | A `sorts[].value` (default `recommended`) |
| `page`, `perpage` | 0-based page; `perpage` up to 100 (default 24) |

```json
{"total":1,"page":0,"perpage":24,"teachers":[
  {"id":158,"name":"سارة علي","photo":"https://...","headline":"IELTS coach","subjects":["English"],
   "lesson_flex":2,"rating":4.9,"reviews":12,"lessons":40,
   "badges":[{"name":"Super Tutor","color":"warning","description":"..."}],
   "hasvideo":true,"url":"https://.../local/academy/teacher.php?id=158"}]}
```

- `rating` is `null` for a teacher without reviews: show "New teacher".
- `subjects` here are display names.
- **Book a lesson:** use the lessons API (`/local/nit_lessons/api.php`): `get_teacher_slots` with
  `teacherid`, then `request_lesson`.

### 4.3 `get_tutor` (GET, `teacherid`)

The public teacher page. It is the same as `local/academy/api.php?function=get_teacher_page`. For an approved
tutor it has a **`tutor`** block:

```json
"tutor": {"listed":true,"badges":[...],"headline":"...","price":2,"subjects":[{"name":"English"}],
          "hasvideo":true,"video":{"embed":"...","src":null,"mimetype":""},
          "quals":[{"title":"BA English","institution":"Cairo University","year":2015,"verified":true}],
          "answers":[{"label":"Languages spoken","value":"English"}],
          "hours":[{"day":"Sunday","ranges":"09:00 – 11:00"}],"timezone":"Africa/Cairo","lessons":40,"bookurl":"..."}
```

- `hours` are already shown in the **viewer's** timezone.
- `teachernotfound` means the user is not an approved teacher and has no courses.

---

## 5. Reviewer (`local/nit_tutors:review`)

### 5.1 `admin_list_applications` (GET)

| Param | Notes |
|---|---|
| `status` | `pending` (default), `changes`, `approved`, `rejected`, `suspended`, `draft`, **`updates`** (approved teachers with a waiting change), `all` |
| `q` | Name or email |
| `page`, `perpage` | default 30 |

```json
{"tabs":[{"status":"pending","label":"Waiting for review","count":1}, ...,
         {"status":"updates","label":"New changes","count":1},{"status":"all","label":"All","count":3}],
 "total":1,"page":0,"perpage":30,
 "rows":[{"userid":158,"name":"...","email":"...","photo":"...","status":"approved","statuslabel":"Approved",
          "haspending":true,"timesubmitted":1792314496,"videostatus":"approved",
          "qualifications":2,"verifiedqualifications":2,"badges":[...]}]}
```

The tab counts match the rows exactly: deleted accounts are left out of both.

### 5.2 `admin_get_application` (GET, `userid`)

```json
{"userid":158,"mode":"updates","name":"...","email":"...","phone":"...","photo":"...","publicurl":"...",
 "status":"approved","statuslabel":"Approved","timesubmitted":..., "timereviewed":..., "reviewnote":"",
 "headline":"API headline","headlinenew":true,
 "bio":"<p>…</p>","bionew":false,
 "subjects":[{"name":"English"}],"subjectsnew":false,
 "price":2,
 "hasvideo":true,"video":{"embed":"...","src":null},"videonew":false,"videostatus":"Approved","videonote":"",
 "quals":[{"title":"DELTA","institution":"Cambridge","year":2022,"isnew":true,
           "fileurl":"https://.../webservice/pluginfile.php/...?token=...","filename":"cert.pdf","isimage":false}],
 "answers":[{"label":"Languages spoken","value":"English","isnew":false,"fileurl":"","filename":"","isimage":false}],
 "missing":[],
 "decisionhelp":"This approved teacher changed their profile (shown below as it will look). ...",
 "canapprove":true,"canreject":true,"approvelabel":"Approve the changes","rejectlabel":"Reject the changes"}
```

- **`mode`** explains what the two buttons do:

  | `mode` | approve | reject (note required) |
  |---|---|---|
  | `application` | approve the teacher | reject the application |
  | `updates` | put the change live | throw the change away |
  | `approved` | — | suspend the teacher |
  | `suspended` | reinstate | — |

- In `updates` mode the data is the **new** version, and the `*new` / `isnew` flags mark what changed. Highlight them.
- `bio` is HTML. Certificates open from `fileurl` (an image when `isimage`).
- Show only the buttons whose `canapprove` / `canreject` is true, with `approvelabel` / `rejectlabel`.

### 5.3 `admin_decide` (POST)

| Param | Notes |
|---|---|
| `userid` | |
| `action` | `approve` \| `reject` |
| `note` | The message the teacher reads. **Required to reject** |

It returns `{"done":true,"message":"Done.","application":{...admin_get_application...}}`. Errors:

- `notereq`: reject without a note.
- `decisionnotoffered`: that button is not offered in this mode.
- `limit_teacher`: the academy's plan has no teacher seat left.

The teacher is notified of every decision.

---

## 6. Changes to existing APIs

- **`/local/nit_lessons/api.php → update_teacher_profile`**: for an approved teacher whose changes are reviewed,
  the `headline` and `subjects` sent here are **ignored**, because they would skip the review.
  Bookings, price and hours still save.
  - `get_teacher_profile` now returns `headlinereviewed: true` for such a teacher.
  - Change the headline and subjects with `save_application` instead. The new teacher profile screen (§3)
    replaces the old lessons-profile screen.
- **`/local/academy/api.php → get_teacher_page`**: for a tutor, `tutor.video.src` (an uploaded intro video) now
  carries the token.
- The web teacher search moved into the catalogue page (`/local/nit_category/index.php?tab=teachers`). Old links to
  `/local/nit_tutors/index.php` still work. In the app, use `search_teachers`.

## 7. Error codes

| `errorcode` | When |
|---|---|
| `authrequired` (401), `invalidtoken` (401) | No token / dead token. `authrequired` also when a teacher-only call is made with the shared token |
| `siteunavailable` (403) | Academy suspended or expired |
| `postrequired` | A write call sent as GET |
| `invalidregistration` | `register_teacher` field errors (`errors`) |
| `invalidteacherdata` | `save_application` field errors (`errors`) |
| `registrationdisabled` | Registration is closed |
| `noteditable` | The application is pending (or cannot be sent now) |
| `incomplete` | Sending an application that still misses something |
| `noreapply` | Sending again after a rejection is not allowed |
| `notereq` | Reject without a note |
| `decisionnotoffered` | Approve / reject not offered in this mode |
| `limit_teacher` | No teacher seat left in the academy's plan |
| `teachernotfound` | `get_tutor` for someone who is not a teacher |
| `nopermissions` | A reviewer call without `local/nit_tutors:review` |
| `unknownfunction` | Typo in `function` |
