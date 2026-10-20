# Games Corner — Mobile API

Everything the app needs to show the **Games Corner** (the hub at `/local/games/index.php`), play its
games natively, save points and badges, and play a **Game activity** placed inside a course.
Last updated **2026-10-20**. How each game plays (rules, scoring, settings): [`games-rules-spec.md`](games-rules-spec.md).

> Server side: plugins `local_games` (the corner) and `mod_games` (the course activity). The full
> platform reference is [`../MOBILE_API.md`](../MOBILE_API.md); this file only adds the games.

## Contents

1. [Basics](#1-basics) — URL, token, calling convention, language, errors
2. [Typical flows](#2-typical-flows) — hub, one round, a game inside a course
3. [`local_games_get_games`](#3-local_games_get_games--the-hub-screen) — the hub screen
4. [`local_games_get_game`](#4-local_games_get_game--one-games-start-card) — one game's start card
5. [`local_games_get_content`](#5-local_games_get_content--one-games-content) — what a game is made of
6. [`local_games_submit_result`](#6-local_games_submit_result--end-of-a-round) — save a round
7. [Game activity inside a course](#7-game-activity-inside-a-course) — `gameid` + `mod_games_record_play`
8. [Reference](#8-reference) — game slugs, error codes, checklist

---

## 1. Basics

### 1.1 Base URL and token

- Base URL: the academy's site, e.g. `https://<slug>.academy2026.nitg-eg.com`
  (local development: `http://localhost:8083`).
- **Token: the normal user token the app already has** (`/login/token.php` with
  `service=moodle_mobile_app`, Google sign-in, or `register_student` — see `MOBILE_API.md` §1.2).
  All games functions are in the `moodle_mobile_app` service; no extra login.
- The shared pre-login token does **not** work here — games are per user (points, badges).
  A guest / not-logged-in user cannot play.

### 1.2 Calling convention (style B — Moodle REST)

Every call is a `POST` (`application/x-www-form-urlencoded`) to:

```
POST /webservice/rest/server.php
```

| Field | Value |
|---|---|
| `wstoken` | the user token |
| `wsfunction` | function name, e.g. `local_games_get_games` |
| `moodlewsrestformat` | `json` (omit it and you get XML) |
| `moodlewssettinglang` | `ar` or `en` — language of every name, label and in-game string |
| `moodlewssettingfilter` | `true` — needed so names an admin typed in both languages come back in one language |

Send the last two on **every** call, like the rest of the app does.

```bash
curl -s "$BASE/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "moodlewssettinglang=ar" -d "moodlewssettingfilter=true" \
  -d "wsfunction=local_games_get_games"
```

### 1.3 Errors

HTTP is always `200`. An error is a JSON object with an `exception` key — check for it before reading
the payload. **Match on `errorcode`**, not on `message` (translated) or `exception` (class name).

```json
{"exception":"core\\exception\\moodle_exception","errorcode":"errorunknowngame",
 "message":"That game does not exist, or is not ready yet."}
```

| `errorcode` | Meaning | App action |
|---|---|---|
| `invalidtoken` | Token wrong / deleted (e.g. after a password change) | Log the user out |
| `accessexception` | Token valid but the function is not allowed for this user/service | Show the message; report it (server setup) |
| `errorunknowngame` | `gameid` is not a real, live game (unknown, or switched off by the admin) | Go back to the hub and refresh it |
| `invalidparameter` | A parameter is missing or the wrong type | Client bug |
| `requireloginerror` | (`mod_games_record_play`) user is not enrolled in the course / cannot see the activity | Ignore for the round; refresh the course |

---

## 2. Typical flows

### 2.1 Games Corner (hub)

1. `local_games_get_games` → draw the hub: header counters, sections, cards, badge shelf.
2. User taps a card → **in parallel**:
   - `local_games_get_game` → start screen (title, how to play, badges for this game);
   - `local_games_get_content` → the material for that game (questions, words, rules…).
3. Play the round **entirely on the device**. No network traffic during a round.
4. Round ends → `local_games_submit_result` once → celebrate `newbadges`, update the header counters
   from the response (no need to call `get_games` again).

### 2.2 A Game activity inside a course

1. The course page (`getalltopics.php`) returns the activity with `modname: "games"` and a `gameid`.
2. Load it like §2.1 step 2, but take the content from **`mod_games_get_content(cmid)`** (§7.2)
   instead of `local_games_get_content`. A teacher can give each activity its own questions/words;
   `local_games_get_game(gameid)` is still used for the start card and badges.
3. Round ends → send **both**:
   - `local_games_submit_result` (points and badges of the corner — kept whether played from the hub or
     a course), and
   - `mod_games_record_play` with the activity's `cmid` (the course's tally + activity completion).

Details in §7.

---

## 3. `local_games_get_games` — the hub screen

No parameters. Everything the web hub shows, in the requested language.

```json
{
  "title": "Games Corner",
  "intro": "Short games that teach something. Play, collect points, earn badges.",
  "pointslabel": "Your points",
  "badgeslabel": "Your badges",
  "playlabel": "Play",
  "soonlabel": "Coming soon",
  "bestscorelabel": "Best: {score}",
  "points": 117,
  "badges": 6,
  "plays": 18,
  "categories": [
    {
      "key": "numbers",
      "emoji": "🔢",
      "name": "Numbers",
      "note": "",
      "games": [
        {
          "id": "math-race",
          "emoji": "🔢",
          "logourl": "",
          "shape": "sumrules",
          "name": "Math Race",
          "description": "Add, subtract and multiply - pick the right answer and keep the race going.",
          "level": 1,
          "live": true,
          "plays": 2,
          "points": 12,
          "bestscore": 12,
          "beststreak": 12
        }
      ]
    }
  ],
  "badgeshelf": [
    {
      "key": "fast-calculator",
      "gameid": "math-race",
      "name": "Sharp Calculator",
      "hint": "10 correct answers in a row",
      "earned": true
    }
  ]
}
```

| Field | Notes |
|---|---|
| `points`, `badges`, `plays` | User's lifetime totals for the whole corner (the two header counters). |
| `categories[]` | Sections in **display order**; games inside are in display order too. Do not re-sort. Section keys: `numbers`, `letters`, `quiz`, `memory`, `motion`. |
| `note` | Optional line under a section heading. `""` = render nothing. |
| `games[].logourl` | Image uploaded by the admin. If not empty, show it **instead of** `emoji`. Optional field — treat missing as `""`. |
| `games[].shape` | Which `get_content` list the game reads (see §5). Use it to pick the native player. |
| `games[].level` | Difficulty 1–3 (web shows that many ⭐). |
| `games[].live` | `false` = planned, not playable: show the card disabled with `soonlabel`. |
| `games[].plays` | `0` = never played → hide the best-score line. |
| `bestscorelabel` | Contains a literal `{score}` — replace it with the card's `bestscore` (word order differs between languages). |
| `badgeshelf[]` | Every badge in the corner and whether the user has it. |

Games switched **off** by the admin in Game control are simply not in the list.

---

## 4. `local_games_get_game` — one game's start card

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `gameid` | string | yes | Slug, e.g. `math-race` |

```json
{
  "id": "math-race",
  "emoji": "🔢",
  "logourl": "",
  "category": "numbers",
  "shape": "sumrules",
  "level": 1,
  "name": "Math Race",
  "description": "Add, subtract and multiply - pick the right answer and keep the race going.",
  "readytitle": "Ready for the race?",
  "howto": "A sum appears, three answers under it. Tap the right one and the runner moves forward.",
  "plays": 2,
  "points": 12,
  "bestscore": 12,
  "beststreak": 12,
  "badges": [
    {
      "key": "fast-calculator",
      "name": "Sharp Calculator",
      "hint": "10 correct answers in a row",
      "earned": true,
      "streak": 10,
      "correct": -1,
      "maxwrong": -1,
      "goal": -1
    }
  ]
}
```

- `readytitle` + `howto` = the start screen.
- A badge's four numbers are its **rule** (all must hold, in one round). **`-1` = not used.**
  (`maxwrong: 0` is a real rule: "no mistakes".) Use them to show progress towards a badge.

| Field | Rule when ≥ 0 |
|---|---|
| `streak` | longest run of correct answers in the round ≥ this |
| `correct` | correct answers in the round ≥ this |
| `maxwrong` | wrong answers in the round ≤ this |
| `goal` | the game's own goal met ≥ this many times (matches won, planets reached…) |

Unknown or switched-off slug → `errorunknowngame`.

---

## 5. `local_games_get_content` — one game's content

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `gameid` | string | yes | Slug |

```json
{
  "lang": "en",
  "arabicdigits": false,
  "speechlang": "en",
  "rulesversion": 1,
  "settings": [{"key": "questions", "value": 20}],
  "revision": 1792394479,
  "shape": "questions",
  "strings": [
    {"key": "start",   "value": "Start"},
    {"key": "correct", "value": "Well done!"},
    {"key": "yougot",  "value": "You collected {$a} points"}
  ],
  "quiz": [
    {"topic": "", "question": "...", "answer": "...", "wrong": ["...", "..."]}
  ],
  "words": [], "shopitems": [], "wordlist": [], "truefalse": [],
  "whoami": [], "colours": [], "sumrules": [], "numberrules": []
}
```

- **`settings`** = the numbers the round is played with (items per round, seconds, lives, speeds…). The web game uses the same values. Keys per game: [`games-rules-spec.md`](games-rules-spec.md). Values are numbers (some decimals).
- **`rulesversion`** changes when any game rule or setting changes. If it is higher than what your build implements, fall back to the web view for that game.
- **`speechlang`** = locale for TTS and speech recognition (`ar-EG` in Arabic, else the UI language).
- **`shape` tells you which list is filled.** All lists are always present; the unused ones are `[]`.
- `strings` = every text the game screens use (buttons, feedback, end card). Placeholders: `{$a}`, `{$b}`.
  **Cache `strings` by `revision` + language. Do not cache the content lists** — the admin can edit
  a game's content at any time, so fetch it each time the game is opened.
- `arabicdigits: true` → display numbers as Arabic-Indic digits (٠١٢٣). Calculations still use normal
  numbers.
- An admin row written in one language only is not returned for the other language (on purpose).
- **Empty list for the game's shape = the game has no content** — don't start an empty round; tell the
  user and let them pick another game.

| `shape` | List | Row |
|---|---|---|
| `questions` | `quiz` | `{topic, question, answer, wrong[]}` — `topic` is `""` |
| `topicquestions` | `quiz` | same, with `topic` set (Question Wheel: one wheel segment per topic) |
| `words` | `words` | `{word, emoji, clue}` |
| `vocabulary` | `wordlist` | plain strings — words Word Builder accepts |
| `statements` | `truefalse` | `{text, true, why}` — show `why` after the answer |
| `clues` | `whoami` | `{answer, emoji, clues[]}` — give clues in order |
| `colours` | `colours` | `{name, hex}` |
| `shopitems` | `shopitems` | `{emoji, name}` — the app invents prices |
| `sumrules` | `sumrules` | `{op, mina, maxa, minb, maxb}` |
| `numberrules` | `numberrules` | `{kind, minn, maxn}` |

`sumrules` and `numberrules` are **recipes**, not questions — the game generates questions from them:

- `sumrules`: `op` = `plus` | `minus` | `times`; first number from `mina..maxa`, second from
  `minb..maxb`. Never show a negative answer: swap the operands when a subtraction would go below 0.
- `numberrules`: `kind` = `even` | `odd` | `greater` | `less` | `divisible` | `equals`. Pick a rule,
  draw its number from `minn..maxn` (`even`/`odd` ignore the range).

The web version of each game (`/local/games/js/<slug>.js`) is the reference implementation for the
gameplay itself.

---

## 6. `local_games_submit_result` — end of a round

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `gameid` | string | yes | Slug |
| `correct` | int | yes | Correct answers this round |
| `wrong` | int | yes | Wrong answers this round |
| `streak` | int | yes | Longest run of correct answers this round |
| `score` | int | yes | Round score as the game counts it |
| `goal` | int | no | Times the game's own goal was met (default `0`) |

```bash
curl -s "$BASE/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" -d "moodlewssettinglang=en" \
  -d "wsfunction=local_games_submit_result" \
  -d "gameid=math-race" -d "correct=12" -d "wrong=0" -d "streak=10" -d "score=120"
```

```json
{
  "points": 12,
  "badges": 1,
  "gamepoints": 12,
  "bestscore": 120,
  "newbadges": [
    {"key": "fast-calculator", "name": "Sharp Calculator"}
  ]
}
```

- Call it **once per finished round**, never per answer.
- Points earned = `correct` (each correct answer is 1 point).
- `points` / `badges` = new totals for the header; `gamepoints` = lifetime points in this game.
- `newbadges` = badges won **by this round** only (`[]` otherwise) → show the celebration.
- `bestscore` = best after this round; compare with the old value to show "new personal best".
- **Badges are decided by the server** from the numbers you send. A badge is given once, for life.
- Values are clamped to `0..500`; `streak` cannot exceed `correct`.
- If the call fails (no network), don't block the child's end screen. You may retry later.

---

## 7. Game activity inside a course

A teacher can add a **Game** activity to a course (one game, optional completion rules: "play N rounds"
and/or "reach score N"). The app plays it natively.

### 7.1 Finding the game of an activity

`/local/multitopics/getalltopics.php` (the course page the app already uses) returns, for each Game
activity:

```json
{
  "id": "140",
  "modname": "games",
  "name": "Math Race test",
  "url": "https://.../mod/games/view.php?id=140",
  "gameid": "math-race",
  "...": "the usual fields (restricted, completion, …)"
}
```

- `id` is the **cmid**. `gameid` is the slug to use with §4–§6.
- Fallback with plain Moodle: `core_course_get_contents` → module with `modname: "games"` →
  `customdata` is a JSON string containing `{"gameid":"math-race", …}`.
- `restricted: true` → show it greyed out and don't open it, same as other activities.

### 7.2 `mod_games_get_content` — the activity's content

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `cmid` | int | yes | The activity's `id` from 7.1 |

Returns **exactly the same structure as `local_games_get_content`** (§5): `shape`, `settings`,
`rulesversion`, `speechlang`, `strings`, and the content lists. Use the same native player for both.

The content lists hold **the teacher's own rows for this activity**. If the teacher wrote none (or none
in the requested language), they hold the corner's default content instead, the same as
`local_games_get_content`. The server decides this; the app just plays what it gets.

```bash
curl -s "$BASE/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" -d "moodlewssettinglang=ar" \
  -d "wsfunction=mod_games_get_content" -d "cmid=140"
```

- Needs `mod/games:view` on the activity (every enrolled student has it). Not enrolled →
  `requireloginerror`; game switched off by the admin → `errorunknowngame`.
- Fetch it each time the activity is opened (the teacher can edit the content at any time).

### 7.3 `mod_games_record_play` — record a round in the course

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `cmid` | int | yes | The activity's `id` from 7.1 |
| `correct` | int | yes | Correct answers this round |
| `streak` | int | yes | Longest run of correct answers |
| `score` | int | yes | Round score |

```bash
curl -s "$BASE/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=mod_games_record_play" \
  -d "cmid=140" -d "correct=8" -d "streak=5" -d "score=80"
```

```json
{"plays": 1, "points": 8, "bestscore": 80, "beststreak": 5}
```

- Values are this user's totals **in this activity** (they feed the teacher's report and completion).
- After it, activity completion is re-checked on the server. Refresh the course page
  (`getalltopics.php`) to show the new completion state.
- At the end of an activity round send **both** `local_games_submit_result` (with the `gameid`) and
  `mod_games_record_play` (with the `cmid`). They are independent; send them in parallel.

---

## 8. Reference

### 8.1 Game slugs

`math-race`, `math-catcher`, `math-shop`, `letter-order`, `word-builder`, `match-connect`, `crossword`,
`word-search`, `speak-words`, `quiz`, `true-false`, `xo-quiz`, `target-answer`, `balloon-pop`, `wheel`,
`space-quiz`, `who-am-i`, `memory-cards`, `puzzle`, `find-difference`, `color-challenge`, `runner`.

For information only — **never hard-code the list**. The catalogue is whatever `local_games_get_games`
returns (admins switch games on/off and rename them). If the app meets a slug it has no native
implementation for, hide that card (or open `/local/games/play.php?id=<slug>` in a WebView).

### 8.2 All functions

| Function | Type | Parameters | Purpose |
|---|---|---|---|
| `local_games_get_games` | read | — | Hub screen |
| `local_games_get_game` | read | `gameid` | Start card + badge rules |
| `local_games_get_content` | read | `gameid` | Game material + in-game strings |
| `local_games_submit_result` | write | `gameid, correct, wrong, streak, score, goal?` | Save a round, award badges |
| `mod_games_get_content` | read | `cmid` | Content of a course activity (teacher's or default) |
| `mod_games_record_play` | write | `cmid, correct, streak, score` | Save a round of a course activity |

Required capabilities (given to every logged-in user / enrolled student by default):
`local/games:play`, `mod/games:play`.

### 8.3 Optional: games-only token

The server also has a service with shortname **`local_games`** that holds only the corner functions
+ `core_webservice_get_site_info`. A separate app that should only play games can log in with
`/login/token.php?…&service=local_games`. **Our main app does not need it** — use the normal
`moodle_mobile_app` token.

### 8.4 Server checklist (for backend / ops)

- Plugins installed: `php admin/cli/upgrade.php --non-interactive` then `purge_caches.php`.
- If a games function answers `accessexception` with a valid token, run:
  ```bash
  docker compose exec moodle php public/local/payments/cli/ws_diagnose.php --token=TOKEN --function=local_games_get_games
  ```
  "Function is NOT attached to service 'moodle_mobile_app'" means the upgrade stopped before its last
  step. Re-sync the mobile service's function list:
  ```bash
  docker compose exec moodle php -r 'define("CLI_SCRIPT",1); require "config.php"; require_once($CFG->libdir."/upgradelib.php"); external_update_descriptions("local_games"); external_update_services(); purge_all_caches();'
  ```
