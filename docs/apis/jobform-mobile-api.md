# Job Form — mobile student API

The **Job Form** activity (`mod_jobform`) lets a student fill in and send a form
(e.g. a job application) after finishing a course. This document covers the
**student-facing** web-service functions a mobile app uses to discover, render,
and submit a Job Form. Admin/teacher management (building the fields, reviewing
submissions) is done on the web and is **not** part of this API.

All four functions are registered against the **Moodle Mobile service**
(`MOODLE_OFFICIAL_MOBILE_SERVICE`), so any valid mobile `wstoken` can call them —
no extra service setup is needed.

---

## 0. Calling convention

Standard Moodle REST web service. Every call is:

```
POST {site}/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken={token}&wsfunction={function}&moodlewsrestformat=json&<params>
```

- `{site}` = `https://academy2026.nitg-eg.com/moodle-new`
- `{token}` = the user's mobile web-service token
- Text returned for labels/names/options is **already resolved to the user's
  language** (the platform stores bilingual `{mlang}` values and resolves them
  server-side), so display it as-is.

The four functions:

| Function | Type | Purpose |
|----------|------|---------|
| `mod_jobform_get_jobforms_by_courses` | read | List Job Form activities in courses |
| `mod_jobform_view_jobform` | write | Log a view + update completion |
| `mod_jobform_get_form` | read | Get fields, groups, gate status, saved answers |
| `mod_jobform_submit_form` | write | Validate + store the student's answers |

---

## 1. Discover Job Form activities

`mod_jobform_get_jobforms_by_courses`

Usually you already know the activity from the course contents
(`core_course_get_contents`, `modname: "jobform"`). This function is the
convenience list.

**Params**

| Name | Type | Notes |
|------|------|-------|
| `courseids[0]`, `courseids[1]`, … | int | Optional. Omit to get all the user's courses. |

**Example**

```
wsfunction=mod_jobform_get_jobforms_by_courses&courseids[0]=13
```

**Response**

```json
{
  "jobforms": [
    {
      "id": 4,
      "coursemodule": 39,
      "course": 13,
      "name": "Job application",
      "intro": "<p>Fill this after finishing the course.</p>",
      "introformat": 1,
      "certid": 7,
      "allowresubmit": 0
    }
  ],
  "warnings": []
}
```

- `coursemodule` is the **cmid** — pass it as `cmid` to the other functions.
- `certid` > 0 means the form is gated behind a certificate (see §5).

---

## 2. Log a view (optional but recommended)

`mod_jobform_view_jobform` — call when the student opens the activity, so
activity completion and logs behave like the web.

**Params:** `cmid` (int).

```
wsfunction=mod_jobform_view_jobform&cmid=39
```

**Response:** `{ "status": true, "warnings": [] }`

---

## 3. Get the form to render

`mod_jobform_get_form` — the main call. Returns the fields, their groups, the
certificate-gate status and any answers already saved by this student.

**Params**

| Name | Type | Notes |
|------|------|-------|
| `cmid` | int | Course module id |
| `lang` | string | Optional. Language for the returned labels/options, e.g. `ar` or `en`. Defaults to the user's language. |

```
wsfunction=mod_jobform_get_form&cmid=39&lang=ar
```

> **Getting Arabic labels.** Labels, group names, option labels and fixed values
> are returned **in the requested language**. Pass `lang=ar` to force Arabic (or
> `lang=en` for English). This only produces Arabic text for fields the admin
> actually **translated** — i.e. fields where both "Field label (English)" and
> "Field label (Arabic)" were filled in the field editor (stored as
> `{mlang en}…{mlang}{mlang ar}…{mlang}`). A field entered in English only has no
> Arabic to show and stays English regardless of `lang`.

**Response**

```jsonc
{
  "jobform": {
    "id": 4,
    "cmid": 39,
    "name": "Job application",
    "intro": "<p>…</p>",
    "introformat": 1,
    "allowresubmit": 0
  },
  "access": {
    "cansubmit": 1,      // 1 = may fill in and send now
    "certrequired": 0,   // 1 = blocked until the linked certificate is earned
    "locked": 0          // 1 = already sent and resubmission is off
  },
  "groups": [
    { "id": 5, "name": "Personal data", "sortorder": 0 },
    { "id": 6, "name": "Job information", "sortorder": 1 }
  ],
  "fields": [
    {
      "id": 10, "name": "Full name", "type": "text",
      "required": 1, "groupid": 5, "sortorder": 0,
      "multiple": 0, "fixedvalue": "", "options": []
    },
    {
      "id": 11, "name": "Phone", "type": "phone",
      "required": 1, "groupid": 5, "sortorder": 1,
      "multiple": 0, "fixedvalue": "", "options": []
    },
    {
      "id": 12, "name": "Skills", "type": "select",
      "required": 0, "groupid": 6, "sortorder": 0,
      "multiple": 1, "fixedvalue": "",
      "options": [
        { "value": "{mlang en}PHP{mlang}{mlang ar}بي اتش بي{mlang}", "label": "PHP" },
        { "value": "JS", "label": "JS" }
      ]
    },
    {
      "id": 13, "name": "Source", "type": "fixed",
      "required": 0, "groupid": 0, "sortorder": 2,
      "multiple": 0, "fixedvalue": "EAAC Academy", "options": []
    }
  ],
  "submission": {
    "status": "draft",          // "" | "draft" | "submitted"
    "timemodified": 1765000000,
    "answers": [
      { "fieldid": 10, "value": "Abdulrahim" }
    ]
  },
  "warnings": []
}
```

### Rendering rules

Render fields ordered by `sortorder`. Group them by `groupid`: show each
`groups[]` entry (in `sortorder`) as a section heading with its fields under it;
fields with `groupid == 0` go in a final "General" section. If no field has a
group, render a flat list.

**Field `type` → widget:**

| `type` | Widget | Value to send back |
|--------|--------|--------------------|
| `text` | single-line text | the string |
| `number` | numeric text | the number as a string |
| `email` | email input | the string |
| `phone` | phone input | digits (`+` allowed), 7–15 digits |
| `url` | url input | must start with `http://` or `https://` |
| `date` | date picker | **unix timestamp** as a string |
| `checkbox` | switch/checkbox | `"1"` or `"0"` |
| `url` (link) | url input | full link |
| `select`, `multiple:0` | dropdown (pick one) | the chosen option's `value` |
| `select`, `multiple:1` | multi-select | **JSON array** of chosen `value`s |
| `fixed` | read-only label | *nothing* — show `fixedvalue`, never editable |

- **Options**: display `option.label`; send back `option.value` **verbatim**
  (it may look like a `{mlang …}` string — don't parse it, just echo it).
- **Fixed**: display `fixedvalue` read-only. The server always stores the admin
  value regardless of what you send, so you may omit fixed fields from the
  submission.
- **Pre-fill** from `submission.answers` (a saved draft or, when
  `allowresubmit == 1`, an editable submission).

---

## 4. Submit the form

`mod_jobform_submit_form` — validates and stores the answers.

**Params**

| Name | Type | Notes |
|------|------|-------|
| `cmid` | int | Course module id |
| `answers[i][fieldid]` | int | Field id |
| `answers[i][value]` | string | See the value table in §3 |
| `draft` | bool | `1` to save without required-field checks; default `0` (final send) |

**Example** (URL-encoded; one entry per answer)

```
wsfunction=mod_jobform_submit_form&cmid=39
&answers[0][fieldid]=10&answers[0][value]=Abdulrahim
&answers[1][fieldid]=11&answers[1][value]=%2B20 100 713 7667
&answers[2][fieldid]=12&answers[2][value]=%5B%22JS%22%5D          // JSON ["JS"]
&answers[3][fieldid]=14&answers[3][value]=1765000000              // a date (unix ts)
&draft=0
```

**Success**

```json
{ "status": true, "submissionid": 21, "errors": [], "warnings": [] }
```

**Validation failed** (nothing is stored):

```json
{
  "status": false,
  "submissionid": 0,
  "errors": [
    { "fieldid": 11, "message": "Please enter a valid phone number (7 to 15 digits, an optional leading +)." },
    { "fieldid": 10, "message": "Required" }
  ],
  "warnings": []
}
```

Map each error to its field by `fieldid`. Validations enforced server-side:
required (non-draft), number, email, phone (7–15 digits, optional leading `+`),
and URL (`http(s)://`).

### Exceptions

Unlike validation errors, these come back as a Moodle **exception** (HTTP 200
with an `exception`/`errorcode` body) because the app should have prevented them
via the `access` flags:

| `errorcode` | Meaning | Prevent by checking |
|-------------|---------|---------------------|
| `certificaterequired` | The linked certificate is not earned yet | `access.certrequired == 1` |
| `alreadysubmitted` | Already sent and resubmission is off | `access.locked == 1` |

---

## 5. The certificate gate

If the activity has a linked **Custom Certificate** (`certid > 0` /
`certrequired: 1`), the student can only submit **after being issued that
certificate** — i.e. after finishing the course. Recommended app flow:

1. `mod_jobform_get_form` → read `access`.
2. If `certrequired == 1`: show "Finish the course to unlock this form" and hide
   the submit button.
3. If `locked == 1`: show the read-only answers from `submission.answers`.
4. Else (`cansubmit == 1`): render the form; allow **Save draft** (`draft=1`) and
   **Send** (`draft=0`).

---

## 6. Typical sequence

```
core_course_get_contents            → find the "jobform" activity + its cmid
mod_jobform_view_jobform(cmid)      → (optional) log the view
mod_jobform_get_form(cmid)          → render fields/groups; check access
mod_jobform_submit_form(cmid, …)    → save draft / send
mod_jobform_get_form(cmid)          → re-read to confirm status == "submitted"
```

---

## 7. Notes

- **Languages**: `name`, group names, option `label`s and `fixedvalue` are
  returned in the caller's language. Pass `lang=ar` / `lang=en` to
  `mod_jobform_get_form` (and `mod_jobform_get_jobforms_by_courses`) to force it;
  otherwise the token user's language is used. Only fields the admin translated
  (both English **and** Arabic filled) have a non-English value — see the note in
  §3.
- **Multi-select storage**: send a JSON array of the option `value`s. The server
  stores it as a JSON array; `get_form` returns it back the same way in
  `submission.answers[].value`.
- **Idempotency**: a student has at most **one** submission per activity.
  Submitting again overwrites it (allowed only while `locked == 0`).
- **Capabilities**: read functions need `mod/jobform:view`; `submit_form` needs
  `mod/jobform:submit` (the student archetype has both).

---

## 8. Troubleshooting

**`errorinvalidcmid` — "No Job Form activity was found for course module id N"**
(previously surfaced as the cryptic `dml_missing_record_exception` /
`invalidrecordunknown` / "Can't find data record in database").

You are passing a `cmid` that is **not a Job Form activity on the site your
token authenticates against**. This is almost always one of:

1. **Wrong id** — `cmid` must be the **course-module id** (the `id` in the web
   URL `…/mod/jobform/view.php?id=<cmid>`), not the instance id and not an id
   from another activity. Fetch the correct value from
   `mod_jobform_get_jobforms_by_courses` → `jobforms[].coursemodule`.
2. **Wrong environment** — the `wstoken` belongs to a different site/database
   than where that activity lives (e.g. testing against a local/staging server
   while the activity exists only on production). Confirm the token's site is
   the same one that serves `…/mod/jobform/view.php?id=<cmid>`.
3. **Stale id** — the activity was deleted or recreated, changing its cmid.

Quick check: open `…/mod/jobform/view.php?id=<cmid>` in a browser **logged in as
the token's user**. If that 404s or redirects, the cmid is wrong for that site.

**`certificaterequired` / `alreadysubmitted`** — expected gate responses; read
`access` from `mod_jobform_get_form` first and branch (see §5).
