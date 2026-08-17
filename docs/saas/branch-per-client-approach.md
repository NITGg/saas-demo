# Branch-per-Client Approach

> **Companion document** to [`academy-saas-analysis.md`](academy-saas-analysis.md).
> This file documents the approach agreed on during our discussion: **each client
> gets their own branch** of the EAAC codebase — not just shared config.

---

## Why this approach (instead of the one in the analysis doc)?

The original analysis (decision **D1**) recommends a **single shared code image**
for all clients, where only config and data differ — and advises **against** a
branch per client.

**But our situation is different:** clients may request **different features or
pages from one another** (not just a different look). This is exactly the
exception the analysis doc itself acknowledges:

> *"Keep the GitHub-per-tenant option only for Professional customers who buy custom
> code — then they get a branch off the base."*

So the branch-per-client approach **fits our case**, because each client may
genuinely have different code.

---

## The idea in one line

The client clicks a button on the NIT website → the system takes a **new copy
(branch)** of the EAAC codebase, applies the client's data and features → deploys
it on the server under a dedicated URL.

```
[NIT website] client clicks the button
     │
     ▼
[API] receives the request
     │
     ├─► calls GitHub  →  "create a new branch from master"   (copy created)
     │
     ├─► writes the client's data + features into the branch   (name/logo/colors/custom plugin)
     │
     ├─► tells the server  →  "run this copy"                  (database + URL + TLS)
     │
     └─► emails the client                                      (ready to use)
```

---

## The golden rule (the most important part of the whole approach)

**Every client-specific change must be isolated in its own "box" — never touch
the shared code.**

In Moodle this is easy, because Moodle is built on **plugins** (each plugin = a
separate box). So any client-specific feature is built as a **dedicated plugin**,
not in core or in the shared code.

Suggested layout for a client branch — it only adds files in isolated places:

```
clients/<slug>/
   config.php          ← wwwroot, dbname, dataroot
   branding.json       ← name, colors, logo, hero images
   license.json        ← plan, limits, expiry date
   assets/             ← uploaded logo and images

public/local/custom_<slug>/   ← (optional) plugin with this client's custom features
```

As long as the client branch only adds things inside these boxes — and never
edits shared files — `git merge master` will **always apply cleanly (no
conflicts)**.

> ⚠️ This respects the **golden rule in `CLAUDE.md`**: never modify Moodle core.
> All customization lives in a client-specific plugin/folder.

---

## The button flow, step by step

| # | Step | How |
|---|------|-----|
| 1 | Client fills the form on the NIT site and clicks "Create" | The form sends the data to an API |
| 2 | The API generates a unique `slug` for the client | e.g. `ahmed-academy` |
| 3 | It calls the **GitHub API**: create a new branch from `master` | named `client/<slug>` — needs a **token** set up once |
| 4 | It writes the `clients/<slug>/` files into the branch (branding + license + config) | commit + push |
| 5 | (If there are custom features) it adds a `local/custom_<slug>/` plugin | in the same branch |
| 6 | It tells the server (CI/CD) to run the copy | checkout the branch + new database + moodledata + subdomain |
| 7 | It applies the branding + license | from `branding.json` and `license.json` |
| 8 | It emails the client the URL and login credentials | `<slug>.nit-eg.com` |

All of this is automatic, within minutes.

---

## The only problem: updates (fan-out) — and its fix

When you push an update (security fix / Moodle upgrade) to `master`, it must reach
every client branch.

The fix: a **CI job** that fans out automatically on every release:

```bash
for b in $(git branch -r | grep 'origin/client/'); do
  git checkout "${b#origin/}"
  git merge --no-edit master || echo "CONFLICT in $b" >> conflicts.txt
  git push
done
```

As long as the branches respect the **golden rule** (isolated changes only),
`conflicts.txt` stays empty and the update propagates to everyone with no manual
intervention.

---

## When this approach fits — and when it doesn't

| Situation | Branch per client? |
|-----------|--------------------|
| Clients want **different features/code** from one another | ✅ Right choice (our case) |
| Only **branding/config/data** differs | ⚠️ Shared code + config is lighter (see D1 in the analysis doc) |
| **200+ clients** on the exact same code | ⚠️ Branches become needless overhead |

---

## Components to build

1. **The form + button** on the NIT website (Next.js).
2. **An API** that receives the request and runs the steps (talks to GitHub + the server).
3. **A GitHub token** with permission to create branches (set up once).
4. **CI/CD** that runs the branch on the server (database + subdomain + TLS).
5. **A fan-out CI job** that propagates updates to all branches.
6. **A branding seeder** that applies `branding.json` to `theme_nit`.
7. **A licensing plugin** (`local_license`) that enforces the plan and limits — as in the analysis doc.

---

## Next step

- Define the form shape and the data required on the NIT website.
- Write the first version of the API that talks to GitHub and creates the branch (simplified real code).

*Prepared as a summary of the session discussion on the "branch per client" approach.*
