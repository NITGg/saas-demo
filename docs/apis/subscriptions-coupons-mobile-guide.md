# Subscriptions & Coupons — Mobile API Guide

How the mobile app browses **discount coupons** and **subscription plans**, previews prices, starts a
paid checkout, and shows the user's subscriptions + payment history.

- **Owners:** `local_nit_commerce` (coupons/offers), `local_nit_subscriptions` (plans), `local_payments` (gateway)
- **Auth:** Moodle **web-service token** (the standard mobile flow) — see [§2](#2-authentication)
- **Transport:** `GET`/`POST` to `/webservice/rest/server.php`, JSON responses

> **Coming from the old Academy app?** These are the same capabilities you had on the 2022 build, but
> they are now **standard Moodle web-service functions** instead of the custom `local/academy/api.php`
> endpoints. Names, calling convention and the purchase flow changed — see the
> [migration map in §7](#7-migration-from-the-old-localacademyapiphp). The biggest change: there is
> **no `purchase_subscription`** anymore; payment goes through a real gateway (Kashier).

---

## 1. Base URL

Everything is called through Moodle's REST server:

```
{WWWROOT}/webservice/rest/server.php
```

- Staging/prod: `WWWROOT = https://academy2026.nitg-eg.com/moodle-new`
- Local dev: your host (Android emulator: `http://10.0.2.2:8081`, iOS sim: `http://localhost:8081`)

Don't hard-code the host — read it from your existing site/base-URL config.

---

## 2. Authentication

Get a token once per logged-in user, then send it on every call.

```
POST {WWWROOT}/login/token.php
Content-Type: application/x-www-form-urlencoded

username=STUDENT&password=PASSWORD&service=moodle_mobile_app
→ { "token": "abc123…", "privatetoken": null }
```

Every function acts on the **token's own user** — a student only ever sees/affects their own
subscriptions and payments.

### Calling convention

Send `wstoken`, `wsfunction`, and always ask for JSON with `moodlewsrestformat=json`:

```
GET  {WWWROOT}/webservice/rest/server.php
       ?wstoken=TOKEN
       &wsfunction=FUNCTION_NAME
       &moodlewsrestformat=json
       &param1=value1&param2=value2
```

- **Read** functions may be `GET`; **write** functions (`create_subscription_checkout`) should be `POST`
  with the params in the form body. There is **no `sesskey`** — the token *is* the credential.
- **Language / the `"Invalid parameter value detected"` trap.** Moodle web services **reject any
  parameter a function doesn't declare** — so blindly appending `lang` (or anything else) to *every*
  call breaks it. Two ways to send the display language:
  - ✅ **Recommended — `moodlewssettinglang`.** Send `moodlewssettinglang=ar` (note the prefix). Moodle
    strips it *before* parameter validation and applies it site-wide, so it works on **every** function
    — these custom ones, the `local_payments` ones, **and core Moodle functions** (`core_course_*`,
    `core_enrol_*`, …). Set it once in your shared API client and drop the per-call `lang`.
  - Also accepted — a plain `lang` (alias `alang`, e.g. `en`/`ar`) is declared on all
    `local_nit_*` and `local_payments` functions. But **core Moodle functions do not accept `lang`**,
    so if your client appends `lang` to core calls too, use `moodlewssettinglang` instead.
- On success you get the raw JSON data (an array or object — **no `{status,data}` wrapper**).
- On failure you get a Moodle exception object — see [§6 Errors](#6-error-handling).

> ⚠️ An invalid/expired token returns `{"exception":"moodle_exception","errorcode":"invalidtoken",…}`.
> Treat any `exception` key, or a non-JSON/HTML body, as "session expired → re-login".

---

## 3. Function reference

| # | What | `wsfunction` | Method | Params |
|---|------|--------------|--------|--------|
| 1 | Available coupons | `local_nit_commerce_get_available_coupons` | GET | — |
| 2 | Preview a discounted price | `local_nit_commerce_preview_discount` | GET | `item_type`, `item_id`, `coupon_code?` |
| 3 | Available subscription plans | `local_nit_subscriptions_get_available_subscriptions` | GET | — |
| 4 | Start a subscription checkout | `local_nit_subscriptions_create_subscription_checkout` | **POST** | `subscriptionid`, `type?`, `seats?`, `coupon_code?`, `country?`, `lang?`, `return_url?` |
| 5 | My subscriptions | `local_nit_subscriptions_get_my_subscriptions` | GET | — |
| 6 | My subscription payments | `local_nit_subscriptions_get_subscription_payment_history` | GET | — |

Related **course-purchase** functions ship in `local_payments` (token, same mobile service):
`local_payments_get_course_price`, `local_payments_get_course_access`, `local_payments_get_payment_methods`,
`local_payments_create_checkout`, `local_payments_verify_payment`, `local_payments_get_payment_history`,
`local_payments_get_invoice`, `local_payments_get_purchased_courses`, `local_payments_get_courses_with_pricing`.
They all now accept `lang`/`alang` too. Course-purchase flow: `create_checkout` → open `checkout_url`
→ after the Kashier redirect, call `verify_payment(order_id)` (the browser page `callback.php` is
**web-only** — it uses a session, so the mobile app uses `verify_payment` instead).

> 💸 **Getting the right price on the Kashier screen.** The amount shown by Kashier is the discounted
> amount computed at checkout. To apply a coupon you **must pass `coupon_code`** to the checkout call
> (`create_checkout` for a course, `create_subscription_checkout` for a plan) — auto-offers apply
> without it, coupons do not. If you change the coupon and re-checkout, a fresh Kashier session is
> created at the new price (a stale same-course pending session at a different price is retired
> automatically).

---

### 3.1 `local_nit_commerce_get_available_coupons`

Active, in-window coupons the user can browse.

```
GET …&wsfunction=local_nit_commerce_get_available_coupons&moodlewsrestformat=json
```
```json
[
  {
    "id": 5,
    "code": "WELCOME10",
    "discount_type": "percent",
    "discount_value": 10,
    "max_discount": 100,
    "usage_type": "multiple",
    "usage_limit": 0,
    "startdate": 0,
    "enddate": 1790000000,
    "status": "active",
    "usage_count": 12,
    "applies_to": [
      { "item_type": "subscription", "item_id": 0, "label": "All subscriptions" },
      { "item_type": "course", "item_id": 42, "label": "English A1" }
    ]
  }
]
```
- `discount_type` = `percent` (value 0–100) or `fixed` (an amount).
- `max_discount` caps a percentage discount (may be `null`/absent = no cap).
- `applies_to[].item_id = 0` means **all** items of that `item_type`.
- Don't compute the final price yourself — call `preview_discount` (§3.2) so server rules win.

### 3.2 `local_nit_commerce_preview_discount`

Preview the price of an item with the best automatic **offer** applied, plus an optional **coupon**
on top. **Charges nothing.** Use it on the checkout screen before starting payment.

```
GET …&wsfunction=local_nit_commerce_preview_discount&moodlewsrestformat=json
     &item_type=subscription&item_id=1&coupon_code=WELCOME10
```
| Param | Req | Notes |
|-------|-----|-------|
| `item_type` | ✔ | `course` \| `package` \| `subscription` \| `program` |
| `item_id` | ✔ | the plan/course id |
| `coupon_code` | — | omit or empty to preview the offer-only price |
| `country` | — | ISO-2 (e.g. `EG`) — used only for **course** base pricing; ignored for other types |

```json
{
  "original": 365.00,
  "offers": [ { "id": 3, "name": "Spring sale", "discount": 36.50 } ],
  "offer_id": 3,
  "offer_name": "Spring sale",
  "offer_discount": 36.50,
  "coupon_id": 5,
  "coupon_code": "WELCOME10",
  "coupon_discount": 32.85,
  "discount": 69.35,
  "final": 295.65,
  "coupon_error": ""
}
```
- `final` = what checkout will charge. `discount` = `offer_discount + coupon_discount`.
- **Invalid coupon does not fail the call** — you get the offer-only price and `coupon_error` is filled
  (e.g. `"This coupon is not applicable to this item"`). Show it inline; keep the Buy button enabled.

### 3.3 `local_nit_subscriptions_get_available_subscriptions`

Active plans the user can buy, each with the courses it unlocks.

```
GET …&wsfunction=local_nit_subscriptions_get_available_subscriptions&moodlewsrestformat=json
```
```json
[
  {
    "id": 1,
    "name": "365-day",
    "description": "<p>Full-year access</p>",
    "price": 365.00,
    "duration_days": 365,
    "status": "active",
    "courses": [
      { "id": 12, "fullname": "English A1" },
      { "id": 13, "fullname": "Arabic B1" }
    ],
    "offer": { "original": 365.00, "final": 328.50, "label": "-10%", "name": "Launch Offer" },

    "b2b_enabled": 1,
    "courses_count": 2,
    "seat_options": [
      { "id": 7, "seats": 10, "discount_percent": 15,
        "original_price": 3650.00, "discount_amount": 547.50, "b2b_price": 3102.50 }
    ],
    "offer_label": "-10%",
    "offer_final": 328.50
  }
]
```
- `courses` is an array of **`{id, fullname}` objects** — use `id` to map which catalog courses a plan
  covers (`fullname` may contain `{mlang}` markup).
- `offer` is a **nested object** `{original, final, label, name}` — **present only when there's an
  active offer**, omitted otherwise. Use it for the price badge.
- `status` is `active` (the endpoint only lists active plans).
- Legacy fields `b2b_enabled`, `courses_count`, `seat_options`, `offer_label`, `offer_final` remain for
  backward compatibility — `seat_options` is for **B2B** (team) plans, empty for a normal plan.

### 3.4 `local_nit_subscriptions_create_subscription_checkout` — **POST**

Starts a **payment-gateway** checkout and returns a `checkout_url`. This **replaces the old
`purchase_subscription`**: nothing is granted until the gateway confirms payment.

```
POST {WWWROOT}/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=TOKEN&wsfunction=local_nit_subscriptions_create_subscription_checkout
&moodlewsrestformat=json
&subscriptionid=1&coupon_code=WELCOME10&lang=en
```
| Param | Req | Default | Notes |
|-------|-----|---------|-------|
| `subscriptionid` | ✔ | — | plan id |
| `type` | — | `normal` | `normal` or `b2b` |
| `seats` | — | `0` | required when `type=b2b` (must match a `seat_options` tier) |
| `coupon_code` | — | `''` | applied to a **normal** purchase only |
| `country` | — | user's | ISO-2; affects provider selection |
| `lang` | — | `en` | gateway display language (`en`/`ar`) |
| `return_url` | — | `''` | where the browser should land after payment |

```json
{
  "order_id": "PAY-2026-00481523",
  "checkout_url": "https://checkout.kashier.io/?...",
  "expires_at": 1790401800,
  "provider": "kashier",
  "transaction_id": 91,
  "amount": 359.20,
  "original_amount": 499.00,
  "currency": "EGP"
}
```
**Do:** open `checkout_url` in an in-app browser / WebView. After the user pays and the gateway
redirects back, **poll `get_my_subscriptions`** (§3.5) until the plan shows `status: "active"` — the
subscription is created server-side by the gateway webhook, not by this call.

> 💰 **`amount`** is the actual price charged, **after** offer + coupon — this is exactly what Kashier
> shows. `original_amount` is the pre-discount price (use it for a strikethrough). If `amount` here
> equals the full price, the discount was not applied server-side (the coupon didn't match, or the
> `coupon_code` didn't reach this call) — it is **not** a Kashier display issue. The course
> `local_payments_create_checkout` returns the same three fields and also accepts `coupon_code`.

Common failures (returned as exceptions): `"You already have an active subscription"` (one active
normal plan per user), `"This subscription plan is not available"` (inactive), `"The selected capacity
is not available"` (bad B2B `seats`).

### 3.5 `local_nit_subscriptions_get_my_subscriptions`

The user's subscriptions, **active first**.

```
GET …&wsfunction=local_nit_subscriptions_get_my_subscriptions&moodlewsrestformat=json
```
```json
[
  {
    "id": 4,
    "subscriptionid": 1,
    "name": "365-day",
    "type": "normal",
    "price_paid": 295.65,
    "status": "active",
    "timeactivated": 1790400000,
    "expires_at": 1821936000,
    "remaining_days": 365,
    "duration_days": 365,
    "courses": [
      { "id": 12, "fullname": "English A1" },
      { "id": 13, "fullname": "Arabic B1" }
    ]
  }
]
```
- `status` is computed live: `active` | `expired` | `cancelled`.
- `remaining_days` = whole days until expiry (0 when not active).
- `courses` is an array of **`{id, fullname}` objects** — the catalog reads each `id` to show
  "included in your subscription" coverage.
- A user holds at most **one active normal** subscription at a time.

### 3.6 `local_nit_subscriptions_get_subscription_payment_history`

Every subscription **payment** the user started (including failed/abandoned ones), newest first.
Source is the gateway transaction log, so records persist after a plan expires.

```
GET …&wsfunction=local_nit_subscriptions_get_subscription_payment_history&moodlewsrestformat=json
```
```json
[
  {
    "id": 91,
    "subscriptionid": 1,
    "name": "365-day",
    "order_id": "PAY-2026-00481523",
    "amount": 295.65,
    "currency": "EGP",
    "status": "completed",
    "payment_method": "card",
    "coupon_code": "WELCOME10",
    "timecreated": 1790400000
  }
]
```
- `status` = `pending` | `completed` | `failed` | `refunded` | `partially_refunded` | `voided`.
- `order_id` is the same reference returned by `create_subscription_checkout`.

---

## 4. Typical purchase flow

```
get_available_subscriptions              → show plan list
        │ user taps a plan
        ▼
preview_discount(item_type=subscription, item_id, coupon_code?)   → show final price
        │ user taps "Buy"
        ▼
create_subscription_checkout(subscriptionid, …)  → open checkout_url (WebView)
        │ user pays; gateway redirects back
        ▼
poll get_my_subscriptions  → status becomes "active"  → unlock the plan's courses
        │
        ▼
get_subscription_payment_history → "Payment history" screen
```

The plan's courses are granted as real Moodle enrolments once payment is confirmed, so they also
appear in the normal course-list web services (e.g. `core_enrol_get_users_courses`).

---

## 5. Field/format notes

- **Money** fields (`price`, `price_paid`, `amount`, `final`, …) are JSON **numbers**, currency **EGP**.
- **Time** fields (`timecreated`, `timeactivated`, `expires_at`, …) are **unix seconds**.
- `description` is HTML; `name` may contain `{mlang}` tags already resolved to the request language.
- Language: append `&lang=ar` (or `en`) as a normal Moodle WS param to localize names/descriptions.

---

## 6. Error handling

A failed WS call returns an exception object (HTTP 200 with an `exception` key):
```json
{ "exception": "moodle_exception", "errorcode": "invalidtoken",
  "message": "Invalid token - token not found" }
```
Handle these:

| Signal | Meaning | UI |
|--------|---------|----|
| `errorcode: invalidtoken` / HTML body | missing/expired token | go to login |
| `errorcode: accessexception` | WS/mobile service off, or function not in the token's service | see §8 |
| `errorcode: err_subnotfound` — `"Subscription not found"` | `subscriptionid` doesn't exist | refresh plan list |
| `errorcode: err_itemnotfound` — `"The requested item was not found."` | bad `item_id` in `preview_discount` | refresh list |
| `"You already have an active subscription"` | one-active-normal rule | show current plan, hide Buy |
| `"This subscription plan is not available"` | plan inactive | refresh list |
| `"The selected capacity is not available"` | bad B2B `seats` | re-pick a `seat_options` tier |
| `errorcode: noproviderfound` | no payment gateway enabled for the country/currency | server misconfig — contact backend |
| `coupon_error` non-empty (in `preview_discount`) | coupon rejected | show inline, keep offer price |

> The write endpoint `create_subscription_checkout` returns **clean, specific messages** — a bad id
> gives `"Subscription not found"` (not a raw DB error) and business-rule failures give their real
> reason (not a generic "Error occurred").

---

## 7. Migration from the old `local/academy/api.php`

| Old (2022 app) | New (this build) | Change |
|----------------|------------------|--------|
| `?function=get_available_coupons&token=` | `wsfunction=local_nit_commerce_get_available_coupons` | standard WS call |
| `?function=preview_discount` | `wsfunction=local_nit_commerce_preview_discount` | same params (`item_type`,`item_id`,`coupon_code`) |
| `?function=get_available_subscriptions` | `wsfunction=local_nit_subscriptions_get_available_subscriptions` | `price` now a number, not `"365.00"` |
| `?function=purchase_subscription` (assume-paid) | `wsfunction=local_nit_subscriptions_create_subscription_checkout` | **now a real gateway checkout**; poll `get_my_subscriptions` for activation |
| `?function=get_my_subscriptions` | `wsfunction=local_nit_subscriptions_get_my_subscriptions` | fields unchanged (money now numeric) |
| `?function=get_subscription_payment_history` | `wsfunction=local_nit_subscriptions_get_subscription_payment_history` | source is the gateway log; adds `currency`,`payment_method`,`coupon_code` |

Key differences:
1. **Envelope:** old returned `{status:"success",data:…}`; WS returns the data directly (errors carry
   an `exception` key).
2. **Auth:** old put `token` in the query; WS uses `wstoken` + `moodlewsrestformat=json`.
3. **No direct purchase:** `create_subscription_checkout` opens a payment page; the server grants the
   subscription only after the gateway confirms.

---

## 8. Prerequisites / capabilities

- **Site must have web services on** (a fresh/default site does not): *Site admin → Advanced features*
  → enable **web services**; *Server → Web services → Manage protocols* → enable **REST**; and enable
  **mobile web services** (or the `moodle_mobile_app` service) so mobile tokens work. Without these,
  every call returns `accessexception` (or an HTML redirect). CLI equivalent:
  ```bash
  php admin/cli/cfg.php --name=enablewebservices --set=1
  php admin/cli/cfg.php --name=webserviceprotocols --set=rest
  php admin/cli/cfg.php --name=enablemobilewebservice --set=1
  ```
- The token's service must expose these functions. They are registered against the **official mobile
  service** (`moodle_mobile_app`), so a standard mobile token includes them after the plugins are
  upgraded. If you use a **custom** external service, add each `wsfunction` to it under
  *Site admin → Server → Web services → External services*.
- `create_subscription_checkout` requires the `local/nit_subscriptions:subscribe` capability
  (granted to authenticated users by default).
- `get_subscription_payment_history` reads `local_payments` transactions; if the payments plugin is
  absent it returns `[]`.
- After deploying new plugin code, run the upgrade so the functions register:
  ```bash
  docker compose exec moodle php admin/cli/upgrade.php --non-interactive && \
  docker compose exec moodle php admin/cli/purge_caches.php
  ```
```
