# local_googleauth — Native Google Sign-In for the Mobile App

Exchange a **Google ID token** (from native Google Sign-In in your Flutter app) for a
**Moodle web service token**, with no browser popup. The app then calls the normal Moodle
REST web services with that token.

```
Flutter app ──(native Google Sign-In)──▶ Google ──(ID token)──▶ Flutter app
Flutter app ──POST idtoken──▶ /local/googleauth/token.php ──▶ { token }
Flutter app ──wstoken──▶ /webservice/rest/server.php ──▶ Moodle data
```

- **Site:** `https://academy2026.nitg-eg.com/moodle-new`
- **Endpoint:** `POST /local/googleauth/token.php`
- **Web service used:** `moodle_mobile_app` (built-in)

---

## 1. The API contract

### Request
```
POST https://academy2026.nitg-eg.com/moodle-new/local/googleauth/token.php
Content-Type: application/x-www-form-urlencoded

idtoken=<GOOGLE_ID_TOKEN_JWT>     # required
service=moodle_mobile_app         # optional (this is the default)
```

### Success — HTTP 200
```json
{
  "token": "2b8f9c...e1",
  "privatetoken": "a91...f0",
  "userid": 42
}
```

### Failure — HTTP 4xx/5xx
```json
{ "error": "invalid_idtoken" }
```

| HTTP | `error` | Meaning |
|------|---------|---------|
| 405 | `method_not_allowed` | Use POST, not GET |
| 503 | `webservices_disabled` | Enable web services on the site |
| 503 | `plugin_disabled` | Turn the plugin on in settings |
| 503 | `no_clientids_configured` | Add at least one client ID in settings |
| 401 | `invalid_idtoken` | Google rejected the token (fake/expired/malformed) |
| 401 | `invalid_issuer` | `iss` is not Google |
| 401 | `invalid_audience` | Token's `aud` is not in the allowed client IDs ← **most common** |
| 401 | `token_expired` | ID token past its `exp` |
| 401 | `email_not_verified` | Google account email not verified |
| 403 | `domain_not_allowed` | Email domain not in the allow-list |
| 404 | `user_not_found` | No matching user and auto-create is off |
| 409 | `email_not_unique` | More than one Moodle user shares that email |
| 403 | `user_suspended` / `guest_not_allowed` | Account cannot log in |
| 404 | `service_not_available` | `moodle_mobile_app` service disabled |

### Using the token afterwards
```
GET https://academy2026.nitg-eg.com/moodle-new/webservice/rest/server.php
    ?wstoken=<TOKEN>
    &wsfunction=core_webservice_get_site_info
    &moodlewsrestformat=json
```

---

## 2. Google Cloud setup (native)

The plugin only accepts ID tokens whose **`aud` claim** matches a configured client ID.
For native sign-in you must request the ID token using a **Web (server) client ID** so the
`aud` is predictable.

1. In [Google Cloud Console](https://console.cloud.google.com) → **APIs & Services → Credentials**:
   - **Web application** client → this is your `serverClientId`. Its ID is what the plugin must trust.
   - **Android** client → package name + SHA-1 fingerprint (debug *and* release).
   - **iOS** client → bundle ID.
2. Configure the OAuth **consent screen** and **publish** it (in Testing mode only listed test users can sign in).
3. In Moodle → **Site administration → Plugins → Local plugins → Google mobile authentication**,
   put the **Web client ID** in *Allowed Google client IDs* (comma-separate multiple if needed).

> Currently configured client ID:
> `31169484251-9k6rrj28s9v6qkgpnltbetibp4dta3m5.apps.googleusercontent.com`

---

## 3. Flutter integration

### Dependencies (`pubspec.yaml`)
```yaml
dependencies:
  google_sign_in: ^6.2.1     # see note below if you are on v7+
  http: ^1.2.0
  flutter_secure_storage: ^9.2.2   # to store the Moodle token safely
```

### Platform config
- **Android:** put your Web + Android client IDs in Google Console (SHA-1 required). No `google-services.json` is strictly needed for ID-token-only sign-in, but add it if you use other Firebase/Google services.
- **iOS:** add the iOS client's reversed client ID to `Info.plist` URL types, and set `GIDClientID`.

### Sign-in + token exchange
```dart
import 'dart:convert';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

const moodleBase = 'https://academy2026.nitg-eg.com/moodle-new';
// Use your WEB client ID here so the ID token's `aud` matches the plugin config.
const webClientId =
    '31169484251-9k6rrj28s9v6qkgpnltbetibp4dta3m5.apps.googleusercontent.com';

final _storage = const FlutterSecureStorage();

Future<String> signInWithGoogle() async {
  // 1) Native Google Sign-In → ID token
  final googleSignIn = GoogleSignIn(
    serverClientId: webClientId,       // <-- makes aud == webClientId
    scopes: ['email', 'profile', 'openid'],
  );
  final account = await googleSignIn.signIn();
  if (account == null) {
    throw Exception('Sign-in cancelled');
  }
  final auth = await account.authentication;
  final idToken = auth.idToken;
  if (idToken == null) {
    throw Exception('No ID token returned');
  }

  // 2) Exchange the ID token for a Moodle web service token
  final res = await http.post(
    Uri.parse('$moodleBase/local/googleauth/token.php'),
    body: {'idtoken': idToken, 'service': 'moodle_mobile_app'},
  );
  final data = jsonDecode(res.body) as Map<String, dynamic>;
  if (res.statusCode != 200 || data['token'] == null) {
    throw Exception('Moodle auth failed: ${data['error'] ?? res.statusCode}');
  }

  final moodleToken = data['token'] as String;
  await _storage.write(key: 'moodle_token', value: moodleToken);
  return moodleToken;
}
```

### Calling a web service
```dart
Future<Map<String, dynamic>> callWs(String wsfunction,
    [Map<String, String> params = const {}]) async {
  final token = await _storage.read(key: 'moodle_token');
  final uri = Uri.parse('$moodleBase/webservice/rest/server.php').replace(
    queryParameters: {
      'wstoken': token!,
      'wsfunction': wsfunction,
      'moodlewsrestformat': 'json',
      ...params,
    },
  );
  final res = await http.get(uri);
  return jsonDecode(res.body) as Map<String, dynamic>;
}

// Example:
final siteInfo = await callWs('core_webservice_get_site_info');
```

### Sign-out
```dart
Future<void> signOut() async {
  await GoogleSignIn().signOut();
  await _storage.delete(key: 'moodle_token');
  // Optional: also invalidate server-side under
  // Site admin → Server → Web services → Manage tokens.
}
```

> **google_sign_in v7+ note:** v7 renamed the flow (`GoogleSignIn.instance`, `initialize()`,
> `authenticate()`, and ID token via `authentication.idToken`). The exchange step (POST to
> `token.php`) is identical — only the native sign-in call changes. Pin `^6.x` to use the code
> above verbatim.

---

## 4. Plugin settings reference

**Site administration → Plugins → Local plugins → Google mobile authentication**

| Setting | Key | Notes |
|---|---|---|
| Enable endpoint | `local_googleauth/enabled` | Master on/off |
| Allowed Google client IDs | `local_googleauth/clientids` | Comma-separated `aud` values to accept |
| Auto-create users | `local_googleauth/allowcreate` | On = unknown verified emails get a new account |
| Auth method for new users | `local_googleauth/newuserauth` | e.g. `oauth2` or `manual` |
| Restrict to email domains | `local_googleauth/restrictdomain` | e.g. `nitg-eg.com` (empty = any) |

Prerequisite (already on): **Enable web services** + **mobile service** enabled site-wide.

---

## 5. Deploying / updating on the server

The plugin lives at `public/local/googleauth/` in the repo. The server bind-mounts the repo
into the container (`./:/var/www/html`), so uploading the files and running the DB upgrade is all
that's needed.

### A) If deploying via git (preferred)
```bash
# on the server
cd /var/www/html/moodle-new-version
git pull
docker compose exec -T moodle php admin/cli/upgrade.php --non-interactive
docker compose exec -T moodle php admin/cli/purge_caches.php
```

### B) If uploading files directly (Windows / PuTTY)
```bash
# from your machine, in the repo root
pscp -r public/local/googleauth root@157.180.118.100:/var/www/html/moodle-new-version/public/local/
```
```bash
# then on the server
cd /var/www/html/moodle-new-version
docker compose exec -T moodle php admin/cli/upgrade.php --non-interactive
docker compose exec -T moodle php admin/cli/purge_caches.php
```

### C) Configure (once), via CLI
```bash
docker compose exec -T moodle php admin/cli/cfg.php --component=local_googleauth --name=enabled --set=1
docker compose exec -T moodle php admin/cli/cfg.php --component=local_googleauth --name=clientids --set="31169484251-9k6rrj28s9v6qkgpnltbetibp4dta3m5.apps.googleusercontent.com"
docker compose exec -T moodle php admin/cli/cfg.php --component=local_googleauth --name=allowcreate --set=1
```
…or just use the settings page in the admin UI.

### Verify
```bash
# GET should return method_not_allowed; POST with a fake token should return invalid_idtoken
curl -sS -X POST -d 'idtoken=fake' https://academy2026.nitg-eg.com/moodle-new/local/googleauth/token.php
```

### Local development
Same files (already in the repo). Bring up local Docker, then run the upgrade command against the
local container and set the same settings in the local admin page (config values live in the DB,
not the repo). Use `http://localhost:8080` as the base URL in the app for local testing.

---

## 6. Security notes

- ID tokens are verified with Google's `tokeninfo` endpoint (one HTTPS call per login). For very
  high volume, swap to local JWT signature verification against Google's JWKS.
- Always call over **HTTPS** (the `privatetoken` is only returned on HTTPS to non-admins).
- Decide deliberately on **auto-create** and **domain restriction** — with auto-create on and no
  domain restriction, any verified Google account can create a Moodle user.
- The endpoint trusts only the `aud` values you list; keep that list tight.
