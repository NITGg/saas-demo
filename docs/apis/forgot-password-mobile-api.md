# Forgot / Reset / Change Password — Mobile API

OTP-based password reset for the Flutter app, plus change-password for a
logged-in user. All endpoints go through the academy JSON dispatcher:

```
POST /local/academy/api.php?function=<name>
```

Responses are always `{"status":"success","data":{...}}` or
`{"status":"fail","error":"<message>"}`. Show the `error` message to the user.

**Auth:**
- **Forgot-password** endpoints (`request_password_otp`, `verify_password_otp`,
  `reset_password`) are **pre-login** → call them with the shared **Registration
  API token** (the same token the app uses for signup).
- **`change_password`** is **post-login** → call it with the **user's own token**.

Only accounts that sign in with **email/password** can be reset here. Google
(OAuth2) users are rejected (they have no local password).

> Requires working outgoing email (SMTP) — already configured on the server.

---

## Forgot-password flow (3 steps)

```
[App] email ─► request_password_otp ─► Moodle emails a 6-digit code
[App] code  ─► verify_password_otp  ─► returns a single-use resettoken
[App] new pw─► reset_password        ─► password changed, user logs in
```

### 1) `request_password_otp`

Emails a 6-digit code (valid 10 minutes). Always returns a generic success —
it never reveals whether the email belongs to an account.

| Param | Type | Notes |
|-------|------|-------|
| `token` | string | Registration API token |
| `email` | string | the account's email |

Method: **POST**

```json
{ "status": "success", "data": { "sent": true, "expiresin": 600 } }
```

Errors (`status:"fail"`): `Please enter a valid email address.` ·
`Too many code requests. Please wait a few minutes and try again.` (max 3 per 15 min).

### 2) `verify_password_otp`

Checks the code. Returns a single-use **`resettoken`** (valid 10 minutes) used in
step 3. Max **5** wrong attempts before the code locks.

| Param | Type | Notes |
|-------|------|-------|
| `token` | string | Registration API token |
| `email` | string | same email |
| `otp` | string | the 6-digit code the user typed |

```json
{ "status": "success", "data": { "resettoken": "a1b2c3…", "expiresin": 600 } }
```

Errors: `The code you entered is incorrect.` · `This code has expired. Please
request a new one.` · `Too many incorrect attempts. Please request a new code.`

### 3) `reset_password`

Sets the new password using the verified `resettoken`.

| Param | Type | Notes |
|-------|------|-------|
| `token` | string | Registration API token |
| `resettoken` | string | from step 2 |
| `newpassword` | string | the new password (must meet the site policy) |

```json
{ "status": "success", "data": { "reset": true } }
```

Errors: `Your reset session has expired. Please start again.` ·
`The new password does not meet the requirements.` (the message includes the
specific policy rule that failed).

After this succeeds, the user logs in normally with the new password.

---

## Change password (logged-in user)

### `change_password`

For a user who is already signed in and knows their current password. Call it
with the **user's own token**.

| Param | Type | Notes |
|-------|------|-------|
| `token` | string | the user's personal token |
| `currentpassword` | string | their existing password |
| `newpassword` | string | the new password (must meet the site policy) |

```json
{ "status": "success", "data": { "changed": true } }
```

Errors: `Your current password is incorrect.` · `The new password does not meet
the requirements.` · `This account cannot change its password here (it signs in
with Google).`

---

## Examples

### curl

```bash
BASE=https://academy2026.nitg-eg.com/moodle-new/local/academy/api.php
REG=<REGISTRATION_API_TOKEN>

# 1) request a code
curl -s -X POST "$BASE?function=request_password_otp" -d "token=$REG&email=student@example.com"

# 2) verify the code the user received by email
curl -s -X POST "$BASE?function=verify_password_otp" -d "token=$REG&email=student@example.com&otp=123456"
# -> {"status":"success","data":{"resettoken":"abc...","expiresin":600}}

# 3) set the new password
curl -s -X POST "$BASE?function=reset_password" -d "token=$REG&resettoken=abc...&newpassword=NewPass456!"

# change password (logged-in user, personal token)
curl -s -X POST "$BASE?function=change_password" -d "token=<USER_TOKEN>&currentpassword=Old&newpassword=NewPass456!"
```

### Dart / Flutter

```dart
const base = 'https://academy2026.nitg-eg.com/moodle-new/local/academy/api.php';

Future<Map<String, dynamic>> _call(String fn, String token, Map<String, String> args) async {
  final res = await http.post(Uri.parse('$base?function=$fn'),
      body: {'token': token, ...args});
  final data = jsonDecode(res.body) as Map<String, dynamic>;
  if (data['status'] != 'success') {
    throw Exception(data['error'] ?? 'Request failed');
  }
  return (data['data'] as Map).cast<String, dynamic>();
}

Future<void> requestOtp(String email) =>
    _call('request_password_otp', kRegistrationToken, {'email': email});

Future<String> verifyOtp(String email, String otp) async =>
    (await _call('verify_password_otp', kRegistrationToken, {'email': email, 'otp': otp}))['resettoken'];

Future<void> resetPassword(String resetToken, String newPassword) =>
    _call('reset_password', kRegistrationToken, {'resettoken': resetToken, 'newpassword': newPassword});

Future<void> changePassword(String userToken, String current, String next) =>
    _call('change_password', userToken, {'currentpassword': current, 'newpassword': next});
```

---

## Security & limits (built in)

- Codes are **hashed at rest** (never stored in plaintext).
- **Expiry:** OTP 10 min; reset token 10 min after verify.
- **Attempt limit:** 5 wrong OTP tries → the code locks (request a new one).
- **Rate limit:** max 3 code requests per email per 15 minutes.
- **No account enumeration:** `request_password_otp` returns the same success
  whether or not the email exists.
- **Single use:** after a successful reset, all of that user's codes/tokens are
  deleted.
- New passwords are validated against the **site password policy**.

---

## Setup checklist (server)

1. `local_academy` upgraded (creates the `academy_password_otps` table).
2. SMTP configured (so the code email sends) — done.
3. The forgot-password endpoints live on `api.php`, authenticated by the shared
   **Registration API** token; `change_password` uses the user's own token.
