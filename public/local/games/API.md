# Games Corner — Mobile API

Everything `/local/games/index.php` draws on the web, available to a native
client. The hub screen is one call; a game screen is two.

- **Site:** `http://localhost:8081` (dev). Production wwwroot replaces it.
- **Transport:** Moodle REST web services. `POST` only, `application/x-www-form-urlencoded`.
- **Service shortname:** `local_games`

---

## 1. What the service exposes

| Function | Type | Purpose |
|---|---|---|
| `core_webservice_get_site_info` | read | Who am I, site name, language. Also: is my token still valid. |
| `local_games_get_games` | read | The whole hub screen: sections, cards, totals, badge shelf. |
| `local_games_get_game` | read | One game's start card and this user's standing in it. |
| `local_games_get_content` | read | One game's own content, plus every in-game string. |
| `local_games_submit_result` | write | Record one finished round, get the new totals and any badge won. |

Nothing else. A token minted for `local_games` cannot read courses, users or
files — it can do the corner and stop there.

---

## 2. Getting a token

```bash
curl -s "http://localhost:8081/login/token.php" \
  -d "username=USERNAME" \
  -d "password=PASSWORD" \
  -d "service=local_games"
```

```json
{"token":"d9bc6d77450813890bd73f516aae99d9","privatetoken":null}
```

Store the token; it does not expire. On failure the same endpoint answers with
`{"error":"...","errorcode":"..."}` — treat `invalidlogin` as wrong
credentials and anything else as "show the message, don't retry".

To check a stored token on app start, call `core_webservice_get_site_info`. An
`invalidtoken` exception means send the user back to the login screen.

> The user must be able to play: the capability `local/games:play` is granted to
> every authenticated user by default, so any normal account works.

---

## 3. Calling convention

Every call is a POST to the one endpoint:

```
POST http://localhost:8081/webservice/rest/server.php
```

with these fields on every request:

| Field | Value |
|---|---|
| `wstoken` | the token from §2 |
| `wsfunction` | the function name |
| `moodlewsrestformat` | `json` — omit it and you get XML |

Arrays use PHP bracket syntax: `banks[0]=quiz&banks[1]=colours`.

**Language.** Responses come back in the user's Moodle language. To override
per call, add `moodlewssettinglang=ar` — every name, description and string in
the response follows it. (The field is `moodlewssettinglang`, not `lang`;
`lang` is rejected as `invalidparameter`.) The corner ships `en` and `ar`.

**Errors.** HTTP is always `200`. An error is a JSON body with an `exception`
key, so check for that before reading the payload:

```json
{"exception":"core\\exception\\moodle_exception","errorcode":"errorunknowngame",
 "message":"That game does not exist, or is not ready yet."}
```

Match on `errorcode`, never on `exception` or `message`: the class name is
namespaced and the message is translated.

| `errorcode` | Meaning |
|---|---|
| `invalidtoken` | Token is wrong or was reset — re-authenticate. |
| `accessexception` | Token is valid but the account may not use the service. |
| `errorunknowngame` | The `gameid` is not a real, playable game. |
| `invalidparameter` | A parameter failed validation — a client bug. |

---

## 4. `local_games_get_games` — the hub screen

No parameters. This is the mobile equivalent of `index.php`: the labels, the
counters, the sections and the badge shelf, all in the caller's language.

```bash
curl -s "http://localhost:8081/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=local_games_get_games"
```

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

Notes for the UI:

- `level` is 1–3. The web hub draws it as that many ⭐; an app may draw it any
  way it likes, which is why the number travels rather than the stars.
- `live: false` is a game on the plan but not built. Show the card, disable it,
  label it with `soonlabel`.
- `note` is an optional line under a section heading. Empty string means there
  is none — render nothing, not an empty row.
- `bestscorelabel` carries a literal `{score}` placeholder. Substitute the
  card's `bestscore` into it rather than composing the sentence yourself; the
  word order differs between languages.
- Sections arrive in display order and so do the cards inside them. Do not
  re-sort.
- A card with `plays: 0` has never been played — hide the best-score line.

Section keys are stable: `numbers`, `letters`, `quiz`, `memory`, `motion`.

---

## 5. `local_games_get_game` — one game's start card

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `gameid` | string | yes | Slug, e.g. `math-race` |

```bash
curl -s "http://localhost:8081/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=local_games_get_game" -d "gameid=math-race"
```

```json
{
  "id": "math-race",
  "emoji": "🔢",
  "category": "numbers",
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

`readytitle` and `howto` are the start screen. The four numeric fields on a
badge are its rule, ANDed together, so a client can draw progress towards it
rather than only whether it was reached:

| Field | Meaning when ≥ 0 |
|---|---|
| `streak` | longest run of correct answers in one round must reach this |
| `correct` | correct answers in one round must reach this |
| `maxwrong` | wrong answers in the round must stay at or below this |
| `goal` | the game's own goal must be met this many times |

**`-1` means the rule does not use that field** — a zero could not say this,
since `maxwrong: 0` is a real and common rule ("no mistakes").

Calling this with an unbuilt or unknown slug raises `errorunknowngame`.

---

## 6. `local_games_get_content` — one game's content

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `gameid` | string | yes | Slug, e.g. `math-race` |

> **Changed.** This function used to take a `banks[]` list and return material
> shared between games. Content now belongs to one game — two games built the
> same way hold separate rows, so that editing one cannot change the other — and
> the call is made per game rather than once for the whole app.

```bash
curl -s "http://localhost:8081/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=local_games_get_content" -d "gameid=quiz"
```

```json
{
  "lang": "en",
  "arabicdigits": false,
  "revision": 1787986669,
  "shape": "questions",
  "strings": [
    {"key": "start",   "value": "Start"},
    {"key": "correct", "value": "Well done!"}
  ],
  "quiz": [
    {"topic": "", "question": "...", "answer": "...", "wrong": ["...", "..."]}
  ],
  "words": [], "shopitems": [], "wordlist": [], "truefalse": [],
  "whoami": [], "colours": [], "sumrules": [], "numberrules": []
}
```

- **`shape` says which slot is filled.** Every slot always travels, and the ones
  this game does not use come back as `[]` — so a client can read any field
  without first checking what it asked for, exactly as before.
- `strings` always travels: it is what the game screens are written in, and no
  round can be drawn without it. Keys arrive without the `js_` prefix.
- **Cache `strings` against `revision`; do not cache the content.** The revision
  moves when the language pack may have. The content is edited in Game control
  at any time, so it is fetched with the game.
- `arabicdigits` is presentation only: show numerals as Arabic-Indic (٠١٢٣). The
  arithmetic itself always runs on real numbers.
- A row written in one language only does not appear when another is requested.
  The word lists are not translations of each other, so this is deliberate.

Shapes, and the slot each fills:

| `shape` | Slot | Row |
|---|---|---|
| `questions` | `quiz` | `{topic, question, answer, wrong[]}` — `topic` is empty |
| `topicquestions` | `quiz` | the same, with `topic` set (the Question Wheel) |
| `words` | `words` | `{word, emoji, clue}` |
| `vocabulary` | `wordlist` | plain strings — what Word Builder validates against |
| `statements` | `truefalse` | `{text, true, why}` |
| `clues` | `whoami` | `{answer, emoji, clues[]}` — in reveal order |
| `colours` | `colours` | `{name, hex}` |
| `shopitems` | `shopitems` | `{emoji, name}` |
| `sumrules` | `sumrules` | `{op, mina, maxa, minb, maxb}` |
| `numberrules` | `numberrules` | `{kind, minn, maxn}` |

The last two are recipes rather than material. Math Race, Number Catcher and
Balloon Pop invent every question as they go, so what an administrator edits — and
what a client receives — is what the game is allowed to invent:

- `op` is `plus`, `minus` or `times`; the two operands are drawn from
  `mina..maxa` and `minb..maxb`. Never present a negative answer: swap the
  operands when a subtraction would go below zero, as the web shell does.
- `kind` is `even`, `odd`, `greater`, `less`, `divisible` or `equals`. The two
  that carry no number ignore `minn`/`maxn`; the rest draw their number from that
  range each time the rule is set.

A client that receives an empty slot has a game with no content. Do not present
an empty round: say so, and let the learner choose another game.

## 7. `local_games_submit_result` — end of a round

| Parameter | Type | Required | Notes |
|---|---|---|---|
| `gameid` | string | yes | Slug |
| `correct` | int | yes | Correct answers this round |
| `wrong` | int | yes | Wrong answers this round |
| `streak` | int | yes | Longest run of correct answers this round |
| `score` | int | yes | Round score as the game counts it |
| `goal` | int | no | Times the game met its own goal. Defaults to `0`. |

```bash
curl -s "http://localhost:8081/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "wsfunction=local_games_submit_result" \
  -d "gameid=math-race" -d "correct=12" -d "wrong=1" \
  -d "streak=10" -d "score=120" -d "goal=0"
```

```json
{
  "points": 129,
  "badges": 7,
  "gamepoints": 24,
  "bestscore": 120,
  "newbadges": [
    {"key": "fast-calculator", "name": "Sharp Calculator"}
  ]
}
```

- Call it **once per finished round**, not per answer.
- `newbadges` is what to celebrate with — it lists only badges won by *this*
  round, and is `[]` the rest of the time.
- The returned `points` and `badges` are the new site-wide totals: update the
  header counters from them instead of re-calling `get_games`.
- **Badges are decided on the server** from the numbers you send. The client
  does not award them and cannot claim one.
- `bestscore` is the game's best after this round, so a client can tell "new
  personal best" by comparing with the value it held before.

---

## 8. A typical app session

1. `core_webservice_get_site_info` — token still good? who is this?
2. `local_games_get_games` — draw the hub.
3. User taps a card → `local_games_get_game` → draw the start card.
4. `local_games_get_content` with that slug → the material for that one game.
5. Play the round entirely on the device. **No network traffic during a round.**
6. Round ends → `local_games_submit_result` → celebrate `newbadges`, update
   the counters from the response.

Steps 3 and 4 can go out in parallel: neither needs the other's answer.

Content is fetched per game rather than once for the whole app, because a game's
rows are its own and an administrator may change them at any time. It is also
smaller — one game's material rather than every bank in the corner — so this
costs less than the single 35 KB fetch it replaces.

---

## 9. Game slugs

`math-race`, `math-catcher`, `math-shop`, `letter-order`, `word-builder`,
`match-connect`, `crossword`, `word-search`, `speak-words`, `quiz`,
`true-false`, `xo-quiz`, `target-answer`, `balloon-pop`, `wheel`, `space-quiz`,
`who-am-i`, `memory-cards`, `puzzle`, `find-difference`, `color-challenge`,
`runner`.

Treat this list as informative, not as a contract: the catalogue is whatever
`local_games_get_games` returns, and games get added. Never hard-code it.

---

## 10. Server-side setup

Done in this environment; the same four steps are what a new site needs.

1. The `local_games` service, defined in [`db/services.php`](db/services.php)
   and created on install — enabled, unrestricted, no file transfer. It comes
   with the plugin, so a `version.php` bump plus
   `admin/cli/upgrade.php` is all it takes.
2. `enablewebservices = 1`
3. `webserviceprotocols = rest`
4. Two capabilities on the **Authenticated user** role, at system context —
   *Site administration → Users → Permissions → Define roles*:
   - `moodle/webservice:createtoken` — so a client may ask `/login/token.php` for a token
   - `webservice/rest:use` — so it may call the REST server with it

Steps 2–4 are site config, not plugin code, so they do not travel with a
`git pull`. Without step 4 in particular, every call answers `accessexception`
even though the token is valid.

Note that the corner's functions are also registered against
`MOODLE_OFFICIAL_MOBILE_SERVICE`, so they work inside the official Moodle App
as well, with no further setup beyond enabling that service.

Tokens can also be issued by hand at *Site administration → Server → Web
services → Manage tokens*, which is the quickest way to hand a developer a
working token without giving out an account password.

When a token answers `accessexception` and it is not obvious why, the repo has
a diagnostic that says which of the above is missing:

```bash
docker compose exec moodle php public/local/payments/cli/ws_diagnose.php --token=TOKEN --function=local_games_get_games
```
