# App-layer encryption — `\local_nit_core\helper\crypto`

A tiny, reusable helper to encrypt sensitive values **inside the database** (ciphertext even in a
`mysqldump`), with the master key kept in `config.php` — never in the DB. Use it for small secrets
you don't search/sort on. For everything else use DB-level TDE / disk encryption (see the project
`ENCRYPTION.md`).

## 1. One-time setup — put the key in `config.php`

Generate a 32-byte key and add it to **each environment's** `config.php` (it's a protected,
non-DB file, so the key never lands in the database or a dump):

```bash
php -r 'echo "base64:".base64_encode(random_bytes(32)),"\n";'
```
```php
// config.php (above  require_once(__DIR__ . '/lib/setup.php');)
$CFG->nit_crypto_key = 'base64:XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX=';
```

> ⚠️ **If you lose this key, the encrypted columns are unrecoverable.** Store it in your secrets
> manager / Vault alongside the Kopia backup password. Use a **different** key per project.
> Algorithm: XChaCha20-Poly1305 (AEAD) via libsodium, AES-256-GCM fallback.

## 2. Encrypt / decrypt in code

```php
use local_nit_core\helper\crypto;

// Encrypt before storing. The 2nd arg (context) binds the ciphertext to a purpose.
$record->card_info = crypto::encrypt(json_encode($cardinfo), 'local_payments.card_info');

// Decrypt on read (same context).
$cardinfo = json_decode(crypto::decrypt($record->card_info, 'local_payments.card_info'), true);
```

- **Null-safe:** `encrypt(null) === null`, `decrypt(null) === null`.
- **Gradual migration:** `decrypt()` returns any value that isn't one of our ciphertexts unchanged,
  so existing plaintext rows keep working — you can encrypt new writes immediately and backfill old
  rows later.
- **Tamper-proof:** decryption fails (throws) if the data, key, or context is wrong (authenticated
  encryption).

## 3. Already wired: `local_payments`

**Write side:** the webhook writer (`local_payments\manager::handle_webhook`) encrypts
`card_info`, `source_of_funds`, `signature_keys` using the shared field→context map
`manager::ENCRYPTED_WEBHOOK_FIELDS`.

**Read side (for displays/reports):** don't read the raw columns — use the accessors, which decrypt
with the correct context and pass legacy plaintext through unchanged:

```php
use local_payments\manager;

// one record for display
$webhook = manager::get_webhook($id);                 // loads + decrypts
$card    = json_decode($webhook->card_info, true);    // back to an array, as before

// an existing record / list
manager::decrypt_webhook($record);                    // decrypts in place
manager::decrypt_webhooks($records);                  // array of records
```

So any present or future webhook display calls `get_webhook()` / `decrypt_webhook()` and gets
plaintext; nothing reads the ciphertext columns directly.

> Backfill existing rows once (run via `php admin/cli/...` or a one-off script):
> ```php
> foreach ($DB->get_records_select('local_payments_webhooks', "card_info IS NOT NULL") as $w) {
>     if (!crypto::is_encrypted($w->card_info)) {
>         $DB->set_field('local_payments_webhooks', 'card_info',
>             crypto::encrypt($w->card_info, 'local_payments.card_info'), ['id' => $w->id]);
>     }
> }
> ```

## 4. Secrets that live in plugin config (API keys, etc.)

Different projects enable different plugins (`vdocipher`, `vimeo`, `nit_ai`, payment providers, SMTP…),
so rather than encrypt each plugin's settings table individually, use **one** of these — both keep
the secret out of a plain DB dump:

**A) Keep it in `config.php` (simplest, strongest — not in the DB at all).** Moodle's
`forced_plugin_settings` sets a plugin setting from `config.php`; it overrides the DB and is
read-only in the admin UI:
```php
// config.php
$CFG->forced_plugin_settings['vdocipher']['apisecret'] = 'sk_live_xxx';
$CFG->forced_plugin_settings['vimeo']['accesstoken']   = 'xxxx';
```

**B) Or store it encrypted in the DB** (when a setting must be editable in the UI): save it with
`crypto::encrypt()` and read it back with the uniform accessor, which also honours option A:
```php
$secret = crypto::config_secret('vdocipher', 'apisecret');
// → returns the config.php forced value if set, else the DB value (decrypted if encrypted).
```

So each project just sets the key once in `config.php`, puts its hottest API secrets in
`forced_plugin_settings`, and anything sensitive that still lives in the DB goes through `crypto`.

## 5. What NOT to encrypt with this

- Core PII that Moodle searches/sorts (`mdl_user.email`, `idnumber`, …) → breaks Moodle; use TDE.
- Password **hashes** (`mdl_user.password`) → already one-way; never encrypt.
- Large/queried blobs (full webhook `payload` used for signature re-checks) → leave as-is unless you
  have a specific reason; encrypting breaks any code that parses them without decrypting.
