# reno.sh API v1

Single JSON API consumed by the iOS app (`reno.sh-ios`), the SSH TUI (`tui/`) and anything else.
Base URL: `{APP_URL}/api/v1`. Local Docker: `http://localhost:8088/api/v1`.

## Conventions

- JSON only. Send `Accept: application/json`.
- IDs are strings (ULIDs for posts/photos/projects/categories/social/orders; numeric ids are serialized as strings too).
- Timestamps are ISO-8601 UTC strings (`2026-11-16T18:30:00Z`), nullable where noted.
- Money is integer minor units (`2500` = $25.00) plus a `currency` (ISO 4217).
- Single resources: `{ "data": {...} }`. Collections: `{ "data": [...], "meta": { "current_page", "last_page", "per_page", "total" } }`.
  Pagination via `?page=N&per_page=N` (max 100, default 20).
- Errors: Laravel standard. `401` unauthenticated, `403` forbidden, `404`, `422` `{ "message": "...", "errors": { "field": ["..."] } }`, `429` throttled.
- Auth: Sanctum personal access tokens, `Authorization: Bearer {token}`. All `/admin/*` routes require a token.

### Clarifications (aligned with the iOS client)

- Small taxonomy lists (`/admin/blog-categories`, `/admin/blog-series`, `/admin/photo-categories`,
  `/admin/social/accounts`) are `{ "data": [...] }` without `meta`.
- `PATCH` accepts explicit `null` to clear nullable fields (`excerpt`, `meta_*`, `blog_category_id`,
  `blog_series_id`, `featured_image`, `published_at`).
- `status: published` without `published_at` publishes now; `scheduled` requires `published_at`.
- Every write containing `share` creates a new SocialPost.
- Multipart booleans are `1`/`0`; arrays are repeated `field[]`.
- `/admin/media` accepts JPEG, PNG, WebP, GIF, HEIC/HEIF, MP4 and MOV (≤ 50 MB). Inline Markdown images
  should use the returned absolute `url`.
- `link_url` is appended to the text (after a blank line) unless the text already contains it.
- Character counting: X and Mastodon count each URL as 23; everything else counts graphemes.
- Social calendar `date` keys are bucketed in the site time zone (`APP_TIMEZONE`).
- A post is Markdown-editable when `is_markdown` is true (a single `markdown` block).

## Rich content ("blocks")

Posts, projects and pages store an ordered list of blocks:

```json
{ "type": "rich-text", "data": { "content": "<p>HTML…</p>", "markdown": "optional source" } }
```

| type            | data                                                                                  |
|-----------------|---------------------------------------------------------------------------------------|
| `rich-text`     | `content` (HTML), `markdown` (string, present when authored as Markdown)              |
| `markdown`      | `markdown` (string). Rendered server side; API responses also include `content` HTML |
| `heading`       | `title`, `subtitle?`, `level` (`h2`/`h3`)                                             |
| `image`         | `image` (url), `alt?`, `caption?`, `alignment` (`left`/`center`/`right`/`full`)       |
| `image-gallery` | `images: [{ image (url), alt?, caption? }]`, `columns?`                               |
| `two-columns`   | `left` (HTML), `right` (HTML)                                                         |
| `callout`       | `type` (`info`/`warning`/`success`/`danger`), `title?`, `content` (HTML)              |
| `code`          | `language`, `code`, `filename?`                                                       |
| `divider`       | `{}`                                                                                  |

API responses resolve image paths to absolute URLs. Every block-bearing resource also has
`text` (plain text, ~80 col friendly, for terminals) and `markdown` (full document as Markdown).

When **writing** posts through the admin API, clients may send blocks as above. For `image` blocks
send the `path` returned by `POST /admin/media` in `data.image`.

---

## Public endpoints (no auth)

### `GET /site`
```json
{ "data": {
  "name": "Reno Philibert", "title": "Full Stack Software Engineer", "tagline": "…",
  "location": "…", "email": "…", "url": "https://reno.sh",
  "socials": [{ "label": "GitHub", "url": "https://github.com/rjp2525" }],
  "ssh": { "host": "reno.sh", "port": 2222 },
  "sections": ["about", "projects", "blog", "gallery", "prints", "contact"]
} }
```

### `GET /about`
```json
{ "data": {
  "pages": [{ "section": "professional|personal|hobbies", "slug", "title", "icon", "text", "markdown" }],
  "experience": [{ "id", "title", "company", "location", "start": "2022-01-01", "end": null, "responsibilities": ["…"] }],
  "skills": [{ "id", "category", "name", "proficiency": "beginner|intermediate|advanced|expert", "started", "ended" }],
  "education": [{ "id", "school_name", "location", "degree", "minor", "started", "graduated", "description", "achievements": [], "projects": [], "extracurriculars": [] }]
} }
```

### `GET /posts` (published only)
Query: `category` (slug), `series` (slug), `tag`, `q` (search), `page`, `per_page`.
```json
{ "data": [PostSummary], "meta": {…} }
```
`PostSummary`:
```json
{ "id", "title", "slug", "excerpt", "url", "featured_image_url", "reading_time": 4,
  "published_at", "is_featured": false,
  "category": { "id", "name", "slug", "color" } | null,
  "series": { "id", "title", "slug", "order": 1 } | null,
  "tags": ["laravel", "vue"] }
```

### `GET /posts/{slug}`
`PostSummary` + `{ "blocks": [Block], "text": "…", "markdown": "…", "meta_title", "meta_description" }`

### `GET /blog/categories`
`{ "data": [{ "id", "name", "slug", "description", "color", "posts_count" }] }`

### `GET /projects`
Query: `type`. `{ "data": [{ "id", "title", "slug", "summary", "type", "status", "url", "github_url", "demo_url", "featured_image_url", "start_date", "end_date", "is_ongoing", "is_featured", "technologies": [{ "name", "slug", "color" }], "web_url" }] }`

### `GET /projects/{slug}`
Project + `{ "description_html", "text", "markdown", "blocks": [Block], "gallery": [url] }`. The description is block content, same shapes as posts (see "Rich content"); `description_html`/`text`/`markdown` are renderings of it.

### `GET /photos`
Query: `category` (slug), `favorites` (bool), `printable` (bool), `page`, `per_page`.
`Photo`:
```json
{ "id", "title", "slug", "description_html", "text", "web_url", "thumbnail_url", "og_image_url",
  "width", "height", "taken_at", "exif_summary", "camera_body", "lens", "focal_length", "aperture", "shutter_speed", "iso",
  "category": { "id", "name", "slug" } | null, "tags": [], "is_favorite", "prints_available": true, "url" }
```

### `GET /photos/{slug}`
`Photo` + `{ "print_options": [PrintOption] }`

`PrintOption` (only products that match the photo's aspect ratio and resolution):
```json
{ "product_id", "name": "Fine Art Print", "type": "fine_art|photo_print|canvas|metal|acrylic|framed",
  "size_label": "12×18 in", "width_in": 12, "height_in": 18, "price": 6500, "currency": "USD",
  "description": "…", "attributes": {} }
```

### `GET /photo-categories`
`{ "data": [{ "id", "name", "slug", "color", "photos_count" }] }`

### `POST /prints/checkout`
Body: `{ "photo_id", "product_id", "quantity": 1, "email?": "…" }`
→ `201 { "data": { "order_id", "number": "RP-000123", "checkout_url": "https://checkout.stripe.com/…" } }`
Clients open `checkout_url` (iOS: `SFSafariViewController`/`ASWebAuthenticationSession`).

### `POST /contact`
Body: `{ "name", "email", "subject?", "message" }` → `202`. Throttled 5/min.

---

## Auth

### `POST /auth/token`
Body: `{ "email", "password", "device_name": "Reno's iPhone" }`
Only emails in `ADMIN_EMAILS` can mint tokens.
→ `201 { "data": { "token": "1|abc…", "user": { "id", "name", "email" } } }` · `422` on bad credentials.

### `GET /auth/me` → `{ "data": { "id", "name", "email" } }`
### `DELETE /auth/token` → `204` (revokes the current token)

---

## Admin endpoints (Bearer token)

### Dashboard: `GET /admin/dashboard`
```json
{ "data": {
  "posts": { "published": 12, "drafts": 3, "scheduled": 1 },
  "photos": { "total": 140, "processing": 0, "failed": 0 },
  "social": { "scheduled": 4, "failed_last_7d": 0, "next": SocialPost | null },
  "orders": { "open": 2, "revenue_30d": 32000, "currency": "USD" },
  "recent_orders": [PrintOrder], "upcoming_social": [SocialPost]
} }
```

### Media: `POST /admin/media` (multipart)
Fields: `file` (image, ≤ 50 MB), `directory?` (`blog/images` default, `social`, `blog/featured`).
→ `201 { "data": { "path": "blog/images/abc.jpg", "url": "https://…/img/blog/images/abc.jpg", "width", "height", "mime", "size" } }`

### Posts
- `GET /admin/posts` query `status` (`draft|scheduled|published|archived`), `q`, `page` → `[AdminPost]` (summary fields + `status`, `updated_at`)
- `POST /admin/posts` → `201 AdminPost`
- `GET /admin/posts/{id}` → `AdminPost` (with `blocks`)
- `PATCH /admin/posts/{id}` → `AdminPost` (partial)
- `DELETE /admin/posts/{id}` → `204` (soft delete)

`AdminPost` write body:
```json
{ "title": "required", "slug?": "auto from title", "excerpt?", "blog_category_id?", "blog_series_id?", "series_order?",
  "tags?": ["…"], "status": "draft|scheduled|published|archived", "published_at?": "ISO (required when scheduled)",
  "is_featured?": false, "featured_image?": "path from /admin/media", "meta_title?", "meta_description?",
  "blocks?": [Block], "markdown?": "shortcut: replaces blocks with a single rich-text block rendered from Markdown",
  "share?": { "account_ids": ["…"], "body?": "custom text", "scheduled_at?": "ISO" } }
```
`share` creates a SocialPost announcing the post (scheduled at `published_at` if not given).
Read shape: write fields + `id`, `url`, `featured_image_url`, `reading_time`, `category`, `series`, `created_at`, `updated_at`.

### Taxonomy helpers (autocomplete)
- `GET /admin/blog-categories` → `[{ id, name, slug, color }]`
- `GET /admin/blog-series` → `[{ id, title, slug }]`
- `GET /admin/tags?type=posts|photos&q=` → `{ "data": ["laravel", "vue"] }`
- `GET /admin/photo-categories` → `[{ id, name, slug }]`

### Photos
- `GET /admin/photos` query `q`, `category`, `status` (`pending|processing|completed|failed`), `page` → `[AdminPhoto]`
- `POST /admin/photos` (multipart) fields: `file` (jpeg/png/webp/tiff/heic, ≤ 100 MB), `title`, `description?`,
  `photo_category_id?`, `tags[]?`, `is_published?` (default true), `is_favorite?`, `prints_enabled?` (default true)
  → `201 AdminPhoto` (`processing_status: "pending"`; EXIF extracted server side, variants generated by the queue)
- `GET /admin/photos/{id}`, `PATCH /admin/photos/{id}` (JSON, metadata only), `DELETE /admin/photos/{id}`

`AdminPhoto` = `Photo` + `{ "processing_status", "is_published", "prints_enabled", "sort_order", "created_at" }`

### Social
`SocialAccount`:
```json
{ "id", "provider": "facebook|instagram|linkedin|x|threads|bluesky|mastodon", "name", "username", "avatar_url",
  "is_active": true, "needs_reauth": false, "limits": { "max_chars": 500, "max_media": 10, "requires_media": false } }
```
`SocialPost`:
```json
{ "id", "body", "status": "draft|scheduled|publishing|published|partially_published|failed",
  "scheduled_at", "published_at", "link_url",
  "media": [{ "path", "url", "mime" }],
  "targets": [{ "id", "account": SocialAccount, "status": "pending|publishing|published|failed", "body_override",
               "provider_url", "error", "published_at" }],
  "source": { "type": "post|photo", "id", "title" } | null,
  "created_at", "updated_at" }
```
- `GET /admin/social/accounts` → `[SocialAccount]` (connecting accounts happens in the web admin; OAuth)
- `GET /admin/social/posts` query `status`, `from`, `to` (ISO, filters `scheduled_at`), `page` → `[SocialPost]`
- `GET /admin/social/calendar?from=&to=` → `{ "data": [{ "date": "2026-11-16", "posts": [SocialPost] }] }`
- `POST /admin/social/posts` body:
  ```json
  { "body": "required unless every target overrides", "account_ids": ["…"], "overrides?": { "{account_id}": "text" },
    "media?": ["path from /admin/media"], "link_url?", "scheduled_at?": "ISO; omit + publish_now=true to post immediately",
    "publish_now?": false, "draft?": false }
  ```
  → `201 SocialPost`. Validation enforces each account's `limits`.
- `GET /admin/social/posts/{id}`, `PATCH /admin/social/posts/{id}` (only while `draft|scheduled|failed`), `DELETE` → `204`
- `POST /admin/social/posts/{id}/publish` → `202 SocialPost` (publish now / retry failed targets)

### About (pages, work history, education, skills)
All routes: Bearer token (Sanctum, ability `admin`), JSON. Same envelope as the existing admin API:
single resource `{"data": {...}}`, lists `{"data": [...]}` (NOT paginated — these sets are small).
IDs are strings. Dates are `YYYY-MM-DD` strings (or null). Validation errors: 422 `{"message", "errors": {field: [msg]}}`.
Store returns 201, update returns 200 with the full resource, delete returns 204.
PATCH is partial: only send fields you change.

#### About pages — `content-pages`
Sections: `personal`, `hobbies` (the About → Personal / Hobbies tabs on the site).

GET    /admin/content-pages?section=personal     list, ordered by section then order
POST   /admin/content-pages                       create
GET    /admin/content-pages/{id}
PATCH  /admin/content-pages/{id}
DELETE /admin/content-pages/{id}
POST   /admin/content-pages/reorder   body {"ids": ["3","1","2"]}  → sets order = index within the given ids; returns {"data": [...]} (all pages, ordered)

Resource:
{
  "id": "1", "section": "personal", "slug": "biography", "title": "Biography",
  "icon": "person" | null, "order": 0, "is_published": true,
  "markdown": "…",            // content as Markdown (same converter as posts)
  "is_markdown": true,        // false if the blocks include things Markdown can't round-trip (gallery, two-columns…); editing markdown then replaces them
  "blocks": [...],            // raw blocks, same shape as posts
  "url": "https://reno.sh/about/personal/biography",
  "updated_at": "2026-11-16T22:00:00+00:00"
}
Input: title (required on create, ≤255), slug (optional, generated from title; unique), section (required on create, enum),
icon (nullable ≤64), order (int ≥0, default = end of section), is_published (bool, default false),
markdown (string ≤200000) — or blocks (array, same rules as posts). Send one of them.

#### Work history — `experiences`
GET    /admin/experiences          list (current roles first, then by end date desc)
POST   /admin/experiences
GET    /admin/experiences/{id}
PATCH  /admin/experiences/{id}
DELETE /admin/experiences/{id}

Resource:
{ "id": "1", "title": "Lead Software Developer", "company": "Clean Program", "location": "Remote" | null,
  "start": "2021-06-22", "end": "2023-06-09" | null (null = current role),
  "responsibilities": ["…", "…"], "tenure": "2 years", "updated_at": "…" }
Input: title (required, ≤255), company (required, ≤255), location (nullable ≤255), start (required date),
end (nullable date, ≥ start), responsibilities (array of non-empty strings ≤2000, max 50 items).

#### Education — `education`
GET/POST /admin/education, GET/PATCH/DELETE /admin/education/{id}

Resource:
{ "id": "1", "school_name": "…", "location": "…" | null, "degree": "…", "minor": "…" | null,
  "started": "2015-09-02" | null, "graduated": "2019-05-12" | null, "description": "…" | null,
  "achievements": ["…"], "projects": ["…"], "extracurriculars": ["…"], "updated_at": "…" }
Input: school_name (required ≤255), degree (required ≤255), location/minor (nullable ≤255),
started/graduated (nullable dates, graduated ≥ started), description (nullable ≤5000),
achievements/projects/extracurriculars (arrays of non-empty strings ≤1000, max 50 each).

#### Skills — `skills`
GET    /admin/skills       list ordered by category, name. Response adds
       "meta": {"categories": ["Backend", "Frontend", …] (existing distinct categories, for autocomplete),
                "proficiencies": [{"value": "beginner", "label": "Beginner"}, …intermediate, proficient, expert]}
POST/GET/PATCH/DELETE as above (/admin/skills/{id})

Resource:
{ "id": "1", "category": "Backend", "name": "Laravel", "proficiency": "expert", "level": "Expert",
  "started": "2014-01-01", "ended": null, "experience": "12 years", "updated_at": "…" }
Input: category (required ≤100), name (required ≤100), proficiency (required enum), started (required date),
ended (nullable date ≥ started).

### Print orders
`PrintOrder`:
```json
{ "id", "number", "status": "pending_payment|paid|submitted|in_production|shipped|completed|cancelled|failed",
  "email", "name", "currency", "subtotal", "shipping", "tax", "total",
  "items": [{ "photo": { "id", "title", "thumbnail_url" }, "product": { "id", "name", "size_label" }, "quantity", "unit_price" }],
  "shipping_address": { "line1", "line2", "city", "state", "postal_code", "country" } | null,
  "tracking": [{ "carrier", "number", "url" }], "prodigi_order_id", "error",
  "paid_at", "submitted_at", "shipped_at", "created_at" }
```
- `GET /admin/orders` query `status`, `page` → `[PrintOrder]`
- `GET /admin/orders/{id}` → `PrintOrder`
