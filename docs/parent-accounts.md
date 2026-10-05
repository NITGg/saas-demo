# Parent dashboard (no parent accounts)

Status: **built** — plugin `local_parent`, page `/local/parent/index.php`, a copy of
bassthalk.com/parent_dashboard.

Parents **do not have accounts**. The earlier design (a parent signs up, gets a
`parent` role in each child's context, a `local_parent_link` table, parent
notifications and web-service functions) was dropped on 2026-10-04 in favour of the
Bassthalk model below. Upgrading to `local_parent` 2026100400 deletes the `parent`
role (with its assignments) and drops `local_parent_link`. Accounts that were
created as parents stay as ordinary users.

## How it works

1. A student registers their own phone (user field `phone1`, or `phone2`) and one
   or more guardian phones — the profile fields `parentphone` (name set by the
   `local_parent/parentphonefield` setting), `fatherphone` and `motherphone`.
2. A parent opens `/local/parent/index.php` (public, no log-in; linked from the
   log-in card as "لوحة تحكم ولي الأمر") and types **the student's phone** and
   **their own phone**.
3. If an active (not suspended / deleted) student has that phone **and** lists that
   guardian phone, the page shows the student's courses (active enrolments). Picking
   a course shows its lectures; each lecture opens a table:

   | column | items | shown as |
   |--------|-------|----------|
   | الفيديوهات | `vimeo` / `vdocipher` activities | watched % (`local_nit_videoprogress`), or "لم يتم مشاهدة الفيديو" |
   | الواجبات | `assign` | released grade %, "handed in, waiting for marking", or "لم يتم حضور الواجب" |
   | الامتحانات | `quiz` | released grade %, "taken, waiting for the result", or "لم يتم حضور الاختبار" |

   A lecture is a course section (subsection content counts inside its parent
   lecture). The general section is listed only when it holds a reported item.
   Only activities the student can see are counted, so unreleased content is never
   reported as missed. Grades come from the gradebook and respect hidden grades.

Nothing is stored about the parent; the result is rendered for that request only.

## Matching

Numbers are normalized before comparing (`dashboard::normalize_phone()`): digits
only, a leading `00` dropped, a local trunk `0` replaced by the default country
code (setting `local_parent/countrycode`, default `20`). So `010…`, `+2010…`,
`0020 10…` and `(010) …-…` are the same number.

## Abuse protection

Both phones must match, and wrong pairs are counted per client IP in the
application cache `local_parent/failures` (15-minute TTL): after
`dashboard::MAX_FAILURES` (10) wrong pairs that IP is refused until the entry
expires. The form posts with a sesskey.

## Files

- `local/parent/classes/dashboard.php` — matching, throttle, report.
- `local/parent/index.php` + `templates/dashboard.mustache` — the page.
- `theme/nit/scss/components/_bthparent.scss` — the look; colours are the
  Brand Colors → Bassthalk (g18) "Parent dashboard" roles (`bthparent*`).
- `theme/nit/pix/bassthalk_parent*.png`, `bassthalk_down_arrow.png` — Bassthalk's images.
- `local/parent/tests/dashboard_test.php` — PHPUnit tests.

## Trying it locally

`php local/nit_finance/cli/seed_test_accounts.php` gives the test student
`nit_test_buyer` phone `01000000099` and parent phone `01100000088`; type those two
on `/local/parent/index.php`.
