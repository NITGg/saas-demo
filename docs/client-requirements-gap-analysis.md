# Client Requirements — Gap Analysis (German Language Teaching Platform)

**Date:** 2026-10-21
**Source:** 8 client messages (2026-10-21)
**Compared against:** branch `jitsi_life` (Moodle 5.2 + NIT plugins under `public/local/*`, theme `public/theme/nit`, block `public/blocks/nit_section`, custom modules `mod/jitsi`, `mod/games`, `mod/flashcard`, `mod/vdocipher`, `mod/vimeo`, `mod/jobform`).
**Method:** a read-only review of code, schema (`db/install.xml`), settings and templates. Nothing was run, so runtime behaviour is not verified.

### Legend

| Mark | Meaning |
|---|---|
| ✅ | **Exists.** Built in our plugins/theme and usable as is, or with configuration only. |
| 🔷 | **Stock Moodle.** Core Moodle covers it and it needs set-up or theming, not code. |
| 🟡 | **Partial.** Something close exists but needs extension or adaptation. |
| ❌ | **Missing.** Has to be built. |

---

## Executive summary

| # | Client area | Status | Main gap |
|---|---|---|---|
| 1 | Bilingual (AR/EN), responsive, modern site | ✅ | The dark-mode visitor toggle is missing. |
| 2 | Home page + teacher bio + CTAs | 🟡 | No editable fields for the 2 CTA buttons or the booking button, no teacher-profile block, and no real contact form. |
| 3 | School-curriculum lessons: services, pricing, registration form | ❌ / 🟡 | No pricing by curriculum type (International vs National, individual vs group). The client's registration form fields are not covered. |
| 3b | Group booking with invite link and completeness status | ❌ | Nothing exists. |
| 4 | German courses A1.1→A2.2 (8 parts) + registration form | 🟡 | The course catalogue exists. Parts/levels, online/offline price, the "min 10 for online" rule and the course application form are missing. |
| 5 | Payments: Vodafone Cash, InstaPay, Mobinil, Stripe, any Visa + 2% surcharge | 🟡 | Kashier (card + wallet) exists. Manual transfer with receipt upload, an admin review queue, Stripe, InstaPay and the surcharge are missing. |
| 6 | Student account / dashboard | 🟡 | The dashboard has no upcoming lessons, deadlines, grades or remaining lessons. |
| 6b | Parent follows child + parent CC'd on messages | 🟡 | A parent dashboard exists (phone-based, **no parent account**). CC'ing the parent is missing. |
| 7 | Teacher ↔ client communication + skill reports (PDF) | 🟡 / ❌ | Messaging and announcements exist. Skill reports, templates, per-student PDF and send-to-parent are missing. |
| 8 | E-homework | 🔷 | Moodle Assignment + Quiz cover almost everything. |
| 9 | Interactive exams | 🔷 | Moodle Quiz covers every listed question type. |
| 9b | Rewards (stars, confetti, points, badges) | 🟡 | Available only inside the Games Corner, not after quizzes. |
| 10 | Teacher admin dashboard + stats + AI | 🟡 | Reports and AI tools exist. There is no unified dashboard, and the registration-request inbox is missing. |
| 11 | AR/EN translation of the whole UI | ✅ | Every custom plugin has `lang/ar` except `local_opspanel`. |
| 12 | Security & privacy (roles, consent, backups) | 🟡 | No parent role, no consent records, no captcha. Off-site backups depend on an external tool. |
| 13 | Interactive Wall (msgs 2–3) | ❌ | Nothing exists. |
| 14 | Booking / cancellation / refund policy + flows (msg 4) | 🟡 | Cancel and refund exist for 1:1 lessons and purchases. The 72h single reschedule, the full status workflow, the policy page and the buttons are missing. |
| 15 | Platform-usage subscription (msgs 6–7) | ✅ / 🟡 | Plans, expiry and reminders exist. Family/multi-student plans and a separate subscription policy are missing. |
| 16 | Educational Store (msg 8) | 🟡 / ❌ | Coupons, purchases and sales reports exist. A file-product catalogue, product pages, protected downloads and bundles are missing. |

**Overall:** the platform's core (LMS, live classes, payments via Kashier, subscriptions, coupons, reviews, reports, AI assistant, AR/EN) is in place. The main build items are:

1. School-lesson booking with group invites.
2. Manual payment proofs with an admin approval queue.
3. The Interactive Wall.
4. The booking-policy workflow.
5. Student skill reports.
6. Parent accounts / CC.
7. The digital-file store.
8. Consent management.

---

## 1. Project idea, design & audience (Msg 1 §1, §11)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Professional, interactive site, mobile + desktop | ✅ | `theme_nit` (Boost child). Templates use fluid `clamp()` / auto-fit grids, and the SCSS has media queries. |
| Arabic (RTL) + English (LTR) with an easy switch | ✅ | The language menu is in the navbar (`theme/nit/templates/theme_boost/navbar.mustache:366`). `local_nit_mlang` gives per-language inputs, and the `multilang2` filter is installed. |
| Translation of menus, buttons, forms, confirmations, errors | ✅ | `lang/ar` exists in all custom plugins except `local_opspanel`. |
| Cheerful, harmonious brand colours | ✅ | Brand Colors editor (`theme/nit/gallery.php`, `--nit-brand-*` variables). |
| Dark mode | 🟡 | The infrastructure exists (`nit_mode` cookie, admin default), but visitors have no toggle and there is no `prefers-color-scheme` support. |
| Slogan "Focus. Learn. Dream. Achieve. Succeed. Celebrate." | 🟡 | Content only. It can go in the editable hero (`homepage_content.php`). |

## 2. Home page & teacher profile (Msg 1 §2)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Professional teacher photo + short bio | 🟡 | Only through the generic "About" section (image, heading, text) in `theme/nit/classes/local/homepage_content.php:76-81`. There is no dedicated teacher block. |
| Two buttons: "School curriculum lessons" + "German courses" | ❌ | The hero CTAs are hard-coded (`blocks/templates/t1/hero.html:32,34`). Editable CTA label/URL fields are needed. |
| Register / book-a-session button | 🟡 | Registration exists (`local/academy/register.php`). There is no booking CTA on the home page, and the school-lesson booking flow itself does not exist (§3). |
| "Why learn with me" features section | 🟡 | Static cards (`about_points` / `about_cards` in `theme/nit/edit.php`), edited as HTML. |
| Student / parent testimonials, with real reviews later | ✅ | `local_nit_reviews` (submit → moderate → approved feed, `home_picks.php:147-162`). Only template T1 is wired to it; other templates still have placeholder text. |
| Contact / inquiry section | ❌ | Templates have `mailto:` only (`t1/contact.html:2`). No form submits or stores inquiries. A form + inbox + notification is needed. |
| Social links (Instagram, follow/contact button) | ✅ | Footer and home settings (`theme/nit/lib.php:2817-2825`, `sitepages_settings.php:88-100`). |
| Note that Instagram is for contact/marketing, not payment | 🟡 | Content only, no feature. |
| Admin edits home content without code | ✅ | Template picker, bilingual content editor, inline editor, site-pages manager. New sections still need block HTML. |

## 3. School-curriculum lessons (Msg 1 §3)

### 3.1 Services & pricing

| Requirement | Status | Evidence / notes |
|---|---|---|
| Service types: full curriculum (book/booklet), specific lessons, weak points, revision/exams, activity books | ❌ | No "service type" concept. It can be modelled as form options in the new booking form. |
| Session = 1h15m | 🟡 | `local_nit_lessons` 1:1 lessons are fixed at **60 min** (`entity/lesson.php:72`). The duration needs to be configurable. |
| International: 800 EGP individual / 400 EGP per student in a group of 5 | ❌ | Lesson price is in Flex credits per teacher (`nit_teacher_profile.lesson_flex`). No money price per curriculum × session type exists. |
| National: 300 EGP individual / 200 EGP per student in a group | ❌ | Same as above. |
| Clearly state that the group price is **per student** | ❌ | UI copy for the new pricing display. |

### 3.2 Registration form (school lessons)

| Field | Status | Notes |
|---|---|---|
| Student full name | ✅ | Moodle user. |
| Mother's name | ❌ | Not collected. |
| Father's phone / mother's phone | ✅ | Collected in `local/academy/classes/local/registration.php:38-57`. |
| Father's email / mother's email | ❌ | Not collected. |
| Home address (residence) | ❌ | Not collected (only the governorate). |
| Grade | ✅ | Collected. |
| School name + type (Private / Languages / National / International → IG or IB) | 🟡 | School and study system are collected. The IG/IB split and the client's type list need adjusting. |
| Textbook / activity-book name | ❌ | Not collected. |
| Specific lessons / units required | ❌ | Not collected. |
| Goal (full curriculum / specific lessons / weak points / revision) | ❌ | Not collected. |
| Session type: individual or group | ❌ | Not collected. |
| Preferred days/times from the teacher's available slots | 🟡 | Teacher weekly availability + slot finder exist (`nit_teacher_hour`, `teacher_service::slots()`). They need wiring into this form. |
| After submit: teacher gets the request + a notification, reviews it, contacts the parent to confirm | ❌ | No request inbox for student registrations. Accounts only go through email confirmation. A reference exists: the teacher-application review queue in `local_nit_tutors` (configurable fields + statuses), which can be reused as a pattern. |

> The current registration form is hard-coded (`local/academy`). The client wants a **booking request form**, which is a separate entity from the user account. Recommendation: a new `local_nit_booking` plugin (request + fields + status workflow) or a generalised version of the `nit_tutors` field builder.

### 3.3 Group booking

| Requirement | Status | Notes |
|---|---|---|
| "Join with other students?" option | ❌ | |
| Parent enters the other students' names | ❌ | |
| Unique group invite link sent to other parents | ❌ | |
| Each parent opens the link and registers their own child | ❌ | |
| Show the registered count and completion status (green when complete) | ❌ | |
| Booking not confirmed until the group conditions are met (min 4; offline within Cairo) | ❌ | |

`nit_lesson` supports exactly one `studentid`. Group live sessions exist only as teacher-created sessions (`local_academysessions`), with no booking or pricing.

## 4. German courses from zero, A1.1 → A2.2 (Msg 1 §4)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Course catalogue | ✅ | `local/nit_category/index.php` (filters for price, level, rating, teachers). |
| Levels A1.1, A1.2, A2.1, A2.2, each split into 2 parts (8 parts) | 🟡 | The course custom field `level` exists (Beginner/Intermediate/Advanced, editable). Model this as categories or 8 courses. **The client is inconsistent: 12 lessons per part vs 6 lessons per part (48 total). Confirm with them.** |
| A1 / A2 CEFR descriptions | 🟡 | Content only (course summary / category description). |
| Show the lesson count, duration and price before enrolling | 🟡 | The course page (`local/academy/course.php`) shows sections/lessons count, hours and price. The catalogue card shows the price but not the lesson count or duration. |
| Lesson = 3h (2.5h + 0.5h break) | 🟡 | Content / schedule setting. |
| Price per part: 5,000 EGP offline / 6,000 EGP online | 🟡 | Per-course prices exist (`local_payments_course_prices`). There is no online/offline price variant per course. |
| Online course only opens when 10 students have registered | ❌ | No minimum-enrolment / waitlist logic. |
| USD option at a clear fixed exchange rate | 🟡 | Per-country/currency prices exist (10 currencies). There is no exchange-rate conversion, and subscriptions, packages and top-ups are hard-coded to EGP. |
| Online vs offline (in-person) courses | ❌ | Everything is online on Jitsi. There is no offline/location concept. |

### 4.1 Course registration form

| Field | Status |
|---|---|
| Full name, phone, email | ✅ |
| Age | ❌ |
| Child vs adult | ❌ |
| Guardian name + phones + address (if child) | 🟡 (guardian phones only) |
| Current level / "no idea" | ❌ |
| Required level + part | ❌ (selecting the course covers it) |
| Goal of learning German | ❌ |
| Preferred times | ❌ |
| Accept privacy policy + booking terms | 🟡 | A single "I agree" checkbox exists (`register.php:383`). It is not stored as a timestamped consent. |
| After submit → payment-steps page → confirmed after payment review | 🟡 | Kashier online checkout exists. Manual payment review does not (§5). |

## 5. Payments (Msg 1 §5, Msg 5)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Online card payments (all Visa/Mastercard) | ✅ | The Kashier gateway (`payments/provider/kashier`), methods `card,wallet`. |
| Funds deposited to the teacher's bank account | 🟡 | Settlement happens through the Kashier merchant account. This is commercial/onboarding work, not code. |
| **2% fee added to the payer**, not the teacher | ❌ | No checkout surcharge exists. It needs adding to the amount calculation and showing on checkout. |
| Vodafone Cash 01032409265 (manual transfer + instructions) | ❌ | No manual-transfer method. Kashier's generic "wallet" may support Vodafone Cash online. Confirm with Kashier. |
| Mobinil (Orange) Cash, InstaPay (client not ready yet) | ❌ | Prepare as manual methods that can be switched on and off. |
| Stripe (not activated yet) | ❌ | The provider sub-plugin framework exists (`paymentprovider` type). A `provider/stripe` needs building. |
| Upload a receipt screenshot or enter the transaction number | ❌ | |
| Teacher reviews/approves the payment in the dashboard before confirming | ❌ | Only admin-initiated workarounds exist: manual wallet credit, offline package assignment (`nit_flex/api.php:124`), admin-assigned subscription, access codes. |
| Clear payment instructions on the page | ❌ | Part of the manual method. |
| Warn clients not to type card data or passwords into forms | 🟡 | Copy only. Card data already never touches our forms, because it goes to Kashier hosted checkout. |
| No test payment data shown as real | ✅ | Kashier has separate live/test keys. |

## 6. Student account & parent follow-up (Msg 1 §6)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Private account per student | ✅ | Moodle accounts + `local_academy` profile. |
| View enrolled courses/sessions | ✅ | `theme/nit/layout/mydashboard.php:51` + `local/nit_lessons/student.php`. |
| Upcoming lesson times | 🟡 | Listed in `nit_lessons/student.php`, not on the dashboard. 🔷 `block_timeline` / calendar could add them. |
| Homework & exams with deadlines | 🔷 | Stock Assignment/Quiz + Timeline. They are not surfaced on the custom dashboard. |
| Grades & test results | 🔷 | Stock gradebook. They are not on the custom dashboard. |
| Notes & reports available to the student | ❌ | Depends on §7 reports. |
| Completed vs remaining lessons | 🟡 | The dashboard shows completion % per course. No remaining-lesson count. |
| Parent follows the child's progress | 🟡 | `local_parent` is a **phone-based public dashboard without an account** (child phone + guardian phone). It shows video %, homework and exam grades. The parent role was deliberately removed (`docs/parent-accounts.md`). |
| A student's data visible only to their parent / the teacher | 🟡 | Phone-pair matching + per-IP throttling. A proper parent account (Moodle "parent" role in the user context) would be stronger. |
| Teacher can **CC the parent** on messages, homework, exams, reports | ❌ | Not possible without parent accounts. The only parent message is a WhatsApp "instant lesson started" alert. |

## 7. Teacher ↔ students/parents communication & reports (Msg 1 §7)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Send messages / inquiries | ✅ | Core messaging. Student↔student can be blocked (`local/academy/classes/local/student_messaging.php`). |
| Announcements about lesson schedules | ✅ | `local_nit_notifications` (popup + push + optional email) + automatic session reminders. There is no "parents" audience. |
| WhatsApp | 🟡 | WhatsApp Cloud API is used by the AI assistant (`nit_assistant/classes/whatsapp/cloud.php`). There is no teacher→class or teacher→parent broadcast. |
| Periodic student-level reports | ❌ | `nit_reports` has data reports (quiz averages, completion) only. |
| Record strengths / weaknesses | ❌ | |
| Track reading / writing / listening / speaking | ❌ | 🔷 Moodle Outcomes / Competencies could model the 4 skills. |
| Per-student recommendations | ❌ | |
| Ready-made report templates | ❌ | |
| Download the report as PDF / send it to the parent | ❌ | TCPDF RTL export exists for table reports (`nit_reports/classes/pdf.php`). It can be reused for a per-student report. |

## 8. E-homework (Msg 1 §8)

| Requirement | Status | Notes |
|---|---|---|
| Homework per student / group / level | 🔷 | Assignment + Quiz with groups / restrict access. |
| MCQ, true/false, fill-in-the-blanks | 🔷 | `qtype_multichoice`, `truefalse`, `gapselect`, `ddwtos`, `multianswer` (cloze). |
| Text questions & language exercises | 🔷 | `essay`, `shortanswer`. |
| Attach images & files | 🔷 | |
| Due date as a task | 🔷 | Assignment due dates + Timeline. |
| View student answers, auto-grade objective items, grade written ones manually, record grades + feedback | 🔷 | |
| Student solves it on the site and sees the result + feedback | 🔷 | |
| AI-generated quizzes from video transcripts (bonus) | ✅ | `local_nit_ai` quizgen. |

## 9. Interactive exams & rewards (Msg 1 §9)

| Requirement | Status | Notes |
|---|---|---|
| Teacher creates/edits exams without a programmer | 🔷 | Moodle Quiz. |
| MCQ, T/F, complete sentences, word ordering, matching, vocabulary/grammar, reading comprehension | 🔷 | `multichoice`, `truefalse`, `gapselect`/`multianswer`, **`ordering`**, `match`, `description` + questions. |
| Images & audio | 🔷 | |
| Marks per question, pass mark, time limit, attempts, auto-grading | 🔷 | |
| Show correct answers + explanations according to settings | 🔷 | Quiz review options + feedback. |
| Final result + percentage | 🔷 | |
| Stars / celebration animation on a correct answer | 🟡 | Exists only in the Games Corner (`local/games/js/shell.js`). There is no confetti and nothing in quizzes. |
| Points counter | 🟡 | Games only (`local_games_progress`). |
| Achievement badges | 🟡 | Games badges exist (`local/games/classes/progress.php`). 🔷 `core_badges` is available for course goals. |
| Encouraging messages after an exam | 🟡 | 🔷 Quiz overall feedback by grade band. There is no animation. |
| Age-appropriate, works on mobile | ✅ | Games are HTML5 and mobile friendly. |

## 10. Teacher admin dashboard (Msg 1 §10)

| Requirement | Status | Evidence / notes |
|---|---|---|
| New registration requests | ❌ | No request inbox (see §3.2). |
| Student & parent data | 🟡 | Moodle users + guardian phone fields. No parent records. |
| Review/confirm booking requests | 🟡 | 1:1 lessons can be managed (`nit_lessons/manage_lessons.php`). The school/group booking does not exist. |
| Payment follow-up | ✅ | `nit_reports` sales + `local_payments` transactions. Manual-proof review is missing (§5). |
| Manage groups & students | 🔷 | Moodle groups/cohorts. |
| Schedules & sessions | ✅ | `local_academysessions` + `mod_jitsi` (attendance, recordings, live monitor). |
| Create levels, courses, lessons; upload material | 🔷 | Moodle courses. |
| Homework & exams; grades & results | 🔷 | |
| Create & send reports | ❌ | See §7. |
| Messages & inquiries | 🟡 | Messaging + AI inbox (`nit_assistant`). No contact-form inbox. |
| Edit prices & intro content | ✅ | Course pricing, subscription/package admin, homepage editor. |
| Stats on registrations, revenue, active students | ✅ | `local_nit_reports` (12 reports, charts, PDF/Excel/CSV). No single KPI home screen. |
| "All with AI assistance" | 🟡 | `nit_assistant` (Claude sales/support bot, web + WhatsApp, leads, tickets), `nit_ai` (video Q&A, quiz generation). There is no AI for reports or analytics. |
| Easy to use, no coding | 🟡 | Mostly. Some screens are stock Moodle admin. |

## 11. Security & privacy (Msg 1 §12, Msg 3 §5, Msg 7 §9–10)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Secure login | ✅ | Moodle auth, Google sign-in (`local_googleauth`), device limit (`local_nit_devices`). |
| Separate permissions for teacher / student / parent | 🟡 | Teacher and student use the standard roles. A `nittutor` role exists. **There is no parent role.** |
| Student info never public | ✅ | Moodle defaults. The parent dashboard is gated by a phone pair. |
| Parent consent to register child data | ❌ | Only an "I agree to terms" checkbox, not stored with a timestamp. 🔷 `tool_policy` (digital age of consent) could be enabled. |
| Parent consent (written) to share the child's photos/videos | ❌ | No consent record or document upload. Needed before the Interactive Wall. |
| Protect registration forms, files, results | 🟡 | Per-IP rate limit on registration (`registration.php:365-383`). No captcha on custom forms. |
| Clear privacy policy & terms of use | ✅ | `local_nit_pages` seeds terms/privacy pages. `local/multitopics/legal.php` also exists. The texts need writing. |
| Backups in more than one safe place | 🟡 | `local_opspanel` embeds an external "moodle-ops" panel that claims off-site copies. It is not in this repo and not verified. |
| Dashboard protected from unauthorised access | ✅ | Capability checks per plugin. |

## 12. Interactive Wall (Msgs 2–3) — ❌ Not existing

Nothing in the codebase is a post feed. The only thing nearby is a static home-page image gallery (up to 24 images, `theme/nit/edit.php:344-410`).

| Requirement | Status |
|---|---|
| Online: student speaking videos, conversations, presentations, projects + description (grade, skill) | ❌ |
| Offline: classroom activity photos, group projects, games, competitions, cultural days | ❌ |
| Showcase of outstanding work; categorise by stage, grade, activity type | ❌ |
| Title, description, date per post | ❌ |
| Likes & comments (if admin allows) | ❌ (🔷 core comments/ratings API can be reused) |
| Gallery/grid cards, filters (videos/photos/projects), lightbox | ❌ |
| Mobile/tablet/desktop | ❌ (the theme is responsive) |
| Admin upload/edit/delete, organise by grade/type | ❌ |
| Moderation/approval before publishing | ❌ (`nit_reviews` moderation can be reused as a pattern) |
| Visibility: public / students & parents only / specific groups | ❌ |
| Publish only after written parental consent (stored for legal use) | ❌ |
| No public upload/comment without controls | ❌ |
| Video hosting | 🟡 (`local_vdocipher` / `local_vimeo` can host videos) |

Recommendation: a new plugin `local_nit_wall` with tables for posts, media, tags, reactions/comments and consent links, plus a moderation queue and an audience field.

## 13. Booking, cancellation & refund policy (Msg 4)

### 13.1 Booking

| Requirement | Status | Notes |
|---|---|---|
| Book individual & group, online & offline | 🟡 | Individual online only (`nit_lessons`). |
| Show available slots, type, duration, price before booking | 🟡 | Slots exist, the duration is fixed at 60 min, and the price is in Flex credits. |
| Electronic **or cash** payment per session option | 🟡 | Online via Kashier/wallet. Cash means admin workarounds only. |
| Confirmation message with details, price, method | 🟡 | `lessonupdate` notifications exist. No price/method details. |
| Send a copy of the policy on confirmation | ❌ | |

### 13.2 Reschedule ("Change Booking")

| Requirement | Status | Notes |
|---|---|---|
| One free reschedule if requested ≥ 72h before | 🟡 | Reschedule request/response exists (`lesson_service.php:597-630`). The deadline is configurable (default 12h). **There is no "once only" limit.** |
| Choose an alternative from the available slots; confirm only if free | ✅ | Availability check in `lesson_service.php:80`. |
| Log the reschedule count per booking; message if already used | ❌ | |
| 72h expiry does not void legal rights | ❌ | Policy text + logic. |

### 13.3 Cancellation & refund (Egyptian Consumer Protection Law 181/2018)

| Requirement | Status | Notes |
|---|---|---|
| Explain reschedule vs cancel vs withdrawal from a prepaid contract | ❌ | Policy content. |
| Distance-contract withdrawal right + exceptions | ❌ | Needs **legal review**. That is not a developer deliverable. |
| Teacher cancels → free alternative or refund | 🟡 | A teacher cancel with reason exists. The Flex credits are returned. |
| Package refund: delivered vs remaining sessions → amount due | ❌ | Refunds exist only for course/activity/subscription purchases (`nit_finance/refunds.php`), to the wallet minus a fee. Not for packages or lessons. |
| Refund via the legal method/time; no hidden fees | 🟡 | Kashier `refund()` is implemented but never called, so refunds go to the wallet only. The refund fee % must be disclosed. |
| No automatic forfeiture or rejection without review | 🟡 | Late lesson cancellation consumes the Flex **automatically**. This conflicts with the requirement. |

### 13.4 Admin

| Requirement | Status | Notes |
|---|---|---|
| View all bookings | ✅ | `manage_lessons.php` (1:1). |
| Accept/track reschedules | ✅ | For 1:1 lessons. |
| View cancel/refund requests | ✅ | `manage_refunds.php`. |
| Statuses: New / Under review / Accepted / Rejected / Refunded | 🟡 | Only pending / approved / rejected. |
| Record the decision reason + date | ✅ | `adminreason`, `timeprocessed`. |
| Notify the student of the decision with the reason | ✅ | Refund-result message. |
| Log of bookings, payments, reschedules, refunds | 🟡 | Payments have an audit log. Lesson status changes are not logged (`transition()` at `lesson_service.php:783`). |

### 13.5 UI

| Requirement | Status |
|---|---|
| Separate page "Booking, Cancellation & Refund Policy" | 🟡 (can be created in `nit_pages`; no content yet) |
| Link in the footer + booking & payment pages | 🟡 (footer links are editable; no link on checkout) |
| Show the policy before payment + save a copy | ❌ |
| Buttons **Change Booking**, **Cancel Booking**, **Request Refund** | 🟡 (actions exist under other names, for lessons only) |
| Mobile-friendly | ✅ |

## 14. Platform-usage subscription (Msgs 6–7)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Independent subscription system, separate from lesson fees | ✅ | `local_nit_subscriptions` (separate from Flex/lessons). It currently **unlocks course access**, so it needs remapping to "platform features" (reports, exams, wall). |
| Admin sets the fee + duration | ✅ | `nit_subscription.price`, `duration_days`. |
| One student or multiple students per plan | 🟡 | B2B seat options exist. No assignment of seats to siblings and no parent link. |
| Show included services, price, duration before payment | ✅ | Plan listing + checkout. |
| Electronic or cash | 🟡 | Kashier + admin-assigned (`source=admin_assigned`). |
| Notify before expiry / renewal | ✅ | Hourly `send_subscription_reminders`, configurable lead days. |
| Track status, start and end dates in the dashboard | ✅ | `manage_subscriptions.php` users tab + subscriptions report. |
| Separate renewal / cancellation / refund policy (Egyptian law) | ❌ | Policy page + content. |
| No auto-charge/renew without consent | ✅ | One-time checkouts; renewal is manual. |
| Access control so students can't see others' data | ✅ | Moodle capabilities. |
| Parental consent mechanism | ❌ | See §11. |
| Monthly vs per-term recommendation + cost | — | A business/quotation item. `duration_days` supports both (30 / ~120 days). |

## 15. Educational Store (Msg 8)

| Requirement | Status | Evidence / notes |
|---|---|---|
| Categories: Worksheets, PowerPoint, Exams & Revision Packs | ❌ | No product entity. Only courses/activities are sold. |
| Product page: name, images, description/objectives, grade/level, file type, pages/slides, price + discount, preview | ❌ | A video preview exists for lessons only (`nit_finance/preview.php`). |
| Online payment & checkout | ✅ | Kashier checkout + wallet (reusable). |
| Download after purchase from the account | 🟡 | Per-activity unlock exists (`nit_item_price` / `nit_purchase`). There is no file-product download. |
| "My Purchases" with re-download | 🟡 | `get_my_purchases` in the wallet page. It needs to list files. |
| Admin: add products, upload PDF/PPTX, edit price/description/images | ❌ | |
| Discounts | ✅ | `local_nit_commerce` coupons + offers (an item type "product" needs adding). |
| Bundles | ❌ | |
| Track orders, payments, sales | ✅ | `nit_reports` sales report (needs a "store" stream). |
| Show/hide products | ❌ | |
| Protect original files (no link access for non-buyers) | 🟡 | Activity access gating exists. It needs `pluginfile` serving with a purchase check, not public URLs. |
| Usage & copyright terms (personal / teaching, no resale) | ❌ | Content + checkbox at checkout. |
| Mobile-friendly | ✅ | Theme. |
| Separate store revenue in financial reports | 🟡 | The sales report splits by item type (course / subscription / package / wallet). It needs a "store" type. |
| Cost of gateway, storage, ops in the quote | — | Quotation item. |

Recommendation: a new `local_nit_store` plugin (product, category, files, bundle), reusing `local_payments` checkout, `nit_commerce` coupons and `nit_reports` sales.

## 16. Final delivery requirements (Msg 1 §13)

| Requirement | Status |
|---|---|
| Real working site, not mock-ups | ✅ (a production Moodle stack) |
| Forms save to the DB | 🟡 (registration yes; booking, contact and course-application forms are missing) |
| Admin dashboard | ✅ / 🟡 |
| Homework, exams, results working | ✅ (stock Moodle) |
| Scalable professional platform | ✅ |

---

## Items to build (proposed backlog)

| Priority | Item | Suggested location | Reuses |
|---|---|---|---|
| P1 | Booking request system (school lessons + courses): configurable forms, curriculum × session-type pricing, online/offline, request inbox with statuses | `local_nit_booking` (new) | `nit_tutors` field builder, `nit_lessons` slots |
| P1 | Group booking: invite link, member counter, min-size rule, confirm-when-complete | `local_nit_booking` | — |
| P1 | Manual payment methods (Vodafone Cash, InstaPay, Orange Cash): instructions, receipt upload / txn ID, admin approval queue | `local_payments` provider `manual` | `local_payments_transactions` |
| P1 | Payer surcharge (2%) on card payments | `local_payments` | Kashier gateway |
| P1 | Parent accounts (role + link to children), parent CC on messages/assignments/reports | `local_parent` (re-introduce accounts) | `docs/parent-accounts.md` history |
| P1 | Consent management: data-processing + media consent, signed file upload, timestamped record | `local_nit_consent` (new) or 🔷 `tool_policy` | — |
| P2 | Interactive Wall (posts, media, filters, lightbox, moderation, audience, likes/comments) | `local_nit_wall` (new) | `vdocipher`/`vimeo`, `nit_reviews` moderation |
| P2 | Booking policy workflow: single 72h reschedule counter, extended refund statuses, package/lesson refunds, gateway refunds, audit log, policy acceptance before checkout, the 3 buttons | `nit_lessons`, `nit_finance`, `local_payments` | existing refund flow |
| P2 | Student skill reports (4 skills, strengths/weaknesses, recommendations, templates, PDF, send to parent) | `local_nit_reports` or new `local_nit_progress` | TCPDF RTL in `nit_reports/classes/pdf.php` |
| P2 | Educational Store (products, files, preview, bundles, My Purchases, protected downloads, store revenue stream) | `local_nit_store` (new) | payments, commerce, reports |
| P3 | Home page: editable CTA buttons, teacher-profile block, working contact form + inbox | `theme_nit`, `local_nit_pages` | homepage content editor |
| P3 | Student dashboard: upcoming lessons, deadlines, grades, remaining lessons | `theme/nit/layout/mydashboard.php` | 🔷 timeline/calendar/grades |
| P3 | Quiz celebrations (stars/confetti, points, encouraging messages, badges) | `theme_nit` AMD or `local_games` | games shell, 🔷 core_badges |
| P3 | Course catalogue: A1.1–A2.2 parts, lesson count/duration on cards, min-10 online rule, USD fixed rate | `local_nit_category`, `local_payments` | course custom fields |
| P3 | Family/multi-student subscription; remap subscriptions to platform features; subscription policy | `local_nit_subscriptions` | seat options |
| P3 | Captcha on public forms; dark-mode toggle; parent audience in notifications; WhatsApp teacher broadcast | various | — |
| P3 | Unified KPI dashboard; AI help for reports | `local_nit_reports`, `local_nit_ai` | — |

## Open questions for the client

1. Lessons per part: **12** or **6**? (Msg 1 says both: "each part 12 lessons" and "each part 6 lessons, 48 total".)
2. Minimum group size: Msg 1 says a group of **5** (main + 4) for pricing but a minimum of **4** for confirmation. Which number confirms the group?
3. Msg 4 mentions both **72h** and **48h** deadlines. Which is correct?
4. Is the 2% surcharge on card payments only, or on wallets/InstaPay too?
5. Platform subscription: monthly or per school term? Per student or per family?
6. Offline lessons: is the location the teacher's centre or the student's home (Cairo only)?
7. The legal text (Consumer Protection Law 181/2018) needs review by a lawyer. Who provides it?
8. The fixed USD exchange rate: who updates it, and how often?

## Out of scope for code (business / ops)

- Kashier/Stripe merchant onboarding, bank settlement, InstaPay/Orange Cash account creation.
- Legal drafting of the policies (privacy, terms, booking/refund, subscription, store copyright).
- Hosting, storage and payment-gateway cost estimates for the quotation.
- Verifying the off-site backups (the external `moodle-ops` tool).
