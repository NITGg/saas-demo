# Instant Lessons ("Get a tutor now") — product & technical outline

Status: proposal · Owner: product · Builds on `local_nit_lessons` (Flex lessons + Jitsi rooms) and `local_nit_tutors` (approved tutors).

## 1. Goal

A student picks a subject and gets a live 1:1 lesson **within about a minute**, without going through
the request → accept → schedule loop. Online teachers see the request as an **on-screen prompt**
(web + mobile push) and the first to accept takes it. Everything after that reuses the existing lesson
lifecycle: Flex payment, Jitsi room, attendance, completion, auto-complete, earnings, reviews.

## 2. User journeys

### Student
1. **Entry points**: a "Get a tutor now" button on the *Book a lesson* tab, on each subject card, and
   on the course page. It only shows when at least one matching teacher is online ("3 tutors online now").
2. **Choose**: subject (required), duration (30 min default; 45 / 60 if enabled), optional short note
   or photo of the problem ("I'm stuck on question 4").
3. **Confirm price**: "This lesson costs N Flex (instant)". Needs an active Flex package; otherwise
   the existing buy-package flow, then back to the request.
4. **Searching screen** (60–90 s countdown): "Finding a tutor…", live count of teachers being asked,
   **Cancel** button (free while searching).
5. **Matched**: "Mr. Ahmed accepted — joining…" → the student is taken straight to the Jitsi room.
6. **No match** before the timeout: Flex hold released, then offer:
   - "Try again" (wider search: any approved teacher of the subject, even outside their hours),
   - "Book the earliest slot" (normal scheduling, pre-filled),
   - "Notify me when a tutor is online" (one-time alert).
7. After the lesson: the usual completion, review prompt, homework.

### Teacher
1. **Go online for instant lessons**: a toggle in the teacher header / *My live lessons* page and the
   mobile app ("Available now"). Requires: approved tutor, available = 1, at least one subject.
   Automatically goes offline when the tab/app is inactive for 2 minutes or the teacher is in a lesson.
2. **Prompt**: a modal with sound + browser/OS notification: student first name, subject, duration,
   note/photo, earnings for this lesson, **Accept / Decline**, 30 s countdown.
3. **Accept** → if still free: the lesson starts at once and the teacher lands in the room.
   If another teacher was faster: "Taken by another tutor" (no penalty).
4. **Decline / ignore**: request moves on; repeated ignores lower the teacher's acceptance rate and,
   after K misses in a row, switch them offline automatically.

### Admin
- Settings: on/off, ring timeout, teachers per wave, instant price (Flex) or +% over the teacher's
  price, allowed durations, teacher bonus %.
- Live view (extend the existing `academysessions` monitor): open requests, online teachers,
  time-to-match, unmatched requests by subject and hour (tells you where to recruit).

## 3. Functional requirements

| # | Requirement |
|---|---|
| F1 | Teacher presence: online/offline/busy with heartbeat; stale after 60 s without a heartbeat. |
| F2 | Eligibility: approved tutor (`teacher_service::bookable()` incl. `local_nit_tutors` approval), teaches the subject, online, not in another lesson, no confirmed lesson starting within duration + 10 min. |
| F3 | Request: subject, duration, note, optional image; one open request per student. |
| F4 | Dispatch in **waves**: ring up to N eligible teachers (best first: favourite/previous tutor → rating → acceptance rate → longest idle), next wave after the ring timeout, stop at the global timeout. |
| F5 | **First accept wins**, atomically; all other prompts close immediately. |
| F6 | On accept: create a `nit_lesson` (status confirmed, confirmed_time = now, `type = instant`), reserve Flex, start it (room created) — both sides redirected to `join.php`. |
| F7 | Money: Flex is **held** when the request is created and released on cancel/timeout; consumed on completion exactly like a normal lesson (same 20/80 split). |
| F8 | Teacher no-show: accepted but did not open the room within 3 min → lesson cancelled, Flex returned, request re-dispatched once, teacher strike. |
| F9 | Student no-show: existing "student absent" rule after `absence_report_minutes`. |
| F10 | Completion: existing complete / auto-complete (72 h, student must have joined). |
| F11 | Notifications: new message provider `instantrequest` (popup + mobile push, high priority) + in-page prompt; student gets "matched" / "no tutor found". |
| F12 | Mobile API parity: every step available through `local/nit_lessons/api.php` (mobile_api). |
| F13 | Arabic/English, RTL, works on phone width. |
| F14 | Audit & reports: request → wave → accept/decline/ignore → lesson id, with timestamps. |

## 4. How it plugs into the current lessons code

| Existing piece | Reused for |
|---|---|
| `nit_lesson` + `lesson_service` (`confirm_lesson`, `start_lesson`, `complete_lesson`, absence rules, `auto_complete_due`) | The instant lesson **is** a normal lesson after accept — add a `type` field (`scheduled` / `instant`). |
| `teacher_service` (`bookable`, `approved`, subjects, `busy_times`) | Eligibility and conflict check. |
| Flex (`flex::reserve/return_flex/consume`) + `wallet::distribute` | Hold, refund and payout. |
| `room_factory` / `jitsi_room` | Immediate room. |
| `notifier` + `db/messages.php` | Add events `instant_offer`, `instant_matched`, `instant_expired`. |
| `local_nit_notifications` (bell + app push) | Push to teachers' phones. |
| `academysessions` monitor (polling wall) | Live admin view. |
| `local_nit_analytics` | Track `instant_request`, `instant_matched`, and purchase as usual. |

### New data (in `local_nit_lessons`)
- `nit_instant_request`: id, studentid, subject, duration, note, fileitemid, status
  (`searching|matched|cancelled|expired`), lessonid, flex_hold, wave, timecreated, expiresat, timematched.
- `nit_instant_offer`: id, requestid, teacherid, status (`ringing|accepted|declined|missed|taken`),
  timesent, timeanswered — one row per prompt (also gives acceptance-rate stats).
- `nit_teacher_presence`: teacherid, status (`online|busy|offline`), lastseen, platform (web/app).
- `nit_lesson.type` (`scheduled` default).

### New services / endpoints
- `instant_service`: `go_online/offline/heartbeat`, `request`, `cancel`, `dispatch_wave`, `accept`,
  `decline`, `expire_due`, `teacher_noshow_due`.
- External functions (web AJAX + mobile): `instant_request`, `instant_status`, `instant_cancel`,
  `teacher_presence`, `teacher_offers` (poll), `instant_accept`, `instant_decline`.
- Scheduled task (every minute) as a safety net: expire requests, advance waves if no browser is
  driving them, teacher no-show, stale presence → offline.

## 5. Technical considerations

**Real-time inside Moodle.** Moodle has no built-in WebSocket push. Options, in order:
1. **MVP — short polling** (what `academysessions/monitor.js` already does): online teachers poll
   `teacher_offers` every 3 s, the searching student polls `instant_status` every 2 s. Endpoints read
   from a MUC cache (no DB hit when nothing changed). Load is tiny at the planned 50 lessons/day.
   Pause when the tab is hidden; push notifications cover backgrounded teachers.
2. **Mobile app**: high-priority push (already wired through `local_nit_notifications`) opens the
   accept screen; the app polls only while the screen is open.
3. **Later — WebSocket relay**: we already run a Socket.IO relay for Excalidraw
   (`academysessions` setting) and an `academy-notify-ws` image exists; publish
   `offer/taken/matched` events there to drop latency to <1 s and stop polling.

**Race conditions.** Accept must be atomic: take a `\core\lock\lock_config::get_lock_factory('local_nit_lessons')`
lock on `instant_<requestid>`, re-read status, then create the lesson inside a delegated
transaction. Same lock for cancel and expire. One open request per student and one active lesson per
teacher are enforced in the service, not only in the UI.

**Money safety.** Reuse Flex holds (reserved Flex) so a cancelled/expired request always returns the
Flex; idempotent per request id (retries never double-charge).

**Presence accuracy.** Heartbeat from the page/app; `busy` while in a lesson (status in_progress);
browser `visibilitychange` + 2-minute idle → offline. Never ring a teacher whose last heartbeat is
older than 60 s.

**Notification UX.** In-page modal + sound (needs one user click per session for audio — ask when
the teacher goes online), browser Notification API permission, push for the app. Respect
Moodle message preferences but make `instantrequest` default-on.

**Safeguarding / privacy.** Teachers see the student's first name only until accept; uploaded images
stored in the request's file area, deleted with the request after 30 days; privacy provider entries.

**Testing (AGENT_TESTING.md).** PHPUnit for: eligibility, wave order, atomic first-accept (two
accepts → one lesson), cancel/expire refunds Flex, teacher no-show re-dispatch, presence staleness,
API permissions (a student cannot accept, a teacher cannot see other teachers' offers).

## 6. Phased delivery

| Phase | Scope | Size |
|---|---|---|
| 1 MVP | Presence toggle + heartbeat, request, single wave to all eligible teachers, polling prompt, first-accept, lesson start, timeout refund, push to app | ~1.5–2 weeks |
| 2 | Waves & ranking, teacher no-show, acceptance-rate stats, admin live view & report, "notify me when online" | ~1 week |
| 3 | WebSocket relay, image/notes triage, favourites first, instant pricing rules | ~1 week |

## 7. Additional options worth considering
1. **Instant trial lesson** — first instant lesson as the paid trial (100% to the academy per the handoff).
2. **"Quick question" mode** — 10–15 min micro-session at a lower Flex price (homework help).
3. **Favourite / previous tutor first** — ring the student's last tutors for a few seconds before everyone else.
4. **Peak-hour pricing & teacher bonus** — +X% for teachers who accept during busy hours; shows "busy now" to students.
5. **Availability heatmap for students** — "Tutors are usually online 5–10 PM" to set expectations.
6. **Teacher reliability badge** — acceptance rate and response time on the public profile (ties into `local_nit_tutors` badges).
7. **AI triage** — summarise the student's note/photo for the teacher before accepting (we already have `local_nit_ai`).
8. **Parent alert** — notify a linked parent when an instant lesson starts (handoff "parent notifications").
9. **Fallback to scheduled** — one tap converts an unmatched request into the earliest scheduled booking with the same subject.

## 8. Implementation status (2026-10-20)

### Done
| Phase | Commit | What |
|---|---|---|
| 1 | `5c2afc8d2` | Teacher "Available now" + heartbeat, request by subject, prompts (pop-up + push), first-accept-wins under a lock, instant lesson started at once, decline / cancel / timeout charge nothing, expiry task, web + mobile API |
| 2 | `e71c740ed` | Ranked waves (previous tutor → rating → acceptance → idle), ring time per wave, teacher no-show → refund + reopen once, admin "Instant lessons live" page |
| 3 | `e2fd7bc84` | Photo of the problem (private, 30-day cleanup), "Notify me when a tutor is online", "Book a lesson instead", extra Flex for instant lessons |
| 3b | this commit | Realtime over the academy notify-ws relay (Socket.IO): instant offer / closed / status signals after commit; polling slows to a safety net while connected |

### Switching realtime on (production)
The relay is the same `academy-notify-ws` container used by the academy site. In `config.php`:
```php
$CFG->academy_notify_publicurl   = 'https://academy2026.nitg-eg.com';
$CFG->academy_notify_path        = '/notify-ws/socket.io';
$CFG->academy_notify_internalurl = 'http://notify-ws:3100';   // reachable from the Moodle container
$CFG->academy_notify_secret      = '<same as the relay NOTIFY_SECRET / INTERNAL_KEY>';
```
and proxy `/notify-ws/` to the relay with WebSocket upgrade (see academy repo `docs/realtime-notifications.md`).
Until then the feature works on polling alone.

### Not finished yet
1. **Relay deployment for jitsi_life** — config + reverse proxy above; not verified live.
2. **Mobile app screens** — the API (`api.php?function=instant_*`) exists, but the app has no "Get a tutor now" / accept screen yet; teachers on the app only get the push.
3. **AI triage** — summarising the student's note/photo for the teacher (local_nit_ai) not built.
4. **Instant trial lesson** — first instant lesson as the paid trial (100% to the academy) not built.
5. **Quick-question mode** (10–15 min, cheaper) and **peak-hour pricing / teacher bonus** — only a flat extra Flex exists.
6. **Teacher reliability badge** on the public profile (acceptance rate is computed, not shown publicly).
7. **Parent alert** when an instant lesson starts.
8. **Audit/report export** of requests and offers (live view only shows 24 h on screen).
9. **Behat / browser tests** for the pop-up and searching screens (PHPUnit covers the service only).

---

# Instant lessons v2 — agreed redesign (2026-10-20)

Replaces the "pick a subject, wait" screen with a tutor-first flow. Everything after the student confirms is unchanged
(normal lesson: room, completion, earnings, reviews).

## A. Student journey
1. **Year → subject.** The school year is pre-selected from the student's profile (`academic_structure::USER_YEAR`,
   set at registration) and can be changed; subjects are those of that year.
2. **Tutor list** for that subject and year (**year-aware**: only tutors who teach that year), sorted: online now →
   favourites → rating → reliability. Each card: photo, name, **rating (stars + count)**, reliability ("answers 90%"),
   **the Flex price of this lesson** (full / quick / peak / trial), "Available now" or **"Usually online: Sun–Thu
   6–10 PM"**, a **★ favourite** toggle, and **View profile**.
3. **How to ask:**
   - **Ask all available tutors (fastest)** — broadcast in ranked waves (as today).
   - **Ask this tutor** (online cards only) — only that tutor rings, 60 s; if no answer: "Ask all available instead?".
   - Offline cards: **Book a lesson** (normal scheduling, pre-filled with their next free slot) and **Notify me when
     this tutor is online**. Offline tutors never receive instant requests by email (they would expire before being read).
4. **Tutor accepts → the student confirms**: "Mr. Ahmed accepted · ★4.8 · 2 Flex · 30 min · View profile" with
   **Start lesson / Decline** and a 30 s countdown. Flex is reserved and the room created only on **Start**.
   Decline / timeout → the tutor is released (not counted against them) and a broadcast continues with the next tutor.
   A **"Start right away with whoever accepts"** checkbox skips this step (default on for quick questions).
5. **After the call**: a **rating prompt** (1–5 + comment, the existing review system), and **Book again with this
   tutor** / **★ favourite**.

## B. Tutor journey
- **Available now** switch (in-app / web, heartbeat) as today.
- **On call** (new, opt-in): receive instant-request **push** even when the app is closed; tapping it opens the
  prompt and makes the tutor available. Not offered (or switched off for 7 days) after **2 no-shows in 30 days**.
- Prompt as today; after Accept the tutor sees **"Waiting for the student to confirm… (30 s)"**, then the room.

## C. Rules & numbers (admin settings, defaults)
| Setting | Default |
|---|---|
| Ask-this-tutor ring time | 60 s |
| Student confirm time | 30 s |
| Auto-start default for quick questions | on |
| No-shows before losing On call / suspension length | 2 in 30 days / 7 days |
| "Usually online" needs | 3+ weeks of presence history |
| Instant-lesson Complete window | the lesson's own length (fixes 45 min > 30 / 15 min lessons) |

## D. New data
- `nit_teacher_presence_log` — online sessions (start, end) per tutor → "usually online" (weekday × hour buckets).
- `nit_favourite_tutor` — student ↔ tutor; going online notifies the student (once per day per tutor).
- `nit_instant_request`: `teacherid_target` (ask this tutor), `autostart`, `confirmdeadline`, status adds
  `accepted` (waiting for the student).
- `nit_teacher_presence`: `oncall`, `oncall_blocked_until`.

## E. Similar products to look at
Cambly (pick an online tutor, call starts), Preply (profile cards, instant booking), Varsity Tutors / TutorMe
(broadcast on-demand matching), Snapask (photo of the question, tutors claim it).

## F. Status
Designed, **not built**. Built so far: §8 above (phases 1–3b + phase 4: quick, trial, peak, AI summary, parent
alert, reliability, export). Pending ops: notify-ws relay on the server (see §8). Pending code: everything in this v2
section; the mobile guide gets the new calls when they are built.
