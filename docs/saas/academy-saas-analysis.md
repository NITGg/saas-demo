# Academy‑as‑a‑Service — Analysis & Architecture

> **One line:** a visitor clicks **“Create academy”** on nit‑eg.com, fills a short
> form, and within minutes gets their **own branded LMS** (a clone of Excellence
> Academy / EAAC) live on the server — free **Demo** for 2 weeks, then upgradeable
> to **Basic / Standard / Professional**, each unlocking more features.

This is the *analysis / documentation* pass (per Ahmed’s “ممكن تعملوا analysis
documentation الأول”). No code yet — this defines **what** we build, the **big
decisions**, and a **phased plan**. Numbers/tiers below come straight from the
WhatsApp brief.

---

## 1. The customer journey (target)

```
nit-eg.com ──[Create academy]──► Form (name AR/EN, logo, hero1, hero2, colors…)
     │
     ▼
Control plane records the request  ──►  Provisioning pipeline
     │                                        │  clone EAAC base
     │                                        │  new DB + moodledata + subdomain
     │                                        │  apply branding + tier limits
     │                                        │  seed demo content
     ▼                                        ▼
  “Your academy is ready” email  ◄────  https://<name>.nit-eg.com  (Demo, 14 days)
     │
     ▼
  Upgrade → Basic / Standard / Professional  (payment)  → limits lifted, 1 year
```

Two clearly separate systems, connected by one pipeline:

| System | Role | Tech (today) |
|--------|------|--------------|
| **nit‑eg.com** | Marketing site + **Create‑academy form** + owner dashboard | Next.js 14, MongoDB/Prisma, next‑intl (AR/EN) |
| **EAAC LMS** | The product each academy runs | Moodle 5.2, Docker (mariadb+moodle+cron), our plugins + `theme_nit` |
| **Control plane** *(new)* | Tracks academies, tiers, expiry; drives provisioning | to be built (extends the Next.js side) |
| **Provisioning orchestrator** *(new)* | Turns a form submission into a live, branded, tier‑limited instance | to be built (worker + scripts) |

---

## 2. The single biggest decision — tenancy model

**How does each academy get isolated from the others?**

| Option | What it means | Verdict |
|--------|---------------|---------|
| **A. Instance‑per‑tenant (“clone EAAC”)** | Each academy = its **own Moodle** (own DB + own `moodledata`), same shared code image, reached at its own subdomain. | ✅ **Recommended** — matches the brief (“Clone eaac”), gives clean isolation, per‑academy branding & feature toggles, and no dependence on Moodle features we don’t have. |
| B. One Moodle, many “tenants” inside | A single Moodle split by category/cohort. | ❌ Core Moodle is **not** multi‑tenant (that’s Moodle **Workplace**, a paid product). Per‑tenant branding, isolation, and feature limits would be a constant fight. |

**Recommendation: Option A.** Concretely:

- **One shared code image** (the EAAC image we already build) runs **every** academy. Academies differ only in **config + database + moodledata + branding**, never in code.
- **Shared MariaDB server, one database per academy** (`moodle_<slug>`), or a DB container per academy if we want harder isolation later. Start with one server, many DBs.
- **Subdomain per academy** — `slug.nit-eg.com` (wildcard DNS `*.nit-eg.com` + wildcard TLS). Custom domains (`academy.com`) become a Professional add‑on later.
- A **reverse proxy** (Traefik or nginx) maps each subdomain → that academy’s container.

### The “publish to GitHub per academy” question — recommend **against** (mostly)

The brief says *“Publish academy on GitHub (new repo or new branch)”*. Analysis:

- If every academy becomes its **own repo/branch of code**, we get **code divergence** — 50 academies = 50 branches to patch for every security fix. That does **not** scale and defeats “automatic”.
- **Better:** all academies run **one repo, tagged releases** (the shared image). What differs per academy is **config + data**, which we *can* version — store each academy’s **config + branding manifest** (not the whole Moodle codebase) in a control‑plane record (and optionally a small per‑academy git repo of *just* that manifest).
- **Keep the GitHub‑per‑tenant option only for Professional** customers who buy **custom code** — then they get a branch off the base and a CI deploy. For Demo/Basic/Standard, never fork code.

> **Decision needed (D1):** confirm “shared image + per‑academy config” instead of a code repo per academy. Everything below assumes this.

---

## 3. System architecture

```
                         ┌────────────────────────────────────────┐
                         │            nit-eg.com (Next.js)          │
   visitor ───► Create   │  • Create-academy form (name, logos…)    │
                academy   │  • Owner dashboard (status, upgrade)     │
                         └───────────────┬──────────────────────────┘
                                         │  POST /api/academies
                                         ▼
                         ┌────────────────────────────────────────┐
                         │        Control plane (new service)       │
                         │  DB: Academy, Plan, License, Job         │
                         │  emits provisioning jobs, tracks expiry  │
                         └───────────────┬──────────────────────────┘
                                         │  job
                                         ▼
                         ┌────────────────────────────────────────┐
                         │     Provisioning orchestrator (worker)   │
                         │  1 create DB + moodledata                │
                         │  2 start container from EAAC image       │
                         │  3 install from TEMPLATE dump (fast)     │
                         │  4 apply branding (theme_nit)            │
                         │  5 apply tier license (local_license)    │
                         │  6 seed demo content                     │
                         │  7 register subdomain + TLS              │
                         └───────────────┬──────────────────────────┘
                                         ▼
   ┌──────────── Reverse proxy (Traefik/nginx, *.nit-eg.com + wildcard TLS) ─────────────┐
   │   slugA.nit-eg.com→[moodle_A]   slugB.nit-eg.com→[moodle_B]   … each: container+DB    │
   └──────────────────────────────────────────────────────────────────────────────────────┘
            shared MariaDB server (one DB per academy)   •   shared cron (or per‑tenant)
```

### Components to build
1. **Control plane** — data + API for academies/plans/licenses/jobs. Extends the existing Next.js/Prisma side (add a Postgres or reuse Mongo; a relational store is cleaner for licensing — see D2).
2. **Provisioning orchestrator** — a worker (Node or PHP CLI) that executes the pipeline steps idempotently, with retries and status reporting back to the control plane.
3. **`local_license` plugin** *(inside Moodle)* — enforces tier limits & expiry from inside each academy (the core of the product; §5).
4. **Branding seeder** — maps form inputs → `theme_nit` settings (§6).
5. **Reverse proxy + wildcard DNS/TLS** — routing and certificates.
6. **Template database** — a pre‑installed EAAC snapshot so provisioning is *seconds*, not a full Moodle install (§4).

---

## 4. Provisioning pipeline (form → live)

Each step is **idempotent** and reports status so the dashboard can show progress and a failed job can be retried.

| # | Step | How |
|---|------|-----|
| 1 | **Validate & reserve** | slug uniqueness, name AR/EN, plan=Demo. Create `Academy` + `Job` rows. |
| 2 | **Create DB + data dir** | `CREATE DATABASE moodle_<slug>`; make `moodledata_<slug>/`. |
| 3 | **Bring up instance** | start a Moodle container from the **EAAC image** with env: `wwwroot=https://<slug>.nit-eg.com`, `dbname`, `dataroot`. |
| 4 | **Install fast (template)** | import a **template SQL dump** (a clean EAAC with all plugins already installed) instead of `install_database.php` → provisioning in seconds. Then run `upgrade.php --non-interactive` (no‑op) + `purge_caches`. |
| 5 | **Branding** | run the seeder: set site name AR/EN, logo, hero1/hero2, colors via `theme_nit` settings + `admin/cli/cfg.php` (§6). |
| 6 | **License / tier** | write the `local_license` record: tier=Demo, limits, `expires = now + 14d`. |
| 7 | **Seed demo content** | create 1 course + 4 video activities + 2 quizzes (demo tier), a default admin user for the owner. |
| 8 | **Route + TLS** | register `<slug>.nit-eg.com` with the proxy; issue/attach wildcard cert. |
| 9 | **Finish** | mark ready; email owner the URL + first‑login credentials. |

> **Why a template dump (step 4):** a fresh Moodle install + plugin upgrades takes minutes and is fragile under automation. A restore of a known‑good snapshot is fast and deterministic — the single most important trick for “automatic”.

---

## 5. Tiers & feature gating — the heart of the product

### 5.1 Tier matrix (from the brief)

| Capability | **Demo** (free, 14 days) | **Basic** (1 yr) | **Standard** (1 yr) | **Professional** (1 yr) |
|---|---|---|---|---|
| Courses | 1 | 3 | 10 | unlimited |
| Teachers | 1 | 1 | unlimited | unlimited |
| **Video source** | limited | **YouTube** (unlimited) | **Vimeo** (unlimited) | **DRM / VdoCipher** (+YouTube/Vimeo) |
| Videos | 4 | unlimited (YouTube) | unlimited (Vimeo) | unlimited (DRM) |
| Quizzes | 2 | unlimited | unlimited | unlimited |
| PDF files | 1 (each activity = 1) | — | 10 | unlimited |
| Any other activity type | 1 each | standard | standard | standard |
| Offers / Coupons | ❌ | ❌ | ❌ | ✅ |
| Subscriptions / Packages | ❌ | ❌ | ❌ | ✅ |
| DRM (VdoCipher) | ❌ | ❌ | ❌ | ✅ |
| Live (Jitsi) | ❌ | ❌ | ❌ | ✅ |
| Duration | **2 weeks** | 1 year | 1 year | 1 year |

*(Blanks/《—》 = to confirm; the brief is explicit on the bold items.)*

Two independent axes fall out of this:
- **Quantity limits** — counts of courses, teachers, activities per type.
- **Feature flags** — DRM, coupons, offers, subscriptions, packages, Jitsi, and the **video‑hosting source** (YouTube → Vimeo → VdoCipher escalating by tier).

### 5.2 `local_license` — the enforcement plugin (new)

A single Moodle plugin that every academy runs. It holds the academy’s **tier, limits, feature flags, and expiry**, and enforces them from inside Moodle.

**Data:** one signed license record per site (tier, JSON of limits+flags, `validuntil`), fetched/renewed from the control plane (so upgrades/renewals propagate without redeploying).

**Enforcement points:**
- **Quantity limits** — a hook on activity/course creation (`mod add`, `core_course` course‑created) blocks creation past the tier’s count and shows an “Upgrade to add more” message.
- **Feature flags** — the existing plugins ask `local_license::has('drm'|'coupons'|'subscriptions'|'packages'|'jitsi')`; `mod_vdocipher`, `local_payments`, `nit_subscriptions` hide/deny when off.
- **Video source** — which video activity is offered depends on tier: Basic → YouTube embed, Standard → Vimeo, Professional → `mod_vdocipher` (DRM). Lower tiers hide the higher‑cost modules.
- **Expiry** — a daily scheduled task marks the site **locked** at `validuntil`; a global hook then redirects everyone (except upgrade/login) to an **“Academy expired — upgrade”** page. Demo → 14 days; paid → 1 year.
- **Grace/read‑only** — on expiry, optionally read‑only for a grace window before hard‑lock (kinder UX; keeps content for when they pay).

> **Design principle:** limits live in **one plugin, driven by data from the control plane** — never hard‑coded per academy. Upgrading a customer = the control plane updates their license; the plugin lifts the limits on next check. No redeploy.

---

## 6. Branding pipeline (form → the academy’s look)

The form fields map directly onto assets we already have in `theme_nit` (we saw `home_hero_block.html`, `home_about_block.html`, brand‑color variables, logo settings):

| Form field | Applied to |
|---|---|
| Academy name (AR / EN) | site `fullname` / `shortname` (multilang), `<title>` |
| Logo | `theme_nit` logo setting |
| Hero image 1 / 2 | `home_hero_block` images |
| Brand colors | `theme_nit` `--nit-brand-*` variables |
| … (categories, about, contact) | corresponding home blocks |

A **seeder script** (Moodle CLI) writes these via `admin/cli/cfg.php` / theme settings + drops uploaded images into the right file areas, then `purge_caches`. Idempotent, re‑runnable when the owner edits branding later.

---

## 7. Lifecycle

- **Create** → Demo, 14 days.
- **Upgrade** (Demo→Basic→Standard→Professional) → payment → control plane bumps tier + `validuntil = now+1yr` → license refresh lifts limits/flags. *(No reprovision; same instance.)*
- **Renew** → extend `validuntil`.
- **Expire** → scheduled task locks; upgrade page shown; data retained through a grace window.
- **Suspend / delete** (non‑payment, abuse, or customer request) → stop container; archive DB + moodledata; hard‑delete after retention period.
- **“Step 02” (post‑provision config)** — once on Professional, the owner configures **payment, offers, subscriptions, …** in their own admin (these are the flags `local_license` unlocked).

---

## 8. Tech decisions to lock (open questions)

| ID | Decision | Recommendation |
|----|----------|----------------|
| **D1** | Repo‑per‑academy vs shared image + config | **Shared image + config** (fork code only for Professional custom builds) |
| **D2** | Control‑plane store | A **relational DB (Postgres)** for licensing/billing; keep Mongo for the marketing site. (Or reuse Mongo to start.) |
| **D3** | Container orchestration | Start with **Docker Compose per tenant on one server** (matches today); grow to a scheduler (Swarm/K8s/Nomad) when tenant count warrants. |
| **D4** | Reverse proxy | **Traefik** (auto‑TLS + dynamic routing by label) vs nginx + certbot. |
| **D5** | DB isolation | **One MariaDB server, DB‑per‑tenant** first; DB‑container‑per‑tenant if isolation/compliance demands. |
| **D6** | Domains | Subdomains `*.nit-eg.com` for all; custom domains as a Professional add‑on. |
| **D7** | Demo limits exactness | Confirm the blanks in the tier matrix (esp. Demo video source, Basic PDFs, Standard/Pro “…”). |

---

## 9. Phased roadmap

**Phase 0 — Foundations (this doc + decisions).** Lock D1–D7.

**Phase 1 — `local_license` (enforcement engine).** The tier/limit/expiry plugin + admin‑settable license, tested by hand on the existing EAAC. *This is the highest‑value, lowest‑risk first build — it’s useful even before automation.*

**Phase 2 — Template + one‑command provisioner (CLI).** Template DB dump + a script that, given `slug + tier + branding`, produces a live branded instance on the server. Operate it manually first.

**Phase 3 — Control plane + Create‑academy form.** The Next.js form + control‑plane records + job queue calling the Phase‑2 provisioner. Demo tier end‑to‑end, self‑serve.

**Phase 4 — Reverse proxy + wildcard TLS + subdomains.** Fully hands‑off routing.

**Phase 5 — Upgrade & billing.** Demo→paid via payment; license refresh; renewals; expiry lock page.

**Phase 6 — Ops.** Backups per tenant, monitoring, suspend/delete, dashboard polish.

> **Suggested first sprint:** Phase 1 (`local_license`) — it turns the *existing* EAAC into a tier‑aware product and is required by every later phase.

---

## 10. Risks & notes

- **Automation fragility** — mitigated by the **template‑dump** install and idempotent, retryable jobs.
- **Resource cost** — instance‑per‑tenant uses more RAM/CPU; fine at low tenant counts, plan a scheduler before scaling.
- **Security/isolation** — per‑tenant DB creds, no shared secrets, proxy‑level rate limits; never expose DB ports.
- **Moodle upgrades across many tenants** — one image + `upgrade.php` per tenant on release; the template keeps new tenants current.
- **Core‑untouched rule still holds** — `local_license` and the branding seeder are plugins/scripts; **no Moodle core edits**, so tenants stay upgradeable.

---

*Prepared as the first‑pass analysis. Next step: confirm decisions D1–D7, then build Phase 1 (`local_license`).*
