# AI assistant ("Ask us") in the app

**For:** the Flutter developer. **Date:** 14/10/2026.
**What:** the same AI assistant as the website's chat button: it answers visitors about courses, plans, prices and
live lessons; answers signed-in students and teachers about **their own** account; and hands the chat to the academy's
team when needed (the team then replies in the same chat). Everything the assistant knows comes from the academy's
own data, and it never takes payments: it gives links (buttons) instead.

Plugin: `local_nit_assistant`. Only the academy can switch it on (it needs an AI key and a daily budget), so **always
ask `get_config` first** and hide the chat when `available` is false.

---

## 1. The endpoint

```
/local/nit_assistant/api.php?function=<name>
```

Same pattern and envelope as our other app APIs (`/local/academy/api.php`, `/local/academysessions/api.php`…):

```json
{"status":"success","data":{ ... }}
{"status":"fail","error":"<readable, ready to show>","errorcode":"<stable code>"}
```

- **HTTP 401** = missing or dead token (`authrequired` / `invalidtoken`) → send the user to login.
- **HTTP 403** = academy suspended/expired (`siteunavailable`).
- Optional on every call: `lang=ar|en` (the greeting's language).
- `send_message` and `new_conversation` must be **POST** (form-encoded). The others accept GET or POST.

### Who is calling: token + device key

| Caller | `token` (or `wstoken`) | `key` |
|---|---|---|
| **Signed-in user** | the user's token from `/login/token.php` | optional, but **send it** (see below) |
| **Visitor** (not signed in) | the shared pre-login `admin_token` from `getsettings.php` | **required** |

`key` is a **device key** the app makes once: 32 random hex characters (letters and digits only, 32–64 long). Store it
in secure storage and send the **same** key on every call. It is the visitor's identity for the chat, like the browser
key on the website. Keep sending it after the user signs in: the visitor's chat then moves to their account (they
don't lose the conversation). Generate a new one on logout if you want the next visitor on that phone to start fresh.

```dart
String newDeviceKey() {
  final r = Random.secure();
  return List.generate(16, (_) => r.nextInt(256).toRadixString(16).padLeft(2, '0')).join(); // 32 hex chars
}
```

The app chat is its own conversation (channel `app`), separate from the website chat of the same user.

---

## 2. Functions

### 2.1 `get_config` — can this user chat, and what to show first

`GET /local/nit_assistant/api.php?function=get_config&token=<token>&lang=ar`

```json
{"status":"success","data":{
  "available": true,          // false → hide the chat entry point
  "signed_in": false,
  "mode": "sales",            // sales (visitor/prospect) | student | teacher — for info only
  "greeting": "أهلًا بيك في بسطتهالك! 👋 …",   // show as the assistant's first bubble (not stored)
  "max_length": 2000,         // longest message accepted (characters)
  "needs_key": true           // true → you must send `key` on the other calls
}}
```

### 2.2 `send_message` — send what the user typed, get the reply

`POST /local/nit_assistant/api.php?function=send_message`
form fields: `token`, `message`, `key` (visitors: required), `lang` (optional), `stream` (optional, `0`/`1`)

**A. Whole reply at once (`stream=0`, default)** — simplest; the call takes as long as the reply (usually 2–15 s,
up to ~60 s when the assistant looks several things up). Use a client timeout of **90 s**.

```json
{"status":"success","data":{
  "outcome": "replied",
  "reply": "We have 5 courses right now:\n\n1. **History 1** …",
  "actions": [ {"label":"See the courses","url":"https://…/local/nit_category/index.php"} ],
  "status": "open",
  "error": "",
  "message_id": 812,          // the stored reply (0 if none)
  "user_message_id": 811      // the stored user message (0 if it was refused)
}}
```

| `outcome` | Meaning | What the app shows |
|---|---|---|
| `replied` | the assistant answered | `reply` as an assistant bubble + `actions` as buttons |
| `handoff` | the assistant passed the chat to the team (`status` = `waiting_human`) | `reply` (it says the team will answer here), then start polling (2.3) |
| `queued` | a person already has this chat; the message went to them, no AI reply | a small note "Your message is with the team"; keep polling |
| `discarded` | a team member took over while the AI was writing; the AI reply was dropped | nothing; keep polling |
| `invalid` | empty or longer than `max_length` | keep the text in the input, show "too long" |
| `full` | the conversation is too long | offer "New conversation" (2.4) |
| `busy` | a reply to the previous message is still being written | "One moment…", let them resend |
| `unavailable` | the assistant is off or out of today's budget | the contact message below |
| `error` | see `error` | see the table below |

| `error` | What the app shows |
|---|---|
| `ratelimited` | "You've sent many messages — please wait a little." |
| `model_unavailable`, `budget_exhausted` | "Sorry, the assistant isn't available right now. Please contact us." (WhatsApp/phone from your app settings) |
| (any other) | a generic "Something went wrong, try again." |

**B. Streamed (`stream=1`)** — the reply appears word by word, like the website. The response is
`text/event-stream` (Server-Sent Events) — **not** the JSON envelope — with these events, in order:

| event | data | do |
|---|---|---|
| `stored` | `{"id":811}` | the user's message was saved |
| `delta` | `{"text":"We have "}` | append to the assistant bubble (many of these) |
| `action` | `{"type":"link","label":"…","url":"…"}` | add a button under the bubble |
| `handoff` | `{"status":"waiting_human","message":"…"}` | the team takes over → start polling |
| `queued` | `{"status":"human"}` | no AI reply; show the "with the team" note, keep polling |
| `done` | `{"messageid":812}` (or `null`) | finished |
| `error` | `{"code":"busy"}` | same codes as above; the stream ends |

Errors before the stream starts (bad token, missing key…) still come as the JSON `fail` envelope — check the
`Content-Type` of the response.

```dart
final req = http.Request('POST', Uri.parse('$site/local/nit_assistant/api.php?function=send_message'))
  ..bodyFields = {'token': token, 'key': deviceKey, 'message': text, 'stream': '1'};
final res = await client.send(req);
if (!(res.headers['content-type'] ?? '').startsWith('text/event-stream')) {
  // JSON fail envelope: read it and show `error`.
}
var event = '', buffer = '';
await for (final chunk in res.stream.transform(utf8.decoder)) {
  buffer += chunk;
  while (buffer.contains('\n\n')) {
    final block = buffer.substring(0, buffer.indexOf('\n\n'));
    buffer = buffer.substring(buffer.indexOf('\n\n') + 2);
    String data = '';
    for (final line in block.split('\n')) {
      if (line.startsWith('event: ')) event = line.substring(7);
      if (line.startsWith('data: ')) data += line.substring(6);
    }
    final json = data.isEmpty ? {} : jsonDecode(data);
    onEvent(event, json); // delta → append json['text'], action → add button, …
  }
}
```

### 2.3 `get_messages` — the history, and the team's replies

`GET /local/nit_assistant/api.php?function=get_messages&token=<token>&key=<key>&after=<last id>`

```json
{"status":"success","data":{
  "status": "human",          // open | waiting_human | human | closed, or "" when there is no chat yet
  "messages": [
    {"id":811,"role":"visitor","text":"what are the courses?","time":1791900000},
    {"id":812,"role":"assistant","text":"We have 5 courses…","time":1791900004},
    {"id":830,"role":"staff","text":"Hi, Mona from the team here…","time":1791900400}
  ]
}}
```

- `role`: `visitor` = the user (right side), `assistant` = the AI, `staff` = a team member (show "Team" above it).
- `after=0` loads the whole chat when the screen opens; then pass the last id you have and append what comes back.
- **Poll** while `status` is `waiting_human` or `human`: every **5 s** while the chat screen is open, every **30 s**
  in the background (show a dot on the chat button when a `staff` message arrives). Stop when it goes back to `open`.
  When `status` is `open` there is nothing to poll for.
- Old messages don't carry their buttons; buttons only come with a fresh reply.

### 2.4 `new_conversation` — start over

`POST /local/nit_assistant/api.php?function=new_conversation` with `token`, `key` → `{"closed": true}`.
Clear the screen and show the greeting again; the next message starts a new chat.

---

## 3. Showing replies

- **Text:** plain text with light Markdown: `**bold**`, lines starting with `- ` (bullets), numbered lines, and
  links (`https://…`, or `[label](https://…)`). A Markdown widget is fine; keep line breaks.
- **Direction:** a reply can be Arabic or English. Set the bubble direction per message (`TextDirection.rtl` when it
  contains Arabic letters).
- **Language:** the assistant answers in the language the user **types** (an English question on the Arabic app gets
  an English answer). `lang` only changes the greeting.
- **Buttons (`actions`):** open `url`. Prefer your native screen when you have one, else an in-app WebView with the
  user's session (autologin), like the other website links:

| URL contains | Native screen (if you have it) |
|---|---|
| `/local/academy/course.php?id=` | course details |
| `/local/payments/buy.php?courseid=` | buy course |
| `/local/nit_lessons/student.php?tab=subavailable` / `mysubs` | plans / my subscription |
| `?tab=flexavailable` / `packages` / `lessons` / `book` / `wallet` / `earnings` / `teaching` | the matching hub tab |
| `/local/nit_lessons/join.php?id=` | join the live lesson |
| `/mod/jitsi/view.php?id=` | join the group class |
| `/local/nit_category/index.php` | course catalogue |
| `/login/signup.php` | sign up |

- Under the chat title show: "AI assistant — it can make mistakes. You can ask for a person at any time."

## 4. Limits

| Limit | Value |
|---|---|
| Message length | `max_length` (2000) characters |
| Messages per person | 30 per hour and 120 per day by default (the academy can change it) → `ratelimited` |
| Replies at a time | one per conversation → `busy` |
| Conversation length | 60 messages → `full` → `new_conversation` |

## 5. Quick test (Postman / curl)

```bash
# 1. Is it on? (visitor: the shared admin_token)
curl "https://<site>/local/nit_assistant/api.php?function=get_config&token=<admin_token>&lang=en"

# 2. Ask something (visitor)
curl -X POST "https://<site>/local/nit_assistant/api.php?function=send_message" \
  -d token=<admin_token> -d key=0123456789abcdef0123456789abcdef -d "message=what courses do you have?"

# 3. The history
curl "https://<site>/local/nit_assistant/api.php?function=get_messages&token=<admin_token>&key=0123456789abcdef0123456789abcdef&after=0"

# Streamed reply (watch it arrive)
curl -N -X POST "https://<site>/local/nit_assistant/api.php?function=send_message" \
  -d token=<user_token> -d stream=1 -d "message=When is my next lesson?"
```

`<site>` is the academy's address, e.g. `https://academy2026.nitg-eg.com/bassthalk`.

---

**Older calls:** the Moodle web-service functions `local_nit_assistant_send_message` and
`local_nit_assistant_get_messages` (via `/webservice/rest/server.php`, signed-in users only) still work, but use the
`api.php` functions above for the app: they also serve visitors, stream, and use the same envelope as our other APIs.
