# Encryption at Rest — DB, Uploads, and Sensitive Columns

How to protect this Moodle's data at rest: **(1)** transparent database encryption (TDE), **(2)**
application/column-level encryption for specific secrets, and **(3)** encrypting uploaded files via
object storage. Plus an **inventory of the sensitive columns** in this codebase (core + our custom
plugins) with a recommended protection for each.

> **The one idea to keep straight:** *where* data is encrypted decides *what* it's protected from.
> - **TDE / disk** = encrypted **on disk only** → protects a **stolen disk / VM / backup**. The live
>   DB still serves plaintext to anyone with the password.
> - **Column / app-level** = encrypted **inside the database** → protects even against a stolen DB
>   password, a dump, or a nosy DBA — but you can't search/sort the field and it needs code.

---

## 1. MariaDB TDE (Transparent Data Encryption) — "the DB encrypts its own files"

The database scrambles its data files on disk (`*.ibd`, redo/undo logs, temp, binlog) using a key,
and unscrambles on read. **Moodle changes nothing** — it's pure DB configuration.

- **Protects:** stolen disk, VM snapshot, raw DB files, a filesystem copy.
- **Does NOT protect:** a running DB. DB password / SQL-injection / server root → plaintext, because
  the engine decrypts for the app.

### Enable it (MariaDB 11.4, `file_key_management` plugin)

```bash
# 1) make a key (guard it — losing it = unreadable DB)
mkdir -p /etc/mysql/encryption
echo "1;$(openssl rand -hex 32)" > /etc/mysql/encryption/keyfile   # "<key-id>;<256-bit key hex>"
chmod 600 /etc/mysql/encryption/keyfile
```
```ini
# 2) my.cnf (add to the mounted mariadb-saas.cnf or a conf.d file)
[mariadb]
plugin_load_add               = file_key_management
file_key_management_filename  = /etc/mysql/encryption/keyfile
file_key_management_encryption_algorithm = AES_CTR
innodb_encrypt_tables         = ON
innodb_encrypt_log            = ON
innodb_encrypt_temporary_tables = ON
encrypt_tmp_disk_tables       = ON
encrypt_binlog                = ON
aria_encrypt_tables           = ON
innodb_encryption_threads     = 4
```
- **Docker:** mount the keyfile + cnf into the `mariadb` container, then restart it. Existing tables
  are encrypted in the background by the encryption threads; new ones inherit automatically.
- ⚠️ **Do it on a clone first, in a maintenance window** (it restarts the DB and rewrites
  tablespaces). Store the keyfile **off the data disk** (a KMS/Vault in production) — a key sitting
  on the stolen disk defeats the purpose.

---

## 2. Application / column-level encryption — "ciphertext even inside the DB"

The **application** encrypts specific fields *before* they're stored, with a key the DB never sees.
The DB holds ciphertext, so even a `mysqldump` or a DBA can't read those fields.

- **Protects:** stolen disk **and** stolen DB password **and** SQL-injection reads **and** insider.
- **Costs:** you **can't search/sort/index** the field; every read/write needs encrypt/decrypt
  **code**; **Moodle core doesn't support it** — so use it only for fields in **our own plugins**
  that we control and rarely query by value.

**Where it's realistic here:** the handful of true secrets/financial fields in our custom plugins
(see the inventory). Encrypt them in the plugin's own save/load code using a key read from
`config.php`/env (never stored in the DB). Do **not** try to column-encrypt core Moodle fields
(email, idnumber, …) — core, reporting and search depend on them; use TDE + access control instead.

> `mdl_user.password` is **already a one-way hash** (bcrypt + pepper) — it is *not* reversible and
> must **never** be "encrypted". Leave it alone.

---

## 3. Encrypting uploaded files (moodledata) — object storage + SSE

Moodle has no built-in filedir encryption. The clean path is the **`tool_objectfs`** plugin, which
offloads `moodledata/filedir` to S3-compatible storage (MinIO), where the storage encrypts at rest.

- **No core code changes.** Install the `tool_objectfs` plugin, then configure everything from
  **Site administration → Plugins → Admin tools → Object storage file system**: endpoint, bucket,
  access key/secret, region, and behaviour (copy / keep-local / delete-local, size threshold).
  *(For our baked SaaS image, the plugin files are added to the image once; after that it's all the
  admin UI.)*
- **Sharing an existing MinIO is fine — use a separate bucket.** Object storage isolates by bucket,
  so Moodle uses its own bucket (e.g. `moodle-filedir`) with its own **scoped** access key, in the
  *same* MinIO as another app. They don't collide. (Give Moodle a key that can touch only its
  bucket.)
- **The encryption comes from MinIO**, not Moodle: enable **server-side encryption (SSE-S3 /
  SSE-KMS) on the bucket**, and everything Moodle writes there is encrypted at rest. Moodle just
  writes to the bucket.
- **Bonus:** this also gets uploads **off the server** (helps the DR / off-site story).
- **Trade-off of a shared MinIO:** it's a shared dependency (if it's down, both apps lose file
  access) and shared capacity — consider a dedicated bucket + key at minimum, or a dedicated MinIO
  for production.

**Alternative (simplest blanket cover):** **LUKS full-disk encryption** on the volumes holding
`/var/lib/mysql`, `moodledata`, and the backup staging dir. One move, encrypts DB + files + dumps at
rest against a stolen disk, zero Moodle impact. Often the best first step.

---

## 4. Sensitive-column inventory for THIS Moodle

Scanned from the schema (`db/install.xml`) across core and our custom plugins. "Protection" is the
*recommended* approach given Moodle's constraints.

### 🔴 Secrets / tokens / financial — strongest candidates for column-level (we control these)

| Table.Column | What it is | Protection |
|---|---|---|
| `local_payments_webhooks.card_info` | payment-method / card details | **Column-level** — or better, **don't store card data at all** (keep only gateway tokens); PCI concern |
| `local_payments_webhooks.signature_keys` | webhook signature secrets | **Column-level** |
| `local_payments_transactions.idempotency_key` | payment idempotency key | Column-level or TDE |
| `local_payments_providers.*` | payment provider API keys/secrets | **Column-level** (or keep keys in config, not DB) |
| `academy_password_otps.resettoken` (+ OTP code) | password-reset OTP / token | **Column-level** or hash it; short TTL |
| `local_nit_devices.tokenid`, `deviceid`, `ip` | device/session identifiers + IP | TDE (column-level if you want extra) |
| `mdl_tool_mfa_secrets.secret` | 2FA / TOTP seeds | TDE (core table — don't code-encrypt) |
| `mdl_external_tokens.token`, `mdl_user_private_key.value` | web-service / API tokens | TDE + rotate + scope |
| `mdl_oauth2_issuer.clientsecret`, `mdl_oauth2_*token.token/refreshtoken` | OAuth2 client secrets / tokens | TDE |
| `mdl_lti*.secret/consumersecret/password`, `mdl_lti_access_tokens.token` | LTI shared secrets/tokens | TDE |
| `mdl_user.secret`, `mdl_user_password_resets.token` | email-confirm / reset tokens | TDE (short-lived) |

### 🟠 Config secrets (stored in `mdl_config_plugins` as name/value, not dedicated columns)

API keys/secrets for **vdocipher**, **vimeo**, **nit_ai**, **nit_commerce / nit_finance /
payments** providers, **SMTP password**, reCAPTCHA secret, registration-hub secret, etc.
→ **Protection:** TDE covers them at rest; prefer keeping the most sensitive in **`config.php` /
environment** rather than the DB where practical. These are secrets — rotate on exposure.

### 🟡 Personal data (PII) — rely on TDE + access control + GDPR tools, NOT column-level

| Columns | Why not column-encrypt |
|---|---|
| `mdl_user`: `email`, `phone1`, `phone2`, `address`, `city`, `country`, `idnumber`, `institution`, `department`, `firstnamephonetic`, `lastnamephonetic`, `lastip` | core + reporting + search + login flows depend on them; encrypting breaks Moodle |
| `mdl_user_info_data` (custom profile fields — may hold national ID, DOB, etc.) | queried/displayed by core profile code |
| `mdl_logstore_standard_log.ip` | IP is PII (GDPR); huge table, needed for reporting |
| `mdl_sessions.sid`, `mdl_messageinbound_datakeys.datakey` | session/runtime data |

For PII, use **TDE/disk encryption** + strong DB access control + Moodle's **GDPR/dataprivacy**
tools (anonymisation, retention) rather than field encryption.

### ⚪ Activity "passwords" — low sensitivity (access gates, not credentials)

`mdl_quiz.password`, `mdl_lesson.password`, `mdl_bigbluebuttonbn.guestpassword`,
`mdl_jitsi.roompassword`, `mdl_quizaccess_seb.quitpassword` → TDE is plenty; not worth column
encryption.

### ✅ Already protected — never "encrypt"

`mdl_user.password` and other password **hashes** (`mdl_user_password_history`) are one-way hashed
(bcrypt + `$CFG->passwordpeppers`). They're already irreversible — encrypting them would break
authentication.

---

## 5. Recommendation for this platform

1. **LUKS full-disk encryption** on the data volumes (DB + moodledata + backup staging) — the
   simplest broad protection against a stolen disk/VM; near-zero Moodle impact. **Do this first.**
2. **MariaDB TDE** on top if you need per-DB encryption independent of the host disk (multi-tenant
   isolation, or a disk you don't control) — §1.
3. **`tool_objectfs` → MinIO (separate bucket, SSE on)** for uploads if you also want offload +
   off-site — §3. No Moodle code change; admin-UI config.
4. **Column-level encryption only for the 🔴 secrets in our own plugins** (`local_payments`
   `card_info`/`signature_keys`, provider keys, `academy_password_otps`) — because we own that code
   and rarely query those by value. Keep the key in `config.php`/env, never in the DB.
5. Leave core PII and password hashes to **TDE + access control + GDPR tools**.

## 6. Key management (the part that actually matters)

- You now have up to three critical keys: the **Kopia backup password**, the **DB TDE keyfile**, and
  any **app-level encryption key**. **Losing any one makes that data unrecoverable.**
- Store them **off the server they protect** (a password manager / KMS / Vault), documented, with a
  recovery owner.
- Backups are already encrypted by Kopia (AES-256). Note a `mysqldump` is **plaintext** in staging —
  keep staging on an encrypted volume (LUKS) and rely on Kopia encrypting the stored snapshot.
- **Rotate** any secret that's ever been exposed (tokens, SMTP/app passwords, DB passwords).
