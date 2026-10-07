# NIT Academy — Mobile API: updates since `MOBILE_API_V4.md`

This file lists **only** what changed after [`MOBILE_API_V4.md`](MOBILE_API_V4.md). Read it on top of
[`MOBILE_API.md`](MOBILE_API.md), `MOBILE_API_V2.md`, `MOBILE_API_V3.md` and `MOBILE_API_V4.md`. Everything not listed here is
exactly as described there.

**Summary:** a new **Static Pages & Articles (CMS)** system (§1) providing public and embedded webview endpoints for custom pages (About Us, Terms, Privacy, FAQ, Contact) and articles; an overview of the new **Reports system** (§2, web only, no mobile endpoints); and **app-visible behaviour updates** (§3) regarding course re-purchase checks (`is_purchased`), video progress reaching 100%, and buyer-language payment confirmations.

| # | Area | Affects | App change needed |
|---|---|---|---|
| 1 | Static pages & CMS | New endpoint `/local/nit_pages/api.php`: `get_pages`, `get_page`, `get_articles`, `get_article` | New screens or webviews for pages & articles |
| 2 | Legal pages fallback | `/local/multitopics/legal.php` now serves content from `local_nit_pages` | No: existing webview links continue working |
| 3 | Reports | 12 administrative reports under `/local/nit_reports/` | No: web-only, no mobile integration |
| 4 | Course purchase status (`is_purchased`) | `is_purchased` now checks both payment AND active enrolment | App receives accurate purchase state when enrolment expires |
| 5 | Video progress (100%) | `local/nit_videoprogress/api.php` percentage now reaches 100% (was 99%) | No: progress displays 100% correctly |
| 6 | Payment notifications | Confirmation messages now resolve `{mlang}` to buyer's language | No: already plain text in user language |

---

## 1. Static Pages & Articles (CMS) — `/local/nit_pages/api.php` (new)

The platform provides a content management system (`local_nit_pages`) where admins create and manage static pages and articles.

**Authentication & Tokens:**
- Any valid token works, including the **shared pre-login token** (`getsettings.php` → `admin_token` / `user_token`).
- Anonymous visitors using the shared token can browse all published public pages and articles.
- Pages marked as "logged-in users only" require an authenticated student/user token (returns `authrequired` / HTTP 401 for visitors).
- Draft pages/articles are visible only to site administrators.

**Languages:**
- The optional `lang=ar|en` query parameter selects the returned title, summary, and content language (default is caller profile / site language).
- HTML responses have `{mlang}` tags pre-resolved so the app never receives raw `{mlang}` strings.

---

### `get_pages` — GET
Lists published static pages accessible to the current caller.

#### Parameters
| param | type | req | description |
|---|---|---|---|
| token | string | no | web-service token or shared token |
| lang | string | no | `ar` or `en` (defaults to current language) |

#### Response
```json
{
  "status": "success",
  "data": {
    "pages": [
      {
        "id": 1,
        "slug": "عن-المنصة",
        "slug_ar": "عن-المنصة",
        "slug_en": "about",
        "title": "عن المنصة",
        "title_ar": "عن المنصة",
        "title_en": "About Us",
        "is_default": true,
        "default_key": "about",
        "updated_at": 1791369600,
        "embedded_url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/page.php?p=%D8%B9%D9%86-%D8%A7%D9%84%D9%85%D9%86%D8%B5%D8%A9&embedded=1&lang=ar",
        "url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/page.php?p=%D8%B9%D9%86-%D8%A7%D9%84%D9%85%D9%86%D8%B5%D8%A9&lang=ar"
      },
      {
        "id": 2,
        "slug": "الشروط-والأحكام",
        "slug_ar": "الشروط-والأحكام",
        "slug_en": "terms",
        "title": "الشروط والأحكام",
        "title_ar": "الشروط والأحكام",
        "title_en": "Terms & Conditions",
        "is_default": true,
        "default_key": "terms",
        "updated_at": 1791369600,
        "embedded_url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/page.php?p=%D8%A7%D9%84%D8%B4%D8%B1%D9%88%D8%B7-%D9%88%D8%A7%D9%84%D8%A3%D8%AD%D9%83%D8%A7%D9%85&embedded=1&lang=ar",
        "url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/page.php?p=%D8%A7%D9%84%D8%B4%D8%B1%D9%88%D8%B7-%D9%88%D8%A7%D9%84%D8%A3%D8%AD%D9%83%D8%A7%D9%85&lang=ar"
      }
    ]
  }
}
```

Use `embedded_url` directly inside a mobile Webview for a clean, chrome-less view of the page.

---

### `get_page` — GET
Fetches details and pre-rendered HTML content of a single static page.

#### Parameters
| param | type | req | description |
|---|---|---|---|
| slug / p | string | one of these | page slug (Arabic or English) |
| id | int | one of these | page record ID |
| key | string | one of these | default page key: `about`, `contact`, `terms`, `privacy`, `faq`, `articles` |
| token | string | no | user token or shared token |
| lang | string | no | `ar` or `en` |

#### Response
```json
{
  "status": "success",
  "data": {
    "id": 3,
    "slug": "سياسة-الخصوصية",
    "slug_ar": "سياسة-الخصوصية",
    "slug_en": "privacy",
    "title": "سياسة الخصوصية",
    "title_ar": "سياسة الخصوصية",
    "title_en": "Privacy Policy",
    "seo_desc": "سياسة الخصوصية وحماية بيانات المستخدمين في المنصة",
    "share_image": "https://example.com/og-privacy.jpg",
    "is_default": true,
    "default_key": "privacy",
    "updated_at": 1791369600,
    "embedded_url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/page.php?p=privacy&embedded=1&lang=ar",
    "url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/page.php?p=privacy&lang=ar",
    "content_html": "<section class=\"nit-section-content\"><h2>جمع المعلومات</h2><p>نقوم بجمع المعلومات اللازمة لتقديم الخدمات التعليمية...</p></section>"
  }
}
```

Errors: `notfound`, `authrequired` (HTTP 401, when page requires login), `nopermission`.

---

### `get_articles` — GET
Search and paginate published articles (newest first).

#### Parameters
| param | type | req | description |
|---|---|---|---|
| q / search | string | no | search keyword across titles and summaries |
| page | int | no | zero-based page index (default 0) |
| perpage | int | no | items per page (default 9, max 50) |
| token | string | no | user token or shared token |
| lang | string | no | `ar` or `en` |

#### Response
```json
{
  "status": "success",
  "data": {
    "articles": [
      {
        "id": 5,
        "slug": "how-to-study-effectively",
        "title": "كيف تذاكر بفعالية للامتحانات الثانوية",
        "title_ar": "كيف تذاكر بفعالية للامتحانات الثانوية",
        "title_en": "How to Study Effectively for Exams",
        "summary": "نصائح وإرشادات لتنظيم وقت المذاكرة وزيادة التركيز وتحقيق أعلى الدرجات.",
        "summary_ar": "نصائح وإرشادات لتنظيم وقت المذاكرة وزيادة التركيز وتحقيق أعلى الدرجات.",
        "summary_en": "Tips and advice for organizing study time, improving focus, and high scores.",
        "cover_image": "https://academy2026.nitg-eg.com/bassthalk/pluginfile.php/1/local_nit_pages/article_cover/5/study.jpg",
        "author_name": "فريق المنصة",
        "status": "published",
        "published_at": 1791285548,
        "url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/article.php?a=how-to-study-effectively&lang=ar"
      }
    ],
    "total": 14,
    "page": 0,
    "perpage": 9,
    "pages": 2
  }
}
```

---

### `get_article` — GET
Fetches the full details and body content of a single article.

#### Parameters
| param | type | req | description |
|---|---|---|---|
| slug / a | string | one of these | article unique slug |
| id | int | one of these | article ID |
| token | string | no | user token or shared token |
| lang | string | no | `ar` or `en` |

#### Response
```json
{
  "status": "success",
  "data": {
    "id": 5,
    "slug": "how-to-study-effectively",
    "title": "كيف تذاكر بفعالية للامتحانات الثانوية",
    "title_ar": "كيف تذاكر بفعالية للامتحانات الثانوية",
    "title_en": "How to Study Effectively for Exams",
    "summary": "نصائح وإرشادات لتنظيم وقت المذاكرة وزيادة التركيز وتحقيق أعلى الدرجات.",
    "summary_ar": "نصائح وإرشادات لتنظيم وقت المذاكرة وزيادة التركيز وتحقيق أعلى الدرجات.",
    "summary_en": "Tips and advice for organizing study time, improving focus, and high scores.",
    "body_html": "<p>البدء مبكراً بتقسيم المواد إلى أجزاء صغيرة يساعد على استيعاب المعلومات...</p>",
    "cover_image": "https://academy2026.nitg-eg.com/bassthalk/pluginfile.php/1/local_nit_pages/article_cover/5/study.jpg",
    "author_name": "أحمد محمود",
    "status": "published",
    "published_at": 1791285548,
    "url": "https://academy2026.nitg-eg.com/bassthalk/local/nit_pages/article.php?a=how-to-study-effectively&lang=ar"
  }
}
```

Errors: `notfound`.

---

## 2. Platform Reports — `/local/nit_reports/` (Web only)

A complete management reporting suite (`local_nit_reports`) has been added for administrators, managers, and teachers.

> **Note for Mobile App Developers:**
> There are **no mobile API endpoints** to integrate for this feature. The reports are web-only screens intended for administrative and teacher dashboards on desktop browsers.

- **URL:** `/local/nit_reports/index.php?report=<key>`
- **Export formats:** PDF, Excel, and CSV via `/local/nit_reports/export.php?report=<key>&format=pdf|excel|csv`.
- **12 Available Reports:**
  1. `students` — Student directory, enrolments, total spent, and status.
  2. `teachers` — Teacher directory, courses taught, student count, and earnings.
  3. `courses` — Course catalogue overview, enrolments, completion rate, and revenue.
  4. `student_results` — Quiz grades and exam performance breakdowns.
  5. `videos` — Video watch completion rates and engagement metrics.
  6. `sales` — Course purchase transactions and payment status.
  7. `subscriptions` — Plan subscriptions, recurring revenue, and active periods.
  8. `packages` — Flex lesson packages purchased and remaining credits.
  9. `lessons` — Private 1-on-1 and group lesson booking logs.
  10. `discounts` — Promotional discount usage and savings breakdown.
  11. `codes` — Coupon voucher codes redemption stats.
  12. `teacher_dues` — Teacher earnings and payout ledger ("Teacher earnings & payouts").
- **Access scope:** Site admins access all reports; scoped category/course managers see only their assigned courses; teachers see only teaching reports for courses they teach.
- The legacy revenue URL `/local/payments/report.php` automatically redirects to the sales report.
- `/local/nit_finance/manage_withdrawals.php` is now titled "Teacher withdrawal requests" and focuses strictly on payouts.

---

## 3. App-Visible Behaviour Changes

The following fixes and behavioural improvements are now active across existing web services:

### 3.1 Course Re-Purchase Check (`is_purchased`)
- **Previous behaviour:** `\local_payments\price_resolver::is_purchased()` returned `true` if a student had *ever* paid for a course, even if their enrolment subsequently expired or was unenrolled by an administrator. This prevented students from buying access again.
- **Updated behaviour:** `price_resolver::is_purchased()` now requires **both** a completed payment transaction **and** an active enrolment. If the enrolment ended or was revoked:
  - `is_purchased` evaluates to `false`.
  - The course pricing is shown again in course cards (`price_resolver::card_context()`).
  - Web service functions `get_courses_with_pricing`, `get_course_access`, and `get_course_price` (under `local/payments/classes/external/`) reflect `is_purchased: false`, allowing the student to complete a new checkout.

### 3.2 Video Completion Percentage (100% Watch State)
- **Previous behaviour:** When a student watched a short video or finished a video to the very last second, `local/nit_videoprogress/api.php` often reported `99%` instead of `100%` because the initial slice at playback start was not counted.
- **Updated behaviour:** `js/tracker.js` now records the initial segment upon playback start. A database upgrade step (`2026100700`) repaired previously saved rows stuck at 99%. Calls to `get_progress` now correctly return `100` for completed videos.

### 3.3 Bilingual Payment Confirmation Notifications
- Purchase confirmation messages, invoice notifications, and course titles embedded within them now resolve `{mlang}` tags according to the buyer's language, ensuring push notifications and emails arrive as pure plain text without raw `{mlang}` tokens.
