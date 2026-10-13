# Encrypted email + username-only login — TEST BRANCH

Branch: `feature/encrypted-email-test`. **Experimental.** This proves that
`mdl_user.email` can be stored encrypted at rest while Moodle keeps working, and that
login can be username-only. It **patches Moodle core** (allowed for this test only — do
not merge to a line where "never edit core" applies without review).

## What it does

- **Email stored encrypted at rest.** `mdl_user.email` holds ciphertext (`ENC1:d.…`). A
  DB dump / stolen password / curious DBA cannot read it.
- **Deterministic encryption** (not the random-nonce mode used for secrets). The same
  email always encrypts to the same ciphertext, so exact-equality lookups still work —
  **forgot-password, duplicate-email detection, admin search by exact value**. It is
  lowercased+trimmed first (emails are case-insensitive). Trade-off: equal emails look
  equal in the DB (equality leakage), and **partial / LIKE / sorted** email search does
  **not** work on the ciphertext.
- **Transparent for the app.** Patched core read paths decrypt email for display and for
  sending mail; patched write paths encrypt on save; patched lookups encrypt the search
  value. Scope is **email only** — names are untouched.
- **Username-only login** via `$CFG->authloginviaemail = 0`.

## Crypto (in the plugin, not core)

`\local_nit_core\helper\crypto`:
- `encrypt_deterministic($plaintext, $context)` / decrypt handles the new `ENC1:d.` algo
  (AES-256-GCM with a **synthetic IV** = HMAC-SHA256(plaintext+context) under a sub-key —
  so identical inputs → identical ciphertext, distinct inputs → distinct IVs, GCM-safe).
- `encrypt_user_email()` / `decrypt_user_email()` / `decrypt_user_email_in($record)` —
  fail-safe wrappers bound to context `core_user.email`. No key configured, legacy
  plaintext, or an unreadable value → returned unchanged (pages never fatal).

## Core files patched (all changes marked `// NIT enc-email (test)`)

| File | Function | Change |
|---|---|---|
| `public/user/lib.php` | `user_create_user`, `user_update_user` | encrypt email before DB write (after validation); restore plaintext on caller |
| `public/user/lib.php` | `user_get_user_details` | decrypt email in output (profile / web services) |
| `public/lib/classes/user.php` | `get_user`, `get_user_by_username`, `get_user_by_idnumber` | decrypt email in result |
| `public/lib/classes/user.php` | `get_user_by_email` | encrypt search value + decrypt result |
| `public/lib/moodlelib.php` | `get_complete_user_data` | encrypt email lookup value; decrypt result (→ `$USER->email`) |
| `public/lib/moodlelib.php` | `email_to_user` | decrypt email (on a clone) before sending |
| `public/lib/datalib.php` | `get_users_listing` | decrypt email per row (admin user list) |
| `public/lib/authlib.php` | `signup_validate_data` | encrypt email for the duplicate-check query |
| `public/login/lib.php` | password-reset lookup | encrypt search value + decrypt result (forgot-password) |
| `public/user/edit.php`, `editadvanced.php` | form load | decrypt email for the edit form |
| `public/user/edit_form.php`, `editadvanced_form.php` | `validation()` | decrypt current email; encrypt the dedupe-query value |

## Deploy & enable (test instance)

1. Deploy the branch image to the **test** instance.
2. `config.php` on that instance:
   ```php
   $CFG->nit_crypto_key = 'base64:…';   // 32-byte key (same mechanism as the rest)
   $CFG->authloginviaemail = 0;          // username-only login
   $CFG->emailchangeconfirmation = 0;    // route email edits through user_update_user (which encrypts)
   ```
3. Upgrade: `php admin/cli/upgrade.php --non-interactive` (nit_core version bump).
4. Backfill existing emails:
   ```
   php local/nit_core/cli/encrypt_user_emails.php            # dry-run
   php local/nit_core/cli/encrypt_user_emails.php --execute  # encrypt
   ```

## Verify

- **DB**: `SELECT id, username, email FROM mdl_user LIMIT 5;` → emails show `ENC1:d.…`.
- **Login**: works with username; email as the login name is rejected.
- **Profile / edit form / admin user list**: show the real (decrypted) email.
- **Forgot password**: entering the email finds the account (deterministic lookup).
- **Outgoing mail**: confirmation / notification emails arrive at the real address.
- **New signup / admin create**: stores encrypted; duplicate email is still detected.

## Known limitations (by design / accepted for the test)

- **No partial/sorted email search.** Admin "filter users where email contains…" and
  sorting by email don't work on ciphertext. Exact-value lookups do.
- **Case/format normalised.** Stored email is lowercased+trimmed, so display is lowercase.
- **Column width.** `mdl_user.email` is `varchar(255)`; deterministic ciphertext of a very
  long email (≈>180 chars) could overflow. Normal emails are fine.
- **Third-party/direct queries.** Any plugin that does `$DB->get_record('user', …)` and
  reads `->email` directly (bypassing the patched choke points) sees ciphertext until it
  routes through `core_user::*` or calls `crypto::decrypt_user_email_in()`.
- **Key loss = unreadable emails.** `$CFG->nit_crypto_key` must be backed up off-box.
- **Core dependency on the plugin.** The patched core calls `\local_nit_core\helper\crypto`
  directly; the plugin must be present in the image (it always is here).

## Revert

`git checkout bassthalk-clone` (this branch is never merged there), or on a running test
instance remove the two `config.php` lines and run the backfill in reverse (not provided —
restore from backup instead).
