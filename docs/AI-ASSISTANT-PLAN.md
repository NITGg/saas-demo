# AI Assistant for the Academy (Moodle) — Analysis & Plan

> Status: **plan, no code yet** (12/10/2026). Same idea as the AI agent built for nit-eg.com
> (nit2, SRS "NITG AI Agent" v1.2), adapted to this Moodle academy: courses, subscriptions,
> Flex lesson packages, lessons, wallet, live sessions, certificates.

---

## 1. What we're building, in one line

A chat assistant on every academy page (and in the mobile app) that **sells** to visitors
(plans, packages, courses, offers — official prices only), **supports** signed-in students
(“when does my subscription end?”, “how many Flex do I have?”, “when is my next lesson?”,
“where is my certificate?”), **helps teachers** (“my lessons today”, “my earnings”), and
**hands over to the academy's staff** when needed — with an inbox for staff, cost control,
and evals, like nit2.

---

## 2. What already exists (analysis)

| Area | What's there | What it means for the assistant |
|---|---|---|
| **AI** | `local_nit_ai` (ALPHA): per-video Q&A from the VdoCipher transcript + teacher quiz generator. Goes through Moodle **core_ai** (`generate_text`) — one prompt string, **no tool use, no system role, no streaming**. Core providers: openai, azureai, gemini, ollama, deepseek, awsbedrock (Claude only via Bedrock). No Anthropic provider. | Keep `local_nit_ai` for **lesson-content** questions and link to it. The assistant needs **tool use + streaming**, which core_ai can't do → its own model client (see §4). |
| **Subscriptions** | `local_nit_subscriptions`: plans (`nit_subscription`), course coverage (`nit_course_access`), purchases (`nit_sub_purchase`, status/expires_at), B2B purchase with seats (no invitation/seat-member code yet). Web services: `get_available_subscriptions`, `get_my_subscriptions`, `get_subscription_payment_history`, `create_subscription_checkout`; api.php `get_my_active_subscription`. | Sales: list plans + covered courses. Support: “my plan, days left, which courses it opens”. |
| **Flex packages** | `local_nit_flex`: `nit_package`, `nit_package_purchase` (remaining/reserved/consumed, expires_at), ledger `nit_flex_tx`. api.php `get_packages`, `get_package_quote`, `get_my_flex`, `get_my_packages`, `get_flex_history`. One active package at a time; unused Flex expires. | Sales: packages + quote. Support: balance, expiry, history. |
| **Lessons (1-to-1)** | `local_nit_lessons`: `nit_lesson` (11 statuses), proposals, teacher profile/subjects/hours. api.php `get_teachers`, `get_teacher_slots`, `request_lesson`, `get_my_lessons`, `get_lesson`, `get_lesson_join`. | “Next lesson / status / join link”, teacher discovery; booking stays a link (or a confirmed action later). |
| **Wallet & single items** | `local_nit_finance`: `nit_wallet`, `nit_wallet_txn`, `nit_purchase`, `nit_access_code`, teacher `nit_earning`/`nit_withdrawal`. api.php `get_wallet`, `get_wallet_history`, `get_my_purchases`, `get_course_lesson_prices`, teacher earnings. | Wallet balance/history; teacher earnings. |
| **Payments** | `local_payments`: **Kashier only**, per-country course prices (`local_payments_course_prices`), transactions with a status machine, invoices, refunds (admin only). Web services `get_courses_with_pricing`, `get_course_price`, `get_payment_history`, `get_invoice`, `get_course_access`… Buy pages: `/local/payments/buy.php?courseid=`, `nit_finance/walletpay.php`, `create_*_checkout`. Currency: courses per country; subscriptions/packages/wallet in **EGP**. | Quote official prices in the right currency; checkout = **link only** (assistant never creates a payment). |
| **Offers & coupons** | `local_nit_commerce`: `get_available_coupons`, `get_available_offers`, `preview_discount`. Programs not built. | Show current offers; never invent discounts. |
| **Other data** | Certificates (`academy` `get_my_certificates`), live sessions (`academysessions` `get_student_sessions`, recordings), ratings (`local_nit_reviews`), CMS pages/articles (`local_nit_pages` — FAQ, policies), video progress, licence tier/features (`local_license`). Parent = `get_child_report` by two phone numbers (no parent role). | Knowledge base = course summaries + CMS pages + admin notes. |
| **Infrastructure** | `local_nit_core`: crypto for secrets (`config_secret`), config/flags/DI/cache helpers, base scheduled/adhoc task, audit trait. `local_nit_notifications`: bell + push (FCM) + email, `sender::send_to_users`. No WhatsApp/Telegram sending. Theme injects via hooks; plugins already use `before_footer_html_generation`. Custom JSON API pattern `/local/<plugin>/api.php?function=…&token=…` (token user or shared visitor token). | Reuse crypto, notifications, task bases, hooks; follow the api.php pattern for the mobile app. |
| **SaaS** | Each client academy is **its own Moodle** (own DB + moodledata, subdomain), provisioned from nit2; **branch per client**; features gated by `local_license` tier. | The assistant must be **per-tenant**: own data, own key/budget/settings, enabled per tier. |
| **Rules** | No Moodle core edits (CLAUDE.md); PHPUnit tests per `AGENT_TESTING.md` (failing-first, wrong-user refused, mock only network/clock/payments). | Everything lives in one new plugin + theme-free injection. |

---

## 3. Key decision — where the assistant runs

| Option | Pros | Cons |
|---|---|---|
| **A. Inside each Moodle as a plugin `local_nit_assistant` (PHP)** ✅ | Data never leaves the tenant; runs **as the logged-in user** with Moodle capabilities (no token bridging); works per tenant automatically; settings/budget per academy; tier-gated by `local_license`; mobile app gets it through web services. | Rewrite the runtime in PHP (we reuse nit2's **design**, not its code); SSE streaming needs care under Apache/PHP. |
| B. Central service in nit2 (Node) calling each academy's web services | Reuses nit2 code (runtime, inbox, evals). | Every academy's student data flows into one shared DB (privacy/isolation risk); token bridging per tenant; academy staff would work in nit2, not their academy; outages hit all tenants. |

**Recommendation: A.** The plugin follows the nit2 contract that already proved itself:
tool registry with strict schemas, server-side ownership (the model never supplies a user id),
budget reserve/settle, idempotency, redaction, handoff state machine, inbox, evals.

**Model access:** call the **Anthropic Messages API directly** from the plugin (tool use +
streaming + prompt caching) through a small client class behind an interface, so a Bedrock
(core_ai) provider can be added later. Models as in nit2: Sonnet 5.5 for chat, Haiku 4.5 for
summaries/tags. API key stored encrypted with `local_nit_core\helper\crypto::config_secret`.

---

## 4. Architecture

```
Page (theme_nit) ──hook before_footer──► chat launcher (plain JS, RTL, like nit_ai/chat.js)
      │                                       │  POST /local/nit_assistant/chat.php  (SSE)
      │                                       ▼
Mobile app ──► web service local_nit_assistant_send (JSON, non-streaming)
                                              │
                         runtime\turn  (limits, timeout, budget reserve/settle, lock)
                                              │
              tools\registry  ──►  sales\*  |  student\*  |  teacher\*  |  common\*
              (strict schemas, mode + capability check, ownership from $USER, audit, redaction)
                                              │
                internal PHP APIs of nit_subscriptions / nit_flex / nit_lessons /
                nit_finance / payments / commerce / academy / academysessions / nit_pages
```

- **Modes, decided by the server on every message:** `sales` (visitor, or a user with no
  student role) · `student` · `teacher` (has a teacher role) · later `parent`. The model never
  chooses its mode.
- **Tools call the plugins' PHP classes directly** (not HTTP), as `$USER`, and re-check
  ownership — e.g. `get_my_lessons` reads only `studentid = $USER->id`.
- **Storage (new tables):** `nit_ai_conv` (status open/waiting_human/human/closed, mode,
  userid|sessionkey, lang, stateversion, lock), `nit_ai_msg` (role, content, status
  sent/draft/discarded/failed, tokens, model), `nit_ai_toolaudit` (redacted),
  `nit_ai_usage_day` (reserved/spent/committed), `nit_ai_usage_event` (per call: cost per
  user/model), `nit_ai_lead`, `nit_ai_ticket`, `nit_ai_idem`, `nit_ai_ratelimit`.
- **Privacy API provider** (export + delete a user's conversations) — required for a Moodle
  plugin and for GDPR.
- **Staff side (admin pages in the plugin):** AI Inbox (take over / reply / hand back),
  Leads, Tickets, Usage & cost, Settings (on/off, budget, greeting, notes, abuse limits,
  working hours, models), Follow-ups (phase 4). Capabilities: `local/nit_assistant:use`,
  `:viewinbox`, `:reply`, `:manage`.
- **Alerts to staff:** `local_nit_notifications` (bell + push + email) to users holding
  `:viewinbox`; optional Telegram per academy.
- **Languages:** reply in the language the user writes in; fixed texts in `lang/ar` + `lang/en`.
- **SaaS:** enabled per tier through a `local_license` feature (`ai_assistant`), with a
  per-tier default daily budget; usage can be reported back to nit2 for billing (phase 5).

---

## 5. Tools by mode (read-only unless marked)

**Sales (visitors and signed-in non-students)**
| Tool | Uses |
|---|---|
| `list_subscriptions` | plans, duration, price (EGP), covered courses, B2B seat options, current offer |
| `list_packages` / `quote_package` | Flex packages, price, expiry; quote with coupon |
| `search_courses` / `get_course` | catalog: title, summary, teacher, rating, price for the visitor's country, free/paid, covered by which plan |
| `list_offers` / `preview_discount` | active offers and coupons — never invents a discount |
| `find_teachers` | teachers by subject, rating, lesson price in Flex |
| `search_help` | CMS pages/articles (FAQ, refund policy, how-to) + admin notes |
| `start_checkout` | **link only** to the existing buy page (subscription / package / course / wallet top-up); sign-up first if needed |
| `capture_lead` *(write)* | name, phone/email, interest, consent |
| `handoff_to_human` *(write)* | to academy staff |

**Student (signed in)**
| Tool | Uses |
|---|---|
| `my_subscription` | active plan, days left, expiry, courses it opens, history |
| `my_flex` | balance, reserved, package expiry, recent ledger |
| `my_lessons` | upcoming/past lessons, status explained, teacher, **join link** when open |
| `my_live_sessions` | next live class, recordings |
| `my_wallet` | balance, recent transactions |
| `my_payments` | payments with status in plain words, invoice link; failed → how to retry |
| `my_certificates` | certificate list + download link |
| `my_progress` | course completion / video progress |
| `renewal_link` | link to renew / buy a package / top up |
| `open_ticket` *(write)* | billing / lesson / technical problem → staff |
| `ask_lesson_video` | points to the lesson's video assistant (`local_nit_ai`) when the question is about lesson content |

**Teacher (phase 3):** `my_teaching_lessons` (today, pending requests), `my_earnings`
(earnings/withdrawals), `my_availability`, `search_help` for teacher how-tos.

**Never:** create payments, refund, cancel, change enrolment, book or cancel lessons
(phase-5 candidate *with* explicit confirmation), reveal other users' data, quote prices not
returned by a tool.

---

## 6. Phases & estimates

| Phase | Scope | Days (1 dev) |
|---|---|---|
| **0 · Foundation** | Plugin skeleton, tables, settings (encrypted key), Anthropic client (tool loop, streaming, caching, retries), budget reserve/settle, rate limits, conversation lock + state machine, redaction, privacy provider, capabilities, PHPUnit harness with a fake model client | 5 |
| **1 · Sales assistant** | Chat launcher on every page (hook, RTL, mobile sheet), SSE endpoint + mobile web service, sales tools, knowledge from CMS/course summaries, lead capture with consent, handoff, **AI Inbox** + takeover/reply, staff alerts, usage & cost page, settings page, evals (≈40 cases AR/EN incl. injection & discount pressure) | 9 |
| **2 · Student support** | Mode decision, student tools, open_ticket + Tickets page, renewal links, link-out to video assistant, evals with seeded fixtures (≈20 cases + privacy cases: another student's data must never show) | 7 |
| **3 · Teacher assistant** | Teacher tools + evals | 3 |
| **4 · Proactive follow-ups** | Daily task drafts (subscription expiring in N days, Flex expiring, checkout started but unpaid — `local_payments_transactions` pending, abandoned chat with consent); staff approve in the inbox; sent via `local_nit_notifications` (push/bell/email) | 4 |
| **5 · Optional** | WhatsApp per academy (needs each academy's Meta number), parent assistant, “which lesson explains X” over approved `local_nit_ai` transcripts, booking a lesson with confirmation, usage reporting to nit2 for billing | — |

**Total for phases 0–4: ≈ 28 working days**, delivered phase by phase with UAT after each,
like nit2.

---

## 7. Testing (per AGENT_TESTING.md)

- **PHPUnit `advanced_testcase`** for every tool (owner sees own data; **another user is
  refused**), the turn loop (limits, timeout, budget exhausted, takeover discards the reply),
  state machine (single-winner takeover), rate limits, privacy provider, inbox actions.
- **Fake model client** injected through `local_nit_core` services (no network in unit tests).
- **Evals** (real model, on demand): a CLI that seeds a test course catalog/users and runs
  the conversation set, judged by Haiku with the tool results as evidence; gate ≥ 90%
  overall, 100% on safety groups — same as nit2.
- CI: add the plugin's tests to `agent-tests.yml`.

---

## 8. Decisions (agreed 12/10/2026)

1. **Placement:** a plugin inside each academy — `local_nit_assistant`.
2. **Cost:** each academy pays with **its own Anthropic API key**, set in the plugin settings
   (stored encrypted). No key → the assistant stays off.
3. **Handoffs:** each academy's **own staff** (users with `local/nit_assistant:viewinbox`).
4. **Actions:** **read-only + links** for now. No booking, paying, refunding or cancelling.
5. **Model access:** the **Claude API directly** (no core_ai / Bedrock).
6. **WhatsApp:** per academy, configured in the settings like nit2 — **built last** (after phase 4).
7. **Scope:** work only on **bassthalk** (branch `bassthalk-clone`); pushes go to that branch.

---

## 9. Phase 1 — what was built (sales assistant)

**Visitors' chat:** a chat button on every page (hook `before_footer_html_generation`; not in the
app WebView, chrome-free layouts or admin pages), RTL, a full-screen sheet on phones. Replies
stream from `chat.php` (server-sent events); `api.php` returns the history and starts a new chat.
A visitor is known by a random key kept in their browser (only its hash is stored); the chat follows
them when they sign in and is not shown again after they sign out. The mobile app uses the web
services `local_nit_assistant_send_message` / `local_nit_assistant_get_messages`.

**Sales tools** (all read the academy's own plugins, so prices/offers match the checkout):
`list_subscriptions`, `list_packages`, `search_courses`, `get_course`, `list_offers` (never coupon
codes), `preview_price` (offer + a coupon the visitor typed; 5 coupon checks per chat),
`find_teachers` (no phone/email), `search_help` (CMS pages, articles, the academy's notes; Arabic
spelling variants), `start_checkout` (link only), `capture_lead` (only with consent),
`handoff_to_human`, plus `get_academy_info`.

**Staff side** (Site administration → Plugins → Local plugins → AI assistant):
AI Inbox (waiting first; take over, reply — replying takes over —, hand back, close; refreshes
itself), Leads (status + notes), Usage & cost (30 days, per model), Settings. Alerts (bell, push,
email) go to users with `local/nit_assistant:viewinbox` — the site admins if nobody has it —
through `local_nit_notifications`, provider `local_nit_assistant/staffalert`.

**Settings added:** visitors can chat, greeting, notes for the assistant (one topic per paragraph),
team working hours, messages per hour per person (×4 per day).

**Tests:** 53 PHPUnit tests; mutation checks for the safety rules. **Evals:** 40 AR/EN cases in
`tests/evals/sales_cases.json`, run with the real model on demand:
`NIT_ASSISTANT_EVAL_KEY=sk-ant-… vendor/bin/phpunit --testsuite local_nit_assistant_testsuite --group nit_assistant_eval`
(test environment only; costs a few cents per run). Gate: 90% overall, 100% of safety cases.

**To switch it on for an academy:** set `$CFG->nit_crypto_key` (if not set), then in the settings
turn it on, paste the academy's Anthropic key, set a daily budget, write the notes and working
hours, and give the staff role `local/nit_assistant:viewinbox` + `:reply`.

**Known:** Flex entities (`local_nit_core\base\entity::get_id()` is public) throw under developer
debugging — a separate fix; the assistant's Flex tests switch developer debugging off meanwhile.
