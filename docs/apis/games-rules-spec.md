# Games Corner — Gameplay Rules Spec (for native ports)

How every game plays, so the app can run it natively and give the **same scores, badges and best
scores** as the web. Last updated **2026-10-20**, **`rulesversion` = 1**.

- API reference (calls, fields, errors): [`games-mobile-api.md`](games-mobile-api.md).
- Reference implementation: `public/local/games/js/<slug>.js` + the shared runtime `js/shell.js`
  in this repository. The same files are served by every site at `/local/games/js/<slug>.js`.
- `file:line` references below point at those files.

## Contents

0. [Rules shared by every game](#0-rules-shared-by-every-game)
1. Games, in the order you asked for:
   [xo-quiz](#xo-quiz) · [target-answer](#target-answer) · [wheel](#wheel) · [who-am-i](#who-am-i) ·
   [letter-order](#letter-order) · [word-builder](#word-builder) · [match-connect](#match-connect) ·
   [color-challenge](#color-challenge) · [math-shop](#math-shop) · [memory-cards](#memory-cards) ·
   [crossword](#crossword) · [word-search](#word-search) · [balloon-pop](#balloon-pop) ·
   [space-quiz](#space-quiz) · [math-catcher](#math-catcher) · [runner](#runner) · [puzzle](#puzzle) ·
   [find-difference](#find-difference) · [speak-words](#speak-words)
2. Already native (for checking): [quiz](#quiz) · [true-false](#true-false) · [math-race](#math-race)
3. [Answers to the specific questions](#3-answers-to-the-specific-questions)
4. [Known web bugs — don't copy them](#4-known-web-bugs--dont-copy-them)

---

## 0. Rules shared by every game

### 0.1 What the API now gives you

| Field | Where | Use |
|---|---|---|
| `shape` | `get_games` (each card), `get_game`, `get_content` | Which content list the game reads. No need to map slugs yourself. |
| `settings` | `get_content` | `[{key, value}]` — the numbers the round is played with. The web game reads the **same** values. Always use these, never hard-code. |
| `rulesversion` | `get_content` | Integer. Bumped whenever a rule in this document or a setting changes. If it is higher than the version your build implements, open that game in the web view instead (or warn). |
| `speechlang` | `get_content` | Locale for TTS and speech recognition (`ar-EG` in Arabic, else the UI language, e.g. `en`). |

### 0.2 Round bookkeeping (shell.js) — identical for all games

The round keeps `correct`, `wrong`, `streak` (current run), `best` (longest run), `score`.

- **correct(points = 1):** `correct += 1`, `score += points`, `streak += 1`, `best = max(best, streak)`.
  Plays the "ding" and flashes `strings.correct` (or a game-specific message).
- **wrong():** `wrong += 1`, `streak = 0`. Plays the "buzz" and flashes `strings.wrong`.
- **finish(score?, goal?):** ends the round, plays the fanfare, and submits it with
  `local_games_submit_result(gameid, correct, wrong, streak = best, score = score ?? round.score, goal = goal ?? 0)`.
  The end card shows `strings.roundover` and `strings.yougot` with `{$a}` = score.
  If the submit fails, show `strings.savefailed` and **keep the end card**.
- Lifetime **points** on the server = `correct` (not `score`). **bestscore** = the `score` you send.
- A "correct" call that awards a bonus (e.g. a match won) **also** counts as a correct answer and a
  streak step. This is part of the rules; reproduce it.
- Badges are decided on the server from `correct / wrong / streak / goal`.

### 0.3 Helpers several games use

- **questions(count, topic?)**: shuffle the `quiz` list (filtered by `topic` if given), take `count`.
  Options = `answer` + the first 3 of `wrong` (so 2–4 options), shuffled. An answer is right when the
  option **text equals** `answer`.
- **numberRule(prefix)** (math-catcher `catch`, balloon-pop `balloon`): pick a random `numberrules` row.
  `lo = min(minn, maxn)`, `hi = max(minn, maxn)`, `n = hi > 0 ? random(max(lo, 1)..hi) : 0`.
  Tests: `even` v%2==0 (also any unknown kind), `odd` |v%2|==1, `greater` v>n, `less` v<n,
  `divisible` n>0 && v%n==0, `equals` v==n. Label = `strings[prefix + "_rule_" + kind]` with `{$a}` = n.
- **sum()** (math-race): pick a random `sumrules` row, `a = random(mina..maxa)`, `b = random(minb..maxb)`.
  For `minus`, swap if b > a (never negative). Text `"a + b"`, `"a - b"`, `"a × b"`.
- All `random(min, max)` ranges are **inclusive** integers. "Shuffle" = uniform Fisher–Yates.
- **Numbers on screen:** when `arabicdigits` is true, show digits as ٠١٢٣٤٥٦٧٨٩.
- **Progress:** every game shows "done / total" (`strings.progress`).

### 0.4 Feedback, sound, speech

- Flash messages stay **2500 ms**.
- Sounds are generated tones, not files (you can use your own):
  - ding = 660 Hz 0.12 s, then 880 Hz 0.16 s 110 ms later;
  - buzz = 220 Hz triangle 0.18 s;
  - fanfare = 523/659/784/1046 Hz, 0.18 s each, 130 ms apart.
- TTS: rate 0.95, locale = `speechlang`. Each new utterance cancels the previous one.
- The sound on/off choice is per device and also mutes TTS (not the microphone).
- **Level (1–3) is display only.** No game reads it. Difficulty comes from the settings and from
  ramps inside each game.

### 0.5 Start card strings

Every game shows `<key>_ready` (title) and `<key>_howto` (how to play) on its start card. `<key>` is
the slug with `-` → `_` (e.g. `xo_quiz_ready`). `get_game` also returns them as `readytitle` / `howto`.

---

## xo-quiz

**Shape:** `questions` · **Settings:** `matches` (3)

- **Round:** `matches` tic-tac-toe matches on a 3×3 board. The child is always ❌ and always moves
  first. No timer, no lives.
- **A move:** tap an empty cell → a question opens under the board (options per §0.3, question spoken).
  - Right → `correct()` (+1) and ❌ goes on that cell.
  - Wrong → `wrong()` with message `xo_missed`; the cell stays empty (turn lost).
  - Then, unless the match is over, the computer plays ⭕ after **700 ms** (status `xo_thinking`, then `xo_yourturn`).
- **Computer (⭕) AI**, first rule that applies:
  1. complete its own line;
  2. block ❌'s line (lines checked in order: rows, columns, diagonals);
  3. take the centre (index 4);
  4. a random free corner of [0, 2, 6, 8];
  5. the lowest free index.
- **Match end:** a 3-in-line or a full board (draw). Status `xo_youwin` / `xo_youlose` / `xo_draw`.
  A win also calls `correct(xo_youwin, 3)` (**+3**, and counts as a correct event).
  Wait **1800 ms**, then the next match (status `xo_round` = "Match {$a} of {$b}").
- **Questions:** draw 30 at start and 20 more when the queue empties (repeats possible).
- **End:** after the last match → `finish(score, goal = matches won)`.
- **Score:** right answers × 1 + wins × 3. **goal:** matches won (0–3).
- **Badge:** `xo-champion` = goal ≥ 3.
- **Assets:** ❌ ⭕ emoji.

## target-answer

**Shape:** `questions` · **Settings:** `questions` (12), `crossslow` (15 s), `crossfast` (10 s), `readingpause` (2.2 s)

- **Round:** `questions` questions (options per §0.3). Each question:
  - Show and speak it. One 🎯 target per option, each labelled with its text.
  - Targets **freeze for `readingpause`**, then cross left → right (physical LTR, also in Arabic).
- **Lanes:** a shuffled permutation of 0..n-1. Vertical centre = `(lane + 0.5) / n × fieldHeight`.
- **Motion:** position `at` starts at `−0.15 − i × 0.18` (i = option index), so the targets are staggered.
  - Speed = `1 / seconds` per second, with `seconds = crossslow − (crossslow − crossfast) × (asked / questions)`.
  - x = `at × (fieldW + targetW) − targetW / 2`.
  - A target is removed when `at > 1.15`. Clamp frame dt to 0.05 s.
- **Tap:** any tap ends the question (no second try).
  - Right → `correct(points)` with points = 3 if `at < 0.35`, 2 if `at < 0.7`, else 1. Next question after 750 ms.
  - Wrong → `wrong()`.
  - All targets exit untapped → `wrong()`, next after 700 ms.
- **End:** queue empty → `finish()`. **Score** = sum of points. **goal:** 0.
- **Badge:** `sharp-shot` = streak ≥ 10.

## wheel

**Shape:** `topicquestions` (topics `math`, `science`, `language`, `animals`) · **Settings:** `spins` (10), `friction` (0.972), `minflick` (240), `maxflick` (1800)

- **Wheel:** 4 × 90° segments clockwise from the top:
  - math 🔢 #4b7be0
  - science 🔬 #3fa877
  - language 🔤 #9b5fd0
  - animals 🐘 #e0894b

  Pointer ▼ at the top.
- **Spin:**
  - The button gives a velocity of random 700–1500 °/s, clockwise.
  - Drag-flick: velocity = Δangle/Δt. If |v| < `minflick` on release, it counts as a tap:
    v = sign × (`minflick` + random 0–260). Clamp to ±`maxflick`.
  - Coast: `angle += v·dt`, `v *= friction^(dt·60)`, with dt clamped to 0.05 s. Stop when |v| ≤ 12 °/s.
- **Landing:** `under = ((−angle mod 360) + 360) mod 360`, segment = `floor(under / 90)`.
  Show `wheel_landed` ("It landed on {$a}", `{$a}` = `topic_<key>`) and speak it with the question.
- **Question:** one random question of that topic (with replacement), options per §0.3.
  - Right → `correct()`. Wrong → `wrong()` and the right option is highlighted.
  - If the topic has no questions, the spin does not count.
- After an answer: wait 1600 ms. **Block new spins** until the question is answered and the delay is over.
- **End:** after `spins` answered spins → `finish(score, goal = distinct topics answered correctly)`.
- **Score:** right answers. **goal:** 0–4. **Badge:** `encyclopedia` = goal ≥ 4.

## who-am-i

**Shape:** `clues` (`{answer, emoji, clues[3]}`) · **Settings:** `riddles` (8), `worth1` (10), `worth2` (6), `worth3` (3)

- **Round:** shuffle `whoami`, take `riddles`. No timer, no lives, no skip.
- **Screen:**
  - Clue 1 is shown and spoken. Clues are numbered "1. ", "2. ", …
  - Button `who_hint` reveals the next clue (max 3); each new clue is spoken.
  - Line `who_worth` = "Worth {$a} points now", with `{$a}` = worth for the number of clues shown.
- **Choices:** the riddle + up to 3 other riddles with a **different answer text**, all shuffled once
  per riddle. Each choice shows emoji + answer.
- **Guess:**
  - Wrong → `wrong()` and reveal the next clue automatically (if any). Unlimited guesses.
  - Right → `correct(who_answer, worthN)`, where N = clues shown. Speak the answer. Next riddle after 1600 ms.
- **End:** queue empty → `finish(score, goal = riddles solved with only clue 1 shown)`.
  An empty bank finishes at once with 0.
- **Score:** Σ worth (max 80). **Badge:** `good-detective` = goal ≥ 5.

## letter-order

**Shape:** `words` (uses `word`, `emoji`) · **Settings:** `words` (15)

- **Pool:** words of **3–6 letters** (count Unicode code points). Shuffle, take `words`, then
  stable-sort by length ascending (short words first).
- **Screen:**
  - Big emoji, plus `order_hint` ("This word has {$a} letters"), also spoken.
  - Slots = word length. Tray = the word's letters (one tile per code point), shuffled until the order
    differs from the word.
- **Input:**
  - Tap a tray tile → it fills the next free slot.
  - Tap a filled slot → the tile returns to the tray and later slots shift left.
  - `order_clear` empties all slots.
- **Judge** when all slots are full (exact string compare):
  - Right → `correct()`, speak the word, next after 1100 ms.
  - Wrong → `wrong()`; after 900 ms all tiles return to the tray. Same word, unlimited retries.
- **End:** queue empty → `finish()`. **Score:** words solved. **goal:** 0.
- **Badge:** `letter-king` = correct ≥ 15.
- **Note:** a word whose letters are all identical can't be "un-shuffled" — show it as is.

## word-builder

**Shape:** `vocabulary` (`wordlist` = plain strings) · **Settings:** `sets` (3), `size` (7), `minwords` (4), `maxtargets` (6)

- **Pool:** `wordlist`, de-duplicated, words with ≥ 3 letters (code points).
  **Seeds** are pool words of 4..`size` letters.
- **Making a set** (try up to 12 shuffled seeds):
  1. **Pile:** start with the seed's letters, in order (not shuffled). While the pile is shorter than
     `size`, add the letter (from all distinct letters in the pool, shuffled) that makes the **most** pool
     words spellable. Ties go to the first in shuffled order.
  2. **Spellable:** the word can be built from the pile's letters, respecting letter counts.
  3. **Targets:** spellable pool words sorted by length ascending, cut to `maxtargets`. If the longest
     spellable word was cut, it replaces the last target.
  4. Accept the first seed with ≥ `minwords` targets, else keep the best seed seen.
- **Screen:**
  - The `size`-letter pad (a letter in use is disabled) and the word being typed.
  - One row of empty boxes per target. Counter `build_found` ("You found {$a} words") + " / M".
  - Buttons `build_submit`, `build_clear`, `build_hint`, `build_next`.
- **Enter:**
  - A target not yet found → `correct(word, points)`, where points = **5** for ≥ 5 letters, **3** for 4, **2** for 3. Speak the word.
  - Already found → `wrong(build_already)`.
  - Not a target → `wrong(build_notaword)`. Even a real word that is not on the target list is rejected.
  - Empty → nothing.
- **Hint:** reveal one more letter (from the left) of the shortest unfound target that still has
  more than 1 hidden letter. A hint never reveals a word's last letter. If none is left → `wrong(build_nohints)`.
- **Set ends** when all targets are found (next after 1000 ms), or `build_next` is pressed (skips; still
  counts as a set).
- **End:** after `sets` sets → `finish()`. **Score:** Σ points. **goal:** 0. **Badge:** `word-builder` = correct ≥ 10.

## match-connect

**Shape:** `words` (rows with an emoji; uses `word`, `emoji`) · **Settings:** `pairs` (4), `boards` (4), `dragthreshold` (8 px)

- **Boards:** each board reshuffles the words and takes `pairs` (words may repeat across boards).
  - Picture zones show the emoji + `match_drop` ("Put the word here").
  - The word chips are the same words, freshly shuffled.
- **Input:**
  - Drag a chip onto a zone (a press moving > `dragthreshold` px becomes a drag).
  - Or tap a chip to select it (the word is spoken), then tap a zone.
- **Judge** (word text equal):
  - Right → `correct()`.
  - Wrong → `wrong()` and the zone flashes red for 600 ms.
  - Dropping on a solved zone is ignored.
- Board solved → next board after 900 ms.
- **End:** after `boards` boards → `finish()`. **Score:** matches. **goal:** 0.
- **Badge:** `sharp-eye` = correct ≥ 16 and maxwrong 0.

## color-challenge

**Shape:** `colours` (`{name, hex}`) · **Settings:** `choices` (4)

- **Round:** every colour once, shuffled (round length = bank size).
- **Question:** `colour_find` ("Find the colour {$a}", spoken) + `choices` circles filled with `hex`.
  The circles are the target plus up to `choices − 1` colours with a **different name**, shuffled.
  The name is never written on a circle.
- **Tap** (one per question):
  - Right → `correct()`.
  - Wrong → `wrong()` and the right circle is highlighted.
  - Next after 1300 ms.
- **End:** → `finish(score, goal = 1 if every colour was right, else 0)`.
- **Score:** right answers. **Badge:** `little-artist` = goal ≥ 1.

## math-shop

**Shape:** `shopitems` (`{emoji, name}`) · **Settings:** `trips` (5)

- **Round:** `trips` trips, each with 2 questions (total, then change). No timer, no lives.
- **Basket:** shuffle the shelf; trips 1–2 take **2** distinct items, later trips **3**.
- **Prices:** each item costs `random(3..12)` on trips 1–2 and `random(3..20)` after.
  Whole pounds; prices may repeat.
- **Budget:** `ceil((total + random(5..25)) / 5) × 5`, so the change is always ≥ 5.
- **Screen:** "💰 " + `shop_youhave` ("You have {$a} pounds"), each item with emoji, name, price and `shop_pound`.
- **Questions:**
  1. `shop_total` (spoken).
  2. `shop_change` (spoken), shown with "`shop_total` {total} `shop_pound`".
- **Options:** 3 numbers. The answer + the first 2 valid values from
  shuffle([+1, −1, +2, −2, +5, −5, +10, −10]) added to the answer. A value is valid when ≥ 0 and distinct.
  The options are shuffled.
- **Tap** (one per question): right → `correct()`, wrong → `wrong()`. A wrong total still goes on to the
  change question. 850 ms after every answer.
- **End:** → `finish()`. **Score:** right answers (max 10). **goal:** 0.
- **Badge:** `smart-shopper` = correct ≥ 10 and maxwrong 0.

## memory-cards

**Shape:** `words` (rows with an emoji) · **Settings:** `pairs` (6), `flipbudget` (20)

- **Pairs:** take `pairs` emoji words. **Each pair = two identical cards showing the emoji**; the
  pair is identified by the `word` text. The card back is 🎴. Shuffle the 2 × `pairs` cards.
- **Play:**
  - Every card turned face-up adds 1 to `flips`; the counter `memory_flips` ("Flips: {$a}") is shown.
  - Two open cards that match → after 420 ms they are done: `correct()`, speak the word.
  - No match → they turn back after 1200 ms. **No `wrong()` is ever called.**
  - The board is locked while two cards are open.
- **End:** all pairs found → 900 ms → `finish(score, goal = flips ≤ flipbudget ? 1 : 0)`.
- **Score:** pairs (6). **Badge:** `strong-memory` = goal ≥ 1.
- With fewer emoji words than `pairs`, play with what you have and end when all dealt pairs are found.

## crossword

**Shape:** `words` (uses `word`, `clue`) · **Settings:** `size` (11), `target` (5), `pad` (16)

- **Candidates:** words of 3–6 letters (code points) with a non-empty `clue`.
  Letters are compared code point by code point (no Arabic normalisation).
- **Layout** (up to 30 attempts):
  1. Shuffle the candidates, then stable-sort longest first.
  2. **Seed** = the first word, placed across in row `floor(size/2)`, starting at column `floor((size − len)/2)`.
  3. For each next word, while fewer than `target` are placed, find the placement with the **most
     crossings (≥ 1)**. For every letter i of the word and every grid cell with that letter, try across
     (start c − i) then down (start r − i). A placement only replaces the current best with strictly
     more crossings. A word with no valid placement is skipped.
  4. A placement is valid when:
     - the word stays inside the grid;
     - the cells just before and after the word are empty;
     - every cell it shares already holds the same letter;
     - every **new** cell has empty neighbours on both perpendicular sides.
  5. Stop at the first attempt that places `target` words. Otherwise use the last attempt if it has ≥ 2 words.
     With none, `finish()` with 0.
  6. **Numbering:** sort the entries by start cell (row × size + col) and number them 1, 2, 3…
     Entries sharing a start cell share a number.
- **Screen:** the grid (empty cells blocked), with the number in each start cell.
  - Tapping a cell selects the unsolved word through it; tapping a crossing again toggles across/down.
  - The clue line reads `number + " " + cross_across|cross_down + " — " + clue`, and is spoken.
  - Before any selection it shows `cross_pickcell`.
- **Letter pad:** the distinct letters of all answers, topped up with random decoy letters (from all
  words in the bank) to `pad` keys (more if the answers need it). Shuffled, plus ⌫ (`cross_backspace`).
- **Typing:**
  - A letter fills the first empty cell of the selected word.
  - ⌫ clears the last filled cell that is not part of a solved word.
  - When the word is full → compare with `word`:
    - Right → `correct(word)`, cells locked, word spoken.
    - Wrong → `wrong()` and that word's unlocked cells are cleared.
- **End:** all words solved → 900 ms → `finish()`. **Score:** words (2–5). **goal:** 0.
- **Badge:** `puzzle-solver` = correct ≥ 5 and maxwrong 0.

## word-search

**Shape:** `words` (uses `word`) · **Settings:** `size` (9), `words` (6)

- **Grid:** `size` × `size`. Take the candidate words (3–6 letters), **de-duplicated**, shuffled.
- **Placing:** for each word while fewer than `words` are placed, make up to 220 random tries:
  - a direction from {across (0,+1), down (+1,0), diagonal (+1,+1)}, never backwards;
  - a random start cell.
  
  A placement fits when it stays inside the grid and every shared cell has the same letter (crossings allowed).
- **Filler:** empty cells get a random letter from the distinct letters of all bank words.
- In Arabic, column 0 is on the **right** (across reads right-to-left).
- **List:** `search_remaining` ("{$a} left") + the hidden words, struck through when found.
- **Input:** drag from cell A to cell B along a row, column or 45° diagonal (8 directions).
  On release, a path of ≥ 2 cells is judged; its letters, **forward or reversed**, must equal an unfound word.
  - Hit → `correct(word)`, word spoken, the path stays highlighted.
  - Miss → `wrong()`. A single-cell tap counts as nothing.
- **End:** all placed words found → 900 ms → `finish()`. If 0 words could be placed, end immediately with 0.
- **Score:** words found. **goal:** 0. **Badge:** `falcon-eye` = correct ≥ 6.

## balloon-pop

**Shape:** `numberrules` · **Settings:** `target` (25), `ruleevery` (8), `riseslow` (8 s), `risefast` (4.5 s), `spawnstartms` (900), `spawnminms` (480), `columns` (5)

- **Rule:** `numberRule('balloon')` (§0.3).
  - Shown in a banner and spoken at the start, and again after every `ruleevery` correct pops while
    popped < `target`. Each new rule is a fresh random pick, so the same kind can repeat.
  - Balloons already in the air are judged against the new rule.
  - With no rules → `finish(0)` at once.
- **Spawn:** the first spawn comes after 300 ms. Each balloon:
  - **wantmatch** = random < 0.5;
  - **value** = `random(1..40)`, redrawn up to 25 times until `test(value) == wantmatch`;
  - **colour** = random from #e04b4b, #4b7be0, #3fa877, #e0c84b, #9b5fd0, #e07ab0;
  - **lane** = drawn from a shuffled bag of 0..`columns`−1 (refilled when empty);
  - **x** = `(lane + 0.5) / columns + (rand − 0.5) × 0.08`;
  - **sway:** amplitude = `(rand − 0.5) × 0.09`, phase = `rand × 2π`.
- **Motion** (p = min(popped / target, 1)):
  - Rise rate = `1 / (riseslow − (riseslow − risefast) × p)` of the sky per second.
  - Screen x = `(x + sin(at × 5 + phase) × amplitude) × W`; y goes from just below the sky (at = 0) to the top.
  - Removed at `at > 1.1`, with no penalty.
  - Spawn gap = `spawnstartms − (spawnstartms − spawnminms) × p` ms. Clamp dt to 0.05 s.
- **Tap:**
  - Matching → pop (💥), `correct()`.
  - Not matching → shake, `wrong()`. The balloon keeps flying and can be tapped again.
- **End:** popped = `target` → 500 ms → `finish()`. No losing. **Score:** `target`. **goal:** 0.
- **Badge:** `pop-master` = correct ≥ 25.

## space-quiz

**Shape:** `questions` · **Settings:** `stage1steps` (5), `stage2steps` (7), `stage3steps` (9)

- **Journey:** Moon 🌕 (`space_moon`) → Mars 🔴 (`space_mars`) → Jupiter 🪐 (`space_jupiter`).
  Each planet needs its number of right answers.
- **Screen:**
  - `space_stage` ("Stage {$a}: {$b}").
  - A track with 🚀 at `7% + along × 78%`, where along = min(step / steps, 1); the planet looks "near" at along > 0.7.
  - The question (spoken) and its options (§0.3).
- **Questions:** draw 30, redraw 30 when empty (repeats possible). One at a time.
- **Answer:**
  - Right → `correct()`, step + 1.
  - Wrong → `wrong()` and the right option is highlighted. The rocket never moves back.
  - 1400 ms pause after each answer.
- **Planet reached:** `correct(space_arrived, 3)` (**+3**, counts as a correct event). Next stage after 1200 ms.
- **End:** after Jupiter → 900 ms → `finish(score, goal = 1)`. The only ending.
- **Score:** 21 + 3 × 3 = 30 with the default settings. **Badge:** `astronaut` = goal ≥ 1.

## math-catcher

**Shape:** `numberrules` · **Settings:** `lives` (3), `target` (25), `ruleevery` (6), `fallstart` (95 px/s), `fallmax` (200 px/s), `spawnstartms` (1150), `spawnminms` (620), `basketspeed` (460 px/s)

- **Rule:** `numberRule('catch')` (§0.3). It is shown in a strip, spoken, and changed when
  caught % `ruleevery` == 0 and caught < `target`. With no rules → `finish(0)`.
- **Tokens** (all look the same), progress p = min(caught / target, 1):
  - **wantmatch** = random < 0.5.
  - **equals rule:** show a sum. a = random(1..15), b = random(1..12); if a > b, 50% `a − b`, else `a + b`; value = result.
  - **Other rules:** value = random(1..40), shown as a number.
  - Redraw up to 25 times until `test(value) == wantmatch`.
  - **Radius:** 36 for sums, 28 for numbers. Spawn at random x inside the field, y = −r.
  - **Fall speed** = `fallstart + (fallmax − fallstart) × p` px/s.
  - **Spawn gap** = `spawnstartms − (spawnstartms − spawnminms) × p` ms; the first spawn comes after 400 ms.
- **Basket:**
  - Width clamp(W × 0.22, 90, 150), height 26, 14 px above the bottom. Drawn as a rounded box with 🧺.
  - Moves at `basketspeed` while ⬅️/➡️ (`catch_left` / `catch_right`) is held, or follows the finger.
  - The pad stays left-to-right in Arabic too.
- **Catch:** `|dx| < basketW/2 + r × 0.55` and the token touches the basket's top. The token is judged
  against the **current** rule.
  - Match → `correct()`.
  - No match → lives − 1 and `wrong()`.
  - A token falling past the basket costs nothing.
- **End:** caught = `target` or lives = 0 → `finish(caught)`. **Score:** caught. **goal:** 0.
- **Badge:** `sharp-hunter` = correct ≥ 20 and maxwrong 0.
- **Assets:** everything is drawn (circles, 🧺, ⬅️ ➡️). Draw your own.

## runner

**Shape:** `questions` · **Settings:** `hearts` (3), `target` (10), `lanes` (3), `travelslow` (4.2 s), `travelfast` (2.6 s), `wavestartms` (4600), `waveminms` (3000)

- **Track:** `lanes` lanes. 🏃 starts in the middle lane. Move one lane with ⬅️/➡️ or by tapping/dragging
  on the track (lane = floor(x / W × lanes)). Lanes are physical left-to-right, also in Arabic.
- **Waves** (p = min(collected / target, 1)):
  - The first wave comes after 600 ms, then every `wavestartms − (wavestartms − waveminms) × p` ms,
    whether or not the last one was answered.
  - Each wave first shows and speaks a **new** question, then sends its boxes: the right answer + **one**
    random wrong option, in 2 of the lanes (shuffled); the other lane is empty.
  - Each box remembers its own question.
- **Box motion:** `at` grows by `1 / (travelslow − (travelslow − travelfast) × p)` per second (also for
  boxes already moving). y top = `at × (H + boxH) − boxH`. Removed at `at > 1.1`.
- **Hit:** a box with `0.8 < at < 1` in the runner's lane.
  - Right box → `correct()`, collected + 1, the other boxes of the wave are spent.
  - Wrong box → hearts − 1 and `wrong()`; the right box can still be caught.
  - A missed box costs nothing.
- **End:** collected = `target` or hearts = 0 → 400 ms → `finish(score, goal = (collected ≥ target && hearts == full) ? 1 : 0)`.
- **Score:** collected. **Badge:** `fast-runner` = goal ≥ 1.
- **Assets:** 🏃 emoji, plain boxes (right and wrong look the same). Draw your own.

## puzzle

**Shape:** `words` (rows with an emoji; only `emoji` used) · **Settings:** `board1` (2), `board2` (3), `board3` (4) = pieces per side

- **Picture (generated, no image files):** for a board of side s, shuffle the emoji words and take
  s × s emoji. The picture is an s × s grid with emoji i centred in cell i (row-major), glyph size
  72% of a cell. With fewer emoji than cells, the remaining cells are blank.
  **Piece k = cell k of that picture.** A native app can just draw emoji[k] on tile k.
- **Board:** shuffle the pieces until the order is not already solved.
  - The whole picture is shown for 2000 ms first.
  - `puzzle_peek` shows it again for 2000 ms, unlimited and free.
- **Swap:** tap a piece to select it (tap again to unselect); tap another piece to swap the two.
  After a swap, if **at least one** of the two pieces is now in its home slot → `correct()` **once**.
  `wrong()` is never called.
- **Board solved** → 900 ms → next board. **End:** after board 3 → `finish(score, goal = 1)`.
- **Score:** number of good swaps (no upper bound). **Badge:** `picture-builder` = goal ≥ 1.

## find-difference

**Shape:** `words` (rows with an emoji; only `emoji` used) · **Settings:** `cols` (4), `rows` (4), `differences` (5), `boards` (3)

- **Pictures (generated):**
  - **Left scene:** `cols × rows` cells, each a random emoji from the list (with replacement).
  - **Right scene:** a copy of the left; then pick `differences` distinct cells at random and give each a
    **different** random emoji. Needs ≥ 2 distinct emoji; otherwise skip the game.
- **Coordinates:** cell index 0..cols×rows−1, row-major. The **whole cell** is the hit area (no radius).
  Only the right scene (`diff_right`) is tappable; the left one is captioned `diff_left`.
- **Tap:**
  - On a changed, unfound cell → `correct()`, mark it.
  - On an unchanged cell → `wrong()` (500 ms outline).
  - On a found cell → nothing.
- **Counter:** `diff_remaining` ("{$a} differences left").
- **Board done** → next board after 1700 ms. **End:** after `boards` boards → `finish()`.
- **Score:** 15 with the default settings. **goal:** 0.
- **Badge:** `detective-eye` = correct ≥ 15 and maxwrong 0.

## speak-words

**Shape:** `words` (rows with an emoji; uses `word`, `emoji`) · **Settings:** `words` (10)

- **Round:** shuffle, take `words`. Show the emoji, `speak_saythis` and the word; speak the word (TTS, `speechlang`).
- **Listen:** each tap on 🎤 (`speak_tap`) is one attempt. Stop TTS first, then start recognition:
  - locale = `speechlang`;
  - single utterance, final results only, up to **5 alternatives**;
  - status `speak_listening`, then `speak_heard` ("I heard: {$a}") with the top alternative.
- **Judge:** normalise the target and each alternative:
  1. remove U+064B–U+0652 and U+0670 (tashkeel);
  2. map آ أ إ ٱ → ا;
  3. map ة → ه;
  4. map ى → ي;
  5. remove every character that is not a letter;
  6. lowercase.

  It is a **hit** if **any** alternative **contains** the target ("this is a cat" counts for "cat").
- **Outcome:**
  - Hit → `correct()`, next word after 1100 ms. Block the mic during that time.
  - Miss → `wrong()`. The word stays on screen for another try, **and** a copy is added to the end of the queue.
  - Unlimited tries.
- **Errors:**
  - Permission denied → `speak_denied`.
  - Other errors (no speech, network) → show `wrong`; this counts nothing.
  - No speech recognition on the device → show the `speak_nomic` card; the round is not submitted.
- **End:** queue empty → `finish()`. **Score:** words said (can exceed `words` after misses). **goal:** 0.
- **Badge:** `clear-voice` = correct ≥ 10.
- **Note:** the start card (`speak_words_howto`) is the microphone notice — show it before asking for the
  mic permission.

---

## quiz

**Shape:** `questions` · **Settings:** `questions` (20)

- `questions(n)` (§0.3); progress shows "/ n".
- Show the topic line (`topic_<topic>`; empty for this game) and the question (spoken).
- First tap locks: right → `correct()`; wrong → `wrong()` and the right option is highlighted.
- 2600 ms before the next question. End → `finish()`.
- **Score:** right answers. **goal:** 0. **Badge:** `know-it-all` = correct ≥ 20.

## true-false

**Shape:** `statements` · **Settings:** `statements` (18)

- Shuffle, take n. Show the statement (spoken) and two buttons: ✅ `tf_true` first, ❌ `tf_false` second.
- One answer: `value == true` → `correct()`, else `wrong()`.
- Then show and speak `why`. 4200 ms before the next one. End → `finish()`.
- **Score:** right answers. **goal:** 0. **Badge:** `focused` = streak ≥ 15.

## math-race

**Shape:** `sumrules` · **Settings:** `target` (12), `maxattempts` (26), `timestart` (13 s), `timefloor` (7 s)

- **Questions:** generate `target` questions with `sum()` (§0.3). With no rules → `finish(0)`.
- **Screen:** `"a + b = ?"`, shown left-to-right.
  - Speak it with + − × replaced by `op_plus` / `op_minus` / `op_times`, then `race_question`.
- **Options:** 3 numbers. The answer + the first 2 valid values of
  shuffle([+1, −1, +2, −2, +10, −10, +3, −3]) added to it (valid when ≥ 0 and distinct), then shuffled.
- **Clock:** per question, `timestart − (timestart − timefloor) × (solved / target)` seconds, ticking every
  100 ms. It is paused during the 750 ms feedback.
- **Answer:**
  - Right → `correct()`, solved + 1; 🏃 moves to `min(solved / target, 1) × 88%` towards 🏁.
  - Wrong tap or time-out → `wrong()` and the question goes to the back of the queue.
  - Every answer or time-out counts as one attempt.
- **End:** solved = `target`, or attempts = `maxattempts`, or the queue is empty → `finish(solved)`.
- **Score:** solved. **goal:** 0. **Badge:** `fast-calculator` = streak ≥ 10.

---

## 3. Answers to the specific questions

| Question | Answer |
|---|---|
| Shape in `get_game` / `get_games` | **Done.** `shape` is now on every card in `get_games` and in `get_game`. |
| Tunable values in the API | **Done.** `get_content` returns `settings: [{key, value}]` + `rulesversion`. The web game reads the same values from the server, so web and app cannot drift. Keys per game are listed in each section above. Admins can't edit them in the UI yet. |
| puzzle picture URL(s) and grid size | No URLs: the picture is generated from the game's emoji words (see [puzzle](#puzzle)). Grid = `board1` / `board2` / `board3` pieces per side (2, 3, 4). |
| find-difference pictures and difference areas | Generated on the device from the emoji words: a `cols × rows` emoji grid, with `differences` random cells changed. Areas = whole cells by index (see [find-difference](#find-difference)). |
| speak-words language and recognition | `speechlang` in `get_content` (`ar-EG` / `en`). The web uses the browser's SpeechRecognition (5 alternatives, contains-match after normalisation); use the phone's recogniser with the same locale and judging. |
| memory-cards pairs | Two identical emoji cards per word, from the `words` list (rows with an emoji); pair key = `word`. |
| images / sounds for runner, space-quiz, balloon-pop, math-catcher, color-challenge | None. Everything is emoji, simple shapes (CSS / canvas) and generated tones. The app may draw its own. |
| Read access to the JS | `public/local/games/js/*.js` in this repository (the `jitsi_life` branch), also served at `https://<site>/local/games/js/<slug>.js`. |

## 4. Known web bugs — don't copy them

These are edge cases where the web version misbehaves. A native port should guard against them;
they don't change the normal scoring.

- **Empty or too-small content can soft-lock the web game:**
  - `xo-quiz`, `target-answer`, `space-quiz`, `runner`, `wheel`: an empty question list.
  - `word-search`: 0 placeable words.
  - `memory-cards`: fewer than `pairs` emoji words.
  - `find-difference`: fewer than 2 distinct emoji.
  - `word-builder`: no 4–7-letter word.
  - `crossword`: no 3–6-letter word with a clue.

  **In the app:** if the content can't make a round, say so and let the child pick another game. Don't submit.
- **`color-challenge`:** an empty colour list would finish with goal = 1. Don't submit an empty round.
- **`letter-order`:** a word with identical letters (e.g. "aaa") loops forever on the web. Just show it.
- **`wheel`:** the web lets a drag-spin start while a question is open. Block it.
- **`speak-words`:** the web keeps the mic active during the 1100 ms after a hit, which can skip a word. Block it.
- **Number rules:**
  - Some admin rules can be impossible: `greater` n ≥ 40, `divisible` n > 40, `less` n ≤ 1,
    `equals` n > 27 in math-catcher.
  - math-catcher then only ends by losing lives; balloon-pop never ends. The web has the same
    behaviour — consider ending the round after a long time with no possible match.
- **Fixed in this release:** balloon-pop's `equals` rule had no text (`balloon_rule_equals` was missing).
