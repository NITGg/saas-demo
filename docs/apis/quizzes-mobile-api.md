# Quizzes — Mobile API

Everything the app needs to **list quizzes, take a quiz natively (all 17 Moodle question types), save
answers, finish, and show the review**. Moodle itself grades every answer, so marks, the gradebook and
the website's review always match what the app shows.
Last updated **2026-10-19**.

> Server side: plugin `local_academy` (`/local/academy/api.php`). The full platform reference is
> [`../MOBILE_API.md`](../MOBILE_API.md) (§3.6 is the short version of this file).

## Contents

1. [Basics](#1-basics) — URL, token, calling convention, errors
2. [The flow](#2-the-flow) — from the course page to the review
3. [Endpoints](#3-endpoints) — the 8 functions
4. [Question types](#4-question-types) — what you draw and what you send, type by type
5. [Results and review](#5-results-and-review)
6. [Images](#6-images)
7. [Reference](#7-reference) — error codes, states, checklist

---

## 1. Basics

### 1.1 URL and token

```
GET|POST {base}/local/academy/api.php?function=<name>&token=<token>[&lang=ar|en]&…
```

- `{base}` = the academy's site, e.g. `https://academy2026.nitg-eg.com/jitsi_life`.
- `token` = **the student's normal user token** (from login). Quizzes are per user.
- `lang=ar|en` (optional) = language of names and error messages.

### 1.2 Calling convention

- **Reads are `GET`, writes are `POST`** (`application/x-www-form-urlencoded`). A write sent as GET
  fails with `postrequired`.
- **JSON parameters** (`answer`, `answers`) are sent as a **JSON string inside a normal form field**.
- Success: `{"status":"success","data": …}`
- Failure: `{"status":"fail","error":"<readable — show it>","errorcode":"<stable code>"}`
- HTTP `401` = token missing/dead → log out. HTTP `403` = academy suspended → blocking screen.
  Everything else is HTTP `200`: check `status`.

---

## 2. The flow

```
course page (getalltopics)                → quiz activity with cmid + nativerender.requires
   │
   ├─ get_quiz (cmid)                      → quiz info screen: name, intro, time limit, attempts allowed
   ├─ get_my_quiz_attempts (quizid)        → is there an unfinished attempt? → continue it
   │
   ├─ start_quiz_attempt (quizid)          → attemptid            (only if nothing to continue)
   ├─ get_attempt_questions (attemptid)    → draw every question  (+ saved answers, to resume)
   ├─ save_quiz_answer  × N                → every time the student answers / changes an answer
   ├─ finish_quiz_attempt (attemptid)      → score + per-question result
   │
   └─ get_quiz_attempt (attemptid)         → review screen
```

Rules of thumb:

- **Always draw from `get_attempt_questions`, never from `get_quiz`.** Only the attempt knows this
  student's calculated numbers, the shuffled order of choices and which question a random slot picked.
- **Save as the student goes** (`save_quiz_answer` on every change, or debounced ~1 s for text). Saving
  again replaces the previous answer; sending `null` clears it. If the app is killed, call
  `get_attempt_questions` again: every question comes back with its saved `response`.
- **Before starting, check `get_my_quiz_attempts`**: if one has `state:"inprogress"`, continue that
  `attemptid` instead of starting a new one.
- **Time limit:** `timelimit` is in seconds (`0` = none) and starts at `timestart`. Show a countdown and
  call `finish_quiz_attempt` when it reaches 0.
- **Can it be native?** In `getalltopics`, a quiz's `nativerender.requires` lists `qtype:<type>` per type
  it uses. All 17 types in §4 and `qtype:random` are native. Anything else (a third-party plugin type),
  or a question that comes back with `supported:false`, needs the quiz opened in a **webview**
  (`nativerender.fallbackurl`).

---

## 3. Endpoints

### 3.1 `get_quizzes` (GET) — quizzes I can take

| Param | |
|---|---|
| `courseid` | optional — only this course |

```json
{"status":"success","data":[
  {"quizid":4,"cmid":96,"courseid":3,"name":"اختبار يحتوي على جميع الأسئلة","intro":"…",
   "timelimit":0,"attempts_allowed":0}
]}
```
`attempts_allowed: 0` = unlimited. Students get quizzes of their enrolled courses; admins get all.

### 3.2 `get_quiz` (GET) — quiz info screen

| Param | |
|---|---|
| `cmid` | the course module id (from the course page) |

```json
{"quizid":4,"cmid":96,"courseid":3,"name":"…","intro":"…","timelimit":600,"attempts_allowed":2,
 "questions":[
   {"slot":1,"questionid":531,"type":"multichoice","text":"…","images":[],"defaultmark":1.0,
    "supported":true,"single":true,"options":[{"id":532,"text":"One","images":[]}]},
   {"slot":2,"questionid":540,"type":"ddwtos","text":"…","images":[],"defaultmark":1.0,"supported":true}
 ]}
```
Use it for the intro screen (number of questions, total marks, which types). **Do not draw questions
from it** — see §2.

### 3.3 `start_quiz_attempt` (POST)

| Param | |
|---|---|
| `quizid` | |

```json
{"attemptid":559,"quizid":4,"attempt_number":1,"timestart":1760865600,"timelimit":600,"state":"inprogress"}
```
Errors: `notenrolled`, `attemptsexhausted` (no attempts left — show `error`).

### 3.4 `get_attempt_questions` (GET) — draw the attempt

| Param | |
|---|---|
| `attemptid` | |

```json
{"attemptid":559,"quizid":4,"quiz_name":"…","attempt_number":1,"state":"inprogress",
 "timestart":1760865600,"timelimit":600,
 "questions":[ { …question… }, … ]}
```

Every question has these common fields, plus the fields of its type (§4):

| Field | Meaning |
|---|---|
| `slot` | position in the quiz (1, 2, 3 …) — draw in this order |
| `questionid` | send it back in `save_quiz_answer` |
| `type` | one of the 17 types in §4 |
| `text` | the question text as plain text (calculated numbers already filled in) |
| `images[]` | image URLs from the question text, ready to load |
| `defaultmark`, `max_mark` | marks this question is worth in the quiz |
| `supported` | `true` = draw natively; `false` = open the quiz in the webview |
| `response` | the answer saved so far, in the same format you send (§4), or `null` |

### 3.5 `save_quiz_answer` (POST) — save one answer

| Param | |
|---|---|
| `attemptid` | |
| `questionid` | from the question |
| `answer` | **JSON string** in the format of the question's type (§4). `null` clears the answer. |
| `slot` | optional — only needed if the same question appears twice in one quiz |

```
POST /local/academy/api.php?function=save_quiz_answer&token=…
attemptid=559&questionid=531&answer=532
attemptid=559&questionid=540&answer={"1":2,"2":1,"3":3}
attemptid=559&questionid=545&answer="frog"
```
```json
{"attemptid":559,"questionid":531,"slot":1,"saved":true,"state":"inprogress"}
```
Plain text that is not valid JSON is accepted as a string (`answer=frog` works too), but send JSON
(`"frog"`) to be safe — e.g. `007` must be sent as `"007"` to keep its zeros.

Errors: `invalidquizanswer` (the answer does not fit this question — §7.1), `attemptalreadyclosed`,
`notyourattempt`, `invalidquestionid`.

### 3.6 `finish_quiz_attempt` (POST) — hand in

| Param | |
|---|---|
| `attemptid` | |

Moodle grades every saved answer and closes the attempt. Returns the result (§5.1).
Unanswered questions count as not answered (`state:"gaveup"`, mark 0).

### 3.7 `submit_quiz_attempt` (POST) — save + hand in, in one call

| Param | |
|---|---|
| `attemptid` | |
| `answers` | JSON string: `[{"questionid":531,"answer":532},{"questionid":540,"answer":{"1":2}}]` |

Saves each answer (same rules as §3.5) and then finishes. Answers saved earlier stay unless this call
sends a new one for the same question. Returns the result (§5.1). Use it for short quizzes; for long
ones prefer saving as you go.

### 3.8 `get_quiz_attempt` (GET) — review

| Param | |
|---|---|
| `attemptid` | |

See §5.2.

### 3.9 `get_my_quiz_attempts` (GET) — my attempts on a quiz

| Param | |
|---|---|
| `quizid` | |

```json
[{"attemptid":559,"attempt_number":1,"state":"finished","score":14.0,"max_score":16.0,"percent":87.5,
  "timestart":1760865600,"timefinish":1760866200},
 {"attemptid":560,"attempt_number":2,"state":"inprogress","score":null,"max_score":16.0,"percent":null,
  "timestart":1760870000,"timefinish":0}]
```

---

## 4. Question types

For each type: the extra fields you get in `get_attempt_questions`, how to draw it, and the `answer`
to send. The examples are exactly what the server sends and accepts.

| # | `type` | What the student does | `answer` format |
|---|---|---|---|
| 4.1 | `multichoice` | pick one / several options | option `id` / `[ids]` |
| 4.2 | `truefalse` | true or false | option `id` or `true`/`false` |
| 4.3 | `shortanswer` | type a word | `"text"` |
| 4.4 | `numerical` | type a number | `"3.14"` or `{"value","unit"}` |
| 4.5 | `calculated`, `calculatedsimple` | type a number (own numbers per student) | same as numerical |
| 4.6 | `calculatedmulti` | pick a number (own numbers per student) | same as multichoice |
| 4.7 | `essay` | write a text | `"text"` |
| 4.8 | `match`, `randomsamatch` | pair each stem with a choice | `{"stemId": choiceId}` |
| 4.9 | `gapselect` | dropdown inside the text | `{"place": value}` |
| 4.10 | `ddwtos` | drag words into gaps | `{"place": value}` |
| 4.11 | `ddimageortext` | drag words/images onto boxes on an image | `{"place": value}` |
| 4.12 | `ddmarker` | drop markers at points on an image | `{"markerNo": [[x,y]]}` |
| 4.13 | `ordering` | drag items into order | `["key", …]` |
| 4.14 | `multianswer` (Cloze) | several small questions inside one text | `{"index": subAnswer}` |
| 4.15 | `description` | nothing — information only | — |

### 4.1 `multichoice`

```json
{"type":"multichoice","text":"Which is the oddest number?","single":true,
 "options":[{"id":532,"text":"Two","images":[]},{"id":531,"text":"One","images":[]},
            {"id":534,"text":"Four","images":[]},{"id":533,"text":"Three","images":[]}]}
```
- Draw the options **in the order received** (already shuffled for this attempt).
- `single:true` → radio buttons → `answer`: `531`
- `single:false` → checkboxes → `answer`: `[531,533]`

### 4.2 `truefalse`

```json
{"type":"truefalse","options":[{"id":601,"text":"True","images":[],"value":true},
                               {"id":602,"text":"False","images":[],"value":false}]}
```
`answer`: the option `id` (`601`) **or** a boolean (`true`).

### 4.3 `shortanswer`

```json
{"type":"shortanswer","text":"Name an amphibian: __________","input":"text"}
```
`answer`: `"frog"`. Moodle does the matching (case and `*` wildcards as the teacher set them).

### 4.4 `numerical`

```json
{"type":"numerical","text":"What is pi to two d.p.?","input":"number",
 "unit_input":"none","units":[],"units_left":false}
```
- `unit_input:"none"` → a number box only → `answer`: `"3.14"`
- `unit_input:"text"` → the student types the unit after the number in the same box
  (`units[]` lists the accepted ones, as a hint) → `answer`: `"3.14 cm"`
- `unit_input:"select"` → a number box + a unit dropdown from `units[]` (`units_left:true` = put the
  dropdown before the number) → `answer`: `{"value":"3.14","unit":"cm"}`

Send the number as **text** exactly as typed (decimal point `.`).

### 4.5 `calculated`, `calculatedsimple`

Same fields and `answer` as `numerical`. The `text` already contains **this student's numbers**:

```json
{"type":"calculated","text":"ثمن الكتاب 7 جنيه. كم ثمن 3 كتب؟","input":"number","unit_input":"none","units":[]}
```
`answer`: `"21"`.

### 4.6 `calculatedmulti`

Same fields and `answer` as `multichoice`; option texts already contain this student's numbers:

```json
{"type":"calculatedmulti","text":"ثمن الكتاب 7 جنيه. كم ثمن 3 كتب؟","single":true,
 "options":[{"id":701,"text":"21","images":[]},{"id":702,"text":"14","images":[]},{"id":703,"text":"28","images":[]}]}
```
`answer`: `701`.

### 4.7 `essay`

```json
{"type":"essay","text":"Write a paragraph about your last holiday.","input":"textarea",
 "response_required":true,"lines":15,"min_words":80,"max_words":100,"attachments_allowed":0}
```
- Multi-line text box with `lines` visible rows. `min_words`/`max_words` (`0` = no limit): show a word
  counter.
- `answer`: `"Last summer I went to …"`
- An essay is **marked by the teacher later**: after finishing, its `mark` is `null` and
  `needs_grading:true` (§5.1).
- File uploads are not available in the app: an essay that **requires** files comes back
  `supported:false` → webview.

### 4.8 `match`, `randomsamatch`

```json
{"type":"match","text":"Classify the animals.",
 "stems":[{"id":811,"text":"frog","images":[]},{"id":812,"text":"cat","images":[]},{"id":813,"text":"newt","images":[]}],
 "choices":[{"id":821,"text":"mammal"},{"id":822,"text":"amphibian"},{"id":823,"text":"insect"}]}
```
- One row per stem with a dropdown of all `choices` (a choice can be used by several stems).
- `answer`: `{"811":822,"812":821,"813":822}` — stem `id` → choice `id`. Leave out stems not answered.
- `randomsamatch` looks and answers exactly the same (its stems are short-answer questions picked at random).

### 4.9 `gapselect` — dropdowns inside the text

```json
{"type":"gapselect","text":"The [[1]] sat on the [[3]].","drag":false,
 "segments":[{"type":"text","text":"The "},{"type":"gap","place":1,"group":1},
             {"type":"text","text":" sat on the "},{"type":"gap","place":2,"group":1},
             {"type":"text","text":"."}],
 "groups":{"1":[{"value":1,"text":"mat","infinite":false},{"value":2,"text":"cat","infinite":false}]}}
```
- Draw `segments` in order as one flowing text (`Text.rich` / spans). Do **not** draw `text`: it still
  holds the raw `[[n]]` markers. Keep the spaces inside text segments.
- Each `gap` = a dropdown with the choices of `groups[gap.group]`.
- `answer`: `{"1":2,"2":1}` — gap `place` → choice `value`.

### 4.10 `ddwtos` — drag words into gaps

Same fields as `gapselect`, with `"drag":true`:

```json
{"type":"ddwtos","drag":true,
 "segments":[{"type":"text","text":"The "},{"type":"gap","place":1,"group":1},
             {"type":"text","text":" brown "},{"type":"gap","place":2,"group":2},
             {"type":"text","text":" jumped over the "},{"type":"gap","place":3,"group":3},
             {"type":"text","text":" dog."}],
 "groups":{"1":[{"value":1,"text":"slow","infinite":false},{"value":2,"text":"quick","infinite":false}],
           "2":[{"value":1,"text":"fox","infinite":false},{"value":2,"text":"dog","infinite":false}],
           "3":[{"value":1,"text":"assiduous","infinite":false},{"value":2,"text":"lazy","infinite":false}]}}
```
- Show the words of all groups as draggable chips under the text; a gap accepts only chips of its own
  `group` (colour each group differently).
- `infinite:false` → the chip disappears once dropped; `infinite:true` → it can be used in many gaps.
- `answer`: `{"1":2,"2":1,"3":2}`.

### 4.11 `ddimageortext` — drag onto an image

```json
{"type":"ddimageortext","text":"Identify the features in this cross-section.",
 "background":{"url":"https://…/local/academy/qfile.php?…&component=qtype_ddimageortext&area=bgimage&…&token=…",
               "width":600,"height":400},
 "drops":[{"place":1,"group":1,"left":43,"top":120,"label":"Box 1"},
          {"place":2,"group":1,"left":310,"top":62,"label":"Box 2"}],
 "groups":{"1":[{"value":1,"text":"Hill","infinite":false,"image":null},
                {"value":2,"text":"Valley","infinite":false,"image":"https://…qfile.php?…&area=dragimage&…"}]}}
```
- Show `background` scaled to the screen: `scale = shownWidth / background.width`.
- Each drop is a visible box whose **top-left corner** is at `(left × scale, top × scale)`. All boxes of
  one group have the size of that group's biggest item.
- Draggable items: `image` set → drag the picture (`text` is its alt text); `image:null` → drag the word.
- `answer`: `{"1":1,"2":2}` — drop `place` → item `value` (same as gaps).

### 4.12 `ddmarker` — drop markers on an image

```json
{"type":"ddmarker","text":"Please place the markers on the map of Milton Keynes …",
 "background":{"url":"https://…qfile.php?…&component=qtype_ddmarker&area=bgimage&…","width":991,"height":721},
 "markers":[{"no":1,"text":"OU","infinite":false,"limit":1},
            {"no":2,"text":"Railway station","infinite":false,"limit":3}]}
```
- The target areas are **never sent** (they are the answer) — the student must know where to drop.
- `limit` = how many copies of the marker can be placed (`0` = unlimited).
- `answer`: where each copy was dropped, in **background pixels** (divide screen points by `scale`):
  `{"1":[[322,213]],"2":[[505,363],[488,401]]}`
- Leave out markers not placed.

### 4.13 `ordering`

```json
{"type":"ordering","text":"Put these words in order.","layout":"vertical",
 "items":[{"key":"ordering_item_9c2a…","text":"Dynamic","images":[]},
          {"key":"ordering_item_51ff…","text":"Modular","images":[]}, …]}
```
- A reorderable list (`layout:"horizontal"` = a row) in the order received.
- `answer`: **every** item `key` once, in the student's order:
  `["ordering_item_51ff…","ordering_item_77ab…", …]`.
- The item list is already a valid answer, so an untouched question can still be saved as-is.

### 4.14 `multianswer` (Cloze — embedded answers)

```json
{"type":"multianswer",
 "segments":[{"type":"text","text":"Complete this opening line of verse: \"The "},
             {"type":"sub","index":1},
             {"type":"text","text":" and the "},
             {"type":"sub","index":2},
             {"type":"text","text":" went to sea\"."}],
 "subquestions":[
   {"index":1,"type":"shortanswer","weight":1.0,"input":"text"},
   {"index":2,"type":"multichoice","weight":1.0,"single":true,"layout":"dropdown",
    "options":[{"id":901,"text":"Wiggly worm","images":[]},{"id":902,"text":"Pussy-cat","images":[]},
               {"id":903,"text":"Bow-wow","images":[]}]}]}
```
- Draw `segments`; each `sub` is a small control for `subquestions[index]`:
  - `shortanswer` → text box; `numerical` → number box;
  - `multichoice` → `layout:"dropdown"` dropdown, `"vertical"` / `"horizontal"` radio buttons
    (checkboxes when `single:false`).
- `answer`: `{"1":"Owl","2":902}` — sub `index` → that sub's answer in its own format
  (text, number text, option id, or `[ids]`). Leave out parts not answered.

### 4.15 `description`

```json
{"type":"description","text":"Read the passage below and answer questions 5–8.","informational":true}
```
Just show the text. There is nothing to answer — do not call `save_quiz_answer`.

---

## 5. Results and review

### 5.1 Result (`finish_quiz_attempt` / `submit_quiz_attempt`)

```json
{"attemptid":559,"state":"finished","score":14.0,"max_score":16.0,"percent":87.5,
 "results":[
   {"slot":1,"questionid":531,"type":"multichoice","mark":1.0,"max_mark":1.0,
    "correct":true,"state":"gradedright","needs_grading":false},
   {"slot":7,"questionid":580,"type":"essay","mark":null,"max_mark":1.0,
    "correct":false,"state":"needsgrading","needs_grading":true}]}
```
`score` is the raw total of marks (out of `max_score`). While an essay waits for the teacher,
`score` does not include it yet.

### 5.2 Review (`get_quiz_attempt`)

```json
{"attemptid":559,"quizid":4,"quiz_name":"…","attempt_number":1,"state":"finished",
 "timestart":1760865600,"timefinish":1760866200,"score":14.0,"max_score":16.0,"percent":87.5,
 "questions":[
   {"slot":1,"questionid":531,"type":"multichoice","text":"…","single":true,"options":[…],
    "max_mark":1.0,"supported":true,
    "response":533,"response_summary":"Three","state":"gradedwrong",
    "mark":0.0,"correct":false,"right_answer":"One","correct_response":531}]}
```
- Each question has the **same shape as in §4** — reuse the same widgets, read-only — plus:
  - `response` — what the student answered (app format), `response_summary` — the same as text;
  - `state` — §7.2;
  - `mark`, `correct`, `right_answer` (text), `correct_response` (the right answer in the app format,
    to highlight it) — **only when the teacher's quiz "Review options" allow it**. A field the quiz
    hides is simply missing; `score`/`percent` are `null` when the quiz hides marks.
- Multiple-choice / true-false also keep the older `correct_answers[{id,text,images,correct}]` list.

---

## 6. Images

All image URLs (`images[]`, `background.url`, drag-item `image`) are served by
`/local/academy/qfile.php?…&token=…` and **load directly** (the token is already in the URL). Students
can only load images of questions in their own courses or attempts.

---

## 7. Reference

### 7.1 Error codes

| errorcode | When | App action |
|---|---|---|
| `invalidquizanswer` | the answer does not fit the question: unknown option/gap/stem, a choice from another gap group, an ordering missing an item, a marker placed more than `limit`, wrong JSON shape | app bug — `error` says which part (e.g. `ddwtos: choice 9 does not fit gap 1`) |
| `notenrolled` | not enrolled in the quiz's course | show `error` |
| `attemptsexhausted` | no attempts left | show `error`, open the last review |
| `attemptalreadyclosed` | saving/finishing an attempt that is already finished | open the review |
| `notyourattempt` | the attempt belongs to someone else | app bug |
| `invalidquestionid` | `questionid` is not in this attempt | reload `get_attempt_questions` |
| `postrequired` | a write sent as GET | app bug |

### 7.2 Question states

| `state` | Meaning |
|---|---|
| `todo` / `complete` / `invalid` | (during the attempt) not answered / answered / incomplete |
| `gradedright` | fully right |
| `gradedpartial` | partly right (e.g. 80% for "toad") |
| `gradedwrong` | wrong |
| `gaveup` | not answered when the attempt was finished |
| `needsgrading` | essay waiting for the teacher |

### 7.3 Checklist

- [ ] Draw from `get_attempt_questions`, in `slot` order; options/items in the order received.
- [ ] `supported:false` (or an unknown `type`) → open the quiz in the webview.
- [ ] Before `start_quiz_attempt`, continue an `inprogress` attempt from `get_my_quiz_attempts`.
- [ ] `save_quiz_answer` on every change; `answer` is a JSON string; `null` clears.
- [ ] On reopen, prefill every question from its `response`.
- [ ] Gap types and Cloze: draw `segments`, not `text`.
- [ ] Drag-onto-image / markers: scale by `shownWidth / background.width`; send background pixels.
- [ ] Countdown from `timestart` + `timelimit`; finish at 0.
- [ ] Review: show only the fields present (the quiz decides what students may see).
