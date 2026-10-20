# Flash Card Set — Mobile API

Everything the app needs to play a **Flash Card Set** activity (`mod_flashcard`) natively: the deck
table, a Leitner round, free play, text / image / sound / video cards, and — for a teacher — the
follow-up report. Last updated **2026-10-20**.

> Server side: plugin `mod_flashcard` (our fork of the Marketplace plugin), web services in
> `public/mod/flashcard/classes/external/`. The platform reference is [`../MOBILE_API.md`](../MOBILE_API.md);
> this file only adds the flash cards.

## Contents

1. [Basics](#1-basics) — URL, token, calling convention, errors
2. [How the activity works](#2-how-the-activity-works) — decks, review times, a round
3. [Typical flows](#3-typical-flows) — open, play a round, free play, teacher
4. [`mod_flashcard_get_flashcard`](#4-mod_flashcard_get_flashcard--open-the-activity) — open the activity
5. [`mod_flashcard_get_cards`](#5-mod_flashcard_get_cards--the-cards-to-play) — the cards to play
6. [`mod_flashcard_submit_answer`](#6-mod_flashcard_submit_answer--i-got-it--i-missed) — "I got it" / "I missed"
7. [`mod_flashcard_get_report`](#7-mod_flashcard_get_report--teacher-follow-up) — teacher follow-up
8. [`mod_flashcard_reset_student`](#8-mod_flashcard_reset_student--start-a-student-over) — start a student over
9. [Drawing a card](#9-drawing-a-card) — faces, media files, flip
10. [Reference](#10-reference) — error codes, checklist

---

## 1. Basics

### 1.1 Base URL and token

- Base URL: the academy's site, e.g. `https://academy2026.nitg-eg.com/jitsi_life`
  (local development: `http://localhost:8083`).
- **Token: the normal user token the app already has** (`/login/token.php` with
  `service=moodle_mobile_app`, Google sign-in, or `register_student` — see `MOBILE_API.md` §1.2).
  All five functions are in the `moodle_mobile_app` service.
- Progress is per user: the shared pre-login (guest) token does **not** work.

### 1.2 Calling convention (Moodle REST)

Every call is a `POST` (`application/x-www-form-urlencoded`) to:

```
POST /webservice/rest/server.php
```

| Field | Value |
|---|---|
| `wstoken` | the user token |
| `wsfunction` | function name, e.g. `mod_flashcard_get_flashcard` |
| `moodlewsrestformat` | `json` |
| `moodlewssettinglang` | `ar` or `en` — language of deck titles and error messages |
| `moodlewssettingfilter` | `true` — so names typed in both languages come back in one |

```bash
curl -s "$BASE/webservice/rest/server.php" \
  -d "wstoken=$TOKEN" -d "moodlewsrestformat=json" \
  -d "moodlewssettinglang=ar" -d "moodlewssettingfilter=true" \
  -d "wsfunction=mod_flashcard_get_flashcard" -d "cmid=119"
```

`cmid` is the activity's course module id — the `id` of the module in
`core_course_get_contents` (where `modname` is `flashcard`), and the `id=` in
`/mod/flashcard/view.php?id=119`.

### 1.3 Errors

A failed call returns HTTP 200 with:

```json
{"exception": "core\\exception\\moodle_exception", "errorcode": "decklocked",
 "message": "لم يحن موعد مراجعة هذه المجموعة بعد. تفتح مرة أخرى بعد 1 يوم 23 ساعات."}
```

`message` is already in the requested language and can be shown as is. See §10 for the codes.

---

## 2. How the activity works

Read this once — the API is a thin layer over it.

- A set has **2, 3 or 4 decks**. Deck **1** is the hardest ("بطاقات صعبة"), the last deck is
  "mastered". Every card of a student starts in deck 1.
- **"I got it"** moves the card up one deck (it stays put in the last deck).
  **"I missed"** leaves it where it is.
- Every deck has a **review time** set by the teacher, in hours (for example deck 1 = 24 h,
  deck 2 = 72 h, deck 3 = 168 h). After a student answers the cards of a deck, the deck
  **closes** until its review time has passed, then it is **due** again.
  This spacing is the whole point of the Leitner method: hard words come back often,
  known words rarely.
- A deck is in one of three **states**: `due` (play it now), `waiting` (closed, opens at
  `opensat`), `empty` (no card). A student can only play a `due` deck; a teacher
  (`canmanage = true`) can open any deck to preview it.
- **A round** = one pass over the cards a deck holds when the student opens it.
  The **app runs the round** (the server keeps no round state for the app):

  1. Get the deck's cards (they come shuffled).
  2. Show them one by one. The student turns the card over, then answers.
  3. Send each answer with `submit_answer`.
  4. **A missed card goes to the end of the queue, once**: ask it again after the other
     cards. Missed a second time, it leaves the round (it stays in its deck and comes back
     at the deck's next review).
  5. When the queue is empty the round is over: show the summary (what was known, what
     to practise again) and the deck table from the last `submit_answer`.

  This is exactly what the website does, so a student sees the same behaviour on both.
- **Free play** ("مراجعة حرة") is a practice mode without decks or progress: all the cards,
  next / previous / remove, nothing is sent to the server.

---

## 3. Typical flows

### 3.1 Open the activity

```
mod_flashcard_get_flashcard(cmid)
  → name, settings, decks[] = the deck table
```

Draw one tile per deck: title, `count`, and a button that depends on `state`:

| `state` | Tile | Button |
|---|---|---|
| `due` | highlighted | **"ذاكر هذه المجموعة"** → start a round (§3.2) |
| `waiting` | normal | none; show "تفتح بعد …" from `waitseconds` / `opensat` |
| `empty` | dimmed | none |

If `canmanage` is true, also show a "معاينة" button on `waiting` decks and a link to the
report (§3.4). If `freeplay` is true, show a "مراجعة حرة" entry (§3.3); if `leitner` is false,
hide the deck table and offer free play only.

### 3.2 Play a Leitner round

```
mod_flashcard_get_cards(cmid, deck = 1)          → cards[] (shuffled)
for each card in the queue:
    show card.question; on tap, show card.answer
    "عرفتها"    → mod_flashcard_submit_answer(cmid, deck, card.cardid, known = 1)
    "لم أعرفها" → mod_flashcard_submit_answer(cmid, deck, card.cardid, known = 0)
                  first miss of this card in the round → push it to the end of the queue
end → summary screen + the `decks` of the last submit_answer
```

- Send `deck` = the deck you got the cards from (also in `card.deck`), not the deck the card
  moved to.
- If `submit_answer` returns `accepted = false`, the card had already left that deck
  (a double tap, or a retry after a timeout that had in fact succeeded): nothing changed on
  the server; just carry on with the next card.
- If the network drops mid-round, answers already sent are kept. Re-open the activity and
  start again from `get_flashcard`.

### 3.3 Free play

```
mod_flashcard_get_cards(cmid, deck = 0)          → every card, cardid = 0
```

Next / previous / "إزالة البطاقة" (remove the card from this practice) / "إعادة البطاقات"
(start over) — all local; do **not** call `submit_answer`.

### 3.4 Teacher

```
mod_flashcard_get_report(cmid [, groupid])       → students[] + words[]
mod_flashcard_reset_student(cmid, userid)        → after a confirmation dialog
```

---

## 4. `mod_flashcard_get_flashcard` — open the activity

Opens the activity: settings and the caller's deck table. Also **marks the activity viewed**
(completion "view"), so call it when the screen opens. First time for a student, it creates
their cards (all in deck 1, due). If the teacher added words since, they are added to deck 1.

**Parameters**

| Name | Type | |
|---|---|---|
| `cmid` | int | course module id |

**Response**

```json
{
  "cmid": 119, "courseid": 3,
  "name": "مفردات الوحدة الأولى",
  "intro": "<p>…</p>",
  "leitner": true, "freeplay": true,
  "questiontype": "text", "answertype": "sound",
  "flipped": false, "autoplaysound": false,
  "deckcount": 3, "totalcards": 7,
  "opensat": 0, "closesat": 0,
  "canmanage": false,
  "decks": [
    {"deck": 1, "title": "بطاقات صعبة",  "count": 2, "state": "due",     "waitseconds": 0,      "opensat": 0},
    {"deck": 2, "title": "بطاقات متوسطة", "count": 5, "state": "waiting", "waitseconds": 255600, "opensat": 1792770000},
    {"deck": 3, "title": "بطاقات سهلة",  "count": 0, "state": "empty",   "waitseconds": 0,      "opensat": 0}
  ]
}
```

| Field | Meaning |
|---|---|
| `leitner` / `freeplay` | which modes the teacher turned on (`freeplay` also checks the caller may use it) |
| `questiontype` / `answertype` | `text`, `image`, `sound`, `imageandsound`, `video` — the media of the front / back face for **every** card of the set |
| `flipped` | the teacher swapped the faces; **already applied** to every card `get_cards` returns — nothing to do |
| `autoplaysound` | start a face's sound / video as soon as the face is shown |
| `opensat` / `closesat` | activity dates (0 = none). Outside them a student gets `outoftimerange` |
| `canmanage` | the caller is a teacher |
| `decks[]` | the deck table — §3.1 |

---

## 5. `mod_flashcard_get_cards` — the cards to play

**Parameters**

| Name | Type | |
|---|---|---|
| `cmid` | int | course module id |
| `deck` | int | `1`…`deckcount` = a Leitner round on that deck; `0` = free play |

A student asking for a deck that is not `due` gets the error `decklocked` (with the time left in
`message`). A teacher always gets the cards.

**Response**

```json
{
  "deck": 1,
  "cards": [
    {
      "cardid": 10, "entryid": 1, "deck": 1,
      "question": {"type": "text",  "text": "journey", "imageurl": "", "soundurl": "", "videourl": ""},
      "answer":   {"type": "sound", "text": "", "imageurl": "",
                   "soundurl": "https://…/webservice/pluginfile.php/440/mod_flashcard/answersoundfile/6/journey.m4a",
                   "videourl": ""}
    }
  ]
}
```

| Field | Meaning |
|---|---|
| `cardid` | this student's card — send it to `submit_answer`. `0` in free play |
| `entryid` | the word in the set (same for every student) — use it as a stable key / cache key |
| `deck` | the deck the card is in (`0` in free play) |
| `question` / `answer` | the front and back face — §9 |

The list is **shuffled** on every call.

---

## 6. `mod_flashcard_submit_answer` — "I got it" / "I missed"

**Parameters**

| Name | Type | |
|---|---|---|
| `cmid` | int | course module id |
| `deck` | int | the deck being played (`card.deck` from `get_cards`) |
| `cardid` | int | `card.cardid` from `get_cards` |
| `known` | int | `1` = "عرفتها" (I got it), `0` = "لم أعرفها" (I missed) |

**Response**

```json
{
  "accepted": true,
  "deck": 2,
  "decks": [ … same as get_flashcard.decks … ]
}
```

| Field | Meaning |
|---|---|
| `accepted` | `false` = the card was no longer in `deck` (already answered); nothing changed |
| `deck` | where the card is now (`deck + 1` after a known answer, unless it was already in the last deck) |
| `decks` | the updated deck table — use the last one for the end-of-round screen |

Completion ("all cards known" / "all cards viewed", if the teacher turned it on) is updated by
this call.

---

## 7. `mod_flashcard_get_report` — teacher follow-up

Teachers only (`mod/flashcard:manage`; a student gets `nopermissions`). The same data as the
website's **الملخص** page.

**Parameters**

| Name | Type | |
|---|---|---|
| `cmid` | int | course module id |
| `groupid` | int, optional | one group only; `0` (default) = everybody |

**Response**

```json
{
  "students": [
    {
      "userid": 3, "fullname": "Sara", "pictureurl": "https://…",
      "status": "late", "duedecks": [1], "latedays": 3, "nextreviewin": 0,
      "mastered": 1, "total": 3, "percent": 33, "unseen": 0, "lastseen": 1792506916,
      "decks": [{"deck": 1, "count": 2}, {"deck": 2, "count": 1}],
      "hardwords": [{"question": "Water", "answer": "ماء", "tries": 5}]
    }
  ],
  "words": [
    {"question": "Water", "answer": "ماء", "hard": 4, "learning": 2, "mastered": 1, "unseen": 3, "tries": 21}
  ]
}
```

`students` comes with the students who need help first (most hard words). `status` — show it
as a coloured label with the same wording as the website:

| `status` | Label (ar) | Meaning |
|---|---|---|
| `late` | متأخر `latedays` يوم: المجموعة `duedecks` | a deck has waited for review for more than a day — follow this student up |
| `due` | عليه مراجعة الآن: المجموعة `duedecks` | a deck became due less than a day ago |
| `ontrack` | ملتزم: المراجعة القادمة بعد `nextreviewin` | every due review is done |
| `mastered` | أتقن كل الكلمات | every word is in the last deck |
| `notstarted` | لم يبدأ بعد | never answered a card |

`hardwords` = words still in deck 1 **after** the student answered them (a word never answered
is counted in `unseen`, not as hard). `words` comes hardest first; `hard` / `learning` /
`mastered` / `unseen` are numbers of students. A face that is not text has `""` as its text.

---

## 8. `mod_flashcard_reset_student` — start a student over

Teachers only. Deletes one student's progress on this set: their next `get_flashcard` puts every
word back in deck 1. **Cannot be undone** — always ask for confirmation first.

| Name | Type | |
|---|---|---|
| `cmid` | int | course module id |
| `userid` | int | `students[].userid` from the report |

```json
{"reset": true}
```

`false` = the student had no progress.

---

## 9. Drawing a card

Each face is:

```json
{"type": "imageandsound", "text": "", "imageurl": "https://…", "soundurl": "https://…", "videourl": ""}
```

| `type` | Draw |
|---|---|
| `text` | `text` — **HTML** (the teacher's words, possibly with formatting). Render as HTML, centred, large |
| `image` | the image at `imageurl` |
| `sound` | a play / pause control for `soundurl` |
| `imageandsound` | the image, and a play control under it |
| `video` | a video player for `videourl` |

- **Media URLs** are `/webservice/pluginfile.php/…` links: append `?token=<user token>` to load
  them (without it the server answers a JSON error). A URL is `""` when the teacher did not
  upload that file — show a "missing file" placeholder.
- Formats are whatever the teacher uploaded: images jpg / png / gif / webp / svg; sound mp3 /
  m4a / aac / wav / ogg; video mp4 / webm. Use the platform player (AVPlayer / ExoPlayer).
- When `autoplaysound` is true, start the sound / video of a face as soon as it is shown.
  **Always pause the hidden face's media** when the card is turned over or the next card comes.
- A tap on the player controls must not turn the card over.
- Faces are already swapped when `flipped` is true.
- Cache media by URL; `entryid` + face is a stable key across students.

---

## 10. Reference

### Error codes

| `errorcode` | When | What to do |
|---|---|---|
| `decklocked` | `get_cards` on a deck that is not due (students) | refresh with `get_flashcard`; show the deck as waiting |
| `outoftimerange` | outside the activity's open / close dates (students) | show `message`, leave the activity |
| `errorleitneroff` / `errorfreeplayoff` | the mode is turned off | hide that mode (`leitner` / `freeplay` from `get_flashcard`) |
| `errorupdatecard` | `submit_answer` with a `cardid` that is not the caller's | a bug in the app: always send `cardid` from `get_cards` |
| `nopermissions` | a student calling `get_report` / `reset_student` | show the teacher screens only when `canmanage` |
| `invalidparameter` | `deck` outside `0`…`deckcount` | |
| `requireloginerror` / `invalidtoken` | the token is missing, expired or not enrolled | the usual login flow (`MOBILE_API.md`) |

### Checklist

- [ ] Call `get_flashcard` when the activity opens (it records the view for completion).
- [ ] Show `due` decks with a button, `waiting` decks with their opening time, no button.
- [ ] Run the round in the app: shuffled list, a missed card goes to the end **once**.
- [ ] Send one `submit_answer` per answer, with the deck the cards came from.
- [ ] Ignore `accepted = false` (already answered).
- [ ] End-of-round screen: known words, words to practise again, the last `decks`.
- [ ] Free play never calls `submit_answer`.
- [ ] Media: append `?token=`, pause the hidden face, do not flip on a control tap.
- [ ] Teacher screens only when `canmanage`; confirm before `reset_student`.
