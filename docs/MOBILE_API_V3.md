# NIT Academy — Mobile API: updates since `MOBILE_API_V2.md`

This file lists **only** what changed after [`MOBILE_API_V2.md`](MOBILE_API_V2.md) (2026-10-06). Read it on top
of [`MOBILE_API.md`](MOBILE_API.md) and `MOBILE_API_V2.md`. Everything not listed here is exactly as described
there.

**Summary:** reviews now cover **teachers** as well as courses, and comments need **moderator approval**.
There are 7 new functions, new fields and parameters on the existing review functions, and 2 behaviour changes the
app must follow. Teacher ratings now come with the teachers directory, the home teachers section and the teacher
page.

| # | Area | Affects | App change needed |
|---|---|---|---|
| 1 | Reviews need approval | `get_course_reviews` lists only approved reviews; every review has `status` | **Yes**: show "waiting for approval" / the rejection reason |
| 2 | Who moderates | `delete_review` needs the new `local/nit_reviews:moderate` (managers). Editing teachers can no longer delete. `canmanage` → `canmoderate` | **Yes**, if the app shows delete actions to teachers |
| 3 | Teacher reviews | new `teacherid` parameter on `get_my_review`, `save_review`, `delete_my_review`; new `get_teacher_ratings`, `get_teacher_reviews`, `get_rate_targets` | New screens (optional) |
| 4 | "My reviews" + moderation | new `get_my_reviews`, `get_moderation_reviews`, `approve_review`, `reject_review` | New screens (optional) |
| 5 | Teacher rating everywhere | `browse_teachers` / `get_teacher` (`rating` is real now, plus `ratingcount`), `get_home_teachers` (`rating`, `ratingcount`), `get_teacher_page` (`rating`, `canrate`, `rateurl`, `hasreviews`, `reviews`) | Show them (optional) |
| 6 | Notifications | new providers `local_nit_reviews/reviewpending`, `local_nit_reviews/reviewmoderated` (popup + push) | Handle the tap (optional) |
| 7 | Home suggested lessons | `get_home_lessons` lists every "is-special" course for every user (no year/division filter) | No |
| 8 | Notification center (admins / managers) | new endpoint `/local/nit_notifications/api.php`: `get_send_options`, `count_recipients`, `send_notification`, `get_notification_log`, `get_notification`; new provider `local_nit_notifications/announcement` | New admin screens (optional); students need nothing new |

---

## 1. Behaviour changes (the existing app must follow these)

**1.1 Only approved reviews are public.** A review with stars only is approved at once. A review **with a
comment** is `pending` until a manager approves it; a rejected one is hidden. Editing a review that has a comment
sends it back to `pending`. So:
- After `save_review` with a comment, the returned `review.status` is `pending`. Tell the user "your comment will
  show after approval", and do not insert it into the public list yourself.
- `get_course_reviews` and the averages (`get_course_ratings`, every `summary`) count approved reviews only.
- `get_my_review` returns the caller's review with `status` and `rejectreason`, whatever its status.

**1.2 Moderators changed.** Approve, reject and delete now need `local/nit_reviews:moderate`: site managers and
admins everywhere, and a manager assigned on a category or a course for those courses only. **Editing teachers
lost the old `local/nit_reviews:manage`**: `delete_review` now answers them `nopermissions`.
`get_course_reviews` returns `canmoderate`; `canmanage` is still sent with the same value for old builds.
Teachers can no longer rate their own course either (`canrate` false).

---

## 2. Reviews reference (replaces `MOBILE_API.md` §5.1)

A learner rates a **course** and each **teacher** of that course: stars (1–5) and an optional comment. There is one
review per learner per course, and one per learner per teacher per course. A teacher review with
`courseid = 0` is for that teacher's private (Flex) lessons.

**Moderation:** stars alone are public at once (`status: "approved"`). A review **with a comment** is
`"pending"` until a moderator approves it. A rejected review is hidden and keeps the moderator's
`rejectreason`. Editing a review that has a comment sends it back to `"pending"`. Only **approved** reviews are
listed or counted in an average.

**Who may do what:**
- **Read:** any logged-in user can read ratings and reviews of a visible course or teacher.
- **Rate a course:** learners actively enrolled in it (`local/nit_reviews:rate`, students).
- **Rate a teacher:** learners enrolled in a course that teacher teaches, or who finished a private lesson
  with them. Nobody rates themself.
- **Moderate (approve, reject, delete):** users with `local/nit_reviews:moderate`. A site manager or admin
  moderates everything; a manager assigned on a category or a course moderates only those courses. Teachers
  cannot moderate.
- The shared visitor token cannot write or moderate (`nopermissions`).

Typical flows:
- **Catalogue cards:** `get_course_ratings`.
- **Teacher cards:** `get_teacher_ratings`, or the `rating` and `ratingcount` fields from the teachers directory.
- **Course page:** `get_course_reviews`, then `get_rate_targets` when the caller wants to rate. That one screen
  lists the course and each teacher, with the caller's current review and its status.
- **Teacher page:** `get_teacher_reviews` (its `ratecourseids` say where the caller may rate the teacher).
- **Rating sheet:** `save_review` creates or updates the review in the same call.
- **"My reviews" screen:** `get_my_reviews`, with each review's status.
- **Moderator screen:** `get_moderation_reviews`, then `approve_review`, `reject_review` or `delete_review`.

Review object (the caller's own, and every write):
```json
{"id":5,"courseid":4,"teacherid":8,"userid":12,"rating":5,"review":"شرحه ممتاز",
 "status":"pending","rejectreason":"","timecreated":1791277376,"timemodified":1791277376}
```
`teacherid` is `0` for a course review. `status` is `pending`, `approved` or `rejected`.

### get_course_ratings — GET

| param | type | req | description |
|---|---|---|---|
| courseids | JSON int array `[4,22]` **or** CSV `4,22` | yes | up to 200 course ids |

```json
{"status":"success","data":{"courses":[
  {"courseid":4,"avg":4.0,"count":1},
  {"courseid":22,"avg":0.0,"count":0}]}}
```
Approved course reviews only (teacher reviews are not in a course's average). Courses with none, or unknown ids,
come back as `avg 0, count 0`, in the order requested.
Errors: `invalidparameter` (malformed JSON), `toomanycourses` (more than 200 ids; split the request).

### get_teacher_ratings — GET

| param | type | req | description |
|---|---|---|---|
| teacherids | JSON int array **or** CSV | yes | up to 200 teacher (user) ids |

```json
{"status":"success","data":{"teachers":[{"teacherid":8,"avg":5.0,"count":1},{"teacherid":6,"avg":0.0,"count":0}]}}
```
The teacher's approved reviews over every course and private lessons.

### get_course_reviews — GET

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course |
| page | int | no | 0-based, default 0 |
| perpage | int | no | default 20, max 50 |

Approved course reviews, newest first. `pictureurl` is ready to load. `ismine` marks the caller's own review.
`canrate` = the caller may rate the course. `canmoderate` = the caller may approve, reject or delete reviews here.
`canmanage` is the old name of `canmoderate`, with the same value.

```json
{"status":"success","data":{
  "courseid":4,
  "summary":{"avg":4.0,"count":1,"stars":{"1":0,"2":0,"3":0,"4":1,"5":0}},
  "canrate":true,"canmoderate":false,"canmanage":false,
  "page":0,"perpage":20,"total":1,
  "reviews":[{"id":6,"courseid":4,"coursename":"الفيزياء - الصف الثالث الثانوي","teacherid":0,"userid":12,
    "fullname":"طالب تجريبي","pictureurl":"http://localhost:8082/theme/image.php/nit/core/1791277031/u/f1",
    "rating":4,"review":"","timecreated":1791277392,"timemodified":1791277392,"ismine":true}]}}
```
`review` may be `""` (stars only). There are more pages while `(page+1)*perpage < total`.
Errors: `coursenotfound`.

### get_teacher_reviews — GET

| param | type | req | description |
|---|---|---|---|
| teacherid | int | yes | teacher (user id) |
| page / perpage | int | no | as above |

```json
{"status":"success","data":{"teacherid":8,
  "summary":{"avg":5.0,"count":1,"stars":{"1":0,"2":0,"3":0,"4":0,"5":1}},
  "ratecourseids":[4],"page":0,"perpage":20,"total":1,
  "reviews":[{"id":5,"courseid":4,"coursename":"الفيزياء - الصف الثالث الثانوي","teacherid":8,"userid":12,
    "fullname":"طالب تجريبي","pictureurl":"…","rating":5,"review":"شرحه ممتاز وبيوصل المعلومة بسهولة",
    "timecreated":1791277376,"timemodified":1791277376,"ismine":true}]}}
```
`ratecourseids` lists the courses where the caller may rate this teacher (`0` = private lessons). When it is
empty, hide "Rate the teacher".
Errors: `teachernotfound`.

### get_rate_targets — GET

| param | type | req | description |
|---|---|---|---|
| courseid | int | one of the two | the course, then each of its teachers |
| teacherid | int | one of the two | that teacher, in every course (and private lessons) the caller may rate them in |

```json
{"status":"success","data":{"targets":[
  {"type":"course","courseid":4,"coursename":"الفيزياء - الصف الثالث الثانوي","teacherid":0,"teachername":"",
   "review":{"id":6,"rating":4,"review":"","status":"approved","rejectreason":"", "…":"…"}},
  {"type":"teacher","courseid":4,"coursename":"الفيزياء - الصف الثالث الثانوي","teacherid":8,
   "teachername":"هشام فؤاد","review":null}]}}
```
Only the targets the caller may rate are listed. Each has the caller's current review (or `null`): show
"waiting for approval" when it is `pending`, and `rejectreason` when it is `rejected`.
Errors: `coursenotfound`, `teachernotfound`, `nopermissions` (shared token).

### get_my_review — GET

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course (`0` with a `teacherid` = private lessons) |
| teacherid | int | no | `0`/missing = the course review; else the review of that teacher |

```json
{"status":"success","data":{"courseid":4,"teacherid":8,"canrate":true,
  "review":{"id":5,"courseid":4,"teacherid":8,"userid":12,"rating":5,"review":"شرحه ممتاز",
            "status":"pending","rejectreason":"","timecreated":1791277376,"timemodified":1791277376},
  "summary":{"avg":0.0,"count":0}}}
```
`review` is `null` when the caller has not rated. `summary` is the course's or the teacher's.
Errors: `coursenotfound`, `teachernotfound`.

### get_my_reviews — GET

Every review the caller wrote (any status), newest first: `{"reviews":[<review object>, …]}`.

### save_review — POST

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course (`0` with a `teacherid` = private lessons) |
| teacherid | int | no | `0`/missing = rate the course; else rate that teacher |
| rating | int | yes | 1–5 |
| review | string | no | comment, up to 2000 characters (HTML stripped); empty = stars only |

Creates or updates the caller's review of that target. The web page `/local/nit_reviews/rate.php` edits the same
row. With a comment, the returned `status` is `pending`: tell the user it will show after approval. Saving the
same stars and comment again keeps the current status.

```json
{"status":"success","data":{
  "review":{"id":5,"courseid":4,"teacherid":8,"userid":12,"rating":5,"review":"شرحه ممتاز",
            "status":"pending","rejectreason":"","timecreated":1791277376,"timemodified":1791277376},
  "summary":{"avg":0.0,"count":0}}}
```
Errors (show the message):
- `cannotrate`: the caller is not actively enrolled in the course.
- `cannotrateteacher`: the caller may not rate this teacher.
- `invalidrating`, `reviewtoolong`, `coursenotfound`, `teachernotfound`, `postrequired`, `nopermissions`.

### delete_my_review — POST

| param | type | req | description |
|---|---|---|---|
| courseid | int | yes | course (`0` = private lessons) |
| teacherid | int | no | `0`/missing = the course review |

```json
{"status":"success","data":{"deleted":true,"summary":{"avg":0.0,"count":0}}}
```
Errors: `reviewnotfound` (nothing to delete; treat it as done), `coursenotfound`, `postrequired`.

### get_moderation_reviews — GET (moderators)

| param | type | req | description |
|---|---|---|---|
| status | string | no | `pending` (default), `approved`, `rejected`, `all` |
| type | string | no | `course` or `teacher`; missing = both |
| courseid | int | no | one course; `-1` = private-lesson reviews |
| q | string | no | search the student's name or the comment |
| page / perpage | int | no | default 30, max 100 |

The caller sees only the courses they moderate. Pending reviews come first, oldest first, so nothing waits forever.
```json
{"status":"success","data":{"pendingcount":1,"page":0,"perpage":30,"total":1,
  "reviews":[{"id":5,"courseid":4,"coursename":"…","teacherid":8,"teachername":"هشام فؤاد","userid":12,
    "fullname":"طالب تجريبي","pictureurl":"…","rating":5,"review":"شرحه ممتاز","status":"pending",
    "rejectreason":"","timecreated":1791277376,"timemodified":1791277376,"ismine":false}]}}
```
Errors: `nopermissions` (the caller moderates nothing).

### approve_review / reject_review — POST (moderators)

| param | type | req | description |
|---|---|---|---|
| reviewid | int | yes | review id |
| reason | string | no | `reject_review` only: shown to the student, up to 500 characters |

Both return `{"review": <review object>}`. The student gets a notification either way.
Errors: `nopermissions` (not a moderator of that course), `reviewnotfound`, `reasontoolong`, `postrequired`.

### delete_review — POST (moderators)

| param | type | req | description |
|---|---|---|---|
| reviewid | int | yes | review id |

```json
{"status":"success","data":{"deleted":true,"reviewid":2,"courseid":4,"teacherid":0,"summary":{"avg":4.0,"count":1}}}
```
`summary` belongs to the teacher when `teacherid` > 0, otherwise to the course.
Errors: `nopermissions`, `reviewnotfound` (already deleted; refresh the list), `postrequired`.

**Notifications** (popup + app push): `local_nit_reviews/reviewpending` goes to the course's moderators when a
comment waits. `local_nit_reviews/reviewmoderated` goes to the student when their review is approved or rejected.

---

## 3. Teacher rating in the other APIs

**3.1 Teachers directory** (`MOBILE_API.md` §3.8, `/local/academy/api.php` → `browse_teachers`, `get_teacher`,
`get_all_teachers`). `rating` used to be always `0`. It is now the teacher's approved learner average (float,
`0.0` when none), and a new `ratingcount` is next to it:
```json
[{"userid":8,"fullname":"هشام فؤاد","photourl":"<url>","rating":5.0,"ratingcount":1,"approved":1,"…":"…"}]
```

**3.2 Home teachers section** (`MOBILE_API.md` §3.10, `get_home_teachers`). Each teacher has two new fields:
```json
{"id":8,"name":"هشام فؤاد","title":"دكتور الفيزياء","photo":"<url>","rating":"5.0","ratingcount":1,"courses":[…]}
```
`rating` is display text. Hide the star badge when `ratingcount` is `0`.

**3.3 Teacher page** (`MOBILE_API.md` §3.10, `get_teacher_page`). New fields:
```json
{"…":"…",
 "rating":{"has":true,"avg":"5.0","count":1},
 "canrate":true,"rateurl":"http://…/local/nit_reviews/rate.php?teacherid=8",
 "hasreviews":true,
 "reviews":[{"name":"طالب تجريبي","picture":"<url>","starson":"★★★★★","starsoff":"","rating":5,
   "date":"6/10/26","text":"شرحه ممتاز وبيوصل المعلومة بسهولة","course":"الفيزياء - الصف الثالث الثانوي"}]}
```
- `rating.avg` is display text. Hide it when `rating.has` is false.
- `reviews` are the latest approved comments (up to 6). For the full list use `get_teacher_reviews`.
- `canrate` true: show "Rate the teacher". Open the rating sheet with `get_rate_targets` (`teacherid`).
- `rateurl` is the same action on the website.

---

## 4. Home suggested lessons (`get_home_lessons`, `MOBILE_API.md` §3)

No field changed, only which courses come back. Every course ticked "is-special" is now listed for **every**
user, whatever their year, division or enrolments (an enrolled course comes with `enrolled: true`: show "enter").
The year/division filter is used only while no course is ticked (the list is then the newest courses).

---

## 5. Notification center — `/local/nit_notifications/api.php` (new)

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
