# Parent accounts — design (v1)

Status: **design only** (no code yet). Next: APIs → parent frontend.

## 1. Goal

Let a **parent** create their own account and follow their child(ren) on the
platform — grades/marks, quiz activity, and event notifications — without giving
the parent a student login or access to anything they shouldn't see.

A parent is linked to a student **by phone number**: the student names their
parent's phone at registration; the parent can only sign up if that number was
already listed by at least one student. One parent number listed by two students
→ the parent sees both children.

Everything lives in a new plugin **`local_parent`** — no Moodle core changes
(see the golden rule in `CLAUDE.md`).

## 2. The parent role

A new custom role **`parent`** (archetype: none — it is not a student/teacher).

Following Moodle's built-in mentor pattern, the role is assigned to the parent
**in each child's user context** (not site-wide). That is what safely unlocks the
"see this specific child" core capabilities and nothing else:

- `moodle/user:viewdetails`, `moodle/user:viewalldetails` — view the child's profile
- `moodle/grade:viewall` — view the child's grades (via the user/grade report)
- `report/*` (outline/usage) as needed — activity/quiz reports for the child

The link table (below) is the plugin's own record of who-parents-whom; the role
assignment is what makes core grade/report pages work for the parent.

## 3. Linking model (phone-based)

Two phone values are involved:

| Field | Whose | Where |
|-------|-------|-------|
| `phone` | the student's own number | existing signup custom profile field |
| `parentphone` | the student's **parent** number | **new** signup field the student fills |

Numbers are **normalized** before any compare (strip spaces/dashes, apply the
default country code, e.g. `010…` and `+2010…` resolve to the same value). All
matching is on the normalized form.

### Data model (plugin tables)

`local_parent_link` — one row per parent↔child relationship:

| column | meaning |
|--------|---------|
| `id` | pk |
| `parentphone` | normalized parent number (the join key) |
| `studentid` | the child user id |
| `parentid` | the parent user id, **null while pending** (no parent account yet) |
| `status` | `pending` → `linked` (→ `rejected`) |
| `timecreated`, `timemodified` | audit |

- When a **student registers** and enters a parent number → insert a `pending`
  row (`parentphone`, `studentid`, `parentid = null`).
- When a **parent registers** → all `pending` rows with that `parentphone` get
  `parentid` set and `status = linked`, and the `parent` role is assigned in each
  child's user context.

## 4. Flows

### 4.1 Student registration (adds a parent number)
1. Student signs up as today, plus a new **"Parent phone"** field.
2. On save, `local_parent` writes a `pending` `local_parent_link` row for that
   normalized number.
3. If a parent account with that number already exists, link immediately
   (`linked` + role assignment) — covers the "parent first, student second" order.

### 4.2 Parent registration (number must already exist)
1. Parent starts sign-up and enters their phone.
2. `local_parent` checks `local_parent_link` for **any** row with that normalized
   `parentphone`.
   - **none found** → registration is refused: *"No student has listed this number.
     Ask your child to add it first."*
   - **found** → allow the account; on creation, link every matching student and
     assign the `parent` role in each child's context.
3. Parent lands on the parent dashboard listing their child(ren).

### 4.3 Two students, one parent number
Both students write the same `parentphone` → two `pending`/`linked` rows share the
number → the one parent account lists **both** children. No special case; it falls
out of the join key.

### 4.4 Anti-abuse (decision needed — see §7)
Because anyone could type any number, the parent phone should be **verified**
before the link is trusted. Options: OTP/SMS to the parent number at parent
sign-up, or an in-app confirm by the student. v1 recommends **OTP verify the
parent's phone at registration**.

## 5. APIs (next phase — outline only)

External web-service functions under `local_parent` (mobile + web), guarded by
capability `local/parent:view` and confirming the caller owns/parents the target:

| function | purpose |
|----------|---------|
| `local_parent_check_phone` | during signup: does this number exist as a parent phone? (eligibility) |
| `local_parent_register` | create the parent account + link matching students (after OTP) |
| `local_parent_list_children` | the parent's linked children (name, avatar, courses) |
| `local_parent_child_grades` | one child's marks per course/activity |
| `local_parent_child_quizzes` | one child's quiz attempts: score, start time, duration |
| `local_parent_notifications` | events for the parent's children (see §6) |

Each function re-checks that `studentid` is linked to the calling parent in
`local_parent_link` (status `linked`) before returning anything.

## 6. Parent frontend (final phase)

A parent dashboard (theme_nit page / `local_parent` pages, and mobile via the WS
above):

- **Children list** — switch between children.
- **Marks** — grades per course and per graded activity.
- **Quiz activity** — each attempt's score, when it was taken, how long it took.
- **Notifications** — pushed on suitable events for a linked child:
  grade posted, quiz submitted / passed / failed, course completed, low
  activity/attendance, subscription about to expire. Delivered through Moodle's
  message API (a `local_parent` message provider) so it reaches web + mobile.

## 7. Open decisions (please confirm before APIs)

1. **Verification**: OTP/SMS to the parent number (recommended) vs student in-app
   approval vs none. Which?
2. **Parent phone field**: a custom profile field named `parentphone`, or a
   dedicated column in `local_parent_link` only? (Profile field = editable later in
   the student's profile; table-only = simpler.)
3. **Role scope**: per-child user-context assignment (recommended, Moodle-native)
   vs a fully custom read path with no role. Confirm the per-child role approach.
4. **Multi-academy**: is a parent scoped to one academy (tenant) or global across
   academies? Affects the uniqueness of the phone key.
5. **Country code / normalization** default (e.g. Egypt `+20`).
