# NIT Academy — Mobile API: updates since `MOBILE_API_V3.md`

This file lists **only** what changed after [`MOBILE_API_V3.md`](MOBILE_API_V3.md). Read it on top of
[`MOBILE_API.md`](MOBILE_API.md), `MOBILE_API_V2.md` and `MOBILE_API_V3.md`. Everything not listed here is
exactly as described there.

**Summary:** a new **notification center**, and **automatic notifications** (§2). Admins and managers send notifications to groups of users, in one or
more languages, and optionally by email. Every notification is kept with its recipients (delivered, failed, read,
email sent). There is one new endpoint with 5 functions for the admin and manager screens. Students need nothing
new: notifications reach them as normal Moodle notifications (bell + push), already in their own language.

| # | Area | Affects | App change needed |
|---|---|---|---|
| 1 | Notification center (admins / managers) | new endpoint `/local/nit_notifications/api.php`: `get_send_options`, `count_recipients`, `send_notification`, `get_notification_log`, `get_notification` | New admin screens (optional) |
| 2 | Receiving notifications | new provider `local_nit_notifications/announcement` (bell + push), through the standard `message_popup_get_popup_notifications` | No: already shown like any notification |
| 3 | Languages | each recipient gets the title and text in their own language, as plain text (never `{mlang}` markup) | No |
| 4 | Email preference | new preference row `local_nit_notifications/announcement_email` ("notifications from the administration, by email"); email goes only when the sender asks **and** the user turned this on | Optional: show it on the app's notification settings screen |
| 5 | Automatic notifications | new providers `local_nit_notifications/subscription`, `/newquiz`, `/sessionreminder`; the existing `local_nit_subscriptions/subscriptionreminder` and `local_nit_flex/expiry` now arrive in the user's language | No: they arrive like any notification. Optional: route the tap by `contexturl` |

---

## 1. Notification center — `/local/nit_notifications/api.php` (new)

Admins and managers send notifications to groups of users, and every notification is kept with its recipients:
who got it, who failed, and who read it. Notifications the platform sends by itself land in the same log
(`source` ≠ `manual`).

**Receiving needs nothing new.** Each notification is a normal Moodle notification (provider
`local_nit_notifications/announcement`), so it already comes through `message_popup_get_popup_notifications`
(`MOBILE_API.md` §9), and through push for devices registered with `core_user_add_user_device`. Read state comes
from `core_message_mark_notification_read`. `customdata` carries `nitnotifid` and `type`, and `contexturl` is the
optional link (open it on tap).

**Languages: the app never sees `{mlang}`.** A notification can be written once per installed language. The
server picks each recipient's language (their profile language, else the site language) before it sends, so the
bell, the push and the email all arrive as plain text in that language. On the admin screens below, `title` and
`body` come in the caller's language (the `lang` parameter, else their own), and `titles` / `bodies` give every
version that was written as `{"ar": "…", "en": "…"}`.

**Email rule.** A user gets an email only when **both** are true: the sender ticked `email`, **and** the user
turned on "Notifications from the platform administration, by email" in their notification preferences (provider
`local_nit_notifications/announcement_email`; it is off until the user turns it on). The plain notification
(`announcement`) never emails by itself. To let users choose in the app, show that preference with the standard
`core_message_get_user_notification_preferences` / `core_user_update_user_preferences`.

**Who may send what** (`local/nit_notifications:send`; any other caller gets `nopermissions`, and so does the
shared visitor token):

| Caller | Audiences | Course |
|---|---|---|
| Admin, site manager | `course_students`, `course_teachers`, `all_students`, `all_teachers`, `managers`, `admins` | required for the two course audiences |
| Manager of a category or a course | `course_students`, `course_teachers` | one of their courses, or `0` = all the courses they manage |

Definitions:
- **Course students:** active enrolments with a student role.
- **Teachers:** anyone with a teacher or editing-teacher role (site admins are left out).
- **Managers:** anyone with a manager role at any level.
- **All students:** every active account that is not a teacher, a manager or an admin, even with no enrolment.
- Suspended accounts and the sender never receive.

Types: `general`, `courses`, `subscriptions`, `offers`. Title up to 200 characters and text up to 4000 (plain
text), in each language. The link is optional and must be a full `http(s)://` URL. Up to 300 recipients are delivered during the
call (`status: "done"`); a bigger group comes back `status: "queued"` and is delivered in the background in
batches. Refresh `get_notification` to follow it.

### get_send_options — GET
What to show on the compose screen.
```json
{"status":"success","data":{"sitewide":false,
  "audiences":[{"key":"course_students","label":"طلاب كورس","needscourse":true},
               {"key":"course_teachers","label":"مدرسين كورس","needscourse":true}],
  "courses":[{"id":0,"name":"كل الكورسات اللي بديرها"},{"id":4,"name":"الفيزياء - الصف الثالث الثانوي"}],
  "types":[{"key":"general","label":"عام"},{"key":"courses","label":"كورسات"},
           {"key":"subscriptions","label":"اشتراكات"},{"key":"offers","label":"عروض وكوبونات"}],
  "languages":[{"code":"ar","name":"العربية ‎(ar)‎","dir":"rtl"},{"code":"en","name":"English ‎(en)‎","dir":"ltr"}],
  "maxtitle":200,"maxbody":4000}}
```
`languages` are the installed languages, the site language first. Show one title box and one text box per
language, and make the first language required.
Show the course picker only when the chosen audience has `needscourse`. A site-wide caller gets every course
and no `0` entry.

### count_recipients — GET
| param | type | req | description |
|---|---|---|---|
| audience | string | yes | an audience key |
| courseid | int | for course audiences | see the table above |

`{"count":2}`. Show "will be sent to N users" before sending. Errors: `audience`, `course`, `choosecourse`.

### send_notification — POST
| param | type | req | description |
|---|---|---|---|
| audience | string | yes | an audience key |
| courseid | int | for course audiences | |
| type | string | no | default `general` |
| title_<code> | string | yes for the first language | one per `languages` entry, e.g. `title_ar`, `title_en`; ≤ 200 each |
| body_<code> | string | yes for the first language | e.g. `body_ar`, `body_en`; ≤ 4000 each, plain text (new lines kept) |
| title / body | string | instead of the above | a single version in any language |
| url | string | no | full link opened from the notification |
| email | 0/1 | no | also send by email (see the email rule below) |

```json
{"status":"success","data":{"notification":{"id":4,"title":"امتحان الفيزياء يوم الخميس","body":"راجعوا الباب الأول",
  "url":"https://…/local/academy/course.php?id=4","type":"courses","source":"manual","audience":"course_students",
  "courseid":4,"coursename":"الفيزياء - الصف الثالث الثانوي","senderid":13,"sendername":"مدير تجريبي",
  "titles":{"ar":"امتحان الفيزياء يوم الخميس","en":"Physics exam on Thursday"},
  "bodies":{"ar":"راجعوا الباب الأول","en":"Revise unit 1"},
  "email":false,"status":"done","total":2,"sent":2,"failed":0,"read":0,"timecreated":1791285548}}}
```
Errors (show the message): `audience`, `course`, `choosecourse`, `type`, `required`, `titletoolong`,
`bodytoolong`, `url`, `norecipients` (nobody in that group, nothing sent), `postrequired`, `nopermissions`.

### get_notification_log — GET
| param | type | req | description |
|---|---|---|---|
| source | string | no | `manual` or `auto`; empty = both |
| type | string | no | a type key |
| courseid | int | no | one course |
| q | string | no | search the title and text |
| page / perpage | int | no | default 30, max 100 |

`{"page":0,"perpage":30,"total":1,"notifications":[<notification as above>]}`, newest first. Admins and site
managers see everything; a scoped manager sees what they sent plus what went to the courses they manage.
`senderid` `0` = sent by the system. `audience` `users` = an automatic notification to specific users.

### get_notification — GET
| param | type | req | description |
|---|---|---|---|
| id | int | yes | notification id |
| state | string | no | `sent`, `failed`, `queued`, `read`, `unread`, `emailsent`, `emailfailed`, `emailoff`; empty = all |
| page / perpage | int | no | default 50, max 200 |

```json
{"status":"success","data":{"notification":{…},"page":0,"perpage":50,"total":2,
  "recipients":[{"userid":12,"fullname":"طالب تجريبي","status":"sent","timesent":1791285548,"timeread":0,
    "email":"failed"}]}}
```
`timeread` `0` = not read yet. `email` is the email copy: `none` (not asked), `sent`, `failed`, or `off` (the user has email off, so none was sent). Errors: `notificationnotfound`, `nopermissions` (not in the caller's scope).

---

## 2. Automatic notifications (new)

The platform now notifies users by itself. **The app needs nothing new to receive them**: each one is a normal
Moodle notification (bell through `message_popup_get_popup_notifications`, push through airnotifier), already in
the recipient's own language (plain text, never `{mlang}`). Open `contexturl` on tap.

| When | Who gets it | Provider (`component` / `eventtype`) | `contexturl` |
|---|---|---|---|
| A subscription is activated, or activated again for a plan the user had before ("renewed") | the student | `local_nit_notifications` / `subscription` | site home |
| An admin cancels a subscription | the student | `local_nit_notifications` / `subscription` | site home |
| An admin cancels or refunds a course purchase | the student | `local_nit_notifications` / `subscription` | site home |
| A quiz becomes visible in a course (once per quiz) | the course's students | `local_nit_notifications` / `newquiz` | `/mod/quiz/view.php?id=<cmid>` |
| Before a live session starts (default 60 and 10 minutes; one reminder when it starts sooner) | the session's students and its teacher | `local_nit_notifications` / `sessionreminder` | `/course/view.php?id=<courseid>` |
| Before a confirmed private (Flex) lesson starts (same times) | the student and the teacher | `local_nit_notifications` / `sessionreminder` | student: `/local/nit_lessons/student.php?tab=lessons`, teacher: `/local/nit_lessons/my_lessons.php` |
| A subscription is about to end (existing, days set by the admin) | the student | `local_nit_subscriptions` / `subscriptionreminder` | site home |
| A lesson package is about to end (existing) | the student | `local_nit_flex` / `expiry` | `/local/nit_lessons/student.php?tab=book` |

Each provider is its own row in the user's notification preferences, so a user can turn a kind off (or turn email
on for it) with the standard `core_message_get_user_notification_preferences` /
`core_user_update_user_preferences`. The admin can switch each kind off for the whole site.

All of them also land in the notification log: `get_notification_log` with `source=auto`. Their `senderid` is `0`
and their `source` names the kind: `subscription_started`, `subscription_renewed`, `subscription_cancelled`,
`course_cancelled`, `course_refunded`, `newquiz`, `session_reminder`, `lesson_reminder`, `subscription_expiry`,
`flex_expiry`.
